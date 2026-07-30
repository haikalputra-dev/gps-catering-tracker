<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A physical GPS tracker registered by the owner.
 *
 * A device is identified by a short `identifier` (typically the
 * hardware serial or an operator-assigned label). Its 40-character
 * Bearer `api_token` is compared with `hash_equals` on every
 * telemetry submission (AR-47 revised). Devices are never hard
 * deleted; owners toggle `is_active` to revoke ingest access.
 *
 * Assignment history lives in `device_assignments`. Accessors
 * `currentAssignment` and `currentCourier` collapse the history to
 * the currently-open row.
 */
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    protected $fillable = [
        'identifier',
        'model',
        'hardware_version',
        'api_token',
        'is_active',
        'last_seen_at',
        'notes',
    ];

    /**
     * The Bearer token is a low-value plaintext credential for the
     * prototype (AR-47 revised) but should still be hidden from any
     * default array/JSON serialisation to reduce accidental leakage in
     * logs, error screens, or debug dumps.
     *
     * @var list<string>
     */
    protected $hidden = ['api_token'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DeviceAssignment::class);
    }

    /**
     * The currently-open assignment for this device, if any.
     *
     * A device has at most one open assignment (AR-50). The relation
     * filters on `unassigned_at IS NULL` so `->currentAssignment` is
     * either the active row or `null`.
     */
    public function currentAssignment(): HasOne
    {
        return $this
            ->hasOne(DeviceAssignment::class)
            ->whereNull('unassigned_at')
            ->latestOfMany('assigned_at');
    }

    /**
     * True while the device may authenticate and submit telemetry.
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Convenience accessor for a masked, last-four-only preview of the
     * token used on the details page. Never returns the full token.
     */
    public function tokenPreview(): string
    {
        $token = (string) ($this->api_token ?? '');

        return $token === ''
            ? '(none)'
            : '••••••••••••••••••••••••••••••••••••'.substr($token, -4);
    }

    /**
     * Scope: active devices.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope: inactive devices.
     */
    #[Scope]
    protected function inactive(Builder $query): void
    {
        $query->where('is_active', false);
    }

    protected static function newFactory(): DeviceFactory
    {
        return DeviceFactory::new();
    }
}
