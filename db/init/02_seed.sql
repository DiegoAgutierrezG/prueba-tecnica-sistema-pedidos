-- Datos ficticios de prueba. Se ejecutan después de 01_schema.sql
-- al inicializar una base nueva. Las fechas se guardan en UTC.

SET NAMES utf8mb4;

START TRANSACTION;

-- La contraseña compartida de estas cuentas de demostración se documenta
-- en README.md. Aquí solo se almacena el resultado de password_hash().
INSERT INTO roles (id, codigo, nombre) VALUES
    (1, 'administrador', 'Administrador'),
    (2, 'vendedor', 'Vendedor'),
    (3, 'bodega', 'Bodega');

-- Selección inicial de bancos colombianos; no incluye billeteras ni SEDPE.
-- Nombres contrastados con la Superintendencia Financiera de Colombia (octubre de 2026).
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

INSERT INTO usuarios (id, nombre, correo, clave_hash, rol_id, activo) VALUES
    (1, 'Administrador de prueba', 'admin@andina.co', '$2y$10$hnqJZIHTI9hIvRKTpoOZl.JP/oQEaLDXIw9Zv0DLLDbPWssPECC3.', 1, 1),
    (2, 'Vendedor de prueba', 'vendedor@andina.co', '$2y$10$hnqJZIHTI9hIvRKTpoOZl.JP/oQEaLDXIw9Zv0DLLDbPWssPECC3.', 2, 1),
    (3, 'Bodega de prueba', 'bodega@andina.co', '$2y$10$hnqJZIHTI9hIvRKTpoOZl.JP/oQEaLDXIw9Zv0DLLDbPWssPECC3.', 3, 1);

INSERT INTO categorias (id, nombre) VALUES
    (1, 'Herramientas'),
    (2, 'Material eléctrico'),
    (3, 'Plomería'),
    (4, 'Aseo');

-- Hay 25 productos para probar la segunda página del listado (20 por página).
-- HM001 y ME004 están bajo su stock mínimo; HM001 tiene solo 3 unidades.
-- HM006 supera los 2.000.000 COP y tiene stock suficiente para pedidos grandes.
-- AS005 y AS006 se marcan exentos para comprobar el cálculo de IVA.
INSERT INTO productos
    (sku, nombre, categoria_id, precio_lista, exento_iva, stock, stock_minimo, activo)
VALUES
    ('HM001', 'Martillo de uña', 1, 28000.00, 0, 3, 5, 1),
    ('HM002', 'Taladro percutor', 1, 340000.00, 0, 15, 5, 1),
    ('HM003', 'Juego de destornilladores', 1, 58000.00, 0, 35, 10, 1),
    ('HM004', 'Llave ajustable', 1, 42000.00, 0, 18, 8, 1),
    ('HM005', 'Sierra manual', 1, 32000.00, 0, 24, 6, 1),
    ('HM006', 'Generador eléctrico industrial', 1, 2450000.00, 0, 12, 4, 1),
    ('HM007', 'Pulidora angular', 1, 285000.00, 0, 20, 5, 1),
    ('ME001', 'Cable eléctrico de 100 m', 2, 185000.00, 0, 30, 10, 1),
    ('ME002', 'Tomacorriente doble', 2, 18000.00, 0, 40, 15, 1),
    ('ME003', 'Interruptor sencillo', 2, 13500.00, 0, 32, 10, 1),
    ('ME004', 'Bombillo LED de 12 W', 2, 14500.00, 0, 2, 6, 1),
    ('ME005', 'Tablero eléctrico de 12 circuitos', 2, 298000.00, 0, 14, 4, 1),
    ('ME006', 'Cinta aislante', 2, 7800.00, 0, 50, 20, 1),
    ('PL001', 'Tubo PVC de media pulgada', 3, 24500.00, 0, 28, 10, 1),
    ('PL002', 'Codo PVC de media pulgada', 3, 4300.00, 0, 80, 25, 1),
    ('PL003', 'Llave de paso', 3, 68500.00, 0, 17, 5, 1),
    ('PL004', 'Grifería para lavamanos', 3, 198000.00, 0, 12, 4, 1),
    ('PL005', 'Unión universal', 3, 11800.00, 0, 55, 15, 1),
    ('PL006', 'Sellador de roscas', 3, 16500.00, 0, 31, 10, 1),
    ('AS001', 'Detergente industrial de 5 L', 4, 55000.00, 0, 22, 8, 1),
    ('AS002', 'Desinfectante multiusos de 1 L', 4, 16500.00, 0, 45, 15, 1),
    ('AS003', 'Jabón líquido de 1 L', 4, 12000.00, 0, 42, 12, 1),
    ('AS004', 'Guantes de aseo', 4, 9800.00, 0, 60, 20, 1),
    ('AS005', 'Tapabocas de protección', 4, 19500.00, 1, 40, 12, 1),
    ('AS006', 'Alcohol antiséptico de 1 L', 4, 26500.00, 1, 20, 6, 1);

-- Cada existencia inicial tiene su movimiento de entrada correspondiente.
INSERT INTO movimientos_inventario
    (producto_id, tipo, cantidad, pedido_id, usuario_id, creado_en)
SELECT id, 'entrada', stock, NULL, 1, UTC_TIMESTAMP(6)
FROM productos
WHERE stock > 0;

-- NIT de demostración con dígitos de verificación calculados.
-- Incluye mayoristas y minoristas, tildes, eñes, cupo bajo e inactivo.
INSERT INTO clientes (nit, razon_social, tipo, cupo_credito, ciudad, activo) VALUES
    ('900123456-8', 'Ferretería El Tornillo', 'minorista', 10000000.00, 'Bogotá', 1),
    ('900123457-5', 'Distribuciones La Sabana', 'mayorista', 25000000.00, 'Chía', 1),
    ('900123458-2', 'Ferretería Niño Jesús', 'minorista', 7000000.00, 'Medellín', 1),
    ('900123459-1', 'Comercializadora Andén Norte', 'mayorista', 18000000.00, 'Cali', 1),
    ('900123460-8', 'Suministros San José', 'minorista', 2000000.00, 'Bucaramanga', 1),
    ('900123461-5', 'Distribuidora Río Claro', 'mayorista', 15000000.00, 'Barranquilla', 1),
    ('900123462-2', 'Ferretería La Montaña', 'minorista', 5000000.00, 'Manizales', 0),
    ('900123463-1', 'Almacén Eléctrico Ñandú', 'mayorista', 12000000.00, 'Pasto', 1);

COMMIT;
