<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/vista.php';
require_once __DIR__ . '/includes/intentos_acceso.php';

try {
    if (usuario_actual() !== null) {
        header('Location: /index.php', true, 302);
        exit;
    }
} catch (Throwable $error) {
    error_log((string) $error);
    mostrar_error(503, 'Servicio no disponible', 'No se pudo consultar la base de datos. Inténtalo de nuevo en unos minutos.');
}

$correo = '';
$mensaje = '';
$mostrarAvisoCierre = ($_SESSION['aviso_cierre'] ?? false) === true;
$mostrarAvisoExpiracion = ($_SESSION['aviso_expiracion'] ?? false) === true;
unset($_SESSION['aviso_cierre'], $_SESSION['aviso_expiracion']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token_csrf'] ?? null;

    if (!csrf_valido(is_string($token) ? $token : null)) {
        mostrar_error(403, 'Formulario vencido', 'Recarga la página e intenta ingresar de nuevo.');
    }

    $correoRecibido = $_POST['correo'] ?? '';
    $claveRecibida = $_POST['clave'] ?? '';
    $correo = is_string($correoRecibido) ? trim($correoRecibido) : '';
    $clave = is_string($claveRecibida) ? $claveRecibida : '';

    if (strlen($correo) <= 120 && filter_var($correo, FILTER_VALIDATE_EMAIL) && $clave !== '') {
        try {
            $resultadoIngreso = verificar_ingreso(
                conectar_bd(), $correo, $clave, $_SERVER['REMOTE_ADDR'] ?? 'sin-ip'
            );
            if (is_int($resultadoIngreso)) {
                iniciar_sesion_de_usuario($resultadoIngreso);
                header('Location: /index.php', true, 303);
                exit;
            }
            if ($resultadoIngreso === 'bloqueado') {
                http_response_code(429);
                header('Retry-After: 900');
                $mensaje = 'Demasiados intentos de ingreso. Inténtalo de nuevo en 15 minutos.';
            }
        } catch (Throwable $error) {
            error_log((string) $error);
            mostrar_error(503, 'Servicio no disponible', 'No se pudo comprobar el acceso. Inténtalo de nuevo en unos minutos.');
        }
    }

    if ($mensaje === '') {
        $mensaje = 'Correo o contraseña incorrectos.';
    }
}

mostrar_cabecera('Iniciar sesión', null, true);
?>
<div class="tarjeta-ingreso">
    <section class="panel-bienvenida" aria-labelledby="titulo-bienvenida">
        <div class="marca-ingreso">
            <img src="/assets/img/icono.png" class="logo-empresa-ingreso" alt="Distribuidora Andina" width="1374" height="1145">
        </div>
        <div class="mensaje-bienvenida">
            <p class="etiqueta-panel">SISTEMA DE PEDIDOS</p>
            <h2 id="titulo-bienvenida">Bienvenido <br>de nuevo</h2>
            <p>Tu espacio para gestionar pedidos, inventario y pagos de forma sencilla.</p>
        </div>
        <img class="ilustracion-ingreso" src="/assets/img/gestion-pedidos-v2.png" alt="" aria-hidden="true">
    </section>

    <section class="panel-formulario" aria-labelledby="titulo-ingreso">
        <div class="contenido-formulario">
            <p class="etiqueta-formulario">ACCESO AL SISTEMA</p>
            <h1 id="titulo-ingreso">Iniciar sesión</h1>
            <p class="subtitulo-formulario">Ingresa tus credenciales para continuar.</p>

            <?php if ($mensaje !== ''): ?>
                <div class="alert alert-danger" role="alert"><?= escapar_html($mensaje) ?></div>
            <?php endif; ?>

            <form method="post" action="/login.php" class="formulario-ingreso">
                <input type="hidden" name="token_csrf" value="<?= escapar_html(token_csrf()) ?>">
                <div class="grupo-campo">
                    <label for="correo">Correo electrónico</label>
                    <div class="control-campo">
                        <span class="icono-campo" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
                        </span>
                        <input id="correo" name="correo" type="email" value="<?= escapar_html($correo) ?>" placeholder="nombre@empresa.com" maxlength="120" autocomplete="username" required autofocus>
                    </div>
                </div>
                <div class="grupo-campo">
                    <label for="clave">Contraseña</label>
                    <div class="control-campo control-clave">
                        <span class="icono-campo" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                        </span>
                        <input id="clave" name="clave" type="password" placeholder="Ingresa tu contraseña" autocomplete="current-password" required>
                        <button class="boton-visibilidad" type="button" data-alternar-clave aria-label="Mostrar contraseña" aria-pressed="false">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12s3.3-5 9.5-5 9.5 5 9.5 5-3.3 5-9.5 5-9.5-5-9.5-5Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="boton-ingreso">Iniciar sesión</button>
            </form>
            <p class="nota-acceso">Acceso exclusivo para personal autorizado.</p>
        </div>
    </section>
</div>
<?php if ($mostrarAvisoCierre || $mostrarAvisoExpiracion): ?>
    <dialog id="dialogo-aviso" class="dialogo-aviso<?= $mostrarAvisoExpiracion ? ' aviso-expiracion' : '' ?>"
        aria-labelledby="titulo-aviso" aria-describedby="descripcion-aviso">
        <span class="dialogo-aviso-icono" aria-hidden="true">
            <?php if ($mostrarAvisoExpiracion): ?>
                <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="24" cy="24" r="18"/><path d="M24 14v11l7 4"/></svg>
            <?php else: ?>
                <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="24" cy="24" r="18"/><path d="m16 24 5.5 5.5L32 19"/></svg>
            <?php endif; ?>
        </span>
        <h2 id="titulo-aviso"><?= $mostrarAvisoExpiracion ? 'Sesión vencida' : 'Sesión cerrada' ?></h2>
        <p id="descripcion-aviso"><?= $mostrarAvisoExpiracion
            ? 'Tu sesión terminó por seguridad. Ingresa de nuevo para continuar.'
            : 'Tu acceso se cerró correctamente. Puedes volver a ingresar cuando lo necesites.' ?></p>
        <form method="dialog">
            <button type="submit" class="boton-cerrar-aviso" autofocus>Entendido</button>
        </form>
    </dialog>
    <noscript><p class="mensaje-sin-script" role="status"><?= $mostrarAvisoExpiracion
        ? 'Tu sesión venció. Ingresa de nuevo para continuar.'
        : 'La sesión se cerró correctamente.' ?></p></noscript>
<?php endif; ?>
<?php mostrar_pie(); ?>
