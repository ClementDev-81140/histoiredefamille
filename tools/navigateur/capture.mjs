/**
 * Capture d'écran d'une page servie par le banc (serveur.mjs).
 *
 *   CAPTURES=/tmp node tools/navigateur/capture.mjs 1280 / /tmp/accueil.png 1
 *
 * Arguments : largeur, adresse, fichier .png, page entière (1, défaut) ou
 * fenêtre (0), hauteur de fenêtre (900 par défaut). `PORT` choisit le port du
 * serveur (8199 par défaut).
 *
 * C'est ce qui produit les PNG de `docs/captures/` : la page doit avoir été
 * rendue par `rendre.php`, ses images converties par `inline-medias.php`, et le
 * serveur lancé avec `THEME_CSS=` la feuille du thème voulu (papier ou bleu).
 */
// Capture d'écran d'une page servie par le banc (serveur.mjs).
// usage : node shot.mjs <largeur> <adresse> <fichier.png> [page-entière 1|0] [hauteur]
import puppeteer from 'puppeteer-core';
import chromium from '@sparticuz/chromium';

const largeur = Number(process.argv[2] || 1280);
const adresse = process.argv[3] || '/';
const fichier = process.argv[4] || '/tmp/h/capture.png';
const entiere = (process.argv[5] ?? '1') !== '0';
const hauteur = Number(process.argv[6] || 900);
const base = 'http://127.0.0.1:' + Number(process.env.PORT || 8199);

const navigateur = await puppeteer.launch({
    executablePath: await chromium.executablePath(),
    args: [...chromium.args, '--no-sandbox', '--disable-dev-shm-usage'],
    headless: true,
    defaultViewport: { width: largeur, height: hauteur },
});
try {
    const page = await navigateur.newPage();
    await page.setCacheEnabled(false);
    await page.goto(base + adresse, { waitUntil: 'load' });
    await page.evaluate(() => document.fonts.ready);
    await new Promise((r) => setTimeout(r, 250));
    await page.screenshot({ path: fichier, fullPage: entiere });
    console.log(fichier, 'écrit (', largeur, 'px', entiere ? 'page entière' : 'fenêtre', ')');
} finally {
    await navigateur.close();
}
