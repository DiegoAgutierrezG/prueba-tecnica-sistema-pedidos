<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/vista.php';

$usuario = exigir_sesion();
$secciones = secciones_visibles($usuario);
$acciones = array_filter(
    ACCIONES,
    static fn (string $nombre, string $permiso): bool => tiene_permiso($usuario, $permiso),
    ARRAY_FILTER_USE_BOTH
);

mostrar_cabecera('Inicio', $usuario, false, 'inicio');
?>
<div class="row align-items-center g-4 mb-5">
    <div class="col-lg-8">
        <p class="text-uppercase text-secondary small fw-semibold mb-2">Sistema de pedidos</p>
        <h1 class="display-6 fw-semibold mb-3">Bienvenido, <?= escapar_html($usuario['nombre']) ?></h1>
        <p class="lead text-secondary mb-0">Tu perfil es <?= escapar_html($usuario['nombre_rol']) ?>. Las secciones que ves corresponden a los permisos de ese rol.</p>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <p class="text-secondary small mb-1">Sesión activa</p>
                <p class="fw-semibold mb-1"><?= escapar_html($usuario['correo']) ?></p>
                <span class="badge text-bg-success">Acceso verificado</span>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info" role="status">
    Clientes, productos, pedidos, pagos y ventas del mes ya están disponibles.
</div>

<h2 class="h4 mt-5 mb-3">Secciones de tu perfil</h2>
<div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
    <?php foreach ($secciones as $seccion): ?>
        <div class="col">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body p-4 d-flex flex-column">
                    <h3 class="h5"><?= escapar_html($seccion['nombre']) ?></h3>
                    <p class="text-secondary flex-grow-1 mb-4"><?= escapar_html($seccion['descripcion']) ?></p>
                    <a class="btn btn-outline-primary align-self-start" href="<?= escapar_html($seccion['ruta']) ?>">Ver sección</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<h2 class="h4 mt-5 mb-3">Acciones asignadas a tu rol</h2>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <ul class="row row-cols-1 row-cols-md-2 g-2 mb-0 ps-4">
            <?php foreach ($acciones as $nombre): ?>
                <li class="col"><?= escapar_html($nombre) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php mostrar_pie(true); ?>
