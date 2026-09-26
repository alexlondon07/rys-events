# Manual de uso

Bienvenido al sistema de **Informes de Actividades de Grupo RYS**. Aquí encontrará, paso a paso, cómo cargar la información de cada evento, adjuntar la evidencia fotográfica y generar el PDF con la plantilla oficial.

## Qué hace el sistema

Permite armar el informe de actividades de un evento a partir de la **plantilla de Excel** que ya usan, completar los datos y las fotos, y **generar el PDF** con la identidad de Grupo RYS.

El flujo típico es:

1. Descargar la plantilla de Excel.
2. Llenarla con los datos del evento y los ítems.
3. Subirla al sistema y revisar los cambios.
4. Aplicar los cambios (se crea o actualiza el informe).
5. Completar lo que falte y subir la evidencia fotográfica.
6. Generar y descargar el PDF.

## Ingreso y seguridad

![Pantalla de ingreso](manual/01-ingreso.png)

- Ingrese con su **correo y contraseña** en la pantalla de inicio.
- También puede entrar con **llave de acceso (passkey)** si la tiene configurada.
- Si olvidó la contraseña, use **“¿Olvidó su contraseña?”** para restablecerla.
- La **verificación en dos pasos (2FA)** es obligatoria para los administradores. Si es admin y no la tiene activa, el sistema lo llevará a **Configuración › Seguridad** para activarla.
- Las cuentas las crea un **administrador**; no hay registro público.

## Roles y permisos

- **Administrador**: todo lo de un editor, más la **Empresa** (logo, firma, representante) y **Usuarios y roles**.
- **Editor**: gestiona informes, ítems, fotos y PDF. No administra usuarios ni la empresa.

El sistema es de trabajo en equipo: cualquier usuario activo puede ver y editar los informes. **Eliminar** un informe solo lo puede hacer un administrador o quien lo creó.

## Informes: crear, buscar, duplicar y eliminar

![Listado de informes](manual/02-informes.png)

En **Informes** (el inicio) está el listado.

- **Crear**: botón “Nuevo informe”; indique el número de contrato y el municipio.
- **Buscar y filtrar**: por contrato, evento o municipio; y por estado (borrador/final) y departamento.
- **Continuar**: abra un informe para editarlo con el asistente.
- **Duplicar**: crea una copia como base para el siguiente evento.
- **Eliminar**: borra el informe (se puede restaurar por un administrador).

Al abrir un informe ve el resumen: avance, ítems, fotos, cargas de Excel, historial de cambios y los botones de **Editar**, **Vista previa**, **Generar PDF** y **Descargar Excel**.

![Vista del informe](manual/05-informe.png)

## El asistente de 6 pasos

![Asistente de edición](manual/04-asistente.png)

Al editar un informe se recorren 6 pasos. Puede saltar entre ellos libremente; todo se **guarda automáticamente**.

1. **Contrato**: número de contrato, departamento y municipio, fecha del informe, periodo (desde/hasta) y objeto del contrato.
2. **Evento**: nombre del evento, fechas, introducción y descripción. Puede usar **plantillas de texto**.
3. **Programación artística**: los ítems artísticos (artista, requerimiento, narrativa, cantidad y fotos).
4. **Técnico y logística**: los ítems técnicos. Puede **importarlos del catálogo**.
5. **Cierre**: conclusión y firmante (se toma de la configuración de la empresa).
6. **Revisión**: checklist de lo que falta y botón para generar el PDF.

En los pasos 3 y 4 puede **agregar ítems**, **reordenarlos** (arrastrando o con las flechas) e importarlos del catálogo.

## Cargar desde Excel

![Importar desde Excel](manual/03-importar.png)

Esta es la forma recomendada de crear o actualizar un informe por partes.

1. Entre a **Importar desde Excel**.
2. Descargue la **plantilla oficial** (o use el Excel que ya tiene).
3. Llénela. Recuerde:
   - El **número de contrato** identifica el informe.
   - La **referencia** (`ART-01`, `TEC-01`…) identifica cada ítem.
   - Una **celda vacía NO borra** lo que ya está en el sistema.
   - Los ítems que no aparezcan en el Excel **no se eliminan**.
4. Suba el archivo y pulse **“Generar vista previa”**.
5. Revise la pantalla:
   - **Cambios detectados**: qué se crea y qué se actualiza (Antes → Después).
   - **Conflictos**: campos que editó en la app después de la última carga; elija qué versión queda.
   - **Errores y avisos**: por hoja y fila. Si hay errores, no se puede aplicar.
6. Pulse **“Aplicar cambios”**.

**Historial de cargas**: quedan las versiones (v1, v2…). Una carga **“En revisión”** es una vista previa que aún no ha aplicado:

- **Ver**: la reabre para revisarla y aplicarla sin volver a subir el archivo.
- **Descartar**: la borra si no la va a usar.

> Al subir de nuevo el mismo contrato, la vista previa pendiente anterior se reemplaza por la nueva. Si el informe fue eliminado, al aplicar se crea **desde cero** con lo que traiga el Excel.

## Evidencia fotográfica

![Evidencia fotográfica de un ítem](manual/11-evidencia.png)

Dentro de cada ítem (pasos 3 y 4) está la sección **Evidencia fotográfica**.

- **Subir fotos**: JPG, PNG o WEBP, hasta el límite indicado por foto y por ítem. Las fotos se **optimizan** automáticamente (se reducen y comprimen) para que el PDF no pese de más. Los **HEIC** del iPhone deben convertirse antes.
- **Leyenda**: cada foto puede llevar un texto (idealmente una línea del requerimiento del contrato).
- **Ordenar**: arrastre las fotos (o use las flechas) para cambiar el orden.
- **Distribución**: elija **1 por página**, **2 por página** o **Collage (hasta 4)**. El collage se arma solo, en una imagen 2x2.

### Evidencia por enlace

Además de subir archivos, puede pegar **enlaces**:

- Una **carpeta de Google Drive** (se muestra incrustada), un **archivo** de Drive, una **imagen directa** (JPG/PNG/WEBP) o **otro proveedor**.
- Para Drive, comparta la carpeta como **“Cualquiera con el enlace”**.
- Por seguridad, los enlaces que apunten a direcciones internas **no se incrustan**; se muestra el enlace para abrirlo aparte.

## Fotos desde Google Drive

Si la empresa configuró la cuenta de servicio de Google Drive, puede **traer las fotos al sistema** (se copian y optimizan, y el PDF deja de depender de Drive).

- **Por ítem**: botón **“Sincronizar fotos de Drive”**.
- **Por informe**: botón **“Sincronizar Drive”** (todos los ítems).

Solo trae las fotos nuevas; **nunca borra** nada en Drive. Las fotos sin permiso quedan marcadas con el error.

## Visor del Excel

![Visor del Excel](manual/07-visor-excel.png)

En **Visor del Excel** puede ver, por informe y por versión, **el archivo tal como se subió**:

- Pestañas por hoja (`Informe`, `Items`, `Fotos`).
- Cada fila con un color según lo que hará el sistema (Nuevo, Cambia, Sin cambios, Conflicto, Error).
- Un panel que explica **cada columna**.

Es útil para entender el Excel sin adivinar. También puede abrirlo desde el informe con **“Visor del Excel”** o **“Analizar”**.

## Generar el PDF

![Vista previa del informe](manual/06-vista-previa.png)

- En la vista del informe, botón **“Generar PDF”** (o **“Regenerar PDF”**).
- El PDF se genera **en segundo plano**; la pantalla muestra el estado (en cola, generando, listo o con error). La descarga se habilita al terminar.
- El PDF sale con **marca de agua “BORRADOR”** mientras el informe sea borrador; al finalizarlo, sale limpio.
- También puede ver una **vista previa** e imprimirla desde el navegador.

## Descargar el Excel

Desde el informe, **“Descargar Excel”** exporta el informe actual a la plantilla oficial (incluye una hoja **Historial** con las últimas cargas). Puede completarlo fuera del sistema y volver a subirlo.

## Empresa (administrador)

![Configuración de la empresa](manual/09-empresa.png)

En **Empresa** configure el **logo**, el **representante legal** y la **firma escaneada**. Estos datos salen en la portada y en el cierre del PDF.

## Usuarios y roles (administrador)

![Usuarios y roles](manual/10-usuarios.png)

En **Usuarios y roles** puede:

- **Crear** usuarios (nombre, correo, rol y contraseña).
- **Editar** sus datos.
- **Cambiar el rol** (admin/editor).
- **Habilitar o deshabilitar** el acceso.
- **Eliminar** un usuario.

Reglas de seguridad: siempre debe quedar **al menos un administrador activo**, y no puede desactivar, degradar ni eliminar su propio usuario.

## Preguntas frecuentes

**¿Por qué una carga aparece “En revisión”?**
Porque generó la vista previa pero aún no la aplicó. Puede **Ver** o **Descartar** desde el historial.

**Subí el mismo Excel dos veces, ¿se duplica el informe?**
No. El contrato identifica el informe y la referencia identifica cada ítem: se **actualiza**, no se duplica.

**Borré un informe y quiero volver a subirlo con el mismo contrato, ¿puedo?**
Sí. Al aplicar, el sistema crea el informe **desde cero** con lo que traiga el Excel.

**¿Las fotos del PDF van sin optimizar?**
No. Se optimizan al subirlas para que el informe pese menos y se genere rápido.

**¿Puedo dejar el informe incompleto?**
Sí. El informe vive como **borrador** hasta que lo finalice; puede generar un PDF borrador en cualquier momento.

**¿Necesito que Drive esté configurado?**
No es obligatorio. Puede subir las fotos directamente o pegar enlaces. La sincronización con Drive es una comodidad adicional.
