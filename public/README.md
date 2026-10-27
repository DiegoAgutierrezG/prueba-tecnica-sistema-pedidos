# public

Raíz web de la aplicación. El acceso usa `index.php`, `login.php`, `logout.php`, `modulo.php` y las funciones de `includes/`. Clientes, productos, pedidos, pagos y reportes ya tienen funciones de negocio:

| Carpeta | Contenido |
| --- | --- |
| `includes/` | Conexión a la base, sesión, matriz de permisos y vista común. |
| `clientes/` | Listar activos, inactivos o ambos, buscar por NIT o razón social, crear, editar, desactivar y reactivar. Valida NIT, cupo, permisos y CSRF. |
| `productos/` | Listado activo paginado de 20, búsqueda, categoría y orden por stock o mínimo; resalta las filas bajo mínimo. Permite crear, editar, desactivar y reactivar. El stock inicial positivo se registra con un movimiento de entrada. |
| `pedidos/` | Listar, crear borradores, ver detalle, confirmar, despachar, entregar y anular. `calculos.php` concentra descuentos, IVA y redondeo. |
| `pagos/` | Listar pedidos con saldo, registrar abonos y consultar historial. Valida estado, saldo, método, permisos y CSRF. |
| `reportes/` | Ventas del mes para administrador: cantidad, total y pedidos incluidos según fecha de creación en Bogotá. |

`modulo.php` redirige las rutas antiguas de clientes, productos, pedidos, pagos y reportes a sus páginas reales. Cada ruta comprueba el permiso correspondiente en el servidor antes de mostrar una página o ejecutar una acción.

En el formulario de clientes, el número del NIT y el dígito de verificación se escriben en casillas separadas. PHP valida ambas partes y guarda el valor normalizado con guion en `clientes.nit`.

En productos, los tres roles consultan el listado de activos; solo el administrador crea, edita y cambia estados. El stock se define al crear y no se modifica desde la edición del catálogo: cualquier cambio de existencias debe acompañarse de un movimiento de inventario.

En pedidos, los tres roles consultan el listado y detalle. Administrador y vendedor crean borradores y confirman; bodega y administrador despachan y entregan; solo el administrador anula. La confirmación valida nuevamente el cupo y el stock dentro de una transacción. Un pedido confirmado con pagos no se puede anular hasta definir la devolución; no se eliminan pagos ni se altera el saldo automáticamente.

En pagos, administrador y vendedor pueden registrar abonos a pedidos confirmados, despachados o entregados. Cada abono debe ser positivo, usar transferencia, efectivo o cheque y no superar el saldo. La operación bloquea pedido y cliente, recalcula lo pagado dentro de una transacción y guarda fecha UTC y usuario. El historial se presenta con la hora de Bogotá. Bodega no tiene acceso a estas rutas.

En reportes, solo el administrador consulta las ventas de un mes elegido. El servidor convierte el inicio del mes y el inicio del siguiente desde `America/Bogota` a UTC y consulta el intervalo semiabierto. Cuenta pedidos confirmados, despachados y entregados según su fecha de creación; muestra el total de sus pedidos y no vuelve a sumar pagos. Un mes inválido recibe HTTP 400.

Las páginas internas comparten un menú lateral verde en `includes/vista.php`. El botón superior lo contrae en escritorio y lo abre como panel en móvil. `assets/css/aplicacion.css` y `assets/js/navegacion.js` controlan su aspecto y comportamiento; el menú muestra solo las secciones autorizadas, mientras cada URL mantiene su comprobación de permisos en PHP. La salida sigue siendo POST con token CSRF.
