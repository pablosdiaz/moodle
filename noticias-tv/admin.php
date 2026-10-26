<?php
// Panel para gestionar las fuentes de noticias.
require_once __DIR__ . '/lib/recolector.php';

session_start();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

if (isset($_GET['salir'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

if (isset($_POST['clave'])) {
    if (hash_equals($CFG['admin_pass'], (string)$_POST['clave'])) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $errorLogin = 'Contraseña incorrecta';
    }
}

if (empty($_SESSION['admin'])) { ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Noticias TV · Acceso</title><link rel="stylesheet" href="assets/admin.css"></head>
<body class="login"><form method="post" class="tarjeta">
    <h1>Noticias TV</h1>
    <?php if (!empty($errorLogin)): ?><p class="error"><?= h($errorLogin) ?></p><?php endif; ?>
    <input type="password" name="clave" placeholder="Contraseña" autofocus required>
    <button>Entrar</button>
</form></body></html>
<?php exit; }

$pdo = db();
$mensaje = '';
$log = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Token CSRF no válido');
    }
    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($accion === 'guardar') {
        $datos = [
            trim($_POST['nombre'] ?? ''),
            trim($_POST['url'] ?? ''),
            in_array($_POST['tipo'] ?? '', ['auto', 'rss', 'html'], true) ? $_POST['tipo'] : 'auto',
            trim($_POST['xpath_item'] ?? '') ?: null,
            trim($_POST['xpath_titulo'] ?? '') ?: null,
            trim($_POST['xpath_enlace'] ?? '') ?: null,
            trim($_POST['xpath_imagen'] ?? '') ?: null,
            preg_match('/^#[0-9a-f]{6}$/i', $_POST['color'] ?? '') ? $_POST['color'] : '#e50914',
        ];
        if ($datos[0] === '' || !filter_var($datos[1], FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $datos[1])) {
            $mensaje = 'Nombre y URL (http/https) son obligatorios.';
        } elseif ($id) {
            $datos[] = $id;
            $pdo->prepare('UPDATE fuentes SET nombre=?, url=?, tipo=?, xpath_item=?, xpath_titulo=?, xpath_enlace=?, xpath_imagen=?, color=? WHERE id=?')
                ->execute($datos);
            $mensaje = 'Fuente actualizada.';
        } else {
            $pdo->prepare('INSERT INTO fuentes (nombre, url, tipo, xpath_item, xpath_titulo, xpath_enlace, xpath_imagen, color) VALUES (?,?,?,?,?,?,?,?)')
                ->execute($datos);
            $mensaje = 'Fuente añadida.';
        }
    } elseif ($accion === 'alternar') {
        $pdo->prepare('UPDATE fuentes SET activo = 1 - activo WHERE id = ?')->execute([$id]);
    } elseif ($accion === 'borrar') {
        $pdo->prepare('DELETE FROM noticias WHERE fuente_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM fuentes WHERE id = ?')->execute([$id]);
        $mensaje = 'Fuente eliminada.';
    } elseif ($accion === 'recolectar') {
        set_time_limit(300);
        $log = recolectar($id ?: null);
    } elseif ($accion === 'probar') {
        // Vista previa sin guardar: útil para ajustar los XPath.
        try {
            $prueba = obtener_noticias([
                'url' => trim($_POST['url'] ?? ''), 'tipo' => $_POST['tipo'] ?? 'auto',
                'xpath_item' => trim($_POST['xpath_item'] ?? ''), 'xpath_titulo' => trim($_POST['xpath_titulo'] ?? ''),
                'xpath_enlace' => trim($_POST['xpath_enlace'] ?? ''), 'xpath_imagen' => trim($_POST['xpath_imagen'] ?? ''),
            ]);
        } catch (Throwable $e) {
            $mensaje = 'Error al probar: ' . $e->getMessage();
        }
    }
}

$fuentes = $pdo->query('SELECT f.*, (SELECT COUNT(*) FROM noticias n WHERE n.fuente_id = f.id) AS total FROM fuentes f ORDER BY f.nombre')->fetchAll();
$editar = [];
if (isset($_GET['editar'])) {
    $st = $pdo->prepare('SELECT * FROM fuentes WHERE id = ?');
    $st->execute([(int)$_GET['editar']]);
    $editar = $st->fetch() ?: [];
}
if (isset($prueba)) {
    $editar = $_POST; // Conserva lo escrito en el formulario.
}
$csrf = '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Noticias TV · Fuentes</title>
<link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<header>
    <h1>Noticias TV</h1>
    <nav><a href="index.php" target="_blank">Ver pantalla ↗</a> <a href="?salir=1">Salir</a></nav>
</header>
<main>
    <?php if ($mensaje): ?><p class="aviso"><?= h($mensaje) ?></p><?php endif; ?>
    <?php if ($log): ?><pre class="log"><?= h(implode("\n", $log)) ?></pre><?php endif; ?>

    <section class="tarjeta">
        <div class="titulo-seccion">
            <h2>Fuentes</h2>
            <form method="post"><?= $csrf ?><input type="hidden" name="accion" value="recolectar"><button>Recolectar todas ahora</button></form>
        </div>
        <div class="tabla">
        <table>
            <thead><tr><th></th><th>Nombre</th><th>Tipo</th><th>Noticias</th><th>Última captura</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($fuentes as $f): ?>
                <tr class="<?= $f['activo'] ? '' : 'inactiva' ?>">
                    <td><span class="color" style="background:<?= h($f['color']) ?>"></span></td>
                    <td><strong><?= h($f['nombre']) ?></strong><br><small><?= h($f['url']) ?></small></td>
                    <td><?= h($f['tipo']) ?></td>
                    <td><?= (int)$f['total'] ?></td>
                    <td><?= h($f['ultima_captura'] ?? '—') ?></td>
                    <td class="<?= str_starts_with((string)$f['ultimo_estado'], 'ERROR') ? 'error' : '' ?>"><?= h($f['ultimo_estado'] ?? '—') ?></td>
                    <td class="acciones">
                        <a href="?editar=<?= (int)$f['id'] ?>#formulario">Editar</a>
                        <form method="post"><?= $csrf ?><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                            <button name="accion" value="recolectar">Recolectar</button>
                            <button name="accion" value="alternar"><?= $f['activo'] ? 'Desactivar' : 'Activar' ?></button>
                            <button name="accion" value="borrar" class="peligro" onclick="return confirm('¿Eliminar la fuente y sus noticias?')">Borrar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$fuentes): ?><tr><td colspan="7">No hay fuentes todavía.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </section>

    <section class="tarjeta" id="formulario">
        <h2><?= !empty($editar['id']) ? 'Editar fuente' : 'Añadir fuente' ?></h2>
        <form method="post" class="formulario">
            <?= $csrf ?>
            <input type="hidden" name="id" value="<?= (int)($editar['id'] ?? 0) ?>">
            <label>Nombre <input name="nombre" required value="<?= h($editar['nombre'] ?? '') ?>"></label>
            <label>URL (feed RSS o página web) <input name="url" type="url" required value="<?= h($editar['url'] ?? '') ?>"></label>
            <label>Tipo
                <select name="tipo">
                    <?php foreach (['auto' => 'Automático', 'rss' => 'RSS / Atom', 'html' => 'Página HTML (XPath)'] as $v => $t): ?>
                        <option value="<?= $v ?>" <?= ($editar['tipo'] ?? 'auto') === $v ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Color <input name="color" type="color" value="<?= h($editar['color'] ?? '#e50914') ?>"></label>
            <details <?= !empty($editar['xpath_item']) ? 'open' : '' ?>>
                <summary>Extracción HTML avanzada (opcional)</summary>
                <p class="ayuda">Si la web no tiene RSS, indica XPath. Vacío = detección automática de &lt;article&gt; y titulares.
                    Ejemplo: elemento <code>//div[contains(@class,"nota")]</code>, título <code>.//h2</code>,
                    enlace <code>.//a/@href</code>, imagen <code>.//img/@src</code>.</p>
                <label>XPath elemento <input name="xpath_item" value="<?= h($editar['xpath_item'] ?? '') ?>"></label>
                <label>XPath título <input name="xpath_titulo" value="<?= h($editar['xpath_titulo'] ?? '') ?>"></label>
                <label>XPath enlace <input name="xpath_enlace" value="<?= h($editar['xpath_enlace'] ?? '') ?>"></label>
                <label>XPath imagen <input name="xpath_imagen" value="<?= h($editar['xpath_imagen'] ?? '') ?>"></label>
            </details>
            <div class="botones">
                <button name="accion" value="guardar">Guardar</button>
                <button name="accion" value="probar" class="secundario" formnovalidate>Probar sin guardar</button>
                <?php if (!empty($editar['id'])): ?><a href="admin.php">Cancelar</a><?php endif; ?>
            </div>
        </form>

        <?php if (!empty($prueba)): ?>
            <h3>Vista previa: <?= count($prueba) ?> noticias</h3>
            <ul class="prueba">
                <?php foreach (array_slice($prueba, 0, 15) as $p): ?>
                    <li><?php if ($p['imagen']): ?><img src="<?= h($p['imagen']) ?>" alt="" loading="lazy"><?php endif; ?>
                        <div><a href="<?= h($p['enlace']) ?>" target="_blank" rel="noopener"><?= h($p['titulo']) ?></a>
                        <small><?= h($p['fecha'] ?? '') ?></small></div></li>
                <?php endforeach; ?>
            </ul>
        <?php elseif (isset($prueba)): ?>
            <p class="error">No se encontraron noticias con esa configuración.</p>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
