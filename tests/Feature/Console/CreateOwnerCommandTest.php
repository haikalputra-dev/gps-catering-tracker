<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateOwnerCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_active_owner_with_hashed_password(): void
    {
        $this->artisan('app:create-owner', [
            '--name' => 'Founder',
            '--email' => 'founder@example.test',
            '--phone' => '081234567890',
        ])
            ->expectsQuestion('Password (min 8 characters)', 'secret1234')
            ->expectsQuestion('Confirm password', 'secret1234')
            ->assertSuccessful();

        $user = User::query()->where('email', 'founder@example.test')->firstOrFail();
        $this->assertSame('owner', $user->role->value);
        $this->assertTrue((bool) $user->is_active);
        $this->assertTrue(Hash::check('secret1234', $user->password));
        $this->assertNotSame('secret1234', $user->password);
    }

    public function test_command_lowercases_and_trims_email(): void
    {
        $this->artisan('app:create-owner', [
            '--name' => 'Founder',
            '--email' => '  Founder@Example.Test  ',
        ])
            ->expectsQuestion('Password (min 8 characters)', 'secret1234')
            ->expectsQuestion('Confirm password', 'secret1234')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'founder@example.test']);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->owner()->create(['email' => 'exists@example.test']);

        $this->artisan('app:create-owner', [
            '--name' => 'Second Owner',
            '--email' => 'exists@example.test',
        ])
            ->expectsQuestion('Password (min 8 characters)', 'secret1234')
            ->expectsQuestion('Confirm password', 'secret1234')
            ->assertFailed();

        $this->assertSame(1, User::query()->where('email', 'exists@example.test')->count());
    }

    public function test_mismatched_password_confirmation_is_rejected(): void
    {
        $this->artisan('app:create-owner', [
            '--name' => 'Founder',
            '--email' => 'mismatch@example.test',
        ])
            ->expectsQuestion('Password (min 8 characters)', 'secret1234')
            ->expectsQuestion('Confirm password', 'different-pw')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'mismatch@example.test']);
    }

    public function test_short_password_is_rejected(): void
    {
        $this->artisan('app:create-owner', [
            '--name' => 'Founder',
            '--email' => 'short@example.test',
        ])
            ->expectsQuestion('Password (min 8 characters)', 'short')
            ->expectsQuestion('Confirm password', 'short')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'short@example.test']);
    }

    public function test_password_is_not_part_of_command_signature(): void
    {
        $signature = (new \App\Console\Commands\CreateOwnerCommand())->getName();
        $this->assertSame('app:create-owner', $signature);

        $definition = (new \App\Console\Commands\CreateOwnerCommand())->getDefinition();

        $this->assertFalse($definition->hasOption('password'));
        $this->assertFalse($definition->hasArgument('password'));
    }
}
