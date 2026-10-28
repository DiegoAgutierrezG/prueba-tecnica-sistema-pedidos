<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/vista.php';
require_once __DIR__ . '/funciones.php';

$usuario = exigir_permiso('pagos.registrar');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    mostrar_error(405, 'Método no permitido', 'Abre el formulario desde la lista de pagos.', $usuario);
}

$esEnvio = $_SERVER['REQUEST_METHOD'] === 'POST';
$id = id_pedido_valido($esEnvio ? ($_POST['id'] ?? null) : ($_GET['id'] ?? null));
if ($id === null) {
    mostrar_error($esEnvio ? 400 : 404, 'Pedido no encontrado', 'No se pudo identificar el pedido. Selecciónalo de nuevo desde Pagos.', $usuario);
}

$errorFormulario = '';
$valorFormulario = is_string($_POST['valor'] ?? null) ? trim($_POST['valor']) : '';
$metodoFormulario = is_string($_POST['metodo'] ?? null) ? $_POST['metodo'] : '';
$datosFormulario = [];
foreach (['banco_transferencia', 'referencia', 'caja', 'banco_cheque', 'numero_cheque'] as $campo) {
    $datosFormulario[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
}

if ($esEnvio) {
    $token = $_POST['token_csrf'] ?? null;
    if (!csrf_valido(is_string($token) ? $token : null)) {
        mostrar_error(403, 'Formulario vencido', 'Recarga el pedido e intenta registrar el pago de nuevo.', $usuario);
    }

    try {
        registrar_pago(conectar_bd(), $id, (int) $usuario['id'], $_POST['valor'] ?? null, $_POST['metodo'] ?? null, $_POST);
        $_SESSION['aviso_pagos'] = 'Pago registrado correctamente.';
        header('Location: /pagos/nuevo.php?id=' . $id, true, 303);
        exit;
    } catch (ReglaPago $error) {
        if ($error->getCode() === 404) {
            mostrar_error(404, 'Pedido no encontrado', $error->getMessage(), $usuario);
        }
        $errorFormulario = $error->getMessage();
        http_response_code($error->getCode() === 409 ? 409 : 422);
    } catch (Throwable $error) {
        error_log((string) $error);
    mostrar_error(503, 'Servicio no disponible', 'No se pudo confirmar el registro del pago. Revisa el historial del pedido antes de intentarlo de nuevo.', $usuario);
    }
}

try {
    $conexion = conectar_bd();
    $pedido = resumen_pedido_pago($conexion, $id);
    if ($pedido === null) {
        mostrar_error(404, 'Pedido no encontrado', 'El pedido solicitado no existe. Selecciona otro desde Pagos.', $usuario);
    }
    $pagos = historial_pagos($conexion, $id);
    $bancos = bancos_activos($conexion);
    $totalCentavos = centavos_de_decimal($pedido['total']);
    $pagadoCentavos = centavos_de_decimal($pedido['pagado']);
    $saldoCentavos = max(0, $totalCentavos - $pagadoCentavos);
    $estadoAdmitido = in_array($pedido['estado'], ['confirmado', 'despachado', 'entregado'], true);
} catch (Throwable $error) {
    error_log((string) $error);
    mostrar_error(503, 'Servicio no disponible', 'No se pudo consultar el pedido y sus pagos. Vuelve a la lista de pagos e inténtalo de nuevo en unos minutos.', $usuario);
}

$aviso = $_SESSION['aviso_pagos'] ?? null;
unset($_SESSION['aviso_pagos']);
mostrar_cabecera('Pagos de ' . $pedido['consecutivo'], $usuario, false, 'pagos');
?>
<nav aria-label="Ruta de navegación" class="mb-4">
    <a href="/index.php" class="text-decoration-none">Inicio</a> <span class="text-secondary">/ </span>
    <a href="/pagos/index.php" class="text-decoration-none">Pagos</a>
    <span class="text-secondary"> / <?= escapar_html($pedido['consecutivo']) ?></span>
</nav>
<?php if (is_string($aviso) && $aviso !== ''): ?>
    <div class="alert alert-success" role="status"><?= escapar_html($aviso) ?></div>
<?php endif; ?>
<?php if ($errorFormulario !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= escapar_html($errorFormulario) ?></div>
<?php endif; ?>
<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h2 mb-2">Pagos de <?= escapar_html($pedido['consecutivo']) ?></h1>
        <p class="text-secondary mb-0"><?= escapar_html($pedido['cliente']) ?> · NIT <?= escapar_html($pedido['nit']) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="/pagos/index.php?vista=historial" class="btn btn-outline-primary">Historial general</a>
        <a href="/pedidos/detalle.php?id=<?= $id ?>" class="btn btn-outline-primary">Ver pedido</a>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4">
        <p class="text-secondary mb-1">Total del pedido</p><strong class="fs-5"><?= escapar_html(mostrar_monto_pago($totalCentavos)) ?></strong>
    </div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4">
        <p class="text-secondary mb-1">Pagado</p><strong class="fs-5"><?= escapar_html(mostrar_monto_pago($pagadoCentavos)) ?></strong>
    </div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4">
        <p class="text-secondary mb-1">Saldo pendiente</p><strong class="fs-5"><?= escapar_html(mostrar_monto_pago($saldoCentavos)) ?></strong>
    </div></div></div>
</div>
<?php if (!$estadoAdmitido): ?>
    <div class="alert alert-info" role="status">Un pedido <?= escapar_html($pedido['estado']) ?> no admite pagos. Los pagos anteriores siguen visibles en el historial.</div>
<?php elseif ($saldoCentavos === 0): ?>
    <div class="alert alert-success" role="status">Este pedido ya está saldado.</div>
<?php else: ?>
    <div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
        <h2 class="h5 mb-2">Registrar abono</h2>
        <p id="ayuda-valor-pago" class="form-text mt-0 mb-3">Ingresa un valor mayor que cero y hasta <?= escapar_html(mostrar_monto_pago($saldoCentavos)) ?>, sin separadores de miles. Si recibiste centavos, puedes usar coma o punto; el historial los mostrará en palabras.</p>
        <form action="/pagos/nuevo.php?id=<?= $id ?>" method="post" class="row g-3" data-formulario-pago>
            <input type="hidden" name="token_csrf" value="<?= escapar_html(token_csrf()) ?>">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="col-md-6">
                <label for="valor" class="form-label">Valor del pago (COP)</label>
                <input id="valor" name="valor" type="text" inputmode="decimal" class="form-control<?= $errorFormulario !== '' ? ' is-invalid' : '' ?>" value="<?= escapar_html($valorFormulario) ?>" maxlength="15" placeholder="Ej. 150000" autocomplete="off" aria-describedby="ayuda-valor-pago" required>
            </div>
            <div class="col-md-6">
                <label for="metodo" class="form-label">Método</label>
                <select id="metodo" name="metodo" class="form-select<?= $errorFormulario !== '' ? ' is-invalid' : '' ?>" required>
                    <option value="">Selecciona un método</option>
                    <?php foreach (METODOS_PAGO as $codigo => $nombre): ?>
                        <option value="<?= escapar_html($codigo) ?>"<?= $metodoFormulario === $codigo ? ' selected' : '' ?>><?= escapar_html($nombre) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 detalle-metodo-pago" data-detalle-metodo="transferencia">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="banco_transferencia" class="form-label">Banco de origen</label>
                        <input id="banco_transferencia" name="banco_transferencia" type="text" list="bancos-pago" class="form-control" maxlength="100" value="<?= escapar_html($datosFormulario['banco_transferencia']) ?>" placeholder="Escribe para buscar un banco" autocomplete="off">
                    </div>
                    <div class="col-md-6">
                        <label for="referencia" class="form-label">Número de referencia</label>
                        <input id="referencia" name="referencia" type="text" class="form-control" maxlength="100" value="<?= escapar_html($datosFormulario['referencia']) ?>" autocomplete="off">
                    </div>
                </div>
            </div>
            <div class="col-12 detalle-metodo-pago" data-detalle-metodo="efectivo">
                <label for="caja" class="form-label">Caja que recibió el dinero</label>
                <input id="caja" name="caja" type="text" class="form-control" maxlength="60" value="<?= escapar_html($datosFormulario['caja']) ?>" placeholder="Ej. Caja principal" autocomplete="off">
            </div>
            <div class="col-12 detalle-metodo-pago" data-detalle-metodo="cheque">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="banco_cheque" class="form-label">Banco emisor</label>
                        <input id="banco_cheque" name="banco_cheque" type="text" list="bancos-pago" class="form-control" maxlength="100" value="<?= escapar_html($datosFormulario['banco_cheque']) ?>" placeholder="Escribe para buscar un banco" autocomplete="off">
                    </div>
                    <div class="col-md-6">
                        <label for="numero_cheque" class="form-label">Número de cheque</label>
                        <input id="numero_cheque" name="numero_cheque" type="text" class="form-control" maxlength="100" value="<?= escapar_html($datosFormulario['numero_cheque']) ?>" autocomplete="off">
                    </div>
                </div>
            </div>
            <datalist id="bancos-pago">
                <?php foreach ($bancos as $banco): ?>
                    <option value="<?= escapar_html($banco['nombre']) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <div class="col-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">Registrar pago</button>
            </div>
        </form>
    </div></div>
<?php endif; ?>
<div id="historial-pagos" class="card border-0 shadow-sm">
    <div class="card-body p-4 pb-0"><h2 class="h5 mb-3">Historial de pagos</h2></div>
    <?php if ($pagos === []): ?>
        <p class="px-4 pb-4 text-secondary mb-0">Todavía no hay pagos registrados para este pedido.</p>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th scope="col" class="ps-4">Fecha en Bogotá</th><th scope="col">Método</th><th scope="col">Detalle</th><th scope="col">Registrado por</th><th scope="col" class="pe-4">Valor</th></tr></thead>
            <tbody>
                <?php foreach ($pagos as $pago): ?>
                    <tr>
                        <td class="ps-4 text-nowrap"><?= escapar_html(fecha_pedido_bogota($pago['fecha'])) ?></td>
                        <td><?= escapar_html(METODOS_PAGO[$pago['metodo']] ?? $pago['metodo']) ?></td>
                        <td><?= escapar_html(descripcion_detalle_pago($pago)) ?></td>
                        <td><?= escapar_html($pago['registrado_por']) ?></td>
                        <td class="pe-4 fw-semibold text-nowrap"><?= escapar_html(mostrar_monto_pago(centavos_de_decimal($pago['valor']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
<script src="/assets/js/pagos.js?v=1" defer></script>
<?php mostrar_pie(true); ?>
