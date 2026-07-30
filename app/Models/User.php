<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Identity\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'phone', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    public function isStaff(): bool
    {
        return $this->role === UserRole::Staff;
    }

    public function isCourier(): bool
    {
        return $this->role === UserRole::Courier;
    }

    /**
     * All device assignments for this user, regardless of status.
     *
     * Only couriers should ever have rows here; the assignment service
     * enforces that at open time. Kept as a plain `hasMany` so admin
     * screens can render history for any user without a role filter.
     */
    public function deviceAssignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\DeviceAssignment::class, 'courier_id');
    }

    /**
     * The currently-open device assignment for this courier, if any.
     *
     * A courier has at most one open assignment (AR-50). The relation
     * filters on `unassigned_at IS NULL` so `->currentDeviceAssignment`
     * is either the active row or `null`.
     */
    public function currentDeviceAssignment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this
            ->hasOne(\App\Models\DeviceAssignment::class, 'courier_id')
            ->whereNull('unassigned_at')
            ->latestOfMany('assigned_at');
    }

    /**
     * Convenience accessor: the Device this courier is currently bound
     * to (if any), or `null`. Uses the open assignment as the source.
     */
    public function currentDevice(): ?\App\Models\Device
    {
        $assignment = $this->currentDeviceAssignment;

        return $assignment?->device;
    }
}
