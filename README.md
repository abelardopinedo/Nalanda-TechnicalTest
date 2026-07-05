# API de Gestión de Candidaturas y Evaluadores

Prueba técnica Backend Senior (Laravel) — API para gestionar candidaturas y evaluadores,
enfocada en arquitectura, desacoplamiento, SQL, patrones y escalabilidad.

## Índice

- [Arquitectura](#arquitectura)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Patrones usados — y deliberadamente evitados](#patrones-usados--y-deliberadamente-evitados)
- [Decisiones que reconsideré](#decisiones-que-reconsideré)
- [Decisiones de alcance](#decisiones-de-alcance)
- [Escalabilidad](#escalabilidad)
- [Testing](#testing)
- [Cómo ejecutar el proyecto](#cómo-ejecutar-el-proyecto)
- [Documentación de la API](#documentación-de-la-api)
- [Con más tiempo](#con-más-tiempo)

---

## Arquitectura

El enunciado exige poder *"reemplazar la capa de datos sin reescribir la lógica de negocio."*
Esa fue la decisión central: **arquitectura Hexagonal (Ports & Adapters) con un split CQRS-lite**
entre escritura y lectura.

- **Escrituras** (registrar una candidatura, validarla, asignar un evaluador) pasan por
  entidades de dominio y objetos de valor sin dependencia de framework, y una interfaz de
  repositorio — Laravel/Eloquent es un detalle de implementación detrás de esa interfaz,
  reemplazable sin tocar la lógica de negocio.
- **Lecturas** (el listado consolidado, el resumen) evitan la hidratación de dominio por
  completo y devuelven DTOs construidos con SQL optimizado + Collections. Forzar un reporte
  grande a través de objetos de dominio sería lento e innecesario; el lado de lectura está
  moldeado por su restricción real (rendimiento), el de escritura por la suya (invariantes de
  negocio).

```
        HTTP (Controllers, FormRequests, API Resources)   ← Laravel, delgado
                          │  llama a
        ┌─────────────────▼─────────────────┐
        │      CAPA DE APLICACIÓN           │   Casos de uso, DTOs/Commands/Queries,
        │  (orquestación, sin framework)    │   Puertos (interfaces) hacia el exterior
        └─────────────────┬─────────────────┘
                          │  depende de
        ┌─────────────────▼─────────────────┐
        │        CAPA DE DOMINIO            │   Entidades, Value Objects,
        │   (PHP puro, cero Illuminate\)    │   Cadena de validación, interfaces de repositorio
        └───────────────────────────────────┘
                          ▲  implementada por
        ┌─────────────────┴─────────────────┐
        │     CAPA DE INFRAESTRUCTURA       │   Repositorios Eloquent + mappers, caché Redis,
        │        (adaptadores Laravel)      │   exportación Excel, Mailer, Queue
        └───────────────────────────────────┘
```

Las dependencias apuntan hacia adentro. El dominio no tiene conocimiento de Laravel;
infraestructura implementa las interfaces que el dominio define.

Consideré DDD completo (agregados, event sourcing, un command bus) y lo descarté — para un CRUD
de candidaturas añadiría ceremonia que un compañero tendría que aprender sin beneficio real. El
objetivo fue desacoplar donde el enunciado lo exige, y mantener todo simple en lo demás.

## Estructura del proyecto

Dos raíces de código físicas hacen el desacoplamiento verificable, no solo declarado:

```
src/                          # Sin imports de Illuminate\. PHP puro.
  Candidacy/
    Domain/
      Candidacy.php                Entidad raíz
      Email.php, YearsOfExperience.php, CvText.php     Value Objects
      CandidacyStatus.php          Enum con transiciones guardadas
      CandidacyRepository.php      Interfaz (puerto)
      Event/           CandidacyRegistered.php, EvaluatorAssigned.php, CandidacyValidated.php
      Exception/       InvalidCandidacyStatusTransition.php, ...
      Validation/      ValidationRule.php (interfaz), Rules/*, ValidationChain.php
    Application/
      UseCase/         RegisterCandidacy.php, ValidateCandidacy.php, AssignEvaluator.php,
                        BulkAssignEvaluator.php, ...
      Command/         AssignEvaluatorCommand.php, BulkAssignEvaluatorCommand.php, ...
      TransactionManager.php, CandidacyBulkLocker.php    Puertos hacia infraestructura
      Exception/       CandidacyNotFoundException.php, EvaluatorNotFoundException.php

app/                          # Laravel — los adaptadores.
  Http/                Controllers, Requests, Resources, rutas api/v1
  Infrastructure/
    Persistence/       Modelos Eloquent, EloquentCandidacyRepository, CandidacyMapper,
                        EloquentCandidacyBulkLocker, implementaciones en memoria para tests
    Query/             CandidacyEvaluatorListingQuery.php, CandidacySummaryQuery.php
                        (query builder crudo, lado de lectura)
    Cache/              CachedCandidacyEvaluatorListingQuery.php, CachedCandidacySummaryQuery.php
    Idempotency/        IdempotentJsonResponse.php (Redis, compartido entre endpoints)
    Report/             MaatwebsiteReportGenerator.php
  Jobs/                GenerateReportJob.php
  Listeners/           WriteDomainEventToActivityLog.php, InvalidateCandidacyReadCache.php
  Providers/           bindings: interfaz → implementación
```

Un **repositorio en memoria (fake)** implementa la misma interfaz que el de Eloquent, usado en
tests unitarios — es la prueba tangible de que la lógica de negocio no depende de Eloquent.

## Patrones usados — y deliberadamente evitados

**Usados, con su justificación:**

- **Chain of Responsibility** — la cadena de validación (ver abajo). Mencionado directamente en
  el enunciado.
- **Repository + Mapper** — permite reemplazar la capa de datos sin tocar la lógica de negocio.
- **Ports & Adapters (Hexagonal)** — el framework como un detalle.
- **Value Objects** — `Email`, `YearsOfExperience`, `CvText` centralizan invariantes
  estructurales.
- **DTO / Command / Query** — datos limpios cruzando los límites de capas.
- **CQRS-lite** — lecturas y escrituras tienen restricciones opuestas; separarlas es una
  decisión de rendimiento, no solo una preferencia arquitectónica.
- **Eventos de dominio + listeners** — desacoplan el log de auditoría y la invalidación de caché
  de las acciones que los disparan. **Registrados de forma explícita**, vía `Event::listen(...)`
  en `App\Infrastructure\Providers\EventServiceProvider::boot()` — no mediante el auto-discovery
  por convención de `app/Listeners` (deshabilitado en `bootstrap/app.php` con
  `->withEvents(discover: false)`). Cada otro binding de esta app (`RepositoryServiceProvider`,
  `ValidationServiceProvider`) es explícito, así que dejar el wiring de eventos implícito sería
  la única excepción a ese criterio. El registro explícito hace que el mapa completo
  evento→listener sea legible en un solo lugar y buscable (`grep Event::listen`), en vez de
  tener que inferirse de los type-hints de `handle()` dispersos por `app/Listeners` — y es más
  seguro durante un refactor, ya que renombrar o ampliar un tipo de parámetro no puede cambiar
  silenciosamente qué se dispara.

**Deliberadamente no usados, y por qué:**

- **Sin command bus/mediator** — invocar los casos de uso directamente es más claro a esta
  escala.
- **Sin patrón Specification** — la cadena de validación ya expresa las reglas.
- **Sin máquina de estados completa** — un enum con backing y métodos de transición guardados es
  suficiente.
- **Sin clases "service" genéricas** — casos de uso de responsabilidad única en su lugar.

### Cadena de validación — acumulativa, no de corte temprano

El requisito #2 pide un endpoint que informe si una candidatura es válida **y por qué**. Un
Chain of Responsibility de libro se detiene en el primer handler que resuelve la solicitud — pero
eso solo reportaría el *primer* fallo. En su lugar, la cadena ejecuta todas las reglas y acumula
todos los resultados:

```php
interface ValidationRule {
    public function check(Candidacy $candidacy): RuleResult;
}

final class ValidationChain {
    public function run(Candidacy $c): ValidationReport {
        $results = array_map(fn($rule) => $rule->check($c), $this->rules);
        return new ValidationReport($results);
    }
}
```

Reglas: `HasCvRule`, `ValidEmailRule`, `MinimumExperienceRule` (≥ 2 años), `CvMinimumLengthRule`,
`DisposableEmailRule`. Añadir una regla implica crear una clase y registrarla en
`config/candidacy_validation.php` — cero cambios a las reglas existentes (Open/Closed).

## Decisiones que reconsideré

Las documento porque el razonamiento detrás de un cambio de rumbo vale más que fingir que la
primera versión nunca existió.

**Claves primarias: UUIDv4 → se consideró auto-increment → UUIDv7.**
Los enteros auto-increment son los más rápidos para los joins/agregaciones que evalúa este
enunciado (más pequeños, secuenciales, sin fragmentación del B-tree). UUIDv4 es más seguro
contra la enumeración, pero las inserciones aleatorias fragmentan el índice y perjudican
justamente las consultas que se evalúan. **UUIDv7** es el punto intermedio: tiene prefijo de
timestamp, por lo que las inserciones se mantienen razonablemente secuenciales (recuperando la
mayor parte de la localidad de auto-increment) manteniéndose no adivinable. Se almacena como
columna `CHAR(36)` por ergonomía de desarrollo; a escala de producción real, `BINARY(16)`
recuperaría el overhead de almacenamiento/índice restante.

Todas las claves primarias persistidas (`candidacies`, `evaluators`, `activity_log`, `reports`)
se generan con `Str::uuid7()`, nunca con `Str::uuid()` (v4) — ver
`EloquentCandidacyRepository::nextIdentity()`, `InMemoryCandidacyRepository::nextIdentity()`,
`CandidacyMapper::eventToActivityLogAttributes()` y `EvaluatorSeeder`. UUIDv4 es completamente
aleatorio, por lo que las inserciones caen en puntos aleatorios del B-tree de la clave primaria,
causando page splits y fragmentación del índice a medida que las tablas crecen. UUIDv7 embebe un
timestamp en milisegundos en sus bits iniciales, por lo que los ids generados son monótonamente
crecientes — las inserciones se mantienen casi siempre al final del índice, el mismo beneficio de
localidad que un entero auto-increment, manteniendo el id único globalmente y no adivinable en
los bits finales aleatorios.

*Regla general:* siempre generar nuevas claves primarias con `Str::uuid7()`. `Str::uuid()` (v4)
es aceptable solo para valores descartables/de fixtures de test que nunca se persisten como clave
primaria de una fila real.

**Validación de registro vs. validación de negocio — se confundieron brevemente.**
Al principio, el propio registro rechazaba candidaturas con poca experiencia o un CV corto. Eso
era un bug: el requisito #1 (registro) solo debe exigir validez **estructural** (formato de
email válido, experiencia entera ≥ 0, nombre/CV no vacíos). Las reglas del requisito #2 (≥ 2
años de experiencia, etc.) son reglas de **negocio** y solo pertenecen a la `ValidationChain`,
disparada explícitamente vía `POST /candidacies/{id}/validate`. Cualquier candidatura bien
formada puede registrarse sin importar cómo puntúe luego contra la cadena.

**Flujo de estados de la candidatura — simplificado dos veces.**
El enum original tenía un estado `UNDER_REVIEW` al que nada transicionaba nunca — se eliminó por
ser inalcanzable/YAGNI. También consideré un estado reversible `IN_CONSIDERATION` para que un
reviewer pudiera anular una decisión automática de validar/rechazar, y un atajo
`REJECTED → ASSIGNED`. Ambos quedaron fuera de alcance: ninguno lo pide el enunciado, ambos
añaden un segundo conjunto de reglas a razonar, y `ASSIGNED` debe significar de forma confiable
"esta candidatura fue validada" — algo que un reviewer puede confiar sin revisar el historial.
**Modelo final**: `RECEIVED → VALIDATED | REJECTED` (automático, dirigido por la cadena, un solo
endpoint) → `VALIDATED → ASSIGNED`. `REJECTED` es terminal. Ver
[Decisiones de alcance](#decisiones-de-alcance).

**Agregaciones del listado consolidado — se evitó una trampa de `GROUP_CONCAT`.**
Una sola query con `GROUP_CONCAT` para "todos los emails evaluados por esta persona" choca con
el límite `group_concat_max_len` de MySQL (1024 bytes por defecto) y trunca silenciosamente bajo
carga — un bug de corrección escondido en un valor de configuración. Además repite el mismo
string agregado en cada fila de ese evaluador. En su lugar: se pagina las candidaturas con un
join normal, luego se ejecuta **una sola** query de agregación para los evaluadores distintos de
esa página, y se combina con una `Collection` (`keyBy`/`map`). Esto acota el cómputo de
agregados a los evaluadores realmente presentes en la página y evita por completo el límite de
truncamiento. Verificado con un test de integración contra MySQL real (ver
[Testing](#testing)).

## Decisiones de alcance

**Las decisiones sobre una candidatura son finales — sin flujo de reconsideración.**
El requisito #2 pide un único endpoint que evalúe una candidatura y reporte por qué — no pide un
flujo de revisión/anulación, y los estados son explícitamente opcionales en el enunciado.
Consideré un estado reversible para que un reviewer pudiera anular una decisión automática, y
decidí no implementarlo: no es un requisito explícito, y el tiempo estaba mejor invertido en los
requisitos que sí se evalúan explícitamente (SQL, escalabilidad, testing). *Con más tiempo*,
añadiría una anulación por parte de un reviewer — un estado y endpoint dedicados, totalmente
auditados vía el log de actividad — ya que los flujos reales de contratación sí necesitan una
corrección humana en el proceso.

**Un solo endpoint para validar, no dos.**
El enunciado dice *"crear un endpoint"* — en singular. `POST /api/v1/candidacies/{id}/validate`
es el único endpoint para este requisito: evalúa la candidatura contra la `ValidationChain` y
confirma el resultado en la misma llamada.

- Si todas las reglas pasan, la candidatura transiciona `RECEIVED → VALIDATED`.
- Si alguna regla falla, la candidatura transiciona `RECEIVED → REJECTED`.
- En ambos casos la respuesta es `200` con `{ isValid, passed, failed }` — un rechazo es un
  resultado válido y esperado de llamar a este endpoint, no un error del cliente. El código HTTP
  describe si la petición se procesó correctamente, no si el resultado de negocio fue favorable;
  el cliente no tiene nada que corregir y reintentar, así que `422` sería incorrecto aquí. Un
  buen ejemplo análogo: un endpoint de autorización de pago devuelve
  `200 { approved: false, reason: "insufficient funds" }`, no `402`.
- Si la candidatura no está actualmente en `RECEIVED`, la respuesta es `409` — este sí es un
  verdadero error del cliente (llamar a esta acción en el estado equivocado). Es una decisión
  única y final, sin variante de solo lectura y sin una acción manual de rechazo separada.

Inicialmente construí una variante `GET` de solo lectura y la eliminé para ajustarme al
requisito literal.

**Sin cron/procesamiento asíncrono para la validación.**
Todas las reglas de validación actuales son verificaciones síncronas en memoria — sin llamadas
externas, por lo que el endpoint responde de inmediato. Si una futura regla requiriera un
servicio externo (p. ej. una API de reputación de email), encolaría el paso de validación en vez
de bloquear la petición. No se implementa ahora porque ninguna regla actual lo necesita, y
cambiaría el contrato del endpoint (resultado síncrono → sondeo de resultado) sin beneficio
presente. Los propios ejemplos de escalabilidad del enunciado (#7) señalan el reporte Excel y la
asignación masiva como las operaciones realmente costosas — esas sí están encoladas/bloqueadas;
la validación no.

**Interpretación del listado consolidado.**
"Listado consolidado" devuelve **una fila por candidatura** (filtrando solo las que tienen
evaluador asignado), con dos columnas agregadas a nivel de evaluador (total asignadas, emails
concatenados) repetidas por fila. Esta lectura se confirma con el orden por defecto exigido —
años de experiencia — que es un campo a nivel de candidato, y solo tiene sentido si las filas son
candidaturas y no evaluadores.

**Interpretación del reporte Excel ("50 candidatos por página").**
Interpretado como 50 filas por **hoja** (`WithMultipleSheets`, en bloques de 50), no como
paginación de impresión — la lectura más natural de "página" en un contexto de hoja de cálculo.
El reporte reutiliza exactamente el mismo whitelist de filtros que el listado consolidado
(`GET /candidacies`, incluyendo el filtro por rango de `years_of_experience`), por lo que un
reporte puede acotarse (p. ej. "solo candidatos con ≥5 años de experiencia") de la misma forma
que el listado — el enunciado vincula explícitamente el reporte con *"el Listado consolidado
visto en el punto 4."*

**Tabla de reportes — estado durable, no solo disparar y olvidar.**
Se añadió una tabla `reports` (`PENDING` / `COMPLETED` / `FAILED` — sin estado `PROCESSING`, ya
que nada en el sistema distingue "en cola" de "ejecutándose" de una forma que algún consumidor
trate de forma diferente) que respalda la generación de reportes, para que un cliente pueda
sondear `GET /reports/{id}` en vez de depender solo del email de finalización, y para que el
reintento por idempotencia tenga un registro durable e inspeccionable en vez de solo una entrada
de caché.

**Visibilidad de la caché.**
`GET /candidacies` y `GET /candidacies/{id}/summary` devuelven un header `X-Cache: HIT`/`MISS` —
barato de añadir, y hace que el comportamiento de la capa de caché sea verificable a simple
vista (`curl` dos veces, ver el header cambiar) en vez de solo demostrable vía contadores de
queries internos en los tests.

**Asignación masiva — sin job de cola serializado, con bloqueo de filas.**
El requisito #7 pide manejar concurrencia en asignaciones masivas, no necesariamente
procesarlas en cola. `POST /evaluators/{id}/assign-bulk` corre síncronamente dentro de una
transacción con `lockForUpdate` sobre las filas afectadas (bloqueadas en orden ascendente de id
para evitar deadlocks entre lotes que se solapan en orden distinto). El verdadero backstop no es
una restricción única adicional — `candidacies.id` ya es la clave primaria, así que una fila
físicamente no puede tener más de un valor de `evaluator_id` a la vez — sino el hecho de que el
estado de cada candidatura se **re-verifica después de adquirir el lock**, no antes: una segunda
transacción que se solapa en una candidatura ya reclamada por la primera la verá `ASSIGNED`
(no `VALIDATED`) en cuanto obtenga su propio lock, y la omite en vez de sobrescribirla. Las
candidaturas no `VALIDATED` (o inexistentes) en un lote se omiten y se reportan en la respuesta
(`{ assigned: [...], skipped: [{ id, reason }] }`) en vez de fallar toda la petición.

## Escalabilidad

- **Caché (Redis)** — el listado consolidado y el resumen se cachean por combinación de
  filtro/orden/página y por id de candidatura, usando cache tags para invalidación selectiva
  (por evaluador/candidatura) en vez de vaciar todo. Se invalida vía los mismos listeners de
  eventos de dominio usados para el log de actividad. Protegido con `Cache::lock` contra
  stampede cuando una clave caliente expira: un segundo request contendido espera a que el
  primero termine en vez de recomputar en paralelo, y si el que tiene el lock tarda demasiado,
  degrada de forma controlada calculando directamente. Nota: `CACHE_STORE` por defecto es
  `database` (caché genérico de Laravel), pero estas dos rutas de lectura usan explícitamente
  `Cache::store('redis')` — Redis debe estar arriba (ya lo está vía `compose.yaml`) para que
  ambas funcionen, aunque el store por defecto de la app diga otra cosa.
- **Idempotencia** —
  - **Generación de reportes**: `Idempotency-Key` respaldada por la tabla `reports` (columna
    `idempotency_key`, única, con manejo explícito de la violación de esa restricción cuando dos
    peticiones simultáneas ganan la carrera al mismo tiempo). Se eligió respaldo en base de
    datos porque la tabla ya necesita trackear estado/historial/redescarga del reporte; la
    idempotencia se apoya en infraestructura que existe por otras razones.
  - **Asignación de evaluadores (individual y masiva)**: `Idempotency-Key` respaldada por Redis
    (`Cache`, TTL configurable), mismo mecanismo compartido entre ambos endpoints bajo distinto
    prefijo de clave. No hay necesidad secundaria de persistir el historial de peticiones de
    asignación a largo plazo, así que un mecanismo más liviano es el adecuado.
- **Concurrencia** — la asignación masiva de evaluadores (`POST /evaluators/{id}/assign-bulk`,
  el *"asignaciones masivas"* del requisito #7) envuelve las filas afectadas en una transacción
  con `lockForUpdate`. Ver [Decisiones de alcance](#decisiones-de-alcance) para el detalle de por
  qué no hace falta una restricción única adicional.
- **Colas** — la generación del reporte Excel corre en cola (`ShouldQueue`, conexión
  `database`), notificando por email solo después de confirmar que el archivo fue escrito
  (evitando una condición de carrera donde el email podría llegar antes de que el reporte
  exista). El worker corre como su propio servicio (`queue`) en `compose.yaml`, arriba
  automáticamente junto al resto del stack — ver
  [Cómo ejecutar el proyecto](#cómo-ejecutar-el-proyecto).

## Testing

- **Unitarios (sin framework)**: value objects (`Email`, `YearsOfExperience`, `CvText`), cada
  regla de validación, el comportamiento acumulativo de la `ValidationChain`, y casos de uso
  probados contra el repositorio fake en memoria (sin BD) — prueba de que el desacoplamiento
  funciona de verdad.
- **De funcionalidad (feature)**: el listado consolidado (agregaciones, orden por defecto,
  filtro, paginación), el endpoint de validación (aprobado/rechazado/409), la asignación
  individual y masiva (incluyendo el guard de solo `VALIDATED` y el escenario de concurrencia
  simulado secuencialmente), el endpoint de resumen, comportamiento hit/miss de caché, reintento
  de idempotencia (reportes y asignación, incluyendo bajo condición de carrera real vía
  `DB::listen`), generación de reportes.
- **De integración (BD real)**: el SQL de agregación del listado consolidado contra MySQL real
  (no SQLite) — la fidelidad de dialecto importa aquí (comportamiento de `GROUP_CONCAT`, forma
  exacta de la query) más que la velocidad del test; y la exportación a Excel contra MySQL real.

## Cómo ejecutar el proyecto

```bash
cp .env.example .env
docker compose run --rm --no-deps laravel.test composer install --ignore-platform-reqs
docker compose up -d
```

`docker/8.3/` (imagen de la app) y `docker/mysql/` (script de init) son copias versionadas —no
`.gitignore`d— de lo que Sail trae dentro de `vendor/laravel/sail/...`. Se sacaron de `vendor/`
a propósito: si `compose.yaml` dependiera de rutas dentro de `vendor/`, el primer
`docker compose run`/`up` en un clon nuevo (sin `vendor/` todavía) fallaría al no encontrar el
contexto de build antes de poder correr `composer install`. Con esto, estos tres comandos
alcanzan, sin pasos previos.

`--no-deps` es necesario: `laravel.test` depende de `migrate` (ver abajo), y `migrate` en sí
necesita `vendor/` para poder correr — sin `--no-deps`, este `composer install` intentaría
levantar `migrate` primero, que fallaría (todavía no existe `vendor/`), bloqueando el propio
`composer install` que lo crearía.

`docker compose up -d` levanta cinco servicios de `compose.yaml`: `migrate` (corre
`php artisan migrate --force && php artisan db:seed --force` y termina — el resto espera a que
termine bien vía `service_completed_successfully` antes de arrancar), `laravel.test` (la app),
`queue` (worker de colas, `php artisan queue:work` con `restart: unless-stopped` — necesario para
la generación de reportes ya que `QUEUE_CONNECTION=database`, arranca solo), `mysql` y `redis`.
La migración y el seed por defecto (`DatabaseSeeder`: `EvaluatorSeeder` — 3 evaluadores — +
`CandidacySeeder` — una candidatura por etapa: received/validated/assigned/rejected) ya quedan
listos solos, sin ningún paso manual.

Después (`<container>` = nombre del servicio de la app, ver `docker ps`, por defecto
`<carpeta>-laravel.test-1`):

```bash
docker exec <container> php artisan key:generate
docker exec <container> php artisan test
```

### Cambiar el seed

`migrate` corre el seed por defecto (`DatabaseSeeder`) en cada `docker compose up` —
`EvaluatorSeeder` es idempotente (`firstOrCreate`), pero `CandidacySeeder` no: correrlo de nuevo
agrega candidaturas de muestra duplicadas en vez de no hacer nada. Para probar otro escenario,
resetea la base y corre el seeder que quieras a mano:

```bash
docker exec <container> php artisan migrate:fresh          # sin --seed: deja las tablas vacías
docker exec <container> php artisan db:seed                # vuelve a correr el seed por defecto
docker exec <container> php artisan db:seed --class=BulkReportReceivedSeeder             # 5 evaluadores + 120 RECEIVED
docker exec <container> php artisan db:seed --class=BulkReportValidatedRejectedSeeder     # 5 evaluadores + 60 VALIDATED + 60 REJECTED
docker exec <container> php artisan db:seed --class=BulkReportSingleEvaluatorSeeder       # 1 evaluador + 51 candidatos
docker exec <container> php artisan db:seed --class=BulkReportSingleEvaluatorFullSheetSeeder
```

En local, `MAIL_MAILER=log`: los emails de notificación de reportes (listos/fallidos) no se
envían de verdad, quedan escritos en `storage/logs/laravel.log`. Para verlos renderizados en una
UI, apunta `MAIL_MAILER` a Mailpit/Mailhog u otro proveedor SMTP local.

## Documentación de la API

La documentación OpenAPI 3.1 se genera automáticamente a partir de las rutas/Form
Requests/Resources con [Scramble](https://scramble.dedoc.co) — sin necesidad de anotación
manual.

```
http://localhost/docs/api        ← interfaz interactiva
http://localhost/docs/api.json   ← especificación OpenAPI en crudo
```

Un snapshot estático se exporta a `api.json` en la raíz del repo (`php artisan scramble:export`).

Gap conocido y aceptado por ahora: los endpoints de asignación (individual y masiva) documentan
un cuerpo de respuesta genérico (`{"type": "object"}`) en vez de su forma real, porque ambos
construyen su respuesta a través de `IdempotentJsonResponse` (un helper compartido que envuelve
la operación en una closure), lo que oculta la expresión `Resource::make(...)->response()` del
análisis estático de Scramble. No afecta el comportamiento real del endpoint, solo la
completitud de su documentación generada.

## Con más tiempo

- Una anulación por parte de un reviewer para reconsiderar una decisión automática de
  validar/rechazar, totalmente auditada.
- Filtro por rango de fechas sobre `assigned_at` en el listado consolidado (actualmente soporta
  coincidencia exacta y, para `years_of_experience`, filtro por rango).
- Almacenamiento `BINARY(16)` para las claves primarias UUIDv7 a escala de producción real.
- Jobs de cola serializados por evaluador para la asignación masiva, además del bloqueo de filas
  actual, si el volumen de asignaciones masivas creciera lo suficiente como para justificar
  también aislamiento a nivel de job.
- Reestructurar `IdempotentJsonResponse` (separar en `replay()`/`remember()`) para que Scramble
  pueda inferir la forma real de la respuesta en los endpoints de asignación (ver gap señalado
  arriba).
