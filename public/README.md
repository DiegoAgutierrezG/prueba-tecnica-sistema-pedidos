# public

Raíz web de la aplicación. El acceso usa `index.php`, `login.php`, `logout.php`, `modulo.php` y las funciones de `includes/`. Clientes, productos, pedidos, pagos y reportes ya tienen funciones de negocio:

| Carpeta | Contenido |
| --- | --- |
| `includes/` | Conexión a la base, sesión, matriz de permisos, límite de intentos de ingreso y vista común. |
| `clientes/` | Listar activos, inactivos o ambos, buscar por NIT o razón social, crear, editar, desactivar y reactivar. Valida NIT, cupo, permisos y CSRF. |
| `productos/` | Listado activo paginado de 20, búsqueda, categoría y orden por stock o mínimo; resalta las filas bajo mínimo. Permite crear, editar, desactivar, reactivar y registrar entradas de stock para varios productos activos. Cada aumento genera un movimiento de entrada. |
| `pedidos/` | Listar y buscar por consecutivo u orden de compra; crear borradores, ver detalle, confirmar, despachar, entregar y anular. `calculos.php` concentra descuentos, IVA y redondeo. |
| `pagos/` | Dos vistas: pagos con saldo y control de devoluciones pendientes o registradas. Permite registrar abonos y constancias de devolución; valida estado, saldo, método, permisos y CSRF. |
| `reportes/` | Ventas del mes y por vendedor, cartera por cliente y productos activos bajo mínimo con faltante. |

`modulo.php` redirige las rutas antiguas de clientes, productos, pedidos, pagos y reportes a sus páginas reales. Cada ruta comprueba el permiso correspondiente en el servidor antes de mostrar una página o ejecutar una acción.

En el formulario de clientes, el número del NIT y el dígito de verificación se escriben en casillas separadas. PHP valida ambas partes y guarda el valor normalizado con guion en `clientes.nit`.

En productos, los tres roles consultan el listado de activos; solo el administrador crea, edita, cambia estados y registra entradas de stock. `entrada.php` permite añadir hasta 30 productos activos con cantidades positivas en una operación y muestra el stock actual de cada selección en un campo de solo lectura; valida de nuevo en el servidor, bloquea las filas por ID y guarda cada aumento junto con su movimiento dentro de una transacción. El stock no se modifica desde la edición del catálogo.

En pedidos, los tres roles consultan el listado y detalle. Administrador y vendedor crean borradores y confirman; bodega y administrador despachan y entregan; solo el administrador anula. El formulario muestra el stock consultado y abre una alerta si la cantidad lo supera. Antes de guardar reúne los faltantes y permite revisar o guardar expresamente solo un borrador. La confirmación valida nuevamente el cupo y el stock dentro de una transacción. Al anular un confirmado, incluso si tiene abonos, el stock se restituye una sola vez. Los pagos permanecen en el historial y el dinero recibido queda pendiente de devolución; la anulación no simula una transferencia al cliente.

En pagos, administrador y vendedor pueden registrar abonos a pedidos confirmados, despachados o entregados. Cada abono debe ser positivo, usar transferencia, efectivo o cheque y no superar el saldo. La operación bloquea pedido y cliente, recalcula lo pagado dentro de una transacción y guarda fecha UTC y usuario. El historial se presenta con la hora de Bogotá. El administrador tiene una segunda vista de devoluciones, con pendientes y registradas en una tabla. Desde la fecha real de anulación, la interfaz marca atención a las 24 horas y urgencia a las 48; son umbrales internos, visibles al abrir o recargar la página. Solo el administrador registra una constancia de devolución total una vez que el dinero fue entregado fuera del sistema. El servidor impide registrarla dos veces; bodega no entra a estas rutas.

`includes/moneda.php` da el mismo formato COP a clientes, productos, pedidos, pagos y reportes: `$ 150.000` para pesos enteros. Cuando un valor contiene centavos, los indica en palabras, por ejemplo `$ 150.000 y 50 centavos`; así la cifra principal no lleva decimales y el importe exacto no se pierde. Las operaciones siguen usando centavos enteros en PHP y `DECIMAL(14,2)` en MySQL. Los formularios permiten introducir centavos cuando existan, sin separadores de miles.

En reportes, solo el administrador consulta las ventas de un mes elegido, la cartera por cliente y los productos bajo mínimo. El servidor convierte el inicio del mes y el inicio del siguiente desde `America/Bogota` a UTC y consulta el intervalo semiabierto. Cuenta pedidos confirmados, despachados y entregados según su fecha de creación; muestra el total de sus pedidos y el desglose por vendedor sin volver a sumar pagos. Un mes inválido recibe HTTP 400.

Las páginas internas comparten un menú lateral verde en `includes/vista.php`. El botón superior lo contrae en escritorio y lo abre como panel en móvil. Los submenús aparecen debajo de cada módulo, con sangría y puntos: se abren al pasar el cursor en escritorio, con la flecha en móvil y mediante teclado. La barra contraída se amplía temporalmente para leer sus opciones. Dan acceso directo a creación de clientes, productos y pedidos, entrada de stock, devoluciones, listado de pedidos y reportes de cartera e inventario. `SUBOPCIONES_MENU` y `subopciones_visibles()` filtran cada acceso según su permiso antes de generar el HTML. `assets/css/aplicacion.css` y `assets/js/navegacion.js` controlan su aspecto y comportamiento; cada URL exige además su permiso en PHP. Los botones de las páginas se conservan. La salida sigue siendo POST con token CSRF.
