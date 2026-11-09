// Contrôle des doublons visibles (1.38.0).
//
// Parcourt les pages rendues par `rendre-tout.php` (toutes les routes GET
// de la matrice d'accès qui répondent 200, pour un visiteur, un lecteur,
// un journaliste « avec validation », un journaliste « sans validation »
// et un administrateur), à 1280 px et 390 px, en thème papier et en thème
// bleu (feuille /theme.css injectée si HDF_THEME_CSS pointe dessus).
//
// Détecte, uniquement parmi les éléments réellement affichés à la largeur
// et au thème donnés :
//   - plus d'un <h1> visible ;
//   - le même texte de titre (h1/h2/h3) répété sur la page ;
//   - deux boutons ou liens de même libellé et même destination dans la
//     même zone (en-tête, pied, navigation, formulaire, bloc de contenu) ;
//   - le même lien dans le menu principal et dans une navigation
//     secondaire du contenu ;
//   - une phrase d'aide ou d'avertissement (> 40 caractères) affichée
//     deux fois, y compris reprise dans un title= ou un aria-label ;
//   - un identifiant HTML (id) utilisé deux fois ;
//   - un bloc de texte entier (> 40 caractères) rendu deux fois.
//
// Les doublons volontaires et justifiés sont listés dans
// `doublons-liste-blanche.json` (à côté de ce fichier) — jamais dans le
// code des pages. Le contrôle échoue (code 1) sur tout doublon hors
// liste blanche.
//
//   node actions-doublons.mjs /tmp/rendus
//   HDF_THEME_CSS=/tmp/theme.css node actions-doublons.mjs /tmp/rendus

import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import chromium from '@sparticuz/chromium';
import puppeteer from 'puppeteer-core';

const dossier = process.argv[2] || '/tmp/rendus';
const racine = path.dirname(fileURLToPath(import.meta.url));
const config = JSON.parse(fs.readFileSync(path.join(racine, 'doublons-liste-blanche.json'), 'utf8'));
const listeBlanche = config.entrees || [];
const manifest = JSON.parse(fs.readFileSync(path.join(dossier, 'manifest.json'), 'utf8'));
const cssSite = fs.readFileSync('/home/user/histoiredefamille/public/assets/style.css');
const cssTheme = process.env.HDF_THEME_CSS && fs.existsSync(process.env.HDF_THEME_CSS)
    ? fs.readFileSync(process.env.HDF_THEME_CSS) : null;

const serveur = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://x');
    if (url.pathname === '/assets/style.css' || url.pathname === '/style.css') {
        res.writeHead(200, { 'Content-Type': 'text/css' });
        return res.end(cssSite);
    }
    if (url.pathname === '/theme.css' && cssTheme) {
        res.writeHead(200, { 'Content-Type': 'text/css' });
        return res.end(cssTheme);
    }
    const nom = decodeURIComponent(url.pathname.replace('/', ''));
    const fichier = path.join(dossier, nom);
    if (nom.endsWith('.html') && fs.existsSync(fichier)) {
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        return res.end(fs.readFileSync(fichier));
    }
    res.writeHead(404);
    res.end('non');
});
await new Promise(r => serveur.listen(8941, '0.0.0.0', r));

process.env.LD_LIBRARY_PATH = process.env.LD_LIBRARY_PATH || '/tmp/al2023/lib';
const browser = await puppeteer.launch({
    executablePath: await chromium.executablePath(),
    args: [...chromium.args, '--no-sandbox'],
    headless: true,
});

function normaliser(texte) {
    return (texte || '').replace(/\s+/g, ' ').trim().toLowerCase();
}

function dansListeBlanche(entree, doublon) {
    return listeBlanche.some(e =>
        (e.page === '*' || entree.route.includes(e.page))
        && e.type === doublon.type
        && normaliser(doublon.texte).includes(normaliser(e.texte)));
}

const analysePage = () => {
    const visible = (el) => {
        const s = getComputedStyle(el);
        if (s.display === 'none' || s.visibility === 'hidden') return false;
        const r = el.getBoundingClientRect();
        return r.width > 0 && r.height > 0;
    };
    const texte = (el) => (el.innerText || '').replace(/\s+/g, ' ').trim();
    // Zone d'un élément : le bloc qui le contient. Deux blocs différents
    // (deux <section>, deux formulaires) sont des zones différentes : un
    // même lien dans « Anniversaires » et dans « Personnes ajoutées »
    // n'est pas un doublon, deux dans le même bloc oui.
    const zonesVues = new Map();
    const zone = (el) => {
        const z = el.closest('header, footer, nav, form, aside, main, section, [class*="menu"]');
        if (!z) return 'page';
        if (z.tagName === 'HEADER') return 'en-tete';
        if (z.tagName === 'FOOTER') return 'pied';
        if (z.tagName === 'NAV') {
            return (z.getAttribute('aria-label') || '').includes('Ariane') || z.classList.contains('fil')
                ? 'ariane' : 'navigation';
        }
        if (!zonesVues.has(z)) zonesVues.set(z, zonesVues.size + 1);
        const nom = z.tagName === 'FORM' ? 'formulaire' : z.tagName === 'ASIDE' ? 'colonne' : 'contenu';
        // Chaque ligne de tableau ou élément de liste est un item distinct :
        // le même libellé y désigne l'item de sa ligne, pas un doublon.
        const item = el.closest('li, tr');
        let suffixe = '';
        if (item && z.contains(item)) {
            if (!zonesVues.has(item)) zonesVues.set(item, zonesVues.size + 1);
            suffixe = '/item' + zonesVues.get(item);
        }
        return nom + '#' + zonesVues.get(z) + suffixe;
    };
    const doublons = [];
    const tous = [...document.querySelectorAll('body *')];

    // Identifiants dupliqués (même masqués : erreur HTML dans tous les cas).
    const ids = {};
    for (const el of tous) {
        if (el.id) ids[el.id] = (ids[el.id] || 0) + 1;
    }
    for (const [id, n] of Object.entries(ids)) {
        if (n > 1) doublons.push({ type: 'id-duplique', texte: '#' + id + ' ×' + n });
    }

    // Titres.
    const titres = tous.filter(el => /^H[123]$/.test(el.tagName) && visible(el));
    const h1 = titres.filter(el => el.tagName === 'H1');
    if (h1.length > 1) {
        doublons.push({ type: 'plusieurs-h1', texte: h1.map(t => texte(t)).join(' | ') });
    }
    const vus = new Map();
    for (const t of titres) {
        const cle = texte(t).toLowerCase();
        if (cle.length < 3) continue;
        if (vus.has(cle)) doublons.push({ type: 'titre-repete', texte: texte(t) });
        else vus.set(cle, t);
    }

    // Liens et boutons de même libellé + même destination, par zone.
    const liens = tous.filter(el =>
        (el.tagName === 'A' && el.getAttribute('href')) || el.tagName === 'BUTTON');
    const parCle = new Map();
    for (const l of liens) {
        if (!visible(l)) continue;
        const libelle = texte(l) || (l.getAttribute('aria-label') || '');
        // Un bouton soumet son formulaire : sa « destination » est l'action
        // du formulaire (deux lignes d'un tableau ont donc des destinations
        // différentes, un même bouton répété garde la même).
        const form = l.closest('form');
        const caches = form
            ? [...form.querySelectorAll('input[type="hidden"]')].map(i => i.name + '=' + i.value).join('&')
            : '';
        const dest = l.tagName === 'A'
            ? l.getAttribute('href')
            : '(formulaire ' + ((form || {}).getAttribute?.('action') || '?') + ' ' + caches + ')';
        if (libelle.length < 2) continue;
        const cle = zone(l) + '|' + libelle.toLowerCase() + '|' + dest;
        if (parCle.has(cle)) {
            doublons.push({ type: 'lien-repete', texte: libelle + ' → ' + dest + ' (' + zone(l) + ')' });
        } else {
            parCle.set(cle, l);
        }
    }

    // Lien du menu principal repris dans une navigation du contenu
    // (hors fil d'Ariane : revenir à la rubrique y est l'usage, pas un
    // doublon de menu).
    const menu = new Set([...document.querySelectorAll('header nav a, header a')]
        .filter(visible).map(a => a.getAttribute('href')));
    for (const a of document.querySelectorAll('main nav a')) {
        if (a.closest('[aria-label*="Ariane"], .fil, [class*="ariane"]')) continue;
        if (visible(a) && menu.has(a.getAttribute('href'))) {
            doublons.push({ type: 'lien-menu-et-secondaire', texte: texte(a) + ' → ' + a.getAttribute('href') });
        }
    }

    // Phrases longues affichées deux fois + blocs entiers répétés.
    const phrases = new Map();
    // Feuilles de texte uniquement : un bloc et son paragraphe enfant
    // portent le même texte, les compter tous les deux serait un faux
    // doublon du contrôle lui-même.
    const candidats = tous.filter(el =>
        ['P', 'LI', 'DIV', 'SECTION', 'TD', 'SUMMARY'].includes(el.tagName)
        && visible(el) && el.children.length === 0);
    for (const el of candidats) {
        const t = texte(el);
        if (t.length <= 40) continue;
        const cle = t.toLowerCase();
        if (phrases.has(cle)) {
            if (phrases.get(cle) !== el) {
                doublons.push({ type: 'phrase-repetee', texte: t.slice(0, 90) });
            }
        } else {
            phrases.set(cle, el);
        }
    }
    // La même phrase dans un title= ou un aria-label d'un élément visible.
    for (const el of tous) {
        if (!visible(el)) continue;
        for (const attr of ['title', 'aria-label']) {
            const v = el.getAttribute && el.getAttribute(attr);
            if (v && v.trim().length > 40 && phrases.has(v.trim().toLowerCase())) {
                doublons.push({ type: 'phrase-repetee', texte: v.trim().slice(0, 90) + ' (' + attr + ')' });
            }
        }
    }

    return doublons;
};

let total = 0;
let tolérés = 0;
const rapport = [];

for (const theme of (cssTheme ? ['papier', 'bleu'] : ['papier'])) {
    for (const largeur of [1280, 390]) {
        const page = await browser.newPage();
        await page.setViewport({ width: largeur, height: 900 });
        for (const entree of manifest) {
            await page.goto('http://127.0.0.1:8941/' + encodeURIComponent(entree.fichier), { waitUntil: 'load' });
            if (theme === 'bleu') {
                await page.addStyleTag({ content: cssTheme.toString() });
            }
            const trouves = await page.evaluate(analysePage);
            for (const d of trouves) {
                const ligne = { profil: entree.profil, route: entree.route, largeur, theme, ...d };
                if (dansListeBlanche(entree, d)) {
                    tolérés++;
                } else {
                    total++;
                    rapport.push(ligne);
                }
            }
        }
        await page.close();
    }
}

await browser.close();
serveur.close();

for (const l of rapport) {
    console.log(`${l.route} [${l.profil}, ${l.largeur}px, ${l.theme}] ${l.type} : ${l.texte}`);
}
console.log(`\n${rapport.length} doublon(s) hors liste blanche, ${tolérés} toléré(s) (liste blanche).`);
process.exit(rapport.length > 0 ? 1 : 0);
