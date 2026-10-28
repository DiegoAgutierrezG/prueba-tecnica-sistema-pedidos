<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/vista.php';
require_once __DIR__ . '/funciones.php';

$usuario = exigir_permiso('pagos.devolver');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    mostrar_error(405, 'Método no permitido', 'Abre el formulario desde el pedido anulado.', $usuario);
}

$esEnvio = $_SERVER['REQUEST_METHOD'] === 'POST';
$id = id_pedido_valido($esEnvio ? ($_POST['id'] ?? null) : ($_GET['id'] ?? null));
if ($id === null) {
    mostrar_error($esEnvio ? 400 : 404, 'Pedido no encontrado', 'No se pudo identificar el pedido.', $usuario);
}

$errorFormulario = '';
$metodoFormulario = is_string($_POST['metodo'] ?? null) ? $_POST['metodo'] : '';
$referenciaFormulario = is_string($_POST['referencia'] ?? null) ? $_POST['referencia'] : '';

if ($esEnvio) {
    $token = $_POST['token_csrf'] ?? null;
    if (!csrf_valido(is_string($token) ? $token : null)) {
        mostrar_error(403, 'Formulario vencido', 'Recarga la página e intenta registrar la devolución de nuevo.', $usuario);
    }

    try {
        registrar_devolucion(conectar_bd(), $id, (int) $usuario['id'], $_POST['metodo'] ?? null, $_POST['referencia'] ?? '');
        $_SESSION['aviso_devoluciones'] = 'Devolución registrada. El dinero entregado al cliente quedó en el historial.';
        header('Location: /pagos/index.php?vista=devoluciones', true, 303);
        exit;
    } catch (ReglaDevolucion $error) {
        if ($error->getCode() === 404) {
            mostrar_error(404, 'Pedido no encontrado', $error->getMessage(), $usuario);
        }
        $errorFormulario = $error->getMessage();
        http_response_code($error->getCode() === 409 ? 409 : 422);
    } catch (Throwable $error) {
        error_log((string) $error);
        mostrar_error(503, 'Servicio no disponible', 'No se pudo registrar la devolución.', $usuario);
    }
}

try {
    $conexion = conectar_bd();
    $pedido = resumen_pedido_pago($conexion, $id);
    if ($pedido === null) {
        mostrar_error(404, 'Pedido no encontrado', 'El pedido solicitado no existe.', $usuario);
    }
    if ($pedido['estado'] !== 'anulado') {
        mostrar_error(409, 'Pedido no anulado', 'Solo se devuelven pagos de pedidos anulados.', $usuario);
    }
    $pagado = centavos_de_decimal($pedido['pagado']);
    if ($pagado === 0) {
        mostrar_error(409, 'Sin pagos', 'Este pedido no tiene pagos por devolver.', $usuario);
    }
    $devolucion = devolucion_pedido($conexion, $id);
} catch (Throwable $error) {
    error_log((string) $error);
    mostrar_error(503, 'Servicio no disponible', 'No se pudo consultar la devolución del pedido.', $usuario);
}

mostrar_cabecera('Devolución de ' . $pedido['consecutivo'], $usuario, false, 'pagos');
?>
<nav aria-label="Ruta de navegación" class="mb-4">
    <a href="/index.php" class="text-decoration-none">Inicio</a> <span class="text-secondary">/ </span>
    <a href="/pagos/index.php" class="text-decoration-none">Pagos</a> <span class="text-secondary">/ </span>
    <a href="/pagos/index.php?vista=devoluciones" class="text-decoration-none">Devoluciones</a>
    <span class="text-secondary"> / <?= escapar_html($pedido['consecutivo']) ?></span>
</nav>
<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h2 mb-2">Devolución de <?= escapar_html($pedido['consecutivo']) ?></h1>
        <p class="text-secondary mb-0"><?= escapar_html($pedido['cliente']) ?> · NIT <?= escapar_html($pedido['nit']) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="/pagos/index.php?vista=devoluciones" class="btn btn-outline-primary">Ver devoluciones</a>
        <a href="/pedidos/detalle.php?id=<?= $id ?>" class="btn btn-outline-secondary">Ver pedido</a>
    </div>
</div>
<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
    <p class="text-secondary mb-1">Total recibido del cliente</p>
    <strong class="fs-4"><?= escapar_html(mostrar_monto_pago($pagado)) ?></strong>
</div></div>
<?php if ($errorFormulario !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= escapar_html($errorFormulario) ?></div>
<?php endif; ?>
<?php if ($devolucion !== null): ?>
    <div class="alert alert-success" role="status">La devolución total ya fue registrada. No se puede registrar una segunda vez.</div>
    <div class="card border-0 shadow-sm"><div class="card-body p-4">
        <h2 class="h5">Constancia de devolución</h2>
        <p class="mb-1">Valor: <?= escapar_html(mostrar_monto_pago(centavos_de_decimal($devolucion['valor']))) ?></p>
        <p class="mb-1">Método: <?= escapar_html(METODOS_PAGO[$devolucion['metodo']] ?? $devolucion['metodo']) ?></p>
        <p class="mb-1">Fecha: <?= escapar_html(fecha_pedido_bogota($devolucion['fecha'])) ?> (Bogotá)</p>
        <p class="mb-1">Registrado por: <?= escapar_html($devolucion['registrado_por']) ?></p>
        <?php if ($devolucion['referencia'] !== null): ?><p class="mb-0">Referencia: <?= escapar_html($devolucion['referencia']) ?></p><?php endif; ?>
    </div></div>
<?php else: ?>
    <div class="alert alert-warning" role="note">
        El dinero sigue pendiente de devolver. Este formulario solo deja constancia de una devolución <strong>ya realizada fuera del sistema</strong>; no hace transferencias ni entrega dinero automáticamente.
    </div>
    <div class="card border-0 shadow-sm"><div class="card-body p-4">
        <h2 class="h5 mb-3">Registrar devolución total realizada</h2>
        <form action="/pagos/devolucion.php?id=<?= $id ?>" method="post" class="row g-3 align-items-end" onsubmit="return confirm('Confirma que ya devolviste todo el importe recibido por este pedido. Esta constancia no transfiere dinero. ¿Registrar la devolución?')">
            <input type="hidden" name="token_csrf" value="<?= escapar_html(token_csrf()) ?>">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="col-md-4">
                <label for="metodo" class="form-label">Método de devolución</label>
                <select id="metodo" name="metodo" class="form-select<?= $errorFormulario !== '' ? ' is-invalid' : '' ?>" required>
                    <option value="">Selecciona un método</option>
                    <?php foreach (METODOS_PAGO as $codigo => $nombre): ?>
                        <option value="<?= escapar_html($codigo) ?>"<?= $metodoFormulario === $codigo ? ' selected' : '' ?>><?= escapar_html($nombre) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label for="referencia" class="form-label">Referencia o comprobante (opcional)</label>
                <input id="referencia" name="referencia" type="text" class="form-control<?= $errorFormulario !== '' ? ' is-invalid' : '' ?>" value="<?= escapar_html($referenciaFormulario) ?>" maxlength="100" autocomplete="off">
            </div>
            <div class="col-md-3 d-grid">
                <button type="submit" class="btn btn-primary">Registrar devolución</button>
            </div>
        </form>
    </div></div>
<?php endif; ?>
<?php mostrar_pie(true); ?>
