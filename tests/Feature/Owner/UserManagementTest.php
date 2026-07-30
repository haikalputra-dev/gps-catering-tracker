<?php

declare(strict_types=1);

namespace Tests\Feature\Owner;

use App\Domain\Identity\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsOwner(): User
    {
        $owner = User::factory()->owner()->create();
        $this->actingAs($owner);

        return $owner;
    }

    public function test_owner_can_list_staff_and_courier_accounts(): void
    {
        $this->actingAsOwner();
        User::factory()->staff()->create(['name' => 'Staff One']);
        User::factory()->courier()->create(['name' => 'Courier One']);

        $response = $this->get('/owner/users');

        $response->assertOk();
        $response->assertSee('Staff One');
        $response->assertSee('Courier One');
    }

    public function test_owner_list_does_not_display_owner_accounts(): void
    {
        $this->actingAsOwner();
        User::factory()->owner()->create(['name' => 'Hidden Owner']);
        User::factory()->staff()->create(['name' => 'Visible Staff']);

        $response = $this->get('/owner/users');

        $response->assertOk();
        $response->assertSee('Visible Staff');
        $response->assertDontSee('Hidden Owner');
    }

    public function test_owner_can_create_staff_account(): void
    {
        $this->actingAsOwner();

        $response = $this->post('/owner/users', [
            'name' => 'New Staff',
            'email' => 'staff@example.test',
            'phone' => '0812-3456-7890',
            'role' => 'staff',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('owner.users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'staff@example.test',
            'role' => 'staff',
            'is_active' => 1,
        ]);
    }

    public function test_owner_can_create_courier_account(): void
    {
        $this->actingAsOwner();

        $this->post('/owner/users', [
            'name' => 'New Courier',
            'email' => 'courier@example.test',
            'role' => 'courier',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'is_active' => '1',
        ])->assertRedirect(route('owner.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'courier@example.test',
            'role' => 'courier',
        ]);
    }

    public function test_owner_cannot_submit_role_owner(): void
    {
        $this->actingAsOwner();

        $response = $this->from('/owner/users/create')->post('/owner/users', [
            'name' => 'Attempt Owner',
            'email' => 'newowner@example.test',
            'role' => 'owner',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'newowner@example.test']);
    }

    public function test_staff_cannot_access_owner_user_management(): void
    {
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->get('/owner/users')->assertForbidden();
        $this->actingAs($staff)->get('/owner/users/create')->assertForbidden();
    }

    public function test_courier_cannot_access_owner_user_management(): void
    {
        $courier = User::factory()->courier()->create();
        $this->actingAs($courier)->get('/owner/users')->assertForbidden();
    }
}
