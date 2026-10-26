<?php
// Recolector de noticias.
//   CLI / cron:  php recolectar.php [id_fuente]
//   Web:         recolectar.php?token=TU_TOKEN[&fuente=ID]

require_once __DIR__ . '/lib/recolector.php';

if (PHP_SAPI !== 'cli') {
    if (!hash_equals($CFG['cron_token'], (string)($_GET['token'] ?? ''))) {
        http_response_code(403);
        exit('Token no válido');
    }
    header('Content-Type: text/plain; charset=utf-8');
    $id = isset($_GET['fuente']) ? (int)$_GET['fuente'] : null;
} else {
    $id = isset($argv[1]) ? (int)$argv[1] : null;
}

set_time_limit(300);
foreach (recolectar($id) as $linea) {
    echo date('[Y-m-d H:i:s] '), $linea, PHP_EOL;
}
