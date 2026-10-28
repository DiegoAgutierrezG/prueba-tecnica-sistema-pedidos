<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/vista.php';
require_once __DIR__ . '/funciones.php';

$usuario = exigir_permiso('pedidos.ver');
$estados = ['borrador', 'confirmado', 'despachado', 'entregado', 'anulado'];
$estadoRecibido = $_GET['estado'] ?? '';
$estado = is_string($estadoRecibido) && in_array($estadoRecibido, $estados, true) ? $estadoRecibido : '';
$busquedaRecibida = $_GET['busqueda'] ?? '';
$busqueda = is_string($busquedaRecibida) ? trim($busquedaRecibida) : '';
if (!mb_check_encoding($busqueda, 'UTF-8') || mb_strlen($busqueda, 'UTF-8') > 40) {
    mostrar_error(400, 'Búsqueda no válida', 'La búsqueda admite hasta 40 caracteres. Acórtala y vuelve a buscar desde Pedidos.', $usuario);
}

try {
    $conexion = conectar_bd();
    $sql = 'SELECT p.id, p.consecutivo, p.orden_compra, p.estado, p.total, p.creado_en,
                   c.razon_social AS cliente
            FROM pedidos AS p INNER JOIN clientes AS c ON c.id = p.cliente_id';
    $parametros = [];
    $condiciones = [];
    if ($estado !== '') {
        $condiciones[] = 'p.estado = :estado';
        $parametros['estado'] = $estado;
    }
    if ($busqueda !== '') {
        $condiciones[] = "(p.consecutivo LIKE :consecutivo ESCAPE '!' OR p.orden_compra LIKE :orden_compra ESCAPE '!')";
        $patron = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $busqueda) . '%';
        $parametros['consecutivo'] = $patron;
        $parametros['orden_compra'] = $patron;
    }
    if ($condiciones !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $condiciones);
    }
    $sql .= ' ORDER BY p.creado_en DESC, p.id DESC';
    $consulta = $conexion->prepare($sql);
    $consulta->execute($parametros);
    $pedidos = $consulta->fetchAll();
} catch (Throwable $error) {
    error_log((string) $error);
    mostrar_error(503, 'Servicio no disponible', 'No se pudo consultar la lista de pedidos. Vuelve al inicio e inténtalo de nuevo en unos minutos.', $usuario);
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
        <div class="flex-grow-1">
            <label for="busqueda" class="form-label">Consecutivo u orden de compra</label>
            <input id="busqueda" name="busqueda" type="search" class="form-control" value="<?= escapar_html($busqueda) ?>" maxlength="40" placeholder="Escribe una referencia">
        </div>
        <div>
            <label for="estado" class="form-label">Filtrar por estado</label>
            <select id="estado" name="estado" class="form-select">
                <option value="">Todos</option>
                <?php foreach ($estados as $opcion): ?>
                    <option value="<?= escapar_html($opcion) ?>" <?= $estado === $opcion ? 'selected' : '' ?>><?= escapar_html(ucfirst($opcion)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-outline-primary">Buscar</button>
    </div>
</form>
<p class="text-secondary small mb-2"><?= count($pedidos) ?> pedido<?= count($pedidos) === 1 ? '' : 's' ?>.</p>
<?php if ($pedidos === []): ?>
    <div class="alert alert-info" role="status">No hay pedidos que coincidan con los filtros.</div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col" class="ps-4">Consecutivo</th>
                        <th scope="col">Orden de compra</th>
                        <th scope="col">Fecha en Bogotá</th>
                        <th scope="col">Cliente</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Total</th>
                        <th scope="col" class="pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos as $pedido): ?>
                        <tr>
                            <td class="ps-4 fw-semibold"><?= escapar_html($pedido['consecutivo']) ?></td>
                            <td><?= escapar_html($pedido['orden_compra'] ?? '—') ?></td>
                            <td class="text-nowrap"><?= escapar_html(fecha_pedido_bogota($pedido['creado_en'])) ?></td>
                            <td><?= escapar_html($pedido['cliente']) ?></td>
                            <td><span class="badge text-bg-<?= $pedido['estado'] === 'anulado' ? 'secondary' : ($pedido['estado'] === 'borrador' ? 'warning' : 'success') ?>"><?= escapar_html(ucfirst($pedido['estado'])) ?></span></td>
                            <td class="text-nowrap"><?= escapar_html(mostrar_decimal_pedido($pedido['total'])) ?></td>
                            <td class="pe-4">
                                <div class="d-flex gap-2">
                                    <a href="/pedidos/detalle.php?id=<?= (int) $pedido['id'] ?>" class="btn btn-outline-primary btn-sm">Ver pedido</a>
                                    <?php if ($pedido['estado'] === 'borrador' && tiene_permiso($usuario, 'pedidos.crear')): ?>
                                        <a href="/pedidos/editar.php?id=<?= (int) $pedido['id'] ?>" class="btn btn-outline-primary btn-sm">Editar</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php mostrar_pie(true); ?>
