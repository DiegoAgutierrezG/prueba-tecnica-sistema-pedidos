<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/vista.php';
require_once __DIR__ . '/funciones.php';

$usuario = exigir_permiso('clientes.ver');
$busquedaRecibida = $_GET['busqueda'] ?? '';
$busqueda = is_string($busquedaRecibida) ? trim($busquedaRecibida) : '';
$estadoRecibido = $_GET['estado'] ?? 'activos';
$estado = in_array($estadoRecibido, ['activos', 'inactivos', 'todos'], true) ? $estadoRecibido : 'activos';
$mensajeBusqueda = '';
$clientes = [];

if (!mb_check_encoding($busqueda, 'UTF-8') || mb_strlen($busqueda, 'UTF-8') > 150) {
    $mensajeBusqueda = 'La búsqueda admite hasta 150 caracteres. Acórtala y vuelve a buscar.';
} else {
    try {
        $sql = 'SELECT id, nit, razon_social, tipo, cupo_credito, ciudad, activo FROM clientes';
        $condiciones = [];
        $parametros = [];

        if ($estado !== 'todos') {
            $condiciones[] = 'activo = :activo';
            $parametros['activo'] = $estado === 'activos' ? 1 : 0;
        }

        if ($busqueda !== '') {
            $condiciones[] = "(nit LIKE :nit ESCAPE '!' OR razon_social LIKE :razon_social ESCAPE '!')";
            $patron = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $busqueda) . '%';
            $parametros['nit'] = $patron;
            $parametros['razon_social'] = $patron;
        }

        if ($condiciones !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $condiciones);
        }

        $consulta = conectar_bd()->prepare($sql . ' ORDER BY razon_social, id');
        $consulta->execute($parametros);
        $clientes = $consulta->fetchAll();
    } catch (Throwable $error) {
        error_log((string) $error);
        mostrar_error(503, 'Servicio no disponible', 'No se pudo consultar la lista de clientes. Vuelve al inicio e inténtalo de nuevo en unos minutos.', $usuario);
    }
}

$aviso = $_SESSION['aviso_clientes'] ?? null;
unset($_SESSION['aviso_clientes']);

mostrar_cabecera('Clientes', $usuario, false, 'clientes');
?>
<nav aria-label="Ruta de navegación" class="mb-4">
    <a href="/index.php" class="text-decoration-none">Inicio</a> <span class="text-secondary">/ Clientes</span>
</nav>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h2 mb-2">Clientes</h1>
        <p class="text-secondary mb-0">Consulta los datos comerciales y el cupo de crédito.</p>
    </div>
    <?php if (tiene_permiso($usuario, 'clientes.editar')): ?>
        <a href="/clientes/formulario.php" class="btn btn-primary">Nuevo cliente</a>
    <?php endif; ?>
</div>

<?php if (is_string($aviso) && $aviso !== ''): ?>
    <div class="alert alert-success" role="status"><?= escapar_html($aviso) ?></div>
<?php endif; ?>
<?php if ($mensajeBusqueda !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= escapar_html($mensajeBusqueda) ?></div>
<?php endif; ?>

<form action="/clientes/index.php" method="get" class="card border-0 shadow-sm mb-4">
    <div class="card-body row g-3 align-items-end">
        <div class="col-md-7">
            <label for="busqueda" class="form-label">Buscar por NIT o razón social</label>
            <input id="busqueda" name="busqueda" type="search" class="form-control" value="<?= escapar_html($busqueda) ?>" maxlength="150" placeholder="Escribe una parte del NIT o nombre">
        </div>
        <div class="col-md-3">
            <label for="estado" class="form-label">Estado</label>
            <select id="estado" name="estado" class="form-select">
                <option value="todos" <?= $estado === 'todos' ? 'selected' : '' ?>>Todos</option>
                <option value="activos" <?= $estado === 'activos' ? 'selected' : '' ?>>Activos</option>
                <option value="inactivos" <?= $estado === 'inactivos' ? 'selected' : '' ?>>Inactivos</option>
            </select>
        </div>
        <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-primary">Aplicar filtros</button>
        </div>
    </div>
</form>

<?php if ($mensajeBusqueda === ''): ?>
    <p class="text-secondary small mb-2"><?= count($clientes) ?> cliente<?= count($clientes) === 1 ? '' : 's' ?> encontrado<?= count($clientes) === 1 ? '' : 's' ?>.</p>
    <?php if ($clientes === []): ?>
        <div class="alert alert-info" role="status">No hay clientes que coincidan con esta búsqueda.</div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" class="ps-4">Razón social</th>
                            <th scope="col">NIT</th>
                            <th scope="col">Tipo</th>
                            <th scope="col">Ciudad</th>
                            <th scope="col">Cupo de crédito</th>
                            <th scope="col">Estado</th>
                            <?php if (tiene_permiso($usuario, 'clientes.editar') || tiene_permiso($usuario, 'clientes.activar')): ?>
                                <th scope="col" class="pe-4">Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td class="ps-4 fw-medium"><?= escapar_html($cliente['razon_social']) ?></td>
                                <td><?= escapar_html($cliente['nit']) ?></td>
                                <td><?= $cliente['tipo'] === 'mayorista' ? 'Mayorista' : 'Minorista' ?></td>
                                <td><?= escapar_html($cliente['ciudad']) ?></td>
                                <td class="text-nowrap"><?= escapar_html(mostrar_cupo($cliente['cupo_credito'])) ?></td>
                                <td><span class="badge text-bg-<?= (int) $cliente['activo'] === 1 ? 'success' : 'secondary' ?>"><?= (int) $cliente['activo'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                                <?php if (tiene_permiso($usuario, 'clientes.editar') || tiene_permiso($usuario, 'clientes.activar')): ?>
                                    <td class="pe-4">
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php if (tiene_permiso($usuario, 'clientes.editar')): ?>
                                                <a href="/clientes/formulario.php?id=<?= (int) $cliente['id'] ?>" class="btn btn-outline-secondary btn-sm">Editar</a>
                                            <?php endif; ?>
                                            <?php if (tiene_permiso($usuario, 'clientes.activar')): ?>
                                                <form action="/clientes/estado.php" method="post" class="m-0 formulario-estado-cliente" data-nombre-cliente="<?= escapar_html($cliente['razon_social']) ?>">
                                                    <input type="hidden" name="token_csrf" value="<?= escapar_html(token_csrf()) ?>">
                                                    <input type="hidden" name="id" value="<?= (int) $cliente['id'] ?>">
                                                    <button type="submit" name="accion" value="<?= (int) $cliente['activo'] === 1 ? 'desactivar' : 'activar' ?>" class="btn btn-outline-<?= (int) $cliente['activo'] === 1 ? 'danger' : 'success' ?> btn-sm">
                                                        <?= (int) $cliente['activo'] === 1 ? 'Desactivar' : 'Reactivar' ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php if (tiene_permiso($usuario, 'clientes.activar')): ?>
    <dialog id="dialogo-estado-cliente" class="dialogo-confirmacion"
        aria-labelledby="titulo-estado-cliente" aria-describedby="descripcion-estado-cliente">
        <span class="icono-confirmacion" aria-hidden="true">
            <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><circle cx="24" cy="24" r="18"/><circle cx="24" cy="19" r="5"/><path d="M15 33c1.5-5 5-7 9-7s7.5 2 9 7"/></svg>
        </span>
        <h2 id="titulo-estado-cliente">Cambiar estado del cliente</h2>
        <p id="descripcion-estado-cliente"></p>
        <div class="acciones-confirmacion">
            <button type="button" class="btn btn-outline-secondary" data-cancelar-estado autofocus>Cancelar</button>
            <button type="button" class="btn btn-primary" data-confirmar-estado>Confirmar</button>
        </div>
    </dialog>
    <script src="/assets/js/clientes.js?v=1" defer></script>
<?php endif; ?>
<?php mostrar_pie(true); ?>
