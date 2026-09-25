# RYS · Informe de Actividades

Aplicación web para que **Grupo RYS S.A.S** construya los informes de actividades de sus
contratos con alcaldías y entidades públicas: carga desde Excel, evidencia fotográfica,
plantillas reutilizables, trazabilidad de cambios y una plantilla de informe con la
identidad de la marca (negro y dorado).

> Documento de alcance original: [`PLAN_MVP.md`](PLAN_MVP.md)

---

## Tabla de contenido

- [Estado del proyecto](#estado-del-proyecto)
- [Stack](#stack)
- [Requisitos](#requisitos)
- [Instalación](#instalación)
- [Configuración](#configuración)
- [Comandos útiles](#comandos-útiles)
- [Funcionalidades](#funcionalidades)
- [Rutas principales](#rutas-principales)
- [Roles y permisos](#roles-y-permisos)
- [Arquitectura](#arquitectura)
- [Modelo de datos](#modelo-de-datos)
- [Flujos clave](#flujos-clave)
- [Calidad y pruebas](#calidad-y-pruebas)
- [Convenciones](#convenciones)
- [Pendientes](#pendientes)

---

## Estado del proyecto

MVP en desarrollo activo. Lo construido está probado (`pint`, `phpstan`, `php artisan test`).

| Área | Estado |
|------|--------|
| Autenticación (login, registro, 2FA, passkeys) | ✅ |
| Roles y módulo de usuarios (crear, editar, habilitar/deshabilitar, eliminar) | ✅ |
| Configuración de la empresa (logo, firma, representante) | ✅ |
| Listado de informes (paginación, búsqueda, filtros, orden, estadísticas) | ✅ |
| Importación desde Excel por partes con vista previa y conflictos | ✅ |
| Asistente de edición de 6 pasos con autoguardado y checklist | ✅ |
| Biblioteca: plantillas de texto y catálogo de ítems | ✅ |
| Evidencia fotográfica: subida local (optimizada) y enlaces de Google Drive | ✅ |
| Trazabilidad (bitácora de cambios por campo) | ✅ |
| Generación de PDF en cola | ⏳ (hoy hay vista previa imprimible) |
| Exportar el informe a Excel | ⏳ |

---

## Stack

- **PHP 8.3** + **Laravel 13**
- **Livewire 4** (componentes de una sola clase) + **Flux UI 2** + **Tailwind CSS 4**
- **MySQL** (MAMP en local)
- **Laravel Fortify** para autenticación (2FA TOTP, códigos de recuperación, passkeys)
- **OpenSpout** para leer `.xlsx` en streaming
- **GD** (extensión de PHP) para redimensionar y comprimir fotos
- **Vite** para el build de assets

---

## Requisitos

- PHP **8.3+** con extensiones `gd`, `mbstring`, `openssl`, `pdo_mysql`, `zip`
- Composer 2
- Node 20+ / npm
- MySQL 8 (o MariaDB)

---

## Instalación

```bash
# 1. Dependencias
composer install
npm install

# 2. Entorno
cp .env.example .env
php artisan key:generate

# 3. Base de datos (crear la BD y ajustar credenciales en .env)
php artisan migrate --seed

# 4. Enlace de almacenamiento (logo, firma y fotos)
php artisan storage:link

# 5. Assets
npm run build

# 6. Servir (MAMP, Herd o artisan)
php artisan serve
```

> **MAMP:** el proyecto suele servirse en
> `http://localhost:8888/rys-events/public/index.php`. Tras agregar o cambiar vistas hay que
> ejecutar `npm run build`, porque Tailwind genera las clases en el build.

---

## Configuración

Variables relevantes de `.env`:

```dotenv
APP_NAME="Grupo RYS"
APP_URL=http://localhost:8888/rys-events/public/index.php

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=8889
DB_DATABASE=rys_automatizacion_db
DB_USERNAME=root
DB_PASSWORD=root
```

Notas:

- `APP_URL` se usa para construir URLs. La app se sirve bajo `public/index.php`, por eso el
  almacenamiento público se referencia con `asset('storage/...')` (que respeta el root de la
  petición) y no con `Storage::url()`.
- El usuario administrador se crea/actualiza con el seeder (`DatabaseSeeder`).
- El **tema es claro fijo** (la identidad de marca no tiene modo oscuro).

---

## Comandos útiles

```bash
php artisan migrate --seed     # migrar y sembrar (admin, DIVIPOLA, biblioteca)
php artisan db:seed --force    # volver a sembrar
php artisan storage:link       # enlace de storage (una vez)
npm run build                  # compilar assets (tras cambios en vistas)
npm run dev                    # Vite en modo desarrollo

composer lint                  # Pint (formato)
composer types:check           # PHPStan (larastan)
php artisan test               # suite completa
composer test                  # lint:check + types:check + tests
```

---

## Funcionalidades

### Autenticación y cuentas
- Login/registro, verificación de correo, recuperación de contraseña.
- **2FA (TOTP)** con códigos de recuperación y opción de confiar 30 días.
- **Passkeys** (WebAuthn).
- **Habilitar/deshabilitar** usuarios: un usuario deshabilitado no puede ingresar y su sesión
  abierta se cierra automáticamente.

### Roles
- `admin` (administrador) y `editor`.
- Solo `admin` accede a Empresa y Usuarios; la Biblioteca y los Informes están disponibles para
  ambos roles.

### Empresa
- Razón social, NIT, contacto, logo y firma escaneada, representante legal. Se usan en la
  portada y el cierre del informe.

### Informes
- **Listado** con paginación, búsqueda (contrato, evento, asunto, municipio), filtros por estado
  y departamento, orden y tamaño de página; estadísticas y estado de avance.
- **Importación desde Excel** por partes (ver [flujos](#flujos-clave)).
- **Asistente de edición de 6 pasos** con autoguardado, navegación libre y checklist.
- **Vista previa imprimible** con la plantilla visual del informe.

### Biblioteca
- **Plantillas de texto** con variables (`{municipio}`, `{departamento}`, `{evento}`,
  `{fecha_inicio}`, `{fecha_fin}`, `{dias}`, `{artista}`, `{contrato}`) y botón "Usar plantilla".
- **Catálogo de ítems** (especificación, narrativa, unidad y cantidad sugeridas) con
  "Importar del catálogo" en los pasos 3 y 4.

### Evidencia fotográfica
- **Subida local** (múltiple) con optimización automática (JPEG, lado máximo 1600 px, miniatura
  de 400 px) vía GD.
- **Enlaces de Google Drive**: pegar enlaces de archivo (se muestran por su miniatura) o de
  carpeta (vista incrustada). La carpeta debe estar compartida como *"cualquiera con el enlace"*.
- Reordenar fotos (subir/bajar), editar la leyenda y eliminar.

### Trazabilidad
- Cada cambio de campo en un informe o ítem queda en `report_activity_logs` con **quién, cuándo,
  valor anterior, valor nuevo y origen** (`app` o `excel`). Se ve como línea de tiempo en la
  vista del informe.

---

## Rutas principales

| Método | Ruta | Nombre | Componente / Controlador |
|--------|------|--------|--------------------------|
| GET | `/` | `home` | redirección |
| GET | `/dashboard` | `dashboard` | `pages::reports.index` |
| GET | `/informes/importar` | `reports.import` | `pages::reports.import` |
| GET | `/informes/plantilla` | `reports.template` | descarga `.xlsx` |
| GET | `/informes/{report}/editar` | `reports.edit` | `pages::reports.wizard` |
| GET | `/informes/{report}` | `reports.show` | `ReportController@show` |
| GET | `/informes/{report}/vista-previa` | `reports.preview` | `ReportController@preview` |
| GET | `/biblioteca/catalogo` | `catalog.index` | `pages::catalog.index` |
| GET | `/biblioteca/plantillas` | `templates.index` | `pages::templates.index` |
| GET | `/empresa` | `company.edit` | `pages::company.edit` *(admin)* |
| GET | `/usuarios` | `users.index` | `pages::users.index` *(admin)* |
| GET | `/settings/profile` | `profile.edit` | `pages::settings.profile` |
| GET | `/settings/security` | `security.edit` | `pages::settings.security` |
| GET | `/settings/appearance` | `appearance.edit` | `pages::settings.appearance` |

---

## Roles y permisos

| Recurso | admin | editor |
|---------|:-----:|:------:|
| Informes (ver/editar/importar) | ✅ | ✅ |
| Biblioteca (catálogo y plantillas) | ✅ | ✅ |
| Empresa | ✅ | ❌ |
| Usuarios y roles | ✅ | ❌ |

El middleware `admin` (`App\Http\Middleware\EnsureUserIsAdmin`) protege las rutas de
administración. El middleware `App\Http\Middleware\EnsureUserIsActive` corre en cada petición web
y expulsa a los usuarios deshabilitados.

---

## Arquitectura

La lógica de negocio vive **fuera** de los componentes de Livewire, en servicios y acciones
pequeñas con una única responsabilidad. Los componentes de página se encargan de la interfaz y
de orquestar.

```
app/
├── Actions/
│   ├── Fortify/                 # crear usuario y reset de contraseña (Fortify)
│   └── Reports/                 # casos de uso de informes
│       ├── AddReportItemToReport.php   # crea ítem con ref y orden
│       ├── StoreItemPhotos.php         # optimiza y guarda fotos subidas
│       └── SyncItemDriveLinks.php      # sincroniza enlaces de Drive
├── Concerns/                    # traits de validación reutilizables
├── Enums/UserRole.php           # roles tipados
├── Http/
│   ├── Controllers/ReportController.php
│   └── Middleware/              # EnsureUserIsAdmin, EnsureUserIsActive
├── Models/                      # Eloquent (Report, ReportItem, …)
├── Observers/                   # registran cambios en la bitácora
├── Services/
│   ├── Photos/PhotoOptimizer.php
│   ├── ReportActivity/ReportActivityLogger.php
│   ├── ReportImport/            # lector, normalizador, previewer y applier
│   └── Text/TextTemplateRenderer.php
└── Providers/                   # AppServiceProvider, FortifyServiceProvider

resources/views/
├── layouts/                     # app (sidebar) y auth
├── pages/                       # componentes Livewire (una clase por archivo)
│   ├── auth/ · settings/ · reports/ · catalog/ · templates/ · users/ · company/
├── reports/                     # show y vista previa imprimible
└── components/                  # componentes Blade reutilizables
```

**Principios aplicados (SOLID):**

- **S — Responsabilidad única:** cada acción/servicio hace una cosa (leer Excel, aplicar el
  import, optimizar fotos, registrar actividad…). Los componentes no contienen reglas de negocio
  pesadas.
- **O — Abierto/cerrado:** los observers de `Report`/`ReportItem` añaden trazabilidad sin tocar
  los modelos ni el importador.
- **L / I — Sustitución e interfaces:** dependencias inyectadas por el contenedor (p. ej.
  `AddReportItemToReport`, `StoreItemPhotos`), fáciles de sustituir en pruebas.
- **D — Inversión de dependencias:** los componentes dependen de clases del dominio, no al revés.

---

## Modelo de datos

| Tabla | Propósito |
|-------|-----------|
| `users` | Cuentas, rol (`admin`/`editor`) y estado (`active`). |
| `departments`, `municipalities` | DIVIPOLA (DANE) para el municipio del contrato. |
| `reports` | Cabecera del informe (contrato, municipio, fechas, textos, estado, PDF). |
| `report_items` | Ítems artísticos y técnicos (`ref`, especificación, narrativa, cantidad, unidad). |
| `report_item_photos` | Fotos (subidas o de Drive), con leyenda, orden y layout. |
| `report_imports` | Historial de cargas de Excel (archivo, resumen, errores). |
| `report_activity_logs` | Bitácora de cambios por campo (solo inserción). |
| `text_templates` | Plantillas de texto con variables. |
| `item_catalog` | Catálogo reutilizable de ítems. |
| `company_settings` | Datos de la empresa (una sola fila). |

---

## Flujos clave

### Carga por partes desde Excel
1. Se sube la plantilla `.xlsx` (`Informe`, `Items`, `Fotos`, `_meta`).
2. El sistema genera una **vista previa**: qué se crea, qué cambia, conflictos y errores por fila.
3. El usuario confirma y un proceso aplica los cambios.
   - El informe se identifica por `numero_contrato`; cada ítem por `ref`.
   - **Celda vacía = no tocar**; los ítems ausentes no se borran.
   - Si un campo se editó en la app después de la última carga, se marca **conflicto** y el
     usuario elige qué versión queda.
4. Cada cambio queda en la bitácora con origen `excel`.

### Edición en el asistente
Los cambios se guardan solos (autoguardado) y registran origen `app`. El paso **Revisión** muestra
un checklist; "Finalizar" exige que no haya errores (los avisos —fotos o narrativas faltantes— no
bloquean).

### Evidencia fotográfica
- Subida local optimizada, o enlaces de Drive (archivo o carpeta).
- En la vista previa se muestran las miniaturas y, si hay carpeta, la carpeta incrustada.

---

## Calidad y pruebas

```bash
composer lint          # Pint
composer types:check   # PHPStan (nivel 7, larastan)
php artisan test       # PHPUnit (Feature + Unit)
```

- Los tests usan SQLite en memoria (`phpunit.xml`) con `RefreshDatabase`.
- Cobertura funcional: importación de Excel, asistente, biblioteca, usuarios, empresa,
  trazabilidad y listado de informes.

---

## Convenciones

- **Idioma:** interfaz y textos en español; el código (clases, métodos, variables) en inglés.
- **Comentarios:** solo cuando aportan contexto (reglas de negocio, motivos). Sin ruido.
- **Estilo visual:** tokens de marca — tinta `#17150F`, dorado `#C9A043`, fondos claros.
  Tipografías Archivo (títulos) y Figtree (texto).
- **Livewire:** un componente de página por archivo en `resources/views/pages`; la lógica
  reutilizable va a `app/Actions` o `app/Services`.
- **Formato de fechas** en la UI: `d/m/Y`.
- Tras modificar vistas, ejecutar `npm run build`.

---

## Pendientes

- Generación de **PDF en cola** (spatie/laravel-pdf + Chromium) con la plantilla oficial.
- **Exportar el informe a Excel** (descargar la plantilla llena).
- Reordenar ítems y fotos con **arrastrar y soltar** (hoy con botones subir/bajar).
- Crear, **duplicar** y eliminar informes desde el listado.
- Límites de fotos (tamaño/cantidad) y armado de **collage**.
- Sincronización automática de Google Drive con cuenta de servicio (opcional).
