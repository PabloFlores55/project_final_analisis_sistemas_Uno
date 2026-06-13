<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $patients = Patient::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'document_id']);

        return response()->json([
            'data' => $patients,
        ]);
    }
}
