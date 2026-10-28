<?php

declare(strict_types=1);

require_once __DIR__ . '/../pedidos/calculos.php';

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
    return mostrar_decimal_cop($importe);
}

function consultar_ventas_mes(PDO $conexion, string $mes): array
{
    $limites = limites_mes_ventas($mes);
    if ($limites === null) {
        throw new InvalidArgumentException('Selecciona un mes válido.');
    }

    $consulta = $conexion->prepare(
        "SELECT p.id, p.consecutivo, p.estado, p.total, p.creado_en,
                c.razon_social AS cliente, u.id AS vendedor_id, u.nombre AS vendedor,
                SUM(p.total) OVER () AS total_mes
         FROM pedidos AS p
         INNER JOIN clientes AS c ON c.id = p.cliente_id
         INNER JOIN usuarios AS u ON u.id = p.usuario_id
         WHERE p.estado IN ('confirmado', 'despachado', 'entregado')
           AND p.creado_en >= :inicio_utc AND p.creado_en < :fin_utc
         ORDER BY p.creado_en DESC, p.id DESC"
    );
    $consulta->execute(['inicio_utc' => $limites[0], 'fin_utc' => $limites[1]]);
    $pedidos = $consulta->fetchAll();
    $vendedores = [];
    foreach ($pedidos as $pedido) {
        $id = (int) $pedido['vendedor_id'];
        if (!isset($vendedores[$id])) {
            $vendedores[$id] = ['nombre' => $pedido['vendedor'], 'cantidad' => 0, 'centavos' => 0];
        }
        $vendedores[$id]['cantidad']++;
        $vendedores[$id]['centavos'] += centavos_de_decimal($pedido['total']);
    }
    usort($vendedores, static fn (array $a, array $b): int => $b['centavos'] <=> $a['centavos']);

    return [
        'cantidad' => count($pedidos),
        'total' => $pedidos === [] ? '0.00' : $pedidos[0]['total_mes'],
        'pedidos' => $pedidos,
        'vendedores' => $vendedores,
        'inicio_utc' => $limites[0],
        'fin_utc' => $limites[1],
    ];
}

function consultar_cartera(PDO $conexion): array
{
    $consulta = $conexion->prepare(
        "SELECT c.id, c.nit, c.razon_social, c.cupo_credito, c.activo,
                COALESCE(ventas.facturado, 0.00) AS facturado,
                COALESCE(ventas.pagado, 0.00) AS pagado
         FROM clientes AS c
         LEFT JOIN (
             SELECT p.cliente_id, SUM(p.total) AS facturado,
                    SUM(COALESCE(abonos.pagado, 0.00)) AS pagado
             FROM pedidos AS p
             LEFT JOIN (
                 SELECT pedido_id, SUM(valor) AS pagado FROM pagos GROUP BY pedido_id
             ) AS abonos ON abonos.pedido_id = p.id
             WHERE p.estado IN ('confirmado', 'despachado', 'entregado')
             GROUP BY p.cliente_id
         ) AS ventas ON ventas.cliente_id = c.id
         ORDER BY c.razon_social, c.id"
    );
    $consulta->execute();
    $clientes = $consulta->fetchAll();
    foreach ($clientes as &$cliente) {
        $cupo = centavos_de_decimal($cliente['cupo_credito']);
        $facturado = centavos_de_decimal($cliente['facturado']);
        $pagado = centavos_de_decimal($cliente['pagado']);
        $saldo = max(0, $facturado - $pagado);
        $cliente['cupo_centavos'] = $cupo;
        $cliente['facturado_centavos'] = $facturado;
        $cliente['pagado_centavos'] = $pagado;
        $cliente['saldo_centavos'] = $saldo;
        $cliente['disponible_centavos'] = max(0, $cupo - $saldo);
        $cliente['cerca_limite'] = $cupo > 0 && $saldo * 10 >= $cupo * 9;
    }
    unset($cliente);
    return $clientes;
}

function consultar_productos_bajo_minimo(PDO $conexion): array
{
    $consulta = $conexion->prepare(
        'SELECT p.sku, p.nombre, c.nombre AS categoria, p.stock, p.stock_minimo,
                p.stock_minimo - p.stock AS faltante
         FROM productos AS p INNER JOIN categorias AS c ON c.id = p.categoria_id
         WHERE p.activo = 1 AND p.stock < p.stock_minimo
         ORDER BY faltante DESC, p.sku ASC'
    );
    $consulta->execute();
    return $consulta->fetchAll();
}
