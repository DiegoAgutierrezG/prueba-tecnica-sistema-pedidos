<?php

declare(strict_types=1);

require_once __DIR__ . '/permisos.php';

function escapar_html(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function icono_menu(string $nombre): string
{
    $trazos = match ($nombre) {
        'inicio' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-6v-7h-4v7H4a1 1 0 0 1-1-1z"/>',
        'clientes' => '<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M17 5a3 3 0 0 1 0 6m1 3a5 5 0 0 1 3 5v1"/>',
        'productos' => '<path d="m3 7 9-4 9 4v10l-9 4-9-4zM3 7l9 4 9-4m-9 4v10"/>',
        'pedidos' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4.5V3h6v1.5M9 10h6m-6 4h6m-6 4h4"/>',
        'pagos' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 9h20m-16 6h4"/>',
        'reportes' => '<path d="M4 20V10m5 10V4m5 16v-7m5 7V8M2 20h20"/>',
        'salir' => '<path d="M10 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5m4-4 4-4-4-4m4 4H9"/>',
        default => '<circle cx="12" cy="12" r="9"/>',
    };

    return '<svg class="icono-menu" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . $trazos . '</svg>';
}

function mostrar_cabecera(string $titulo, ?array $usuario = null, bool $esIngreso = false, ?string $seccionActiva = null): void
{
    $rutaActual = $_SERVER['REQUEST_URI'] ?? '';
    ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escapar_html($titulo) ?> · Distribuidora Andina</title>
    <link rel="icon" type="image/png" href="/assets/img/icono.png">
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <?php if ($esIngreso): ?>
        <link rel="stylesheet" href="/assets/css/acceso.css?v=10">
        <script src="/assets/js/acceso.js?v=3" defer></script>
    <?php elseif ($usuario !== null): ?>
        <link rel="stylesheet" href="/assets/css/aplicacion.css?v=16">
        <script src="/assets/js/navegacion.js?v=4" defer></script>
    <?php endif; ?>
</head>
<body class="<?= $esIngreso ? 'pagina-ingreso' : ($usuario !== null ? 'pagina-aplicacion' : 'bg-light') ?>">
<?php if ($usuario !== null): ?>
    <aside id="menu-lateral" class="menu-lateral" aria-label="Menú principal">
        <a href="/index.php" class="marca-aplicacion" aria-label="Distribuidora Andina, ir al inicio" title="Ir al inicio">
            <span class="logo-empresa-menu"><img src="/assets/img/icono.png" alt="" width="1374" height="1145"></span>
        </a>

        <nav class="enlaces-menu" aria-label="Secciones principales">
            <span class="rotulo-menu">MENÚ</span>
            <a href="/index.php" class="enlace-menu<?= $seccionActiva === 'inicio' ? ' activo' : '' ?>" title="Inicio"<?= $seccionActiva === 'inicio' ? ' aria-current="page"' : '' ?>>
                <?= icono_menu('inicio') ?><span class="texto-menu">Inicio</span>
            </a>
            <?php foreach (secciones_visibles($usuario) as $clave => $seccion): ?>
                <?php
                $subopciones = subopciones_visibles($usuario, $clave);
                $subopcionActiva = in_array($rutaActual, array_column($subopciones, 'ruta'), true);
                $paginaPrincipal = $seccionActiva === $clave && !$subopcionActiva
                    && parse_url($rutaActual, PHP_URL_PATH) === $seccion['ruta'];
                ?>
                <div class="grupo-menu"<?= $subopciones !== [] ? ' data-submenu' : '' ?>>
                    <div class="fila-menu<?= $seccionActiva === $clave ? ' activo' : '' ?>">
                        <a href="<?= escapar_html($seccion['ruta']) ?>" class="enlace-menu" title="<?= escapar_html($seccion['nombre']) ?>"<?= $paginaPrincipal ? ' aria-current="page"' : '' ?>>
                            <?= icono_menu($clave) ?><span class="texto-menu"><?= escapar_html($seccion['nombre']) ?></span>
                        </a>
                        <?php if ($subopciones !== []): ?>
                            <button type="button" class="boton-submenu" data-alternar-submenu
                                aria-controls="submenu-<?= escapar_html($clave) ?>" aria-expanded="false"
                                aria-label="Opciones de <?= escapar_html($seccion['nombre']) ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php if ($subopciones !== []): ?>
                        <ul id="submenu-<?= escapar_html($clave) ?>" class="submenu-menu" aria-label="Accesos de <?= escapar_html($seccion['nombre']) ?>" hidden>
                            <?php foreach ($subopciones as $opcion): ?>
                                <?php $esActual = $seccionActiva === $clave && $rutaActual === $opcion['ruta']; ?>
                                <li><a href="<?= escapar_html($opcion['ruta']) ?>" class="enlace-submenu<?= $esActual ? ' activo' : '' ?>"<?= $esActual ? ' aria-current="page"' : '' ?>><?= escapar_html($opcion['nombre']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="pie-menu">
            <div class="usuario-menu" title="<?= escapar_html($usuario['nombre'] . ' · ' . $usuario['nombre_rol']) ?>">
                <span class="avatar-menu" aria-hidden="true"><?= escapar_html(mb_strtoupper(mb_substr($usuario['nombre'], 0, 1))) ?></span>
                <span class="datos-usuario"><strong><?= escapar_html($usuario['nombre']) ?></strong><small><?= escapar_html($usuario['nombre_rol']) ?></small></span>
            </div>
            <form action="/logout.php" method="post" class="formulario-salida">
                <input type="hidden" name="token_csrf" value="<?= escapar_html(token_csrf()) ?>">
                <button type="submit" class="enlace-menu boton-salida" title="Cerrar sesión">
                    <?= icono_menu('salir') ?><span class="texto-menu">Cerrar sesión</span>
                </button>
            </form>
        </div>
    </aside>
    <div class="velo-menu" data-cerrar-menu aria-hidden="true"></div>
    <dialog id="dialogo-confirmar-salida" class="dialogo-confirmacion"
        aria-labelledby="titulo-confirmar-salida" aria-describedby="descripcion-confirmar-salida">
        <span class="icono-confirmacion" aria-hidden="true">
            <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><circle cx="24" cy="24" r="18"/><path d="M21 16h-5v16h5m2-8h11m-5-5 5 5-5 5"/></svg>
        </span>
        <h2 id="titulo-confirmar-salida">¿Cerrar sesión?</h2>
        <p id="descripcion-confirmar-salida">Tendrás que ingresar de nuevo para continuar.</p>
        <div class="acciones-confirmacion">
            <button type="button" class="btn btn-outline-secondary" data-cancelar-salida autofocus>Cancelar</button>
            <button type="button" class="btn btn-primary" data-confirmar-salida>Sí, cerrar sesión</button>
        </div>
    </dialog>
    <div class="area-principal">
        <header class="barra-superior">
            <button type="button" class="boton-menu" data-alternar-menu aria-controls="menu-lateral" aria-expanded="true" aria-label="Contraer menú" title="Contraer menú">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <div class="titulo-superior">
                <span class="subtitulo-superior">Sistema de pedidos</span>
                <strong><?= escapar_html($titulo) ?></strong>
            </div>
            <span class="rol-superior"><?= escapar_html($usuario['nombre_rol']) ?></span>
        </header>
        <main class="contenido-aplicacion" id="contenido-principal">
<?php else: ?>
<main class="<?= $esIngreso ? 'contenedor-ingreso' : 'container py-5' ?>" id="contenido-principal">
<?php endif; ?>
    <?php
}

function mostrar_pie(bool $conMenu = false): void
{
    ?>
</main>
<?php if ($conMenu): ?></div><?php endif; ?>
</body>
</html>
    <?php
}

function mostrar_error(int $codigo, string $titulo, string $mensaje, ?array $usuario = null): never
{
    http_response_code($codigo);
    mostrar_cabecera($titulo, $usuario);
    ?>
    <div class="card border-0 shadow-sm mx-auto" style="max-width: 38rem">
        <div class="card-body p-4 p-md-5">
            <p class="text-secondary mb-2">Error <?= $codigo ?></p>
            <h1 class="h3 mb-3"><?= escapar_html($titulo) ?></h1>
            <p class="mb-4"><?= escapar_html($mensaje) ?></p>
            <a href="<?= $usuario === null ? '/login.php' : '/index.php' ?>" class="btn btn-primary">Volver</a>
        </div>
    </div>
    <?php
    mostrar_pie($usuario !== null);
    exit;
}
