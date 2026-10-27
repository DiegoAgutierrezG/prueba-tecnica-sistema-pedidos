# Migración de roles

`001_roles.sql` cambia una base creada con el esquema anterior: crea `roles`, copia los tres valores existentes a `usuarios.rol_id`, añade la llave foránea y retira `usuarios.rol`. Conserva usuarios y contraseñas. Se ejecuta **una sola vez** sobre una base que todavía tenga la columna `usuarios.rol`; una base nueva ya contiene el modelo actualizado por `db/init/`.

Antes de aplicarla, hacer un respaldo de la base. En PowerShell, desde la raíz del proyecto y con Docker Compose iniciado:

```powershell
Get-Content -Raw -Encoding UTF8 db/migraciones/001_roles.sql | docker compose exec -T db sh -c 'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; exec mysql -u root "$MYSQL_DATABASE"'
```

MySQL confirma los cambios de estructura (`ALTER TABLE`) de forma implícita, por lo que una falla intermedia no se revierte como una transacción común. Revisar el error y el estado de la base antes de repetir o restaurar el respaldo. No se ejecuta automáticamente al iniciar Docker porque el volumen ya existe.
