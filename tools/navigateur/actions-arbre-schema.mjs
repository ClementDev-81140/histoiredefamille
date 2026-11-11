// Contrôle « arbre : le schéma, ses photos et son filigrane » (1.40.0).
//
// Constat corrigé : sur grand écran, le dessin restait un petit cadre
// centré aux proportions de la gravure — 736 px sur un cadre de 1048 px
// à 1280 px (70 %), cartes perdues dans des rangées à moitié vides,
// aucune photo, conjoint seul avec la personne sur le tronc.
//
// Ce contrôle ouvre une page /arbre rendue par le routeur et vérifie,
// dans un vrai navigateur :
//   - à 1280 et 1920 px, le schéma occupe au moins 90 % de la largeur
//     de son cadre, mise en page du serveur (JavaScript coupé) comme
//     état d'ouverture (JavaScript actif, zoom lu) ;
//   - chaque carte porte une photo ronde chargée (portrait visible ou
//     silhouette du site, `alt` conforme), sa relation, son nom, son
//     lien de centrage `/arbre?racine=…#chene` ; les actions de fiche
//     sont en dehors du dessin (vérifiées par actions-arbre-visible.mjs) ;
//     aucune date n'est plus sur les cartes ;
//   - les traits du serveur arrivent en face des cartes (≤ 4 px) ;
//   - le contraste des noms, des relations et des traits, mesuré sur
//     le filigrane réel (pixels de la gravure composés à son opacité),
//     reste ≥ 4,5:1 — lancer deux fois : palette papier (défaut) et
//     palette bleue (HDF_THEME pointe la feuille /theme.css bleue) ;
//   - le filigrane est à 12 % d'opacité et disparaît à l'impression.
//
//   HDF_MINIATURES=/tmp/miniatures.json node actions-arbre-schema.mjs /tmp/arbre.html
//   HDF_THEME=/tmp/theme-bleu.css HDF_MINIATURES=… node actions-arbre-schema.mjs /tmp/arbre-bleu.html
//
// HDF_MINIATURES : {"<id document>": {"mini": "/chemin.jpg", "plein": "/chemin.jpg"}}
// HDF_DEPOT : racine d'un autre dépôt (preuve « avant », anciennes feuilles).

import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import chromium from '@sparticuz/chromium';
import puppeteer from 'puppeteer-core';

const fichier = process.argv[2];
if (!fichier) {
    console.error('Usage : actions-arbre-schema.mjs <page-arbre.html>');
    process.exit(2);
}
const racine = path.dirname(fileURLToPath(import.meta.url));
let depot = path.resolve(racine, '..', '..');
if (process.env.HDF_DEPOT) {
    depot = process.env.HDF_DEPOT;
} else if (!fs.existsSync(path.join(depot, 'public/assets/style.css'))) {
    depot = process.env.HDF_REPO || '/home/user/histoiredefamille';
}
const miniatures = process.env.HDF_MINIATURES
    ? JSON.parse(fs.readFileSync(process.env.HDF_MINIATURES, 'utf8'))
    : {};
const theme = process.env.HDF_THEME && fs.existsSync(process.env.HDF_THEME)
    ? fs.readFileSync(process.env.HDF_THEME)
    : null;

let html = fs.readFileSync(fichier, 'utf8');
// Le fichier peut avoir été rendu en papier ; le contrôle bleu doit alors
// charger effectivement la feuille de thème plutôt que seulement la servir.
if (theme && !html.includes('/theme.css')) {
    html = html.replace('</head>', '<link rel="stylesheet" href="/theme.css"></head>');
}
const serveur = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://x');
    if (url.pathname === '/theme.css' && theme) {
        res.writeHead(200, { 'Content-Type': 'text/css' });
        return res.end(theme);
    }
    if (url.pathname.startsWith('/assets/')) {
        const f = path.join(depot, 'public', url.pathname);
        if (fs.existsSync(f)) {
            const type = url.pathname.endsWith('.js') ? 'text/javascript'
                : url.pathname.endsWith('.css') ? 'text/css'
                : url.pathname.endsWith('.svg') ? 'image/svg+xml'
                : url.pathname.endsWith('.webp') ? 'image/webp' : 'image/jpeg';
            res.writeHead(200, { 'Content-Type': type });
            return res.end(fs.readFileSync(f));
        }
    }
    const mMini = url.pathname.match(/^\/media\/miniature\/(\d+)$/);
    const mPlein = url.pathname.match(/^\/f\/(\d+)$/);
    const doc = miniatures[(mMini || mPlein || [])[1]];
    if (doc) {
        const chemin = mMini ? doc.mini : doc.plein;
        if (chemin && fs.existsSync(chemin)) {
            res.writeHead(200, { 'Content-Type': 'image/jpeg' });
            return res.end(fs.readFileSync(chemin));
        }
    }
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    res.end(html);
});
await new Promise(r => serveur.listen(8964, '0.0.0.0', r));

const browser = await puppeteer.launch({
    executablePath: await chromium.executablePath(),
    args: [...chromium.args, '--no-sandbox'],
    headless: true,
});
console.log('Navigateur :', await browser.version());
console.log('Palette :', theme ? 'bleue (HDF_THEME)' : 'papier (défaut)');

let echecs = 0;
const echec = (msg) => { console.log('ÉCHEC ' + msg); echecs++; };

// --- 1. Largeurs, sans JavaScript (la mise en page du serveur) -------------
for (const largeur of [1280, 1920]) {
    const page = await browser.newPage();
    await page.setJavaScriptEnabled(false);
    await page.setViewport({ width: largeur, height: 900 });
    await page.goto('http://127.0.0.1:8964/arbre', { waitUntil: 'load' });
    const m = await page.evaluate(() => {
        const dessin = document.querySelector('.arbre-chene__dessin');
        const section = document.querySelector('.arbre-chene');
        const cs = getComputedStyle(section);
        const dispo = section.clientWidth
            - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
        return { dessin: dessin.offsetWidth, dispo };
    });
    const part = Math.round((m.dessin / m.dispo) * 100);
    console.log(`[${largeur}px, sans JS] schéma ${m.dessin}/${m.dispo} px = ${part} % de la place`);
    if (part < 90) echec(`[${largeur}px] le schéma n’occupe que ${part} % de sa place (< 90 %)`);
    await page.close();
}

// --- 2. État d'ouverture avec JavaScript, cartes, photos, traits, contraste
const page = await browser.newPage();
await page.setViewport({ width: 1280, height: 900 });
await page.goto('http://127.0.0.1:8964/arbre', { waitUntil: 'load' });
await new Promise(r => setTimeout(r, 600));

const etat = await page.evaluate(() => {
    const cadre = document.querySelector('[data-explo-cadre]').getBoundingClientRect();
    const dessin = document.querySelector('.arbre-chene__dessin');
    const rd = dessin.getBoundingClientRect();
    const cartes = [...document.querySelectorAll('.arbre-chene__noeud')].map(el => {
        const img = el.querySelector('.arbre-chene__photo');
        const lien = el.querySelector('.arbre-chene__lien');
        return {
            relation: el.getAttribute('data-relation') || '',
            centre: el.hasAttribute('data-explo-centre'),
            src: img ? img.getAttribute('src') : null,
            alt: img ? img.getAttribute('alt') : null,
            silhouette: img ? img.classList.contains('arbre-chene__photo--silhouette') : false,
            chargee: img ? (img.complete && img.naturalWidth > 0) : false,
            href: lien ? lien.getAttribute('href') : null,
        };
    });
    // Les arrivées des traits (y 12 → 24) face au centre des cartes.
    const centres = [...document.querySelectorAll('.arbre-chene__noeud')].map(el => {
        const r = el.getBoundingClientRect();
        return (r.left + r.right) / 2;
    });
    let ecartMax = 0;
    let arrivees = 0;
    document.querySelectorAll('.arbre-chene__traits').forEach(svg => {
        const rs = svg.getBoundingClientRect();
        svg.querySelectorAll('line').forEach(l => {
            if (l.getAttribute('y1') !== '12' || l.getAttribute('y2') !== '24') return;
            arrivees++;
            const x = rs.left + (parseFloat(l.getAttribute('x1')) / 100) * rs.width;
            const ecart = Math.min(...centres.map(c => Math.abs(c - x)));
            ecartMax = Math.max(ecartMax, ecart);
        });
    });
    const avant = getComputedStyle(dessin, '::before');
    return {
        ratio: Math.round((rd.width / cadre.width) * 100),
        niveau: (document.querySelector('[data-explo-niveau]') || {}).textContent || '',
        cartes,
        dates: document.querySelectorAll('.arbre-chene__dessin .arbre-chene__dates').length,
        arrivees,
        ecartMax: Math.round(ecartMax * 10) / 10,
        filigraneOpacite: avant.opacity,
        filigraneFond: avant.backgroundImage !== 'none',
    };
});
console.log(`[1280px, avec JS] dessin ${etat.ratio} % du cadre, zoom ${etat.niveau.trim()}`
    + ` ; ${etat.cartes.length} cartes, ${etat.arrivees} arrivées de traits, écart max ${etat.ecartMax} px`);
if (etat.ratio < 90) echec(`[1280px, avec JS] le dessin n’occupe que ${etat.ratio} % du cadre`);
if (etat.cartes.length === 0) echec('aucune carte dans le schéma');
if (etat.dates > 0) echec(`${etat.dates} date(s) encore sur les cartes`);
for (const [i, c] of etat.cartes.entries()) {
    if (!c.src) { echec(`carte ${i + 1} : pas de photo`); continue; }
    if (!c.chargee) echec(`carte ${i + 1} : image non chargée (${c.src})`);
    if (c.silhouette && c.alt !== '') echec(`carte ${i + 1} : la silhouette doit avoir alt=""`);
    if (!c.silhouette && !(c.alt || '').startsWith('Portrait de ')) {
        echec(`carte ${i + 1} : alt de portrait incorrect (« ${c.alt} »)`);
    }
    if (!/^\/arbre\?racine=\d+#chene$/.test(c.href || '')) {
        echec(`carte ${i + 1} : le lien de la carte ne centre pas (${c.href})`);
    }

}
if (!etat.cartes.some(c => c.centre && c.relation === 'selection')) {
    echec('la personne sélectionnée n’est pas marquée comme centre');
}
if (etat.arrivees === 0) echec('aucune arrivée de trait à mesurer');
if (etat.ecartMax > 4) echec(`les traits arrivent jusqu’à ${etat.ecartMax} px du centre des cartes (> 4)`);
if (!etat.filigraneFond || Math.abs(parseFloat(etat.filigraneOpacite) - 0.12) > 0.005) {
    echec(`filigrane : fond=${etat.filigraneFond}, opacité=${etat.filigraneOpacite} (attendu : image à 0.12)`);
}

// --- 3. Contraste mesuré sur le filigrane réel ------------------------------
const contraste = await page.evaluate(async () => {
    const racineStyles = getComputedStyle(document.documentElement);
    const px = (nom) => racineStyles.getPropertyValue(nom).trim();
    const rgb = (s) => {
        s = s.trim();
        if (s.startsWith('#')) {
            const n = parseInt(s.slice(1), 16);
            return { r: (n >> 16) & 255, g: (n >> 8) & 255, b: n & 255 };
        }
        const m = s.match(/[\d.]+/g).map(Number);
        return { r: m[0], g: m[1], b: m[2] };
    };
    const lum = ({ r, g, b }) => {
        const f = (v) => {
            v /= 255;
            return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
        };
        return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
    };
    const ratio = (a, b) => {
        const [l1, l2] = [lum(a), lum(b)].sort((x, y) => y - x);
        return (l1 + 0.05) / (l2 + 0.05);
    };
    const melange = (avant, fond, a) => ({
        r: avant.r * a + fond.r * (1 - a),
        g: avant.g * a + fond.g * (1 - a),
        b: avant.b * a + fond.b * (1 - a),
    });

    const dessin = document.querySelector('.arbre-chene__dessin');
    const fond = rgb(getComputedStyle(document.body).backgroundColor === 'rgba(0, 0, 0, 0)'
        ? px('--fond') : getComputedStyle(document.body).backgroundColor);
    const surface = rgb(px('--surface'));
    const texte = rgb(px('--texte'));
    const doux = rgb(px('--texte-doux'));
    const accent = rgb(px('--accent'));

    // Le pire fond que le filigrane puisse faire : les pixels extrêmes de
    // la gravure, composés à son opacité réelle sur le fond de la page.
    const img = new Image();
    img.src = '/assets/arbre-chene.jpg';
    await img.decode();
    const boite = dessin.getBoundingClientRect();
    const echelle = Math.max(boite.width / img.naturalWidth, boite.height / img.naturalHeight);
    const toile = document.createElement('canvas');
    toile.width = Math.max(2, Math.round(boite.width));
    toile.height = Math.max(2, Math.round(boite.height));
    const ctx = toile.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(img,
        (img.naturalWidth - boite.width / echelle) / 2,
        (img.naturalHeight - boite.height / echelle) / 2,
        boite.width / echelle, boite.height / echelle,
        0, 0, toile.width, toile.height);
    const donnees = ctx.getImageData(0, 0, toile.width, toile.height).data;
    let sombre = { r: 255, g: 255, b: 255 };
    let clair = { r: 0, g: 0, b: 0 };
    for (let i = 0; i < donnees.length; i += 16) {
        const p = { r: donnees[i], g: donnees[i + 1], b: donnees[i + 2] };
        if (lum(p) < lum(sombre)) sombre = p;
        if (lum(p) > lum(clair)) clair = p;
    }
    const opacite = parseFloat(getComputedStyle(dessin, '::before').opacity);
    // Texte sombre sur fond clair (les deux palettes) : le pire fond est
    // le plus sombre ; on compose aussi le plus clair par honnêteté.
    const fondPire = melange(sombre, fond, opacite);
    const fondClair = melange(clair, fond, opacite);
    // Le trait réel : couleur et opacité lues sur une ligne rendue.
    const ligne = document.querySelector('.arbre-chene__traits line');
    const styleLigne = getComputedStyle(ligne);
    const couleurTrait = rgb(styleLigne.stroke);
    const opaciteTrait = parseFloat(styleLigne.opacity);
    const trait = melange(couleurTrait, fondPire, opaciteTrait);
    const traitClair = melange(couleurTrait, fondClair, opaciteTrait);
    return {
        nomSurCarte: ratio(texte, surface),
        relationSurCarte: ratio(doux, surface),
        selectionSurCarte: ratio(accent, surface),
        traitSurFiligrane: Math.min(ratio(trait, fondPire), ratio(traitClair, fondClair)),
    };
});
for (const [nom, valeur] of Object.entries(contraste)) {
    console.log(`contraste ${nom} : ${valeur.toFixed(2)}:1`);
    if (valeur < 4.5) echec(`contraste ${nom} = ${valeur.toFixed(2)}:1 (< 4,5:1)`);
}

// --- 4. Impression : le filigrane disparaît ---------------------------------
await page.emulateMediaType('print');
const impression = await page.evaluate(() => getComputedStyle(
    document.querySelector('.arbre-chene__dessin'), '::before').display);
console.log(`impression : filigrane display=${impression}`);
if (impression !== 'none') echec('le filigrane reste visible à l’impression');
await page.emulateMediaType('screen');
await page.close();

await browser.close();
serveur.close();
console.log(echecs === 0
    ? '\nOK : schéma plein cadre, photos et silhouettes, traits en face, contraste tenu, filigrane effacé à l’impression.'
    : `\n${echecs} échec(s).`);
process.exit(echecs === 0 ? 0 : 1);
