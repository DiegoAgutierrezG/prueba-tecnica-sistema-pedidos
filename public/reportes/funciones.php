<?php

declare(strict_types=1);

function limites_mes_ventas(string $mes): ?array
{
    if (!preg_match('/^([0-9]{4})-(0[1-9]|1[0-2])$/D', $mes, $partes)
        || (int) $partes[1] < 1000 || (int) $partes[1] > 9998) {
        return null;
    }

    $zonaBogota = new DateTimeZone('America/Bogota');
    $zonaUtc = new DateTimeZone('UTC');
    $inicio = new DateTimeImmutable($mes . '-01 00:00:00', $zonaBogota);
    $siguienteMes = $inicio->modify('first day of next month');

    return [
        $inicio->setTimezone($zonaUtc)->format('Y-m-d H:i:s.u'),
        $siguienteMes->setTimezone($zonaUtc)->format('Y-m-d H:i:s.u'),
    ];
}

function titulo_mes_ventas(string $mes): string
{
    $nombres = [
        '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
        '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
        '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
    ];
    return $nombres[substr($mes, 5, 2)] . ' de ' . substr($mes, 0, 4);
}

function mostrar_importe_ventas(string $importe): string
{
    if (!preg_match('/^([0-9]+)(?:\.([0-9]{1,2}))?$/D', $importe, $partes)) {
        throw new UnexpectedValueException('El importe del reporte no tiene un formato válido.');
    }

    $pesos = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $partes[1]);
    return '$ ' . $pesos . ',' . str_pad($partes[2] ?? '0', 2, '0');
}

function consultar_ventas_mes(PDO $conexion, string $mes): array
{
    $limites = limites_mes_ventas($mes);
    if ($limites === null) {
        throw new InvalidArgumentException('Selecciona un mes válido.');
    }

    $consulta = $conexion->prepare(
        "SELECT p.id, p.consecutivo, p.estado, p.total, p.creado_en,
                c.razon_social AS cliente, SUM(p.total) OVER () AS total_mes
         FROM pedidos AS p
         INNER JOIN clientes AS c ON c.id = p.cliente_id
         WHERE p.estado IN ('confirmado', 'despachado', 'entregado')
           AND p.creado_en >= :inicio_utc AND p.creado_en < :fin_utc
         ORDER BY p.creado_en DESC, p.id DESC"
    );
    $consulta->execute(['inicio_utc' => $limites[0], 'fin_utc' => $limites[1]]);
    $pedidos = $consulta->fetchAll();

    return [
        'cantidad' => count($pedidos),
        'total' => $pedidos === [] ? '0.00' : $pedidos[0]['total_mes'],
        'pedidos' => $pedidos,
        'inicio_utc' => $limites[0],
        'fin_utc' => $limites[1],
    ];
}
