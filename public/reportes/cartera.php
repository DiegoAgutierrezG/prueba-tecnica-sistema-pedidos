<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/vista.php';
require_once __DIR__ . '/funciones.php';

$usuario = exigir_permiso('reportes.ver');
$busquedaRecibida = $_GET['busqueda'] ?? '';
$busqueda = is_string($busquedaRecibida) ? trim($busquedaRecibida) : '';
$mensajeBusqueda = '';
$clientes = [];

if (!mb_check_encoding($busqueda, 'UTF-8') || mb_strlen($busqueda, 'UTF-8') > 150) {
    $mensajeBusqueda = 'La búsqueda admite hasta 150 caracteres. Acórtala y vuelve a buscar.';
} else {
    try {
        $clientes = consultar_cartera(conectar_bd(), $busqueda);
    } catch (Throwable $error) {
        error_log((string) $error);
        mostrar_error(503, 'Servicio no disponible', 'No se pudo consultar la cartera. Vuelve al inicio e inténtalo de nuevo en unos minutos.', $usuario);
    }
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
<form action="/reportes/cartera.php" method="get" class="card border-0 shadow-sm mb-4">
    <div class="card-body row g-3 align-items-end">
        <div class="col-md-8">
            <label for="busqueda-cartera" class="form-label">Buscar por NIT o razón social</label>
            <input id="busqueda-cartera" name="busqueda" type="search" class="form-control" value="<?= escapar_html($busqueda) ?>" maxlength="150" placeholder="Escribe una parte del NIT o nombre">
        </div>
        <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-primary">Buscar</button>
        </div>
        <div class="col-md-2 d-grid">
            <a href="/reportes/cartera.php" class="btn btn-outline-secondary">Limpiar</a>
        </div>
    </div>
</form>
<?php if ($mensajeBusqueda !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= escapar_html($mensajeBusqueda) ?></div>
<?php else: ?>
    <p class="text-secondary small mb-2"><?= count($clientes) ?> cliente<?= count($clientes) === 1 ? '' : 's' ?> encontrado<?= count($clientes) === 1 ? '' : 's' ?>.</p>
    <?php if ($clientes === []): ?>
        <div class="alert alert-info" role="status"><?= $busqueda === '' ? 'No hay clientes registrados.' : 'No hay clientes que coincidan con esta búsqueda.' ?></div>
    <?php else: ?>
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
    <?php endif; ?>
<?php endif; ?>
<?php mostrar_pie(true); ?>
