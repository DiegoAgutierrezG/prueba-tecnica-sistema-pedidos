<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/vista.php';
require_once __DIR__ . '/funciones.php';

$usuario = exigir_permiso('pedidos.ver');
$estados = ['borrador', 'confirmado', 'despachado', 'entregado', 'anulado'];
$estadoRecibido = $_GET['estado'] ?? '';
$estado = is_string($estadoRecibido) && in_array($estadoRecibido, $estados, true) ? $estadoRecibido : '';

try {
    $conexion = conectar_bd();
    $sql = 'SELECT p.id, p.consecutivo, p.estado, p.total, p.creado_en,
                   c.razon_social AS cliente
            FROM pedidos AS p INNER JOIN clientes AS c ON c.id = p.cliente_id';
    $parametros = [];
    if ($estado !== '') {
        $sql .= ' WHERE p.estado = :estado';
        $parametros['estado'] = $estado;
    }
    $sql .= ' ORDER BY p.creado_en DESC, p.id DESC';
    $consulta = $conexion->prepare($sql);
    $consulta->execute($parametros);
    $pedidos = $consulta->fetchAll();
} catch (Throwable $error) {
    error_log((string) $error);
    mostrar_error(503, 'Servicio no disponible', 'No se pudo consultar la lista de pedidos.', $usuario);
}

$aviso = $_SESSION['aviso_pedidos'] ?? null;
unset($_SESSION['aviso_pedidos']);
mostrar_cabecera('Pedidos', $usuario, false, 'pedidos');
?>
<nav aria-label="Ruta de navegación" class="mb-4">
    <a href="/index.php" class="text-decoration-none">Inicio</a> <span class="text-secondary">/ Pedidos</span>
</nav>
<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h2 mb-2">Pedidos</h1>
        <p class="text-secondary mb-0">Consulta los pedidos y su estado actual.</p>
    </div>
    <?php if (tiene_permiso($usuario, 'pedidos.crear')): ?>
        <a href="/pedidos/nuevo.php" class="btn btn-primary">Nuevo pedido</a>
    <?php endif; ?>
</div>
<?php if (is_string($aviso) && $aviso !== ''): ?>
    <div class="alert alert-success" role="status"><?= escapar_html($aviso) ?></div>
<?php endif; ?>
<form action="/pedidos/index.php" method="get" class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap align-items-end gap-3">
        <div>
            <label for="estado" class="form-label">Filtrar por estado</label>
            <select id="estado" name="estado" class="form-select">
                <option value="">Todos</option>
                <?php foreach ($estados as $opcion): ?>
                    <option value="<?= escapar_html($opcion) ?>" <?= $estado === $opcion ? 'selected' : '' ?>><?= escapar_html(ucfirst($opcion)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-outline-primary">Filtrar</button>
    </div>
</form>
<p class="text-secondary small mb-2"><?= count($pedidos) ?> pedido<?= count($pedidos) === 1 ? '' : 's' ?>.</p>
<?php if ($pedidos === []): ?>
    <div class="alert alert-info" role="status">No hay pedidos para este estado.</div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col" class="ps-4">Consecutivo</th>
                        <th scope="col">Fecha en Bogotá</th>
                        <th scope="col">Cliente</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Total</th>
                        <th scope="col" class="pe-4">Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos as $pedido): ?>
                        <tr>
                            <td class="ps-4 fw-semibold"><?= escapar_html($pedido['consecutivo']) ?></td>
                            <td class="text-nowrap"><?= escapar_html(fecha_pedido_bogota($pedido['creado_en'])) ?></td>
                            <td><?= escapar_html($pedido['cliente']) ?></td>
                            <td><span class="badge text-bg-<?= $pedido['estado'] === 'anulado' ? 'secondary' : ($pedido['estado'] === 'borrador' ? 'warning' : 'success') ?>"><?= escapar_html(ucfirst($pedido['estado'])) ?></span></td>
                            <td class="text-nowrap"><?= escapar_html(mostrar_decimal_pedido($pedido['total'])) ?></td>
                            <td class="pe-4"><a href="/pedidos/detalle.php?id=<?= (int) $pedido['id'] ?>" class="btn btn-outline-primary btn-sm">Ver pedido</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php mostrar_pie(true); ?>
