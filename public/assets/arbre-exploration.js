/**
 * Histoire de famille — exploration de l'arbre « chêne gravé » (1.35.0).
 *
 * Chargé uniquement sur /arbre. Sans ce fichier — JavaScript coupé, script
 * bloqué — la page reste exactement celle du serveur : les boutons
 * d'exploration portent l'attribut `hidden` dans le HTML et le cadre ne
 * contraint pas le dessin, tout l'arbre est lisible et cliquable.
 *
 * Les positions passent par des propriétés CSS personnalisées posées avec
 * l'API CSSOM (`element.style.setProperty`) sur le seul élément du dessin :
 * aucun attribut `style=` n'est écrit dans le HTML par le serveur, et la CSP
 * « style-src 'self' » ne bloque pas l'API CSSOM (vérifié dans le navigateur :
 * le zoom calculé change bien le transform rendu).
 */
(function () {
    'use strict';

    var racine = document.querySelector('[data-arbre-exploration]');
    if (!racine) {
        return;
    }
    var barre = racine.querySelector('[data-explo-barre]');
    var aide = racine.querySelector('[data-explo-aide]');
    var cadre = racine.querySelector('[data-explo-cadre]');
    var dessin = racine.querySelector('[data-explo-dessin]');
    var niveau = racine.querySelector('[data-explo-niveau]');
    if (!barre || !cadre || !dessin) {
        return;
    }

    var ZOOM_MIN = 0.25;
    var ZOOM_MAX = 2.5;
    var PAS = 80;          // déplacement des boutons fléchés, en pixels écran
    var MARGE = 48;        // l'arbre peut dépasser d'autant, jamais disparaître

    var echelle = 1;
    var x = 0;
    var y = 0;

    var mouvementReduit = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Le cadre ne devient un cadre (hauteur bornée, dessin transformé) que
    // maintenant : sans JavaScript, rien n'est rogné.
    racine.classList.add('arbre-explo--actif');
    barre.hidden = false;
    if (aide) {
        aide.hidden = false;
    }
    if (!mouvementReduit) {
        racine.classList.add('arbre-explo--anime');
    }

    /** Dimensions du dessin sans le transform courant. */
    function tailleDessin() {
        return { largeur: dessin.offsetWidth, hauteur: dessin.offsetHeight };
    }

    function borner() {
        var t = tailleDessin();
        var cw = cadre.clientWidth;
        var ch = cadre.clientHeight;
        var largeur = t.largeur * echelle;
        var hauteur = t.hauteur * echelle;
        // On garde toujours au moins MARGE pixels de l'arbre dans le cadre,
        // dans les deux sens : c'est ce qui permet de centrer une carte qui
        // n'est pas au milieu du dessin (la personne sélectionnée, sur le
        // tronc, est à gauche de son conjoint) sans jamais perdre l'arbre.
        x = Math.min(cw - MARGE, Math.max(-(largeur - MARGE), x));
        y = Math.min(ch - MARGE, Math.max(-(hauteur - MARGE), y));
    }

    function appliquer() {
        borner();
        dessin.style.setProperty('--explo-zoom', String(echelle));
        dessin.style.setProperty('--explo-x', Math.round(x) + 'px');
        dessin.style.setProperty('--explo-y', Math.round(y) + 'px');
        if (niveau) {
            niveau.textContent = Math.round(echelle * 100) + ' %';
        }
    }

    /**
     * Position d'un élément dans un conteneur, en coordonnées de mise en page
     * (offsetLeft/offsetTop) : contrairement aux rectangles à l'écran, elles
     * ne bougent ni avec le transform, ni pendant son animation — les mesures
     * restent justes au chargement comme après un zoom.
     */
    function positionDans(element, conteneur) {
        var px = 0;
        var py = 0;
        var el = element;
        while (el && el !== conteneur) {
            px += el.offsetLeft;
            py += el.offsetTop;
            el = el.offsetParent;
        }
        return { x: px, y: py };
    }

    /**
     * Position du dessin dans le cadre, hors transform : sur grand écran, le
     * dessin est centré par ses marges, il ne commence donc pas à (0, 0).
     */
    function baseDessin() {
        return positionDans(dessin, cadre);
    }

    /** Centre (coordonnées du dessin, sans transform) d'un élément. */
    function centreDe(element) {
        var p = positionDans(element, dessin);
        return {
            x: p.x + element.offsetWidth / 2,
            y: p.y + element.offsetHeight / 2
        };
    }

    /** Centre le cadre sur la personne sélectionnée, zoom conservé. */
    function centrer() {
        var cible = dessin.querySelector('[data-explo-centre]');
        if (!cible) {
            return;
        }
        var c = centreDe(cible);
        var b = baseDessin();
        x = cadre.clientWidth / 2 - b.x - c.x * echelle;
        y = cadre.clientHeight / 2 - b.y - c.y * echelle;
        appliquer();
    }

    /** Ajuste le zoom pour que tout l'arbre tienne dans le cadre. */
    function voirTout() {
        var t = tailleDessin();
        if (t.largeur === 0 || t.hauteur === 0) {
            return;
        }
        echelle = Math.max(ZOOM_MIN, Math.min(
            cadre.clientWidth / t.largeur,
            cadre.clientHeight / t.hauteur,
            ZOOM_MAX
        ));
        var b = baseDessin();
        x = (cadre.clientWidth - t.largeur * echelle) / 2 - b.x;
        y = (cadre.clientHeight - t.hauteur * echelle) / 2 - b.y;
        appliquer();
    }

    /** Zoom autour d'un point du cadre (son centre par défaut). */
    function zoomer(facteur, point) {
        var avant = echelle;
        echelle = Math.max(ZOOM_MIN, Math.min(ZOOM_MAX, echelle * facteur));
        if (echelle === avant) {
            appliquer();
            return;
        }
        var b = baseDessin();
        var px = point ? point.x : cadre.clientWidth / 2;
        var py = point ? point.y : cadre.clientHeight / 2;
        // Le point du dessin qui se trouve sous le pointeur y reste.
        var lx = (px - b.x - x) / avant;
        var ly = (py - b.y - y) / avant;
        x = px - b.x - lx * echelle;
        y = py - b.y - ly * echelle;
        appliquer();
    }

    function deplacer(dx, dy) {
        x += dx;
        y += dy;
        appliquer();
    }

    // --- Barre d'outils ----------------------------------------------------
    barre.addEventListener('click', function (evenement) {
        var bouton = evenement.target.closest
            ? evenement.target.closest('[data-explo]')
            : null;
        if (!bouton) {
            return;
        }
        switch (bouton.getAttribute('data-explo')) {
            case 'zoom-plus': zoomer(1.25); break;
            case 'zoom-moins': zoomer(0.8); break;
            case 'gauche': deplacer(PAS, 0); break;
            case 'droite': deplacer(-PAS, 0); break;
            case 'haut': deplacer(0, PAS); break;
            case 'bas': deplacer(0, -PAS); break;
            case 'centrer': centrer(); break;
            case 'tout': voirTout(); break;
        }
    });

    // --- Clavier dans le cadre --------------------------------------------
    // Tab entre et sort librement (aucun piège) ; les flèches déplacent,
    // + et − zooment quand le cadre lui-même a le focus.
    cadre.addEventListener('keydown', function (evenement) {
        if (evenement.target !== cadre) {
            return;
        }
        switch (evenement.key) {
            case 'ArrowLeft': deplacer(PAS, 0); evenement.preventDefault(); break;
            case 'ArrowRight': deplacer(-PAS, 0); evenement.preventDefault(); break;
            case 'ArrowUp': deplacer(0, PAS); evenement.preventDefault(); break;
            case 'ArrowDown': deplacer(0, -PAS); evenement.preventDefault(); break;
            case '+': case '=': zoomer(1.25); evenement.preventDefault(); break;
            case '-': zoomer(0.8); evenement.preventDefault(); break;
        }
    });

    // --- Molette ------------------------------------------------------------
    cadre.addEventListener('wheel', function (evenement) {
        evenement.preventDefault();
        var rect = cadre.getBoundingClientRect();
        zoomer(evenement.deltaY < 0 ? 1.12 : 0.89, {
            x: evenement.clientX - rect.left,
            y: evenement.clientY - rect.top
        });
    }, { passive: false });

    // --- Glisser et pincement ------------------------------------------------
    var pointeurs = new Map();
    var depart = null;
    var distanceDepart = 0;
    var echelleDepart = 1;
    var aBouge = false;

    cadre.addEventListener('pointerdown', function (evenement) {
        // Les liens restent des liens : on ne capture le geste que s'il bouge.
        pointeurs.set(evenement.pointerId, { x: evenement.clientX, y: evenement.clientY });
        if (pointeurs.size === 1) {
            depart = { x: evenement.clientX, y: evenement.clientY, tx: x, ty: y };
            aBouge = false;
        } else if (pointeurs.size === 2) {
            var pts = Array.from(pointeurs.values());
            distanceDepart = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
            echelleDepart = echelle;
            var rect2 = cadre.getBoundingClientRect();
            var milieuDepart = {
                x: (pts[0].x + pts[1].x) / 2 - rect2.left,
                y: (pts[0].y + pts[1].y) / 2 - rect2.top
            };
            var base2 = baseDessin();
            depart = {
                x: (pts[0].x + pts[1].x) / 2,
                y: (pts[0].y + pts[1].y) / 2,
                tx: x, ty: y, bx: base2.x, by: base2.y,
                // Le point du dessin sous les deux doigts doit y rester.
                lx: (milieuDepart.x - base2.x - x) / echelle,
                ly: (milieuDepart.y - base2.y - y) / echelle
            };
            aBouge = true;
        }
        racine.setAttribute('data-glisse', '1');
    });

    cadre.addEventListener('pointermove', function (evenement) {
        if (!pointeurs.has(evenement.pointerId) || !depart) {
            return;
        }
        pointeurs.set(evenement.pointerId, { x: evenement.clientX, y: evenement.clientY });

        if (pointeurs.size === 2 && distanceDepart > 0) {
            var pts = Array.from(pointeurs.values());
            var distance = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
            var milieu = {
                x: (pts[0].x + pts[1].x) / 2,
                y: (pts[0].y + pts[1].y) / 2
            };
            var rect = cadre.getBoundingClientRect();
            echelle = Math.max(ZOOM_MIN, Math.min(ZOOM_MAX, echelleDepart * distance / distanceDepart));
            // Ancrage au milieu des deux doigts, qui se déplace avec eux.
            x = (milieu.x - rect.left) - depart.bx - depart.lx * echelle;
            y = (milieu.y - rect.top) - depart.by - depart.ly * echelle;
            appliquer();
            aBouge = true;
            return;
        }

        var dx = evenement.clientX - depart.x;
        var dy = evenement.clientY - depart.y;
        if (Math.abs(dx) + Math.abs(dy) > 4) {
            aBouge = true;
        }
        if (aBouge) {
            x = depart.tx + dx;
            y = depart.ty + dy;
            appliquer();
        }
    });

    function finirPointeur(evenement) {
        pointeurs.delete(evenement.pointerId);
        if (pointeurs.size === 0) {
            depart = null;
            racine.removeAttribute('data-glisse');
        } else if (pointeurs.size === 1) {
            // Il reste un doigt : repartir de sa position pour éviter un saut.
            var reste = Array.from(pointeurs.values())[0];
            depart = { x: reste.x, y: reste.y, tx: x, ty: y };
            distanceDepart = 0;
        }
    }
    cadre.addEventListener('pointerup', finirPointeur);
    cadre.addEventListener('pointercancel', finirPointeur);

    // Un glisser ne doit pas déclencher le lien qu'il survole.
    cadre.addEventListener('click', function (evenement) {
        if (aBouge) {
            evenement.preventDefault();
            evenement.stopPropagation();
            aBouge = false;
        }
    }, true);

    // --- État initial ---------------------------------------------------------
    /**
     * Zoom de départ : le plus grand (≤ 100 %) qui montre, dans le cadre, à la
     * fois le tronc et la génération des parents juste au-dessus. Sur un
     * téléphone, il recule donc tout seul ; sur un grand écran, il reste à
     * 100 %. Puis on centre sur la personne sélectionnée.
     */
    function zoomInitial() {
        var cible = dessin.querySelector('[data-explo-centre]');
        if (!cible) {
            return 1;
        }
        var parents = dessin.querySelectorAll('[data-relation="parent"]');
        if (parents.length === 0) {
            return 1;
        }
        var haut = Infinity;
        parents.forEach(function (parent) {
            haut = Math.min(haut, positionDans(parent, dessin).y);
        });
        var bas = positionDans(cible, dessin).y + cible.offsetHeight;
        var hauteurUtile = bas - haut; // echelle vaut 1 ici
        if (hauteurUtile <= 0) {
            return 1;
        }
        return Math.max(ZOOM_MIN, Math.min(1, cadre.clientHeight / hauteurUtile));
    }

    echelle = zoomInitial();
    appliquer();
    centrer();

    // Après le chargement complet (polices, gravure), les mesures sont stables.
    window.addEventListener('load', function () {
        centrer();
    });
    window.addEventListener('resize', function () {
        appliquer();
    });
})();
