// Contrôle « arbre : tout visible d'un coup sur téléphone » (1.39.1).
//
// Constat corrigé : à 390 px, l'arbre s'ouvrait à 100 % — 2 cartes sur 6
// dans le cadre, les autres coupées ou dehors — et même après
// « Voir tout », des cartes dépassaient le bord droit du dessin (la
// gravure ne couvrait que la boîte visible, pas le contenu défilant).
//
// Ce contrôle ouvre des pages /arbre rendues par le routeur à 360, 390
// et 430 px (JavaScript actif, comme un vrai téléphone) et mesure le
// rectangle réel de CHAQUE carte (.arbre-chene__noeud) :
//   - au chargement, après « Voir tout » et après « Centrer », chaque
//     carte est entièrement dans le cadre d'exploration ;
//   - aucun nom ni aucune date n'est coupé dans sa carte (le texte
//     mesuré tient dans la carte) ;
//   - si une fratrie n'est pas entièrement visible, l'indice sous le
//     cadre annonce le nombre exact de frères et sœurs restants ;
//   - la page ne déborde pas horizontalement.
//
//   node actions-arbre-visible.mjs /tmp/arbre-r1.html /tmp/arbre-r3.html …

import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import chromium from '@sparticuz/chromium';
import puppeteer from 'puppeteer-core';

const fichiers = process.argv.slice(2);
if (fichiers.length === 0) {
    console.error('Usage : actions-arbre-visible.mjs <page-arbre.html> […]');
    process.exit(2);
}
const racine = path.dirname(fileURLToPath(import.meta.url));
let depot = path.resolve(racine, '..', '..');
if (!fs.existsSync(path.join(depot, 'public/assets/style.css'))) {
    depot = process.env.HDF_REPO || '/home/user/histoiredefamille';
}

let htmlCourant = '';
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
    res.end(htmlCourant);
});
await new Promise(r => serveur.listen(8962, '0.0.0.0', r));

const browser = await puppeteer.launch({
    executablePath: await chromium.executablePath(),
    args: [...chromium.args, '--no-sandbox'],
    headless: true,
});
console.log('Navigateur :', await browser.version());

const mesurerCartes = () => {
    const cadreEl = document.querySelector('[data-explo-cadre]');
    const cadre = cadreEl.getBoundingClientRect();
    const cartes = [...document.querySelectorAll('.arbre-chene__noeud')].map(el => {
        const r = el.getBoundingClientRect();
        const nom = (el.querySelector('.arbre-chene__nom') || {}).textContent || '';
        let texteCoupe = false;
        for (const span of el.querySelectorAll('.arbre-chene__nom, .arbre-chene__dates')) {
            if (span.scrollWidth > span.clientWidth + 1 || span.scrollHeight > span.clientHeight + 1 && span.clientHeight > 0 && getComputedStyle(span).whiteSpace === 'nowrap') {
                texteCoupe = true;
            }
            const range = document.createRange();
            range.selectNodeContents(span);
            const rr = range.getBoundingClientRect();
            if (rr.width > r.width + 1) texteCoupe = true;
        }
        return {
            nom: nom.trim(),
            dedans: r.top >= cadre.top - 1 && r.bottom <= cadre.bottom + 1
                && r.left >= cadre.left - 1 && r.right <= cadre.right + 1,
            texteCoupe,
        };
    });
    const indice = document.querySelector('[data-explo-indice]');
    const premiereRangee = document.querySelector(
        '.arbre-chene__partie--racines .arbre-chene__rangee');
    let freresDehors = 0;
    if (premiereRangee) {
        for (const el of premiereRangee.querySelectorAll('.arbre-chene__noeud')) {
            const r = el.getBoundingClientRect();
            if (r.top < cadre.top - 1 || r.bottom > cadre.bottom + 1
                || r.left < cadre.left - 1 || r.right > cadre.right + 1) {
                freresDehors++;
            }
        }
    }
    return {
        cartes,
        freresDehors,
        indice: indice ? { visible: !indice.hidden, texte: indice.textContent.trim() } : null,
        debordementPage: document.documentElement.scrollWidth > window.innerWidth,
        hauteurCadre: Math.round(cadre.height),
        hauteurEcran: window.innerHeight,
    };
};

let echecs = 0;
for (const fichier of fichiers) {
    htmlCourant = fs.readFileSync(fichier, 'utf8');
    for (const largeur of [360, 390, 430]) {
        const page = await browser.newPage();
        await page.setViewport({ width: largeur, height: 800 });
        await page.goto('http://127.0.0.1:8962/arbre', { waitUntil: 'load' });
        await new Promise(r => setTimeout(r, 500));

        for (const etape of ['chargement', 'tout', 'centrer']) {
            if (etape !== 'chargement') {
                await page.evaluate((nom) => {
                    const b = document.querySelector(`[data-explo="${nom}"]`);
                    if (b) b.click();
                }, etape);
                await new Promise(r => setTimeout(r, 350));
            }
            const m = await page.evaluate(mesurerCartes);
            const dehors = m.cartes.filter(c => !c.dedans);
            const coupes = m.cartes.filter(c => c.texteCoupe);
            console.log(`[${path.basename(fichier)}, ${largeur}px, ${etape}] `
                + `${m.cartes.length - dehors.length}/${m.cartes.length} cartes dans le cadre`
                + (dehors.length ? ` — dehors : ${dehors.map(c => c.nom).join(', ')}` : '')
                + (coupes.length ? ` — texte coupé : ${coupes.map(c => c.nom).join(', ')}` : '')
                + ` ; cadre ${m.hauteurCadre}px/${m.hauteurEcran}px`
                + (m.indice ? ` ; indice ${m.indice.visible ? '« ' + m.indice.texte + ' »' : 'masqué'}` : ' ; indice ABSENT'));
            if (dehors.length > 0) { console.log(`ÉCHEC [${largeur}px, ${etape}] des cartes sont hors du cadre`); echecs++; }
            if (coupes.length > 0) { console.log(`ÉCHEC [${largeur}px, ${etape}] du texte est coupé dans une carte`); echecs++; }
            if (m.debordementPage) { console.log(`ÉCHEC [${largeur}px] la page déborde horizontalement`); echecs++; }
            if (m.indice === null) { console.log(`ÉCHEC [${largeur}px] l'indice « frères et sœurs » est absent de la page`); echecs++; }
            else if (m.freresDehors > 0) {
                const annonce = parseInt(m.indice.texte, 10);
                if (!m.indice.visible || annonce !== m.freresDehors) {
                    console.log(`ÉCHEC [${largeur}px, ${etape}] indice « ${m.indice.texte} » mais ${m.freresDehors} frère(s) et sœur(s) hors du cadre`);
                    echecs++;
                }
            } else if (m.indice.visible) {
                console.log(`ÉCHEC [${largeur}px, ${etape}] l'indice est visible alors que toute la fratrie tient dans le cadre`);
                echecs++;
            }
        }
        await page.close();
    }
}

await browser.close();
serveur.close();
console.log(echecs === 0 ? '\nOK : tout l’arbre est visible d’un coup, aucune carte coupée.' : `\n${echecs} échec(s).`);
process.exit(echecs === 0 ? 0 : 1);
