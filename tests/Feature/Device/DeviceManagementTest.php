<?php

declare(strict_types=1);

namespace Tests\Feature\Device;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsOwner(): User
    {
        $owner = User::factory()->owner()->create();
        $this->actingAs($owner);

        return $owner;
    }

    public function test_owner_can_list_devices(): void
    {
        $this->actingAsOwner();
        Device::factory()->create(['identifier' => 'DEV-ONE-001']);
        Device::factory()->create(['identifier' => 'DEV-TWO-002']);

        $response = $this->get(route('devices.index'));

        $response->assertOk();
        $response->assertSee('DEV-ONE-001');
        $response->assertSee('DEV-TWO-002');
    }

    public function test_owner_can_render_create_form(): void
    {
        $this->actingAsOwner();

        $response = $this->get(route('devices.create'));

        $response->assertOk();
        $response->assertSee('identifier', false);
    }

    public function test_owner_can_register_a_device_and_sees_token_once(): void
    {
        $this->actingAsOwner();

        $response = $this->post(route('devices.store'), [
            'identifier' => 'dev-abc-123',
            'model' => 'Ruggedized X1',
            'hardware_version' => '1.4.2',
            'is_active' => '1',
            'notes' => 'Warehouse spare unit.',
        ]);

        $device = Device::query()->firstOrFail();
        $response->assertRedirect(route('devices.show', $device));
        $response->assertSessionHas('token_plain');

        $this->assertSame('DEV-ABC-123', $device->identifier, 'identifier is normalised to uppercase');
        $this->assertSame('Ruggedized X1', $device->model);
        $this->assertSame('1.4.2', $device->hardware_version);
        $this->assertTrue((bool) $device->is_active);
        $this->assertNotEmpty($device->api_token, 'plaintext token stored (AR-47)');
        $this->assertGreaterThanOrEqual(32, strlen($device->api_token));

        // The token flash is one-shot: following the redirect and
        // reading the same page again should not re-expose it.
        $show = $this->get(route('devices.show', $device));
        $show->assertOk();
        $show->assertSessionMissing('token_plain');
    }

    public function test_identifier_is_normalised_and_must_be_unique(): void
    {
        $this->actingAsOwner();
        Device::factory()->create(['identifier' => 'DEV-DUP-001']);

        $response = $this->post(route('devices.store'), [
            'identifier' => 'dev-dup-001',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('identifier');
        $this->assertSame(1, Device::query()->count());
    }

    public function test_store_requires_identifier(): void
    {
        $this->actingAsOwner();

        $response = $this->post(route('devices.store'), [
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('identifier');
    }

    public function test_owner_can_update_device_metadata(): void
    {
        $this->actingAsOwner();
        $device = Device::factory()->create([
            'identifier' => 'DEV-EDIT-01',
            'model' => 'Old Model',
        ]);

        $response = $this->put(route('devices.update', $device), [
            'identifier' => 'DEV-EDIT-01',
            'model' => 'New Model',
            'hardware_version' => '2.0.0',
            'is_active' => '1',
            'notes' => null,
        ]);

        $response->assertRedirect(route('devices.show', $device));
        $device->refresh();
        $this->assertSame('New Model', $device->model);
        $this->assertSame('2.0.0', $device->hardware_version);
    }

    public function test_owner_can_rotate_api_token(): void
    {
        $this->actingAsOwner();
        $device = Device::factory()->withToken('KEEP-ME-32-CHARS-XXXXXXXXXXXXXXXX')->create();
        $oldToken = $device->api_token;

        $response = $this->post(route('devices.rotate-token', $device));

        $response->assertRedirect(route('devices.show', $device));
        $response->assertSessionHas('token_plain');
        $device->refresh();
        $this->assertNotSame($oldToken, $device->api_token, 'token is rotated');
        $this->assertNotEmpty($device->api_token);
    }

    public function test_deactivating_device_auto_unassigns_current_courier(): void
    {
        $owner = $this->actingAsOwner();
        $device = Device::factory()->create();
        $courier = User::factory()->courier()->create();

        // Bind via HTTP so we exercise the same code path an owner uses.
        $this->post(route('devices.assign', $device), [
            'courier_id' => $courier->id,
        ])->assertRedirect();

        $this->assertTrue($device->fresh()->currentAssignment !== null);

        $this->put(route('devices.update', $device), [
            'identifier' => $device->identifier,
            'is_active' => '0',
        ])->assertRedirect(route('devices.show', $device));

        $device->refresh();
        $this->assertFalse((bool) $device->is_active);
        $this->assertNull($device->currentAssignment, 'binding was auto-closed on deactivation');
    }

    public function test_staff_cannot_access_device_admin(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        $this->get(route('devices.index'))->assertForbidden();
        $this->post(route('devices.store'), ['identifier' => 'X', 'is_active' => '1'])
            ->assertForbidden();
    }

    public function test_courier_cannot_access_device_admin(): void
    {
        $this->actingAs(User::factory()->courier()->create());

        $this->get(route('devices.index'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('devices.index'))->assertRedirect(route('login'));
    }
}
