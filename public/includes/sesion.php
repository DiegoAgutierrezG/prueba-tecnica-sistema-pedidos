<?php

declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

const LIMITE_INACTIVIDAD = 30 * 60;
const LIMITE_SESION = 8 * 60 * 60;

function iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string) LIMITE_SESION);
    session_name('andina_sesion');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (!@session_start()) {
        throw new RuntimeException('No se pudo iniciar la sesión.');
    }
}

function token_csrf(): string
{
    iniciar_sesion();

    if (!isset($_SESSION['token_csrf'])) {
        $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['token_csrf'];
}

function csrf_valido(?string $token): bool
{
    iniciar_sesion();

    return $token !== null
        && isset($_SESSION['token_csrf'])
        && hash_equals($_SESSION['token_csrf'], $token);
}

function iniciar_sesion_de_usuario(int $usuarioId): void
{
    iniciar_sesion();
    $_SESSION = [];

    if (!@session_regenerate_id(true)) {
        throw new RuntimeException('No se pudo renovar la sesión.');
    }

    $_SESSION['usuario_id'] = $usuarioId;
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
    $_SESSION['inicio_sesion'] = time();
    $_SESSION['ultimo_acceso'] = $_SESSION['inicio_sesion'];
}

function sesion_vencida(array $datos, int $ahora): bool
{
    $inicio = $datos['inicio_sesion'] ?? null;
    $ultimoAcceso = $datos['ultimo_acceso'] ?? null;

    return !is_int($inicio)
        || !is_int($ultimoAcceso)
        || $inicio > $ahora
        || $ultimoAcceso > $ahora
        || $ultimoAcceso < $inicio
        || $ahora - $ultimoAcceso >= LIMITE_INACTIVIDAD
        || $ahora - $inicio >= LIMITE_SESION;
}

function usuario_actual(): ?array
{
    iniciar_sesion();
    $usuarioId = $_SESSION['usuario_id'] ?? null;

    if (!is_int($usuarioId) || $usuarioId < 1) {
        return null;
    }

    $ahora = time();

    if (sesion_vencida($_SESSION, $ahora)) {
        cerrar_sesion_vencida();
        return null;
    }

    $consulta = conectar_bd()->prepare(
        'SELECT u.id, u.nombre, u.correo, r.codigo AS codigo_rol, r.nombre AS nombre_rol
         FROM usuarios AS u
         INNER JOIN roles AS r ON r.id = u.rol_id
         WHERE u.id = :id AND u.activo = 1 LIMIT 1'
    );
    $consulta->execute(['id' => $usuarioId]);
    $usuario = $consulta->fetch();

    if ($usuario === false) {
        unset($_SESSION['usuario_id']);
        return null;
    }

    $usuario['id'] = (int) $usuario['id'];
    $_SESSION['ultimo_acceso'] = $ahora;
    return $usuario;
}

function cerrar_sesion(): void
{
    iniciar_sesion();
    $_SESSION = [];

    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $cookie['path'],
        'domain' => $cookie['domain'],
        'secure' => $cookie['secure'],
        'httponly' => $cookie['httponly'],
        'samesite' => $cookie['samesite'],
    ]);
    session_destroy();
    session_id('');
}

function cerrar_sesion_vencida(): void
{
    cerrar_sesion();
    iniciar_sesion();
    $_SESSION['aviso_expiracion'] = true;
}
