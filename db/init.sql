-- =====================================================================
-- Eciglogística · Mini-sistema de categorización de consultas con IA
-- Se ejecuta automáticamente la PRIMERA vez que arranca el contenedor MySQL.
-- Para volver a ejecutarlo: docker compose down -v && docker compose up
-- =====================================================================

SET NAMES utf8mb4;  -- asegura que tildes y ñ de los INSERT se guarden bien

CREATE TABLE IF NOT EXISTS consultas (
    id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,

    -- Quién escribe: en Eciglogística los clientes son tiendas (B2B)
    nombre_tienda  VARCHAR(120)  NOT NULL,
    email          VARCHAR(255)  NOT NULL,

    -- La consulta en sí. TEXT porque un mensaje puede ser largo;
    -- el límite real (p. ej. 2000 caracteres) lo valida el backend.
    texto          TEXT          NOT NULL,

    -- NULL mientras la IA no ha respondido. ENUM: la BD rechaza
    -- cualquier categoría que no sea una de estas tres.
    categoria      ENUM('Soporte Técnico', 'Duda Comercial', 'Agradecimiento') NULL,

    -- Ciclo de vida: pendiente -> clasificada | error
    estado         ENUM('pendiente', 'clasificada', 'error') NOT NULL DEFAULT 'pendiente',

    -- Trazabilidad: qué clasificó la consulta (modelo de IA o 'simulado')
    clasificado_por VARCHAR(80)  NULL,
    error_msg      VARCHAR(255)  NULL,

    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at   DATETIME      NULL,

    PRIMARY KEY (id),
    INDEX idx_categoria (categoria),   -- para filtrar por categoría
    INDEX idx_estado (estado),         -- para buscar pendientes / errores
    INDEX idx_created (created_at)     -- para ordenar por fecha
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Datos de prueba: consultas realistas de tiendas clientes
-- ---------------------------------------------------------------------
INSERT INTO consultas
    (nombre_tienda, email, texto, categoria, estado, clasificado_por, error_msg, created_at, processed_at)
VALUES
    ('Vape Shop Alzira', 'pedidos@vapeshopalzira.es',
     'Hola, en el pedido de ayer nos han llegado 5 pods que no cargan con ningún cable. ¿Cómo gestionamos la garantía?',
     'Soporte Técnico', 'clasificada', 'simulado', NULL,
     '2026-11-03 09:12:00', '2026-11-03 09:12:02'),

    ('Estanco Nº 14 Burjassot', 'estanco14@gmail.com',
     'Buenas, ¿tenéis precios por volumen en sales de nicotina de 10 ml? Queremos ampliar la sección de vapeo.',
     'Duda Comercial', 'clasificada', 'simulado', NULL,
     '2026-11-04 11:30:00', '2026-11-04 11:30:01'),

    ('Grow & Vape Torrent', 'info@growvapetorrent.com',
     'Ha llegado todo en menos de 24 h y perfectamente embalado. ¡Mil gracias, como siempre!',
     'Agradecimiento', 'clasificada', 'simulado', NULL,
     '2026-11-05 16:45:00', '2026-11-05 16:45:01'),

    ('Shisha Point Valencia', 'compras@shishapoint.es',
     '¿Podéis enviarme el EC-ID de los líquidos de 50 ml que pedimos la semana pasada? Nos lo pide una inspección.',
     NULL, 'error', NULL, 'Servicio de IA no disponible (timeout)',
     '2026-11-06 10:05:00', '2026-11-06 10:05:15');
