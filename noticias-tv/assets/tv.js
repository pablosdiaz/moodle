(function () {
    'use strict';

    var op = window.TV_OPCIONES || {};
    var SEG = (op.segundos || 12) * 1000;
    var noticias = [];
    var actual = -1;
    var temporizador = null;
    var $ = function (id) { return document.getElementById(id); };

    // ---------- Reloj ----------
    function reloj() {
        var d = new Date();
        $('hora').textContent = d.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
        var f = d.toLocaleDateString('es', { weekday: 'long', day: 'numeric', month: 'long' });
        $('fecha').textContent = f.charAt(0).toUpperCase() + f.slice(1);
    }
    reloj();
    setInterval(reloj, 1000);

    function hace(ts) {
        if (!ts) { return ''; }
        var m = Math.max(0, Math.round((Date.now() / 1000 - ts) / 60));
        if (m < 1) { return 'Ahora'; }
        if (m < 60) { return 'Hace ' + m + ' min'; }
        var h = Math.round(m / 60);
        if (h < 24) { return 'Hace ' + h + (h === 1 ? ' hora' : ' horas'); }
        var dd = Math.round(h / 24);
        return 'Hace ' + dd + (dd === 1 ? ' día' : ' días');
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    // Precarga la imagen; si falla se muestra el fondo de color.
    function precargar(url) {
        return new Promise(function (ok) {
            if (!url) { return ok(false); }
            var img = new Image();
            var t = setTimeout(function () { ok(false); }, 5000);
            img.onload = function () { clearTimeout(t); ok(img.naturalWidth > 50); };
            img.onerror = function () { clearTimeout(t); ok(false); };
            img.src = url;
        });
    }

    // ---------- Noticia principal ----------
    function mostrar(i) {
        if (!noticias.length) { return; }
        actual = (i + noticias.length) % noticias.length;
        var n = noticias[actual];
        var principal = $('principal');

        principal.classList.add('saliendo');
        precargar(n.imagen).then(function (hayFoto) {
            setTimeout(function () {
                var img = $('imagen');
                // Reinicia la animación Ken Burns.
                var nueva = img.cloneNode(false);
                nueva.className = 'imagen' + (hayFoto ? '' : ' sin-foto');
                nueva.style.backgroundImage = hayFoto ? 'url("' + n.imagen.replace(/"/g, '%22') + '")' : '';
                img.parentNode.replaceChild(nueva, img);

                document.documentElement.style.setProperty('--acento', n.color || '#e50914');
                $('fuente').textContent = n.fuente;
                $('hace').textContent = hace(n.timestamp);
                $('titulo').textContent = n.titulo;
                $('resumen').textContent = (n.resumen && n.resumen !== n.titulo) ? n.resumen : '';
                $('contador').textContent = (actual + 1) + ' / ' + noticias.length;
                principal.classList.remove('saliendo');
                barra();
                lista();
            }, 600);
        });

        clearTimeout(temporizador);
        temporizador = setTimeout(function () { mostrar(actual + 1); }, SEG);
    }

    function barra() {
        var b = $('barra');
        b.style.transition = 'none';
        b.style.width = '0';
        void b.offsetWidth;
        b.style.transition = 'width ' + (SEG - 600) + 'ms linear';
        b.style.width = '100%';
    }

    function lista() {
        var html = '';
        var cuantas = Math.min(4, noticias.length - 1);
        for (var k = 1; k <= cuantas; k++) {
            var n = noticias[(actual + k) % noticias.length];
            var mini = n.imagen
                ? '<div class="mini" style="background-image:url(&quot;' + esc(n.imagen) + '&quot;)"></div>'
                : '<div class="mini sin-foto"></div>';
            html += '<li style="--c:' + esc(n.color) + ';animation-delay:' + (k * 80) + 'ms">' + mini +
                '<div><b>' + esc(n.fuente) + '</b><span>' + esc(n.titulo) + '</span></div></li>';
        }
        $('lista').innerHTML = html;
    }

    function ticker() {
        var t = $('ticker');
        t.innerHTML = noticias.map(function (n) {
            return '<em>■</em>' + esc(n.fuente.toUpperCase()) + ': ' + esc(n.titulo);
        }).join('');
        // Velocidad constante sin importar la cantidad de texto.
        var px = t.scrollWidth || 4000;
        t.style.setProperty('--duracion', Math.max(30, Math.round(px / 90)) + 's');
    }

    // ---------- Datos ----------
    function cargar() {
        var url = 'api.php?limite=' + (op.limite || 40) + (op.fuentes ? '&fuentes=' + op.fuentes : '');
        return fetch(url, { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) { throw new Error(d.error); }
                var primeraVez = !noticias.length;
                var idActual = noticias[actual] && noticias[actual].id;
                noticias = d.noticias;
                if (!noticias.length) {
                    $('titulo').textContent = 'Aún no hay noticias. Ejecuta el recolector.';
                    return;
                }
                ticker();
                // Mantiene la posición tras refrescar.
                var pos = noticias.findIndex(function (n) { return n.id === idActual; });
                actual = pos >= 0 ? pos : -1;
                if (primeraVez) { mostrar(0); } else { lista(); }
            })
            .catch(function () {
                if (!noticias.length) { $('titulo').textContent = 'Sin conexión con el servidor. Reintentando…'; }
            });
    }

    cargar();
    setInterval(cargar, (op.refresco || 5) * 60 * 1000);
    // Recarga completa cada 6 h para evitar fugas de memoria en TVs encendidas 24/7.
    setTimeout(function () { location.reload(); }, 6 * 3600 * 1000);

    // ---------- Controles ----------
    function pantallaCompleta() {
        var el = document.documentElement;
        if (!document.fullscreenElement && el.requestFullscreen) { el.requestFullscreen().catch(function () {}); }
    }
    document.addEventListener('click', pantallaCompleta);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight') { mostrar(actual + 1); }
        else if (e.key === 'ArrowLeft') { mostrar(actual - 1); }
        else { pantallaCompleta(); }
    });
    setTimeout(function () { $('aviso-pantalla').classList.add('oculto'); }, 6000);
})();
