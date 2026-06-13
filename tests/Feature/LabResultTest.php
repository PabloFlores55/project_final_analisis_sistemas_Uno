<?php

namespace Tests\Feature;

use App\Models\LabResult;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class LabResultTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function authHeaders(Tenant $tenant, User $user): array
    {
        $token = JWTAuth::fromUser($user);

        return [
            'Authorization' => "Bearer {$token}",
            'X-Tenant-ID' => $tenant->id,
            'Accept' => 'application/json',
        ];
    }

    public function test_status_is_calculated_automatically_when_saving(): void
    {
        $tenant = Tenant::factory()->create();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $normal = LabResult::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'test_name' => 'Glucosa',
            'value' => 90,
            'reference_min' => 70,
            'reference_max' => 100,
        ]);

        $high = LabResult::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'test_name' => 'Glucosa',
            'value' => 245,
            'reference_min' => 70,
            'reference_max' => 100,
        ]);

        $low = LabResult::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'test_name' => 'Hemoglobina',
            'value' => 8.2,
            'reference_min' => 12,
            'reference_max' => 16,
        ]);

        $this->assertSame(LabResult::STATUS_NORMAL, $normal->status);
        $this->assertFalse($normal->is_critical);

        $this->assertSame(LabResult::STATUS_CRITICAL_HIGH, $high->status);
        $this->assertTrue($high->is_critical);

        $this->assertSame(LabResult::STATUS_CRITICAL_LOW, $low->status);
        $this->assertTrue($low->is_critical);
    }

    public function test_index_lists_results_and_filters_critical_ones(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        LabResult::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'test_name' => 'Glucosa',
            'value' => 90,
            'unit' => 'mg/dL',
            'reference_min' => 70,
            'reference_max' => 100,
        ]);

        LabResult::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'test_name' => 'Potasio',
            'value' => 6.8,
            'unit' => 'mmol/L',
            'reference_min' => 3.5,
            'reference_max' => 5.0,
        ]);

        $response = $this->withHeaders($this->authHeaders($tenant, $user))
            ->getJson('/api/v1/lab-results');

        $response->assertOk();
        $response->assertJsonPath('meta.total', 2);
        $response->assertJsonPath('meta.critical_count', 1);

        $criticalResponse = $this->withHeaders($this->authHeaders($tenant, $user))
            ->getJson('/api/v1/lab-results?critical=1');

        $criticalResponse->assertOk();
        $criticalResponse->assertJsonPath('meta.total', 1);
        $criticalResponse->assertJsonPath('data.0.test_name', 'Potasio');
        $criticalResponse->assertJsonPath('data.0.status', LabResult::STATUS_CRITICAL_HIGH);
    }

    public function test_store_creates_lab_result_with_calculated_status(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->withHeaders($this->authHeaders($tenant, $user))
            ->postJson('/api/v1/lab-results', [
                'patient_id' => $patient->id,
                'test_name' => 'Hemoglobina',
                'value' => 8.2,
                'unit' => 'g/dL',
                'reference_min' => 12,
                'reference_max' => 16,
                'resulted_at' => now()->toIso8601String(),
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', LabResult::STATUS_CRITICAL_LOW);
        $response->assertJsonPath('data.is_critical', true);

        $this->assertDatabaseHas('lab_results', [
            'patient_id' => $patient->id,
            'test_name' => 'Hemoglobina',
            'status' => LabResult::STATUS_CRITICAL_LOW,
        ]);
    }

    public function test_endpoints_require_authentication_and_tenant_header(): void
    {
        $tenant = Tenant::factory()->create();

        $this->getJson('/api/v1/lab-results')
            ->assertStatus(400);

        $this->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->getJson('/api/v1/lab-results')
            ->assertStatus(401);
    }
}
