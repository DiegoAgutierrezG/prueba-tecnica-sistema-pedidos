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
    mostrar_error($esEnvio ? 400 : 404, 'Pedido no encontrado', 'No se pudo identificar el pedido.', $usuario);
}

$errorFormulario = '';
$valorFormulario = is_string($_POST['valor'] ?? null) ? trim($_POST['valor']) : '';
$metodoFormulario = is_string($_POST['metodo'] ?? null) ? $_POST['metodo'] : '';

if ($esEnvio) {
    $token = $_POST['token_csrf'] ?? null;
    if (!csrf_valido(is_string($token) ? $token : null)) {
        mostrar_error(403, 'Formulario vencido', 'Recarga el pedido e intenta registrar el pago de nuevo.', $usuario);
    }

    try {
        registrar_pago(conectar_bd(), $id, (int) $usuario['id'], $_POST['valor'] ?? null, $_POST['metodo'] ?? null);
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
        mostrar_error(503, 'Servicio no disponible', 'No se pudo registrar el pago.', $usuario);
    }
}

try {
    $conexion = conectar_bd();
    $pedido = resumen_pedido_pago($conexion, $id);
    if ($pedido === null) {
        mostrar_error(404, 'Pedido no encontrado', 'El pedido solicitado no existe.', $usuario);
    }
    $pagos = historial_pagos($conexion, $id);
    $totalCentavos = centavos_de_decimal($pedido['total']);
    $pagadoCentavos = centavos_de_decimal($pedido['pagado']);
    $saldoCentavos = max(0, $totalCentavos - $pagadoCentavos);
    $estadoAdmitido = in_array($pedido['estado'], ['confirmado', 'despachado', 'entregado'], true);
} catch (Throwable $error) {
    error_log((string) $error);
    mostrar_error(503, 'Servicio no disponible', 'No se pudo consultar el pedido y sus pagos.', $usuario);
}

$aviso = $_SESSION['aviso_pagos'] ?? null;
unset($_SESSION['aviso_pagos']);
mostrar_cabecera('Pago de ' . $pedido['consecutivo'], $usuario, false, 'pagos');
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
        <h1 class="h2 mb-2">Pago de <?= escapar_html($pedido['consecutivo']) ?></h1>
        <p class="text-secondary mb-0"><?= escapar_html($pedido['cliente']) ?> · NIT <?= escapar_html($pedido['nit']) ?></p>
    </div>
    <a href="/pedidos/detalle.php?id=<?= $id ?>" class="btn btn-outline-primary">Ver pedido</a>
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
        <p id="ayuda-valor-pago" class="form-text mt-0 mb-3">Ingresa un valor mayor que cero y hasta <?= escapar_html(mostrar_monto_pago($saldoCentavos)) ?>. Usa coma o punto para los centavos, sin separadores de miles.</p>
        <form action="/pagos/nuevo.php?id=<?= $id ?>" method="post" class="row g-3 align-items-end">
            <input type="hidden" name="token_csrf" value="<?= escapar_html(token_csrf()) ?>">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="col-md-5">
                <label for="valor" class="form-label">Valor del pago (COP)</label>
                <input id="valor" name="valor" type="text" inputmode="decimal" class="form-control<?= $errorFormulario !== '' ? ' is-invalid' : '' ?>" value="<?= escapar_html($valorFormulario) ?>" maxlength="15" placeholder="Ej. 150000,00" autocomplete="off" aria-describedby="ayuda-valor-pago" required>
            </div>
            <div class="col-md-4">
                <label for="metodo" class="form-label">Método</label>
                <select id="metodo" name="metodo" class="form-select<?= $errorFormulario !== '' ? ' is-invalid' : '' ?>" required>
                    <option value="">Selecciona un método</option>
                    <?php foreach (METODOS_PAGO as $codigo => $nombre): ?>
                        <option value="<?= escapar_html($codigo) ?>"<?= $metodoFormulario === $codigo ? ' selected' : '' ?>><?= escapar_html($nombre) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-grid">
                <button type="submit" class="btn btn-primary">Registrar pago</button>
            </div>
        </form>
    </div></div>
<?php endif; ?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4 pb-0"><h2 class="h5 mb-3">Historial de pagos</h2></div>
    <?php if ($pagos === []): ?>
        <p class="px-4 pb-4 text-secondary mb-0">Todavía no hay pagos registrados para este pedido.</p>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th scope="col" class="ps-4">Fecha en Bogotá</th><th scope="col">Método</th><th scope="col">Registrado por</th><th scope="col" class="pe-4">Valor</th></tr></thead>
            <tbody>
                <?php foreach ($pagos as $pago): ?>
                    <tr>
                        <td class="ps-4 text-nowrap"><?= escapar_html(fecha_pedido_bogota($pago['fecha'])) ?></td>
                        <td><?= escapar_html(METODOS_PAGO[$pago['metodo']] ?? $pago['metodo']) ?></td>
                        <td><?= escapar_html($pago['registrado_por']) ?></td>
                        <td class="pe-4 fw-semibold text-nowrap"><?= escapar_html(mostrar_monto_pago(centavos_de_decimal($pago['valor']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
<?php mostrar_pie(true); ?>
