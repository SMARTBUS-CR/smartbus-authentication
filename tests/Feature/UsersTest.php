<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);
});

describe('User Management and Authorization', function () {
    it('lists users for an authorized administrator with filtering and pagination', function () {
        $admin = User::factory()->withRole(UserRole::SuperAdmin)->create();
        User::factory()->create(['name' => 'Ana Smith', 'email' => 'ana@example.com']);
        User::factory()->create(['name' => 'Bob Jones', 'email' => 'bob@example.com']);
        Sanctum::actingAs($admin);

        $this->getJson(route('users.index', ['filter' => ['name' => 'Ana'], 'page' => ['size' => 1]]))
            ->assertSuccessful()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('data.0.attributes.name', 'Ana Smith');
    });

    it('rejects administrative access without a token or permission', function () {
        $this->getJson(route('users.index'))
            ->assertUnauthorized()
            ->assertJsonPath('errors.0.status', '401')
            ->assertJsonPath('errors.0.title', 'Unauthorized');

        Sanctum::actingAs(User::factory()->withRole(UserRole::Passenger)->create());

        $this->getJson(route('users.index'))
            ->assertForbidden()
            ->assertJsonPath('errors.0.status', '403')
            ->assertJsonPath('errors.0.title', 'Forbidden');
    });

    it('creates and updates a user without changing the password optionally', function () {
        $admin = User::factory()->withRole(UserRole::SuperAdmin)->create();
        Sanctum::actingAs($admin);

        $this->postJson(route('users.store'), [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'Strong!Password123',
            'password_confirmation' => 'Strong!Password123',
            'roles' => [UserRole::Driver->value],
        ])->assertCreated();

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->patchJson(route('users.update', ['user' => $user]), ['name' => 'Updated User'])
            ->assertSuccessful()
            ->assertJsonPath('data.attributes.name', 'Updated User');
    });

    it('shows a user by its route-bound identifier', function () {
        $admin = User::factory()->withRole(UserRole::SuperAdmin)->create();
        $user = User::factory()->withRole(UserRole::Driver)->create([
            'name' => 'Driver Detail',
            'email' => 'driver-detail@example.com',
        ]);
        Sanctum::actingAs($admin);

        $this->getJson(route('users.show', ['user' => $user]))
            ->assertSuccessful()
            ->assertJsonPath('data.id', (string) $user->getKey())
            ->assertJsonPath('data.attributes.name', 'Driver Detail');

        expect($user->fresh()->hasRole(UserRole::Driver))->toBeTrue();
    });

    it('changes a password and invalidates the user tokens', function () {
        $admin = User::factory()->withRole(UserRole::SuperAdmin)->create();
        $user = User::factory()->create(['password' => 'Old!Password123']);
        $user->createToken('existing-device');
        Sanctum::actingAs($admin);

        $this->patchJson(route('users.update', ['user' => $user]), [
            'password' => 'New!Password123',
            'password_confirmation' => 'New!Password123',
        ])->assertSuccessful();

        expect($user->fresh()->tokens()->count())->toBe(0)
            ->and(Hash::check('New!Password123', $user->fresh()->password))->toBeTrue();
    });

    it('rejects duplicate emails and unknown roles', function () {
        $admin = User::factory()->withRole(UserRole::SuperAdmin)->create();
        User::factory()->create(['email' => 'taken@example.com']);
        Sanctum::actingAs($admin);

        $this->postJson(route('users.store'), [
            'name' => 'Duplicate',
            'email' => 'taken@example.com',
            'password' => 'Strong!Password123',
            'password_confirmation' => 'Strong!Password123',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/email');

        $user = User::factory()->create();
        $this->postJson(route('users.roles.store', ['user' => $user, 'role' => 'not-a-role']))
            ->assertUnprocessable();
    });

    it('assigns roles idempotently and returns inherited and direct permissions', function () {
        $admin = User::factory()->withRole(UserRole::SuperAdmin)->create();
        $user = User::factory()->create();
        Sanctum::actingAs($admin);

        $this->postJson(route('users.roles.store', ['user' => $user, 'role' => UserRole::Driver->value]))
            ->assertSuccessful();
        $this->postJson(route('users.roles.store', ['user' => $user, 'role' => UserRole::Driver->value]))
            ->assertSuccessful();
        expect($user->fresh()->roles)->toHaveCount(1);

        $permission = Permission::findByName('users.view', 'web');
        $permission->assignRole(UserRole::Driver->value);
        $user->refresh();

        $this->getJson(route('users.permissions.index', ['user' => $user]))
            ->assertSuccessful()
            ->assertJsonPath('data.attributes.effective.0', 'users.view');

        $this->postJson(route('users.permissions.store', ['user' => $user, 'permission' => 'users.update']))
            ->assertSuccessful();
        $this->deleteJson(route('users.permissions.destroy', ['user' => $user, 'permission' => 'users.update']))
            ->assertSuccessful();
    });

    it('does not allow deleting the last administrator', function () {
        $admin = User::factory()->withRole(UserRole::SuperAdmin)->create();
        $target = User::factory()->withRole(UserRole::CompanyAdmin)->create();
        Sanctum::actingAs($admin);

        $this->deleteJson(route('users.destroy', ['user' => $target]))->assertSuccessful();
        $this->deleteJson(route('users.destroy', ['user' => $admin]))->assertConflict();
    });
});

describe('Roles and Permissions Controllers', function () {
    it('lists the valid roles and permissions for an authorized administrator', function () {
        $admin = User::factory()->withRole(UserRole::SuperAdmin)->create();
        Sanctum::actingAs($admin);

        $this->getJson(route('roles.index'))
            ->assertSuccessful()
            ->assertJsonPath('data.0.attributes.value', UserRole::CompanyAdmin->value)
            ->assertJsonPath('data.3.attributes.value', UserRole::SuperAdmin->value);

        $this->getJson(route('permissions.index'))
            ->assertSuccessful()
            ->assertJsonFragment(['name' => 'users.view'])
            ->assertJsonFragment(['name' => 'users.assign-permissions']);
    });

    it('rejects catalog access for users without the required permission', function () {
        Sanctum::actingAs(User::factory()->withRole(UserRole::Passenger)->create());

        $this->getJson(route('roles.index'))->assertForbidden();
        $this->getJson(route('permissions.index'))->assertForbidden();
    });
});

describe('UserRoles Controller', function () {
    it('lists, replaces, assigns, and revokes user roles', function () {
        $admin = User::factory()->withRole(UserRole::SuperAdmin)->create();
        $user = User::factory()->create();
        Sanctum::actingAs($admin);

        $this->getJson(route('users.roles.index', ['user' => $user]))
            ->assertSuccessful()
            ->assertJsonCount(0, 'data');

        $this->putJson(route('users.roles.update', ['user' => $user]), [
            'roles' => [UserRole::Driver->value, UserRole::Passenger->value],
        ])->assertSuccessful();

        expect($user->fresh()->getRoleNames()->sort()->values()->all())
            ->toBe([UserRole::Driver->value, UserRole::Passenger->value]);

        $this->postJson(route('users.roles.store', ['user' => $user, 'role' => UserRole::CompanyAdmin->value]))
            ->assertSuccessful();
        $this->deleteJson(route('users.roles.destroy', ['user' => $user, 'role' => UserRole::Driver->value]))
            ->assertSuccessful();

        expect($user->fresh()->hasRole(UserRole::Driver))->toBeFalse()
            ->and($user->fresh()->hasRole(UserRole::CompanyAdmin))->toBeTrue();
    });

    it('rejects an unknown role and protects self administrator revocation', function () {
        $admin = User::factory()->withRole(UserRole::SuperAdmin)->create();
        Sanctum::actingAs($admin);

        $this->postJson(route('users.roles.store', ['user' => $admin, 'role' => 'unknown']))
            ->assertUnprocessable();
        $this->deleteJson(route('users.roles.destroy', ['user' => $admin, 'role' => UserRole::SuperAdmin->value]))
            ->assertConflict();
    });
});

describe('UserPermissions Controller', function () {
    it('returns direct and effective permissions and synchronizes direct permissions', function () {
        $admin = User::factory()->withRole(UserRole::SuperAdmin)->create();
        $user = User::factory()->withRole(UserRole::Driver)->create();
        $rolePermission = Permission::findByName('users.view', 'web');
        $rolePermission->assignRole(UserRole::Driver->value);
        Sanctum::actingAs($admin);

        $this->getJson(route('users.permissions.index', ['user' => $user]))
            ->assertSuccessful()
            ->assertJsonPath('data.attributes.direct', [])
            ->assertJsonPath('data.attributes.effective.0', 'users.view');

        $this->putJson(route('users.permissions.update', ['user' => $user]), [
            'permissions' => ['users.update'],
        ])->assertSuccessful();

        expect($user->fresh()->getDirectPermissions()->pluck('name')->all())->toBe(['users.update']);
    });

    it('assigns permissions idempotently and revokes direct permissions', function () {
        $admin = User::factory()->withRole(UserRole::SuperAdmin)->create();
        $user = User::factory()->create();
        Sanctum::actingAs($admin);

        $route = route('users.permissions.store', ['user' => $user, 'permission' => 'users.update']);
        $this->postJson($route)->assertSuccessful();
        $this->postJson($route)->assertSuccessful();
        expect($user->fresh()->getDirectPermissions())->toHaveCount(1);

        $this->deleteJson(route('users.permissions.destroy', ['user' => $user, 'permission' => 'users.update']))
            ->assertSuccessful();
        expect($user->fresh()->getDirectPermissions())->toBeEmpty();
    });

    it('rejects permission management without authorization', function () {
        $user = User::factory()->create();
        Sanctum::actingAs(User::factory()->withRole(UserRole::Passenger)->create());

        $this->getJson(route('users.permissions.index', ['user' => $user]))->assertForbidden();
        $this->putJson(route('users.permissions.update', ['user' => $user]), ['permissions' => []])->assertForbidden();
    });
});
