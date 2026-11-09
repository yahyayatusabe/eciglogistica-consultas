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

    -- Ayuda al equipo (también la genera la IA):
    urgencia       ENUM('alta', 'media', 'baja') NULL,   -- para priorizar qué atender antes
    resumen        VARCHAR(255)  NULL,                    -- de qué va la consulta, en una línea
    respuesta_sugerida TEXT      NULL,                    -- borrador que una persona revisa antes de enviar

    -- Ciclo de vida: pendiente -> clasificada | error
    estado         ENUM('pendiente', 'clasificada', 'error') NOT NULL DEFAULT 'pendiente',

    -- Trazabilidad: qué clasificó la consulta (modelo de IA o 'simulado')
    clasificado_por VARCHAR(80)  NULL,
    error_msg      VARCHAR(255)  NULL,

    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at   DATETIME      NULL,

    PRIMARY KEY (id),
    INDEX idx_categoria (categoria),   -- para filtrar por categoría
    INDEX idx_urgencia (urgencia),     -- para filtrar las urgentes
    INDEX idx_estado (estado),         -- para buscar pendientes / errores
    INDEX idx_created (created_at)     -- para ordenar por fecha
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Datos de prueba: consultas realistas de tiendas clientes
-- (urgencia, resumen y respuesta escritos a mano como ejemplo del resultado de la IA)
-- ---------------------------------------------------------------------
INSERT INTO consultas
    (nombre_tienda, email, texto, categoria, urgencia, resumen, respuesta_sugerida,
     estado, clasificado_por, error_msg, created_at, processed_at)
VALUES
    ('Vape Shop Alzira', 'pedidos@vapeshopalzira.es',
     'Hola, en el pedido de ayer nos han llegado 5 pods que no cargan con ningún cable. ¿Cómo gestionamos la garantía?',
     'Soporte Técnico', 'alta',
     '5 pods del último pedido no cargan; piden gestionar la garantía.',
     'Hola, equipo de Vape Shop Alzira:\n\nSentimos mucho el problema con los pods. Vamos a revisar vuestro pedido y en breve os indicamos cómo tramitar la garantía y la reposición.\n\nUn saludo,\nAtención al Cliente · Eciglogística',
     'clasificada', 'datos de prueba', NULL,
     '2026-11-03 09:12:00', '2026-11-03 09:12:02'),

    ('Estanco Nº 14 Burjassot', 'estanco14@gmail.com',
     'Buenas, ¿tenéis precios por volumen en sales de nicotina de 10 ml? Queremos ampliar la sección de vapeo.',
     'Duda Comercial', 'media',
     'Piden precios por volumen en sales de nicotina de 10 ml.',
     'Hola, equipo de Estanco Nº 14 Burjassot:\n\n¡Gracias por vuestro interés! Un comercial se pondrá en contacto con vosotros para enviaros las condiciones por volumen de sales de nicotina.\n\nUn saludo,\nAtención al Cliente · Eciglogística',
     'clasificada', 'datos de prueba', NULL,
     '2026-11-04 11:30:00', '2026-11-04 11:30:01'),

    ('Grow & Vape Torrent', 'info@growvapetorrent.com',
     'Ha llegado todo en menos de 24 h y perfectamente embalado. ¡Mil gracias, como siempre!',
     'Agradecimiento', 'baja',
     'Agradecen la entrega rápida y el buen embalaje.',
     'Hola, equipo de Grow & Vape Torrent:\n\n¡Muchas gracias por vuestro mensaje! Nos alegra que todo llegara bien. Aquí estamos para lo que necesitéis.\n\nUn saludo,\nAtención al Cliente · Eciglogística',
     'clasificada', 'datos de prueba', NULL,
     '2026-11-05 16:45:00', '2026-11-05 16:45:01'),

    ('Shisha Point Valencia', 'compras@shishapoint.es',
     '¿Podéis enviarme el EC-ID de los líquidos de 50 ml que pedimos la semana pasada? Nos lo pide una inspección.',
     NULL, NULL, NULL, NULL,
     'error', NULL, 'Servicio de IA no disponible (timeout)',
     '2026-11-06 10:05:00', '2026-11-06 10:05:15');
