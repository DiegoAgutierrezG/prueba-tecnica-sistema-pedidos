<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/vista.php';
require_once __DIR__ . '/funciones.php';

$usuario = exigir_permiso('pedidos.crear');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    mostrar_error(405, 'Método no permitido', 'Usa el formulario para crear un pedido.', $usuario);
}

$errorFormulario = '';
$clienteElegido = '';
$ordenCompra = '';
$filas = [['producto_id' => '', 'cantidad' => '', 'nombre' => '', 'stock' => '']];
$nombreCliente = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token_csrf'] ?? null;
    if (!csrf_valido(is_string($token) ? $token : null)) {
        mostrar_error(403, 'Formulario vencido', 'Recarga la página e intenta crear el pedido de nuevo.', $usuario);
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
        $pedidoId = crear_pedido(conectar_bd(), $usuario['id'], $_POST);
        header('Location: /pedidos/detalle.php?id=' . $pedidoId, true, 303);
        exit;
    } catch (ReglaPedido $error) {
        $errorFormulario = $error->getMessage();
    } catch (Throwable $error) {
        error_log((string) $error);
        mostrar_error(503, 'Servicio no disponible', 'No se pudo guardar el pedido.', $usuario);
    }
}

try {
    $conexion = conectar_bd();
    if ($clienteElegido !== '') {
        $consultaCliente = $conexion->prepare('SELECT razon_social, nit FROM clientes WHERE id = :id AND activo = 1');
        $consultaCliente->execute(['id' => id_pedido_valido($clienteElegido) ?? 0]);
        $cliente = $consultaCliente->fetch();
        if ($cliente !== false) {
            $nombreCliente = $cliente['razon_social'] . ' · ' . $cliente['nit'];
        } else {
            $clienteElegido = '';
        }
    }
    $consultaProducto = $conexion->prepare('SELECT sku, nombre, stock FROM productos WHERE id = :id AND activo = 1');
    foreach ($filas as &$fila) {
        if ($fila['producto_id'] === '') continue;
        $consultaProducto->execute(['id' => id_pedido_valido($fila['producto_id']) ?? 0]);
        $producto = $consultaProducto->fetch();
        if ($producto !== false) {
            $fila['nombre'] = $producto['sku'] . ' · ' . $producto['nombre'];
            $fila['stock'] = (string) $producto['stock'];
        } else {
            $fila['producto_id'] = '';
        }
    }
    unset($fila);
} catch (Throwable $error) {
    error_log((string) $error);
    mostrar_error(503, 'Servicio no disponible', 'No se pudieron cargar las selecciones del pedido.', $usuario);
}

mostrar_cabecera('Nuevo pedido', $usuario, false, 'pedidos');
?>
<nav aria-label="Ruta de navegación" class="mb-4">
    <a href="/index.php" class="text-decoration-none">Inicio</a> <span class="text-secondary">/ </span>
    <a href="/pedidos/index.php" class="text-decoration-none">Pedidos</a> <span class="text-secondary">/ Nuevo pedido</span>
</nav>
<div class="row justify-content-center">
    <div class="col-xl-10">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <h1 class="h2 mb-2">Nuevo pedido</h1>
                <p class="text-secondary">Se guardará como borrador. El stock y el cupo se comprueban al confirmarlo.</p>
                <?php if ($errorFormulario !== ''): ?>
                    <div class="alert alert-danger" role="alert"><?= escapar_html($errorFormulario) ?></div>
                <?php endif; ?>

                <form action="/pedidos/nuevo.php" method="post">
                    <input type="hidden" name="token_csrf" value="<?= escapar_html(token_csrf()) ?>">
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
                    <p class="text-secondary small">Cada producto puede aparecer una vez. El precio y los descuentos se fijan al guardar el borrador.</p>
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
                        <button type="submit" class="btn btn-primary">Guardar borrador</button>
                        <a href="/pedidos/index.php" class="btn btn-outline-secondary">Cancelar</a>
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
<script src="/assets/js/pedidos.js?v=3" defer></script>
<?php mostrar_pie(true); ?>
