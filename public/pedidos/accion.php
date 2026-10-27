<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/vista.php';
require_once __DIR__ . '/funciones.php';

$permisos = [
    'confirmar' => 'pedidos.crear',
    'despachar' => 'pedidos.despachar',
    'entregar' => 'pedidos.despachar',
    'anular' => 'pedidos.anular',
];
$usuario = exigir_permiso('pedidos.ver');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    mostrar_error(405, 'Método no permitido', 'Cambia el estado desde el detalle del pedido.', $usuario);
}
$accion = $_POST['accion'] ?? null;
if (!is_string($accion) || !isset($permisos[$accion])) {
    mostrar_error(400, 'Acción inválida', 'La acción solicitada no existe.', $usuario);
}
$usuario = exigir_permiso($permisos[$accion]);

$token = $_POST['token_csrf'] ?? null;
if (!csrf_valido(is_string($token) ? $token : null)) {
    mostrar_error(403, 'Formulario vencido', 'Recarga el pedido e intenta la acción de nuevo.', $usuario);
}
$id = id_pedido_valido($_POST['id'] ?? null);
if ($id === null) mostrar_error(400, 'Pedido inválido', 'No se pudo identificar el pedido.', $usuario);

try {
    cambiar_estado_pedido(conectar_bd(), $id, $usuario['id'], $accion);
} catch (ReglaPedido $error) {
    mostrar_error(in_array($error->getCode(), [400, 404, 409, 422], true) ? $error->getCode() : 409,
        'No se pudo cambiar el pedido', $error->getMessage(), $usuario);
} catch (Throwable $error) {
    error_log((string) $error);
    mostrar_error(503, 'Servicio no disponible', 'No se pudo cambiar el estado del pedido.', $usuario);
}

$_SESSION['aviso_pedidos'] = match ($accion) {
    'confirmar' => 'Pedido confirmado; se descontó el stock y se registraron las salidas.',
    'despachar' => 'Pedido marcado como despachado.',
    'entregar' => 'Pedido marcado como entregado.',
    'anular' => 'Pedido anulado correctamente.',
};
header('Location: /pedidos/detalle.php?id=' . $id, true, 303);
exit;
