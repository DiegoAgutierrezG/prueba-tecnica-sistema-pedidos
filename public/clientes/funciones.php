<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/moneda.php';

function texto_cliente(mixed $valor): string
{
    return is_string($valor) ? trim($valor) : '';
}

function digito_verificacion_nit(string $numero): int
{
    $factores = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];
    $suma = 0;

    foreach (array_reverse(str_split($numero)) as $posicion => $digito) {
        $suma += (int) $digito * $factores[$posicion];
    }

    $residuo = $suma % 11;
    return $residuo < 2 ? $residuo : 11 - $residuo;
}

function normalizar_cupo(string $valor): ?string
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

function validar_datos_cliente(array $entrada): array
{
    $datos = [
        'nit_numero' => texto_cliente($entrada['nit_numero'] ?? null),
        'nit_dv' => texto_cliente($entrada['nit_dv'] ?? null),
        'nit' => '',
        'razon_social' => texto_cliente($entrada['razon_social'] ?? null),
        'tipo' => texto_cliente($entrada['tipo'] ?? null),
        'cupo_credito' => texto_cliente($entrada['cupo_credito'] ?? null),
        'ciudad' => texto_cliente($entrada['ciudad'] ?? null),
    ];
    $errores = [];

    if (!preg_match('/^[1-9][0-9]{0,14}$/D', $datos['nit_numero'])
        || !preg_match('/^[0-9]$/D', $datos['nit_dv'])
        || digito_verificacion_nit($datos['nit_numero']) !== (int) $datos['nit_dv']) {
        $errores['nit'] = 'Revisa el número del NIT y su dígito de verificación.';
    } else {
        $datos['nit'] = $datos['nit_numero'] . '-' . $datos['nit_dv'];
    }

    foreach (['razon_social' => 150, 'ciudad' => 80] as $campo => $limite) {
        $texto = $datos[$campo];

        if ($texto === '' || !mb_check_encoding($texto, 'UTF-8')
            || mb_strlen($texto, 'UTF-8') > $limite
            || preg_match('/[\x00-\x1F\x7F]/u', $texto)) {
            $etiqueta = $campo === 'razon_social' ? 'La razón social' : 'La ciudad';
            $errores[$campo] = $etiqueta . ' es obligatoria y admite hasta ' . $limite . ' caracteres.';
        }
    }

    if (!in_array($datos['tipo'], ['minorista', 'mayorista'], true)) {
        $errores['tipo'] = 'Selecciona minorista o mayorista.';
    }

    $cupo = normalizar_cupo($datos['cupo_credito']);
    if ($cupo === null) {
        $errores['cupo_credito'] = 'Ingresa un cupo no negativo, sin separadores de miles y con máximo dos decimales.';
    } else {
        $datos['cupo_credito'] = $cupo;
    }

    return [$datos, $errores];
}

function mostrar_cupo(string $valor): string
{
    return mostrar_decimal_cop($valor);
}
