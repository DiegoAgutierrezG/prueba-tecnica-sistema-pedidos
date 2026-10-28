<?php

declare(strict_types=1);

function roles_asignables(PDO $conexion): array
{
    $consulta = $conexion->prepare(
        "SELECT id, codigo, nombre FROM roles
         WHERE codigo IN ('administrador', 'vendedor', 'bodega') ORDER BY id"
    );
    $consulta->execute();

    $roles = [];
    foreach ($consulta->fetchAll() as $rol) {
        $roles[(int) $rol['id']] = $rol;
    }
    return $roles;
}

function datos_usuario(array $entrada, array $roles, bool $esEdicion): array
{
    $nombreRecibido = $entrada['nombre'] ?? null;
    $correoRecibido = $entrada['correo'] ?? null;
    $rolRecibido = $entrada['rol_id'] ?? null;
    $activoRecibido = $entrada['activo'] ?? null;
    $claveRecibida = $entrada['clave'] ?? null;
    $confirmacionRecibida = $entrada['confirmar_clave'] ?? null;

    $nombre = is_string($nombreRecibido) ? trim($nombreRecibido) : '';
    $correoOriginal = is_string($correoRecibido) ? trim($correoRecibido) : '';
    $correo = mb_check_encoding($correoOriginal, 'UTF-8')
        ? mb_strtolower($correoOriginal, 'UTF-8') : $correoOriginal;
    $rolId = is_string($rolRecibido) ? $rolRecibido : '';
    $activo = is_string($activoRecibido) ? $activoRecibido : '';
    $clave = is_string($claveRecibida) ? $claveRecibida : '';
    $confirmacion = is_string($confirmacionRecibida) ? $confirmacionRecibida : '';
    $errores = [];

    if (!mb_check_encoding($nombre, 'UTF-8') || mb_strlen($nombre, 'UTF-8') < 2
        || mb_strlen($nombre, 'UTF-8') > 100 || preg_match('/[\p{C}]/u', $nombre)) {
        $errores['nombre'] = 'Escribe un nombre de 2 a 100 caracteres.';
    }
    if (!mb_check_encoding($correo, 'UTF-8') || strlen($correo) > 120
        || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
        $errores['correo'] = 'Escribe un correo válido de hasta 120 caracteres.';
    }
    if ($rolId === '' || !ctype_digit($rolId) || !isset($roles[(int) $rolId])) {
        $errores['rol_id'] = 'Selecciona uno de los roles disponibles.';
    }
    if (!in_array($activo, ['0', '1'], true)) {
        $errores['activo'] = 'Selecciona un estado válido.';
    }
    if (!$esEdicion || $clave !== '' || $confirmacion !== '') {
        if (!mb_check_encoding($clave, 'UTF-8') || mb_strlen($clave, 'UTF-8') < 12
            || strlen($clave) > 72) {
            $errores['clave'] = 'La contraseña debe tener al menos 12 caracteres y un máximo de 72 bytes.';
        }
        if ($clave !== $confirmacion) {
            $errores['confirmar_clave'] = 'Las contraseñas no coinciden.';
        }
    }

    return [[
        'nombre' => $nombre,
        'correo' => $correo,
        'rol_id' => $rolId,
        'activo' => $activo,
        'clave' => $clave,
    ], $errores];
}

function guardar_usuario(PDO $conexion, ?int $id, array $datos, int $administradorId): int
{
    $claveHash = $datos['clave'] === '' ? null : password_hash($datos['clave'], PASSWORD_BCRYPT, ['cost' => 12]);
    $conexion->beginTransaction();
    try {
        // La fila del rol serializa cambios simultáneos de administradores.
        $consulta = $conexion->prepare("SELECT id FROM roles WHERE codigo = 'administrador' FOR UPDATE");
        $consulta->execute();
        $rolAdministrador = $consulta->fetchColumn();
        if ($rolAdministrador === false) {
            throw new RuntimeException('No existe el rol administrador.');
        }
        $rolAdministrador = (int) $rolAdministrador;

        $consulta = $conexion->prepare('SELECT rol_id, activo FROM usuarios WHERE id = :id FOR UPDATE');
        $consulta->execute(['id' => $administradorId]);
        $administrador = $consulta->fetch();
        if ($administrador === false || (int) $administrador['rol_id'] !== $rolAdministrador
            || (int) $administrador['activo'] !== 1) {
            throw new DomainException('Tu acceso de administrador cambió. Vuelve a ingresar.');
        }

        if ($id === null) {
            $consulta = $conexion->prepare(
                'INSERT INTO usuarios (nombre, correo, clave_hash, rol_id, activo)
                 VALUES (:nombre, :correo, :clave_hash, :rol_id, :activo)'
            );
            $consulta->execute([
                'nombre' => $datos['nombre'], 'correo' => $datos['correo'],
                'clave_hash' => $claveHash, 'rol_id' => (int) $datos['rol_id'],
                'activo' => (int) $datos['activo'],
            ]);
            $id = (int) $conexion->lastInsertId();
        } else {
            $consulta->execute(['id' => $id]);
            $anterior = $consulta->fetch();
            if ($anterior === false) {
                throw new OutOfBoundsException('El usuario ya no existe.');
            }

            $nuevoRol = (int) $datos['rol_id'];
            $nuevoActivo = (int) $datos['activo'];
            if ($id === $administradorId && ($nuevoRol !== $rolAdministrador || $nuevoActivo !== 1)) {
                throw new DomainException('No puedes cambiar tu propio rol ni desactivar tu cuenta. Pide a otro administrador que gestione ese cambio.');
            }
            if ((int) $anterior['rol_id'] === $rolAdministrador && (int) $anterior['activo'] === 1
                && ($nuevoRol !== $rolAdministrador || $nuevoActivo !== 1)) {
                $contar = $conexion->prepare(
                    'SELECT COUNT(*) FROM usuarios WHERE rol_id = :rol_id AND activo = 1'
                );
                $contar->execute(['rol_id' => $rolAdministrador]);
                if ((int) $contar->fetchColumn() <= 1) {
                    throw new DomainException('Debe quedar al menos un administrador activo. Activa o asigna otro antes de desactivar esta cuenta.');
                }
            }

            $campos = 'nombre = :nombre, correo = :correo, rol_id = :rol_id, activo = :activo';
            $parametros = [
                'nombre' => $datos['nombre'], 'correo' => $datos['correo'],
                'rol_id' => $nuevoRol, 'activo' => $nuevoActivo, 'id' => $id,
            ];
            if ($claveHash !== null) {
                $campos .= ', clave_hash = :clave_hash';
                $parametros['clave_hash'] = $claveHash;
            }
            $actualizar = $conexion->prepare('UPDATE usuarios SET ' . $campos . ' WHERE id = :id');
            $actualizar->execute($parametros);
        }

        $conexion->commit();
        return $id;
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        throw $error;
    }
}
