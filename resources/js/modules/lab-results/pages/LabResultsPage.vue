<template>
    <section class="lab-results">
        <h2 class="lab-results__title">
            Valores críticos de laboratorio
        </h2>
        <p class="lab-results__subtitle">
            Indicador y alerta de resultados de laboratorio fuera del rango de referencia.
        </p>

        <div class="lab-results__toolbar">
            <span
                v-if="!loading && !errorMessage"
                class="lab-results__summary"
                :class="meta.critical_count > 0 ? 'lab-results__summary--alert' : 'lab-results__summary--ok'"
            >
                {{ meta.critical_count }} de {{ meta.total }} resultado(s) en estado crítico
            </span>

            <label class="lab-results__filter">
                <input
                    v-model="onlyCritical"
                    type="checkbox"
                    @change="fetchResults"
                >
                Mostrar solo valores críticos
            </label>
        </div>

        <p v-if="loading" class="lab-results__state">
            Cargando resultados…
        </p>
        <p v-else-if="errorMessage" class="lab-results__state lab-results__state--error">
            {{ errorMessage }}
        </p>
        <p v-else-if="results.length === 0" class="lab-results__state">
            No hay resultados para mostrar.
        </p>

        <table v-else class="lab-results__table">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Prueba</th>
                    <th>Valor</th>
                    <th>Rango de referencia</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="result in results"
                    :key="result.id"
                    class="lab-results__row"
                    :class="{ 'lab-results__row--critical': result.is_critical }"
                >
                    <td>{{ result.patient?.full_name ?? '—' }}</td>
                    <td>{{ result.test_name }}</td>
                    <td>{{ result.value }} {{ result.unit }}</td>
                    <td>{{ result.reference_min }} - {{ result.reference_max }} {{ result.unit }}</td>
                    <td>
                        <span
                            class="lab-results__status"
                            :class="result.is_critical ? 'lab-results__status--critical' : 'lab-results__status--normal'"
                        >
                            {{ statusLabel(result.status) }}
                        </span>
                    </td>
                    <td>{{ formatDate(result.resulted_at) }}</td>
                </tr>
            </tbody>
        </table>
    </section>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { fetchLabResults } from '@/modules/lab-results/services/labResultsApi';

const results = ref([]);
const meta = ref({ total: 0, critical_count: 0 });
const onlyCritical = ref(false);
const loading = ref(true);
const errorMessage = ref('');

const STATUS_LABELS = {
    normal: 'Normal',
    critico_alto: 'Crítico (alto)',
    critico_bajo: 'Crítico (bajo)',
};

function statusLabel(status) {
    return STATUS_LABELS[status] ?? status;
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}

async function fetchResults() {
    loading.value = true;
    errorMessage.value = '';

    try {
        const { data } = await fetchLabResults({ critical: onlyCritical.value });
        results.value = data.data;
        meta.value = data.meta;
    } catch (error) {
        errorMessage.value = error?.response?.data?.message
            ?? 'No fue posible cargar los resultados de laboratorio.';
    } finally {
        loading.value = false;
    }
}

onMounted(fetchResults);
</script>

<style scoped>
.lab-results {
    max-width: 960px;
}

.lab-results__title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.lab-results__subtitle {
    color: #475569;
    margin-bottom: 1.25rem;
}

.lab-results__toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.lab-results__summary {
    font-weight: 600;
    padding: 0.4rem 0.85rem;
    border-radius: 999px;
    font-size: 0.9rem;
}

.lab-results__summary--ok {
    background: #dcfce7;
    color: #166534;
}

.lab-results__summary--alert {
    background: #fee2e2;
    color: #b91c1c;
}

.lab-results__filter {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: #334155;
}

.lab-results__state {
    color: #475569;
}

.lab-results__state--error {
    color: #b91c1c;
}

.lab-results__table {
    width: 100%;
    border-collapse: collapse;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
}

.lab-results__table th,
.lab-results__table td {
    text-align: left;
    padding: 0.65rem 0.85rem;
    border-bottom: 1px solid #e2e8f0;
    font-size: 0.9rem;
}

.lab-results__table th {
    background: #f8fafc;
    font-weight: 600;
    color: #334155;
}

.lab-results__row--critical {
    background: #fef2f2;
}

.lab-results__status {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 600;
}

.lab-results__status--normal {
    background: #dcfce7;
    color: #166534;
}

.lab-results__status--critical {
    background: #fee2e2;
    color: #b91c1c;
}
</style>
