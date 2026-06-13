import api from '@/plugins/axios';

export function fetchLabResults({ critical = false, patientId = null } = {}) {
    return api.get('/lab-results', {
        params: {
            critical: critical ? 1 : undefined,
            patient_id: patientId ?? undefined,
        },
    });
}
