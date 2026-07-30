<?php

declare(strict_types=1);

namespace Tests\Feature\Owner;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsOwner(): User
    {
        $owner = User::factory()->owner()->create();
        $this->actingAs($owner);

        return $owner;
    }

    public function test_owner_can_update_staff_account(): void
    {
        $this->actingAsOwner();
        $staff = User::factory()->staff()->create([
            'name' => 'Old Name',
            'email' => 'old@example.test',
        ]);

        $this->put("/owner/users/{$staff->id}", [
            'name' => 'New Name',
            'email' => 'new@example.test',
            'phone' => '',
            'role' => 'staff',
            'password' => '',
            'password_confirmation' => '',
            'is_active' => '1',
        ])->assertRedirect(route('owner.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'name' => 'New Name',
            'email' => 'new@example.test',
        ]);
    }

    public function test_owner_can_switch_staff_and_courier(): void
    {
        $this->actingAsOwner();
        $staff = User::factory()->staff()->create();
        $courier = User::factory()->courier()->create();

        $this->put("/owner/users/{$staff->id}", $this->baseUpdate($staff, ['role' => 'courier']));
        $this->put("/owner/users/{$courier->id}", $this->baseUpdate($courier, ['role' => 'staff']));

        $this->assertDatabaseHas('users', ['id' => $staff->id, 'role' => 'courier']);
        $this->assertDatabaseHas('users', ['id' => $courier->id, 'role' => 'staff']);
    }

    public function test_owner_can_deactivate_staff_or_courier(): void
    {
        $this->actingAsOwner();
        $staff = User::factory()->staff()->create();

        $this->put("/owner/users/{$staff->id}", $this->baseUpdate($staff, ['is_active' => '0']));

        $this->assertDatabaseHas('users', ['id' => $staff->id, 'is_active' => 0]);
    }

    public function test_deactivated_account_cannot_log_in(): void
    {
        $this->actingAsOwner();
        $staff = User::factory()->staff()->create([
            'email' => 'target@example.test',
            'password' => Hash::make('secret1234'),
        ]);

        $this->put("/owner/users/{$staff->id}", $this->baseUpdate($staff, ['is_active' => '0']));

        $this->assertFalse((bool) $staff->fresh()->is_active);

        // Try to authenticate as the deactivated user in an independent request.
        $this->flushSession();
        auth('web')->logout();

        $response = $this->from('/login')->post('/login', [
            'email' => 'target@example.test',
            'password' => 'secret1234',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
    }

    public function test_empty_update_password_preserves_existing_password(): void
    {
        $this->actingAsOwner();
        $staff = User::factory()->staff()->create([
            'password' => Hash::make('original-pw'),
        ]);
        $originalHash = $staff->password;

        $this->put("/owner/users/{$staff->id}", $this->baseUpdate($staff, ['password' => '', 'password_confirmation' => '']));

        $this->assertSame($originalHash, $staff->fresh()->password);
    }

    public function test_supplied_update_password_changes_password(): void
    {
        $this->actingAsOwner();
        $staff = User::factory()->staff()->create([
            'password' => Hash::make('original-pw'),
        ]);
        $original = $staff->password;

        $this->put("/owner/users/{$staff->id}", $this->baseUpdate($staff, [
            'password' => 'brand-new-pw',
            'password_confirmation' => 'brand-new-pw',
        ]));

        $fresh = $staff->fresh();
        $this->assertNotSame($original, $fresh->password);
        $this->assertTrue(Hash::check('brand-new-pw', $fresh->password));
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $this->actingAsOwner();
        User::factory()->courier()->create(['email' => 'taken@example.test']);
        $staff = User::factory()->staff()->create();

        $response = $this->from(route('owner.users.edit', $staff))
            ->put("/owner/users/{$staff->id}", $this->baseUpdate($staff, ['email' => 'taken@example.test']));

        $response->assertSessionHasErrors('email');
    }

    public function test_alphabetic_phone_is_rejected(): void
    {
        $this->actingAsOwner();
        $staff = User::factory()->staff()->create();

        $response = $this->from(route('owner.users.edit', $staff))
            ->put("/owner/users/{$staff->id}", $this->baseUpdate($staff, ['phone' => 'not-a-phone']));

        $response->assertSessionHasErrors('phone');
    }

    public function test_owner_cannot_be_edited_through_crafted_request(): void
    {
        $this->actingAsOwner();
        $target = User::factory()->owner()->create(['name' => 'Untouchable Owner']);

        $this->get("/owner/users/{$target->id}/edit")->assertNotFound();

        $this->put("/owner/users/{$target->id}", [
            'name' => 'Hacked',
            'email' => $target->email,
            'phone' => '',
            'role' => 'staff',
            'password' => '',
            'password_confirmation' => '',
            'is_active' => '1',
        ])->assertNotFound();

        $this->assertSame('Untouchable Owner', $target->fresh()->name);
    }

    public function test_no_account_delete_route_exists(): void
    {
        $this->actingAsOwner();
        $staff = User::factory()->staff()->create();

        $this->delete("/owner/users/{$staff->id}")->assertStatus(405);
        $this->assertDatabaseHas('users', ['id' => $staff->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function baseUpdate(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? '',
            'role' => $user->role->value,
            'password' => '',
            'password_confirmation' => '',
            'is_active' => $user->is_active ? '1' : '0',
        ], $overrides);
    }
}
