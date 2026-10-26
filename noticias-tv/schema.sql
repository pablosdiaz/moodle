-- Esquema MySQL / MariaDB para Noticias TV
CREATE DATABASE IF NOT EXISTS noticias_tv CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE noticias_tv;

CREATE TABLE IF NOT EXISTS fuentes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(100)  NOT NULL,
    url             VARCHAR(500)  NOT NULL,
    -- auto: detecta RSS/Atom o HTML; rss: fuerza feed; html: fuerza extracción con XPath
    tipo            ENUM('auto','rss','html') NOT NULL DEFAULT 'auto',
    -- Solo para tipo html (opcionales). Si se dejan vacíos se usa detección automática.
    xpath_item      VARCHAR(255)  NULL,
    xpath_titulo    VARCHAR(255)  NULL,
    xpath_enlace    VARCHAR(255)  NULL,
    xpath_imagen    VARCHAR(255)  NULL,
    color           VARCHAR(7)    NOT NULL DEFAULT '#e50914',
    activo          TINYINT(1)    NOT NULL DEFAULT 1,
    ultima_captura  DATETIME      NULL,
    ultimo_estado   VARCHAR(255)  NULL,
    creado          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS noticias (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fuente_id          INT UNSIGNED  NOT NULL,
    hash               CHAR(40)      NOT NULL,
    titulo             VARCHAR(500)  NOT NULL,
    enlace             VARCHAR(1000) NOT NULL,
    resumen            TEXT          NULL,
    imagen             VARCHAR(1000) NULL,
    fecha_publicacion  DATETIME      NULL,
    fecha_captura      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_hash (hash),
    KEY idx_fecha (fecha_publicacion),
    CONSTRAINT fk_noticia_fuente FOREIGN KEY (fuente_id) REFERENCES fuentes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fuentes de ejemplo (puedes editarlas desde admin.php)
INSERT INTO fuentes (nombre, url, tipo, color) VALUES
    ('BBC Mundo',     'https://feeds.bbci.co.uk/mundo/rss.xml',                       'rss',  '#bb1919'),
    ('El País',       'https://feeds.elpais.com/mrss-s/pages/ep/site/elpais.com/portada', 'rss', '#0a5ea8'),
    ('DW Español',    'https://rss.dw.com/xml/rss-sp-all',                            'rss',  '#0b2a4a'),
    ('France 24',     'https://www.france24.com/es/rss',                              'rss',  '#00a1e4');
