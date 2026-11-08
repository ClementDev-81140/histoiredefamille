/**
 * Contrôle « vrai navigateur » de la 1.35.1 : une vidéo d'archive à marque
 * M4A envoyée par morceaux, et un message de refus lisible.
 *
 *   CAPTURES=/tmp node tools/navigateur/actions-envoi-media.mjs 1280
 *   CAPTURES=/tmp node tools/navigateur/actions-envoi-media.mjs 390
 *
 * Le serveur de tools/navigateur/serveur.mjs doit tourner avec la page
 * /admin/envoyer rendue par rendre.php. Comme pour les autres contrôles
 * d'envoi, les adresses du protocole répondent ce que répond le site (mêmes
 * clés JSON) : **le contrôle du contenu réel est couvert par les essais PHP**
 * (`tests.php envoi` et `conversion`) ; ici, on vérifie le navigateur —
 * le dépôt du fichier synthétique, l'aboutissement de l'envoi, le message
 * de refus affiché, et la propreté de la page.
 *
 * Le fichier d'essai est le conteneur ISO synthétique des essais PHP
 * (marque « M4A », piste vidéo fabriquée octet par octet, aucun ffmpeg).
 *
 * Variables : CAPTURES, PORT.
 */
import puppeteer from 'puppeteer-core';
import chromium from '@sparticuz/chromium';

const largeur = Number(process.argv[2] || 1280);
const sortie = process.env.CAPTURES || '/tmp';
const port = Number(process.env.PORT || 8199);
const base = 'http://127.0.0.1:' + port;
const fichierVideo = process.env.FICHIER_VIDEO || '/tmp/Bienvenue sur la lune.MP4';
const fichierVide = process.env.FICHIER_VIDE || '/tmp/fichier-vide.mp4';

let ok = 0;
let ko = 0;
const verifier = (nom, condition, detail = '') => {
    if (condition) { ok++; console.log('  ok      ' + nom + (detail ? ' — ' + detail : '')); }
    else { ko++; console.log('  ÉCHEC   ' + nom + (detail ? ' — ' + detail : '')); }
};

const navigateur = await puppeteer.launch({
    executablePath: await chromium.executablePath(),
    args: [...chromium.args, '--no-sandbox', '--disable-dev-shm-usage'],
    headless: true,
    defaultViewport: { width: largeur, height: 900 },
});

const page = await navigateur.newPage();
await page.setCacheEnabled(false);
const erreurs = [];
page.on('pageerror', (e) => erreurs.push(String(e)));
page.on('console', (m) => { if (m.type() === 'error') erreurs.push(m.text()); });

await page.goto(base + '/admin/envoyer', { waitUntil: 'load' });
await page.waitForSelector('[data-envoi]');

// --- La vidéo à marque M4A -------------------------------------------------
const champ = await page.$('#envoi-fichiers');
verifier('le champ d’envoi existe', champ !== null);
await champ.uploadFile(fichierVideo);
try {
    await page.waitForFunction(
        () => document.body.textContent.includes('Le fichier est prêt')
            || document.body.textContent.includes('conversion'),
        { timeout: 30000 }
    );
    verifier('l’envoi de « Bienvenue sur la lune.MP4 » (marque M4A) aboutit dans le navigateur', true);
} catch (e) {
    verifier('l’envoi de « Bienvenue sur la lune.MP4 » (marque M4A) aboutit dans le navigateur', false, String(e).slice(0, 100));
}
const etatVideo = await page.evaluate(() => ({
    texte: document.body.textContent,
    statut: document.querySelector('.envoi-ligne__statut')?.textContent || '',
}));
verifier('aucun message de refus de type pour cette vidéo',
    !etatVideo.texte.includes('n’est pas celui d’une vidéo')
    && !etatVideo.texte.includes('type détecté'),
    etatVideo.statut.slice(0, 90));
await page.screenshot({ path: sortie + '/envoi-video-m4a-' + largeur + '.png' });
console.log('  capture envoi-video-m4a-' + largeur + '.png');

// --- Le refus lisible ---------------------------------------------------------
await champ.uploadFile(fichierVide);
await page.waitForFunction(
    () => document.body.textContent.includes('rien à envoyer'),
    { timeout: 15000 }
).catch(() => {});
const refus = await page.evaluate(() => document.body.textContent);
verifier('le refus d’un fichier vide est une phrase simple avec une action',
    refus.includes('Le fichier est vide (0 octet)') && refus.includes('recommencez'));
await page.screenshot({ path: sortie + '/envoi-refus-lisible-' + largeur + '.png' });
console.log('  capture envoi-refus-lisible-' + largeur + '.png');

// --- Propreté --------------------------------------------------------------------
const propre = await page.evaluate(() => ({
    stylesEnLigne: Array.from(document.querySelectorAll('[style]'))
        .filter((el) => !el.className || !String(el.className).includes('envoi')).length,
    scriptsEnLigne: Array.from(document.querySelectorAll('script:not([src])')).length,
    etrangers: Array.from(document.querySelectorAll('[src], link[href]'))
        .map((el) => el.getAttribute('src') || el.getAttribute('href'))
        .filter((a) => a && /^(https?:)?\/\//.test(a) && !a.startsWith(location.origin)).length,
    debordement: document.documentElement.scrollWidth - window.innerWidth,
    accept: document.querySelector('[data-envoi]')?.getAttribute('data-envoi-accept') || '',
}));
verifier('la page annonce les nouvelles extensions (.mts, .wmv, .flac…)',
    propre.accept.includes('.mts') && propre.accept.includes('.wmv') && propre.accept.includes('.flac'),
    propre.accept.slice(0, 120));
verifier('aucun script en ligne, aucune ressource extérieure, aucun scroll horizontal',
    propre.scriptsEnLigne === 0 && propre.etrangers === 0 && propre.debordement <= 1, JSON.stringify(propre));
verifier('aucune erreur JavaScript', erreurs.length === 0, erreurs.join(' / '));

await navigateur.close();
console.log('\n' + (ok + ko) + ' contrôle(s) — ' + ok + ' ok, ' + ko + ' échec(s).');
process.exit(ko === 0 ? 0 : 1);
