<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/permisos.php';

$usuario = exigir_permiso('productos.administrar');
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$termino = $_GET['q'] ?? null;
if (!is_string($termino) || !mb_check_encoding($termino, 'UTF-8')) {
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

$excluidosRecibidos = $_GET['excluir'] ?? [];
if (!is_array($excluidosRecibidos) || count($excluidosRecibidos) > 30) {
    http_response_code(422);
    echo json_encode(['error' => 'La lista de productos excluidos no es válida.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$excluidos = [];
foreach ($excluidosRecibidos as $idRecibido) {
    if (!is_string($idRecibido)
        || filter_var($idRecibido, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
        http_response_code(422);
        echo json_encode(['error' => 'La lista de productos excluidos no es válida.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $excluidos[(int) $idRecibido] = true;
}

$patron = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $termino) . '%';
$parametros = ['sku' => $patron, 'nombre' => $patron];
$filtroExcluidos = '';
if ($excluidos !== []) {
    $marcadores = [];
    foreach (array_keys($excluidos) as $posicion => $id) {
        $nombre = 'excluido_' . $posicion;
        $marcadores[] = ':' . $nombre;
        $parametros[$nombre] = $id;
    }
    $filtroExcluidos = ' AND id NOT IN (' . implode(', ', $marcadores) . ')';
}

try {
    $consulta = conectar_bd()->prepare(
        "SELECT id, sku, nombre, stock FROM productos
         WHERE activo = 1 AND (sku LIKE :sku ESCAPE '!' OR nombre LIKE :nombre ESCAPE '!')
         $filtroExcluidos
         ORDER BY sku, id LIMIT 8"
    );
    $consulta->execute($parametros);
    $resultados = array_map(static fn (array $producto): array => [
        'id' => (string) $producto['id'],
        'nombre' => $producto['sku'] . ' · ' . $producto['nombre'],
        'detalle' => 'Stock actual: ' . $producto['stock'],
        'stock' => (int) $producto['stock'],
    ], $consulta->fetchAll());

    echo json_encode(['resultados' => $resultados], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    error_log((string) $error);
    http_response_code(503);
    echo json_encode(['error' => 'No se pudieron consultar los productos.'], JSON_UNESCAPED_UNICODE);
}
