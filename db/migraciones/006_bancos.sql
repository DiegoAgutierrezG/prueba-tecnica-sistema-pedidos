-- Catálogo inicial de bancos para transferencias y cheques.
-- Aplicar una sola vez en bases existentes, después de 005_datos_pago.sql.
SET NAMES utf8mb4;

CREATE TABLE bancos (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bancos_nombre (nombre),
    CONSTRAINT chk_bancos_activo CHECK (activo IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO bancos (nombre) VALUES
    ('Bancolombia'),
    ('Banco de Bogotá'),
    ('Banco Davivienda'),
    ('BBVA Colombia'),
    ('Banco de Occidente'),
    ('Banco Popular'),
    ('Banco Caja Social'),
    ('Banco Agrario de Colombia'),
    ('Banco AV Villas'),
    ('Banco GNB Sudameris'),
    ('Banco Falabella'),
    ('Banco Pichincha'),
    ('Banco W'),
    ('Banco Finandina'),
    ('Bancoomeva'),
    ('Banco Davibank'),
    ('Itaú Colombia'),
    ('Bancamía');

ALTER TABLE pagos
    ADD COLUMN banco_id SMALLINT UNSIGNED NULL AFTER metodo,
    ADD KEY idx_pagos_banco (banco_id),
    ADD CONSTRAINT fk_pagos_banco FOREIGN KEY (banco_id)
        REFERENCES bancos (id) ON DELETE RESTRICT ON UPDATE RESTRICT;

-- Los bancos históricos con nombre idéntico al catálogo pueden enlazarse.
-- Los demás conservan su texto sin inventar una equivalencia.
UPDATE pagos AS p
INNER JOIN bancos AS b ON BINARY p.banco = BINARY b.nombre
SET p.banco_id = b.id
WHERE p.banco_id IS NULL;
