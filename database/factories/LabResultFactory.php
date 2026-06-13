<?php

namespace Database\Factories;

use App\Models\LabResult;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabResult>
 */
class LabResultFactory extends Factory
{
    protected $model = LabResult::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'tenant_id' => fn (array $attributes) => Patient::find($attributes['patient_id'])->tenant_id,
            'test_name' => 'Glucosa',
            'value' => 90,
            'unit' => 'mg/dL',
            'reference_min' => 70,
            'reference_max' => 100,
            'resulted_at' => now(),
            'notes' => null,
        ];
    }

    /**
     * Force the generated value above the reference range.
     */
    public function criticalHigh(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => ((float) $attributes['reference_max']) + 10,
        ]);
    }

    /**
     * Force the generated value below the reference range.
     */
    public function criticalLow(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => max(0, ((float) $attributes['reference_min']) - 10),
        ]);
    }
}
