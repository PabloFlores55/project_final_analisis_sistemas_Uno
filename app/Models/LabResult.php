<?php

namespace App\Models;

use Database\Factories\LabResultFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class LabResult extends Model
{
    /** @use HasFactory<LabResultFactory> */
    use HasFactory;

    public const STATUS_NORMAL = 'normal';

    public const STATUS_CRITICAL_LOW = 'critico_bajo';

    public const STATUS_CRITICAL_HIGH = 'critico_alto';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'patient_id',
        'test_name',
        'value',
        'unit',
        'reference_min',
        'reference_max',
        'status',
        'resulted_at',
        'notes',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'is_critical',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'reference_min' => 'decimal:2',
            'reference_max' => 'decimal:2',
            'resulted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (LabResult $labResult): void {
            $labResult->status = static::resolveStatus(
                (float) $labResult->value,
                (float) $labResult->reference_min,
                (float) $labResult->reference_max,
            );
        });
    }

    public static function resolveStatus(float $value, float $referenceMin, float $referenceMax): string
    {
        if ($value < $referenceMin) {
            return self::STATUS_CRITICAL_LOW;
        }

        if ($value > $referenceMax) {
            return self::STATUS_CRITICAL_HIGH;
        }

        return self::STATUS_NORMAL;
    }

    protected function isCritical(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->status !== self::STATUS_NORMAL,
        );
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_NORMAL);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
