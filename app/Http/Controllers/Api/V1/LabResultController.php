<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LabResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LabResultController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $query = LabResult::query()
            ->where('tenant_id', $tenant->id)
            ->with('patient')
            ->orderByDesc('resulted_at');

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->integer('patient_id'));
        }

        if ($request->boolean('critical')) {
            $query->critical();
        }

        $results = $query->get();

        return response()->json([
            'data' => $results,
            'meta' => [
                'total' => $results->count(),
                'critical_count' => $results->where('is_critical', true)->count(),
            ],
        ]);
    }

    public function show(Request $request, LabResult $labResult): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ((string) $labResult->tenant_id !== (string) $tenant->id) {
            return response()->json([
                'message' => 'Resultado no encontrado.',
            ], 404);
        }

        return response()->json([
            'data' => $labResult->load('patient'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'patient_id' => [
                'required',
                'integer',
                Rule::exists('patients', 'id')->where('tenant_id', $tenant->id),
            ],
            'test_name' => ['required', 'string', 'max:255'],
            'value' => ['required', 'numeric'],
            'unit' => ['required', 'string', 'max:50'],
            'reference_min' => ['required', 'numeric', 'lt:reference_max'],
            'reference_max' => ['required', 'numeric', 'gt:reference_min'],
            'resulted_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $labResult = LabResult::query()->create([
            ...$validated,
            'tenant_id' => $tenant->id,
        ]);

        return response()->json([
            'data' => $labResult->load('patient'),
        ], 201);
    }
}
