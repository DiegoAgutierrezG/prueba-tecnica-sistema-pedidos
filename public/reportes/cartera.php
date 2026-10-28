<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/vista.php';
require_once __DIR__ . '/funciones.php';

$usuario = exigir_permiso('reportes.ver');

try {
    $clientes = consultar_cartera(conectar_bd());
} catch (Throwable $error) {
    error_log((string) $error);
    mostrar_error(503, 'Servicio no disponible', 'No se pudo consultar la cartera.', $usuario);
}

mostrar_cabecera('Cartera por cliente', $usuario, false, 'reportes');
?>
<nav aria-label="Ruta de navegación" class="mb-4">
    <a href="/index.php" class="text-decoration-none">Inicio</a> <span class="text-secondary">/ <a href="/reportes/index.php">Reportes</a> / Cartera</span>
</nav>
<div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h2 mb-2">Cartera por cliente</h1>
        <p class="text-secondary mb-0">El saldo se calcula con pedidos vigentes menos los pagos registrados.</p>
    </div>
    <a href="/reportes/index.php" class="btn btn-outline-primary align-self-start">Ventas del mes</a>
</div>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
                <th scope="col" class="ps-4">Cliente</th>
                <th scope="col">Cupo</th>
                <th scope="col">Facturado</th>
                <th scope="col">Pagado</th>
                <th scope="col">Saldo pendiente</th>
                <th scope="col" class="pe-4">Cupo disponible</th>
            </tr></thead>
            <tbody>
                <?php foreach ($clientes as $cliente): ?>
                    <tr class="<?= $cliente['cerca_limite'] ? 'table-warning' : '' ?>">
                        <td class="ps-4"><strong><?= escapar_html($cliente['razon_social']) ?></strong><br><small class="text-secondary"><?= escapar_html($cliente['nit']) ?><?= (int) $cliente['activo'] === 0 ? ' · Inactivo' : '' ?></small><?php if ($cliente['cerca_limite']): ?><br><span class="badge text-bg-warning">Cupo usado al 90 % o más</span><?php endif; ?></td>
                        <td class="text-nowrap"><?= escapar_html(mostrar_importe_ventas(decimal_de_centavos($cliente['cupo_centavos']))) ?></td>
                        <td class="text-nowrap"><?= escapar_html(mostrar_importe_ventas(decimal_de_centavos($cliente['facturado_centavos']))) ?></td>
                        <td class="text-nowrap"><?= escapar_html(mostrar_importe_ventas(decimal_de_centavos($cliente['pagado_centavos']))) ?></td>
                        <td class="text-nowrap fw-semibold"><?= escapar_html(mostrar_importe_ventas(decimal_de_centavos($cliente['saldo_centavos']))) ?></td>
                        <td class="text-nowrap pe-4"><?= escapar_html(mostrar_importe_ventas(decimal_de_centavos($cliente['disponible_centavos']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php mostrar_pie(true); ?>
