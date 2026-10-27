<?php

declare(strict_types=1);

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
    [$entero, $centavos] = explode('.', $valor);
    $agrupado = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $entero);
    return '$ ' . $agrupado . ',' . $centavos;
}
