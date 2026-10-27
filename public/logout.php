<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/vista.php';

$usuario = exigir_sesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    mostrar_error(405, 'Método no permitido', 'Cierra la sesión desde el botón del menú.', $usuario);
}

$token = $_POST['token_csrf'] ?? null;

if (!csrf_valido(is_string($token) ? $token : null)) {
    mostrar_error(403, 'Formulario vencido', 'Recarga la página e intenta cerrar sesión de nuevo.', $usuario);
}

cerrar_sesion();
iniciar_sesion();
$_SESSION['aviso_cierre'] = true;
header('Location: /login.php', true, 303);
exit;
