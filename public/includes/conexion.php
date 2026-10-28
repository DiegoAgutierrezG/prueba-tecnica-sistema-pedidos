<?php

declare(strict_types=1);

function conectar_bd(): PDO
{
    $host = getenv('DB_HOST');
    $puerto = getenv('DB_PORT');
    $nombre = getenv('DB_NAME');
    $usuario = getenv('DB_USER');
    $clave = getenv('DB_PASSWORD');
    $archivoClave = getenv('DB_PASSWORD_FILE');

    if (is_string($archivoClave) && $archivoClave !== '') {
        $contenido = @file_get_contents($archivoClave);
        if ($contenido === false) {
            throw new RuntimeException('No se pudo leer la clave de la base de datos.');
        }
        $clave = rtrim($contenido, "\r\n");
    }

    if (!$host || !$puerto || !$nombre || !$usuario || !$clave) {
        throw new RuntimeException('Falta configurar la conexión a la base de datos.');
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $puerto, $nombre);

    return new PDO($dsn, $usuario, $clave, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
