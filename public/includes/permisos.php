<?php

declare(strict_types=1);

require_once __DIR__ . '/sesion.php';

const PERMISOS_POR_ROL = [
    'administrador' => [
        'usuarios.administrar',
        'clientes.ver', 'clientes.editar', 'clientes.activar',
        'productos.ver', 'productos.administrar',
        'pedidos.ver', 'pedidos.crear', 'pedidos.despachar', 'pedidos.anular',
        'pagos.registrar', 'pagos.devolver', 'reportes.ver',
    ],
    'vendedor' => [
        'clientes.ver', 'clientes.editar',
        'productos.ver',
        'pedidos.ver', 'pedidos.crear',
        'pagos.registrar',
    ],
    'bodega' => [
        'clientes.ver', 'productos.ver',
        'pedidos.ver', 'pedidos.despachar',
    ],
];

const SECCIONES = [
    'clientes' => ['nombre' => 'Clientes', 'permiso' => 'clientes.ver', 'descripcion' => 'Consulta de clientes y cupos de crédito.', 'ruta' => '/clientes/index.php'],
    'productos' => ['nombre' => 'Productos', 'permiso' => 'productos.ver', 'descripcion' => 'Catálogo, precios y existencias.', 'ruta' => '/productos/index.php'],
    'pedidos' => ['nombre' => 'Pedidos', 'permiso' => 'pedidos.ver', 'descripcion' => 'Seguimiento de pedidos y sus estados.', 'ruta' => '/pedidos/index.php'],
    'pagos' => ['nombre' => 'Pagos', 'permiso' => 'pagos.registrar', 'descripcion' => 'Abonos, historial y devoluciones de pedidos.', 'ruta' => '/pagos/index.php'],
    'reportes' => ['nombre' => 'Reportes', 'permiso' => 'reportes.ver', 'descripcion' => 'Ventas del mes, participación por vendedor y cartera.', 'ruta' => '/reportes/index.php'],
];

const ACCIONES = [
    'clientes.editar' => 'Crear y editar clientes',
    'clientes.activar' => 'Activar y desactivar clientes',
    'productos.administrar' => 'Administrar productos',
    'pedidos.crear' => 'Crear y confirmar pedidos',
    'pedidos.despachar' => 'Despachar y entregar pedidos',
    'pedidos.anular' => 'Anular pedidos',
    'pagos.registrar' => 'Registrar pagos',
    'pagos.devolver' => 'Registrar devoluciones',
    'reportes.ver' => 'Consultar ventas y cartera',
];

const SUBOPCIONES_MENU = [
    'clientes' => [
        ['nombre' => 'Nuevo cliente', 'ruta' => '/clientes/formulario.php', 'permiso' => 'clientes.editar'],
    ],
    'productos' => [
        ['nombre' => 'Nuevo producto', 'ruta' => '/productos/formulario.php', 'permiso' => 'productos.administrar'],
        ['nombre' => 'Añadir stock', 'ruta' => '/productos/entrada.php', 'permiso' => 'productos.administrar'],
    ],
    'pedidos' => [
        ['nombre' => 'Nuevo pedido', 'ruta' => '/pedidos/nuevo.php', 'permiso' => 'pedidos.crear'],
    ],
    'pagos' => [
        ['nombre' => 'Historial de pagos', 'ruta' => '/pagos/index.php?vista=historial', 'permiso' => 'pagos.registrar'],
        ['nombre' => 'Devoluciones', 'ruta' => '/pagos/index.php?vista=devoluciones', 'permiso' => 'pagos.devolver'],
        ['nombre' => 'Ver todos los pedidos', 'ruta' => '/pedidos/index.php', 'permiso' => 'pedidos.ver'],
    ],
    'reportes' => [
        ['nombre' => 'Cartera por cliente', 'ruta' => '/reportes/cartera.php', 'permiso' => 'reportes.ver'],
        ['nombre' => 'Productos bajo mínimo', 'ruta' => '/reportes/inventario.php', 'permiso' => 'reportes.ver'],
    ],
];

function tiene_permiso(array $usuario, string $permiso): bool
{
    return in_array($permiso, PERMISOS_POR_ROL[$usuario['codigo_rol']] ?? [], true);
}

function exigir_sesion(): array
{
    try {
        iniciar_sesion();

        if (isset($_SESSION['usuario_id']) && sesion_vencida($_SESSION, time())) {
            cerrar_sesion_vencida();
            header('Location: /login.php', true, 302);
            exit;
        }

        $usuario = usuario_actual();
    } catch (Throwable $error) {
        error_log((string) $error);
        require_once __DIR__ . '/vista.php';
        mostrar_error(503, 'Servicio no disponible', 'No se pudo comprobar la sesión. Inténtalo de nuevo en unos minutos.');
    }

    if ($usuario === null) {
        header('Location: /login.php', true, 302);
        exit;
    }

    return $usuario;
}

function exigir_permiso(string $permiso): array
{
    $usuario = exigir_sesion();

    if (!tiene_permiso($usuario, $permiso)) {
        require_once __DIR__ . '/vista.php';
        mostrar_error(403, 'Acceso denegado', 'Tu usuario no tiene permiso para entrar a esta sección. Si necesitas acceso, solicítalo al administrador.', $usuario);
    }

    return $usuario;
}

function secciones_visibles(array $usuario): array
{
    return array_filter(
        SECCIONES,
        static fn (array $seccion): bool => tiene_permiso($usuario, $seccion['permiso'])
    );
}

function subopciones_visibles(array $usuario, string $seccion): array
{
    return array_filter(
        SUBOPCIONES_MENU[$seccion] ?? [],
        static fn (array $opcion): bool => tiene_permiso($usuario, $opcion['permiso'])
    );
}
