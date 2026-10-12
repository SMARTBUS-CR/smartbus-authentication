<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class PassengerAccountDeletionService
{
    public function canDelete(User $user): bool
    {
        $assignments = $this->roleAssignments($user);

        $hasPassengerRole = (clone $assignments)
            ->where('roles.name', UserRole::Passenger->value)
            ->where('roles.guard_name', 'web')
            ->exists();

        $hasOtherRoles = (clone $assignments)
            ->where(function (Builder $query): void {
                $query
                    ->where('roles.name', '!=', UserRole::Passenger->value)
                    ->orWhere('roles.guard_name', '!=', 'web');
            })
            ->exists();

        return $hasPassengerRole && ! $hasOtherRoles;
    }

    public function delete(User $user, #[SensitiveParameter] string $currentPassword): void
    {
        $user->getConnection()->transaction(function () use ($user, $currentPassword): void {
            $storedUser = $user->newQuery()
                ->lockForUpdate()
                ->findOrFail($user->getKey());

            abort_unless($this->canDelete($storedUser), 403);

            if (! Hash::check($currentPassword, $storedUser->getAuthPassword())) {
                throw ValidationException::withMessages([
                    'current_password' => __('validation.current_password'),
                ]);
            }

            $storedUser->tokens()->delete();
            $storedUser->delete();
        });
    }

    private function roleAssignments(User $user): Builder
    {
        $tables = config('permission.table_names');
        $modelKey = config('permission.column_names.model_morph_key');

        return $user->getConnection()
            ->table($tables['model_has_roles'].' as assignments')
            ->join(
                $tables['roles'].' as roles',
                'roles.id',
                '=',
                'assignments.role_id',
            )
            ->where('assignments.'.$modelKey, $user->getKey())
            ->where('assignments.model_type', $user->getMorphClass());
    }
}
