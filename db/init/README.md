# db/init

Scripts SQL que se ejecutan al crear la base de datos por primera vez, en orden alfabético:

- `01_schema.sql`: diez tablas iniciales, llaves, índices y restricciones; `roles` se relaciona con `usuarios` mediante `rol_id`.
- `02_seed.sql`: tres roles y los datos de prueba de la sección 9 del documento de requerimientos.

Docker Compose monta esta carpeta en `/docker-entrypoint-initdb.d` del contenedor de MySQL para que se carguen solos.

Ambos SQL declaran `SET NAMES utf8mb4` para que las tildes y eñes de la semilla se interpreten correctamente aunque el cliente `mysql` arranque con otra codificación.

Los dos archivos se probaron en MySQL 8.0.46. MySQL solo ejecuta esta inicialización al crear un volumen de datos vacío; agregar o editar un archivo después no modifica automáticamente una base existente.

Si ya existía una base con `usuarios.rol`, se aplica una sola vez `db/migraciones/001_roles.sql` siguiendo las indicaciones de `db/migraciones/README.md`. No se debe eliminar el volumen para adoptar el nuevo modelo.
