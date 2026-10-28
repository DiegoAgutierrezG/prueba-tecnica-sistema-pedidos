<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/vista.php';
require_once __DIR__ . '/funciones.php';

$usuario = exigir_permiso('pagos.registrar');
$puedeDevolver = tiene_permiso($usuario, 'pagos.devolver');
$vista = $_GET['vista'] ?? 'pagos';
if (!is_string($vista) || !in_array($vista, ['pagos', 'devoluciones'], true)) {
    mostrar_error(400, 'Vista inválida', 'Selecciona Pagos o Devoluciones.', $usuario);
}
if ($vista === 'devoluciones' && !$puedeDevolver) {
    mostrar_error(403, 'Acceso denegado', 'Tu usuario no tiene permiso para consultar devoluciones.', $usuario);
}

$pedidos = [];
$devoluciones = [];
$pendientes = 0;
$enAtencion = 0;
$urgentes = 0;
$sinFecha = 0;
$ahoraUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));

try {
    $conexion = conectar_bd();
    if ($vista === 'pagos') {
        $consulta = $conexion->prepare(
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
        $consulta->execute();
        $pedidos = $consulta->fetchAll();
    }
    if ($puedeDevolver) {
        $consulta = $conexion->prepare(
            "SELECT p.id, p.consecutivo, p.anulado_en, c.razon_social AS cliente,
                    pg.total_pagado AS pagado, d.id AS devolucion_id, d.fecha AS devuelto_en
             FROM pedidos AS p
             INNER JOIN clientes AS c ON c.id = p.cliente_id
             INNER JOIN (SELECT pedido_id, SUM(valor) AS total_pagado FROM pagos GROUP BY pedido_id) AS pg
                 ON pg.pedido_id = p.id
             LEFT JOIN devoluciones AS d ON d.pedido_id = p.id
             WHERE p.estado = 'anulado' AND pg.total_pagado > 0
             ORDER BY (d.id IS NOT NULL), p.anulado_en ASC, d.fecha DESC, p.id DESC"
        );
        $consulta->execute();
        $devoluciones = $consulta->fetchAll();
        foreach ($devoluciones as &$devolucion) {
            if ($devolucion['devolucion_id'] !== null) continue;
            $devolucion['espera'] = estado_espera_devolucion($devolucion['anulado_en'], $ahoraUtc);
            $pendientes++;
            if ($devolucion['espera']['nivel'] === 'urgente') $urgentes++;
            if ($devolucion['espera']['nivel'] === 'atencion') $enAtencion++;
            if ($devolucion['espera']['nivel'] === 'sin_fecha') $sinFecha++;
        }
        unset($devolucion);
    }
} catch (Throwable $error) {
    error_log((string) $error);
    mostrar_error(503, 'Servicio no disponible', 'No se pudo consultar el módulo de pagos.', $usuario);
}

$registradas = count($devoluciones) - $pendientes;
$avisos = [];
if ($urgentes > 0) $avisos[] = $urgentes . ' con 48 horas o más';
if ($enAtencion > 0) $avisos[] = $enAtencion . ' con 24 a 47 horas';
if ($sinFecha > 0) $avisos[] = $sinFecha . ' sin fecha de anulación';
$avisoDevoluciones = $_SESSION['aviso_devoluciones'] ?? null;
unset($_SESSION['aviso_devoluciones']);
mostrar_cabecera($vista === 'devoluciones' ? 'Devoluciones' : 'Pagos', $usuario, false, 'pagos');
?>
<nav aria-label="Ruta de navegación" class="mb-4">
    <a href="/index.php" class="text-decoration-none">Inicio</a> <span class="text-secondary">/ Pagos<?= $vista === 'devoluciones' ? ' / Devoluciones' : '' ?></span>
</nav>
<?php if ($vista === 'devoluciones' && is_string($avisoDevoluciones) && $avisoDevoluciones !== ''): ?>
    <div class="alert alert-success" role="status"><?= escapar_html($avisoDevoluciones) ?></div>
<?php endif; ?>
<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h2 mb-2"><?= $vista === 'devoluciones' ? 'Devoluciones' : 'Pagos' ?></h1>
        <p class="text-secondary mb-0"><?= $vista === 'devoluciones'
            ? 'Control de dinero pendiente y constancias de devoluciones realizadas.'
            : 'Registra abonos a pedidos con saldo pendiente.' ?></p>
    </div>
    <a href="/pedidos/index.php" class="btn btn-outline-primary">Ver todos los pedidos</a>
</div>
<nav class="d-flex flex-wrap gap-2 mb-4" aria-label="Vistas del módulo de pagos">
    <a href="/pagos/index.php" class="btn btn-<?= $vista === 'pagos' ? 'primary' : 'outline-primary' ?>"<?= $vista === 'pagos' ? ' aria-current="page"' : '' ?>>Pagos</a>
    <?php if ($puedeDevolver): ?>
        <a href="/pagos/index.php?vista=devoluciones" class="btn btn-<?= $vista === 'devoluciones' ? 'primary' : 'outline-primary' ?>"<?= $vista === 'devoluciones' ? ' aria-current="page"' : '' ?>>
            Devoluciones<?php if ($pendientes > 0): ?> <span class="badge text-bg-<?= $urgentes > 0 ? 'danger' : ($enAtencion > 0 ? 'warning' : ($sinFecha > 0 ? 'secondary' : 'light')) ?> ms-1"><?= $pendientes ?> pendiente<?= $pendientes === 1 ? '' : 's' ?></span><?php endif; ?>
        </a>
    <?php endif; ?>
</nav>
<?php if ($puedeDevolver && $avisos !== []): ?>
    <div class="alert alert-<?= $urgentes > 0 ? 'danger' : 'warning' ?>" role="alert">
        Devoluciones por revisar: <?= escapar_html(implode('; ', $avisos)) ?> desde la anulación.
        <a href="/pagos/index.php?vista=devoluciones" class="alert-link">Ver devoluciones</a>.
    </div>
<?php endif; ?>
<?php if ($vista === 'pagos'): ?>
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
<?php else: ?>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <p class="text-secondary mb-0"><?= $pendientes ?> pendiente<?= $pendientes === 1 ? '' : 's' ?> · <?= $registradas ?> registrada<?= $registradas === 1 ? '' : 's' ?>.</p>
        <span class="text-secondary small">Alertas internas: atención desde 24 h; urgente desde 48 h.</span>
    </div>
    <?php if ($devoluciones === []): ?>
        <div class="alert alert-info" role="status">No hay pedidos anulados con pagos para devolver.</div>
    <?php else: ?>
        <div class="card border-0 shadow-sm"><div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr>
                    <th scope="col" class="ps-4">Pedido</th>
                    <th scope="col">Cliente</th>
                    <th scope="col">Anulado en Bogotá</th>
                    <th scope="col">Importe</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Tiempo pendiente</th>
                    <th scope="col" class="pe-4">Acción</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($devoluciones as $devolucion): ?>
                        <?php
                        $registrada = $devolucion['devolucion_id'] !== null;
                        $nivel = $registrada ? 'registrada' : $devolucion['espera']['nivel'];
                        $color = match ($nivel) {
                            'urgente' => 'danger', 'atencion' => 'warning',
                            'sin_fecha' => 'secondary', 'registrada' => 'success', default => 'info',
                        };
                        $estadoTexto = match ($nivel) {
                            'urgente' => 'Urgente', 'atencion' => 'Atención',
                            'sin_fecha' => 'Revisar fecha', 'registrada' => 'Registrada', default => 'Pendiente',
                        };
                        $horas = $registrada ? null : $devolucion['espera']['horas'];
                        ?>
                        <tr class="<?= $nivel === 'urgente' ? 'table-danger' : ($nivel === 'atencion' ? 'table-warning' : '') ?>">
                            <td class="ps-4 fw-semibold"><?= escapar_html($devolucion['consecutivo']) ?></td>
                            <td><?= escapar_html($devolucion['cliente']) ?></td>
                            <td class="text-nowrap"><?= $devolucion['anulado_en'] === null ? 'Sin fecha' : escapar_html(fecha_pedido_bogota($devolucion['anulado_en'])) ?></td>
                            <td class="text-nowrap"><?= escapar_html(mostrar_monto_pago(centavos_de_decimal($devolucion['pagado']))) ?></td>
                            <td><span class="badge text-bg-<?= $color ?>"><?= $estadoTexto ?></span></td>
                            <td class="text-nowrap"><?php if ($registrada): ?>
                                Resuelta el <?= escapar_html(fecha_pedido_bogota($devolucion['devuelto_en'])) ?>
                            <?php elseif ($horas === null): ?>
                                Sin fecha de anulación
                            <?php else: ?>
                                <?= $horas < 1 ? 'Menos de 1 h' : $horas . ' h' ?>
                            <?php endif; ?></td>
                            <td class="pe-4"><a href="/pagos/devolucion.php?id=<?= (int) $devolucion['id'] ?>" class="btn btn-outline-primary btn-sm"><?= $registrada ? 'Ver constancia' : 'Registrar devolución' ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div></div>
        <p class="text-secondary small mt-3">El registro de devolución se hace después de entregar el dinero fuera del sistema. Las alertas se actualizan al abrir o recargar esta página.</p>
    <?php endif; ?>
<?php endif; ?>
<?php mostrar_pie(true); ?>
