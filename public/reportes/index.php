<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/vista.php';
require_once __DIR__ . '/../pedidos/calculos.php';
require_once __DIR__ . '/funciones.php';

$usuario = exigir_permiso('reportes.ver');
$mesActual = (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))->format('Y-m');
$mesRecibido = $_GET['mes'] ?? $mesActual;
if (!is_string($mesRecibido) || limites_mes_ventas($mesRecibido) === null) {
    mostrar_error(400, 'Mes no válido', 'Selecciona un mes entre 1000-01 y 9998-12.', $usuario);
}

try {
    $reporte = consultar_ventas_mes(conectar_bd(), $mesRecibido);
} catch (Throwable $error) {
    error_log((string) $error);
    mostrar_error(503, 'Servicio no disponible', 'No se pudo consultar el reporte de ventas.', $usuario);
}

mostrar_cabecera('Ventas del mes', $usuario, false, 'reportes');
?>
<nav aria-label="Ruta de navegación" class="mb-4">
    <a href="/index.php" class="text-decoration-none">Inicio</a> <span class="text-secondary">/ Ventas del mes</span>
</nav>
<div class="mb-4">
    <h1 class="h2 mb-2">Ventas del mes</h1>
    <p class="text-secondary mb-0">Consulta pedidos confirmados, despachados y entregados según su fecha de creación en Bogotá.</p>
</div>
<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="/reportes/cartera.php" class="btn btn-outline-primary">Cartera por cliente</a>
    <a href="/reportes/inventario.php" class="btn btn-outline-primary">Productos bajo mínimo</a>
</div>
<form action="/reportes/index.php" method="get" class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap align-items-end gap-3">
        <div>
            <label for="mes" class="form-label">Mes de las ventas</label>
            <input type="month" id="mes" name="mes" class="form-control" min="1000-01" max="9998-12" value="<?= escapar_html($mesRecibido) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Consultar</button>
    </div>
</form>
<h2 class="h4 mb-3"><?= escapar_html(titulo_mes_ventas($mesRecibido)) ?></h2>
<div class="row g-3 mb-4">
    <div class="col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4">
        <p class="text-secondary mb-2">Pedidos vendidos</p>
        <strong class="fs-2"><?= (int) $reporte['cantidad'] ?></strong>
    </div></div></div>
    <div class="col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4">
        <p class="text-secondary mb-2">Total vendido (COP)</p>
        <strong class="fs-2"><?= escapar_html(mostrar_importe_ventas($reporte['total'])) ?></strong>
    </div></div></div>
</div>
<p class="text-secondary small mb-3">Incluye el mes completo, desde el día 1 a las 00:00 hasta antes del día 1 del mes siguiente, en hora de Bogotá. Se toma la fecha de creación del pedido y su estado actual; los pagos no se suman de nuevo.</p>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4 pb-0"><h3 class="h5 mb-3">Ventas por vendedor</h3></div>
    <?php if ($reporte['vendedores'] === []): ?>
        <p class="px-4 pb-4 text-secondary mb-0">No hay ventas para distribuir en este mes.</p>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
                <th scope="col" class="ps-4">Vendedor</th>
                <th scope="col">Pedidos</th>
                <th scope="col">Total vendido</th>
                <th scope="col" class="pe-4">Participación</th>
            </tr></thead>
            <tbody>
                <?php $totalCentavos = centavos_de_decimal($reporte['total']); ?>
                <?php foreach ($reporte['vendedores'] as $vendedor): ?>
                    <tr>
                        <td class="ps-4"><?= escapar_html($vendedor['nombre']) ?></td>
                        <td><?= (int) $vendedor['cantidad'] ?></td>
                        <td class="text-nowrap"><?= escapar_html(mostrar_importe_ventas(decimal_de_centavos($vendedor['centavos']))) ?></td>
                        <td class="pe-4"><?= $totalCentavos > 0 ? escapar_html(number_format(100 * $vendedor['centavos'] / $totalCentavos, 1, ',', '.')) : '0,0' ?> %</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4 pb-0"><h3 class="h5 mb-3">Pedidos incluidos</h3></div>
    <?php if ($reporte['pedidos'] === []): ?>
        <p class="px-4 pb-4 text-secondary mb-0">No hay ventas registradas para este mes.</p>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
                <th scope="col" class="ps-4">Pedido</th>
                <th scope="col">Creado en Bogotá</th>
                <th scope="col">Cliente</th>
                <th scope="col">Estado</th>
                <th scope="col" class="pe-4">Total</th>
            </tr></thead>
            <tbody>
                <?php foreach ($reporte['pedidos'] as $pedido): ?>
                    <tr>
                        <td class="ps-4"><a href="/pedidos/detalle.php?id=<?= (int) $pedido['id'] ?>" class="fw-semibold text-decoration-none"><?= escapar_html($pedido['consecutivo']) ?></a></td>
                        <td class="text-nowrap"><?= escapar_html(fecha_pedido_bogota($pedido['creado_en'])) ?></td>
                        <td><?= escapar_html($pedido['cliente']) ?></td>
                        <td><?= escapar_html(ucfirst($pedido['estado'])) ?></td>
                        <td class="pe-4 text-nowrap fw-semibold"><?= escapar_html(mostrar_importe_ventas($pedido['total'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
<?php mostrar_pie(true); ?>
