// Contrôle « arbre sur téléphone » (1.39.0).
//
// Deux régressions vues sur téléphone, que les essais PHP ne pouvaient
// pas voir :
//   1. le décor (la gravure du chêne) n'était derrière les noms que sur
//      grand écran ; sur téléphone, c'était un bandeau séparé au-dessus,
//      et le dessin des cartes restait sur fond uni ;
//   2. quand une personne avait plusieurs enfants, un seul semblait
//      exister : les données étaient complètes, mais la rangée passait
//      en colonne et le cadre d'exploration (hauteur fixe, contenu
//      rogné) ne laissait tenir qu'un enfant à l'écran.
//
// Ce contrôle ouvre la page /arbre rendue par le routeur (un parent avec
// au moins trois enfants) à 360, 390 et 430 px et échoue si :
//   - le fond du dessin n'est pas la gravure, à ces trois largeurs ;
//   - les enfants d'une même génération ne sont pas tous là (nombre de
//     cartes = nombre annoncé par la légende) et sur la même ligne
//     (même ordonnée dans le dessin) ;
//   - un nom est coupé dans sa carte ;
//   - les traits parents → enfants ne tombent pas en face des centres
//     des cartes (6 px de tolérance) ;
//   - la page elle-même déborde horizontalement ;
//   - avec JavaScript et le bouton « Voir tout », un enfant reste hors
//     du cadre d'exploration.
//
//   node actions-arbre-mobile.mjs /tmp/arbre.html

import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import chromium from '@sparticuz/chromium';
import puppeteer from 'puppeteer-core';

const fichierHtml = process.argv[2] || '/tmp/arbre.html';
const racine = path.dirname(fileURLToPath(import.meta.url));
let depot = path.resolve(racine, '..', '..');
if (!fs.existsSync(path.join(depot, 'public/assets/style.css'))) {
    depot = process.env.HDF_REPO || '/home/user/histoiredefamille';
}
const html = fs.readFileSync(fichierHtml);

const serveur = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://x');
    if (url.pathname.startsWith('/assets/')) {
        const f = path.join(depot, 'public', url.pathname);
        if (fs.existsSync(f)) {
            const type = url.pathname.endsWith('.js') ? 'text/javascript'
                : url.pathname.endsWith('.css') ? 'text/css'
                : url.pathname.endsWith('.webp') ? 'image/webp' : 'image/jpeg';
            res.writeHead(200, { 'Content-Type': type });
            return res.end(fs.readFileSync(f));
        }
    }
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    res.end(html);
});
await new Promise(r => serveur.listen(8961, '0.0.0.0', r));

const browser = await puppeteer.launch({
    executablePath: await chromium.executablePath(),
    args: [...chromium.args, '--no-sandbox'],
    headless: true,
});
console.log('Navigateur :', await browser.version());

const mesurer = () => {
    const dessin = document.querySelector('.arbre-chene__dessin');
    const enfants = [...document.querySelectorAll(
        '.arbre-chene__partie--racines .arbre-chene__rangee .arbre-chene__noeud')];
    const legende = document.querySelector('.arbre-chene__partie--racines .arbre-chene__compte');
    const svg = document.querySelector('.arbre-chene__partie--racines .arbre-chene__traits');
    const base = dessin.getBoundingClientRect().left - dessin.scrollLeft;
    const centres = enfants.map(el => {
        const b = el.getBoundingClientRect();
        return b.left + b.width / 2 - base;
    });
    let traits = [];
    if (svg) {
        const r = svg.getBoundingClientRect();
        traits = [...svg.querySelectorAll('line')]
            .filter(l => l.getAttribute('y1') === '12' && l.getAttribute('y2') === '24')
            .map(l => (parseFloat(l.getAttribute('x1')) / 100) * r.width + (r.left - base));
    }
    return {
        // Le décor derrière les noms : fond du dessin jusqu'en 1.39.x,
        // couche ::before en filigrane depuis 1.40.0 — les deux comptent.
        fond: dessin
            ? (getComputedStyle(dessin).backgroundImage !== 'none'
                ? getComputedStyle(dessin).backgroundImage
                : getComputedStyle(dessin, '::before').backgroundImage)
            : '',
        nbEnfants: enfants.length,
        annonce: legende ? parseInt(legende.textContent, 10) : null,
        memeLigne: new Set(enfants.map(el => Math.round(el.getBoundingClientRect().top))).size === 1,
        nomsCoupes: enfants.filter(el => el.scrollWidth > el.clientWidth + 1)
            .map(el => el.textContent.replace(/\s+/g, ' ').trim().slice(0, 30)),
        centres, traits,
        debordementPage: document.documentElement.scrollWidth > window.innerWidth,
        defilementDessin: dessin ? dessin.scrollWidth > dessin.clientWidth : false,
    };
};

let echecs = 0;
for (const largeur of [360, 390, 430]) {
    const page = await browser.newPage();
    await page.setJavaScriptEnabled(false);
    await page.setViewport({ width: largeur, height: 800 });
    await page.goto('http://127.0.0.1:8961/arbre', { waitUntil: 'load' });
    const m = await page.evaluate(mesurer);
    console.log(`[${largeur}px, sans JavaScript] fond=${m.fond !== 'none' ? 'gravure' : 'AUCUN'} `
        + `enfants=${m.nbEnfants}/${m.annonce} mêmeLigne=${m.memeLigne} défilement=${m.defilementDessin}`);
    if (m.fond === 'none' || m.fond === '') { console.log(`ÉCHEC [${largeur}px] le décor n'est pas derrière les noms`); echecs++; }
    if (m.annonce !== null && m.nbEnfants !== m.annonce) { console.log(`ÉCHEC [${largeur}px] ${m.nbEnfants} enfant(s) affiché(s), ${m.annonce} annoncé(s)`); echecs++; }
    if (m.nbEnfants < 3) { console.log(`ÉCHEC [${largeur}px] il faut une personne avec au moins trois enfants pour ce contrôle`); echecs++; }
    if (!m.memeLigne) { console.log(`ÉCHEC [${largeur}px] les frères et sœurs ne sont pas sur la même ligne`); echecs++; }
    if (m.nomsCoupes.length > 0) { console.log(`ÉCHEC [${largeur}px] nom(s) coupé(s) : ${m.nomsCoupes.join(' ; ')}`); echecs++; }
    if (m.debordementPage) { console.log(`ÉCHEC [${largeur}px] la page déborde horizontalement`); echecs++; }
    if (m.traits.length === m.centres.length && m.centres.length > 0) {
        const ecartMax = Math.max(...m.centres.map((c, i) => Math.abs(c - m.traits[i])));
        console.log(`    traits : écart maximal ${Math.round(ecartMax)} px avec les centres des cartes`);
        if (ecartMax > 6) { console.log(`ÉCHEC [${largeur}px] les traits ne tombent pas en face des enfants`); echecs++; }
    }

    // Avec JavaScript : « Voir tout » doit montrer chaque enfant dans le cadre.
    await page.setJavaScriptEnabled(true);
    await page.reload({ waitUntil: 'load' });
    await new Promise(r => setTimeout(r, 400));
    await page.evaluate(() => {
        const bouton = document.querySelector('[data-explo="tout"]');
        if (bouton) bouton.click();
    });
    await new Promise(r => setTimeout(r, 300));
    const visibles = await page.evaluate(() => {
        const cadre = document.querySelector('[data-explo-cadre]').getBoundingClientRect();
        return [...document.querySelectorAll('.arbre-chene__partie--racines .arbre-chene__noeud')]
            .map(el => {
                const b = el.getBoundingClientRect();
                return b.top >= cadre.top - 1 && b.bottom <= cadre.bottom + 1
                    && b.left >= cadre.left - 1 && b.right <= cadre.right + 1;
            });
    });
    console.log(`[${largeur}px, JavaScript + « Voir tout »] enfants dans le cadre : ${visibles.filter(Boolean).length}/${visibles.length}`);
    if (visibles.some(v => !v)) { console.log(`ÉCHEC [${largeur}px] un enfant reste hors du cadre après « Voir tout »`); echecs++; }
    await page.close();
}

await browser.close();
serveur.close();
console.log(echecs === 0 ? '\nOK : décor derrière les noms, fratrie entière sur une ligne.' : `\n${echecs} échec(s).`);
process.exit(echecs === 0 ? 0 : 1);
