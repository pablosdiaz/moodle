<?php
// Configuración de Noticias TV.
// Puedes sobreescribir cualquier valor creando config.local.php (no se versiona).

$CFG = [
    // Conexión MySQL.
    'db_host'    => 'localhost',
    'db_name'    => 'noticias_tv',
    'db_user'    => 'root',
    'db_pass'    => '',
    'db_dsn'     => null, // Opcional: DSN PDO completo; si se define, ignora host/nombre.

    // Contraseña del panel admin.php y token para lanzar el recolector por web.
    'admin_pass' => 'cambiar-esta-clave',
    'cron_token' => 'cambiar-este-token',

    // Pantalla.
    'titulo_canal'      => 'NOTICIAS 24H',
    'segundos_slide'    => 12,   // Tiempo que se muestra cada noticia.
    'max_noticias'      => 40,   // Noticias en rotación.
    'refresco_minutos'  => 5,    // Cada cuánto la pantalla pide noticias nuevas.
    'zona_horaria'      => 'America/Argentina/Buenos_Aires',

    // Recolector.
    'max_por_fuente'    => 25,   // Máximo de noticias nuevas por fuente y pasada.
    'dias_conservar'    => 7,    // Se borran noticias más antiguas.
    'timeout'           => 20,
    'user_agent'        => 'Mozilla/5.0 (NoticiasTV/1.0; +https://example.org)',
];

if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

date_default_timezone_set($CFG['zona_horaria']);
