<?php

declare(strict_types=1);

const VENTANA_INTENTOS = 15 * 60;
const LIMITE_INTENTOS_CORREO = 5;
const LIMITE_INTENTOS_IP = 20;

/** Devuelve el ID autenticado, "incorrecto" o "bloqueado". */
function verificar_ingreso(PDO $conexion, string $correo, string $clave, string $ip): int|string
{
    $limites = [
        hash('sha256', 'correo:' . mb_strtolower($correo, 'UTF-8')) => LIMITE_INTENTOS_CORREO,
        hash('sha256', 'ip:' . $ip) => LIMITE_INTENTOS_IP,
    ];
    ksort($limites, SORT_STRING);
    $ahora = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $fechaActual = $ahora->format('Y-m-d H:i:s.u');
    $fechaBloqueo = $ahora->modify('+' . VENTANA_INTENTOS . ' seconds')->format('Y-m-d H:i:s.u');

    $conexion->beginTransaction();
    try {
        $crear = $conexion->prepare('INSERT IGNORE INTO intentos_acceso (clave) VALUES (:clave)');
        $bloquear = $conexion->prepare('SELECT intentos, ultimo_fallo, bloqueado_hasta FROM intentos_acceso WHERE clave = :clave FOR UPDATE');
        $registros = [];
        foreach ($limites as $llave => $limite) {
            $crear->execute(['clave' => $llave]);
            $bloquear->execute(['clave' => $llave]);
            $registros[$llave] = $bloquear->fetch();
        }

        foreach ($registros as $registro) {
            if ($registro['bloqueado_hasta'] !== null && $registro['bloqueado_hasta'] > $fechaActual) {
                $conexion->commit();
                return 'bloqueado';
            }
        }

        $consulta = $conexion->prepare('SELECT id, clave_hash FROM usuarios WHERE correo = :correo AND activo = 1 LIMIT 1');
        $consulta->execute(['correo' => $correo]);
        $usuario = $consulta->fetch();

        if ($usuario !== false && password_verify($clave, $usuario['clave_hash'])) {
            $limpiar = $conexion->prepare('DELETE FROM intentos_acceso WHERE clave = :clave');
            foreach ($limites as $llave => $limite) {
                $limpiar->execute(['clave' => $llave]);
            }
            $conexion->commit();
            return (int) $usuario['id'];
        }

        $actualizar = $conexion->prepare(
            'UPDATE intentos_acceso SET intentos = :intentos, ultimo_fallo = :ultimo_fallo,
             bloqueado_hasta = :bloqueado_hasta WHERE clave = :clave'
        );
        foreach ($limites as $llave => $limite) {
            $registro = $registros[$llave];
            $enVentana = $registro['ultimo_fallo'] !== null
                && $registro['ultimo_fallo'] >= $ahora->modify('-' . VENTANA_INTENTOS . ' seconds')->format('Y-m-d H:i:s.u');
            $intentos = $enVentana ? min(255, (int) $registro['intentos'] + 1) : 1;
            $actualizar->execute([
                'intentos' => $intentos,
                'ultimo_fallo' => $fechaActual,
                'bloqueado_hasta' => $intentos >= $limite ? $fechaBloqueo : null,
                'clave' => $llave,
            ]);
        }
        $conexion->commit();
        return 'incorrecto';
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        throw $error;
    }
}
