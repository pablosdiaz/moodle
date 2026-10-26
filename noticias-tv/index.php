<?php
// Pantalla de noticias estilo canal de TV. Pensada para un televisor / monitor a pantalla completa.
//   index.php?fuentes=1,3   (opcional: solo algunas fuentes)
require_once __DIR__ . '/config.php';

$opciones = [
    'segundos'  => (int)$CFG['segundos_slide'],
    'refresco'  => (int)$CFG['refresco_minutos'],
    'limite'    => (int)$CFG['max_noticias'],
    'fuentes'   => preg_replace('/[^0-9,]/', '', (string)($_GET['fuentes'] ?? '')),
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($CFG['titulo_canal']) ?></title>
<link rel="stylesheet" href="assets/tv.css">
</head>
<body>
<div id="tv">
    <header class="barra-superior">
        <div class="logo"><span class="punto-vivo"></span><?= htmlspecialchars($CFG['titulo_canal']) ?></div>
        <div class="en-vivo">EN VIVO</div>
        <div class="reloj"><span id="hora">--:--</span><small id="fecha"></small></div>
    </header>

    <main class="escenario">
        <section class="principal" id="principal">
            <div class="imagen" id="imagen"></div>
            <div class="degradado"></div>
            <div class="contenido">
                <div class="etiquetas">
                    <span class="fuente" id="fuente"></span>
                    <span class="hace" id="hace"></span>
                </div>
                <h1 id="titulo">Cargando noticias…</h1>
                <p id="resumen"></p>
            </div>
            <div class="progreso"><div id="barra"></div></div>
            <div class="contador" id="contador"></div>
        </section>

        <aside class="siguientes">
            <h2>A continuación</h2>
            <ol id="lista"></ol>
        </aside>
    </main>

    <footer class="zocalo">
        <div class="zocalo-etiqueta">ÚLTIMO MOMENTO</div>
        <div class="zocalo-pista"><div class="zocalo-texto" id="ticker"></div></div>
    </footer>
</div>
<div id="aviso-pantalla">Pulsa cualquier tecla o haz clic para pantalla completa</div>
<script>window.TV_OPCIONES = <?= json_encode($opciones) ?>;</script>
<script src="assets/tv.js"></script>
</body>
</html>
