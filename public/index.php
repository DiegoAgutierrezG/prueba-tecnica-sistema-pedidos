<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/vista.php';

$usuario = exigir_sesion();
$secciones = secciones_visibles($usuario);
$estadosPedido = ['borrador' => 0, 'confirmado' => 0, 'despachado' => 0, 'entregado' => 0, 'anulado' => 0];
$nombresEstado = [
    'borrador' => 'en borrador', 'confirmado' => 'confirmados',
    'despachado' => 'despachados', 'entregado' => 'entregados', 'anulado' => 'anulados',
];
try {
    $conexion = conectar_bd();
    $consulta = $conexion->prepare('SELECT estado, COUNT(*) AS cantidad FROM pedidos GROUP BY estado');
    $consulta->execute();
    foreach ($consulta->fetchAll() as $fila) {
        $estadosPedido[$fila['estado']] = (int) $fila['cantidad'];
    }
    $consulta = $conexion->prepare('SELECT COUNT(*) FROM productos WHERE activo = 1 AND stock < stock_minimo');
    $consulta->execute();
    $productosBajoMinimo = (int) $consulta->fetchColumn();
} catch (Throwable $error) {
    error_log((string) $error);
    mostrar_error(503, 'Servicio no disponible', 'No se pudo consultar el resumen de inicio. Inténtalo de nuevo en unos minutos.', $usuario);
}

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

<div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 g-3 mb-4" aria-label="Resumen del sistema">
    <?php foreach ($estadosPedido as $estado => $cantidad): ?>
        <div class="col"><a class="card h-100 border-0 shadow-sm text-decoration-none text-body" href="/pedidos/index.php?estado=<?= escapar_html($estado) ?>">
            <div class="card-body"><span class="text-secondary small">Pedidos <?= escapar_html($nombresEstado[$estado]) ?></span><strong class="d-block fs-3 mt-2"><?= $cantidad ?></strong></div>
        </a></div>
    <?php endforeach; ?>
    <div class="col"><a class="card h-100 border-0 shadow-sm text-decoration-none text-body" href="<?= tiene_permiso($usuario, 'reportes.ver') ? '/reportes/inventario.php' : '/productos/index.php' ?>">
        <div class="card-body"><span class="text-secondary small">Productos bajo mínimo</span><strong class="d-block fs-3 mt-2"><?= $productosBajoMinimo ?></strong></div>
    </a></div>
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

<?php mostrar_pie(true); ?>
