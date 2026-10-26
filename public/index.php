<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/conexion.php';

$baseDisponible = false;

try {
    conectar_bd()->query('SELECT 1');
    $baseDisponible = true;
} catch (Throwable $error) {
    error_log((string) $error);
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistema de pedidos · Distribuidora Andina</title>
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
</head>
<body>
    <main class="container py-5">
        <h1 class="mb-3">Sistema de pedidos</h1>
        <p class="lead">Distribuidora Andina · preparación del entorno</p>
        <div class="alert alert-<?= $baseDisponible ? 'success' : 'warning' ?>" role="status">
            <?= $baseDisponible
                ? 'PHP y MySQL están conectados.'
                : 'No se pudo conectar con la base de datos. Revisa la configuración y los registros del servidor.' ?>
        </div>
        <p>Los módulos funcionales de la prueba técnica todavía están en desarrollo.</p>
    </main>
</body>
</html>
