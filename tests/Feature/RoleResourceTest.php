<?php

use App\Enums\UserRoles;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

pest()->use(RefreshDatabase::class);

describe('RoleResource & UserResource (JSON:API)', function () {

    it('returns the role resource attributes in JSON:API format', function () {
        $roleModel = new Role(['name' => UserRoles::SUPER_ADMIN->value]);
        $roleModel->id = 1;

        $resource = RoleResource::make($roleModel);
        $resolved = $resource->resolve();

        expect((array) $resolved['data']['attributes'])->toBe([
            'value' => 'super-admin',
            'label' => 'Super Admin',
        ]);
    });

    it('returns transformed roles in the user resource relationships', function () {
        Role::create(['name' => UserRoles::SUPER_ADMIN->value, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole(UserRoles::SUPER_ADMIN);

        // Cargamos ansiosamente la relación 'roles' para que el recurso la procese
        $user->load('roles');
        $response = UserResource::make($user)
            ->additional(['included' => RoleResource::collection($user->roles)])
            ->response()
            ->getData(true);

        $response = (array) $response;
        expect($response['data']['type'])->toBe('users')
            ->and($response['data']['attributes']['email'])->toBe($user->email)
            ->and($response['included'][0]['type'])->toBe('roles')
            ->and($response['included'][0]['attributes'])->toBe([
                    'value' => 'super-admin',
                    'label' => 'Super Admin',
                ]);
    });
});