<?php

declare(strict_types=1);

function mostrar_decimal_cop(string $importe): string
{
    if (!preg_match('/^([0-9]+)(?:\.([0-9]{1,2}))?$/D', $importe, $partes)) {
        throw new UnexpectedValueException('El importe no tiene un formato válido.');
    }

    $pesos = ltrim($partes[1], '0');
    $pesos = $pesos === '' ? '0' : $pesos;
    $centavos = (int) str_pad($partes[2] ?? '0', 2, '0');
    $formato = '$ ' . preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $pesos);

    if ($centavos === 0) {
        return $formato;
    }
    return $formato . ' y ' . $centavos . ($centavos === 1 ? ' centavo' : ' centavos');
}

function mostrar_centavos_cop(int $centavos): string
{
    if ($centavos < 0) {
        throw new UnexpectedValueException('El importe no puede ser negativo.');
    }

    return mostrar_decimal_cop(intdiv($centavos, 100) . '.'
        . str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT));
}
