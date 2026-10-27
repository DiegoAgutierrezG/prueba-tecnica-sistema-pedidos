<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/vista.php';
require_once __DIR__ . '/funciones.php';

$usuario = exigir_permiso('pagos.registrar');

try {
    $consulta = conectar_bd()->query(
        "SELECT p.id, p.consecutivo, p.estado, p.total, p.creado_en,
                c.razon_social AS cliente, COALESCE(pg.total_pagado, 0.00) AS pagado
         FROM pedidos AS p
         INNER JOIN clientes AS c ON c.id = p.cliente_id
         LEFT JOIN (
             SELECT pedido_id, SUM(valor) AS total_pagado FROM pagos GROUP BY pedido_id
         ) AS pg ON pg.pedido_id = p.id
         WHERE p.estado IN ('confirmado', 'despachado', 'entregado')
           AND p.total > COALESCE(pg.total_pagado, 0.00)
         ORDER BY p.creado_en DESC, p.id DESC"
    );
    $pedidos = $consulta->fetchAll();
} catch (Throwable $error) {
    error_log((string) $error);
    mostrar_error(503, 'Servicio no disponible', 'No se pudieron consultar los pedidos con saldo.', $usuario);
}

mostrar_cabecera('Pagos', $usuario, false, 'pagos');
?>
<nav aria-label="Ruta de navegación" class="mb-4">
    <a href="/index.php" class="text-decoration-none">Inicio</a> <span class="text-secondary">/ Pagos</span>
</nav>
<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h2 mb-2">Pagos</h1>
        <p class="text-secondary mb-0">Registra abonos a pedidos con saldo pendiente.</p>
    </div>
    <a href="/pedidos/index.php" class="btn btn-outline-primary">Ver todos los pedidos</a>
</div>
<p class="text-secondary small mb-2"><?= count($pedidos) ?> pedido<?= count($pedidos) === 1 ? '' : 's' ?> con saldo pendiente.</p>
<?php if ($pedidos === []): ?>
    <div class="alert alert-info" role="status">No hay pedidos confirmados, despachados o entregados con saldo pendiente.</div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr>
                    <th scope="col" class="ps-4">Pedido</th>
                    <th scope="col">Fecha en Bogotá</th>
                    <th scope="col">Cliente</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Total</th>
                    <th scope="col">Pagado</th>
                    <th scope="col">Saldo</th>
                    <th scope="col" class="pe-4">Acción</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($pedidos as $pedido): ?>
                        <?php $saldo = centavos_de_decimal($pedido['total']) - centavos_de_decimal($pedido['pagado']); ?>
                        <tr>
                            <td class="ps-4 fw-semibold"><?= escapar_html($pedido['consecutivo']) ?></td>
                            <td class="text-nowrap"><?= escapar_html(fecha_pedido_bogota($pedido['creado_en'])) ?></td>
                            <td><?= escapar_html($pedido['cliente']) ?></td>
                            <td><?= escapar_html(ucfirst($pedido['estado'])) ?></td>
                            <td class="text-nowrap"><?= escapar_html(mostrar_monto_pago(centavos_de_decimal($pedido['total']))) ?></td>
                            <td class="text-nowrap"><?= escapar_html(mostrar_monto_pago(centavos_de_decimal($pedido['pagado']))) ?></td>
                            <td class="text-nowrap fw-semibold"><?= escapar_html(mostrar_monto_pago($saldo)) ?></td>
                            <td class="pe-4"><a href="/pagos/nuevo.php?id=<?= (int) $pedido['id'] ?>" class="btn btn-outline-primary btn-sm">Registrar pago</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php mostrar_pie(true); ?>
