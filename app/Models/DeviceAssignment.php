<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DeviceAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One continuous binding between a Device and a courier User.
 *
 * A row is "open" while `unassigned_at` is NULL; the
 * DeviceAssignmentService opens a new row when a device is assigned
 * and closes the current row when the device is unassigned or
 * reassigned. Multiple closed rows may exist per (device, courier)
 * pair over time; at most one open row per device and one open row
 * per courier are permitted at any moment (AR-50). That invariant is
 * enforced by the domain service, not by the database.
 */
class DeviceAssignment extends Model
{
    /** @use HasFactory<DeviceAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'device_id',
        'courier_id',
        'assigned_at',
        'unassigned_at',
        'assigned_by_user_id',
        'unassigned_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function unassignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unassigned_by_user_id');
    }

    /**
     * True while this assignment row is the currently active binding.
     */
    public function isOpen(): bool
    {
        return $this->unassigned_at === null;
    }

    /**
     * Scope: currently-active (open) assignments only.
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('unassigned_at');
    }

    /**
     * Scope: historical (closed) assignments only.
     */
    #[Scope]
    protected function closed(Builder $query): void
    {
        $query->whereNotNull('unassigned_at');
    }

    protected static function newFactory(): DeviceAssignmentFactory
    {
        return DeviceAssignmentFactory::new();
    }
}
