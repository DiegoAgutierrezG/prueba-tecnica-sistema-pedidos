# Sistema de pedidos de Distribuidora Andina

Aplicación interna de pedidos, inventario y pagos para Distribuidora Andina S.A.S. Este repositorio está en desarrollo. Por ahora contiene la infraestructura local y una página de comprobación; los módulos de negocio, el esquema SQL y los datos de prueba siguen pendientes.

## Documentos de referencia

- [Requisitos en PDF](docs/Requerimientos_Aplicativo_Pedidos.pdf) y [en Word](docs/Requerimientos_Aplicativo_Pedidos.docx).
- [Modelo de datos y casos de uso](docs/Diagramas_Aplicativo_Pedidos.html).

Los documentos de `docs/` se conservan sin modificaciones. Las reglas de negocio de la sección 4 rigen los cálculos, el crédito y los movimientos de inventario.

## Entorno local

Se requiere Docker Desktop con contenedores Linux y Docker Compose. No es necesario instalar PHP ni MySQL en el equipo: las imágenes de Docker incluyen PHP 8.2 con Apache y MySQL 8.0. Bootstrap 5.3.8 está incluido localmente en `public/assets/bootstrap/`, junto con su licencia.

Para preparar una copia nueva:

1. Copiar `.env.example` a `.env` y completar las dos claves vacías con valores aleatorios propios.
2. Ejecutar `docker compose up -d` desde la raíz del proyecto.
3. Abrir <http://localhost:8080>. La página actual solo comprueba la conexión entre PHP y MySQL.

En el equipo de desarrollo inicial ya existe un `.env` local con dos claves aleatorias. Git lo ignora; nunca se debe incluir en un commit. El paso de creación de `.env` en una copia nueva sigue pendiente de automatizar para cumplir el requisito final de arranque con un único comando.

## Configuración de contenedores

| Servicio | Imagen | Acceso local | Función |
| --- | --- | --- | --- |
| `app` | `php:8.2-apache` con `pdo_mysql` | <http://localhost:8080> | Sirve `public/` y recibe la configuración de la base mediante variables de entorno. |
| `db` | `mysql:8.0` | `127.0.0.1:3307` | Almacena los datos en el volumen `datos_mysql` e inicializa la base con los scripts de `db/init/` la primera vez. |

`docker-compose.yml` espera `DB_NAME`, `DB_USER`, `DB_PASSWORD` y `MYSQL_ROOT_PASSWORD` del archivo `.env`. `DB_HOST=db` y `DB_PORT=3306` son direcciones internas entre los contenedores. El puerto 3307 solo es para conectar herramientas locales a MySQL. Las variables `TZ` se fijan en `America/Bogota`; el código de fechas deberá aplicar esta zona horaria de forma coherente.

## Estado y próximos pasos

Todavía faltan el esquema SQL, los datos de prueba, las pantallas y permisos de los tres roles, los cálculos de pedidos, las transacciones de inventario y crédito, los pagos, los reportes, las verificaciones funcionales y el despliegue en AWS. La página inicial no representa una entrega funcional. El código, las tablas, los mensajes y la documentación del dominio se mantendrán en español. Los avances se registrarán en commits pequeños con mensajes en español.
