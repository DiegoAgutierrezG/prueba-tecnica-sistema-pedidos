<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/vista.php';
require_once __DIR__ . '/funciones.php';

$usuario = exigir_permiso('productos.ver');
$puedeAdministrar = tiene_permiso($usuario, 'productos.administrar');
$estadoRecibido = $_GET['estado'] ?? 'activos';
$estado = $estadoRecibido === 'inactivos' ? 'inactivos' : 'activos';

if ($estado === 'inactivos' && !$puedeAdministrar) {
    mostrar_error(403, 'Acceso denegado', 'Tu usuario no puede consultar productos inactivos.', $usuario);
}

$busquedaRecibida = $_GET['busqueda'] ?? '';
$busqueda = is_string($busquedaRecibida) ? trim($busquedaRecibida) : '';
$categoriaRecibida = $_GET['categoria'] ?? '';
$categoria = is_string($categoriaRecibida) ? $categoriaRecibida : '';
$ordenRecibido = $_GET['orden'] ?? 'sku';
$orden = in_array($ordenRecibido, ['sku', 'stock', 'minimo'], true) ? $ordenRecibido : 'sku';
$direccionRecibida = $_GET['direccion'] ?? 'asc';
$direccion = $orden !== 'sku' && $direccionRecibida === 'desc' ? 'desc' : 'asc';
$paginaRecibida = $_GET['pagina'] ?? '1';
$paginaSolicitada = is_string($paginaRecibida) && ctype_digit($paginaRecibida)
    ? max(1, (int) $paginaRecibida) : 1;
$mensajeFiltro = '';
$productos = [];
$total = 0;
$paginas = 1;
$pagina = 1;
$filtrosEnlace = ['busqueda' => $busqueda, 'categoria' => $categoria, 'estado' => $estado];

try {
    $conexion = conectar_bd();
    $categorias = $conexion->query('SELECT id, nombre FROM categorias ORDER BY nombre, id')->fetchAll();

    if (!mb_check_encoding($busqueda, 'UTF-8') || mb_strlen($busqueda, 'UTF-8') > 150) {
        $mensajeFiltro = 'La búsqueda admite hasta 150 caracteres.';
    } elseif ($categoria !== '' && (!ctype_digit($categoria)
        || !in_array((int) $categoria, array_map('intval', array_column($categorias, 'id')), true))) {
        $mensajeFiltro = 'Selecciona una categoría disponible.';
    } else {
        $condiciones = ['p.activo = :activo'];
        $parametros = ['activo' => $estado === 'activos' ? 1 : 0];

        if ($categoria !== '') {
            $condiciones[] = 'p.categoria_id = :categoria';
            $parametros['categoria'] = (int) $categoria;
        }
        if ($busqueda !== '') {
            $condiciones[] = "(p.sku LIKE :sku ESCAPE '!' OR p.nombre LIKE :nombre ESCAPE '!')";
            $patron = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $busqueda) . '%';
            $parametros['sku'] = $patron;
            $parametros['nombre'] = $patron;
        }

        $desde = ' FROM productos AS p INNER JOIN categorias AS c ON c.id = p.categoria_id
                   WHERE ' . implode(' AND ', $condiciones);
        $conteo = $conexion->prepare('SELECT COUNT(*)' . $desde);
        $conteo->execute($parametros);
        $total = (int) $conteo->fetchColumn();
        $paginas = max(1, intdiv($total + 19, 20));
        $pagina = min($paginaSolicitada, $paginas);

        $columnaOrden = match ($orden) {
            'stock' => 'p.stock',
            'minimo' => 'p.stock_minimo',
            default => 'p.sku',
        };
        $sentidoOrden = $direccion === 'desc' ? 'DESC' : 'ASC';
        $ordenSql = $orden === 'sku'
            ? 'p.sku ASC, p.id ASC'
            : $columnaOrden . ' ' . $sentidoOrden . ', p.sku ASC, p.id ASC';

        $consulta = $conexion->prepare(
            'SELECT p.id, p.sku, p.nombre, c.nombre AS categoria, p.precio_lista,
                    p.exento_iva, p.stock, p.stock_minimo, p.activo'
            . $desde . ' ORDER BY ' . $ordenSql . ' LIMIT :limite OFFSET :desplazamiento'
        );
        foreach ($parametros as $clave => $valor) {
            $consulta->bindValue(':' . $clave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $consulta->bindValue(':limite', 20, PDO::PARAM_INT);
        $consulta->bindValue(':desplazamiento', ($pagina - 1) * 20, PDO::PARAM_INT);
        $consulta->execute();
        $productos = $consulta->fetchAll();
    }
} catch (Throwable $error) {
    error_log((string) $error);
    mostrar_error(503, 'Servicio no disponible', 'No se pudo consultar el catálogo de productos.', $usuario);
}

$aviso = $_SESSION['aviso_productos'] ?? null;
unset($_SESSION['aviso_productos']);

mostrar_cabecera('Productos', $usuario, false, 'productos');
?>
<nav aria-label="Ruta de navegación" class="mb-4">
    <a href="/index.php" class="text-decoration-none">Inicio</a> <span class="text-secondary">/ Productos</span>
</nav>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h2 mb-2">Productos</h1>
        <p class="text-secondary mb-0">Consulta precios de lista y existencias por categoría.</p>
    </div>
    <?php if ($puedeAdministrar): ?>
        <a href="/productos/formulario.php" class="btn btn-primary">Nuevo producto</a>
    <?php endif; ?>
</div>

<?php if (is_string($aviso) && $aviso !== ''): ?>
    <div class="alert alert-success" role="status"><?= escapar_html($aviso) ?></div>
<?php endif; ?>
<?php if ($mensajeFiltro !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= escapar_html($mensajeFiltro) ?></div>
<?php endif; ?>

<form action="/productos/index.php" method="get" class="card border-0 shadow-sm mb-4">
    <?php if ($orden !== 'sku'): ?>
        <input type="hidden" name="orden" value="<?= escapar_html($orden) ?>">
        <input type="hidden" name="direccion" value="<?= escapar_html($direccion) ?>">
    <?php endif; ?>
    <div class="card-body row g-3 align-items-end">
        <div class="col-md-5">
            <label for="busqueda" class="form-label">SKU o nombre</label>
            <input id="busqueda" name="busqueda" type="search" class="form-control" value="<?= escapar_html($busqueda) ?>" maxlength="150" placeholder="Buscar producto">
        </div>
        <div class="col-md-3">
            <label for="categoria" class="form-label">Categoría</label>
            <select id="categoria" name="categoria" class="form-select">
                <option value="">Todas</option>
                <?php foreach ($categorias as $opcion): ?>
                    <option value="<?= (int) $opcion['id'] ?>" <?= $categoria === (string) $opcion['id'] ? 'selected' : '' ?>><?= escapar_html($opcion['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($puedeAdministrar): ?>
            <div class="col-md-2">
                <label for="estado" class="form-label">Estado</label>
                <select id="estado" name="estado" class="form-select">
                    <option value="activos" <?= $estado === 'activos' ? 'selected' : '' ?>>Activos</option>
                    <option value="inactivos" <?= $estado === 'inactivos' ? 'selected' : '' ?>>Inactivos</option>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-primary">Filtrar</button>
        </div>
    </div>
</form>

<?php if ($mensajeFiltro === ''): ?>
    <p class="text-secondary small mb-2">
        <?= $total ?> producto<?= $total === 1 ? '' : 's' ?> encontrado<?= $total === 1 ? '' : 's' ?>.
        <?php if ($orden !== 'sku'): ?>
            <a href="/productos/index.php?<?= escapar_html(http_build_query($filtrosEnlace)) ?>">Volver al orden por SKU</a>
        <?php endif; ?>
    </p>
    <?php if ($productos === []): ?>
        <div class="alert alert-info" role="status">No hay productos que coincidan con estos filtros.</div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 tabla-productos">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" class="ps-4"<?= $orden === 'sku' ? ' aria-sort="ascending"' : '' ?>>SKU</th>
                            <th scope="col">Producto</th>
                            <th scope="col">Categoría</th>
                            <th scope="col">Precio sin IVA</th>
                            <th scope="col">IVA</th>
                            <th scope="col" class="text-nowrap"<?= $orden === 'stock' ? ' aria-sort="' . ($direccion === 'asc' ? 'ascending' : 'descending') . '"' : '' ?>>
                                Stock
                                <span class="flechas-orden">
                                    <a class="btn flecha-orden<?= $orden === 'stock' && $direccion === 'asc' ? ' activa' : '' ?>" href="/productos/index.php?<?= escapar_html(http_build_query($filtrosEnlace + ['orden' => 'stock', 'direccion' => 'asc'])) ?>" aria-label="Ordenar stock de menor a mayor" title="Stock: menor a mayor"><span aria-hidden="true">↑</span></a>
                                    <a class="btn flecha-orden<?= $orden === 'stock' && $direccion === 'desc' ? ' activa' : '' ?>" href="/productos/index.php?<?= escapar_html(http_build_query($filtrosEnlace + ['orden' => 'stock', 'direccion' => 'desc'])) ?>" aria-label="Ordenar stock de mayor a menor" title="Stock: mayor a menor"><span aria-hidden="true">↓</span></a>
                                </span>
                            </th>
                            <th scope="col" class="text-nowrap"<?= $orden === 'minimo' ? ' aria-sort="' . ($direccion === 'asc' ? 'ascending' : 'descending') . '"' : '' ?>>
                                Mínimo
                                <span class="flechas-orden">
                                    <a class="btn flecha-orden<?= $orden === 'minimo' && $direccion === 'asc' ? ' activa' : '' ?>" href="/productos/index.php?<?= escapar_html(http_build_query($filtrosEnlace + ['orden' => 'minimo', 'direccion' => 'asc'])) ?>" aria-label="Ordenar mínimo de menor a mayor" title="Mínimo: menor a mayor"><span aria-hidden="true">↑</span></a>
                                    <a class="btn flecha-orden<?= $orden === 'minimo' && $direccion === 'desc' ? ' activa' : '' ?>" href="/productos/index.php?<?= escapar_html(http_build_query($filtrosEnlace + ['orden' => 'minimo', 'direccion' => 'desc'])) ?>" aria-label="Ordenar mínimo de mayor a menor" title="Mínimo: mayor a menor"><span aria-hidden="true">↓</span></a>
                                </span>
                            </th>
                            <?php if ($puedeAdministrar): ?><th scope="col">Estado</th><th scope="col" class="pe-4">Acciones</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $producto): ?>
                            <?php $bajoMinimo = (int) $producto['stock'] < (int) $producto['stock_minimo']; ?>
                            <tr<?= $bajoMinimo ? ' class="table-warning"' : '' ?>>
                                <td class="ps-4 fw-semibold"><?= escapar_html($producto['sku']) ?></td>
                                <td><?= escapar_html($producto['nombre']) ?></td>
                                <td><?= escapar_html($producto['categoria']) ?></td>
                                <td class="text-nowrap"><?= escapar_html(mostrar_precio($producto['precio_lista'])) ?></td>
                                <td><?= (int) $producto['exento_iva'] === 1 ? 'Exento' : '19 %' ?></td>
                                <td>
                                    <?= (int) $producto['stock'] ?>
                                    <?php if ($bajoMinimo): ?>
                                        <span class="badge text-bg-warning ms-1">Bajo mínimo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int) $producto['stock_minimo'] ?></td>
                                <?php if ($puedeAdministrar): ?>
                                    <td><span class="badge text-bg-<?= (int) $producto['activo'] === 1 ? 'success' : 'secondary' ?>"><?= (int) $producto['activo'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                                    <td class="pe-4">
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="/productos/formulario.php?id=<?= (int) $producto['id'] ?>" class="btn btn-outline-secondary btn-sm">Editar</a>
                                            <form action="/productos/estado.php" method="post" class="m-0">
                                                <input type="hidden" name="token_csrf" value="<?= escapar_html(token_csrf()) ?>">
                                                <input type="hidden" name="id" value="<?= (int) $producto['id'] ?>">
                                                <button type="submit" name="accion" value="<?= (int) $producto['activo'] === 1 ? 'desactivar' : 'activar' ?>" class="btn btn-outline-<?= (int) $producto['activo'] === 1 ? 'danger' : 'success' ?> btn-sm" onclick="return confirm('¿Confirmas este cambio de estado?')">
                                                    <?= (int) $producto['activo'] === 1 ? 'Desactivar' : 'Reactivar' ?>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($paginas > 1): ?>
            <nav aria-label="Páginas de productos" class="d-flex align-items-center justify-content-between gap-3 mt-3">
                <span class="text-secondary small">Página <?= $pagina ?> de <?= $paginas ?></span>
                <div class="d-flex gap-2">
                    <?php if ($pagina > 1): ?>
                        <a class="btn btn-outline-primary btn-sm" href="/productos/index.php?<?= escapar_html(http_build_query($filtrosEnlace + ['orden' => $orden, 'direccion' => $direccion, 'pagina' => $pagina - 1])) ?>">Anterior</a>
                    <?php endif; ?>
                    <?php if ($pagina < $paginas): ?>
                        <a class="btn btn-outline-primary btn-sm" href="/productos/index.php?<?= escapar_html(http_build_query($filtrosEnlace + ['orden' => $orden, 'direccion' => $direccion, 'pagina' => $pagina + 1])) ?>">Siguiente</a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
<?php mostrar_pie(true); ?>
