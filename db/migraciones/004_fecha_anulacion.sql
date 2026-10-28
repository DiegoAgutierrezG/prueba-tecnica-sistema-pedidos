-- Fecha efectiva de anulación para medir devoluciones pendientes.
-- Aplicar una sola vez en bases existentes. Las reversiones permiten recuperar
-- la fecha de anulaciones anteriores de pedidos confirmados.
ALTER TABLE pedidos
    ADD COLUMN anulado_en DATETIME(6) NULL AFTER creado_en;

UPDATE pedidos AS p
INNER JOIN (
    SELECT pedido_id, MIN(creado_en) AS fecha_anulacion
    FROM movimientos_inventario
    WHERE tipo = 'reversion'
    GROUP BY pedido_id
) AS m ON m.pedido_id = p.id
SET p.anulado_en = m.fecha_anulacion
WHERE p.estado = 'anulado' AND p.anulado_en IS NULL;
