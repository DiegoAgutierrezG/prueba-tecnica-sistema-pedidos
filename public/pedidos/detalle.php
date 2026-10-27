<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/vista.php';
require_once __DIR__ . '/funciones.php';

$usuario = exigir_permiso('pedidos.ver');
$id = id_pedido_valido($_GET['id'] ?? null);
if ($id === null) mostrar_error(404, 'Pedido no encontrado', 'El pedido solicitado no existe.', $usuario);

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
    if ($pedido === false) mostrar_error(404, 'Pedido no encontrado', 'El pedido solicitado no existe.', $usuario);
    $lineas = detalle_lineas_pedido($conexion, $id);
    $totales = calcular_totales_pedido($lineas);
    $consulta = $conexion->prepare('SELECT COALESCE(SUM(valor), 0.00) FROM pagos WHERE pedido_id = :id');
    $consulta->execute(['id' => $id]);
    $pagado = centavos_de_decimal((string) $consulta->fetchColumn());
    $total = centavos_de_decimal($pedido['total']);
} catch (Throwable $error) {
    error_log((string) $error);
    mostrar_error(503, 'Servicio no disponible', 'No se pudo consultar el detalle del pedido.', $usuario);
}

$aviso = $_SESSION['aviso_pedidos'] ?? null;
unset($_SESSION['aviso_pedidos']);
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
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h2 mb-2"><?= escapar_html($pedido['consecutivo']) ?></h1>
        <span class="badge text-bg-<?= $pedido['estado'] === 'anulado' ? 'secondary' : ($pedido['estado'] === 'borrador' ? 'warning' : 'success') ?>"><?= escapar_html(ucfirst($pedido['estado'])) ?></span>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (in_array($pedido['estado'], ['confirmado', 'despachado', 'entregado'], true)
            && $pagado < $total && tiene_permiso($usuario, 'pagos.registrar')): ?>
            <a href="/pagos/nuevo.php?id=<?= $id ?>" class="btn btn-outline-primary">Registrar pago</a>
        <?php endif; ?>
        <?php
        $acciones = [];
        if ($pedido['estado'] === 'borrador' && tiene_permiso($usuario, 'pedidos.crear')) $acciones['confirmar'] = 'Confirmar pedido';
        if ($pedido['estado'] === 'confirmado' && tiene_permiso($usuario, 'pedidos.despachar')) $acciones['despachar'] = 'Despachar';
        if ($pedido['estado'] === 'despachado' && tiene_permiso($usuario, 'pedidos.despachar')) $acciones['entregar'] = 'Marcar entregado';
        if (in_array($pedido['estado'], ['borrador', 'confirmado'], true) && tiene_permiso($usuario, 'pedidos.anular')) $acciones['anular'] = 'Anular pedido';
        ?>
        <?php foreach ($acciones as $accion => $etiqueta): ?>
            <form action="/pedidos/accion.php" method="post" class="m-0">
                <input type="hidden" name="token_csrf" value="<?= escapar_html(token_csrf()) ?>">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" name="accion" value="<?= escapar_html($accion) ?>" class="btn btn-<?= $accion === 'anular' ? 'outline-danger' : 'primary' ?>" <?= $accion === 'anular' ? 'onclick="return confirm(\'¿Confirmas que deseas anular este pedido?\')"' : '' ?>><?= escapar_html($etiqueta) ?></button>
            </form>
        <?php endforeach; ?>
    </div>
</div>

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
</div>
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
            <div class="d-flex justify-content-between"><span>Saldo</span><strong><?= escapar_html(mostrar_centavos_pedido(max(0, $total - $pagado))) ?></strong></div>
        </div></div>
    </div>
</div>
<?php mostrar_pie(true); ?>
