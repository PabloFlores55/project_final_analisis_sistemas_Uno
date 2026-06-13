# Sprint 1 — Análisis del proyecto y plan del módulo asignado

**Estudiante:** José Pablo Carías Flores (carné 1890-23-7587)
**Módulo asignado (Cuadro 2):** N.º 22 — *Valores críticos*
**Descripción oficial del módulo:** Implementar un indicador o alerta para resultados de laboratorio fuera de rango.

---

## 1. Arquitectura del proyecto base

El proyecto sigue un modelo **SPA + API REST**:

- El navegador carga una única vista Blade (`resources/views/app.blade.php`) que monta una aplicación **Vue 3**.
- El backend expone toda su funcionalidad como **JSON** bajo el prefijo `/api/v1` (configurado en `bootstrap/app.php`).
- Cualquier ruta web no reconocida cae al *fallback* de `routes/web.php`, que sirve la misma vista para que **Vue Router** controle la navegación del lado cliente.

### 1.1 Stack tecnológico

| Capa | Tecnología | Rol |
|------|------------|-----|
| Backend | Laravel 12 (PHP ^8.2) | Lógica de negocio, persistencia, contratos HTTP |
| Autenticación API | `tymon/jwt-auth` ^2.2 | Tokens JWT sobre el guard `api` (sin sesiones para el API) |
| Autorización (RBAC) | `spatie/laravel-permission` ^6.21 | Roles (`Admin`, `Médico`, `Enfermera`, `TecnicoLab`, `Recepcionista`) sobre `User` |
| Multitenancy | `stancl/tenancy` ^3.9 + tabla `tenants` | Tenant identificado por cabecera `X-Tenant-ID`, columna `tenant_id` en tablas dependientes |
| Frontend | Vue 3 + Vue Router 4 + Pinia | SPA, estado global, vistas |
| Build | Vite 7 + `laravel-vite-plugin` + Tailwind 4 | Empaquetado de JS/CSS, alias `@` → `resources/js` |
| Cliente HTTP | Axios (`resources/js/plugins/axios.js`) | Inyecta `Authorization: Bearer` y `X-Tenant-ID` desde `localStorage` |
| Base de datos | SQLite (dev) / MySQL 8 (prod) | `config/database.php`, default `sqlite` |

### 1.2 Flujo de una petición autenticada

1. El cliente envía `X-Tenant-ID` (UUID del tenant) y, si hay sesión, `Authorization: Bearer <jwt>`.
2. `TenantMiddleware` (`app/Http/Middleware/TenantMiddleware.php`) valida que el tenant exista; si no, responde `404`. Si existe, lo inyecta en `request->attributes` y como singleton `currentTenant`.
3. `JwtAuth` (`app/Http/Middleware/JwtAuth.php`) valida el token y verifica que `tenant_id` del usuario autenticado coincida con la cabecera (`403` si no coincide).
4. El controlador responde siempre en JSON.

### 1.3 Piezas relevantes ya existentes

```
app/Http/Controllers/Api/V1/AuthController.php   # registro, login, me, refresh, logout
app/Http/Middleware/TenantMiddleware.php         # cabecera X-Tenant-ID
app/Http/Middleware/JwtAuth.php                  # JWT + coherencia tenant
app/Models/User.php                              # JWT + HasRoles + tenant_id
app/Models/Tenant.php                            # modelo Stancl / tabla tenants
resources/js/                                    # Vue: router, stores, páginas, Axios
routes/api.php                                   # rutas bajo prefijo api/v1
database/seeders/RoleSeeder.php                  # roles base
database/seeders/TenantSeeder.php                # tenant demo "san-marcos-demo"
```

La base **no incluye** módulos clínicos (pacientes, laboratorio, etc.). El módulo 22 requiere una estructura mínima de **resultados de laboratorio** para poder implementar el indicador de valores críticos; esa estructura mínima se construye como parte de este trabajo y se documenta abajo.

---

## 2. Plan del módulo 22 — Valores críticos

### 2.1 Objetivo

Permitir que el personal autenticado consulte los **resultados de laboratorio** de un tenant y vea, de forma clara y visual, cuáles están **fuera del rango de referencia** (valores críticos: por arriba o por debajo de lo normal), con una alerta destacada y un filtro dedicado.

### 2.2 Modelo de datos propuesto

Para que el módulo sea funcional de forma autónoma (los módulos de "Gestión de pacientes" y "Resultados de laboratorio" completos son responsabilidad de otros compañeros), se agrega una estructura mínima de soporte:

**Tabla `patients`** (mínima, multitenant)

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | string FK → tenants.id | cascade on delete |
| full_name | string | |
| document_id | string | identificación (DPI/expediente) |
| birth_date | date nullable | |
| timestamps | | |

**Tabla `lab_results`** (multitenant)

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | string FK → tenants.id | cascade on delete |
| patient_id | bigint FK → patients.id | cascade on delete |
| test_name | string | p. ej. "Glucosa", "Potasio" |
| value | decimal(10,2) | valor medido |
| unit | string | p. ej. "mg/dL" |
| reference_min | decimal(10,2) | límite inferior normal |
| reference_max | decimal(10,2) | límite superior normal |
| status | enum: `normal`, `critico_bajo`, `critico_alto` | calculado al guardar |
| resulted_at | datetime | fecha del resultado |
| notes | string nullable | |
| timestamps | | |

**Regla de negocio (indicador de valor crítico):**

```
si value < reference_min  -> status = critico_bajo
si value > reference_max  -> status = critico_alto
en otro caso               -> status = normal
```

El campo `status` se calcula automáticamente en el modelo `LabResult` al crear/actualizar el registro (no se confía en que el cliente lo envíe), y se expone también como atributo `is_critical` (booleano) para consumo del frontend.

### 2.3 Endpoints API planificados (bajo `tenant` + `jwt.auth`)

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/lab-results` | Lista resultados del tenant. Filtros opcionales: `?patient_id=`, `?critical=1` (solo críticos) |
| GET | `/api/v1/lab-results/{id}` | Detalle de un resultado |
| POST | `/api/v1/lab-results` | Crea un resultado (calcula `status` automáticamente) |
| GET | `/api/v1/patients` | Lista mínima de pacientes (para selects) |

### 2.4 Vista frontend planificada

Nueva página **"Valores críticos / Resultados de laboratorio"** (`resources/js/modules/lab-results/pages/LabResultsPage.vue`):

- Tabla con: paciente, prueba, valor + unidad, rango de referencia, estado, fecha.
- Filas con `status` ≠ `normal` resaltadas visualmente (color/icono de alerta).
- Casilla "Mostrar solo valores críticos" que aplica `?critical=1`.
- Resumen superior: contador de resultados críticos pendientes.
- Acceso desde la navegación principal (`AppLayout.vue`), ruta protegida (`meta.requiresAuth`).

### 2.5 Criterios de aceptación

1. Al listar resultados, cada uno muestra su estado calculado (`normal` / `critico_alto` / `critico_bajo`).
2. La vista resalta visualmente los valores críticos (color + etiqueta/ícono).
3. El filtro "solo críticos" usa el parámetro `critical=1` del API y devuelve únicamente registros con `status != normal`.
4. Las rutas respetan el middleware existente (`tenant`, `jwt.auth`) y la respuesta es JSON.
5. Existen datos de ejemplo (seeder) con casos normales y críticos para poder probar el indicador sin captura manual.
6. Pruebas automatizadas (PHPUnit) cubren el cálculo de `status`/`is_critical` y el filtro `critical=1`.

---

## 3. Plan de sprints

| Sprint | Contenido |
|--------|-----------|
| **Sprint 1** (este documento) | Análisis de arquitectura/stack del proyecto base y diseño del módulo 22 (modelo de datos, endpoints, vista, criterios de aceptación). |
| **Sprint 2** | Implementación funcional: migraciones, modelos, seeders, controlador y rutas API, vista Vue con indicador de valores críticos, pruebas automatizadas. |
| **Sprint 3** | Diagramas UML (casos de uso, clases, secuencia) del módulo 22. |

---

## 4. Cómo ejecutar y verificar (referencia)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate --seed
npm install
npm run dev      # en otra terminal: php artisan serve
php artisan test
```
