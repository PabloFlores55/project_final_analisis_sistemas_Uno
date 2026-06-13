# Proyecto base — Evaluación Final Análisis de Sistemas I

Proyecto **Laravel 12 + Vue 3 (Vite)** con **JWT**, **Spatie Laravel Permission** y **Stancl Tenancy** (tenant identificado por cabecera `X-Tenant-ID`). Esta base se entrega para que el estudiante analice la estructura existente y desarrolle el módulo asignado por el docente.

---

## Arquitectura construida

La aplicación sigue un modelo **SPA + API REST**: el navegador carga una única vista Blade que monta Vue; el backend expone JSON bajo `/api/v1`.

### Vista general

| Capa | Tecnología | Para qué sirve |
|------|------------|----------------|
| **Backend / API** | Laravel 12 | Punto único de negocio, persistencia, seguridad y contratos HTTP JSON. |
| **Autenticación API** | `tymon/jwt-auth` | Emite y valida tokens JWT en el guard `api`; no usa sesiones para el API. |
| **Autorización (RBAC)** | `spatie/laravel-permission` | Roles y permisos sobre el modelo `User` (guard `api`). |
| **Multitenancy base** | `stancl/tenancy` + tabla `tenants` | Modelo `Tenant` y columna `tenant_id` en usuarios. El tenant activo se **indica en cada petición** con `X-Tenant-ID` (sin bases de datos separadas en esta fase). |
| **Middleware propio** | `TenantMiddleware`, `JwtAuth` | `TenantMiddleware` resuelve y valida el tenant por cabecera; `JwtAuth` protege rutas con JWT y coherencia tenant–token. |
| **Frontend** | Vue 3 + Vue Router + Pinia | SPA: rutas del lado cliente, estado global (p. ej. sesión / token) y pantallas como login. |
| **Build frontend** | Vite 7 + `@vitejs/plugin-vue` | Empaqueta JS/CSS; alias `@` apunta a `resources/js`. |
| **Cliente HTTP** | Axios (`resources/js/plugins/axios.js`) | Llama al API con `Authorization: Bearer` y `X-Tenant-ID` según lo guardado en `localStorage`. |
| **Vista shell** | `resources/views/app.blade.php` | Inyecta el bundle Vite y el `<div id="app">` donde Vue se monta. |
| **Rutas web** | `routes/web.php` | Cualquier ruta devuelve la misma SPA (fallback) para que Vue Router maneje `/`, `/login`, etc. |

### Flujo típico de una petición

1. El usuario (o el formulario de login) fija el **ID del tenant**; Axios envía `X-Tenant-ID` y, si hay sesión, el **JWT** en `Authorization`.
2. Laravel aplica `TenantMiddleware` donde corresponda: si el tenant no existe, responde 404 JSON.
3. En rutas protegidas, `jwt.auth` valida el token; opcionalmente se compara el tenant del header con el del usuario del token.
4. Las respuestas del API son siempre **JSON**.

### Estructura relevante en el repo

```
app/Http/Controllers/Api/V1/AuthController.php   # registro, login, me, refresh, logout
app/Http/Middleware/TenantMiddleware.php         # cabecera X-Tenant-ID
app/Http/Middleware/JwtAuth.php                  # JWT + coherencia tenant
app/Models/User.php                              # JWT + HasRoles + tenant_id
app/Models/Tenant.php                            # modelo Stancl / tabla tenants
resources/js/                                    # Vue: router, stores, páginas, Axios
routes/api.php                                   # rutas bajo prefijo api/v1 (ver bootstrap/app.php)
```

---

## Qué se necesita para correr el proyecto

### Software instalado en tu máquina

| Requisito | Uso |
|-----------|-----|
| **PHP ≥ 8.2** | Ejecutar Laravel y Composer scripts (`artisan`, migraciones). |
| **Composer ≥ 2.x** | Instalar dependencias PHP (`vendor/`). |
| **Node.js ≥ 20** y **npm** | Instalar dependencias JS y ejecutar Vite (`npm run dev` / `npm run build`). |
| **Extensiones PHP habituales** | `openssl`, `pdo`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath` (según tu stack). |
| **Base de datos** | **SQLite** (rápido en desarrollo, archivo `database/database.sqlite`) o **MySQL 8** en entornos más cercanos a producción. |

### Variables de entorno imprescindibles

Tras copiar `.env.example` a `.env`:

- **`APP_KEY`** — `php artisan key:generate`
- **`JWT_SECRET`** — `php artisan jwt:secret`
- **Conexión a BD** — según elijas SQLite o MySQL en `.env`
- **`VITE_API_URL`** — URL base del API que usará el frontend en desarrollo (p. ej. `http://localhost:8000/api/v1`) si el navegador sirve la SPA desde otro puerto (Vite).

Sin PHP/Composer/Node o sin BD configurada, el proyecto no podrá migrar ni compilar el frontend.

---

## Instalación y ejecución

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Configura la base de datos en `.env` (SQLite o MySQL). Luego:

```bash
php artisan migrate
npm install
npm run dev
```

En **otra terminal**, el servidor HTTP de Laravel:

```bash
php artisan serve
```

Abre el frontend según la URL que muestre Vite (típicamente `http://localhost:5173`) y asegúrate de que `VITE_API_URL` apunte al backend (`php artisan serve` suele ser `http://127.0.0.1:8000`).

### Variables `.env` más usadas

| Variable | Descripción |
|----------|-------------|
| `APP_URL` | URL pública del backend (p. ej. `http://localhost:8000`). |
| `FRONTEND_URL` | URL del frontend en desarrollo (referencia / CORS si aplica). |
| `JWT_SECRET` | Secreto de firma JWT (generado con `jwt:secret`). |
| `JWT_TTL` | Minutos de vida del access token (por defecto 60). |
| `VITE_API_URL` | Base URL del API para Axios desde Vite. |

## API (`/api/v1`)

Todas las rutas del API requieren la cabecera **`X-Tenant-ID`** (UUID del tenant).

| Método | Ruta | Auth |
|--------|------|------|
| POST | `/auth/register` | No (devuelve JWT al registrar) |
| POST | `/auth/login` | No |
| GET | `/auth/me` | Bearer JWT |
| POST | `/auth/refresh` | Middleware `jwt.refresh` (renovación con ventana de refresh) |
| POST | `/auth/logout` | Bearer JWT |
| GET | `/patients` | Bearer JWT |
| GET | `/lab-results` | Bearer JWT (filtros: `?critical=1`, `?patient_id=`) |
| GET | `/lab-results/{id}` | Bearer JWT |
| POST | `/lab-results` | Bearer JWT |

Respuestas siempre en **JSON**.

---

## Módulo implementado — Valores críticos de laboratorio (Sprint 2)

**Estudiante:** José Pablo Carías Flores (carné 1890-23-7587) — módulo 22 del Cuadro 2 ("Valores críticos": indicador o alerta para resultados de laboratorio fuera de rango).

El análisis completo (arquitectura, stack y diseño del módulo) está en [`docs/sprint-1-analisis.md`](docs/sprint-1-analisis.md).

### Qué se implementó

- Tablas `patients` y `lab_results` (multitenant, `tenant_id` con FK a `tenants`).
- Modelo `LabResult` que calcula automáticamente el campo `status` (`normal`, `critico_alto`, `critico_bajo`) y el atributo `is_critical` al guardar, comparando `value` contra `reference_min`/`reference_max`.
- Endpoints `GET /api/v1/lab-results` (con filtros `critical=1` y `patient_id`), `GET /api/v1/lab-results/{id}`, `POST /api/v1/lab-results` y `GET /api/v1/patients`, todos bajo `tenant` + `jwt.auth`.
- Seeder (`LabResultSeeder`) con pacientes y resultados de ejemplo (algunos normales y otros críticos) para el tenant demo `san-marcos-demo`.
- Vista Vue **"Valores críticos"** (`resources/js/modules/lab-results/pages/LabResultsPage.vue`), accesible desde el menú principal, con:
  - Tabla de resultados (paciente, prueba, valor, rango de referencia, estado, fecha).
  - Resaltado visual y etiqueta de estado para resultados críticos.
  - Resumen de cantidad de resultados críticos.
  - Filtro "Mostrar solo valores críticos".
- Pruebas automatizadas (`tests/Feature/LabResultTest.php`) que validan el cálculo del estado, el filtro `critical=1` y la protección de las rutas.

### Cómo revisar / probar el módulo

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate --seed
npm install
npm run dev      # en otra terminal: php artisan serve
```

1. Inicia sesión (o regístrate) usando el tenant demo `00000000-0000-4000-8000-000000000001`.
2. Entra al menú **"Valores críticos"**.
3. Verifica que los resultados fuera de rango aparezcan resaltados con la etiqueta "Crítico (alto)" / "Crítico (bajo)".
4. Activa "Mostrar solo valores críticos" y confirma que solo se listan esos registros.
5. Corre las pruebas: `php artisan test --filter=LabResultTest`.

---

## Validación recomendada

```bash
php artisan route:list --path=api
php artisan config:clear
npm run build
php artisan test
```

---

## Resumen de trabajo realizado

| Sprint | Entregable | Ubicación |
|--------|------------|-----------|
| Sprint 1 — Análisis | Arquitectura, stack y diseño del módulo 22 ("Valores críticos") | [`docs/sprint-1-analisis.md`](docs/sprint-1-analisis.md) |
| Sprint 2 — Implementación | Migraciones, modelos, controladores, rutas, seeder, vista Vue y pruebas del módulo | Ver sección [Módulo implementado](#módulo-implementado--valores-críticos-de-laboratorio-sprint-2) |
| Sprint 3 — Diagramas UML | Casos de uso, clases y secuencia del módulo | [`docs/sprint-3-uml.md`](docs/sprint-3-uml.md) |

Durante la verificación funcional también se corrigieron dos errores detectados en el proyecto base:

- Un error de compatibilidad con PHP 8.4 en `app/Http/Middleware/JwtAuth.php` (colisión de nombre entre la clase `JwtAuth` y la fachada `JWTAuth`) que impedía levantar la API.
- Un ajuste en `database/seeders/LabResultSeeder.php` para que los datos de ejemplo se guarden con el estado (`normal`/`crítico`) correctamente calculado.

### Sobre el uso de herramientas de IA

Como apoyo puntual durante el desarrollo se utilizó un asistente de IA (Claude) para: depurar el error de compatibilidad con PHP 8.4 mencionado arriba, generar un primer borrador de los diagramas UML (Sprint 3) y del documento de análisis (Sprint 1), y redactar partes de esta documentación. Todo el código del módulo fue revisado, probado (`php artisan test`) y validado de forma funcional antes de subirlo al repositorio.

---

## Entrega esperada

El estudiante debe trabajar sobre su propio fork del repositorio y entregar en Canvas el enlace al repositorio forkeado, junto con una breve descripción del módulo implementado y los commits principales que evidencian su avance.
