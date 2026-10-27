-- Aplicar una sola vez a la base creada con el esquema anterior.
-- MySQL confirma cada ALTER TABLE de forma implícita; hacer respaldo antes.

CREATE TABLE roles (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(30) NOT NULL,
    nombre VARCHAR(50) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO roles (id, codigo, nombre) VALUES
    (1, 'administrador', 'Administrador'),
    (2, 'vendedor', 'Vendedor'),
    (3, 'bodega', 'Bodega');

ALTER TABLE usuarios
    ADD COLUMN rol_id SMALLINT UNSIGNED NULL AFTER clave_hash;

UPDATE usuarios AS u
INNER JOIN roles AS r ON r.codigo = u.rol
SET u.rol_id = r.id;

-- El esquema anterior restringía rol a esos tres valores mediante ENUM.
-- NOT NULL impide terminar la migración si algún usuario no recibió rol_id.
ALTER TABLE usuarios
    MODIFY COLUMN rol_id SMALLINT UNSIGNED NOT NULL,
    ADD KEY idx_usuarios_rol (rol_id),
    ADD CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id)
        REFERENCES roles (id) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE usuarios
    DROP COLUMN rol;
