<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/vista.php';

$administrador = exigir_permiso('usuarios.administrar');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    mostrar_error(405, 'Método no permitido', 'Abre la lista de usuarios desde el menú.', $administrador);
}

$busquedaRecibida = $_GET['busqueda'] ?? '';
$busqueda = is_string($busquedaRecibida) ? trim($busquedaRecibida) : '';
$estadoRecibido = $_GET['estado'] ?? 'todos';
$estado = is_string($estadoRecibido) && in_array($estadoRecibido, ['todos', 'activos', 'inactivos'], true)
    ? $estadoRecibido : 'todos';
$errorBusqueda = '';
$usuarios = [];

if (!mb_check_encoding($busqueda, 'UTF-8') || mb_strlen($busqueda, 'UTF-8') > 120) {
    $errorBusqueda = 'La búsqueda admite hasta 120 caracteres. Acórtala y vuelve a buscar.';
} else {
    try {
        $sql = 'SELECT u.id, u.nombre, u.correo, u.activo, r.nombre AS nombre_rol
                FROM usuarios AS u INNER JOIN roles AS r ON r.id = u.rol_id';
        $condiciones = [];
        $parametros = [];
        if ($estado !== 'todos') {
            $condiciones[] = 'u.activo = :activo';
            $parametros['activo'] = $estado === 'activos' ? 1 : 0;
        }
        if ($busqueda !== '') {
            $condiciones[] = "(u.nombre LIKE :nombre ESCAPE '!' OR u.correo LIKE :correo ESCAPE '!')";
            $patron = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $busqueda) . '%';
            $parametros['nombre'] = $patron;
            $parametros['correo'] = $patron;
        }
        if ($condiciones !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $condiciones);
        }
        $consulta = conectar_bd()->prepare($sql . ' ORDER BY u.nombre, u.id');
        $consulta->execute($parametros);
        $usuarios = $consulta->fetchAll();
    } catch (Throwable $error) {
        error_log((string) $error);
        mostrar_error(503, 'Servicio no disponible', 'No se pudo consultar la lista de usuarios. Vuelve al inicio e inténtalo de nuevo en unos minutos.', $administrador);
    }
}

$aviso = $_SESSION['aviso_usuarios'] ?? null;
unset($_SESSION['aviso_usuarios']);
$accionAviso = is_array($aviso) ? ($aviso['accion'] ?? null) : null;
$nombreAviso = is_array($aviso) && is_string($aviso['nombre'] ?? null) ? $aviso['nombre'] : '';
$tituloAviso = $accionAviso === 'crear' ? 'Usuario creado' : 'Cambios guardados';
$mensajeAviso = $accionAviso === 'crear'
    ? 'Se creó la cuenta de ' . $nombreAviso . ' correctamente.'
    : 'Se guardaron los cambios de ' . $nombreAviso . ' correctamente.';

mostrar_cabecera('Administrar usuarios', $administrador, false, 'usuarios');
?>
<nav aria-label="Ruta de navegación" class="mb-4">
    <a href="/index.php" class="text-decoration-none">Inicio</a>
    <span class="text-secondary"> / Usuarios</span>
</nav>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h2 mb-2">Administrar usuarios</h1>
        <p class="text-secondary mb-0">Crea cuentas y asigna el rol que corresponde a cada persona.</p>
    </div>
    <a href="/usuarios/formulario.php" class="btn btn-primary">Nuevo usuario</a>
</div>

<?php if (in_array($accionAviso, ['crear', 'editar'], true)): ?>
    <div id="aviso-usuario-respaldo" class="alert alert-success" role="status"><?= escapar_html($mensajeAviso) ?></div>
    <dialog id="dialogo-resultado-usuario" class="dialogo-confirmacion"
        aria-labelledby="titulo-resultado-usuario" aria-describedby="descripcion-resultado-usuario">
        <span class="icono-confirmacion" aria-hidden="true">
            <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><circle cx="24" cy="24" r="18"/><path d="m16 24 6 6 11-12"/></svg>
        </span>
        <h2 id="titulo-resultado-usuario"><?= escapar_html($tituloAviso) ?></h2>
        <p id="descripcion-resultado-usuario"><?= escapar_html($mensajeAviso) ?></p>
        <div class="acciones-confirmacion">
            <button type="button" class="btn btn-primary" data-cerrar-resultado-usuario>Entendido</button>
        </div>
    </dialog>
<?php endif; ?>
<?php if ($errorBusqueda !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= escapar_html($errorBusqueda) ?></div>
<?php endif; ?>

<form action="/usuarios/index.php" method="get" class="card border-0 shadow-sm mb-4">
    <div class="card-body row g-3 align-items-end">
        <div class="col-md-7">
            <label for="busqueda" class="form-label">Buscar por nombre o correo</label>
            <input id="busqueda" name="busqueda" type="search" class="form-control" value="<?= escapar_html($busqueda) ?>" maxlength="120" placeholder="Escribe un nombre o correo">
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

<?php if ($errorBusqueda === ''): ?>
    <p class="text-secondary small mb-2"><?= count($usuarios) ?> usuario<?= count($usuarios) === 1 ? '' : 's' ?> encontrado<?= count($usuarios) === 1 ? '' : 's' ?>.</p>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th scope="col">Nombre</th><th scope="col">Correo</th><th scope="col">Rol</th><th scope="col">Estado</th><th scope="col" class="text-end">Acción</th></tr></thead>
                <tbody>
                <?php if ($usuarios === []): ?>
                    <tr><td colspan="5" class="text-center text-secondary py-4">No hay usuarios con esos filtros.</td></tr>
                <?php else: ?>
                    <?php foreach ($usuarios as $fila): ?>
                        <tr>
                            <td>
                                <strong><?= escapar_html($fila['nombre']) ?></strong>
                                <?php if ((int) $fila['id'] === $administrador['id']): ?><small class="text-secondary ms-1">(tu cuenta)</small><?php endif; ?>
                            </td>
                            <td><?= escapar_html($fila['correo']) ?></td>
                            <td><?= escapar_html($fila['nombre_rol']) ?></td>
                            <td><span class="badge <?= (int) $fila['activo'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int) $fila['activo'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                            <td class="text-end"><a href="/usuarios/formulario.php?id=<?= (int) $fila['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php mostrar_pie(true); ?>
