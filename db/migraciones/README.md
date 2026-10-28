# Migraciones de la base de datos

Estas instrucciones corresponden a bases existentes. Una instalación nueva ya incluye los cambios en `db/init/01_schema.sql`.

## Roles

`001_roles.sql` cambia una base creada con el esquema anterior: crea `roles`, copia los tres valores existentes a `usuarios.rol_id`, añade la llave foránea y retira `usuarios.rol`. Conserva usuarios y contraseñas. Se ejecuta **una sola vez** sobre una base que todavía tenga la columna `usuarios.rol`; una base nueva ya contiene el modelo actualizado por `db/init/`.

Antes de aplicarla, hacer un respaldo de la base. En PowerShell, desde la raíz del proyecto y con Docker Compose iniciado:

El comando `andina-mysql-root.sh` lee la clave de administración desde el volumen privado del contenedor. No hace falta escribirla en la consola.

```powershell
Get-Content -Raw -Encoding UTF8 db/migraciones/001_roles.sql | docker compose exec -T db sh /usr/local/bin/andina-mysql-root.sh
```

MySQL confirma los cambios de estructura (`ALTER TABLE`) de forma implícita, por lo que una falla intermedia no se revierte como una transacción común. Revisar el error y el estado de la base antes de repetir o restaurar el respaldo. No se ejecuta automáticamente al iniciar Docker porque el volumen ya existe.

## Límite de intentos de acceso

`002_intentos_acceso.sql` añade la tabla de contadores para OP07. Se aplica una sola vez si el volumen MySQL ya existía antes de esta opción. La tabla almacena claves SHA-256 derivadas del correo y la IP, el número de fallos y la fecha de desbloqueo; no guarda contraseñas. En una base nueva, `db/init/01_schema.sql` ya crea la tabla.

```powershell
Get-Content -Raw -Encoding UTF8 db/migraciones/002_intentos_acceso.sql | docker compose exec -T db sh /usr/local/bin/andina-mysql-root.sh
```

## Devoluciones de pedidos anulados

`003_devoluciones.sql` añade una constancia para registrar la devolución total de los abonos de un pedido anulado. Una anulación conserva los pagos y deja el dinero pendiente hasta que el administrador registre que la devolución se realizó fuera del sistema. La tabla impide registrar dos devoluciones para el mismo pedido. Una base nueva ya la crea en `db/init/01_schema.sql`.

```powershell
Get-Content -Raw -Encoding UTF8 db/migraciones/003_devoluciones.sql | docker compose exec -T db sh /usr/local/bin/andina-mysql-root.sh
```

## Fecha de anulación y alertas de devolución

`004_fecha_anulacion.sql` agrega `pedidos.anulado_en` para medir el tiempo desde la anulación, no desde la creación del pedido. En anulaciones antiguas de pedidos confirmados, recupera la fecha de los movimientos de reversión de inventario. Si no hay una fecha fiable, queda vacía y la interfaz pide revisión manual en vez de inventarla. Debe aplicarse después de `003_devoluciones.sql` en bases existentes; las bases nuevas ya incluyen la columna.

```powershell
Get-Content -Raw -Encoding UTF8 db/migraciones/004_fecha_anulacion.sql | docker compose exec -T db sh /usr/local/bin/andina-mysql-root.sh
```

## Datos de cada método de pago

`005_datos_pago.sql` agrega banco y referencia para transferencias, caja para efectivo, y banco y número de cheque para cheques. Los pagos históricos conservan esos campos vacíos; no se inventan datos que antes no se capturaban. La aplicación exige los datos correspondientes al registrar pagos nuevos. Una instalación nueva ya tiene las columnas en `db/init/01_schema.sql`.

Haz un respaldo antes de aplicar la migración **una sola vez** a una base existente:

```powershell
Get-Content -Raw -Encoding UTF8 db/migraciones/005_datos_pago.sql | docker compose exec -T db sh /usr/local/bin/andina-mysql-root.sh
```

## Catálogo de bancos

`006_bancos.sql` crea `bancos`, carga una selección inicial de entidades colombianas y añade `pagos.banco_id` con llave foránea. Se aplica **después de `005_datos_pago.sql`**, con respaldo previo. Los pagos históricos conservan el nombre de banco guardado; solo se enlazan al catálogo cuando el texto coincide con un nombre de la lista. Las instalaciones nuevas ya incluyen la tabla y la llave en `01_schema.sql`, y los nombres en `02_seed.sql`.

```powershell
Get-Content -Raw -Encoding UTF8 db/migraciones/006_bancos.sql | docker compose exec -T db sh /usr/local/bin/andina-mysql-root.sh
```

La selección inicial toma como referencia el [registro de entidades vigiladas de la Superintendencia Financiera de Colombia](https://www.superfinanciera.gov.co/SIMEV2/rnve/resultadobusqueda), consultado el 28 de octubre de 2026. No es un listado exhaustivo ni una clasificación por tamaño; se pueden agregar bancos a la tabla sin modificar el formulario.
