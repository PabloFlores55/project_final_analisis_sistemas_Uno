<?php

namespace Database\Seeders;

use App\Models\LabResult;
use App\Models\Patient;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class LabResultSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'san-marcos-demo')->first();

        if ($tenant === null) {
            return;
        }

        $patients = [
            ['full_name' => 'María Fernanda López', 'document_id' => '1001', 'birth_date' => '1988-03-12'],
            ['full_name' => 'Carlos Eduardo Pérez', 'document_id' => '1002', 'birth_date' => '1975-11-02'],
            ['full_name' => 'Ana Lucía Gómez', 'document_id' => '1003', 'birth_date' => '1995-07-23'],
        ];

        $patientModels = [];

        foreach ($patients as $patient) {
            $patientModels[] = Patient::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'document_id' => $patient['document_id']],
                [
                    'full_name' => $patient['full_name'],
                    'birth_date' => $patient['birth_date'],
                ]
            );
        }

        if (LabResult::query()->where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        // [paciente, prueba, valor, unidad, ref_min, ref_max, horas_atras, notas]
        $results = [
            [0, 'Glucosa', 92, 'mg/dL', 70, 100, 2, null],
            [0, 'Potasio', 6.8, 'mmol/L', 3.5, 5.0, 1, 'Repetir muestra para confirmar.'],
            [0, 'Hemoglobina', 13.5, 'g/dL', 12, 16, 5, null],
            [1, 'Glucosa', 245, 'mg/dL', 70, 100, 3, 'Paciente con antecedente de diabetes.'],
            [1, 'Creatinina', 1.0, 'mg/dL', 0.6, 1.2, 4, null],
            [1, 'Sodio', 128, 'mmol/L', 135, 145, 2, 'Valorar hidratación.'],
            [2, 'Hemoglobina', 8.2, 'g/dL', 12, 16, 6, 'Paciente refiere fatiga.'],
            [2, 'Glucosa', 88, 'mg/dL', 70, 100, 1, null],
            [2, 'Potasio', 4.1, 'mmol/L', 3.5, 5.0, 1, null],
        ];

        foreach ($results as [$patientIndex, $testName, $value, $unit, $min, $max, $hoursAgo, $notes]) {
            LabResult::query()->create([
                'tenant_id' => $tenant->id,
                'patient_id' => $patientModels[$patientIndex]->id,
                'test_name' => $testName,
                'value' => $value,
                'unit' => $unit,
                'reference_min' => $min,
                'reference_max' => $max,
                'resulted_at' => now()->subHours($hoursAgo),
                'notes' => $notes,
            ]);
        }
    }
}
