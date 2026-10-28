<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/vista.php';
require_once __DIR__ . '/funciones.php';

$usuario = exigir_permiso('clientes.editar');
$idRecibido = $_GET['id'] ?? null;
$esEdicion = $idRecibido !== null;

if ($esEdicion && (!is_string($idRecibido) || !ctype_digit($idRecibido) || (int) $idRecibido < 1)) {
    mostrar_error(404, 'Cliente no encontrado', 'El cliente solicitado no existe. Selecciona otro desde la lista de clientes.', $usuario);
}

$id = $esEdicion ? (int) $idRecibido : null;

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    mostrar_error(405, 'Método no permitido', 'Usa el formulario para guardar el cliente.', $usuario);
}

$datos = [
    'nit' => '',
    'nit_numero' => '',
    'nit_dv' => '',
    'razon_social' => '',
    'tipo' => '',
    'cupo_credito' => '0',
    'ciudad' => '',
];
$errores = [];
$clienteActual = null;

if ($esEdicion) {
    try {
        $consulta = conectar_bd()->prepare(
            'SELECT id, nit, razon_social, tipo, cupo_credito, ciudad, activo FROM clientes WHERE id = :id LIMIT 1'
        );
        $consulta->execute(['id' => $id]);
        $clienteActual = $consulta->fetch();
    } catch (Throwable $error) {
        error_log((string) $error);
        mostrar_error(503, 'Servicio no disponible', 'No se pudo consultar el cliente. Vuelve a la lista e inténtalo de nuevo en unos minutos.', $usuario);
    }

    if ($clienteActual === false) {
        mostrar_error(404, 'Cliente no encontrado', 'El cliente solicitado no existe. Selecciona otro desde la lista de clientes.', $usuario);
    }

    [$datos['nit_numero'], $datos['nit_dv']] = array_pad(explode('-', $clienteActual['nit'], 2), 2, '');
    foreach (['nit', 'razon_social', 'tipo', 'cupo_credito', 'ciudad'] as $campo) {
        $datos[$campo] = $clienteActual[$campo];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token_csrf'] ?? null;
    if (!csrf_valido(is_string($token) ? $token : null)) {
        mostrar_error(403, 'Formulario vencido', 'Recarga la página e intenta guardar el cliente de nuevo.', $usuario);
    }

    [$datos, $errores] = validar_datos_cliente($_POST);

    if ($errores === []) {
        try {
            $conexion = conectar_bd();
            $parametros = [
                'nit' => $datos['nit'],
                'razon_social' => $datos['razon_social'],
                'tipo' => $datos['tipo'],
                'cupo_credito' => $datos['cupo_credito'],
                'ciudad' => $datos['ciudad'],
            ];

            if ($esEdicion) {
                $consulta = $conexion->prepare(
                    'UPDATE clientes SET nit = :nit, razon_social = :razon_social, tipo = :tipo,
                     cupo_credito = :cupo_credito, ciudad = :ciudad WHERE id = :id'
                );
                $parametros['id'] = $id;
            } else {
                $consulta = $conexion->prepare(
                    'INSERT INTO clientes (nit, razon_social, tipo, cupo_credito, ciudad)
                     VALUES (:nit, :razon_social, :tipo, :cupo_credito, :ciudad)'
                );
            }

            $consulta->execute($parametros);
            $_SESSION['aviso_clientes'] = $esEdicion ? 'Cliente actualizado correctamente.' : 'Cliente creado correctamente.';
            header('Location: /clientes/index.php' . ($esEdicion && (int) $clienteActual['activo'] === 0 ? '?estado=inactivos' : ''), true, 303);
            exit;
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? null) === 1062) {
                $errores['nit'] = 'Este NIT ya pertenece a otro cliente. Revisa el número o busca ese cliente en la lista.';
            } else {
                error_log((string) $error);
                mostrar_error(503, 'Servicio no disponible', 'No se pudo confirmar el guardado del cliente. Revisa la lista antes de intentarlo de nuevo.', $usuario);
            }
        }
    }
}

$titulo = $esEdicion ? 'Editar cliente' : 'Nuevo cliente';
$cupoFormulario = preg_replace('/^([0-9]+)\.00$/', '$1', $datos['cupo_credito']);
mostrar_cabecera($titulo, $usuario, false, 'clientes');
?>
<nav aria-label="Ruta de navegación" class="mb-4">
    <a href="/index.php" class="text-decoration-none">Inicio</a>
    <span class="text-secondary"> / </span>
    <a href="/clientes/index.php" class="text-decoration-none">Clientes</a>
    <span class="text-secondary"> / <?= escapar_html($titulo) ?></span>
</nav>

<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <h1 class="h2 mb-2"><?= escapar_html($titulo) ?></h1>
                <p class="text-secondary mb-4">Completa los datos comerciales del cliente.</p>

                <?php if ($errores !== []): ?>
                    <div class="alert alert-danger" role="alert" data-error-cliente-respaldo>
                        Revisa los campos señalados antes de guardar.
                    </div>
                <?php endif; ?>

                <form action="/clientes/formulario.php<?= $esEdicion ? '?id=' . $id : '' ?>" method="post">
                    <input type="hidden" name="token_csrf" value="<?= escapar_html(token_csrf()) ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-8 col-sm-9">
                            <label for="nit_numero" class="form-label">Número del NIT</label>
                            <input id="nit_numero" name="nit_numero" type="text" inputmode="numeric" pattern="[1-9][0-9]{0,14}" class="form-control <?= isset($errores['nit']) ? 'is-invalid' : '' ?>" value="<?= escapar_html($datos['nit_numero']) ?>" maxlength="15" placeholder="900123456" autocomplete="off" aria-describedby="ayuda-nit<?= isset($errores['nit']) ? ' error-nit' : '' ?>" required>
                        </div>
                        <div class="col-4 col-sm-3">
                            <label for="nit_dv" class="form-label">DV</label>
                            <input id="nit_dv" name="nit_dv" type="text" inputmode="numeric" pattern="[0-9]" class="form-control <?= isset($errores['nit']) ? 'is-invalid' : '' ?>" value="<?= escapar_html($datos['nit_dv']) ?>" maxlength="1" placeholder="8" autocomplete="off" aria-describedby="ayuda-nit<?= isset($errores['nit']) ? ' error-nit' : '' ?>" required>
                        </div>
                        <?php if (isset($errores['nit'])): ?><div id="error-nit" class="text-danger small"><?= escapar_html($errores['nit']) ?></div><?php endif; ?>
                        <div id="ayuda-nit" class="form-text">DV significa dígito de verificación. Escribe solo números; el guion se agrega al guardar.</div>
                    </div>

                    <div class="mb-3">
                        <label for="razon_social" class="form-label">Razón social</label>
                        <input id="razon_social" name="razon_social" type="text" class="form-control <?= isset($errores['razon_social']) ? 'is-invalid' : '' ?>" value="<?= escapar_html($datos['razon_social']) ?>" maxlength="150" required>
                        <?php if (isset($errores['razon_social'])): ?><div class="invalid-feedback"><?= escapar_html($errores['razon_social']) ?></div><?php endif; ?>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="tipo" class="form-label">Tipo de cliente</label>
                            <select id="tipo" name="tipo" class="form-select <?= isset($errores['tipo']) ? 'is-invalid' : '' ?>" required>
                                <option value="" <?= $datos['tipo'] === '' ? 'selected' : '' ?>>Seleccionar</option>
                                <option value="minorista" <?= $datos['tipo'] === 'minorista' ? 'selected' : '' ?>>Minorista</option>
                                <option value="mayorista" <?= $datos['tipo'] === 'mayorista' ? 'selected' : '' ?>>Mayorista</option>
                            </select>
                            <?php if (isset($errores['tipo'])): ?><div class="invalid-feedback"><?= escapar_html($errores['tipo']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="cupo_credito" class="form-label">Cupo de crédito (COP)</label>
                            <input id="cupo_credito" name="cupo_credito" type="text" inputmode="decimal" class="form-control <?= isset($errores['cupo_credito']) ? 'is-invalid' : '' ?>" value="<?= escapar_html($cupoFormulario) ?>" maxlength="20" placeholder="0" required>
                            <?php if (isset($errores['cupo_credito'])): ?><div class="invalid-feedback"><?= escapar_html($errores['cupo_credito']) ?></div><?php endif; ?>
                            <div class="form-text">Sin separadores de miles; se aceptan coma o punto decimal.</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="ciudad" class="form-label">Ciudad</label>
                        <input id="ciudad" name="ciudad" type="text" class="form-control <?= isset($errores['ciudad']) ? 'is-invalid' : '' ?>" value="<?= escapar_html($datos['ciudad']) ?>" maxlength="80" required>
                        <?php if (isset($errores['ciudad'])): ?><div class="invalid-feedback"><?= escapar_html($errores['ciudad']) ?></div><?php endif; ?>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary"><?= $esEdicion ? 'Guardar cambios' : 'Crear cliente' ?></button>
                        <a href="/clientes/index.php<?= $esEdicion && (int) $clienteActual['activo'] === 0 ? '?estado=inactivos' : '' ?>" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php if ($errores !== []): ?>
<dialog id="dialogo-error-cliente" class="dialogo-confirmacion aviso-error dialogo-error-cliente"
    aria-labelledby="titulo-error-cliente" aria-describedby="descripcion-error-cliente">
    <span class="icono-confirmacion" aria-hidden="true">
        <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M24 5a19 19 0 1 0 0 38 19 19 0 0 0 0-38Zm0 11v12m0 5v1"/></svg>
    </span>
    <h2 id="titulo-error-cliente">Revisa los datos del cliente</h2>
    <div id="descripcion-error-cliente" class="text-start mb-4">
        <p class="mb-2">Corrige estos datos para guardar:</p>
        <ul class="mb-0 ps-3">
            <?php foreach ($errores as $mensaje): ?>
                <li class="mb-2"><?= escapar_html($mensaje) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <form method="dialog" class="acciones-confirmacion">
        <button type="submit" class="btn btn-primary" autofocus>Entendido</button>
    </form>
</dialog>
<?php endif; ?>
<script src="/assets/js/clientes.js?v=2" defer></script>
<?php mostrar_pie(true); ?>
