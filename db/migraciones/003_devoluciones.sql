-- Ejecutar una vez en instalaciones creadas antes del registro de devoluciones.
CREATE TABLE IF NOT EXISTS devoluciones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id BIGINT UNSIGNED NOT NULL,
    valor DECIMAL(14, 2) NOT NULL,
    metodo ENUM('transferencia', 'efectivo', 'cheque') NOT NULL,
    referencia VARCHAR(100) NULL,
    fecha DATETIME(6) NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_devoluciones_pedido (pedido_id),
    KEY idx_devoluciones_usuario (usuario_id),
    CONSTRAINT fk_devoluciones_pedido FOREIGN KEY (pedido_id)
        REFERENCES pedidos (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_devoluciones_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_devoluciones_valor CHECK (valor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
