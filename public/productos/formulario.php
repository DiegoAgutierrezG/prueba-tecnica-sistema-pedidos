<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/vista.php';
require_once __DIR__ . '/funciones.php';

$usuario = exigir_permiso('productos.administrar');
$idRecibido = $_GET['id'] ?? null;
$esEdicion = $idRecibido !== null;

if ($esEdicion && (!is_string($idRecibido) || !ctype_digit($idRecibido) || (int) $idRecibido < 1)) {
    mostrar_error(404, 'Producto no encontrado', 'El producto solicitado no existe. Selecciona otro desde el catálogo.', $usuario);
}

$id = $esEdicion ? (int) $idRecibido : null;

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    mostrar_error(405, 'Método no permitido', 'Usa el formulario para guardar el producto.', $usuario);
}

$datos = [
    'sku' => '',
    'nombre' => '',
    'categoria_id' => '',
    'precio_lista' => '0',
    'exento_iva' => '0',
    'stock_minimo' => '0',
    'stock_inicial' => '0',
];
$errores = [];
$productoActual = null;

try {
    $conexion = conectar_bd();
    $consultaCategorias = $conexion->prepare('SELECT id, nombre FROM categorias ORDER BY nombre, id');
    $consultaCategorias->execute();
    $categorias = $consultaCategorias->fetchAll();

    if ($esEdicion) {
        $consulta = $conexion->prepare(
            'SELECT id, sku, nombre, categoria_id, precio_lista, exento_iva,
                    stock, stock_minimo, activo FROM productos WHERE id = :id LIMIT 1'
        );
        $consulta->execute(['id' => $id]);
        $productoActual = $consulta->fetch();

        if ($productoActual === false) {
            mostrar_error(404, 'Producto no encontrado', 'El producto solicitado no existe. Selecciona otro desde el catálogo.', $usuario);
        }

        foreach (['sku', 'nombre', 'categoria_id', 'precio_lista', 'exento_iva', 'stock_minimo'] as $campo) {
            $datos[$campo] = (string) $productoActual[$campo];
        }
    }
} catch (Throwable $error) {
    error_log((string) $error);
    mostrar_error(503, 'Servicio no disponible', 'No se pudo cargar el formulario de productos. Vuelve a la lista e inténtalo de nuevo en unos minutos.', $usuario);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token_csrf'] ?? null;
    if (!csrf_valido(is_string($token) ? $token : null)) {
        mostrar_error(403, 'Formulario vencido', 'Recarga la página e intenta guardar el producto de nuevo.', $usuario);
    }

    [$datos, $errores] = validar_datos_producto($_POST, $categorias, !$esEdicion);

    if ($errores === []) {
        try {
            $parametros = [
                'sku' => $datos['sku'],
                'nombre' => $datos['nombre'],
                'categoria_id' => (int) $datos['categoria_id'],
                'precio_lista' => $datos['precio_lista'],
                'exento_iva' => (int) $datos['exento_iva'],
                'stock_minimo' => $datos['stock_minimo'],
            ];

            if ($esEdicion) {
                $consulta = $conexion->prepare(
                    'UPDATE productos SET sku = :sku, nombre = :nombre, categoria_id = :categoria_id,
                     precio_lista = :precio_lista, exento_iva = :exento_iva,
                     stock_minimo = :stock_minimo WHERE id = :id'
                );
                $parametros['id'] = $id;
                $consulta->execute($parametros);
            } else {
                $conexion->beginTransaction();
                $consulta = $conexion->prepare(
                    'INSERT INTO productos
                     (sku, nombre, categoria_id, precio_lista, exento_iva, stock, stock_minimo)
                     VALUES (:sku, :nombre, :categoria_id, :precio_lista, :exento_iva, :stock, :stock_minimo)'
                );
                $consulta->execute($parametros + ['stock' => $datos['stock_inicial']]);
                $productoId = (int) $conexion->lastInsertId();

                if ($datos['stock_inicial'] !== '0') {
                    $movimiento = $conexion->prepare(
                        "INSERT INTO movimientos_inventario
                         (producto_id, tipo, cantidad, pedido_id, usuario_id, creado_en)
                         VALUES (:producto_id, 'entrada', :cantidad, NULL, :usuario_id, UTC_TIMESTAMP(6))"
                    );
                    $movimiento->execute([
                        'producto_id' => $productoId,
                        'cantidad' => $datos['stock_inicial'],
                        'usuario_id' => $usuario['id'],
                    ]);
                }

                $conexion->commit();
            }

            $_SESSION['aviso_productos'] = $esEdicion
                ? 'Producto actualizado correctamente.' : 'Producto creado correctamente.';
            header('Location: /productos/index.php' . ($esEdicion && (int) $productoActual['activo'] === 0 ? '?estado=inactivos' : ''), true, 303);
            exit;
        } catch (PDOException $error) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            if (($error->errorInfo[1] ?? null) === 1062) {
                $errores['sku'] = 'Este SKU ya pertenece a otro producto. Escribe uno distinto o busca el producto existente.';
            } elseif (($error->errorInfo[1] ?? null) === 1452) {
                $errores['categoria_id'] = 'La categoría seleccionada ya no está disponible. Selecciona otra de la lista.';
            } else {
                error_log((string) $error);
                mostrar_error(503, 'Servicio no disponible', 'No se pudo confirmar el guardado del producto. Revisa el catálogo antes de intentarlo de nuevo.', $usuario);
            }
        } catch (Throwable $error) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            error_log((string) $error);
            mostrar_error(503, 'Servicio no disponible', 'No se pudo confirmar el guardado del producto. Revisa el catálogo antes de intentarlo de nuevo.', $usuario);
        }
    }
}

$titulo = $esEdicion ? 'Editar producto' : 'Nuevo producto';
$precioFormulario = preg_replace('/^([0-9]+)\.00$/', '$1', $datos['precio_lista']);
mostrar_cabecera($titulo, $usuario, false, 'productos');
?>
<nav aria-label="Ruta de navegación" class="mb-4">
    <a href="/index.php" class="text-decoration-none">Inicio</a>
    <span class="text-secondary"> / </span>
    <a href="/productos/index.php" class="text-decoration-none">Productos</a>
    <span class="text-secondary"> / <?= escapar_html($titulo) ?></span>
</nav>

<div class="row justify-content-center">
    <div class="col-lg-9 col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <h1 class="h2 mb-2"><?= escapar_html($titulo) ?></h1>
                <p class="text-secondary mb-4">Completa los datos del catálogo y del inventario.</p>

                <?php if ($errores !== []): ?>
                    <div class="alert alert-danger" role="alert">Revisa los campos señalados antes de guardar.</div>
                <?php endif; ?>
                <?php if ($esEdicion && (int) $productoActual['activo'] === 0): ?>
                    <div class="alert alert-warning" role="status">Este producto está inactivo. Puedes editar sus datos o reactivarlo desde el listado de inactivos.</div>
                <?php endif; ?>

                <form action="/productos/formulario.php<?= $esEdicion ? '?id=' . $id : '' ?>" method="post">
                    <input type="hidden" name="token_csrf" value="<?= escapar_html(token_csrf()) ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="sku" class="form-label">SKU</label>
                            <input id="sku" name="sku" type="text" class="form-control <?= isset($errores['sku']) ? 'is-invalid' : '' ?>" value="<?= escapar_html($datos['sku']) ?>" maxlength="20" placeholder="HM001" required>
                            <?php if (isset($errores['sku'])): ?><div class="invalid-feedback"><?= escapar_html($errores['sku']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-8">
                            <label for="nombre" class="form-label">Nombre</label>
                            <input id="nombre" name="nombre" type="text" class="form-control <?= isset($errores['nombre']) ? 'is-invalid' : '' ?>" value="<?= escapar_html($datos['nombre']) ?>" maxlength="150" required>
                            <?php if (isset($errores['nombre'])): ?><div class="invalid-feedback"><?= escapar_html($errores['nombre']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="categoria_id" class="form-label">Categoría</label>
                            <select id="categoria_id" name="categoria_id" class="form-select <?= isset($errores['categoria_id']) ? 'is-invalid' : '' ?>" required>
                                <option value="">Seleccionar</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?= (int) $categoria['id'] ?>" <?= $datos['categoria_id'] === (string) $categoria['id'] ? 'selected' : '' ?>><?= escapar_html($categoria['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errores['categoria_id'])): ?><div class="invalid-feedback"><?= escapar_html($errores['categoria_id']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="precio_lista" class="form-label">Precio de lista sin IVA (COP)</label>
                            <input id="precio_lista" name="precio_lista" type="text" inputmode="decimal" class="form-control <?= isset($errores['precio_lista']) ? 'is-invalid' : '' ?>" value="<?= escapar_html($precioFormulario) ?>" maxlength="20" placeholder="28000" required>
                            <?php if (isset($errores['precio_lista'])): ?><div class="invalid-feedback"><?= escapar_html($errores['precio_lista']) ?></div><?php endif; ?>
                            <div class="form-text">Se aceptan coma o punto decimal, sin separadores de miles.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="exento_iva" class="form-label">IVA</label>
                            <select id="exento_iva" name="exento_iva" class="form-select <?= isset($errores['exento_iva']) ? 'is-invalid' : '' ?>" required>
                                <option value="0" <?= $datos['exento_iva'] === '0' ? 'selected' : '' ?>>Gravado (19 %)</option>
                                <option value="1" <?= $datos['exento_iva'] === '1' ? 'selected' : '' ?>>Exento (0 %)</option>
                            </select>
                            <?php if (isset($errores['exento_iva'])): ?><div class="invalid-feedback"><?= escapar_html($errores['exento_iva']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="stock_minimo" class="form-label">Stock mínimo</label>
                            <input id="stock_minimo" name="stock_minimo" type="text" inputmode="numeric" pattern="[0-9]+" class="form-control <?= isset($errores['stock_minimo']) ? 'is-invalid' : '' ?>" value="<?= escapar_html($datos['stock_minimo']) ?>" maxlength="10" required>
                            <?php if (isset($errores['stock_minimo'])): ?><div class="invalid-feedback"><?= escapar_html($errores['stock_minimo']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <?php if ($esEdicion): ?>
                        <div class="alert alert-info mb-4" role="note">Stock actual: <strong><?= (int) $productoActual['stock'] ?> unidades</strong>. Para reponer existencias, usa <a href="/productos/entrada.php" class="alert-link">Añadir stock</a>.</div>
                    <?php else: ?>
                        <div class="mb-4">
                            <label for="stock_inicial" class="form-label">Stock inicial</label>
                            <input id="stock_inicial" name="stock_inicial" type="text" inputmode="numeric" pattern="[0-9]+" class="form-control <?= isset($errores['stock_inicial']) ? 'is-invalid' : '' ?>" value="<?= escapar_html($datos['stock_inicial']) ?>" maxlength="10" required>
                            <?php if (isset($errores['stock_inicial'])): ?><div class="invalid-feedback"><?= escapar_html($errores['stock_inicial']) ?></div><?php endif; ?>
                            <div class="form-text">Si es mayor que cero, se registra una entrada de inventario junto con el producto.</div>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary"><?= $esEdicion ? 'Guardar cambios' : 'Crear producto' ?></button>
                        <a href="/productos/index.php<?= $esEdicion && (int) $productoActual['activo'] === 0 ? '?estado=inactivos' : '' ?>" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php mostrar_pie(true); ?>
