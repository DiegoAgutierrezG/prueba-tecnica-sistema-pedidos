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
            throw new ReglaPedido('El pedido tiene un producto repetido. Deja una sola línea por producto y ajusta su cantidad.', 422);
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
        throw new ReglaPedido('El cliente ya no está disponible para pedidos. Selecciona un cliente activo.', 422);
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
            throw new ReglaPedido('Uno de los productos seleccionados ya no está activo. Quítalo o sustitúyelo por uno activo.', 422);
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
        throw new ReglaPedido('Se agotaron los consecutivos disponibles para este año. Solicita al administrador revisar la numeración antes de crear otro pedido.', 409);
    }
    $numero = $ultimo + 1;
    $actualizar = $conexion->prepare("UPDATE secuencias SET ultimo = :numero WHERE prefijo = 'PED' AND anio = :anio");
    $actualizar->execute(['numero' => $numero, 'anio' => $anio]);
    return sprintf('PED-%04d-%06d', $anio, $numero);
}

function validar_entrada_borrador(array $entrada): array
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

    return [$clienteId, $orden, $cantidades];
}

function calcular_lineas_borrador(
    PDO $conexion, array $cliente, array $cantidades, array $lineasGuardadas = [], bool $cambioCliente = false
): array
{
    $productos = productos_para_pedido($conexion, array_keys($cantidades));
    $lineas = [];
    foreach ($cantidades as $productoId => $cantidad) {
        $producto = $productos[$productoId];
        $guardada = $lineasGuardadas[$productoId] ?? null;
        $precio = $guardada === null ? $producto['precio_lista'] : $guardada['precio_unitario'];
        $iva = $guardada === null
            ? ((int) $producto['exento_iva'] === 1 ? 0 : 19)
            : (int) $guardada['iva_porcentaje'];
        $descuento = $guardada !== null && !$cambioCliente && (int) $guardada['cantidad'] === $cantidad
            ? (int) $guardada['descuento_porcentaje']
            : descuento_de_linea($cliente['tipo'], $cantidad);
        $importes = calcular_importes_linea(
            centavos_de_decimal($precio), $cantidad, $descuento, $iva
        );
        $lineas[] = ['producto_id' => $productoId, 'cantidad' => $cantidad,
            'precio_unitario' => $precio, 'descuento_porcentaje' => $descuento,
            'iva_porcentaje' => $iva] + $importes;
    }
    return $lineas;
}

function lineas_guardadas_borrador(PDO $conexion, int $pedidoId): array
{
    $consulta = $conexion->prepare(
        'SELECT id, producto_id, cantidad, precio_unitario, descuento_porcentaje, iva_porcentaje
         FROM pedido_detalle WHERE pedido_id = :id ORDER BY id FOR UPDATE'
    );
    $consulta->execute(['id' => $pedidoId]);
    $lineas = [];
    foreach ($consulta->fetchAll() as $linea) {
        $lineas[(int) $linea['producto_id']] = $linea;
    }
    return $lineas;
}

function actualizar_lineas_borrador(PDO $conexion, int $pedidoId, array $lineas, array $guardadas): void
{
    $actualizar = $conexion->prepare(
        'UPDATE pedido_detalle SET cantidad = :cantidad, descuento_porcentaje = :descuento
         WHERE id = :id AND pedido_id = :pedido_id'
    );
    $nuevas = [];
    foreach ($lineas as $linea) {
        $anterior = $guardadas[$linea['producto_id']] ?? null;
        if ($anterior === null) {
            $nuevas[] = $linea;
            continue;
        }
        if ((int) $anterior['cantidad'] !== $linea['cantidad']
            || (int) $anterior['descuento_porcentaje'] !== $linea['descuento_porcentaje']) {
            $actualizar->execute([
                'cantidad' => $linea['cantidad'], 'descuento' => $linea['descuento_porcentaje'],
                'id' => $anterior['id'], 'pedido_id' => $pedidoId,
            ]);
        }
        unset($guardadas[$linea['producto_id']]);
    }

    $borrar = $conexion->prepare('DELETE FROM pedido_detalle WHERE id = :id AND pedido_id = :pedido_id');
    foreach ($guardadas as $linea) {
        $borrar->execute(['id' => $linea['id'], 'pedido_id' => $pedidoId]);
    }
    guardar_lineas_borrador($conexion, $pedidoId, $nuevas);
}

function guardar_lineas_borrador(PDO $conexion, int $pedidoId, array $lineas): void
{
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
}

function version_borrador_pedido(PDO $conexion, int $pedidoId, array $pedido): string
{
    $consulta = $conexion->prepare(
        'SELECT id, producto_id, cantidad, precio_unitario, descuento_porcentaje, iva_porcentaje
         FROM pedido_detalle WHERE pedido_id = :id ORDER BY id'
    );
    $consulta->execute(['id' => $pedidoId]);
    return hash('sha256', json_encode([
        $pedido['cliente_id'], $pedido['orden_compra'], $pedido['subtotal'],
        $pedido['descuento'], $pedido['iva'], $pedido['total'], $consulta->fetchAll(),
    ], JSON_THROW_ON_ERROR));
}

function crear_pedido(PDO $conexion, int $usuarioId, array $entrada): int
{
    [$clienteId, $orden, $cantidades] = validar_entrada_borrador($entrada);

    $conexion->beginTransaction();
    try {
        $cliente = cliente_para_pedido($conexion, $clienteId);
        $lineas = calcular_lineas_borrador($conexion, $cliente, $cantidades);
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
        guardar_lineas_borrador($conexion, $pedidoId, $lineas);
        $conexion->commit();
        return $pedidoId;
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        throw $error;
    }
}

function actualizar_borrador_pedido(PDO $conexion, int $pedidoId, array $entrada, string $versionEsperada): void
{
    [$clienteId, $orden, $cantidades] = validar_entrada_borrador($entrada);

    $conexion->beginTransaction();
    try {
        $consulta = $conexion->prepare(
            'SELECT id, estado, cliente_id, orden_compra, subtotal, descuento, iva, total
             FROM pedidos WHERE id = :id FOR UPDATE'
        );
        $consulta->execute(['id' => $pedidoId]);
        $pedido = $consulta->fetch();
        if ($pedido === false) throw new ReglaPedido('El pedido solicitado no existe. Selecciona otro desde la lista de pedidos.', 404);
        if ($pedido['estado'] !== 'borrador') {
            throw new ReglaPedido('Solo se pueden editar pedidos en borrador. Actualiza la página.', 409);
        }
        if (!preg_match('/^[a-f0-9]{64}$/D', $versionEsperada)
            || !hash_equals(version_borrador_pedido($conexion, $pedidoId, $pedido), $versionEsperada)) {
            throw new ReglaPedido('Este borrador cambió desde que abriste el formulario. Recarga el pedido antes de editarlo.', 409);
        }

        $cliente = cliente_para_pedido($conexion, $clienteId);
        $guardadas = lineas_guardadas_borrador($conexion, $pedidoId);
        $lineas = calcular_lineas_borrador(
            $conexion, $cliente, $cantidades, $guardadas, (int) $pedido['cliente_id'] !== $clienteId
        );
        $totales = calcular_totales_pedido($lineas);
        $actualizar = $conexion->prepare(
            "UPDATE pedidos SET cliente_id = :cliente_id, orden_compra = :orden_compra,
             subtotal = :subtotal, descuento = :descuento, iva = :iva, total = :total
             WHERE id = :id AND estado = 'borrador'"
        );
        $actualizar->execute([
            'id' => $pedidoId, 'cliente_id' => $clienteId,
            'orden_compra' => $orden === '' ? null : $orden,
            'subtotal' => decimal_de_centavos($totales['subtotal']),
            'descuento' => decimal_de_centavos($totales['descuento']),
            'iva' => decimal_de_centavos($totales['iva']),
            'total' => decimal_de_centavos($totales['total']),
        ]);
        actualizar_lineas_borrador($conexion, $pedidoId, $lineas, $guardadas);
        $conexion->commit();
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
        throw new ReglaPedido('La acción solicitada no existe. Vuelve al detalle y elige una acción disponible.', 400);
    }

    $conexion->beginTransaction();
    try {
        $consulta = $conexion->prepare('SELECT id, cliente_id, estado, total FROM pedidos WHERE id = :id FOR UPDATE');
        $consulta->execute(['id' => $pedidoId]);
        $pedido = $consulta->fetch();
        if ($pedido === false) throw new ReglaPedido('El pedido solicitado no existe. Selecciona otro desde la lista de pedidos.', 404);
        $estadoAnterior = $pedido['estado'];
        if (!in_array($estadoAnterior, $transiciones[$accion]['desde'], true)) {
            throw new ReglaPedido('El estado actual no permite esta acción. Actualiza el pedido.', 409);
        }

        if ($accion === 'confirmar') {
            $cliente = cliente_para_pedido($conexion, (int) $pedido['cliente_id']);
            $saldo = saldo_cliente_centavos($conexion, (int) $pedido['cliente_id']);
            $total = centavos_de_decimal($pedido['total']);
            if ($total > centavos_de_decimal($cliente['cupo_credito']) - $saldo) {
                throw new ReglaPedido('El saldo pendiente más este pedido supera el cupo de crédito del cliente. Reduce el valor del pedido o revisa el cupo y los pagos pendientes.', 409);
            }
            $lineas = detalle_lineas_pedido($conexion, $pedidoId);
            if ($lineas === []) throw new ReglaPedido('El pedido no tiene productos. Añade al menos uno antes de confirmarlo.', 409);
            $productos = productos_para_pedido($conexion, array_column($lineas, 'producto_id'));
            foreach ($lineas as $linea) {
                if ((int) $productos[(int) $linea['producto_id']]['stock'] < (int) $linea['cantidad']) {
                    throw new StockInsuficientePedido('No hay stock suficiente de ' . $linea['sku'] . ' (' . $linea['nombre'] . '). Reduce la cantidad o repón existencias antes de confirmar.', 409);
                }
            }
        } elseif ($accion === 'anular' && $estadoAnterior === 'confirmado') {
            $lineas = detalle_lineas_pedido($conexion, $pedidoId);
            $ids = array_column($lineas, 'producto_id');
            sort($ids, SORT_NUMERIC);
            $bloquear = $conexion->prepare('SELECT id FROM productos WHERE id = :id FOR UPDATE');
            foreach ($ids as $id) {
                $bloquear->execute(['id' => $id]);
                if ($bloquear->fetch() === false) throw new ReglaPedido('Falta un producto del pedido. Actualiza el borrador y selecciona un producto disponible.', 409);
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
