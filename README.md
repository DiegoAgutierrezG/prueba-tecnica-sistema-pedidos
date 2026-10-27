# Sistema de pedidos de Distribuidora Andina

Aplicación interna de pedidos, inventario, pagos y ventas del mes para Distribuidora Andina S.A.S. Este repositorio está en desarrollo. Ya incluye el entorno local, el esquema, datos de prueba, inicio y cierre de sesión, permisos por rol y los módulos de clientes, productos, pedidos, pagos y ventas del mes.

## Documentos de referencia

- [Requisitos en PDF](docs/Requerimientos_Aplicativo_Pedidos.pdf) y [en Word](docs/Requerimientos_Aplicativo_Pedidos.docx).
- [Modelo de datos y casos de uso](docs/Diagramas_Aplicativo_Pedidos.html).

Los documentos originales de requisitos y diagramas se conservan sin modificaciones. Las reglas de negocio de la sección 4 rigen los cálculos, el crédito y los movimientos de inventario.

## Entorno local

Se requiere Docker Desktop con contenedores Linux y Docker Compose. No es necesario instalar PHP ni MySQL en el equipo: las imágenes de Docker incluyen PHP 8.2 con Apache y MySQL 8.0. Bootstrap 5.3.8 está incluido localmente en `public/assets/bootstrap/`, junto con su licencia.

Para arrancar una copia nueva:

1. Ejecutar `docker compose up -d` desde la raíz del proyecto.
2. Abrir <http://localhost:8080>. Sin una sesión activa, el sistema lleva al formulario de ingreso.

Compose usa valores de demostración locales cuando no existe `.env`. Para personalizar la configuración, copia `.env.example` a `.env` y define contraseñas propias; Git ignora ese archivo. Los valores de demostración son públicos y solo sirven para esta prueba local: antes de desplegar en AWS hay que suministrar credenciales privadas mediante variables de entorno.

## Configuración de contenedores

| Servicio | Imagen | Acceso local | Función |
| --- | --- | --- | --- |
| `app` | `php:8.2-apache` con `pdo_mysql` | <http://localhost:8080> | Sirve `public/` y recibe la configuración de la base mediante variables de entorno. |
| `db` | `mysql:8.0` | `127.0.0.1:3307` | Almacena los datos en el volumen `datos_mysql` e inicializa la base con los scripts de `db/init/` la primera vez. |

`docker-compose.yml` acepta `DB_NAME`, `DB_USER`, `DB_PASSWORD` y `MYSQL_ROOT_PASSWORD` desde `.env` o variables de entorno; si faltan, usa los valores de demostración. `APP_PORT` y `MYSQL_PORT` permiten cambiar los puertos locales, que por defecto son 8080 y 3307. `DB_HOST=db` y `DB_PORT=3306` son direcciones internas entre los contenedores. El puerto 3307 solo es para conectar herramientas locales a MySQL. Las variables `TZ` se fijan en `America/Bogota`; el código de fechas deberá aplicar esta zona horaria de forma coherente.

## Datos de prueba

Al inicializar una base nueva, MySQL ejecuta `db/init/01_schema.sql` y luego `db/init/02_seed.sql`. Este último carga tres roles, tres usuarios, cuatro categorías, 25 productos, ocho clientes y un movimiento de entrada por cada producto con stock inicial. Son datos ficticios para comprobar el comportamiento del sistema. Los scripts fijan `utf8mb4` para cargar bien tildes y eñes. Para una base creada antes de la tabla `roles`, se incluye la migración de una sola ejecución en [db/migraciones](db/migraciones/README.md); no hace falta borrar el volumen.

| Rol | Correo |
| --- | --- |
| Administrador | `admin@andina.co` |
| Vendedor | `vendedor@andina.co` |
| Bodega | `bodega@andina.co` |

La contraseña de prueba de las tres cuentas es `AndinaDemo2026!`. La base guarda únicamente su hash bcrypt (`$2y$`, costo 10), comprobado con `password_get_info()` en PHP 8.2.35. Es un hash irreversible, no un cifrado que permita recuperar la contraseña. Estas credenciales son solo para la demostración local.

## Acceso y permisos

El ingreso usa correo y contraseña de un usuario activo. `usuarios.rol_id` referencia a `roles.id`; el código estable `roles.codigo` identifica la matriz de permisos y `roles.nombre` se muestra en la interfaz. El servidor vuelve a consultar el rol y a comprobar los permisos al abrir cada sección. Un usuario sin sesión vuelve al formulario y uno sin permiso recibe HTTP 403. La salida se realiza por POST con token CSRF.

La autenticación usa una sesión de PHP identificada por una cookie `HttpOnly` y `SameSite=Lax` (`Secure` cuando hay HTTPS). El token CSRF protege los formularios; no es un token de acceso. La sesión vence tras **30 minutos sin actividad** o **8 horas desde el ingreso**, aunque haya actividad. El servidor comprueba ambos límites en la siguiente petición y pide ingresar de nuevo. Estos límites se configuran en `public/includes/sesion.php`.

| Rol | Acciones asignadas |
| --- | --- |
| Administrador | Todas las acciones de vendedor y bodega; además administra productos, activa o desactiva clientes, anula pedidos y consulta ventas. |
| Vendedor | Crea y edita clientes, crea y confirma pedidos y registra pagos. |
| Bodega | Despacha pedidos y los marca como entregados. |

Los tres roles tienen permiso para consultar clientes, productos y pedidos. Clientes muestra activos por defecto y permite filtrar por activos, inactivos o todos, además de buscar por NIT o razón social. Administrador y vendedor pueden crear y editar; solo el administrador puede desactivar o reactivar. El servidor valida el NIT y su dígito de verificación, el cupo y los permisos. Productos permite consultar activos en páginas de 20, buscar por SKU o nombre y filtrar por categoría; solo el administrador puede crear, editar, desactivar y reactivar. Al crear con stock positivo, se registra una entrada de inventario en la misma transacción. En pedidos, administrador y vendedor pueden crear borradores y confirmarlos; bodega y administrador pueden despachar y entregar; solo el administrador puede anular. La confirmación verifica cupo y existencias en una transacción, descuenta stock y registra salidas. La anulación de un confirmado sin pagos devuelve stock una vez y registra reversiones. En pagos, administrador y vendedor registran abonos por transferencia, efectivo o cheque a pedidos confirmados, despachados o entregados. El servidor impide valores superiores al saldo incluso si llegan dos solicitudes simultáneas. Solo el administrador consulta ventas del mes: cantidad de pedidos y total de los confirmados, despachados y entregados creados durante el mes elegido en hora de Bogotá. `public/includes/permisos.php` contiene la matriz de permisos que deben usar todas las páginas y acciones.

## Estado y próximos pasos

El esquema y los datos de prueba se ejecutaron correctamente en MySQL 8.0.46, incluso desde una copia nueva sin `.env`. El acceso con los tres roles y las operaciones de clientes, productos, pedidos, pagos y ventas del mes se probaron por HTTP, incluidos rechazos 403 y CSRF. Las transacciones de pedidos, los abonos concurrentes y los límites del mes en Bogotá se verificaron en bases temporales separadas. El stock inicial y las salidas o reversiones de pedidos se guardan junto con sus movimientos; las fechas de pedidos, movimientos y pagos se escriben en UTC y se muestran en Bogotá. Quedan el despliegue en AWS y las funcionalidades opcionales que se decidan abordar. El código, las tablas, los mensajes y la documentación del dominio se mantendrán en español.

Las verificaciones automatizadas se ejecutaron en bases temporales. Sus scripts se conservan localmente fuera de la entrega.
