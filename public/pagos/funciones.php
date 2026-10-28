<?php

declare(strict_types=1);

require_once __DIR__ . '/../pedidos/funciones.php';

const METODOS_PAGO = [
    'transferencia' => 'Transferencia',
    'efectivo' => 'Efectivo',
    'cheque' => 'Cheque',
];

const HORAS_DEVOLUCION_ATENCION = 24;
const HORAS_DEVOLUCION_URGENTE = 48;

final class ReglaPago extends RuntimeException
{
}

final class ReglaDevolucion extends RuntimeException
{
}

function centavos_pago_recibido(mixed $valor): ?int
{
    if (!is_string($valor) || !preg_match('/^([0-9]{1,12})(?:[.,]([0-9]{1,2}))?$/D', trim($valor), $partes)) {
        return null;
    }

    $centavos = (int) $partes[1] * 100 + (int) str_pad($partes[2] ?? '0', 2, '0');
    return $centavos > 0 && $centavos <= MAX_CENTAVOS_PEDIDO ? $centavos : null;
}

function mostrar_monto_pago(int $centavos): string
{
    return mostrar_centavos_cop($centavos);
}

function estado_espera_devolucion(?string $anuladoEn, ?DateTimeImmutable $ahora = null): array
{
    if ($anuladoEn === null) {
        return ['nivel' => 'sin_fecha', 'horas' => null];
    }

    $zonaUtc = new DateTimeZone('UTC');
    $fechaAnulacion = new DateTimeImmutable($anuladoEn, $zonaUtc);
    $ahora ??= new DateTimeImmutable('now', $zonaUtc);
    $horas = intdiv(max(0, $ahora->getTimestamp() - $fechaAnulacion->getTimestamp()), 3600);

    $nivel = $horas >= HORAS_DEVOLUCION_URGENTE ? 'urgente'
        : ($horas >= HORAS_DEVOLUCION_ATENCION ? 'atencion' : 'reciente');
    return ['nivel' => $nivel, 'horas' => $horas];
}

function resumen_pedido_pago(PDO $conexion, int $pedidoId): ?array
{
    $consulta = $conexion->prepare(
        'SELECT p.id, p.consecutivo, p.estado, p.total, p.creado_en,
                c.razon_social AS cliente, c.nit,
                COALESCE(pagado.total_pagado, 0.00) AS pagado
         FROM pedidos AS p
         INNER JOIN clientes AS c ON c.id = p.cliente_id
         LEFT JOIN (
             SELECT pedido_id, SUM(valor) AS total_pagado FROM pagos GROUP BY pedido_id
         ) AS pagado ON pagado.pedido_id = p.id
         WHERE p.id = :id LIMIT 1'
    );
    $consulta->execute(['id' => $pedidoId]);
    $pedido = $consulta->fetch();
    return $pedido === false ? null : $pedido;
}

function historial_pagos(PDO $conexion, int $pedidoId): array
{
    $consulta = $conexion->prepare(
        'SELECT pg.id, pg.valor, pg.metodo, pg.fecha, u.nombre AS registrado_por
         FROM pagos AS pg INNER JOIN usuarios AS u ON u.id = pg.usuario_id
         WHERE pg.pedido_id = :id ORDER BY pg.fecha DESC, pg.id DESC'
    );
    $consulta->execute(['id' => $pedidoId]);
    return $consulta->fetchAll();
}

function devolucion_pedido(PDO $conexion, int $pedidoId): ?array
{
    $consulta = $conexion->prepare(
        'SELECT d.valor, d.metodo, d.referencia, d.fecha, u.nombre AS registrado_por
         FROM devoluciones AS d INNER JOIN usuarios AS u ON u.id = d.usuario_id
         WHERE d.pedido_id = :id LIMIT 1'
    );
    $consulta->execute(['id' => $pedidoId]);
    $devolucion = $consulta->fetch();
    return $devolucion === false ? null : $devolucion;
}

function registrar_devolucion(PDO $conexion, int $pedidoId, int $usuarioId, mixed $metodoRecibido, mixed $referenciaRecibida): int
{
    if (!is_string($metodoRecibido) || !isset(METODOS_PAGO[$metodoRecibido])) {
        throw new ReglaDevolucion('Selecciona cómo se devolvió el dinero al cliente.', 422);
    }
    if (!is_string($referenciaRecibida) || !mb_check_encoding($referenciaRecibida, 'UTF-8')) {
        throw new ReglaDevolucion('Ingresa una referencia válida o deja el campo vacío.', 422);
    }
    $referencia = trim($referenciaRecibida);
    if (mb_strlen($referencia, 'UTF-8') > 100 || preg_match('/[\x00-\x1F\x7F]/u', $referencia)) {
        throw new ReglaDevolucion('La referencia admite hasta 100 caracteres y una sola línea.', 422);
    }

    $conexion->beginTransaction();
    try {
        $consulta = $conexion->prepare('SELECT estado FROM pedidos WHERE id = :id FOR UPDATE');
        $consulta->execute(['id' => $pedidoId]);
        $pedido = $consulta->fetch();
        if ($pedido === false) {
            throw new ReglaDevolucion('El pedido solicitado no existe.', 404);
        }
        if ($pedido['estado'] !== 'anulado') {
            throw new ReglaDevolucion('Solo se registran devoluciones de pedidos anulados.', 409);
        }

        $consulta = $conexion->prepare('SELECT COALESCE(SUM(valor), 0.00) FROM pagos WHERE pedido_id = :id');
        $consulta->execute(['id' => $pedidoId]);
        $pagado = centavos_de_decimal((string) $consulta->fetchColumn());
        if ($pagado === 0) {
            throw new ReglaDevolucion('Este pedido no tiene pagos por devolver.', 409);
        }
        $consulta = $conexion->prepare('SELECT id FROM devoluciones WHERE pedido_id = :id');
        $consulta->execute(['id' => $pedidoId]);
        if ($consulta->fetch() !== false) {
            throw new ReglaDevolucion('La devolución de este pedido ya fue registrada.', 409);
        }

        $insertar = $conexion->prepare(
            'INSERT INTO devoluciones (pedido_id, valor, metodo, referencia, fecha, usuario_id)
             VALUES (:pedido_id, :valor, :metodo, :referencia, UTC_TIMESTAMP(6), :usuario_id)'
        );
        $insertar->execute([
            'pedido_id' => $pedidoId,
            'valor' => decimal_de_centavos($pagado),
            'metodo' => $metodoRecibido,
            'referencia' => $referencia === '' ? null : $referencia,
            'usuario_id' => $usuarioId,
        ]);
        $devolucionId = (int) $conexion->lastInsertId();
        $conexion->commit();
        return $devolucionId;
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        throw $error;
    }
}

function registrar_pago(PDO $conexion, int $pedidoId, int $usuarioId, mixed $valorRecibido, mixed $metodoRecibido): int
{
    $valorCentavos = centavos_pago_recibido($valorRecibido);
    if ($valorCentavos === null) {
        throw new ReglaPago('Ingresa un valor mayor que cero, sin separadores de miles y con máximo dos decimales.', 422);
    }
    if (!is_string($metodoRecibido) || !isset(METODOS_PAGO[$metodoRecibido])) {
        throw new ReglaPago('Selecciona transferencia, efectivo o cheque.', 422);
    }

    $conexion->beginTransaction();
    try {
        $consulta = $conexion->prepare(
            'SELECT id, cliente_id, estado, total FROM pedidos WHERE id = :id FOR UPDATE'
        );
        $consulta->execute(['id' => $pedidoId]);
        $pedido = $consulta->fetch();
        if ($pedido === false) {
            throw new ReglaPago('El pedido solicitado no existe.', 404);
        }
        if (!in_array($pedido['estado'], ['confirmado', 'despachado', 'entregado'], true)) {
            throw new ReglaPago('Este pedido no admite pagos en su estado actual.', 409);
        }

        // La confirmación de pedidos bloquea primero el pedido y después el cliente.
        // Conservamos ese orden para que el cálculo del cupo vea pagos ya confirmados.
        $bloquearCliente = $conexion->prepare('SELECT id FROM clientes WHERE id = :id FOR UPDATE');
        $bloquearCliente->execute(['id' => $pedido['cliente_id']]);
        if ($bloquearCliente->fetch() === false) {
            throw new ReglaPago('El cliente del pedido no existe.', 409);
        }

        $consulta = $conexion->prepare('SELECT COALESCE(SUM(valor), 0.00) FROM pagos WHERE pedido_id = :id');
        $consulta->execute(['id' => $pedidoId]);
        $pagadoCentavos = centavos_de_decimal((string) $consulta->fetchColumn());
        $saldoCentavos = centavos_de_decimal($pedido['total']) - $pagadoCentavos;
        if ($saldoCentavos <= 0) {
            throw new ReglaPago('El pedido ya no tiene saldo pendiente.', 409);
        }
        if ($valorCentavos > $saldoCentavos) {
            throw new ReglaPago('El valor supera el saldo pendiente del pedido.', 409);
        }

        $insertar = $conexion->prepare(
            'INSERT INTO pagos (pedido_id, valor, metodo, fecha, usuario_id)
             VALUES (:pedido_id, :valor, :metodo, UTC_TIMESTAMP(6), :usuario_id)'
        );
        $insertar->execute([
            'pedido_id' => $pedidoId,
            'valor' => decimal_de_centavos($valorCentavos),
            'metodo' => $metodoRecibido,
            'usuario_id' => $usuarioId,
        ]);
        $pagoId = (int) $conexion->lastInsertId();
        $conexion->commit();
        return $pagoId;
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        throw $error;
    }
}
