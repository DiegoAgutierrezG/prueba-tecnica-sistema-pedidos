<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/vista.php';
require_once __DIR__ . '/funciones.php';

$usuario = exigir_permiso('productos.administrar');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    mostrar_error(405, 'Método no permitido', 'Usa el formulario para registrar la entrada.', $usuario);
}

$errorFormulario = '';
$filas = [['producto_id' => '', 'cantidad' => '', 'nombre' => '', 'stock_actual' => '']];
$tokenOperacion = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $tokenOperacion = bin2hex(random_bytes(16));
    $pendientes = $_SESSION['entradas_stock_pendientes'] ?? [];
    if (!is_array($pendientes)) $pendientes = [];
    $pendientes[$tokenOperacion] = true;
    $_SESSION['entradas_stock_pendientes'] = array_slice($pendientes, -5, null, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token_csrf'] ?? null;
    if (!csrf_valido(is_string($token) ? $token : null)) {
        mostrar_error(403, 'Formulario vencido', 'Recarga la página e intenta registrar la entrada de nuevo.', $usuario);
    }
    $tokenRecibido = $_POST['token_operacion'] ?? null;
    if (!is_string($tokenRecibido)
        || !isset($_SESSION['entradas_stock_pendientes'][$tokenRecibido])) {
        mostrar_error(409, 'Entrada ya procesada', 'Abre de nuevo el formulario para registrar otra entrada.', $usuario);
    }
    $tokenOperacion = $tokenRecibido;

    $ids = $_POST['producto_id'] ?? null;
    $cantidades = $_POST['cantidad'] ?? null;
    if (is_array($ids) && is_array($cantidades)) {
        $filas = [];
        foreach (array_slice($ids, 0, 30, true) as $posicion => $id) {
            $filas[] = [
                'producto_id' => is_string($id) ? trim($id) : '',
                'cantidad' => is_string($cantidades[$posicion] ?? null) ? trim($cantidades[$posicion]) : '',
                'nombre' => '',
                'stock_actual' => '',
            ];
        }
        if ($filas === []) $filas[] = ['producto_id' => '', 'cantidad' => '', 'nombre' => '', 'stock_actual' => ''];
    }

    try {
        $totalProductos = registrar_entradas_stock(conectar_bd(), (int) $usuario['id'], $ids, $cantidades);
        unset($_SESSION['entradas_stock_pendientes'][$tokenOperacion]);
        $_SESSION['aviso_productos'] = 'Entrada de inventario registrada para ' . $totalProductos
            . ' producto' . ($totalProductos === 1 ? '.' : 's.');
        header('Location: /productos/index.php', true, 303);
        exit;
    } catch (ReglaInventario $error) {
        $errorFormulario = $error->getMessage();
    } catch (Throwable $error) {
        error_log((string) $error);
        mostrar_error(503, 'Servicio no disponible', 'No se pudo registrar la entrada de inventario.', $usuario);
    }

    try {
        $consulta = conectar_bd()->prepare('SELECT sku, nombre, stock FROM productos WHERE id = :id AND activo = 1');
        foreach ($filas as &$fila) {
            $id = filter_var($fila['producto_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) continue;
            $consulta->execute(['id' => $id]);
            $producto = $consulta->fetch();
            if ($producto !== false) {
                $fila['nombre'] = $producto['sku'] . ' · ' . $producto['nombre'];
                $fila['stock_actual'] = (string) $producto['stock'];
            } else {
                $fila['producto_id'] = '';
            }
        }
        unset($fila);
    } catch (Throwable $error) {
        error_log((string) $error);
        mostrar_error(503, 'Servicio no disponible', 'No se pudieron recuperar los productos seleccionados.', $usuario);
    }
}

mostrar_cabecera('Añadir stock', $usuario, false, 'productos');
?>
<nav aria-label="Ruta de navegación" class="mb-4">
    <a href="/index.php" class="text-decoration-none">Inicio</a> <span class="text-secondary">/ </span>
    <a href="/productos/index.php" class="text-decoration-none">Productos</a>
    <span class="text-secondary"> / Añadir stock</span>
</nav>

<div class="row justify-content-center">
    <div class="col-xl-10">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <h1 class="h2 mb-2">Añadir stock</h1>
                <p class="text-secondary mb-4">Agrega productos activos y la cantidad que ingresó al inventario.</p>
                <noscript><div class="alert alert-warning">Activa JavaScript para buscar y agregar productos.</div></noscript>

                <?php if ($errorFormulario !== ''): ?>
                    <div class="alert alert-danger" role="alert"><?= escapar_html($errorFormulario) ?></div>
                <?php endif; ?>

                <form action="/productos/entrada.php" method="post" id="formulario-entrada-stock">
                    <input type="hidden" name="token_csrf" value="<?= escapar_html(token_csrf()) ?>">
                    <input type="hidden" name="token_operacion" value="<?= escapar_html($tokenOperacion) ?>">
                    <div id="lineas-entrada">
                        <?php foreach ($filas as $posicion => $fila): ?>
                            <div class="row g-2 align-items-end mb-3 linea-entrada">
                                <div class="col-md-5 campo-busqueda" data-buscador-producto>
                                    <label for="producto-entrada-<?= $posicion ?>" class="form-label">Producto</label>
                                    <input id="producto-entrada-<?= $posicion ?>" type="search" class="form-control" data-entrada-busqueda
                                        value="<?= escapar_html($fila['nombre']) ?>" placeholder="Escribe SKU o nombre"
                                        autocomplete="off" maxlength="100" role="combobox" aria-autocomplete="list"
                                        aria-controls="resultados-entrada-<?= $posicion ?>" aria-expanded="false">
                                    <input type="hidden" name="producto_id[]" data-id-seleccionado value="<?= escapar_html($fila['producto_id']) ?>">
                                    <div id="resultados-entrada-<?= $posicion ?>" class="resultados-busqueda" data-resultados-busqueda role="listbox" aria-label="Resultados de productos" hidden></div>
                                </div>
                                <div class="col-md-3">
                                    <label for="stock-actual-<?= $posicion ?>" class="form-label">Stock actual</label>
                                    <input id="stock-actual-<?= $posicion ?>" type="text" class="form-control bg-light" data-stock-actual value="<?= escapar_html($fila['stock_actual']) ?>" placeholder="—" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label for="cantidad-entrada-<?= $posicion ?>" class="form-label">Cantidad a añadir</label>
                                    <input id="cantidad-entrada-<?= $posicion ?>" name="cantidad[]" type="text" inputmode="numeric" pattern="[1-9][0-9]*" maxlength="10" class="form-control" value="<?= escapar_html($fila['cantidad']) ?>" placeholder="1">
                                </div>
                                <div class="col-md-1 d-grid"><button type="button" class="btn btn-outline-secondary" data-quitar-linea aria-label="Quitar producto" title="Quitar producto">×</button></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm mt-1" data-agregar-linea>Agregar otro producto</button>
                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">Registrar entradas</button>
                        <a href="/productos/index.php" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>

                <template id="plantilla-linea-entrada">
                    <div class="row g-2 align-items-end mb-3 linea-entrada">
                        <div class="col-md-5 campo-busqueda" data-buscador-producto>
                            <label class="form-label" data-etiqueta-producto>Producto</label>
                            <input type="search" class="form-control" data-entrada-busqueda placeholder="Escribe SKU o nombre"
                                autocomplete="off" maxlength="100" role="combobox" aria-autocomplete="list" aria-expanded="false">
                            <input type="hidden" name="producto_id[]" data-id-seleccionado>
                            <div class="resultados-busqueda" data-resultados-busqueda role="listbox" aria-label="Resultados de productos" hidden></div>
                        </div>
                        <div class="col-md-3"><label class="form-label" data-etiqueta-stock>Stock actual</label><input type="text" class="form-control bg-light" data-stock-actual placeholder="—" readonly></div>
                        <div class="col-md-3"><label class="form-label" data-etiqueta-cantidad>Cantidad a añadir</label><input name="cantidad[]" type="text" inputmode="numeric" pattern="[1-9][0-9]*" maxlength="10" class="form-control" placeholder="1"></div>
                        <div class="col-md-1 d-grid"><button type="button" class="btn btn-outline-secondary" data-quitar-linea aria-label="Quitar producto" title="Quitar producto">×</button></div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
<dialog id="dialogo-confirmar-entrada" class="dialogo-calculo dialogo-resumen-stock" aria-labelledby="titulo-confirmar-entrada" aria-describedby="descripcion-confirmar-entrada">
    <div class="encabezado-calculo">
        <h2 id="titulo-confirmar-entrada" class="h5 mb-0">Confirmar entrada de stock</h2>
    </div>
    <div class="contenido-calculo">
        <p id="descripcion-confirmar-entrada" class="text-secondary mt-3 mb-0"></p>
        <div id="resumen-entrada"></div>
        <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-outline-secondary" data-cancelar-entrada autofocus>Volver</button>
            <button type="button" class="btn btn-primary" data-confirmar-entrada>Confirmar entrada</button>
        </div>
    </div>
</dialog>
<script src="/assets/js/entrada-stock.js?v=4" defer></script>
<?php mostrar_pie(true); ?>
