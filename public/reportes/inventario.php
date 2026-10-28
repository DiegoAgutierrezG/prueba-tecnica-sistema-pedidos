<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/vista.php';
require_once __DIR__ . '/funciones.php';

$usuario = exigir_permiso('reportes.ver');

try {
    $productos = consultar_productos_bajo_minimo(conectar_bd());
} catch (Throwable $error) {
    error_log((string) $error);
    mostrar_error(503, 'Servicio no disponible', 'No se pudo consultar el inventario.', $usuario);
}

mostrar_cabecera('Productos bajo mínimo', $usuario, false, 'reportes');
?>
<nav aria-label="Ruta de navegación" class="mb-4">
    <a href="/index.php" class="text-decoration-none">Inicio</a> <span class="text-secondary">/ <a href="/reportes/index.php">Reportes</a> / Inventario bajo mínimo</span>
</nav>
<h1 class="h2 mb-2">Productos bajo el stock mínimo</h1>
<p class="text-secondary mb-4">Solo se incluyen productos activos. El faltante indica cuántas unidades hacen falta para alcanzar el mínimo.</p>
<p class="text-secondary small"><?= count($productos) ?> producto<?= count($productos) === 1 ? '' : 's' ?> por reponer.</p>
<?php if ($productos === []): ?>
    <div class="alert alert-success" role="status">Todos los productos activos están en su mínimo o por encima.</div>
<?php else: ?>
    <div class="card border-0 shadow-sm"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
                <th scope="col" class="ps-4">SKU</th>
                <th scope="col">Producto</th>
                <th scope="col">Categoría</th>
                <th scope="col">Stock</th>
                <th scope="col">Mínimo</th>
                <th scope="col" class="pe-4">Faltante</th>
            </tr></thead>
            <tbody>
                <?php foreach ($productos as $producto): ?>
                    <tr class="table-warning">
                        <td class="ps-4 fw-semibold"><?= escapar_html($producto['sku']) ?></td>
                        <td><?= escapar_html($producto['nombre']) ?></td>
                        <td><?= escapar_html($producto['categoria']) ?></td>
                        <td><?= (int) $producto['stock'] ?></td>
                        <td><?= (int) $producto['stock_minimo'] ?></td>
                        <td class="pe-4 fw-semibold"><?= (int) $producto['faltante'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
<?php endif; ?>
<?php mostrar_pie(true); ?>
