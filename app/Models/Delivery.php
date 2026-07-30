<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Delivery\DeliveryStatus;
use Database\Factories\DeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    /** @use HasFactory<DeliveryFactory> */
    use HasFactory;

    protected $fillable = [
        'receipt_number',
        'status',
        'kitchen_id',
        'customer_id',
        'scheduled_at',
        'notes',
        'kitchen_code',
        'kitchen_name',
        'kitchen_address',
        'kitchen_latitude',
        'kitchen_longitude',
        'customer_name',
        'customer_phone',
        'customer_address',
        'customer_latitude',
        'customer_longitude',
        'scheduled_by_user_id',
        'scheduled_at_recorded',
        'cancellation_reason',
        'cancelled_by_user_id',
        'cancelled_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'scheduled_at' => 'datetime',
            'scheduled_at_recorded' => 'datetime',
            'cancelled_at' => 'datetime',
            'kitchen_latitude' => 'decimal:7',
            'kitchen_longitude' => 'decimal:7',
            'customer_latitude' => 'decimal:7',
            'customer_longitude' => 'decimal:7',
        ];
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(Kitchen::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /**
     * True when the delivery may still be edited (draft only).
     */
    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    /**
     * True when the delivery counts against the concurrency cap.
     */
    public function isActiveForConcurrency(): bool
    {
        return $this->status->isActiveForConcurrency();
    }

    /**
     * True when the delivery is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * Scope: drafts only.
     */
    #[Scope]
    protected function draft(Builder $query): void
    {
        $query->where('status', DeliveryStatus::Draft->value);
    }

    /**
     * Scope: scheduled deliveries only.
     */
    #[Scope]
    protected function scheduled(Builder $query): void
    {
        $query->where('status', DeliveryStatus::Scheduled->value);
    }

    /**
     * Scope: any non-terminal status (counts against the concurrency cap).
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereIn(
            'status',
            array_map(
                static fn (DeliveryStatus $status): string => $status->value,
                DeliveryStatus::activeCases(),
            ),
        );
    }

    /**
     * Scope: any terminal status.
     */
    #[Scope]
    protected function terminal(Builder $query): void
    {
        $query->whereIn(
            'status',
            array_map(
                static fn (DeliveryStatus $status): string => $status->value,
                DeliveryStatus::terminalCases(),
            ),
        );
    }

    protected static function newFactory(): DeliveryFactory
    {
        return DeliveryFactory::new();
    }
}
