<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/moneda.php';

function texto_producto(mixed $valor): string
{
    return is_string($valor) ? trim($valor) : '';
}

function normalizar_precio(string $valor): ?string
{
    $valor = str_replace(',', '.', trim($valor));

    if (!preg_match('/^([0-9]+)(?:\.([0-9]{1,2}))?$/D', $valor, $partes)) {
        return null;
    }

    $entero = ltrim($partes[1], '0');
    $entero = $entero === '' ? '0' : $entero;

    if (strlen($entero) > 12) {
        return null;
    }

    return $entero . '.' . str_pad($partes[2] ?? '0', 2, '0');
}

function normalizar_unidades(string $valor): ?string
{
    if (!preg_match('/^[0-9]+$/D', $valor)) {
        return null;
    }

    $numero = ltrim($valor, '0');
    $numero = $numero === '' ? '0' : $numero;

    if (strlen($numero) > 10 || (strlen($numero) === 10 && strcmp($numero, '4294967295') > 0)) {
        return null;
    }

    return $numero;
}

function validar_datos_producto(array $entrada, array $categorias, bool $esCreacion): array
{
    $datos = [
        'sku' => texto_producto($entrada['sku'] ?? null),
        'nombre' => texto_producto($entrada['nombre'] ?? null),
        'categoria_id' => texto_producto($entrada['categoria_id'] ?? null),
        'precio_lista' => texto_producto($entrada['precio_lista'] ?? null),
        'exento_iva' => texto_producto($entrada['exento_iva'] ?? null),
        'stock_minimo' => texto_producto($entrada['stock_minimo'] ?? null),
        'stock_inicial' => $esCreacion ? texto_producto($entrada['stock_inicial'] ?? null) : '0',
    ];
    $errores = [];

    foreach (['sku' => 20, 'nombre' => 150] as $campo => $limite) {
        $texto = $datos[$campo];
        if ($texto === '' || !mb_check_encoding($texto, 'UTF-8')
            || mb_strlen($texto, 'UTF-8') > $limite
            || preg_match('/[\x00-\x1F\x7F]/u', $texto)) {
            $errores[$campo] = ($campo === 'sku' ? 'El SKU' : 'El nombre')
                . ' es obligatorio y admite hasta ' . $limite . ' caracteres.';
        }
    }

    $idCategoria = $datos['categoria_id'];
    if (!ctype_digit($idCategoria) || (int) $idCategoria < 1
        || !in_array((int) $idCategoria, array_map('intval', array_column($categorias, 'id')), true)) {
        $errores['categoria_id'] = 'Selecciona una categoría disponible.';
    }

    $precio = normalizar_precio($datos['precio_lista']);
    if ($precio === null) {
        $errores['precio_lista'] = 'Ingresa un precio no negativo, sin separadores de miles y con máximo dos decimales.';
    } else {
        $datos['precio_lista'] = $precio;
    }

    if (!in_array($datos['exento_iva'], ['0', '1'], true)) {
        $errores['exento_iva'] = 'Indica si el producto está exento de IVA.';
    }

    foreach (['stock_minimo' => 'stock mínimo', 'stock_inicial' => 'stock inicial'] as $campo => $nombre) {
        if ($campo === 'stock_inicial' && !$esCreacion) {
            continue;
        }

        $unidades = normalizar_unidades($datos[$campo]);
        if ($unidades === null) {
            $errores[$campo] = 'El ' . $nombre . ' debe ser un número entero entre 0 y 4.294.967.295.';
        } else {
            $datos[$campo] = $unidades;
        }
    }

    return [$datos, $errores];
}

function mostrar_precio(string $valor): string
{
    return mostrar_decimal_cop($valor);
}

final class ReglaInventario extends RuntimeException
{
}

function registrar_entradas_stock(PDO $conexion, int $usuarioId, mixed $ids, mixed $cantidades): int
{
    if (!is_array($ids) || !is_array($cantidades) || count($ids) !== count($cantidades)
        || count($ids) > 30 || array_keys($ids) !== array_keys($cantidades)) {
        throw new ReglaInventario('Revisa la lista de productos y cantidades.', 422);
    }

    $entradas = [];
    foreach ($ids as $posicion => $idRecibido) {
        $cantidadRecibida = $cantidades[$posicion];
        if (!is_string($idRecibido) || !is_string($cantidadRecibida)) {
            throw new ReglaInventario('Selecciona un producto y una cantidad válida en cada fila.', 422);
        }
        $idRecibido = trim($idRecibido);
        $cantidadRecibida = trim($cantidadRecibida);
        if ($idRecibido === '' && $cantidadRecibida === '') continue;

        $id = filter_var($idRecibido, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $cantidad = normalizar_unidades($cantidadRecibida);
        if ($id === false || $cantidad === null || $cantidad === '0') {
            throw new ReglaInventario('Selecciona un producto y una cantidad entera mayor que cero en cada fila.', 422);
        }
        if (isset($entradas[$id])) {
            throw new ReglaInventario('Cada producto debe aparecer una sola vez en la entrada.', 422);
        }
        $entradas[$id] = (int) $cantidad;
    }

    if ($entradas === []) {
        throw new ReglaInventario('Agrega al menos un producto con una cantidad mayor que cero.', 422);
    }

    ksort($entradas, SORT_NUMERIC);
    $conexion->beginTransaction();
    try {
        $consultar = $conexion->prepare('SELECT stock, activo FROM productos WHERE id = :id FOR UPDATE');
        $actualizar = $conexion->prepare('UPDATE productos SET stock = stock + :cantidad WHERE id = :id');
        $registrar = $conexion->prepare(
            "INSERT INTO movimientos_inventario
             (producto_id, tipo, cantidad, pedido_id, usuario_id, creado_en)
             VALUES (:producto_id, 'entrada', :cantidad, NULL, :usuario_id, UTC_TIMESTAMP(6))"
        );

        foreach ($entradas as $id => $cantidad) {
            $consultar->execute(['id' => $id]);
            $producto = $consultar->fetch();
            if ($producto === false || (int) $producto['activo'] !== 1) {
                throw new ReglaInventario('Uno de los productos ya no está activo. Actualiza la lista.', 409);
            }
            if ((int) $producto['stock'] > 4294967295 - $cantidad) {
                throw new ReglaInventario('La entrada supera el máximo de existencias de uno de los productos.', 422);
            }

            $actualizar->execute(['cantidad' => $cantidad, 'id' => $id]);
            $registrar->execute([
                'producto_id' => $id,
                'cantidad' => $cantidad,
                'usuario_id' => $usuarioId,
            ]);
        }

        $conexion->commit();
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        throw $error;
    }

    return count($entradas);
}
