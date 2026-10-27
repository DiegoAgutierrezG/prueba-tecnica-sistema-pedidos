<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/vista.php';

$usuario = exigir_permiso('productos.administrar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    mostrar_error(405, 'Método no permitido', 'Cambia el estado desde la lista de productos.', $usuario);
}

$token = $_POST['token_csrf'] ?? null;
if (!csrf_valido(is_string($token) ? $token : null)) {
    mostrar_error(403, 'Formulario vencido', 'Recarga la lista e intenta el cambio de nuevo.', $usuario);
}

$idRecibido = $_POST['id'] ?? null;
$accion = $_POST['accion'] ?? null;

if (!is_string($idRecibido) || !ctype_digit($idRecibido) || (int) $idRecibido < 1
    || !in_array($accion, ['activar', 'desactivar'], true)) {
    mostrar_error(400, 'Solicitud inválida', 'No se pudo identificar el producto o la acción.', $usuario);
}

$id = (int) $idRecibido;
$activoNuevo = $accion === 'activar' ? 1 : 0;

try {
    $conexion = conectar_bd();
    $consulta = $conexion->prepare(
        'UPDATE productos SET activo = :activo_nuevo
         WHERE id = :id AND activo = :activo_anterior'
    );
    $consulta->execute([
        'activo_nuevo' => $activoNuevo,
        'id' => $id,
        'activo_anterior' => 1 - $activoNuevo,
    ]);

    if ($consulta->rowCount() === 0) {
        $comprobar = $conexion->prepare('SELECT id FROM productos WHERE id = :id LIMIT 1');
        $comprobar->execute(['id' => $id]);

        if ($comprobar->fetch() === false) {
            mostrar_error(404, 'Producto no encontrado', 'El producto solicitado no existe.', $usuario);
        }

        mostrar_error(409, 'Estado ya modificado', 'El estado de este producto cambió. Actualiza la lista antes de intentarlo de nuevo.', $usuario);
    }
} catch (Throwable $error) {
    error_log((string) $error);
    mostrar_error(503, 'Servicio no disponible', 'No se pudo cambiar el estado del producto.', $usuario);
}

$_SESSION['aviso_productos'] = $activoNuevo === 1
    ? 'Producto reactivado correctamente.'
    : 'Producto desactivado correctamente.';
header('Location: /productos/index.php' . ($activoNuevo === 1 ? '' : '?estado=inactivos'), true, 303);
exit;
