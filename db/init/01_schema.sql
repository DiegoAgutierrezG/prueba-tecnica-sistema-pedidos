-- Esquema inicial de Distribuidora Andina.
-- La imagen de MySQL ejecuta este archivo en la base indicada por MYSQL_DATABASE
-- solo al inicializar un volumen de datos vacío.
-- Los DATETIME se guardarán en UTC; PHP convertirá las fechas a America/Bogota.

SET NAMES utf8mb4;

CREATE TABLE roles (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(30) NOT NULL,
    nombre VARCHAR(50) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(120) NOT NULL,
    clave_hash VARCHAR(255) NOT NULL,
    rol_id SMALLINT UNSIGNED NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_correo (correo),
    KEY idx_usuarios_rol (rol_id),
    CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id)
        REFERENCES roles (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_usuarios_activo CHECK (activo IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE intentos_acceso (
    clave CHAR(64) NOT NULL,
    intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ultimo_fallo DATETIME(6) NULL,
    bloqueado_hasta DATETIME(6) NULL,
    PRIMARY KEY (clave),
    KEY idx_intentos_bloqueado (bloqueado_hasta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE clientes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nit VARCHAR(20) NOT NULL,
    razon_social VARCHAR(150) NOT NULL,
    tipo ENUM('minorista', 'mayorista') NOT NULL,
    cupo_credito DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    ciudad VARCHAR(80) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_clientes_nit (nit),
    KEY idx_clientes_activo (activo),
    CONSTRAINT chk_clientes_cupo CHECK (cupo_credito >= 0),
    CONSTRAINT chk_clientes_activo CHECK (activo IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE categorias (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(80) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categorias_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE productos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sku VARCHAR(20) NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    categoria_id BIGINT UNSIGNED NOT NULL,
    precio_lista DECIMAL(14, 2) NOT NULL,
    exento_iva TINYINT(1) NOT NULL DEFAULT 0,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    stock_minimo INT UNSIGNED NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_productos_sku (sku),
    KEY idx_productos_categoria (categoria_id),
    KEY idx_productos_activo_sku (activo, sku),
    CONSTRAINT fk_productos_categoria FOREIGN KEY (categoria_id)
        REFERENCES categorias (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_productos_precio CHECK (precio_lista >= 0),
    CONSTRAINT chk_productos_exento CHECK (exento_iva IN (0, 1)),
    CONSTRAINT chk_productos_activo CHECK (activo IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Una fila por año reserva el siguiente consecutivo dentro de la transacción.
CREATE TABLE secuencias (
    prefijo VARCHAR(10) NOT NULL,
    anio SMALLINT UNSIGNED NOT NULL,
    ultimo INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (prefijo, anio),
    CONSTRAINT chk_secuencias_ultimo CHECK (ultimo <= 999999)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE pedidos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    consecutivo VARCHAR(20) NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    estado ENUM('borrador', 'confirmado', 'despachado', 'entregado', 'anulado')
        NOT NULL DEFAULT 'borrador',
    orden_compra VARCHAR(40) NULL,
    subtotal DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    descuento DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    iva DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    total DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    creado_en DATETIME(6) NOT NULL,
    anulado_en DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pedidos_consecutivo (consecutivo),
    KEY idx_pedidos_cliente_estado (cliente_id, estado),
    KEY idx_pedidos_usuario (usuario_id),
    KEY idx_pedidos_estado_fecha (estado, creado_en),
    KEY idx_pedidos_fecha (creado_en),
    CONSTRAINT fk_pedidos_cliente FOREIGN KEY (cliente_id)
        REFERENCES clientes (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_pedidos_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_pedidos_subtotal CHECK (subtotal >= 0),
    CONSTRAINT chk_pedidos_descuento CHECK (descuento >= 0),
    CONSTRAINT chk_pedidos_iva CHECK (iva >= 0),
    CONSTRAINT chk_pedidos_total CHECK (total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Precio, descuento e IVA son una instantánea del producto al crear la línea.
CREATE TABLE pedido_detalle (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id BIGINT UNSIGNED NOT NULL,
    producto_id BIGINT UNSIGNED NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    precio_unitario DECIMAL(14, 2) NOT NULL,
    descuento_porcentaje DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    iva_porcentaje DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id),
    KEY idx_detalle_pedido (pedido_id),
    KEY idx_detalle_producto (producto_id),
    CONSTRAINT fk_detalle_pedido FOREIGN KEY (pedido_id)
        REFERENCES pedidos (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_detalle_producto FOREIGN KEY (producto_id)
        REFERENCES productos (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_detalle_cantidad CHECK (cantidad > 0),
    CONSTRAINT chk_detalle_precio CHECK (precio_unitario >= 0),
    CONSTRAINT chk_detalle_descuento CHECK (descuento_porcentaje BETWEEN 0 AND 100),
    CONSTRAINT chk_detalle_iva CHECK (iva_porcentaje BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE pagos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id BIGINT UNSIGNED NOT NULL,
    valor DECIMAL(14, 2) NOT NULL,
    metodo ENUM('transferencia', 'efectivo', 'cheque') NOT NULL,
    fecha DATETIME(6) NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY idx_pagos_pedido (pedido_id),
    KEY idx_pagos_usuario (usuario_id),
    CONSTRAINT fk_pagos_pedido FOREIGN KEY (pedido_id)
        REFERENCES pedidos (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_pagos_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_pagos_valor CHECK (valor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Una devolución se registra solo cuando el dinero de un pedido anulado ya se entregó al cliente.
-- Si el pedido anulado tiene pagos y no tiene devolución, el importe pagado sigue pendiente.
CREATE TABLE devoluciones (
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

CREATE TABLE movimientos_inventario (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    producto_id BIGINT UNSIGNED NOT NULL,
    tipo ENUM('entrada', 'salida', 'reversion') NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    pedido_id BIGINT UNSIGNED NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    creado_en DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_movimientos_producto (producto_id),
    KEY idx_movimientos_pedido (pedido_id),
    KEY idx_movimientos_usuario (usuario_id),
    CONSTRAINT fk_movimientos_producto FOREIGN KEY (producto_id)
        REFERENCES productos (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_movimientos_pedido FOREIGN KEY (pedido_id)
        REFERENCES pedidos (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_movimientos_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_movimientos_cantidad CHECK (cantidad > 0),
    CONSTRAINT chk_movimientos_pedido CHECK (tipo = 'entrada' OR pedido_id IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
