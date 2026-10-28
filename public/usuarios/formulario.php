<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/vista.php';
require_once __DIR__ . '/funciones.php';

$administrador = exigir_permiso('usuarios.administrar');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    mostrar_error(405, 'Método no permitido', 'Usa el formulario para guardar el usuario.', $administrador);
}

$idRecibido = $_GET['id'] ?? null;
$esEdicion = $idRecibido !== null;
if ($esEdicion && (!is_string($idRecibido) || !ctype_digit($idRecibido) || (int) $idRecibido < 1)) {
    mostrar_error(404, 'Usuario no encontrado', 'El usuario solicitado no existe. Selecciona otro desde la lista de usuarios.', $administrador);
}
$id = $esEdicion ? (int) $idRecibido : null;
$miCuenta = $id !== null && $id === $administrador['id'];

try {
    $conexion = conectar_bd();
    $roles = roles_asignables($conexion);
    if ($roles === []) {
        throw new RuntimeException('No hay roles asignables.');
    }
    $usuarioActual = null;
    if ($esEdicion) {
        $consulta = $conexion->prepare(
            'SELECT id, nombre, correo, rol_id, activo FROM usuarios WHERE id = :id LIMIT 1'
        );
        $consulta->execute(['id' => $id]);
        $usuarioActual = $consulta->fetch();
        if ($usuarioActual === false) {
            mostrar_error(404, 'Usuario no encontrado', 'El usuario solicitado no existe. Selecciona otro desde la lista de usuarios.', $administrador);
        }
    }
} catch (Throwable $error) {
    error_log((string) $error);
    mostrar_error(503, 'Servicio no disponible', 'No se pudo cargar el formulario de usuarios. Vuelve a la lista e inténtalo de nuevo en unos minutos.', $administrador);
}

$datos = [
    'nombre' => $usuarioActual['nombre'] ?? '',
    'correo' => $usuarioActual['correo'] ?? '',
    'rol_id' => isset($usuarioActual['rol_id']) ? (string) $usuarioActual['rol_id'] : '',
    'activo' => isset($usuarioActual['activo']) ? (string) $usuarioActual['activo'] : '1',
    'clave' => '',
];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token_csrf'] ?? null;
    if (!csrf_valido(is_string($token) ? $token : null)) {
        mostrar_error(403, 'Formulario vencido', 'Recarga la página e intenta guardar el usuario de nuevo.', $administrador);
    }

    [$datos, $errores] = datos_usuario($_POST, $roles, $esEdicion);
    if ($errores === []) {
        try {
            guardar_usuario($conexion, $id, $datos, $administrador['id']);
            if ($miCuenta && $datos['clave'] !== '') {
                iniciar_sesion_de_usuario($administrador['id']);
            }
            $_SESSION['aviso_usuarios'] = [
                'accion' => $esEdicion ? 'editar' : 'crear',
                'nombre' => $datos['nombre'],
            ];
            header('Location: /usuarios/index.php', true, 303);
            exit;
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? null) === 1062) {
                $errores['correo'] = 'Este correo ya pertenece a otro usuario. Escribe uno distinto o edita la cuenta existente.';
                http_response_code(409);
            } else {
                error_log((string) $error);
                mostrar_error(503, 'Servicio no disponible', 'No se pudo confirmar el guardado del usuario. Revisa la lista antes de intentarlo de nuevo.', $administrador);
            }
        } catch (DomainException $error) {
            $errores['general'] = $error->getMessage();
            http_response_code(409);
        } catch (OutOfBoundsException $error) {
            mostrar_error(404, 'Usuario no encontrado', 'El usuario solicitado ya no existe. Actualiza la lista y selecciona otra cuenta.', $administrador);
        } catch (Throwable $error) {
            error_log((string) $error);
            mostrar_error(503, 'Servicio no disponible', 'No se pudo confirmar el guardado del usuario. Revisa la lista antes de intentarlo de nuevo.', $administrador);
        }
    }
}

$titulo = $esEdicion ? 'Editar usuario' : 'Nuevo usuario';
mostrar_cabecera($titulo, $administrador, false, 'usuarios');
?>
<nav aria-label="Ruta de navegación" class="mb-4">
    <a href="/index.php" class="text-decoration-none">Inicio</a>
    <span class="text-secondary"> / </span>
    <a href="/usuarios/index.php" class="text-decoration-none">Usuarios</a>
    <span class="text-secondary"> / <?= escapar_html($titulo) ?></span>
</nav>

<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <h1 class="h2 mb-2"><?= escapar_html($titulo) ?></h1>
                <p class="text-secondary mb-4">Define los datos de acceso y el rol de la persona.</p>

                <?php if ($errores !== []): ?>
                    <div class="alert alert-danger" role="alert"><?= isset($errores['general']) ? escapar_html($errores['general']) : 'Revisa los campos señalados antes de guardar.' ?></div>
                <?php endif; ?>

                <form id="formulario-usuario" action="/usuarios/formulario.php<?= $esEdicion ? '?id=' . $id : '' ?>" method="post" data-accion-usuario="<?= $esEdicion ? 'editar' : 'crear' ?>">
                    <input type="hidden" name="token_csrf" value="<?= escapar_html(token_csrf()) ?>">

                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre completo</label>
                        <input id="nombre" name="nombre" type="text" class="form-control <?= isset($errores['nombre']) ? 'is-invalid' : '' ?>" value="<?= escapar_html($datos['nombre']) ?>" minlength="2" maxlength="100" autocomplete="name" required>
                        <?php if (isset($errores['nombre'])): ?><div class="invalid-feedback"><?= escapar_html($errores['nombre']) ?></div><?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="correo" class="form-label">Correo electrónico</label>
                        <input id="correo" name="correo" type="email" class="form-control <?= isset($errores['correo']) ? 'is-invalid' : '' ?>" value="<?= escapar_html($datos['correo']) ?>" maxlength="120" autocomplete="off" required>
                        <?php if (isset($errores['correo'])): ?><div class="invalid-feedback"><?= escapar_html($errores['correo']) ?></div><?php endif; ?>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label for="rol_id" class="form-label">Rol</label>
                            <select id="rol_id" name="rol_id" class="form-select <?= isset($errores['rol_id']) ? 'is-invalid' : '' ?>" <?= $miCuenta ? 'disabled' : 'required' ?>>
                                <option value="">Seleccionar rol</option>
                                <?php foreach ($roles as $rol): ?>
                                    <option value="<?= (int) $rol['id'] ?>" <?= $datos['rol_id'] === (string) $rol['id'] ? 'selected' : '' ?>><?= escapar_html($rol['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($miCuenta): ?><input type="hidden" name="rol_id" value="<?= (int) $usuarioActual['rol_id'] ?>"><?php endif; ?>
                            <?php if (isset($errores['rol_id'])): ?><div class="invalid-feedback"><?= escapar_html($errores['rol_id']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-5">
                            <label for="activo" class="form-label">Estado</label>
                            <select id="activo" name="activo" class="form-select <?= isset($errores['activo']) ? 'is-invalid' : '' ?>" <?= $miCuenta ? 'disabled' : 'required' ?>>
                                <option value="1" <?= $datos['activo'] === '1' ? 'selected' : '' ?>>Activo</option>
                                <option value="0" <?= $datos['activo'] === '0' ? 'selected' : '' ?>>Inactivo</option>
                            </select>
                            <?php if ($miCuenta): ?><input type="hidden" name="activo" value="1"><?php endif; ?>
                            <?php if (isset($errores['activo'])): ?><div class="invalid-feedback"><?= escapar_html($errores['activo']) ?></div><?php endif; ?>
                        </div>
                    </div>
                    <?php if ($miCuenta): ?><p class="form-text mb-3">Puedes actualizar tus datos y contraseña. Tu rol y tu estado no se pueden cambiar desde tu propia cuenta.</p><?php endif; ?>

                    <div class="row g-3 mb-2">
                        <div class="col-md-6">
                            <label for="clave" class="form-label"><?= $esEdicion ? 'Nueva contraseña (opcional)' : 'Contraseña' ?></label>
                            <input id="clave" name="clave" type="password" class="form-control <?= isset($errores['clave']) ? 'is-invalid' : '' ?>" minlength="12" maxlength="72" autocomplete="new-password" <?= $esEdicion ? '' : 'required' ?>>
                            <?php if (isset($errores['clave'])): ?><div class="invalid-feedback"><?= escapar_html($errores['clave']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="confirmar_clave" class="form-label">Confirmar contraseña</label>
                            <input id="confirmar_clave" name="confirmar_clave" type="password" class="form-control <?= isset($errores['confirmar_clave']) ? 'is-invalid' : '' ?>" minlength="12" maxlength="72" autocomplete="new-password" <?= $esEdicion ? '' : 'required' ?>>
                            <?php if (isset($errores['confirmar_clave'])): ?><div class="invalid-feedback"><?= escapar_html($errores['confirmar_clave']) ?></div><?php endif; ?>
                        </div>
                    </div>
                    <p class="form-text mb-4">Mínimo 12 caracteres. Al editar, deja ambos campos vacíos para conservar la contraseña actual.</p>

                    <div class="d-flex flex-wrap gap-2">
                        <button id="boton-guardar-usuario" type="submit" class="btn btn-primary"><?= $esEdicion ? 'Guardar cambios' : 'Crear usuario' ?></button>
                        <a href="/usuarios/index.php" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<dialog id="dialogo-confirmar-usuario" class="dialogo-confirmacion dialogo-usuario"
    aria-labelledby="titulo-confirmar-usuario" aria-describedby="descripcion-confirmar-usuario">
    <span class="icono-confirmacion" aria-hidden="true">
        <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><circle cx="24" cy="24" r="18"/><path d="m16 24 6 6 11-12"/></svg>
    </span>
    <h2 id="titulo-confirmar-usuario"></h2>
    <p id="descripcion-confirmar-usuario">Revisa los datos antes de confirmar.</p>
    <dl class="resumen-confirmacion-usuario">
        <div><dt>Nombre</dt><dd data-resumen-nombre></dd></div>
        <div><dt>Correo</dt><dd data-resumen-correo></dd></div>
        <div><dt>Rol</dt><dd data-resumen-rol></dd></div>
        <div><dt>Estado</dt><dd data-resumen-estado></dd></div>
        <div><dt>Contraseña</dt><dd data-resumen-clave></dd></div>
    </dl>
    <div class="acciones-confirmacion">
        <button type="button" class="btn btn-outline-secondary" data-cancelar-usuario>Cancelar</button>
        <button type="button" class="btn btn-primary" data-confirmar-usuario></button>
    </div>
</dialog>
<?php mostrar_pie(true); ?>
