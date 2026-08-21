<?php

use App\Enums\UserRoles;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

pest()->use(RefreshDatabase::class);

describe("RoleResource", function () {

    it('returns the role value and label', function () {
        $resource = RoleResource::make(new Role(['name' => UserRoles::SUPER_ADMIN->value]));

        expect($resource->resolve())->toBe([
            'value' => 'super-admin',
            'label' => 'Super Admin',
        ]);
    });

    it('returns transformed roles in the user resource', function () {
        Role::create(['name' => UserRoles::SUPER_ADMIN->value, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole(UserRoles::SUPER_ADMIN);

        expect(UserResource::make($user)->resolve()['roles']->resolve())->toBe([
            [
                'value' => 'super-admin',
                'label' => 'Super Admin',
            ],
        ]);
    });
});
