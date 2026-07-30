<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Identity\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Creates the first owner account without going through public registration.
 *
 * The password is only ever read from a hidden terminal prompt. Passing the
 * password as a CLI argument or option is intentionally impossible.
 */
class CreateOwnerCommand extends Command
{
    /**
     * Signature intentionally excludes any password option.
     */
    protected $signature = 'app:create-owner
        {--name= : Full name of the owner}
        {--email= : Email address (will be stored lowercase)}
        {--phone= : Optional phone number}';

    protected $description = 'Create the initial owner account (password entered via hidden prompt)';

    public function handle(): int
    {
        $name = $this->resolveOption('name', 'Name');
        $email = strtolower(trim((string) $this->resolveOption('email', 'Email')));
        $phoneOption = $this->option('phone');
        $phone = $phoneOption === null ? null : trim((string) $phoneOption);
        if ($phone === '') {
            $phone = null;
        }

        $password = (string) $this->secret('Password (min 8 characters)');
        $confirmation = (string) $this->secret('Confirm password');

        $data = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+\-() ]+$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'role' => UserRole::Owner,
            'is_active' => true,
            'password' => Hash::make($password),
        ]);

        $this->info("Owner account created for {$email}.");

        return self::SUCCESS;
    }

    /**
     * Read an option from the CLI, or prompt for it if missing.
     */
    private function resolveOption(string $option, string $label): string
    {
        $value = $this->option($option);
        if ($value === null || $value === '') {
            $value = $this->ask($label);
        }

        return trim((string) $value);
    }
}
