-- Ejecutar una vez en instalaciones creadas antes del límite de intentos de ingreso.
CREATE TABLE IF NOT EXISTS intentos_acceso (
    clave CHAR(64) NOT NULL,
    intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ultimo_fallo DATETIME(6) NULL,
    bloqueado_hasta DATETIME(6) NULL,
    PRIMARY KEY (clave),
    KEY idx_intentos_bloqueado (bloqueado_hasta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
