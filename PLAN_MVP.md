# Plan de trabajo: MVP "Informe de Actividades" (Grupo RYS S.A.S)

Stack: **Laravel 13 + MySQL + starter kit oficial de Laravel (Livewire + Flux UI)**
Referencia analizada: `recursos-dev/informe_de_actividades.pdf` (151 páginas, 155 MB) y `recursos-dev/logo_rys.png`
Diseño aprobado (layouts HTML exportados de Claude Design): `recursos-dev/RYS Informes MVP.html` (ver Fase 0)
Plantillas de carga: `plantillas/plantilla_informe_rys.xlsx` y `plantillas/ejemplo_guadalupe_PS-762026.xlsx`

---

## 1. Análisis del documento actual

### 1.1 Estructura del informe (en orden)

| # | Sección | Contenido | Páginas en el ejemplo |
|---|---------|-----------|-----------------------|
| 1 | **Portada** | "INFORME DE ACTIVIDADES", No. de contrato (PS-762026), municipio y departamento | 1 |
| 2 | **Ficha del contrato** | Fecha del informe, periodo de seguimiento (desde/hasta), asunto, objeto del contrato (texto largo) | 2 |
| 3 | **Introducción** | Texto narrativo general | 2 |
| 4 | **Encabezado del evento** | Mes/año, No. de contrato, municipio, nombre del evento, descripción (fechas, alcance, cumplimiento de riders) | 3–4 |
| 5 | **Ítems ejecutados** (el grueso del documento) | Tabla de 4 columnas por ítem + páginas de fotos de evidencia | 5–150 |
| 6 | **Conclusión y firma** | Texto de cierre, firma escaneada, nombre y cargo del representante legal | 151 |

### 1.2 Anatomía de cada ítem (bloque repetible)

Cada ítem es una fila de tabla con 4 columnas y luego sus fotos:

| Columna | Ejemplo |
|---------|---------|
| **Categoría / componente** | "SERVICIOS ARTÍSTICOS (INCLUYE EL CUMPLIMIENTO DEL RIDER TÉCNICO…)", "SONIDO, MICROFONERÍA Y DISTRIBUCIÓN ELÉCTRICA (CANTIDAD EN DÍAS)" |
| **Especificación contratada** | Lo que exige el contrato: "Proveer cantante con su agrupación de música vallenata… (Hebert Vargas)" o el listado de equipos línea por línea |
| **Actividad ejecutada** | Narrativa de lo que se hizo (varios párrafos) |
| **Cantidad** | Número, con la unidad en el título de la categoría: días, camiones, "agrupada" |

**Evidencia fotográfica**, dos formatos:
- **Foto con leyenda**: el título es una línea de la especificación (p. ej. "8 monitores NEXO P15 / NXAMP 4X2") y lleva 2 fotos por página. Así se comprueba cada equipo contratado.
- **Collage**: grillas de 2x2 o 2x1 (hospitalidad, camerinos, entrega de kits). Casi siempre ya vienen armadas como una sola imagen.
- Las fotos traen marca de agua con fecha, hora y dirección (app tipo *Timestamp Camera*). Es evidencia de cara a la alcaldía.

### 1.3 Ítems del ejemplo (18)

- **Servicios artísticos (12)**: presentador y trovadores, Amaro, Dani El Sudaca, Festival de Trova (jurados, presentador, parrandero, 8 trovadores), El Andariego, Camilo Baena, Hebert Vargas, Tropicombo, Los Relicarios, El Combo Hispano, artistas locales (3 agrupaciones en un ítem), DJ Disco Show Urbano.
- **Técnicos y logísticos (11)**: sonido, iluminación, pantallas (central y relevo), techos, tarimas, personal de producción y montaje, backline, efectos y pirotecnia, planta eléctrica, streaming y circuito cerrado, transporte.

En los ítems artísticos se ve un patrón fijo: una página de tabla y unas 4 páginas de fotos.

### 1.4 Hallazgos que definen el producto

1. **Mucho texto repetido.** El párrafo "Previo al desarrollo del evento se realizó un proceso de coordinación… riders técnicos y de hospitalidad…" aparece casi igual en los 12 ítems artísticos, y lo mismo pasa con el de hospitalidad. **Hace falta una biblioteca de plantillas de texto** con variables como `{artista}`, `{municipio}` y `{evento}`.
2. **Las especificaciones técnicas salen del anexo del contrato** y se repiten de un evento a otro (NEXO, GrandMA3, tarima 8x8…). **Hace falta un catálogo de ítems reutilizable** para no volver a escribirlos.
3. **Cada línea de la especificación es candidata a foto de evidencia.** La interfaz debería dejar escoger una línea como leyenda de la foto.
4. **El PDF pesa 155 MB** porque las fotos van sin optimizar. Hay que **redimensionar y comprimir** al subirlas: la meta es menos de 20 MB.
5. **Errores que el sistema puede evitar**: "segumiento", "Rodinng", una sección que dice "tres días" cuando fueron cuatro, y un hospedaje "04 y 05 de julio" en un evento del 17 al 20 de julio. Conviene **validar fechas contra el periodo** y usar textos fijos revisados.
6. **Identidad visual**: encabezado negro con el logo circular dorado a la izquierda y una línea dorada, pie negro con línea dorada, fondo gris muy claro, títulos en sans-serif negrita. El PDF debe reproducir esta plantilla.

---

## 2. Alcance del MVP

### Entra
- Login y usuarios (roles básicos: **admin** y **editor**).
- **Verificación en dos pasos (2FA)** con app autenticadora (TOTP), códigos de recuperación y opción de confiar en el equipo por 30 días. Se apoya en Laravel Fortify, que ya viene en el starter kit.
- Catálogo de **municipios de Colombia** (semilla DANE: departamento y municipio).
- CRUD de **informes** con **asistente paso a paso** y autoguardado (borrador → finalizado).
- **Catálogo de ítems** (categoría, especificación, unidad, narrativa sugerida) para importar a un informe.
- **Plantillas de texto** con variables (introducción, descripción, coordinación, hospitalidad, conclusión).
- **Carga masiva de fotos** por ítem: arrastrar y soltar o desde el celular, con compresión, reordenamiento, leyenda y opción de collage.
- **Generación del PDF** con la plantilla visual de RYS, en cola, y descarga.
- **Duplicar un informe** para usarlo como base del siguiente evento.
- **Carga desde Excel** con plantilla oficial (.xlsx), **por partes**: el mismo archivo se sube varias veces y el informe se va completando (ver sección 3.1).
- **Fotos desde Google Drive**: por carpeta (recomendado) o por enlace de cada archivo, con **sincronización** para traer las fotos nuevas.
- **Descargar el Excel del informe actual**, para completarlo fuera del sistema y volver a subirlo.
- **Informe incompleto como estado normal**: se puede guardar y avanzar sin fotos ni textos definitivos, y generar un **PDF borrador** (con marca de agua) en cualquier momento.
- Configuración de la empresa: logo, representante legal, firma escaneada.

### No entra (fase 2)
- Redacción de narrativas con IA a partir de las fotos o de la especificación.
- Exportar a Word (.docx) editable.
- Multiempresa (SaaS).
- Portal para que la alcaldía revise y apruebe.
- Lectura de EXIF/GPS para ubicar y validar las fotos automáticamente.
- Presupuesto y control financiero del contrato.

---

## 3. Flujo del asistente (step form)

```
[1 Contrato] → [2 Evento] → [3 Programación artística] → [4 Técnico y logística] → [5 Cierre] → [6 Revisión y PDF]
```

| Paso | Campos / acciones | Ayudas de UX |
|------|-------------------|--------------|
| **1. Contrato** | No. de contrato, departamento → municipio, fecha del informe, periodo desde/hasta, asunto (autogenerado), objeto | Selects encadenados; el asunto se arma solo: "Informe de actividades No {contrato}" |
| **2. Evento** | Nombre del evento, fechas/días del evento, introducción, descripción | Botón "Usar plantilla" con variables ya reemplazadas; se valida que las fechas caigan dentro del periodo |
| **3. Programación artística** | Lista de artistas o ítems: requerimiento contratado, nombre del artista, género, narrativa, cantidad, fotos | Tarjetas reordenables; "Agregar artista" precarga los textos de coordinación y hospitalidad; contador de fotos por ítem |
| **4. Técnico y logística** | Ítems desde el catálogo (sonido, luces, pantallas, tarimas…), con especificación editable, narrativa, cantidad, unidad y fotos | "Importar del catálogo" con casillas; las líneas de la especificación se ofrecen como leyendas de las fotos |
| **5. Cierre** | Conclusión, firmante (desde configuración) | Plantilla de conclusión |
| **6. Revisión** | Checklist de completitud (ítems sin fotos, sin narrativa, fechas fuera de rango), vista previa, "Generar PDF" | Barra de progreso; aviso cuando el PDF queda listo |

Principios de UX: se puede saltar entre pasos (no es lineal a la fuerza), autoguardado cada vez que cambia un campo, indicador de avance por paso y buen funcionamiento en celular para subir fotos desde el sitio del evento.

**Llenado progresivo.** Ningún campo bloquea el guardado: el informe vive como borrador hasta que el usuario lo finaliza. Cada ítem muestra lo que le falta (sin fotos, sin narrativa o con fotos sin título), y el listado de informes muestra el avance. Solo **"Finalizar"** exige que la revisión no tenga errores. Mientras tanto, el PDF sale con la marca de agua "BORRADOR".

---

## 3.1 Carga desde Excel y fotos en Google Drive

Archivos generados:
- `plantillas/plantilla_informe_rys.xlsx`: la plantilla vacía.
- `plantillas/ejemplo_guadalupe_PS-762026.xlsx`: el ejemplo lleno con los 23 ítems del informe real.

### Estructura de la plantilla
| Hoja | Contenido |
|------|-----------|
| **Instrucciones** | Cómo se carga por partes y cómo funcionan las fotos y los permisos de Drive |
| **Informe** | Formato campo/valor, con claves fijas (`numero_contrato`, `municipio`, `periodo_desde`, …) |
| **Items** | Un renglón por ítem: `ref`, `tipo`, `orden`, `categoria_pdf`, `artista`, `requerimiento_contrato`, `actividad_ejecutada`, `agregar_textos_estandar`, `cantidad`, `unidad`, `carpeta_drive`, `distribucion_fotos`, `notas` |
| **Fotos** (opcional) | Una foto por renglón: `ref_item`, `url_drive`, `titulo`, `orden` |
| Listas (oculta), `_meta` (muy oculta) | Valores de las listas desplegables y versión de la plantilla (`rys-informe-plantilla:v1`) |

La fila 1 de cada hoja tiene las **claves técnicas** que lee el importador; la fila 2 tiene los títulos para las personas. Así se pueden cambiar los títulos sin romper la carga.

### Reglas de la carga por partes (upsert)
1. **Informe** = `numero_contrato`. Si ya existe se actualiza; si no, se crea un borrador.
2. **Ítem** = `ref` dentro del informe. Si existe se actualiza; si es nuevo, se agrega. Los ítems que no aparecen en el Excel **no se borran**.
3. **Celda vacía = no tocar.** El Excel nunca borra datos; para borrar se usa la aplicación.
4. **Vista previa obligatoria**: el sistema muestra qué se crea, qué cambia (antes → después), las advertencias (municipio no reconocido, fechas fuera del periodo) y los errores por fila. Luego el usuario confirma.
5. Si un campo se editó en la aplicación después de la última carga y el Excel trae otro valor, se marca como **conflicto** y el usuario elige qué versión queda.
6. Queda un **historial de cargas** (quién, cuándo, archivo y resumen) para rastrear cambios.
7. **Trazabilidad por campo (bitácora).** Además del historial de cargas, cada cambio de campo queda registrado en `report_activity_logs`: informe, ítem (`ref`), campo, valor anterior, valor nuevo, **origen** (`app`, `excel`, `drive`), usuario y fecha. Al reimportar un Excel con cambios, la bitácora dice exactamente qué se actualizó; al editar en la aplicación, también. La vista del informe muestra una línea de tiempo con los últimos cambios.
8. **El Excel no es la fuente de verdad del historial.** El historial vive en la base de datos; la hoja `Historial` (o `_meta`) solo refleja la versión de plantilla y un resumen de las últimas cargas al exportar. Si alguien edita el archivo por fuera, el diff se detecta en la vista previa antes de aplicar.

### Fotos en Google Drive
- **Por carpeta (recomendado)**: `carpeta_drive` en el ítem; el sistema lista y trae todas las imágenes de la carpeta.
- **Por archivo**: hoja `Fotos` con el enlace y el título; sirve cuando se necesita un título exacto por foto.
- **Enlaces aceptados**: `drive.google.com/file/d/{id}/…`, `open?id={id}`, `uc?id={id}` y `drive/folders/{id}`. Se extrae el ID.
- **Acceso**: con la **API de Google Drive v3 y una cuenta de servicio**. El cliente comparta la carpeta raíz de eventos con el correo de esa cuenta como lector, una sola vez. Como alternativa se aceptan carpetas públicas "cualquiera con el enlace" usando una API key. No se usa `uc?export=download` porque es poco confiable en archivos grandes.
- **Las fotos se copian** al almacenamiento propio y se optimizan: el PDF no depende de Drive y no se daña si alguien borra o mueve archivos allá.
- **Sincronización incremental**: se guarda el `drive_file_id` de cada foto importada. "Sincronizar fotos", o subir de nuevo el Excel, solo trae las fotos nuevas y **nunca borra** las que ya se trajeron.
- **Título por defecto**: el que diga la hoja Fotos; si no hay, el nombre del archivo cuando coincide con una línea del requerimiento; si no, queda "Sin título" para asignarlo en la aplicación.
- **Límites**: solo imágenes (jpg, png, heic, webp), un tamaño máximo por archivo y un reintento automático. Las fotos sin permiso se reportan en la vista previa con el nombre del archivo.

### Flujo en la aplicación
`Informes → Importar desde Excel → subir .xlsx → vista previa (crear / actualizar / conflictos / errores) → confirmar → Job en cola que aplica los cambios y trae las fotos de Drive → notificación con el resumen`

---

## 4. Modelo de datos (MySQL)

```
users                (id, name, email, password, role)
company_settings     (id, name, nit, logo_path, legal_rep_name, legal_rep_title, signature_path)
departments          (id, dane_code, name)
municipalities       (id, department_id, dane_code, name)

reports              (id, user_id, municipality_id, contract_number, report_date,
                      period_start, period_end, subject, contract_object,
                      event_name, event_start, event_end,
                      introduction, event_description, conclusion,
                      status[draft|final], current_step, pdf_path, pdf_generated_at,
                      timestamps, soft_deletes)

report_items         (id, report_id, type[artistic|technical], category_label,
                      specification (text), artist_name nullable, narrative (text),
                      quantity, unit[dias|camiones|agrupada|unidad], sort_order)

report_item_photos   (id, report_item_id, path, thumb_path, caption nullable,
                      layout[single|pair|collage], sort_order, taken_at nullable,
                      width, height, size_bytes)

item_catalog         (id, type, category_label, specification, default_narrative,
                      default_unit, default_quantity, active)

text_templates       (id, key[introduction|description|coordination|hospitality|conclusion|…],
                      name, body_with_variables, active)

-- Carga desde Excel / Drive
report_imports       (id, report_id nullable, user_id, file_path, status[previewing|confirmed|applied|failed],
                      summary json, errors json, created_at, applied_at)

-- Trazabilidad
report_activity_logs (id, report_id, report_item_id nullable, item_ref nullable, report_import_id nullable,
                      user_id nullable, action[created|updated|deleted], field nullable, label nullable,
                      old_value longtext nullable, new_value longtext nullable,
                      source[app|excel|drive|system], created_at)
```

Cambios sobre las tablas anteriores:
- `reports.contract_number` es **único**: es la llave de la carga por partes.
- `report_items` suma `ref` (único por informe), `drive_folder_id` (nullable), `drive_synced_at`, `add_standard_texts` (bool) y `internal_notes`.
- `report_item_photos` suma `source[upload|drive]`, `drive_file_id` (nullable, único por ítem) y `original_name`.
- Para detectar conflictos, cada ítem guarda `updated_in_app_at` y `imported_at`.
- `report_activity_logs` es **solo inserción** (sin `updated_at`): nunca se edita ni se borra un registro. El campo `item_ref` se conserva aunque el ítem se elimine, para que la bitácora siga siendo legible.

Variables de las plantillas: `{municipio}`, `{departamento}`, `{evento}`, `{fecha_inicio}`, `{fecha_fin}`, `{dias}`, `{artista}`, `{contrato}`.

---

## 5. Arquitectura técnica

- **Laravel 13** con el **starter kit de Livewire (Flux UI + Tailwind)**. El asistente queda como un componente Livewire por paso (o Volt), con estado en BD y sin armar una API aparte.
  - *Alternativa*: starter kit de React + Inertia (shadcn/ui) si se quiere una interfaz más rica para ordenar fotos. Para este MVP Livewire es más rápido de construir y mantener.
- **Fotos**: `intervention/image` para redimensionar a un lado máximo de ~1600 px, JPEG al 75 % y miniatura de 400 px. Se procesan en un **Job en cola**. Para ordenar con arrastrar y soltar, SortableJS (el plugin `wire:sortable` de Livewire).
- **PDF**: `spatie/laravel-pdf` (Browsershot / Chromium headless). Da HTML + CSS con encabezado y pie fijos, tablas que se parten entre páginas y buena calidad de imagen. DomPDF no se recomienda porque le cuestan los layouts complejos y los PDF de muchas páginas.
  - Se genera en un **Job en cola** (`GenerateReportPdf`) con notificación al terminar.
- **Almacenamiento**: disco `local` en el MVP y S3 o compatible más adelante (queda configurable).
- **Excel**: `openspout/openspout` para leer en streaming (rápido y con poca memoria) y `phpoffice/phpspreadsheet` solo para exportar el informe a la plantilla, conservando formatos y listas.
- **Google Drive**: `google/apiclient` (Drive v3) con cuenta de servicio y credenciales en `.env`. Un Job por carpeta o foto (`SyncDriveFolder`, `ImportDrivePhoto`) con reintentos y límite de peticiones.
- **Colas**: driver `database` en el MVP.
- **Seeds**: departamentos y municipios DANE, catálogo inicial con los 11 ítems técnicos del PDF de ejemplo y plantillas de texto sacadas del informe real.
- **Tests**: Pest (feature tests del asistente, de la generación del PDF y de las plantillas).

---

## 6. Fases y tareas

### Fase 0: Diseño con Claude Design (antes de escribir código): ✅ hecho

**Fuente de verdad visual:** `recursos-dev/RYS Informes MVP.html`, el lienzo exportado con todos los layouts en HTML. Versión en línea: https://claude.ai/artifact/UDAb4gFPoVBGgH7A6kqg1e

Al construir cada vista en Blade/Livewire, hay que copiar de ese archivo los valores exactos: colores, tipografías, espaciados, radios y textos. No se reinterpretan.

Tokens de diseño que salen del lienzo:
- **Colores**: tinta `#17150F`, menú lateral `#0F0E0B`, fondo `#F3F1EC`, superficie `#FFFFFF`, bordes `#E3DED3` / `#D3CBBB`, texto secundario `#5F584A`, dorado `#C9A043` (texto dorado `#7F5C12`, fondo dorado suave `#F6EEDB`), éxito `#2C7549`, aviso `#8F520A`, error `#A8261D`.
- **Tipografías**: Archivo para títulos, Figtree para texto y Open Sans / Arial en la plantilla del PDF.
- **Radios**: 8 px en controles, 12 px en tarjetas, 999 px en insignias y chips. Altura de controles: 42 px.

| Pantalla del lienzo | Vista a construir |
|---|---|
| Ingreso · Verificación en dos pasos (2FA) | Auth (Fortify) + desafío TOTP |
| Listado de informes | `reports.index` |
| Paso 1 · Contrato … Paso 6 · Revisión y PDF | Asistente `reports.wizard` (un componente Livewire por paso) |
| Evidencia fotográfica del ítem · Fotos desde el celular | Componente de fotos (escritorio y móvil) |
| Catálogo de ítems · Plantillas de texto · Configuración | Administración |
| Importar desde Excel: revisión de cambios · resultado y fotos de Drive | `reports.import` (vista previa y resultado) |
| Página "PDF del informe" (portada, ficha, ítem, fotos, collage, conclusión) | Plantillas Blade del PDF (Browsershot) |

Pantallas diseñadas (lista original de alcance):
1. Login
2. Dashboard / listado de informes (estado, municipio, fechas, progreso, acciones: continuar, duplicar, descargar PDF)
3. Asistente, paso 1: Contrato
4. Asistente, paso 2: Evento (con el selector de plantillas)
5. Asistente, paso 3: Programación artística (lista de tarjetas y panel de edición del artista)
6. Asistente, paso 4: Técnico (modal "importar del catálogo" y editor de ítem)
7. **Componente de fotos** (subida, cuadrícula, leyenda, reordenar, collage). Es la pieza más importante del producto.
8. Asistente, paso 5: Cierre
9. Asistente, paso 6: Revisión, checklist y generación del PDF
10. Catálogo de ítems (CRUD)
11. Plantillas de texto (CRUD con vista previa de variables)
12. Configuración de la empresa (logo, firma, representante)
13. **Plantilla del PDF**: portada, ficha, página de ítem y páginas de fotos (1, 2 y collage)

Identidad: negro, dorado (degradado del logo) y gris claro. La interfaz debe ser sobria y clara, y el dorado va solo como acento.

### Fase 1: Base del proyecto
- [x] Crear el proyecto Laravel 13 con el starter kit de Livewire y configurar MySQL
- [x] Autenticación, roles (admin/editor) y layout con la marca RYS
- [x] Módulo de administración de usuarios y roles (solo admin): crear, editar datos, cambiar rol, habilitar/deshabilitar y eliminar; el último admin no se puede degradar ni borrar y un usuario deshabilitado no puede ingresar
- [x] Apariencia fija en tema claro de la marca (se retiró el tema oscuro que rompía el contraste)
- [ ] Migraciones y modelos (sección 4), factories y seeders (DANE, catálogo, plantillas)
- [x] Configuración de la empresa (logo, firma, representante legal) — `/empresa`, solo admin, con vista previa y borrado de imágenes

### Fase 2: Gestión de informes y asistente
- [x] Listado de informes con paginación, búsqueda, filtros (estado/departamento), orden, tamaño de página, estadísticas y **crear, duplicar y eliminar**
- [x] Asistente de 6 pasos (`/informes/{id}/editar`) con navegación libre, autoguardado e indicador de avance
- [x] Motor de plantillas de texto (reemplazo de variables) y botón "Usar plantilla" en Evento y Cierre
- [x] Catálogo de ítems e importación desde el catálogo en los pasos 3 y 4 (Biblioteca › Catálogo de ítems)
- [x] Pasos 3 y 4: CRUD de ítems con edición en línea (falta orden por arrastre)
- [x] Validaciones: fechas del evento dentro del periodo, campos obligatorios y checklist de revisión

### Fase 3: Evidencia fotográfica
- [x] Subida múltiple con optimización (redimensionar y comprimir con GD) y miniatura
- [x] Evidencia por enlace de Google Drive: pegar enlaces de archivo (miniatura) o de carpeta (vista incrustada)
- [x] Cuadrícula de fotos con reordenamiento, leyenda editable y eliminación por ítem
- [x] Reordenar ítems (subir/bajar) dentro de cada sección
- [ ] Límites: tamaño máximo, formatos JPG/PNG/HEIC→JPG y fotos máximas por ítem

### Fase 3.5: Carga desde Excel y Google Drive
- [ ] Validar la plantilla v1 con el cliente, contra el Excel que usan hoy
- [ ] Lector de la plantilla (claves de la fila 1, versión en `_meta`), normalización de fechas, municipios DANE y listas
- [ ] Motor de carga por partes: informe por contrato, ítem por `ref`, celdas vacías que no se tocan, detección de conflictos
- [ ] Pantalla de vista previa (crear / actualizar / conflictos / errores por fila) y confirmación
- [ ] Integración con Drive: extraer el ID del enlace, listar carpetas, descargar y optimizar fotos, reportar las que no tienen permiso
- [ ] Botón "Sincronizar fotos de Drive" por ítem y por informe (solo trae las nuevas)
- [ ] Exportar el informe actual a la plantilla ("Descargar Excel")
- [x] Historial de cargas del informe **versionado** (v1, v2…): archivo descargable, resumen y detalle de qué cambió en cada carga; aviso si se sube un archivo idéntico
- [x] Foto de portada por informe (enlace de Drive en el Excel o subida en la app) con miniatura
- [x] Bitácora de cambios por campo (`report_activity_logs`): registrar origen (`app`/`excel`), usuario, valor anterior y nuevo
- [ ] Línea de tiempo de cambios en la vista del informe (y por ítem)
- [ ] Hoja `Historial`/`_meta` al exportar el Excel con versión de plantilla y resumen de últimas cargas
- [ ] Tests con el archivo `ejemplo_guadalupe_PS-762026.xlsx`: primera carga, recarga sin cambios (sin duplicados), recarga con cambios y con conflictos

### Fase 4: Generación del PDF
- [x] Plantilla Blade/CSS del informe: encabezado y pie de marca, portada, ficha, tabla de 4 columnas y páginas de fotos
- [x] Respetar `distribucion_fotos` (1 / 2 / collage), paginar todas las fotos, leyendas y textos estándar (coordinación/hospitalidad)
- [x] Empresa real (logo, firma, representante) y numeración de páginas
- [x] Generar y descargar PDF con Chrome headless (`spatie/laravel-pdf` + Browsershot); hoy es síncrono
- [ ] Job en cola con notificación (fase 2)
- [ ] Comparar lado a lado contra el PDF real del cliente y ajustar hasta que coincidan
- [ ] Meta: un informe de ~150 fotos en menos de 20 MB y en menos de 2 minutos

### Fase 5: QA y entrega
- [ ] Cargar el informe de Guadalupe completo como prueba de aceptación
- [ ] Tests con Pest de los flujos críticos
- [ ] Despliegue (VPS/Forge o el hosting del cliente), colas con Supervisor y Chromium instalado en el servidor
- [ ] Sesión de capacitación y manual corto

---

## 7. Preguntas para el cliente

1. **¿Nos pueden compartir el Excel que usan hoy?** Sirve para ver campos, fórmulas y el anexo técnico que copian.
2. ¿La columna "Cantidad" siempre sale del contrato? ¿Hace falta mostrar valores o presupuesto? En el PDF actual no aparecen.
3. ¿Cuántas personas van a usar el sistema? ¿Suben fotos varias personas desde el evento?
4. ¿La alcaldía pide algún formato oficial adicional (actas, planillas de asistencia, certificados)?
5. ¿Necesitan también el informe en Word para ajustes de última hora?
6. ¿Siempre firma el mismo representante legal?
7. ¿Las fotos deben conservar la marca de agua de fecha y ubicación? El sistema no la quita, pero hay que confirmar si es obligatoria.
8. ¿Su Google Drive es de **Google Workspace** (cuenta de empresa) o personal? ¿Pueden compartir la carpeta raíz de eventos con una cuenta de servicio? Esto define cómo se da el acceso.
9. ¿Cómo organizan hoy las fotos en Drive: una carpeta por evento, por artista o por día? Conviene que la plantilla siga esa misma organización.


## 8 la plantilla a usar es esta, se debe instalar esta plantilla para el desarrollo la version gratuita

https://free-demo.tailadmin.com/

https://github.com/TailAdmin/tailadmin-free-tailwind-dashboard-template

la version de laravel
