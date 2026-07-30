<?php

declare(strict_types=1);

namespace App\Domain\Identity;

/**
 * Identity role for accounts in the users table.
 *
 * Stored values are exactly "owner", "staff" and "courier". These strings
 * are persisted in the database and MUST NOT change.
 *
 * Web forms must NEVER accept the "owner" value. Use {@see manageableRoles()}
 * for anything driven by user input.
 */
enum UserRole: string
{
    case Owner = 'owner';
    case Staff = 'staff';
    case Courier = 'courier';

    /**
     * Human-readable label used in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Staff => 'Staff',
            self::Courier => 'Courier',
        };
    }

    /**
     * Roles that the owner may assign through the web UI.
     *
     * The "owner" role is intentionally excluded so it can never be created
     * or granted through public form input.
     *
     * @return array<int, self>
     */
    public static function manageableRoles(): array
    {
        return [self::Staff, self::Courier];
    }

    /**
     * Manageable role values as plain strings, useful for validation rules.
     *
     * @return array<int, string>
     */
    public static function manageableValues(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::manageableRoles());
    }
}
