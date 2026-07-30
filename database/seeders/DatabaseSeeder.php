<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Device\DeviceAssignmentService;
use App\Domain\Identity\UserRole;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Development seeder.
 *
 * Builds a complete, ready-to-drive test setup:
 *   - three users (owner, staff, courier), all with password "password"
 *   - one kitchen (KITCHEN-DEMO) in Sukabumi
 *   - one customer whose phone ends in 1234 (memorable tracking code)
 *   - one device bound to the courier with a hardcoded Bearer token
 *
 * Idempotent: every row uses `updateOrCreate` on a stable natural key
 * (email, kitchen code, customer phone, device identifier) so
 * `php artisan db:seed` may be re-run without producing duplicates or
 * errors. Device assignment is delegated to
 * `App\Domain\Device\DeviceAssignmentService`, which is itself
 * idempotent for the same courier (Packet 11, AR-50).
 *
 * Deliberately does NOT create a delivery. The developer exercises
 * the delivery-creation flow through the UI.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Shared password for all three seeded accounts. Never used in
     * production — this seeder is dev-only.
     */
    private const SEED_PASSWORD = 'password';

    /**
     * Fixed Bearer token so the developer can copy-paste it into the
     * simulator or firmware config without going through the rotate
     * UI. Rotate this in the admin surface before any production use.
     */
    private const SEED_DEVICE_TOKEN = 'demo_tok_2p8XkzVFqR5wBhLmN3sJcYtQu7v';

    public function run(): void
    {
        $owner = $this->seedUser(
            email: 'owner@test.local',
            name: 'Test Owner',
            phone: '081234567890',
            role: UserRole::Owner,
        );

        $staff = $this->seedUser(
            email: 'staff@test.local',
            name: 'Test Staff',
            phone: '081234567891',
            role: UserRole::Staff,
        );

        $courier = $this->seedUser(
            email: 'courier@test.local',
            name: 'Test Courier',
            phone: '081234567892',
            role: UserRole::Courier,
        );

        $kitchen = Kitchen::query()->updateOrCreate(
            ['code' => 'KITCHEN-DEMO'],
            [
                'name' => 'Dapur Demo',
                'address' => 'Jl. Contoh No. 1, Sukabumi',
                'phone' => '0266123456',
                'latitude' => -6.9175000,
                'longitude' => 106.9270000,
                'is_active' => true,
            ],
        );

        $customer = Customer::query()->updateOrCreate(
            ['phone' => '081234561234'],
            [
                'name' => 'Test Customer',
                'address' => 'Jl. Pelanggan No. 5, Sukabumi',
                'notes' => 'Ring the doorbell twice.',
                'latitude' => -6.9350000,
                'longitude' => 106.9450000,
                'is_active' => true,
            ],
        );

        // Schema note (AR-53): the `devices` table uses `identifier`,
        // `model`, `hardware_version`, and `notes`. There is no `label`
        // or `serial_number` column. The task's "serial" maps to
        // `identifier`; "hardware model" maps to `model`; the human
        // label lives in `notes`.
        $device = Device::query()->updateOrCreate(
            ['identifier' => 'ESP-DEMO-001'],
            [
                'model' => 'ESP32-WROOM-32',
                'hardware_version' => null,
                'api_token' => self::SEED_DEVICE_TOKEN,
                'is_active' => true,
                'notes' => 'Demo Tracker — seeded for local testing.',
            ],
        );

        // DeviceAssignmentService::assign is idempotent for the same
        // courier: re-running the seeder returns the existing open
        // assignment row without opening a new one.
        app(DeviceAssignmentService::class)->assign(
            device: $device,
            courier: $courier,
            performedBy: $owner,
            notes: 'Seeded for testing.',
        );

        $this->printSummary(
            owner: $owner,
            staff: $staff,
            courier: $courier,
            kitchen: $kitchen,
            customer: $customer,
            device: $device,
        );
    }

    /**
     * Create or update a user by email, keeping the password fresh.
     *
     * The password is hashed here (via `Hash::make`) rather than relying
     * on the model's `hashed` cast because `updateOrCreate` writes the
     * value into the DB before the mutator runs on subsequent calls,
     * and we want the same behaviour on both first-run and re-run.
     */
    private function seedUser(
        string $email,
        string $name,
        string $phone,
        UserRole $role,
    ): User {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(self::SEED_PASSWORD),
                'role' => $role,
                'phone' => $phone,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }

    private function printSummary(
        User $owner,
        User $staff,
        User $courier,
        Kitchen $kitchen,
        Customer $customer,
        Device $device,
    ): void {
        $line = str_repeat('=', 60);
        $customerPhone = (string) $customer->phone;
        $customerLast4 = substr($customerPhone, -4);

        $this->command->line('');
        $this->command->line($line);
        $this->command->info('Seed complete — development test data ready.');
        $this->command->line($line);

        $this->command->line('');
        $this->command->line('Accounts (shared password: '.self::SEED_PASSWORD.')');
        $this->command->line('  owner   : '.$owner->email);
        $this->command->line('  staff   : '.$staff->email);
        $this->command->line('  courier : '.$courier->email);

        $this->command->line('');
        $this->command->line('Kitchen');
        $this->command->line('  code  : '.$kitchen->code);
        $this->command->line('  name  : '.$kitchen->name);
        $this->command->line(sprintf('  coords: %.7f, %.7f', (float) $kitchen->latitude, (float) $kitchen->longitude));

        $this->command->line('');
        $this->command->line('Customer');
        $this->command->line('  name    : '.$customer->name);
        $this->command->line('  phone   : '.$customerPhone);
        $this->command->line('  last-4  : '.$customerLast4.'   <-- tracking-page auth code');

        $this->command->line('');
        $this->command->line('Device');
        $this->command->line('  id         : '.$device->id);
        $this->command->line('  identifier : '.$device->identifier);
        $this->command->line('  api_token  : '.self::SEED_DEVICE_TOKEN);
        $this->command->line('  bound to   : '.$courier->email);

        $this->command->line('');
        $this->command->line('Next steps');
        $this->command->line('  1. Log in as '.$owner->email.' and create a delivery for');
        $this->command->line('     kitchen "'.$kitchen->code.'" -> customer "'.$customer->name.'".');
        $this->command->line('  2. Schedule the delivery and assign courier '.$courier->email.'.');
        $this->command->line('  3. Log in as '.$courier->email.' and tap "Dispatch" to move');
        $this->command->line('     the delivery to in_transit.');
        $this->command->line('  4. Run the simulator to drive the live map:');
        $this->command->line('       php artisan telemetry:simulate --device='.$device->identifier);
        $this->command->line('  5. Track the delivery as a customer at /track using the');
        $this->command->line('     receipt number and phone last-4 "'.$customerLast4.'".');
        $this->command->line($line);
        $this->command->line('');
    }
}
