<?php

declare(strict_types=1);

require_once '/var/www/html/pedidos/calculos.php';

function comprobar(bool $condicion, string $caso): void
{
    if (!$condicion) {
        throw new RuntimeException('Falló: ' . $caso);
    }
}

comprobar(descuento_de_linea('minorista', 9) === 0, 'minorista con menos de 10 unidades');
comprobar(descuento_de_linea('minorista', 10) === 5, 'descuento por volumen');
comprobar(descuento_de_linea('mayorista', 10) === 8, 'se aplica el mayor descuento, sin sumarlos');

$linea = calcular_importes_linea(10000, 10, 5, 19);
comprobar($linea === [
    'subtotal' => 100000,
    'descuento' => 5000,
    'iva' => 18050,
    'total_linea' => 113050,
], 'descuento antes de IVA');

$exenta = calcular_importes_linea(5001, 1, 0, 0);
comprobar($exenta['iva'] === 0, 'producto exento de IVA');
comprobar(calcular_totales_pedido([$exenta])['total'] === 10000, 'redondeo hacia arriba a múltiplo de $ 50');
comprobar(calcular_totales_pedido([calcular_importes_linea(5000, 1, 0, 0)])['total'] === 5000,
    'importe exacto sin ajuste');
comprobar(calcular_totales_pedido([calcular_importes_linea(23400, 1, 0, 0)])['total'] === 25000,
    '$ 234 se ajustan a $ 250');
comprobar(calcular_totales_pedido([calcular_importes_linea(15245600, 1, 0, 0)])['total'] === 15250000,
    '$ 152.456 se ajustan a $ 152.500');
comprobar(mostrar_centavos_pedido(374465000) === '$ 3.744.650', 'COP sin decimales');
comprobar(mostrar_centavos_pedido(123456) === '$ 1.234 y 56 centavos', 'centavos visibles sin decimal');
comprobar(mostrar_decimal_cop('0.01') === '$ 0 y 1 centavo', 'un centavo exacto');
comprobar(mostrar_decimal_cop('123456789012345.67') === '$ 123.456.789.012.345 y 67 centavos',
    'importe grande sin pérdida de precisión');

$totales = calcular_totales_pedido([$linea, $exenta]);
comprobar($totales['subtotal'] === 105001 && $totales['descuento'] === 5000
    && $totales['iva'] === 18050 && $totales['total'] === 120000,
    'suma de líneas y redondeo final');

try {
    calcular_importes_linea(-1, 1, 0, 19);
    throw new RuntimeException('Se aceptó un precio negativo.');
} catch (ReglaPedido $error) {
    comprobar($error->getCode() === 422, 'rechazo de precio inválido');
}

echo "Cálculos de totales: OK\n";
