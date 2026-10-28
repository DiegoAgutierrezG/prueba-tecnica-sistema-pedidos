<?php

declare(strict_types=1);

if (!isset($editarBorrador) || !is_bool($editarBorrador)) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/vista.php';
require_once __DIR__ . '/funciones.php';

$usuario = exigir_permiso('pedidos.crear');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    mostrar_error(405, 'Método no permitido', 'Usa el formulario del pedido.', $usuario);
}

$pedidoId = $editarBorrador ? id_pedido_valido($_GET['id'] ?? null) : null;
if ($editarBorrador && $pedidoId === null) {
    mostrar_error(404, 'Pedido no encontrado', 'El pedido solicitado no existe. Selecciona otro desde la lista de pedidos.', $usuario);
}

$errorFormulario = '';
$clienteElegido = '';
$ordenCompra = '';
$filas = [['producto_id' => '', 'cantidad' => '', 'nombre' => '', 'stock' => '']];
$nombreCliente = '';
$consecutivo = '';
$versionBorrador = '';

if ($editarBorrador) {
    try {
        $conexion = conectar_bd();
        $consulta = $conexion->prepare(
            'SELECT id, consecutivo, estado, cliente_id, orden_compra, subtotal, descuento, iva, total
             FROM pedidos WHERE id = :id LIMIT 1'
        );
        $consulta->execute(['id' => $pedidoId]);
        $pedido = $consulta->fetch();
        if ($pedido === false) mostrar_error(404, 'Pedido no encontrado', 'El pedido solicitado no existe. Selecciona otro desde la lista de pedidos.', $usuario);
        if ($pedido['estado'] !== 'borrador') {
            mostrar_error(409, 'Pedido no editable', 'Solo se pueden editar pedidos en borrador. Vuelve al detalle para ver su estado actual.', $usuario);
        }
        $consecutivo = $pedido['consecutivo'];
        $versionBorrador = version_borrador_pedido($conexion, $pedidoId, $pedido);
        $clienteElegido = (string) $pedido['cliente_id'];
        $ordenCompra = $pedido['orden_compra'] ?? '';
        $filas = [];
        foreach (detalle_lineas_pedido($conexion, $pedidoId) as $linea) {
            $filas[] = [
                'producto_id' => (string) $linea['producto_id'],
                'cantidad' => (string) $linea['cantidad'],
                'nombre' => $linea['sku'] . ' · ' . $linea['nombre'] . ((int) $linea['activo'] === 1 ? '' : ' (inactivo)'),
                'stock' => (string) $linea['stock'],
            ];
        }
        if ($filas === []) $filas[] = ['producto_id' => '', 'cantidad' => '', 'nombre' => '', 'stock' => ''];
    } catch (Throwable $error) {
        error_log((string) $error);
        mostrar_error(503, 'Servicio no disponible', 'No se pudo cargar el borrador. Vuelve a la lista de pedidos e inténtalo de nuevo en unos minutos.', $usuario);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token_csrf'] ?? null;
    if (!csrf_valido(is_string($token) ? $token : null)) {
        mostrar_error(403, 'Formulario vencido', 'Recarga la página e intenta guardar el pedido de nuevo.', $usuario);
    }

    if ($editarBorrador) {
        $versionBorrador = is_string($_POST['version_borrador'] ?? null) ? $_POST['version_borrador'] : '';
    }
    $clienteElegido = is_string($_POST['cliente_id'] ?? null) ? $_POST['cliente_id'] : '';
    $ordenCompra = is_string($_POST['orden_compra'] ?? null) ? $_POST['orden_compra'] : '';
    $ids = $_POST['producto_id'] ?? [];
    $cantidades = $_POST['cantidad'] ?? [];
    if (is_array($ids) && is_array($cantidades)) {
        $filas = [];
        foreach (array_slice($ids, 0, 30) as $posicion => $id) {
            $filas[] = [
                'producto_id' => is_string($id) ? $id : '',
                'cantidad' => is_string($cantidades[$posicion] ?? null) ? $cantidades[$posicion] : '',
                'nombre' => '',
                'stock' => '',
            ];
        }
        if ($filas === []) $filas[] = ['producto_id' => '', 'cantidad' => '', 'nombre' => '', 'stock' => ''];
    }

    try {
        if ($editarBorrador) {
            actualizar_borrador_pedido(conectar_bd(), $pedidoId, $_POST, $versionBorrador);
            $_SESSION['aviso_pedidos'] = 'Cambios guardados en el borrador.';
        } else {
            $pedidoId = crear_pedido(conectar_bd(), $usuario['id'], $_POST);
            $_SESSION['aviso_pedidos'] = 'Pedido guardado como borrador.';
        }
        header('Location: /pedidos/detalle.php?id=' . $pedidoId, true, 303);
        exit;
    } catch (ReglaPedido $error) {
        if ($editarBorrador && in_array($error->getCode(), [404, 409], true)) {
            mostrar_error($error->getCode(), 'No se pudo editar el pedido', $error->getMessage(), $usuario);
        }
        $errorFormulario = $error->getMessage();
    } catch (Throwable $error) {
        error_log((string) $error);
        mostrar_error(503, 'Servicio no disponible', 'No se pudo confirmar el guardado del pedido. Revisa la lista antes de intentarlo de nuevo.', $usuario);
    }
}

try {
    $conexion = conectar_bd();
    if ($clienteElegido !== '') {
        $consultaCliente = $conexion->prepare('SELECT razon_social, nit, activo FROM clientes WHERE id = :id');
        $consultaCliente->execute(['id' => id_pedido_valido($clienteElegido) ?? 0]);
        $cliente = $consultaCliente->fetch();
        if ($cliente !== false) {
            $nombreCliente = $cliente['razon_social'] . ' · ' . $cliente['nit']
                . ((int) $cliente['activo'] === 1 ? '' : ' (inactivo)');
        } else {
            $clienteElegido = '';
        }
    }
    $consultaProducto = $conexion->prepare('SELECT sku, nombre, stock, activo FROM productos WHERE id = :id');
    foreach ($filas as &$fila) {
        if ($fila['producto_id'] === '') continue;
        $consultaProducto->execute(['id' => id_pedido_valido($fila['producto_id']) ?? 0]);
        $producto = $consultaProducto->fetch();
        if ($producto !== false) {
            $fila['nombre'] = $producto['sku'] . ' · ' . $producto['nombre']
                . ((int) $producto['activo'] === 1 ? '' : ' (inactivo)');
            $fila['stock'] = (string) $producto['stock'];
        } else {
            $fila['producto_id'] = '';
        }
    }
    unset($fila);
} catch (Throwable $error) {
    error_log((string) $error);
    mostrar_error(503, 'Servicio no disponible', 'No se pudieron cargar las selecciones del pedido. Vuelve a la lista e inténtalo de nuevo en unos minutos.', $usuario);
}

$tituloFormulario = $editarBorrador ? 'Editar borrador ' . $consecutivo : 'Nuevo pedido';
mostrar_cabecera($tituloFormulario, $usuario, false, 'pedidos');
?>
<nav aria-label="Ruta de navegación" class="mb-4">
    <a href="/index.php" class="text-decoration-none">Inicio</a> <span class="text-secondary">/ </span>
    <a href="/pedidos/index.php" class="text-decoration-none">Pedidos</a>
    <?php if ($editarBorrador): ?>
        <span class="text-secondary">/ </span><a href="/pedidos/detalle.php?id=<?= $pedidoId ?>" class="text-decoration-none"><?= escapar_html($consecutivo) ?></a>
        <span class="text-secondary">/ Editar borrador</span>
    <?php else: ?>
        <span class="text-secondary">/ Nuevo pedido</span>
    <?php endif; ?>
</nav>
<div class="row justify-content-center">
    <div class="col-xl-10">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <h1 class="h2 mb-2"><?= escapar_html($tituloFormulario) ?></h1>
                <p class="text-secondary"><?= $editarBorrador
                    ? 'Los productos existentes conservan su precio e IVA. Los nuevos usan los valores actuales; el descuento se ajusta si cambias cliente o cantidad. El stock y el cupo se comprobarán al confirmar.'
                    : 'Se guardará como borrador. El stock y el cupo se comprueban al confirmarlo.' ?></p>
                <?php if ($errorFormulario !== ''): ?>
                    <div class="alert alert-danger" role="alert"><?= escapar_html($errorFormulario) ?></div>
                <?php endif; ?>

                <form action="<?= $editarBorrador ? '/pedidos/editar.php?id=' . $pedidoId : '/pedidos/nuevo.php' ?>" method="post" data-formulario-pedido>
                    <input type="hidden" name="token_csrf" value="<?= escapar_html(token_csrf()) ?>">
                    <?php if ($editarBorrador): ?>
                        <input type="hidden" name="version_borrador" value="<?= escapar_html($versionBorrador) ?>">
                    <?php endif; ?>
                    <div class="row g-3 mb-4">
                        <div class="col-md-7">
                            <div class="campo-busqueda" data-buscador="cliente">
                                <label for="cliente-busqueda" class="form-label">Cliente activo</label>
                                <input id="cliente-busqueda" type="search" class="form-control" data-entrada-busqueda
                                    value="<?= escapar_html($nombreCliente) ?>" placeholder="Escribe razón social o NIT"
                                    autocomplete="off" maxlength="100" role="combobox" aria-autocomplete="list"
                                    aria-controls="resultados-cliente" aria-expanded="false" required>
                                <input type="hidden" name="cliente_id" data-id-seleccionado value="<?= escapar_html($clienteElegido) ?>">
                                <div id="resultados-cliente" class="resultados-busqueda" data-resultados-busqueda role="listbox" aria-label="Resultados de clientes" hidden></div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <label for="orden_compra" class="form-label">Orden de compra del cliente (opcional)</label>
                            <input id="orden_compra" name="orden_compra" type="text" class="form-control" value="<?= escapar_html($ordenCompra) ?>" maxlength="40">
                        </div>
                    </div>

                    <h2 class="h5">Productos</h2>
                    <p class="text-secondary small">Cada producto puede aparecer una vez. El precio y el IVA quedan guardados por línea; el descuento depende del cliente y la cantidad.</p>
                    <div id="lineas-pedido">
                        <?php foreach ($filas as $posicion => $fila): ?>
                            <div class="row g-2 align-items-end mb-2 linea-pedido" data-stock-actual="<?= escapar_html($fila['stock']) ?>">
                                <div class="col-sm-8 campo-busqueda" data-buscador="producto">
                                    <label class="form-label d-flex justify-content-between gap-2" for="producto-<?= $posicion ?>">Producto activo
                                        <span class="text-secondary small" data-stock-producto<?= $fila['stock'] === '' ? ' hidden' : '' ?>><?= $fila['stock'] === '' ? '' : 'Stock: ' . escapar_html($fila['stock']) ?></span>
                                    </label>
                                    <input id="producto-<?= $posicion ?>" type="search" class="form-control" data-entrada-busqueda
                                        value="<?= escapar_html($fila['nombre']) ?>" placeholder="Escribe SKU o nombre"
                                        autocomplete="off" maxlength="100" role="combobox" aria-autocomplete="list"
                                        aria-controls="resultados-producto-<?= $posicion ?>" aria-expanded="false">
                                    <input type="hidden" name="producto_id[]" data-id-seleccionado value="<?= escapar_html($fila['producto_id']) ?>">
                                    <div id="resultados-producto-<?= $posicion ?>" class="resultados-busqueda" data-resultados-busqueda role="listbox" aria-label="Resultados de productos" hidden></div>
                                </div>
                                <div class="col-sm-3">
                                    <label class="form-label" for="cantidad-<?= $posicion ?>">Cantidad</label>
                                    <input id="cantidad-<?= $posicion ?>" name="cantidad[]" type="text" inputmode="numeric" pattern="[1-9][0-9]*" maxlength="10" class="form-control" value="<?= escapar_html($fila['cantidad']) ?>" placeholder="1">
                                </div>
                                <div class="col-sm-1 d-grid"><button type="button" class="btn btn-outline-secondary" data-quitar-linea aria-label="Quitar línea" title="Quitar línea">×</button></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm mt-2" data-agregar-linea>Agregar otro producto</button>
                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="submit" class="btn btn-primary"><?= $editarBorrador ? 'Guardar cambios' : 'Guardar borrador' ?></button>
                        <a href="<?= $editarBorrador ? '/pedidos/detalle.php?id=' . $pedidoId : '/pedidos/index.php' ?>" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
                <template id="plantilla-linea">
                    <div class="row g-2 align-items-end mb-2 linea-pedido" data-stock-actual="">
                        <div class="col-sm-8 campo-busqueda" data-buscador="producto">
                            <label class="form-label d-flex justify-content-between gap-2" data-etiqueta-producto>Producto activo <span class="text-secondary small" data-stock-producto hidden></span></label>
                            <input type="search" class="form-control" data-entrada-busqueda placeholder="Escribe SKU o nombre"
                                autocomplete="off" maxlength="100" role="combobox" aria-autocomplete="list" aria-expanded="false">
                            <input type="hidden" name="producto_id[]" data-id-seleccionado>
                            <div class="resultados-busqueda" data-resultados-busqueda role="listbox" aria-label="Resultados de productos" hidden></div>
                        </div>
                        <div class="col-sm-3"><label class="form-label" data-etiqueta-cantidad>Cantidad</label><input name="cantidad[]" type="text" inputmode="numeric" pattern="[1-9][0-9]*" maxlength="10" class="form-control" placeholder="1"></div>
                        <div class="col-sm-1 d-grid"><button type="button" class="btn btn-outline-secondary" data-quitar-linea aria-label="Quitar línea" title="Quitar línea">×</button></div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
<dialog id="dialogo-stock-pedido" class="dialogo-confirmacion aviso-stock" aria-labelledby="titulo-stock-pedido" aria-describedby="descripcion-stock-pedido">
    <span class="icono-confirmacion" aria-hidden="true">
        <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a3.5 3.5 0 0 1 6 0l16 28a3.5 3.5 0 0 1-3 5H8a3.5 3.5 0 0 1-3-5zM24 18v11m0 5v1"/></svg>
    </span>
    <h2 id="titulo-stock-pedido">Stock insuficiente</h2>
    <p id="descripcion-stock-pedido">La cantidad solicitada supera las existencias consultadas.</p>
    <div id="resumen-stock-pedido" class="resumen-alerta-stock"></div>
    <p class="small">Puedes guardar un borrador, pero necesitarás stock suficiente para confirmarlo.</p>
    <div class="acciones-confirmacion">
        <button type="button" class="btn btn-outline-secondary" data-revisar-stock autofocus>Revisar cantidades</button>
        <button type="button" class="btn btn-primary" data-guardar-sin-stock hidden>Guardar solo borrador</button>
    </div>
</dialog>
<script src="/assets/js/pedidos.js?v=4" defer></script>
<?php mostrar_pie(true); ?>
