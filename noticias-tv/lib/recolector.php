<?php
// Funciones para descargar y extraer noticias de feeds RSS/Atom y páginas HTML.

require_once __DIR__ . '/db.php';

/** Descarga una URL y devuelve [cuerpo, url_final]. Lanza excepción si falla. */
function descargar(string $url): array {
    global $CFG;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => $CFG['timeout'],
        CURLOPT_USERAGENT      => $CFG['user_agent'],
        CURLOPT_ENCODING       => '',
        CURLOPT_HTTPHEADER     => ['Accept: application/rss+xml, application/atom+xml, application/xml, text/html;q=0.9, */*;q=0.8'],
    ]);
    $cuerpo = curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $final  = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
    $error  = curl_error($ch);
    curl_close($ch);
    if ($cuerpo === false || $codigo >= 400) {
        throw new RuntimeException($error ?: "HTTP $codigo");
    }
    return [$cuerpo, $final];
}

function es_feed(string $cuerpo): bool {
    $inicio = strtolower(substr(ltrim($cuerpo), 0, 1000));
    return str_contains($inicio, '<rss') || str_contains($inicio, '<feed') || str_contains($inicio, '<rdf:rdf');
}

/** Convierte una URL relativa en absoluta respecto a $base. */
function url_absoluta(string $url, string $base): string {
    $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
    if ($url === '' || preg_match('~^[a-z][a-z0-9+.-]*:~i', $url)) {
        return $url;
    }
    $p = parse_url($base);
    $origen = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '');
    if (str_starts_with($url, '//')) {
        return ($p['scheme'] ?? 'https') . ':' . $url;
    }
    if (str_starts_with($url, '/')) {
        return $origen . $url;
    }
    $dir = preg_replace('~/[^/]*$~', '/', $p['path'] ?? '/');
    return $origen . $dir . $url;
}

function limpiar_texto(?string $s, int $max = 0): string {
    $s = html_entity_decode(strip_tags((string)$s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    if ($max > 0 && mb_strlen($s) > $max) {
        $s = rtrim(mb_substr($s, 0, $max - 1)) . '…';
    }
    return $s;
}

function fecha_mysql(?string $s): ?string {
    $t = $s ? strtotime(trim($s)) : false;
    return $t ? date('Y-m-d H:i:s', $t) : null;
}

/** Primera imagen <img src> dentro de un fragmento HTML. */
function primera_imagen(?string $html): ?string {
    if ($html && preg_match('~<img[^>]+src=["\']([^"\']+)["\']~i', $html, $m)) {
        return $m[1];
    }
    return null;
}

/** Extrae noticias de un feed RSS 2.0, RSS 1.0 (RDF) o Atom. */
function parsear_feed(string $xml, string $base): array {
    $prev = libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
    libxml_use_internal_errors($prev);
    if ($doc === false) {
        throw new RuntimeException('XML no válido');
    }
    $items = [];
    $nodos = $doc->channel->item ?? null;
    if (!$nodos || !count($nodos)) {
        $nodos = $doc->item ?? null;           // RSS 1.0
    }
    if (!$nodos || !count($nodos)) {
        $nodos = $doc->entry ?? null;          // Atom
    }
    foreach ($nodos ?? [] as $it) {
        $ns = $it->getNamespaces(true);
        $media   = isset($ns['media'])   ? $it->children($ns['media'])   : null;
        $content = isset($ns['content']) ? $it->children($ns['content']) : null;
        $dc      = isset($ns['dc'])      ? $it->children($ns['dc'])      : null;

        // Enlace: RSS <link>texto</link> o Atom <link href rel="alternate">.
        $enlace = trim((string)$it->link);
        if ($enlace === '' && isset($it->link)) {
            foreach ($it->link as $l) {
                $rel = (string)$l['rel'];
                if ($rel === '' || $rel === 'alternate') {
                    $enlace = (string)$l['href'];
                    break;
                }
            }
        }
        if ($enlace === '') {
            $enlace = trim((string)$it->guid);
        }

        $descripcion = (string)($it->description ?? '') ?: (string)($it->summary ?? '') ?: (string)($it->content ?? '');
        $html = $content ? (string)$content->encoded : '';

        // Imagen: media:content, media:thumbnail, media:group, enclosure o primera <img>.
        $imagen = null;
        if ($media) {
            foreach ([$media->content, $media->thumbnail, $media->group->content ?? null, $media->group->thumbnail ?? null] as $m) {
                if ($m && isset($m[0]) && (string)$m[0]->attributes()->url !== '') {
                    $imagen = (string)$m[0]->attributes()->url;
                    break;
                }
            }
        }
        if (!$imagen && isset($it->enclosure) && str_starts_with((string)$it->enclosure['type'], 'image')) {
            $imagen = (string)$it->enclosure['url'];
        }
        $imagen = $imagen ?: primera_imagen($html) ?: primera_imagen($descripcion);

        $fecha = (string)($it->pubDate ?? '') ?: (string)($it->published ?? '') ?: (string)($it->updated ?? '')
            ?: ($dc ? (string)$dc->date : '');

        $items[] = [
            'titulo'  => limpiar_texto((string)$it->title, 500),
            'enlace'  => url_absoluta($enlace, $base),
            'resumen' => limpiar_texto($descripcion ?: $html, 600),
            'imagen'  => $imagen ? url_absoluta($imagen, $base) : null,
            'fecha'   => fecha_mysql($fecha),
        ];
    }
    return $items;
}

function cargar_html(string $html): DOMXPath {
    $dom = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    // Fuerza UTF-8 si la página no declara codificación.
    if (!preg_match('~<meta[^>]+charset~i', $html)) {
        $html = '<?xml encoding="UTF-8">' . $html;
    }
    $dom->loadHTML($html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    return new DOMXPath($dom);
}

/** Busca un feed RSS/Atom anunciado en el <head> de una página. */
function detectar_feed(string $html, string $base): ?string {
    $xp = cargar_html($html);
    $q = '//link[@rel="alternate"][contains(@type,"rss") or contains(@type,"atom")]/@href';
    foreach ($xp->query($q) as $href) {
        return url_absoluta($href->nodeValue, $base);
    }
    return null;
}

function valor_nodo(DOMXPath $xp, string $expr, DOMNode $ctx): string {
    $res = $xp->evaluate($expr, $ctx);
    if ($res instanceof DOMNodeList) {
        return $res->length ? trim($res->item(0)->nodeValue) : '';
    }
    return trim((string)$res);
}

/**
 * Extrae noticias de una página HTML. Con XPath configurados en la fuente usa esos;
 * si no, busca titulares típicos: <article> y encabezados h1-h3 con enlace.
 */
function parsear_html(string $html, string $base, array $fuente = []): array {
    $xp = cargar_html($html);
    $items = [];
    $vistos = [];

    if (!empty($fuente['xpath_item'])) {
        $nodos  = $xp->query($fuente['xpath_item']);
        $xTit   = $fuente['xpath_titulo'] ?: './/h1|.//h2|.//h3|.//a';
        $xEnl   = $fuente['xpath_enlace'] ?: './/a/@href';
        $xImg   = $fuente['xpath_imagen'] ?: './/img/@src';
        foreach ($nodos ?: [] as $n) {
            $items[] = [
                'titulo' => valor_nodo($xp, $xTit, $n),
                'enlace' => valor_nodo($xp, $xEnl, $n),
                'imagen' => valor_nodo($xp, $xImg, $n),
                'resumen' => valor_nodo($xp, './/p', $n),
            ];
        }
    } else {
        foreach ($xp->query('//article') as $n) {
            $items[] = [
                'titulo'  => valor_nodo($xp, '(.//h1|.//h2|.//h3|.//h4)[1]', $n),
                'enlace'  => valor_nodo($xp, '(.//h1|.//h2|.//h3|.//h4)[1]//a/@href', $n) ?: valor_nodo($xp, './/a/@href', $n),
                'imagen'  => valor_nodo($xp, './/img/@data-src', $n) ?: valor_nodo($xp, './/img/@src', $n),
                'resumen' => valor_nodo($xp, './/p', $n),
            ];
        }
        if (!$items) {
            foreach ($xp->query('//h1[.//a]|//h2[.//a]|//h3[.//a]') as $n) {
                $items[] = [
                    'titulo'  => trim($n->textContent),
                    'enlace'  => valor_nodo($xp, './/a/@href', $n),
                    'imagen'  => '',
                    'resumen' => '',
                ];
            }
        }
    }

    $salida = [];
    foreach ($items as $it) {
        $titulo = limpiar_texto($it['titulo'], 500);
        $enlace = $it['enlace'] ? url_absoluta($it['enlace'], $base) : '';
        // Descarta menús y enlaces cortos que no son titulares.
        if (mb_strlen($titulo) < 15 || !str_starts_with($enlace, 'http') || isset($vistos[$enlace])) {
            continue;
        }
        $vistos[$enlace] = true;
        $img = trim($it['imagen'] ?? '');
        $salida[] = [
            'titulo'  => $titulo,
            'enlace'  => $enlace,
            'resumen' => limpiar_texto($it['resumen'] ?? '', 600),
            'imagen'  => ($img && !str_starts_with($img, 'data:')) ? url_absoluta($img, $base) : null,
            'fecha'   => null,
        ];
    }
    return $salida;
}

/** Obtiene las noticias de una fuente según su tipo. */
function obtener_noticias(array $fuente): array {
    [$cuerpo, $url] = descargar($fuente['url']);
    $tipo = $fuente['tipo'] ?? 'auto';

    if ($tipo === 'rss' || ($tipo === 'auto' && es_feed($cuerpo))) {
        return parsear_feed($cuerpo, $url);
    }
    if ($tipo === 'auto' && ($feed = detectar_feed($cuerpo, $url))) {
        [$xml, $urlFeed] = descargar($feed);
        if (es_feed($xml)) {
            return parsear_feed($xml, $urlFeed);
        }
    }
    return parsear_html($cuerpo, $url, $fuente);
}

/** Guarda noticias nuevas (ignora duplicadas por enlace). Devuelve cuántas insertó. */
function guardar_noticias(int $fuenteId, array $items): int {
    global $CFG;
    $pdo = db();
    $ignorar = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
    $st = $pdo->prepare("$ignorar INTO noticias (fuente_id, hash, titulo, enlace, resumen, imagen, fecha_publicacion, fecha_captura)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $nuevas = 0;
    $ahora = date('Y-m-d H:i:s');
    foreach (array_slice($items, 0, $CFG['max_por_fuente']) as $it) {
        if ($it['titulo'] === '' || $it['enlace'] === '') {
            continue;
        }
        // Evita fechas futuras (zonas horarias mal informadas).
        $fecha = ($it['fecha'] && $it['fecha'] <= $ahora) ? $it['fecha'] : $ahora;
        $st->execute([$fuenteId, sha1($it['enlace']), $it['titulo'], mb_substr($it['enlace'], 0, 1000),
                      $it['resumen'] ?: null, $it['imagen'] ? mb_substr($it['imagen'], 0, 1000) : null, $fecha, $ahora]);
        $nuevas += $st->rowCount();
    }
    return $nuevas;
}

/** Recorre las fuentes activas (o una concreta) y devuelve un registro por fuente. */
function recolectar(?int $soloId = null): array {
    global $CFG;
    $pdo = db();
    $sql = 'SELECT * FROM fuentes WHERE activo = 1' . ($soloId ? ' AND id = ' . (int)$soloId : '');
    $log = [];
    foreach ($pdo->query($sql)->fetchAll() as $f) {
        try {
            $items = obtener_noticias($f);
            $n = guardar_noticias((int)$f['id'], $items);
            $estado = "OK: " . count($items) . " encontradas, $n nuevas";
        } catch (Throwable $e) {
            $estado = 'ERROR: ' . mb_substr($e->getMessage(), 0, 240);
        }
        $pdo->prepare('UPDATE fuentes SET ultima_captura = ?, ultimo_estado = ? WHERE id = ?')
            ->execute([date('Y-m-d H:i:s'), $estado, $f['id']]);
        $log[] = "[{$f['nombre']}] $estado";
    }
    $limite = date('Y-m-d H:i:s', time() - $CFG['dias_conservar'] * 86400);
    $pdo->prepare('DELETE FROM noticias WHERE fecha_captura < ?')->execute([$limite]);
    return $log;
}
