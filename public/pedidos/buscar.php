<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/calculos.php';

$usuario = exigir_permiso('pedidos.crear');
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tipo = $_GET['tipo'] ?? null;
$termino = $_GET['q'] ?? null;
if (!is_string($tipo) || !in_array($tipo, ['cliente', 'producto'], true)
    || !is_string($termino) || !mb_check_encoding($termino, 'UTF-8')) {
    http_response_code(400);
    echo json_encode(['error' => 'Búsqueda inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$termino = trim($termino);
if (mb_strlen($termino, 'UTF-8') > 100) {
    http_response_code(422);
    echo json_encode(['error' => 'La búsqueda admite hasta 100 caracteres.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($termino === '') {
    echo '{"resultados":[]}';
    exit;
}

$patron = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $termino) . '%';

try {
    $conexion = conectar_bd();
    if ($tipo === 'cliente') {
        $consulta = $conexion->prepare(
            "SELECT id, razon_social, nit, tipo FROM clientes
             WHERE activo = 1 AND (razon_social LIKE :nombre ESCAPE '!' OR nit LIKE :nit ESCAPE '!')
             ORDER BY razon_social, id LIMIT 8"
        );
        $consulta->execute(['nombre' => $patron, 'nit' => $patron]);
        $resultados = array_map(static fn (array $cliente): array => [
            'id' => (string) $cliente['id'],
            'nombre' => $cliente['razon_social'] . ' · ' . $cliente['nit'],
            'detalle' => ucfirst($cliente['tipo']),
        ], $consulta->fetchAll());
    } else {
        $consulta = $conexion->prepare(
            "SELECT id, sku, nombre, precio_lista, stock FROM productos
             WHERE activo = 1 AND (sku LIKE :sku ESCAPE '!' OR nombre LIKE :nombre ESCAPE '!')
             ORDER BY sku, id LIMIT 8"
        );
        $consulta->execute(['sku' => $patron, 'nombre' => $patron]);
        $resultados = array_map(static fn (array $producto): array => [
            'id' => (string) $producto['id'],
            'nombre' => $producto['sku'] . ' · ' . $producto['nombre'],
            'detalle' => mostrar_decimal_pedido($producto['precio_lista']) . ' · stock ' . $producto['stock'],
            'stock' => (int) $producto['stock'],
        ], $consulta->fetchAll());
    }

    echo json_encode(['resultados' => $resultados], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    error_log((string) $error);
    http_response_code(503);
    echo json_encode(['error' => 'No se pudieron cargar los resultados.'], JSON_UNESCAPED_UNICODE);
}
