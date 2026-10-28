<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/vista.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/../pagos/funciones.php';

$usuario = exigir_permiso('pedidos.ver');
$id = id_pedido_valido($_GET['id'] ?? null);
if ($id === null) mostrar_error(404, 'Pedido no encontrado', 'El pedido solicitado no existe. Selecciona otro desde la lista de pedidos.', $usuario);

try {
    $conexion = conectar_bd();
    $consulta = $conexion->prepare(
        'SELECT p.id, p.consecutivo, p.estado, p.orden_compra, p.subtotal,
                p.descuento, p.iva, p.total, p.creado_en,
                c.razon_social AS cliente, c.nit, u.nombre AS creador
         FROM pedidos AS p
         INNER JOIN clientes AS c ON c.id = p.cliente_id
         INNER JOIN usuarios AS u ON u.id = p.usuario_id
         WHERE p.id = :id LIMIT 1'
    );
    $consulta->execute(['id' => $id]);
    $pedido = $consulta->fetch();
    if ($pedido === false) mostrar_error(404, 'Pedido no encontrado', 'El pedido solicitado no existe. Selecciona otro desde la lista de pedidos.', $usuario);
    $lineas = detalle_lineas_pedido($conexion, $id);
    $totales = calcular_totales_pedido($lineas);
    $consulta = $conexion->prepare('SELECT COALESCE(SUM(valor), 0.00) FROM pagos WHERE pedido_id = :id');
    $consulta->execute(['id' => $id]);
    $pagado = centavos_de_decimal((string) $consulta->fetchColumn());
    $devolucion = devolucion_pedido($conexion, $id);
    $devuelto = $devolucion === null ? 0 : centavos_de_decimal($devolucion['valor']);
    $total = centavos_de_decimal($pedido['total']);
} catch (Throwable $error) {
    error_log((string) $error);
    mostrar_error(503, 'Servicio no disponible', 'No se pudo consultar el detalle del pedido. Vuelve a la lista e inténtalo de nuevo en unos minutos.', $usuario);
}

$aviso = $_SESSION['aviso_pedidos'] ?? null;
unset($_SESSION['aviso_pedidos']);
$avisoStock = $_SESSION['aviso_stock_pedido'] ?? null;
unset($_SESSION['aviso_stock_pedido']);
$mensajeStock = is_array($avisoStock) && ($avisoStock['id'] ?? null) === $id
    && is_string($avisoStock['mensaje'] ?? null) ? $avisoStock['mensaje'] : null;
mostrar_cabecera($pedido['consecutivo'], $usuario, false, 'pedidos');
?>
<nav aria-label="Ruta de navegación" class="mb-4">
    <a href="/index.php" class="text-decoration-none">Inicio</a> <span class="text-secondary">/ </span>
    <a href="/pedidos/index.php" class="text-decoration-none">Pedidos</a>
    <span class="text-secondary"> / <?= escapar_html($pedido['consecutivo']) ?></span>
</nav>
<?php if (is_string($aviso) && $aviso !== ''): ?>
    <div class="alert alert-success" role="status"><?= escapar_html($aviso) ?></div>
<?php endif; ?>
<?php if ($mensajeStock !== null): ?>
    <dialog id="dialogo-stock-confirmacion" class="dialogo-confirmacion aviso-stock" aria-labelledby="titulo-stock-confirmacion" aria-describedby="descripcion-stock-confirmacion">
        <span class="icono-confirmacion" aria-hidden="true">
            <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a3.5 3.5 0 0 1 6 0l16 28a3.5 3.5 0 0 1-3 5H8a3.5 3.5 0 0 1-3-5zM24 18v11m0 5v1"/></svg>
        </span>
        <h2 id="titulo-stock-confirmacion">Stock insuficiente</h2>
        <p id="descripcion-stock-confirmacion"><?= escapar_html($mensajeStock) ?> El pedido sigue como borrador.</p>
        <form method="dialog" class="acciones-confirmacion">
            <button type="submit" class="btn btn-primary" autofocus>Entendido</button>
        </form>
    </dialog>
    <noscript><div class="alert alert-warning" role="alert"><?= escapar_html($mensajeStock) ?> El pedido sigue como borrador.</div></noscript>
<?php endif; ?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h2 mb-2"><?= escapar_html($pedido['consecutivo']) ?></h1>
        <span class="badge text-bg-<?= $pedido['estado'] === 'anulado' ? 'secondary' : ($pedido['estado'] === 'borrador' ? 'warning' : 'success') ?>"><?= escapar_html(ucfirst($pedido['estado'])) ?></span>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($pedido['estado'] === 'borrador' && tiene_permiso($usuario, 'pedidos.crear')): ?>
            <a href="/pedidos/editar.php?id=<?= $id ?>" class="btn btn-outline-primary">Editar borrador</a>
        <?php endif; ?>
        <?php if (in_array($pedido['estado'], ['confirmado', 'despachado', 'entregado'], true)
            && $pagado < $total && tiene_permiso($usuario, 'pagos.registrar')): ?>
            <a href="/pagos/nuevo.php?id=<?= $id ?>" class="btn btn-outline-primary">Registrar pago</a>
        <?php endif; ?>
        <?php if ($pagado > 0 && ($pedido['estado'] === 'anulado' || $pagado >= $total)
            && tiene_permiso($usuario, 'pagos.registrar')): ?>
            <a href="/pagos/nuevo.php?id=<?= $id ?>#historial-pagos" class="btn btn-outline-primary">Ver historial de pagos</a>
        <?php endif; ?>
        <?php
        $acciones = [];
        if ($pedido['estado'] === 'borrador' && tiene_permiso($usuario, 'pedidos.crear')) $acciones['confirmar'] = 'Confirmar pedido';
        if ($pedido['estado'] === 'confirmado' && tiene_permiso($usuario, 'pedidos.despachar')) $acciones['despachar'] = 'Despachar';
        if ($pedido['estado'] === 'despachado' && tiene_permiso($usuario, 'pedidos.despachar')) $acciones['entregar'] = 'Marcar entregado';
        if (in_array($pedido['estado'], ['borrador', 'confirmado'], true)
            && tiene_permiso($usuario, 'pedidos.anular')) {
            $acciones['anular'] = 'Anular pedido';
        }
        $mensajeAnulacion = $pagado > 0
            ? '¿Confirmas la anulación? El dinero recibido quedará pendiente de devolución y el stock regresará al inventario.'
            : '¿Confirmas que deseas anular este pedido?';
        ?>
        <?php foreach ($acciones as $accion => $etiqueta): ?>
            <form action="/pedidos/accion.php" method="post" class="m-0<?= $accion === 'anular' ? ' formulario-anular-pedido' : '' ?>">
                <input type="hidden" name="token_csrf" value="<?= escapar_html(token_csrf()) ?>">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" name="accion" value="<?= escapar_html($accion) ?>" class="btn btn-<?= $accion === 'anular' ? 'outline-danger' : 'primary' ?>"><?= escapar_html($etiqueta) ?></button>
            </form>
        <?php endforeach; ?>
    </div>
</div>
<?php if (isset($acciones['anular'])): ?>
    <dialog id="dialogo-anular-pedido" class="dialogo-confirmacion es-desactivacion" aria-labelledby="titulo-anular-pedido" aria-describedby="descripcion-anular-pedido">
        <span class="icono-confirmacion" aria-hidden="true">
            <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><circle cx="24" cy="24" r="18"/><path d="M18 18l12 12m0-12L18 30"/></svg>
        </span>
        <h2 id="titulo-anular-pedido">¿Anular pedido?</h2>
        <p id="descripcion-anular-pedido"><?= escapar_html($mensajeAnulacion) ?></p>
        <div class="acciones-confirmacion">
            <button type="button" class="btn btn-outline-secondary" data-cancelar-anulacion>Volver</button>
            <button type="button" class="btn btn-danger" data-confirmar-anulacion>Sí, anular pedido</button>
        </div>
    </dialog>
<?php endif; ?>
<?php if ($pedido['estado'] === 'confirmado' && $pagado > 0): ?>
    <div class="alert alert-warning" role="status">
        Este pedido tiene pagos registrados. Si lo anulas, el stock se restituye y <?= escapar_html(mostrar_monto_pago($pagado)) ?> quedará pendiente de devolver al cliente.
    </div>
<?php endif; ?>
<?php if ($pedido['estado'] === 'anulado' && $pagado > 0): ?>
    <?php if ($devuelto === 0): ?>
        <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2" role="status">
            <span>Devolución pendiente: <strong><?= escapar_html(mostrar_monto_pago($pagado)) ?></strong>. Anular el pedido no devolvió el dinero automáticamente.</span>
            <?php if (tiene_permiso($usuario, 'pagos.devolver')): ?>
                <a href="/pagos/devolucion.php?id=<?= $id ?>" class="btn btn-outline-dark btn-sm">Registrar devolución realizada</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-success" role="status">
            Devolución registrada: <strong><?= escapar_html(mostrar_monto_pago($devuelto)) ?></strong>,
            <?= escapar_html(METODOS_PAGO[$devolucion['metodo']] ?? $devolucion['metodo']) ?>,
            <?= escapar_html(fecha_pedido_bogota($devolucion['fecha'])) ?> (Bogotá).
            <?php if (tiene_permiso($usuario, 'pagos.devolver')): ?>
                <a href="/pagos/devolucion.php?id=<?= $id ?>" class="alert-link">Ver constancia</a>.
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-7">
        <div class="card border-0 shadow-sm h-100"><div class="card-body p-4">
            <h2 class="h5">Cliente</h2>
            <p class="mb-1 fw-semibold"><?= escapar_html($pedido['cliente']) ?></p>
            <p class="text-secondary mb-0">NIT <?= escapar_html($pedido['nit']) ?></p>
        </div></div>
    </div>
    <div class="col-md-5">
        <div class="card border-0 shadow-sm h-100"><div class="card-body p-4">
            <h2 class="h5">Datos del pedido</h2>
            <p class="mb-1">Creado: <?= escapar_html(fecha_pedido_bogota($pedido['creado_en'])) ?> (Bogotá)</p>
            <p class="mb-1">Por: <?= escapar_html($pedido['creador']) ?></p>
            <p class="mb-0">Orden de compra: <?= $pedido['orden_compra'] === null ? 'No indicada' : escapar_html($pedido['orden_compra']) ?></p>
        </div></div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
                <th class="ps-4" scope="col">Producto</th><th scope="col">Cantidad</th>
                <th scope="col">Precio sin IVA</th><th scope="col">Descuento</th>
                <th scope="col">IVA</th><th scope="col" class="pe-4">Total línea</th>
            </tr></thead>
            <tbody>
                <?php foreach ($lineas as $linea): ?>
                    <tr>
                        <td class="ps-4"><?= escapar_html($linea['sku'] . ' · ' . $linea['nombre']) ?></td>
                        <td><?= (int) $linea['cantidad'] ?></td>
                        <td class="text-nowrap"><?= escapar_html(mostrar_decimal_pedido($linea['precio_unitario'])) ?></td>
                        <td><?= (int) $linea['descuento_porcentaje'] ?> %</td>
                        <td><?= (int) $linea['iva_porcentaje'] ?> %</td>
                        <td class="pe-4 text-nowrap"><?= escapar_html(mostrar_centavos_pedido($linea['total_linea'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white border-0 px-4 py-3 text-end">
        <button type="button" class="btn btn-outline-primary btn-sm" id="abrir-calculo" aria-haspopup="dialog" aria-controls="dialogo-calculo">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" class="me-1"><circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8h.01"/></svg>
            Ver cálculo por producto
        </button>
    </div>
</div>
<dialog id="dialogo-calculo" class="dialogo-calculo" aria-labelledby="titulo-calculo">
    <div class="encabezado-calculo">
        <h2 id="titulo-calculo" class="h5 mb-0">Cálculo del pedido</h2>
        <form method="dialog" class="m-0"><button type="submit" class="btn btn-outline-primary btn-sm">Cerrar</button></form>
    </div>
    <div class="contenido-calculo">
        <?php foreach ($lineas as $linea): ?>
            <section class="producto-calculo">
                <h3 class="h6 mb-3"><?= escapar_html($linea['sku'] . ' · ' . $linea['nombre']) ?></h3>
                <dl class="filas-calculo">
                    <div><dt>Precio unitario:</dt><dd><?= escapar_html(mostrar_decimal_pedido($linea['precio_unitario'])) ?></dd></div>
                    <div><dt>Cantidad:</dt><dd><?= (int) $linea['cantidad'] ?></dd></div>
                    <div><dt>Subtotal:</dt><dd><?= escapar_html(mostrar_centavos_pedido($linea['subtotal'])) ?></dd></div>
                    <div><dt>Descuento:</dt><dd><?= (int) $linea['descuento_porcentaje'] ?> %</dd></div>
                    <div><dt>Valor del descuento:</dt><dd><?= escapar_html(mostrar_centavos_pedido($linea['descuento'])) ?></dd></div>
                    <div><dt>Base después del descuento:</dt><dd><?= escapar_html(mostrar_centavos_pedido($linea['subtotal'] - $linea['descuento'])) ?></dd></div>
                    <div><dt>IVA:</dt><dd><?= (int) $linea['iva_porcentaje'] ?> %</dd></div>
                    <div><dt>Valor del IVA:</dt><dd><?= escapar_html(mostrar_centavos_pedido($linea['iva'])) ?></dd></div>
                    <div class="total-calculo"><dt>Total línea:</dt><dd><?= escapar_html(mostrar_centavos_pedido($linea['total_linea'])) ?></dd></div>
                </dl>
            </section>
        <?php endforeach; ?>
        <section class="producto-calculo">
            <h3 class="h6 mb-3">Total del pedido</h3>
            <dl class="filas-calculo">
                <div><dt>Subtotal:</dt><dd><?= escapar_html(mostrar_centavos_pedido($totales['subtotal'])) ?></dd></div>
                <div><dt>Descuento:</dt><dd><?= escapar_html(mostrar_centavos_pedido($totales['descuento'])) ?></dd></div>
                <div><dt>IVA:</dt><dd><?= escapar_html(mostrar_centavos_pedido($totales['iva'])) ?></dd></div>
                <div><dt>Antes del ajuste:</dt><dd><?= escapar_html(mostrar_centavos_pedido($totales['total'] - $totales['ajuste'])) ?></dd></div>
                <div><dt>Ajuste a múltiplo de $ 50:</dt><dd><?= escapar_html(mostrar_centavos_pedido($totales['ajuste'])) ?></dd></div>
                <div class="total-calculo"><dt>Total:</dt><dd><?= escapar_html(mostrar_centavos_pedido($totales['total'])) ?></dd></div>
            </dl>
        </section>
    </div>
</dialog>
<div class="row justify-content-end">
    <div class="col-md-6 col-xl-5">
        <div class="card border-0 shadow-sm"><div class="card-body p-4">
            <h2 class="h5 mb-3">Resumen</h2>
            <div class="d-flex justify-content-between"><span>Subtotal</span><strong><?= escapar_html(mostrar_decimal_pedido($pedido['subtotal'])) ?></strong></div>
            <div class="d-flex justify-content-between"><span>Descuento</span><strong>− <?= escapar_html(mostrar_decimal_pedido($pedido['descuento'])) ?></strong></div>
            <div class="d-flex justify-content-between"><span>IVA</span><strong><?= escapar_html(mostrar_decimal_pedido($pedido['iva'])) ?></strong></div>
            <div class="d-flex justify-content-between"><span>Ajuste a múltiplo de $ 50</span><strong><?= escapar_html(mostrar_centavos_pedido($totales['ajuste'])) ?></strong></div>
            <hr>
            <div class="d-flex justify-content-between fs-5"><span>Total</span><strong><?= escapar_html(mostrar_decimal_pedido($pedido['total'])) ?></strong></div>
            <div class="d-flex justify-content-between mt-3"><span>Pagos registrados</span><strong><?= escapar_html(mostrar_centavos_pedido($pagado)) ?></strong></div>
            <div class="d-flex justify-content-between"><span>Saldo por cobrar</span><strong><?= escapar_html(mostrar_centavos_pedido($pedido['estado'] === 'anulado' ? 0 : max(0, $total - $pagado))) ?></strong></div>
            <?php if ($pedido['estado'] === 'anulado' && $pagado > 0): ?>
                <div class="d-flex justify-content-between"><span>Pendiente de devolución</span><strong><?= escapar_html(mostrar_monto_pago(max(0, $pagado - $devuelto))) ?></strong></div>
            <?php endif; ?>
        </div></div>
    </div>
</div>
<script src="/assets/js/detalle-pedido.js?v=3" defer></script>
<?php mostrar_pie(true); ?>
