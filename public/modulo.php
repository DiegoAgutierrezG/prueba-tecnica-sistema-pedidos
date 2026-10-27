<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/vista.php';

$usuario = exigir_sesion();
$claveRecibida = $_GET['seccion'] ?? null;
$clave = is_string($claveRecibida) ? $claveRecibida : '';

if (!isset(SECCIONES[$clave])) {
    mostrar_error(404, 'Sección no encontrada', 'La dirección solicitada no corresponde a una sección del sistema.', $usuario);
}

$seccion = SECCIONES[$clave];
$usuario = exigir_permiso($seccion['permiso']);

if (in_array($clave, ['clientes', 'productos', 'pedidos', 'pagos', 'reportes'], true)) {
    header('Location: /' . $clave . '/index.php', true, 302);
    exit;
}

$acciones = array_filter(
    ACCIONES,
    static fn (string $nombre, string $permiso): bool => str_starts_with($permiso, $clave . '.') && tiene_permiso($usuario, $permiso),
    ARRAY_FILTER_USE_BOTH
);

mostrar_cabecera($seccion['nombre'], $usuario, false, $clave);
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <a href="/index.php" class="text-decoration-none">← Volver al inicio</a>
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body p-4 p-md-5">
                <p class="text-uppercase text-secondary small fw-semibold mb-2">Sección autorizada</p>
                <h1 class="h2 mb-3"><?= escapar_html($seccion['nombre']) ?></h1>
                <p class="lead text-secondary"><?= escapar_html($seccion['descripcion']) ?></p>
                <div class="alert alert-info mb-0" role="status">
                    El acceso por rol ya está preparado. Las funciones de esta sección se implementarán en la siguiente etapa.
                </div>
            </div>
        </div>
        <?php if ($acciones !== []): ?>
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body p-4">
                    <h2 class="h5">Acciones previstas para tu rol</h2>
                    <ul class="mb-0">
                        <?php foreach ($acciones as $nombre): ?>
                            <li><?= escapar_html($nombre) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php mostrar_pie(true); ?>
