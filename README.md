# Sistema de pedidos de Distribuidora Andina

Aplicación interna de pedidos, inventario, pagos y reportes para Distribuidora Andina S.A.S. Incluye el entorno local, el esquema y los datos de prueba, el acceso con permisos por rol y los módulos de clientes, productos, pedidos, pagos y reportes. El despliegue público aún está pendiente.

## Documentos de referencia

- [Requisitos en PDF](docs/Requerimientos_Aplicativo_Pedidos.pdf) y [en Word](docs/Requerimientos_Aplicativo_Pedidos.docx).
- [Modelo de datos y casos de uso](docs/Diagramas_Aplicativo_Pedidos.html).
- [Arquitectura y tecnologías (Word)](docs/01_Arquitectura_y_tecnologias_v1.1.docx).

Los documentos originales de requisitos y diagramas se conservan sin modificaciones. Las reglas de negocio de la sección 4 rigen los cálculos, el crédito y los movimientos de inventario.

## Entorno local

Se requiere Docker Desktop con contenedores Linux y Docker Compose. No es necesario instalar PHP ni MySQL en el equipo: las imágenes de Docker incluyen PHP 8.2 con Apache y MySQL 8.0. Bootstrap 5.3.8 está incluido localmente en `public/assets/bootstrap/`, junto con su licencia.

Para arrancar una copia nueva:

1. Ejecutar `docker compose up -d` desde la raíz del proyecto.
2. Abrir <http://localhost:8080>. Sin una sesión activa, el sistema lleva al formulario de ingreso.

Si no existe `.env`, el primer arranque genera dos contraseñas aleatorias: una para la aplicación y otra para la administración de MySQL. Se guardan en volúmenes privados de Docker, fuera de Git. Si prefieres claves propias, copia `.env.example` a `.env` y defínelas **antes del primer arranque**; Git ignora ese archivo. Una base ya inicializada conserva sus claves: cambiar `.env` por sí solo no cambia las contraseñas de MySQL. La instalación local existente puede seguir usando su `.env` y sus datos.

## Configuración de contenedores

| Servicio | Imagen | Acceso local | Función |
| --- | --- | --- | --- |
| `app` | `php:8.2-apache` con `pdo_mysql` | <http://localhost:8080> | Sirve `public/` y lee la clave de conexión desde un archivo privado montado solo para lectura. |
| `db` | `mysql:8.0` | `127.0.0.1:3307` | Guarda los datos en `datos_mysql`, crea las claves privadas al primer arranque e inicializa la base con `db/init/`. |

`DB_NAME` y `DB_USER` tienen valores por defecto que son nombres, no credenciales. `DB_PASSWORD` y `MYSQL_ROOT_PASSWORD` son opcionales en una instalación nueva; si se omiten, `db/docker/preparar-claves.sh` genera claves aleatorias y las guarda en `clave_aplicacion` y `clave_root`. PHP recibe únicamente el archivo de la clave de aplicación; la clave de administración permanece en `db`. `APP_PORT` y `MYSQL_PORT` permiten cambiar los puertos locales, que por defecto son 8080 y 3307. `DB_HOST=db` y `DB_PORT=3306` son direcciones internas entre contenedores. El puerto 3307 solo permite conectar herramientas del mismo equipo a MySQL. La zona horaria del entorno es `America/Bogota`.

## Datos de prueba

Al inicializar una base nueva, MySQL ejecuta `db/init/01_schema.sql` y luego `db/init/02_seed.sql`. Este último carga tres roles, tres usuarios, 18 bancos, cuatro categorías, 25 productos, ocho clientes y un movimiento de entrada por cada producto con stock inicial. Los bancos forman un catálogo inicial; los demás son datos ficticios para comprobar el comportamiento del sistema. Los scripts fijan `utf8mb4` para cargar bien tildes y eñes. La inicialización solo se ejecuta con un volumen de MySQL vacío; una base existente no se modifica al reiniciar los contenedores.

| Rol | Correo |
| --- | --- |
| Administrador | `admin@andina.co` |
| Vendedor | `vendedor@andina.co` |
| Bodega | `bodega@andina.co` |

La contraseña de prueba de las tres cuentas es `AndinaDemo2026!`. La base guarda únicamente su hash bcrypt (`$2y$`, costo 10), comprobado con `password_get_info()` en PHP 8.2.35. Es un hash irreversible, no un cifrado que permita recuperar la contraseña. Estas credenciales son solo para la demostración local.

## Acceso y permisos

El ingreso usa correo y contraseña de un usuario activo. `usuarios.rol_id` referencia a `roles.id`; el código estable `roles.codigo` identifica la matriz de permisos y `roles.nombre` se muestra en la interfaz. El servidor vuelve a consultar el rol y a comprobar los permisos al abrir cada sección. Un usuario sin sesión vuelve al formulario y uno sin permiso recibe HTTP 403. La salida se realiza por POST con token CSRF.

La autenticación usa una sesión de PHP identificada por una cookie `HttpOnly` y `SameSite=Lax` (`Secure` cuando hay HTTPS). El token CSRF protege los formularios; no es un token de acceso. La sesión vence tras **30 minutos sin actividad** o **8 horas desde el ingreso**, aunque haya actividad. El servidor comprueba ambos límites en la siguiente petición y pide ingresar de nuevo. Estos límites se configuran en `public/includes/sesion.php`. El ingreso limita los intentos fallidos por correo y por IP durante 15 minutos; los contadores se guardan en MySQL para que borrar cookies no los reinicie.

| Rol | Acciones asignadas |
| --- | --- |
| Administrador | Todas las acciones de vendedor y bodega; además administra productos, activa o desactiva clientes, anula pedidos y consulta ventas. |
| Vendedor | Crea y edita clientes, crea y confirma pedidos y registra pagos. |
| Bodega | Despacha pedidos y los marca como entregados. |

Los tres roles tienen permiso para consultar clientes, productos y pedidos. Clientes muestra activos por defecto y permite filtrar por activos, inactivos o todos, además de buscar por NIT o razón social. Administrador y vendedor pueden crear y editar; solo el administrador puede desactivar o reactivar. El servidor valida el NIT y su dígito de verificación, el cupo y los permisos. Productos permite consultar activos en páginas de 20, buscar por SKU o nombre y filtrar por categoría; solo el administrador puede crear, editar, desactivar y reactivar. Al crear con stock positivo, se registra una entrada de inventario en la misma transacción. En pedidos, administrador y vendedor pueden crear, editar y confirmar borradores; bodega y administrador pueden despachar y entregar; solo el administrador puede anular. La edición usa el mismo formulario y conserva el consecutivo y la fecha: las líneas existentes mantienen su precio e IVA, las nuevas usan los valores vigentes y el descuento se ajusta al cambiar cliente o cantidad. La confirmación verifica cupo y existencias en una transacción, descuenta stock y registra salidas. La anulación de un confirmado devuelve stock una vez y registra reversiones, aunque tenga pagos. Los abonos quedan en el historial como dinero pendiente de devolver; el administrador registra la devolución total únicamente después de realizarla fuera del sistema. En pagos, administrador y vendedor registran abonos por transferencia, efectivo o cheque a pedidos confirmados, despachados o entregados. El servidor impide valores superiores al saldo incluso si llegan dos solicitudes simultáneas. Solo el administrador consulta reportes, incluido el inventario bajo mínimo y las ventas del mes. `public/includes/permisos.php` contiene la matriz de permisos que deben usar todas las páginas y acciones.

## Supuestos y decisiones de diseño

- **Cobros y devoluciones:** el sistema registra dinero recibido o devuelto fuera de la aplicación; no se conecta con bancos ni procesa transferencias. Los datos de banco, referencia o caja sirven para consultar después cómo se realizó cada pago. Al anular un pedido con abonos, el inventario se revierte una sola vez y el dinero queda pendiente de devolver. El administrador registra la devolución únicamente después de comprobar que se hizo al cliente.
- **Borradores e inventario:** crear o editar un borrador no reserva unidades. El stock se comprueba y descuenta al confirmar, dentro de una transacción; por ello, una cantidad disponible al crear el pedido puede dejar de estarlo al confirmarlo. Las líneas existentes de un borrador conservan el precio y el IVA registrados; las nuevas toman los vigentes.
- **Cálculos y moneda:** cada línea aplica el descuento y después el IVA que corresponda al producto. La suma del pedido se ajusta una sola vez, hacia arriba, al siguiente múltiplo de $ 50 COP. Los abonos se comparan con ese total final. Los importes se presentan sin cifras decimales y, cuando existen centavos, se expresan en palabras para no ocultarlos.
- **Fechas:** pedidos, pagos y movimientos se guardan en UTC; la interfaz y los límites del reporte mensual usan `America/Bogota`. Así, un pedido próximo a medianoche se asigna al mes que corresponde en Bogotá.
- **Cuentas y catálogos:** los roles disponibles son administrador, vendedor y bodega. Los usuarios y productos iniciales son ficticios y las contraseñas publicadas arriba son solo para la demostración local; deben sustituirse antes de exponer el sistema públicamente. Los bancos cargados son un catálogo inicial, no una verificación bancaria.

## Estado de entrega y pendientes

En local, el esquema y los datos de prueba se ejecutaron en MySQL 8.0.46, también desde una copia nueva sin `.env`. Se comprobaron por HTTP el acceso de los tres roles, los módulos principales, los rechazos por permisos y CSRF, los abonos concurrentes, la anulación con devolución pendiente y los límites mensuales en Bogotá. Las opciones adicionales incluyen cartera por cliente, ventas por vendedor, inventario bajo mínimo, indicadores de inicio, búsqueda de pedidos y límite de intentos de ingreso. La prueba unitaria de totales se incluye en `tests/totales_unitarios.php`; las demás pruebas de desarrollo se conservan localmente fuera de la entrega. Se ejecuta sin modificar la base de datos:

```powershell
docker compose run --rm --no-deps -v "${PWD}/tests:/pruebas:ro" app php /pruebas/totales_unitarios.php
```

Queda pendiente el despliegue solicitado en AWS, configurar credenciales distintas de las cuentas de demostración y publicar la URL de acceso. El código, las tablas, los mensajes y la documentación del dominio se mantienen en español. Las alertas de devoluciones pendientes aparecen al abrir o recargar Pagos: a las 24 horas requieren atención y a las 48 horas se marcan como urgentes.
