/**
 * Contrôle « vrai navigateur » de la 1.32.0 : thèmes, vidéos sans image,
 * articles sans photo.
 *
 *   CAPTURES=/tmp node tools/navigateur/actions-132.mjs 390
 *   CAPTURES=/tmp node tools/navigateur/actions-132.mjs 1280
 *
 * Le serveur de tools/navigateur/serveur.mjs doit tourner avec les pages
 * rendues par rendre.php :
 *
 *   PAGES_ALBUM='{"/":"/tmp/accueil.html",
 *                 "/articles":"/tmp/articles.html",
 *                 "/videos":"/tmp/videos.html"}' \
 *   THEME_CSS=/tmp/theme-papier.css   (ou theme-bleu.css) \
 *   CAPTURES=/tmp node tools/navigateur/serveur.mjs
 *
 * Ce que l'on regarde ici, et qu'aucun essai PHP ne peut voir :
 *
 *   - une **vidéo sans image** n'affiche **ni carré gris, ni vignette vide** :
 *     la carte reste sur le papier et porte l'icône de lecture, visible ;
 *   - un **article sans photo** n'affiche **aucun cadre** (pas d'image, pas de
 *     lettre, pas de plaque de remplacement) et la carte garde une hauteur ;
 *   - toutes les images de la page sont réellement chargées (aucune image
 *     cassée ne remplace le vide) ;
 *   - ni style ni script en ligne, aucune ressource extérieure ;
 *   - aucun débordement horizontal à la largeur demandée ;
 *   - la palette demandée est bien appliquée (fond du document).
 *
 * Variables : THEME (papier | bleu), THEME_FOND (couleur attendue en rgb()),
 * CAPTURES (dossier des captures), PORT (8199).
 */
import puppeteer from 'puppeteer-core';
import chromium from '@sparticuz/chromium';

const largeur = Number(process.argv[2] === '1280' ? 1280 : 390);
const theme = process.env.THEME || 'papier';
const fondAttendu = process.env.THEME_FOND || (theme === 'bleu' ? 'rgb(238, 243, 248)' : 'rgb(243, 231, 206)');
const sortie = process.env.CAPTURES || '/tmp';
const port = Number(process.env.PORT || 8199);
const base = 'http://127.0.0.1:' + port;

let ok = 0;
let ko = 0;
const souci = (texte) => { console.log('  ÉCHEC   ' + texte); ko++; };
const bien = (texte) => { console.log('  ok      ' + texte); ok++; };
const verifier = (condition, texte) => (condition ? bien(texte) : souci(texte));

const navigateur = await puppeteer.launch({
    executablePath: await chromium.executablePath(),
    args: [...chromium.args, '--no-sandbox', '--disable-dev-shm-usage'],
    headless: true,
});

const page = await navigateur.newPage();
await page.setViewport({ width: largeur, height: 900, deviceScaleFactor: 1 });
const erreursJs = [];
page.on('pageerror', (e) => erreursJs.push(String(e)));
page.on('console', (m) => { if (m.type() === 'error') erreursJs.push(m.text()); });

async function ouvrir(chemin) {
    await page.goto(base + chemin, { waitUntil: 'networkidle0' });
}

// --- 1. Accueil d'un visiteur : rien ne déborde, les images sont chargées ---
await ouvrir('/');
verifier(
    await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1),
    'accueil : aucun débordement horizontal à ' + largeur + ' px'
);
const fond = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
verifier(fond === fondAttendu, 'palette ' + theme + ' : le fond du document est ' + fond + ' (attendu ' + fondAttendu + ')');

const imagesAccueil = await page.evaluate(() => Array.from(document.images).map((i) => ({
    src: i.getAttribute('src') || '',
    charge: i.complete && i.naturalWidth > 0,
})));
verifier(imagesAccueil.every((i) => i.charge), 'accueil : les ' + imagesAccueil.length + ' image(s) sont réellement chargées');

// --- 2. Liste des articles : un article sans photo n'a AUCUN cadre ---------
await ouvrir('/articles');
const articles = await page.evaluate(() => {
    const cartes = Array.from(document.querySelectorAll('.article-carte'));
    const sansImage = cartes.filter((c) => c.classList.contains('article-carte--sans-image'));
    return {
        total: cartes.length,
        sansImage: sansImage.length,
        imagesDansSansImage: sansImage.reduce((n, c) => n + c.querySelectorAll('img').length, 0),
        hauteurMin: sansImage.length === 0 ? 0 : Math.min(...sansImage.map((c) => Math.round(c.getBoundingClientRect().height))),
        // Aucun repli : pas de lettre, pas de plaque grise.
        replis: sansImage.reduce((n, c) => n + c.querySelectorAll(
            '[class*="placeholder"], [class*="initiale"], [class*="vignette-vide"], [class*="sans-photo__"]'
        ).length, 0),
        imagesCassées: Array.from(document.images).filter((i) => !(i.complete && i.naturalWidth > 0)).length,
    };
});
verifier(articles.total > 0, 'articles : la liste affiche ' + articles.total + ' carte(s)');
verifier(articles.imagesCassées === 0, 'articles : aucune image cassée dans la liste');
verifier(
    articles.imagesDansSansImage === 0,
    'articles : les ' + articles.sansImage + ' carte(s) sans photo n’affichent aucune balise d’image'
);
verifier(
    articles.replis === 0,
    'articles : aucun cadre, aucune lettre de remplacement sur ces cartes'
);
if (articles.sansImage > 0) {
    verifier(articles.hauteurMin >= 60, 'articles : une carte sans photo garde une hauteur lisible (' + articles.hauteurMin + ' px)');
}

// --- 3. Liste des vidéos : sans image, une icône de lecture, pas de carré ---
await ouvrir('/videos');
const videos = await page.evaluate(() => {
    const apercus = Array.from(document.querySelectorAll('.carte__apercu--video'));
    const sansImage = apercus.filter((a) => a.classList.contains('carte__apercu--sans-image'));
    const details = sansImage.map((a) => {
        const lecture = a.querySelector('.carte__lecture');
        const style = getComputedStyle(a);
        return {
            images: a.querySelectorAll('img').length,
            lectureVisible: lecture !== null && lecture.getBoundingClientRect().width > 0
                && lecture.getBoundingClientRect().height > 0,
            fond: style.backgroundColor,
            // Le carré gris d'avant : un fond sombre et uni, sans image.
            // Un fond transparent laisse voir le papier : ce n'est pas un carré.
            gris: (() => {
                const nombres = (style.backgroundColor.match(/\d+/g) || []).map(Number);
                const transparent = nombres.length < 4 || nombres[3] === 0;
                if (transparent || nombres.length < 3) {
                    return false;
                }
                return (nombres[0] + nombres[1] + nombres[2]) / 3 < 128;
            })(),
        };
    });
    return {
        total: apercus.length,
        sansImage: sansImage.length,
        details,
        imagesCassées: Array.from(document.images).filter((i) => !(i.complete && i.naturalWidth > 0)).length,
    };
});
verifier(videos.total > 0, 'vidéos : la liste affiche ' + videos.total + ' aperçu(s)');
verifier(videos.imagesCassées === 0, 'vidéos : aucune image cassée dans la liste');
verifier(
    videos.details.every((d) => d.images === 0),
    'vidéos : les ' + videos.sansImage + ' vidéo(s) sans image n’ont aucune balise d’image'
);
verifier(
    videos.details.every((d) => d.lectureVisible),
    'vidéos : chacune montre l’icône de lecture, visiblement'
);
verifier(
    videos.details.every((d) => !d.gris),
    'vidéos : aucun carré gris (fond de l’aperçu : ' + [...new Set(videos.details.map((d) => d.fond))].join(', ') + ')'
);

// --- 4. Ni style ni script en ligne, aucune ressource extérieure -----------
const page0 = await page.content();
verifier(!/\sstyle="/i.test(page0), 'aucun attribut style= dans la page');
verifier(!/<script(?![^>]*\ssrc=)[^>]*>[\s\S]*?<\/script>/i.test(page0), 'aucun script en ligne');
verifier(!/(src|href)="https?:\/\//i.test(page0), 'aucune ressource extérieure');

// --- 5. Captures -----------------------------------------------------------
await ouvrir('/articles');
await page.screenshot({ path: sortie + '/articles-sans-photo-' + theme + '-' + largeur + '.png', fullPage: true });
await ouvrir('/videos');
await page.screenshot({ path: sortie + '/videos-sans-image-' + theme + '-' + largeur + '.png', fullPage: true });

verifier(erreursJs.length === 0, 'aucune erreur JavaScript (' + erreursJs.slice(0, 3).join(' | ') + ')');

await navigateur.close();
console.log('\n' + (ok + ko) + ' vérification(s) — ' + ok + ' ok, ' + ko + ' échec(s).');
process.exit(ko === 0 ? 0 : 1);
