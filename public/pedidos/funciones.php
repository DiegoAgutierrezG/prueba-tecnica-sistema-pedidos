<?php

declare(strict_types=1);

require_once __DIR__ . '/calculos.php';

function id_pedido_valido(mixed $valor): ?int
{
    return is_string($valor) && ctype_digit($valor) && (int) $valor > 0 ? (int) $valor : null;
}

function lineas_recibidas(array $entrada): array
{
    $ids = $entrada['producto_id'] ?? null;
    $cantidades = $entrada['cantidad'] ?? null;
    if (!is_array($ids) || !is_array($cantidades) || count($ids) !== count($cantidades)
        || count($ids) > 30 || count($ids) === 0) {
        throw new ReglaPedido('Agrega entre 1 y 30 líneas de producto.', 422);
    }

    $lineas = [];
    foreach ($ids as $posicion => $idRecibido) {
        $cantidadRecibida = $cantidades[$posicion] ?? null;
        if (!is_string($idRecibido) || !is_string($cantidadRecibida)) {
            throw new ReglaPedido('Revisa los productos y cantidades del pedido.', 422);
        }
        $idRecibido = trim($idRecibido);
        $cantidadRecibida = trim($cantidadRecibida);
        if ($idRecibido === '' && $cantidadRecibida === '') {
            continue;
        }
        if (!ctype_digit($idRecibido) || (int) $idRecibido < 1
            || !ctype_digit($cantidadRecibida) || (int) $cantidadRecibida < 1
            || strlen($cantidadRecibida) > 10 || (int) $cantidadRecibida > MAX_CANTIDAD_PEDIDO) {
            throw new ReglaPedido('Selecciona un producto y escribe una cantidad entera mayor que cero en cada línea.', 422);
        }
        $id = (int) $idRecibido;
        if (isset($lineas[$id])) {
            throw new ReglaPedido('Cada producto puede aparecer una sola vez en el pedido.', 422);
        }
        $lineas[$id] = (int) $cantidadRecibida;
    }

    if ($lineas === []) {
        throw new ReglaPedido('Agrega al menos un producto al pedido.', 422);
    }
    ksort($lineas, SORT_NUMERIC);
    return $lineas;
}

function cliente_para_pedido(PDO $conexion, int $id, bool $exigirActivo = true): array
{
    $consulta = $conexion->prepare('SELECT id, razon_social, tipo, cupo_credito, activo FROM clientes WHERE id = :id FOR UPDATE');
    $consulta->execute(['id' => $id]);
    $cliente = $consulta->fetch();
    if ($cliente === false || ($exigirActivo && (int) $cliente['activo'] !== 1)) {
        throw new ReglaPedido('El cliente ya no está disponible para pedidos.', 422);
    }
    return $cliente;
}

function productos_para_pedido(PDO $conexion, array $ids): array
{
    $productos = [];
    $consulta = $conexion->prepare(
        'SELECT id, sku, nombre, precio_lista, exento_iva, stock, activo FROM productos WHERE id = :id FOR UPDATE'
    );
    sort($ids, SORT_NUMERIC);
    foreach ($ids as $id) {
        $consulta->execute(['id' => $id]);
        $producto = $consulta->fetch();
        if ($producto === false || (int) $producto['activo'] !== 1) {
            throw new ReglaPedido('Uno de los productos seleccionados ya no está activo.', 422);
        }
        $productos[$id] = $producto;
    }
    return $productos;
}

function siguiente_consecutivo(PDO $conexion, int $anio): string
{
    $reservar = $conexion->prepare(
        "INSERT INTO secuencias (prefijo, anio, ultimo) VALUES ('PED', :anio, 0)
         ON DUPLICATE KEY UPDATE ultimo = ultimo"
    );
    $reservar->execute(['anio' => $anio]);
    $consulta = $conexion->prepare("SELECT ultimo FROM secuencias WHERE prefijo = 'PED' AND anio = :anio FOR UPDATE");
    $consulta->execute(['anio' => $anio]);
    $ultimo = (int) $consulta->fetchColumn();
    if ($ultimo >= 999999) {
        throw new ReglaPedido('Se agotaron los consecutivos disponibles para este año.', 409);
    }
    $numero = $ultimo + 1;
    $actualizar = $conexion->prepare("UPDATE secuencias SET ultimo = :numero WHERE prefijo = 'PED' AND anio = :anio");
    $actualizar->execute(['numero' => $numero, 'anio' => $anio]);
    return sprintf('PED-%04d-%06d', $anio, $numero);
}

function crear_pedido(PDO $conexion, int $usuarioId, array $entrada): int
{
    $clienteId = id_pedido_valido($entrada['cliente_id'] ?? null);
    $orden = $entrada['orden_compra'] ?? '';
    if ($clienteId === null || !is_string($orden)) {
        throw new ReglaPedido('Selecciona un cliente válido.', 422);
    }
    $orden = trim($orden);
    if (!mb_check_encoding($orden, 'UTF-8') || mb_strlen($orden, 'UTF-8') > 40
        || preg_match('/[\x00-\x1F\x7F]/u', $orden)) {
        throw new ReglaPedido('La orden de compra admite hasta 40 caracteres.', 422);
    }
    $cantidades = lineas_recibidas($entrada);

    $conexion->beginTransaction();
    try {
        $cliente = cliente_para_pedido($conexion, $clienteId);
        $productos = productos_para_pedido($conexion, array_keys($cantidades));
        $lineas = [];
        foreach ($cantidades as $productoId => $cantidad) {
            $producto = $productos[$productoId];
            $descuento = descuento_de_linea($cliente['tipo'], $cantidad);
            $iva = (int) $producto['exento_iva'] === 1 ? 0 : 19;
            $importes = calcular_importes_linea(
                centavos_de_decimal($producto['precio_lista']), $cantidad, $descuento, $iva
            );
            $lineas[] = ['producto_id' => $productoId, 'cantidad' => $cantidad,
                'precio_unitario' => $producto['precio_lista'], 'descuento_porcentaje' => $descuento,
                'iva_porcentaje' => $iva] + $importes;
        }
        $totales = calcular_totales_pedido($lineas);
        $anio = (int) (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))->format('Y');
        $consecutivo = siguiente_consecutivo($conexion, $anio);

        $insertar = $conexion->prepare(
            'INSERT INTO pedidos (consecutivo, cliente_id, usuario_id, estado, orden_compra,
             subtotal, descuento, iva, total, creado_en)
             VALUES (:consecutivo, :cliente_id, :usuario_id, \'borrador\', :orden_compra,
             :subtotal, :descuento, :iva, :total, UTC_TIMESTAMP(6))'
        );
        $insertar->execute([
            'consecutivo' => $consecutivo, 'cliente_id' => $clienteId, 'usuario_id' => $usuarioId,
            'orden_compra' => $orden === '' ? null : $orden,
            'subtotal' => decimal_de_centavos($totales['subtotal']),
            'descuento' => decimal_de_centavos($totales['descuento']),
            'iva' => decimal_de_centavos($totales['iva']),
            'total' => decimal_de_centavos($totales['total']),
        ]);
        $pedidoId = (int) $conexion->lastInsertId();
        $insertarLinea = $conexion->prepare(
            'INSERT INTO pedido_detalle
             (pedido_id, producto_id, cantidad, precio_unitario, descuento_porcentaje, iva_porcentaje)
             VALUES (:pedido_id, :producto_id, :cantidad, :precio_unitario, :descuento, :iva)'
        );
        foreach ($lineas as $linea) {
            $insertarLinea->execute([
                'pedido_id' => $pedidoId, 'producto_id' => $linea['producto_id'],
                'cantidad' => $linea['cantidad'], 'precio_unitario' => $linea['precio_unitario'],
                'descuento' => $linea['descuento_porcentaje'], 'iva' => $linea['iva_porcentaje'],
            ]);
        }
        $conexion->commit();
        return $pedidoId;
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        throw $error;
    }
}

function saldo_cliente_centavos(PDO $conexion, int $clienteId): int
{
    $consulta = $conexion->prepare(
        "SELECT COALESCE(SUM(total), 0.00) FROM pedidos
         WHERE cliente_id = :id AND estado IN ('confirmado', 'despachado', 'entregado')"
    );
    $consulta->execute(['id' => $clienteId]);
    $facturado = centavos_de_decimal((string) $consulta->fetchColumn());
    $consulta = $conexion->prepare(
        "SELECT COALESCE(SUM(pg.valor), 0.00) FROM pagos AS pg
         INNER JOIN pedidos AS p ON p.id = pg.pedido_id
         WHERE p.cliente_id = :id AND p.estado IN ('confirmado', 'despachado', 'entregado')"
    );
    $consulta->execute(['id' => $clienteId]);
    $pagado = centavos_de_decimal((string) $consulta->fetchColumn());
    return max(0, $facturado - $pagado);
}

function detalle_lineas_pedido(PDO $conexion, int $pedidoId): array
{
    $consulta = $conexion->prepare(
        'SELECT d.producto_id, d.cantidad, d.precio_unitario, d.descuento_porcentaje,
                d.iva_porcentaje, p.sku, p.nombre, p.stock, p.activo
         FROM pedido_detalle AS d INNER JOIN productos AS p ON p.id = d.producto_id
         WHERE d.pedido_id = :id ORDER BY d.id'
    );
    $consulta->execute(['id' => $pedidoId]);
    $lineas = $consulta->fetchAll();
    foreach ($lineas as &$linea) {
        $linea += calcular_importes_linea(
            centavos_de_decimal($linea['precio_unitario']), (int) $linea['cantidad'],
            (int) $linea['descuento_porcentaje'], (int) $linea['iva_porcentaje']
        );
    }
    unset($linea);
    return $lineas;
}

function cambiar_estado_pedido(PDO $conexion, int $pedidoId, int $usuarioId, string $accion): void
{
    $transiciones = [
        'confirmar' => ['desde' => ['borrador'], 'hasta' => 'confirmado'],
        'despachar' => ['desde' => ['confirmado'], 'hasta' => 'despachado'],
        'entregar' => ['desde' => ['despachado'], 'hasta' => 'entregado'],
        'anular' => ['desde' => ['borrador', 'confirmado'], 'hasta' => 'anulado'],
    ];
    if (!isset($transiciones[$accion])) {
        throw new ReglaPedido('La acción solicitada no existe.', 400);
    }

    $conexion->beginTransaction();
    try {
        $consulta = $conexion->prepare('SELECT id, cliente_id, estado, total FROM pedidos WHERE id = :id FOR UPDATE');
        $consulta->execute(['id' => $pedidoId]);
        $pedido = $consulta->fetch();
        if ($pedido === false) throw new ReglaPedido('El pedido solicitado no existe.', 404);
        $estadoAnterior = $pedido['estado'];
        if (!in_array($estadoAnterior, $transiciones[$accion]['desde'], true)) {
            throw new ReglaPedido('El estado actual no permite esta acción. Actualiza el pedido.', 409);
        }

        if ($accion === 'confirmar') {
            $cliente = cliente_para_pedido($conexion, (int) $pedido['cliente_id']);
            $saldo = saldo_cliente_centavos($conexion, (int) $pedido['cliente_id']);
            $total = centavos_de_decimal($pedido['total']);
            if ($total > centavos_de_decimal($cliente['cupo_credito']) - $saldo) {
                throw new ReglaPedido('El saldo pendiente más este pedido supera el cupo de crédito del cliente.', 409);
            }
            $lineas = detalle_lineas_pedido($conexion, $pedidoId);
            if ($lineas === []) throw new ReglaPedido('El pedido no tiene productos.', 409);
            $productos = productos_para_pedido($conexion, array_column($lineas, 'producto_id'));
            foreach ($lineas as $linea) {
                if ((int) $productos[(int) $linea['producto_id']]['stock'] < (int) $linea['cantidad']) {
                    throw new ReglaPedido('No hay stock suficiente de ' . $linea['sku'] . ' (' . $linea['nombre'] . ').', 409);
                }
            }
        } elseif ($accion === 'anular' && $estadoAnterior === 'confirmado') {
            $lineas = detalle_lineas_pedido($conexion, $pedidoId);
            $ids = array_column($lineas, 'producto_id');
            sort($ids, SORT_NUMERIC);
            $bloquear = $conexion->prepare('SELECT id FROM productos WHERE id = :id FOR UPDATE');
            foreach ($ids as $id) {
                $bloquear->execute(['id' => $id]);
                if ($bloquear->fetch() === false) throw new ReglaPedido('Falta un producto del pedido.', 409);
            }
        }

        if ($accion === 'confirmar' || ($accion === 'anular' && $estadoAnterior === 'confirmado')) {
            $actualizarStock = $conexion->prepare(
                $accion === 'confirmar'
                    ? 'UPDATE productos SET stock = stock - :cantidad WHERE id = :id AND activo = 1 AND stock >= :disponible'
                    : 'UPDATE productos SET stock = stock + :cantidad WHERE id = :id AND stock <= :maximo'
            );
            $insertarMovimiento = $conexion->prepare(
                'INSERT INTO movimientos_inventario (producto_id, tipo, cantidad, pedido_id, usuario_id, creado_en)
                 VALUES (:producto_id, :tipo, :cantidad, :pedido_id, :usuario_id, UTC_TIMESTAMP(6))'
            );
            foreach ($lineas as $linea) {
                $cantidad = (int) $linea['cantidad'];
                $parametrosStock = ['id' => $linea['producto_id'], 'cantidad' => $cantidad];
                if ($accion === 'confirmar') $parametrosStock['disponible'] = $cantidad;
                if ($accion === 'anular') $parametrosStock['maximo'] = MAX_CANTIDAD_PEDIDO - $cantidad;
                $actualizarStock->execute($parametrosStock);
                if ($actualizarStock->rowCount() !== 1) {
                    throw new ReglaPedido('No se pudo actualizar el stock de ' . $linea['sku'] . '. Actualiza el pedido.', 409);
                }
                $insertarMovimiento->execute([
                    'producto_id' => $linea['producto_id'], 'tipo' => $accion === 'confirmar' ? 'salida' : 'reversion',
                    'cantidad' => $cantidad, 'pedido_id' => $pedidoId, 'usuario_id' => $usuarioId,
                ]);
            }
        }

        $sqlEstado = $accion === 'anular'
            ? 'UPDATE pedidos SET estado = :estado, anulado_en = UTC_TIMESTAMP(6) WHERE id = :id AND estado = :anterior'
            : 'UPDATE pedidos SET estado = :estado WHERE id = :id AND estado = :anterior';
        $actualizar = $conexion->prepare($sqlEstado);
        $actualizar->execute(['estado' => $transiciones[$accion]['hasta'], 'id' => $pedidoId, 'anterior' => $estadoAnterior]);
        if ($actualizar->rowCount() !== 1) throw new ReglaPedido('El estado del pedido cambió. Actualiza la página.', 409);
        $conexion->commit();
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        throw $error;
    }
}
