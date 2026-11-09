// Contrôle « Dans la famille visible sur ordinateur » (1.38.1).
//
// Pourquoi ce contrôle existe : en 1.38.0, le bloc « Dans la famille » de
// l'accueil connecté est devenu un seul <details> (fermé) pour le téléphone
// et le bureau, la feuille de style forçant l'affichage sur grand écran.
// Les moteurs récents masquent pourtant le contenu d'un <details> fermé
// par ::details-content (content-visibility: hidden) : les boîtes des
// enfants gardaient une taille, mais aucun texte n'était peint — bug vu
// seulement dans un vrai navigateur récent, corrigé en 1.38.1.
//
// Ce contrôle échoue donc si, à 1280 px, le texte du bloc n'est pas
// réellement visible. « Visible » est mesuré sur le texte lui-même, pas
// sur la taille des boîtes :
//   1. le <details> est fermé (c'est le cas en production) et la règle
//      ::details-content le rend visible (content-visibility: visible) ;
//   2. chaque titre et chaque ligne du bloc produit des rectangles de
//      texte (Range.getClientRects) non vides ;
//   3. le centre de ces rectangles répond au pointage
//      (document.elementFromPoint) : le texte est peint et atteignable,
//      pas seulement dimensionné.
//
//   node actions-famille-visible.mjs /tmp/rendus
//
// Variables : HDF_CSS (feuille de style à servir au lieu de celle du
// dépôt — sert à vérifier que le contrôle échoue avec la feuille fautive),
// HDF_THEME_CSS (feuille de thème bleu, optionnelle).

import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import chromium from '@sparticuz/chromium';
import puppeteer from 'puppeteer-core';

const dossier = process.argv[2] || '/tmp/rendus';
const racine = path.dirname(fileURLToPath(import.meta.url));
let depot = path.resolve(racine, '..', '..');
if (!fs.existsSync(path.join(depot, 'public/assets/style.css'))) {
    // Copie du contrôle hors du dépôt (banc d'essai) : dépôt par défaut.
    depot = process.env.HDF_REPO || '/home/user/histoiredefamille';
}
const cssSite = fs.readFileSync(process.env.HDF_CSS || path.join(depot, 'public/assets/style.css'));
const cssTheme = process.env.HDF_THEME_CSS && fs.existsSync(process.env.HDF_THEME_CSS)
    ? fs.readFileSync(process.env.HDF_THEME_CSS) : null;
const manifest = JSON.parse(fs.readFileSync(path.join(dossier, 'manifest.json'), 'utf8'));
const entree = manifest.find(e => e.profil === 'lecteur' && e.route === '/');
if (!entree) {
    console.error('Page d’accueil connecté (lecteur, /) absente du dossier de rendus.');
    process.exit(2);
}

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
await new Promise(r => serveur.listen(8953, '0.0.0.0', r));

const browser = await puppeteer.launch({
    executablePath: await chromium.executablePath(),
    args: [...chromium.args, '--no-sandbox'],
    headless: true,
});
console.log('Navigateur :', await browser.version());

const mesure = () => {
    const bloc = document.querySelector('.dans-la-famille');
    if (!bloc) return { erreur: 'bloc « Dans la famille » absent de la page' };
    const details = bloc.querySelector('details.accueil-famille__repli');
    const pseudo = details ? getComputedStyle(details, '::details-content') : null;

    // Le texte, mesuré : pour chaque élément de texte du bloc, les
    // rectangles de ses caractères (Range) et le pointage en leur centre.
    const textes = [];
    for (const el of bloc.querySelectorAll('h2, h3, li')) {
        const texte = (el.innerText || '').replace(/\s+/g, ' ').trim();
        if (!texte) continue;
        // Le pointage se fait dans la fenêtre : on amène le texte à l'écran.
        el.scrollIntoView({ block: 'center' });
        const range = document.createRange();
        range.selectNodeContents(el);
        const rects = [...range.getClientRects()].filter(r => r.width > 0 && r.height > 0);
        let pointe = false;
        if (rects.length > 0) {
            const r = rects[0];
            const touche = document.elementFromPoint(
                r.left + Math.min(5, r.width / 2), r.top + r.height / 2);
            pointe = touche !== null && (el.contains(touche) || touche.contains(el) || bloc.contains(touche));
        }
        textes.push({ texte: texte.slice(0, 60), rectangles: rects.length, pointe });
    }

    const summary = details ? details.querySelector('summary') : null;

    // Les liens « Tout voir » réellement affichés : un seul doit l'être
    // (celui de l'en-tête sur ordinateur, celui du bas sur téléphone).
    let toutVoirVisibles = 0;
    for (const a of bloc.querySelectorAll('a[href="/famille"]')) {
        const range = document.createRange();
        range.selectNodeContents(a);
        const affiche = [...range.getClientRects()].some(r => r.width > 0 && r.height > 0)
            && getComputedStyle(a).visibility !== 'hidden';
        if (affiche) toutVoirVisibles++;
    }

    return {
        detailsPresent: details !== null,
        detailsOuvert: details ? details.open : null,
        pseudoContentVisibility: pseudo ? pseudo.contentVisibility : null,
        summaryDisplay: summary ? getComputedStyle(summary).display : null,
        debordement: document.documentElement.scrollWidth > window.innerWidth,
        toutVoirVisibles,
        textes,
    };
};

let echecs = 0;
for (const theme of (cssTheme ? ['papier', 'bleu'] : ['papier'])) {
    for (const largeur of [1280, 390]) {
        const page = await browser.newPage();
        await page.setViewport({ width: largeur, height: 900 });
        await page.goto('http://127.0.0.1:8953/' + encodeURIComponent(entree.fichier), { waitUntil: 'load' });
        if (theme === 'bleu') {
            await page.addStyleTag({ content: cssTheme.toString() });
        }
        const r = await page.evaluate(mesure);
        const etiquette = `${largeur}px, ${theme}`;
        if (r.erreur) {
            console.log(`ÉCHEC [${etiquette}] ${r.erreur}`);
            echecs++;
            continue;
        }
        console.log(`[${etiquette}] <details> présent=${r.detailsPresent} ouvert=${r.detailsOuvert} `
            + `::details-content=${r.pseudoContentVisibility} summary=${r.summaryDisplay} `
            + `débordement=${r.debordement}`);
        for (const t of r.textes) {
            console.log(`    texte « ${t.texte} » : ${t.rectangles} rectangle(s), pointé=${t.pointe}`);
        }
        if (r.debordement) {
            console.log(`ÉCHEC [${etiquette}] débordement horizontal`);
            echecs++;
        }
        if (largeur === 1280) {
            // Le cœur du contrôle (1.38.1) : fermé mais visible, texte peint.
            if (!r.detailsPresent || r.detailsOuvert !== false) {
                console.log(`ÉCHEC [${etiquette}] le <details> devrait être présent et fermé (cas de production)`);
                echecs++;
            }
            if (r.pseudoContentVisibility !== 'visible') {
                console.log(`ÉCHEC [${etiquette}] ::details-content n'est pas visible `
                    + `(content-visibility: ${r.pseudoContentVisibility}) : le contenu serait masqué`);
                echecs++;
            }
            if (r.summaryDisplay !== 'none') {
                console.log(`ÉCHEC [${etiquette}] le <summary> devrait être masqué sur ordinateur`);
                echecs++;
            }
            if (r.toutVoirVisibles !== 1) {
                console.log(`ÉCHEC [${etiquette}] ${r.toutVoirVisibles} lien(s) « Tout voir » visible(s), 1 attendu`);
                echecs++;
            }
            if (r.textes.length === 0) {
                console.log(`ÉCHEC [${etiquette}] aucun texte dans le bloc`);
                echecs++;
            }
            for (const t of r.textes) {
                if (t.rectangles === 0 || !t.pointe) {
                    console.log(`ÉCHEC [${etiquette}] texte non visible : « ${t.texte} »`);
                    echecs++;
                }
            }
        }
        await page.close();
    }
}

await browser.close();
serveur.close();
console.log(echecs === 0 ? '\nOK : le bloc « Dans la famille » est visible, texte mesuré.' : `\n${echecs} échec(s).`);
process.exit(echecs === 0 ? 0 : 1);
