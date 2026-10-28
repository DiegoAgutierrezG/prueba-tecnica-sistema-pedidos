-- Datos de soporte por método de pago. Las filas anteriores conservan NULL.
-- Aplicar una sola vez en bases existentes, después de hacer un respaldo.
ALTER TABLE pagos
    ADD COLUMN banco VARCHAR(100) NULL AFTER metodo,
    ADD COLUMN referencia VARCHAR(100) NULL AFTER banco,
    ADD COLUMN caja VARCHAR(60) NULL AFTER referencia,
    ADD COLUMN numero_cheque VARCHAR(100) NULL AFTER caja;
