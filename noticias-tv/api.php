<?php
// Devuelve en JSON las últimas noticias para la pantalla.
//   api.php?limite=40&fuentes=1,3

require_once __DIR__ . '/lib/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$limite = max(1, min(200, (int)($_GET['limite'] ?? $CFG['max_noticias'])));
$ids = array_filter(array_map('intval', explode(',', (string)($_GET['fuentes'] ?? ''))));

$sql = 'SELECT n.id, n.titulo, n.enlace, n.resumen, n.imagen, n.fecha_publicacion AS fecha,
               f.nombre AS fuente, f.color
          FROM noticias n JOIN fuentes f ON f.id = n.fuente_id
         WHERE f.activo = 1';
if ($ids) {
    $sql .= ' AND f.id IN (' . implode(',', $ids) . ')';
}
$sql .= ' ORDER BY n.fecha_publicacion DESC, n.id DESC LIMIT ' . $limite;

try {
    $noticias = db()->query($sql)->fetchAll();
    foreach ($noticias as &$n) {
        $n['timestamp'] = $n['fecha'] ? strtotime($n['fecha']) : null;
    }
    echo json_encode(['ok' => true, 'generado' => time(), 'noticias' => $noticias], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo leer la base de datos']);
}
