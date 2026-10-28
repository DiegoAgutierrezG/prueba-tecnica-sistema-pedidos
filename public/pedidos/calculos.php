<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/moneda.php';

const MAX_CENTAVOS_PEDIDO = 99999999999999;
const MAX_CANTIDAD_PEDIDO = 4294967295;

class ReglaPedido extends RuntimeException
{
}

final class StockInsuficientePedido extends ReglaPedido
{
}

function centavos_de_decimal(string $monto): int
{
    if (!preg_match('/^([0-9]{1,12})(?:\.([0-9]{1,2}))?$/D', $monto, $partes)) {
        throw new ReglaPedido('No se pudo interpretar un importe del pedido. Solicita al administrador revisar sus datos antes de continuar.', 409);
    }

    return (int) $partes[1] * 100 + (int) str_pad($partes[2] ?? '0', 2, '0');
}

function decimal_de_centavos(int $centavos): string
{
    return intdiv($centavos, 100) . '.' . str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
}

function mostrar_centavos_pedido(int $centavos): string
{
    return mostrar_centavos_cop($centavos);
}

function mostrar_decimal_pedido(string $monto): string
{
    return mostrar_centavos_pedido(centavos_de_decimal($monto));
}

function fecha_pedido_bogota(string $fechaUtc): string
{
    $fecha = new DateTimeImmutable($fechaUtc, new DateTimeZone('UTC'));
    return $fecha->setTimezone(new DateTimeZone('America/Bogota'))->format('d/m/Y H:i');
}

function descuento_de_linea(string $tipoCliente, int $cantidad): int
{
    return max($tipoCliente === 'mayorista' ? 8 : 0, $cantidad >= 10 ? 5 : 0);
}

function calcular_importes_linea(int $precioCentavos, int $cantidad, int $descuentoPorcentaje, int $ivaPorcentaje): array
{
    if ($precioCentavos < 0 || $precioCentavos > MAX_CENTAVOS_PEDIDO
        || $cantidad < 1 || $cantidad > MAX_CANTIDAD_PEDIDO
        || !in_array($descuentoPorcentaje, [0, 5, 8], true)
        || !in_array($ivaPorcentaje, [0, 19], true)) {
        throw new ReglaPedido('No se pudo calcular una línea del pedido. Revisa el producto y la cantidad antes de continuar.', 422);
    }

    if ($precioCentavos > 0 && $cantidad > intdiv(MAX_CENTAVOS_PEDIDO, $precioCentavos)) {
        throw new ReglaPedido('El valor de una línea supera el máximo permitido para un pedido. Reduce su cantidad.', 422);
    }

    $subtotal = $precioCentavos * $cantidad;
    $descuento = intdiv($subtotal * $descuentoPorcentaje + 50, 100);
    $base = $subtotal - $descuento;
    $iva = intdiv($base * $ivaPorcentaje + 50, 100);
    $totalLinea = $base + $iva;

    if ($totalLinea > MAX_CENTAVOS_PEDIDO) {
        throw new ReglaPedido('El valor de una línea supera el máximo permitido para un pedido. Reduce su cantidad.', 422);
    }

    return [
        'subtotal' => $subtotal,
        'descuento' => $descuento,
        'iva' => $iva,
        'total_linea' => $totalLinea,
    ];
}

function calcular_totales_pedido(array $lineas): array
{
    $subtotal = 0;
    $descuento = 0;
    $iva = 0;

    foreach ($lineas as $linea) {
        $subtotal += $linea['subtotal'];
        $descuento += $linea['descuento'];
        $iva += $linea['iva'];

        if ($subtotal > MAX_CENTAVOS_PEDIDO || $descuento > MAX_CENTAVOS_PEDIDO
            || $iva > MAX_CENTAVOS_PEDIDO) {
            throw new ReglaPedido('Los importes del pedido superan el máximo permitido. Reduce las cantidades y vuelve a calcular.', 422);
        }
    }

    $sinRedondeo = $subtotal - $descuento + $iva;
    if ($sinRedondeo > MAX_CENTAVOS_PEDIDO) {
        throw new ReglaPedido('El total del pedido supera el máximo permitido. Reduce las cantidades y vuelve a calcular.', 422);
    }

    $total = intdiv($sinRedondeo + 4999, 5000) * 5000;
    if ($total > MAX_CENTAVOS_PEDIDO) {
        throw new ReglaPedido('El total redondeado supera el máximo permitido. Reduce las cantidades y vuelve a calcular.', 422);
    }

    return [
        'subtotal' => $subtotal,
        'descuento' => $descuento,
        'iva' => $iva,
        'ajuste' => $total - $sinRedondeo,
        'total' => $total,
    ];
}
