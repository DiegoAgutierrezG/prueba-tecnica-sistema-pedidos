<?php

declare(strict_types=1);

require_once __DIR__ . '/sesion.php';

const PERMISOS_POR_ROL = [
    'administrador' => [
        'clientes.ver', 'clientes.editar', 'clientes.activar',
        'productos.ver', 'productos.administrar',
        'pedidos.ver', 'pedidos.crear', 'pedidos.despachar', 'pedidos.anular',
        'pagos.registrar', 'reportes.ver',
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
    'pagos' => ['nombre' => 'Pagos', 'permiso' => 'pagos.registrar', 'descripcion' => 'Registro de abonos a pedidos.', 'ruta' => '/pagos/index.php'],
    'reportes' => ['nombre' => 'Ventas del mes', 'permiso' => 'reportes.ver', 'descripcion' => 'Cantidad de pedidos y total vendido por mes.', 'ruta' => '/reportes/index.php'],
];

const ACCIONES = [
    'clientes.editar' => 'Crear y editar clientes',
    'clientes.activar' => 'Activar y desactivar clientes',
    'productos.administrar' => 'Administrar productos',
    'pedidos.crear' => 'Crear y confirmar pedidos',
    'pedidos.despachar' => 'Despachar y entregar pedidos',
    'pedidos.anular' => 'Anular pedidos',
    'pagos.registrar' => 'Registrar pagos',
    'reportes.ver' => 'Consultar ventas del mes',
];

function tiene_permiso(array $usuario, string $permiso): bool
{
    return in_array($permiso, PERMISOS_POR_ROL[$usuario['codigo_rol']] ?? [], true);
}

function exigir_sesion(): array
{
    iniciar_sesion();

    if (isset($_SESSION['usuario_id']) && sesion_vencida($_SESSION, time())) {
        cerrar_sesion_vencida();
        header('Location: /login.php', true, 302);
        exit;
    }

    $usuario = usuario_actual();

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
        mostrar_error(403, 'Acceso denegado', 'Tu usuario no tiene permiso para entrar a esta sección.', $usuario);
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
