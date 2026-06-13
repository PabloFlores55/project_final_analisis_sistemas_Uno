# Sprint 3 — Diagramas UML del módulo 22 (Valores críticos)

**Estudiante:** José Pablo Carías Flores (carné 1890-23-7587)
**Módulo:** N.º 22 — Valores críticos (indicador/alerta para resultados de laboratorio fuera de rango)

Estos diagramas están escritos en sintaxis **Mermaid**. GitHub los renderiza automáticamente al ver este archivo en el repositorio. Para el documento de Word, se pueden exportar como imagen usando [mermaid.live](https://mermaid.live) (pegar el bloque de código y exportar PNG/SVG).

---

## 1. Diagrama de casos de uso

Actor principal: **Personal autenticado** (Médico, Enfermera, TecnicoLab, Admin, Recepcionista — cualquier usuario del tenant con JWT válido). El sistema calcula automáticamente el estado crítico, por lo que también se representa como actor secundario.

```mermaid
flowchart LR
    Personal(["Personal autenticado"])
    Sistema(["Sistema (cálculo automático)"])

    subgraph Modulo22["Módulo 22 - Valores críticos"]
        UC1(("Consultar resultados\nde laboratorio"))
        UC2(("Filtrar solo\nvalores críticos"))
        UC3(("Ver detalle\nde un resultado"))
        UC4(("Registrar resultado\nde laboratorio"))
        UC5(("Consultar listado\nde pacientes"))
        UC6(("Calcular estado\n(normal / crítico alto / crítico bajo)"))
    end

    Personal --> UC1
    Personal --> UC2
    Personal --> UC3
    Personal --> UC4
    Personal --> UC5

    UC2 -.->|extiende| UC1
    UC4 -.->|incluye| UC6
    Sistema --> UC6
```

---

## 2. Diagrama de clases

Incluye las entidades relevantes para el módulo: `Tenant`, `User`, `Patient` y `LabResult`. Se destaca la lógica de cálculo del estado crítico dentro de `LabResult`.

```mermaid
classDiagram
    class Tenant {
        +string id
        +string name
        +string slug
        +array data
    }

    class User {
        +int id
        +string tenant_id
        +string name
        +string email
        +string password
    }

    class Patient {
        +int id
        +string tenant_id
        +string full_name
        +string document_id
        +date birth_date
    }

    class LabResult {
        +int id
        +string tenant_id
        +int patient_id
        +string test_name
        +decimal value
        +string unit
        +decimal reference_min
        +decimal reference_max
        +string status
        +datetime resulted_at
        +string notes
        +bool is_critical
        +resolveStatus(value, min, max) string
        +scopeCritical(query) Builder
    }

    Tenant "1" --> "many" User : tenant_id
    Tenant "1" --> "many" Patient : tenant_id
    Tenant "1" --> "many" LabResult : tenant_id
    Patient "1" --> "many" LabResult : patient_id
```

**Regla codificada en `LabResult::resolveStatus()`** (se ejecuta en el evento `saving` del modelo):

```
value < reference_min  => status = "critico_bajo"
value > reference_max  => status = "critico_alto"
en otro caso            => status = "normal"
is_critical = (status != "normal")
```

---

## 3. Diagramas de secuencia

### 3.1 Consultar valores críticos (`GET /api/v1/lab-results?critical=1`)

```mermaid
sequenceDiagram
    actor U as Usuario
    participant V as LabResultsPage.vue
    participant Ax as Axios (api)
    participant TM as TenantMiddleware
    participant JA as JwtAuth
    participant C as LabResultController
    participant M as LabResult (modelo)
    participant DB as Base de datos

    U->>V: Activa "Mostrar solo críticos"
    V->>Ax: GET /lab-results?critical=1
    Ax->>TM: Request + X-Tenant-ID
    TM->>TM: Valida tenant existente
    TM->>JA: next()
    JA->>JA: Valida JWT y tenant del usuario
    JA->>C: next()
    C->>M: LabResult::where(tenant_id)->critical()->with(patient)->get()
    M->>DB: SELECT ... WHERE tenant_id = ? AND status != 'normal'
    DB-->>M: filas
    M-->>C: Collection<LabResult> (con is_critical)
    C-->>Ax: 200 JSON {data, meta: {total, critical_count}}
    Ax-->>V: data, meta
    V-->>U: Tabla resaltada con resultados críticos
```

### 3.2 Registrar un resultado de laboratorio (`POST /api/v1/lab-results`)

```mermaid
sequenceDiagram
    actor U as Personal de laboratorio
    participant Ax as Axios (api)
    participant TM as TenantMiddleware
    participant JA as JwtAuth
    participant C as LabResultController
    participant M as LabResult (modelo)
    participant DB as Base de datos

    U->>Ax: POST /lab-results {patient_id, test_name, value, reference_min, reference_max, ...}
    Ax->>TM: Request + X-Tenant-ID + Bearer JWT
    TM->>JA: tenant válido
    JA->>C: usuario autenticado y tenant coincide
    C->>C: Valida payload (Request::validate)
    C->>M: LabResult::create([...])
    M->>M: evento saving -> resolveStatus(value, reference_min, reference_max)
    M->>DB: INSERT con status calculado
    DB-->>M: registro creado
    M-->>C: LabResult (con is_critical)
    C-->>Ax: 201 JSON {data: LabResult}
    Ax-->>U: Confirmación + estado (normal/crítico)
```

---

## 4. Resumen para el documento de entrega

| Diagrama | Qué muestra |
|----------|-------------|
| Casos de uso | Acciones del personal autenticado sobre el módulo 22 y el cálculo automático del estado crítico. |
| Clases | Entidades `Tenant`, `User`, `Patient`, `LabResult` y la lógica de `resolveStatus()` / `is_critical`. |
| Secuencia (consulta) | Flujo completo de filtrado de valores críticos desde la vista Vue hasta la base de datos. |
| Secuencia (registro) | Flujo de creación de un resultado y cómo se asigna automáticamente el estado crítico. |
