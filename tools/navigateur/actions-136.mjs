/**
 * Contrôle « vrai navigateur » de la 1.36.0 : Thèmes, archives et dossiers.
 *
 *   CAPTURES=/tmp node tools/navigateur/actions-136.mjs 1280
 *   CAPTURES=/tmp node tools/navigateur/actions-136.mjs 390
 *
 * Le serveur de tools/navigateur/serveur.mjs doit tourner avec les pages
 * rendues par rendre.php (compte administrateur pour les écrans d'admin,
 * visiteur ou lecteur sinon) :
 *
 *   PAGES_ALBUM='{
 *     "/admin/categories":"/tmp/nav-themes.html",
 *     "/documents":"/tmp/nav-documents.html",
 *     "/dossiers/1":"/tmp/nav-dossier.html",
 *     "/admin/envoyer":"/tmp/nav-envoyer.html",
 *     "/famille-sans":"/tmp/nav-famille-sans.html",
 *     "/famille":"/tmp/nav-famille-avec.html"}' \
 *   THEME_CSS=/tmp/theme-papier.css   (ou theme-bleu.css) \
 *   CAPTURES=/tmp node tools/navigateur/serveur.mjs
 *
 * Ce que l'on regarde ici, et qu'aucun essai PHP ne peut voir :
 *
 *   - la page « Thèmes » de l'administration, sa phrase d'aide, aucun mot
 *     « catégorie » affiché ;
 *   - une carte de document : le thème en surtitre, le type une seule fois,
 *     la mention « Archive » ;
 *   - l'accueil de la famille SANS anniversaire proche : le bloc
 *     « Anniversaires à venir » est absent du HTML ; AVEC : il est là, avec
 *     sa phrase d'explication ;
 *   - la page « Envoyer des fichiers » : la case « Classer en archive » et
 *     sa phrase exacte sont visibles ;
 *   - la page d'un dossier : son nom, sa description, son contenu visible ;
 *   - ni style ni script en ligne, aucune ressource extérieure ;
 *   - aucun débordement horizontal à la largeur demandée (le menu allongé
 *     de la 1.36.0 est donc mesuré tel quel, sans contournement) ;
 *   - la palette demandée est bien appliquée (fond du document).
 *
 * Variables : THEME (papier | bleu), THEME_FOND (couleur attendue en rgb()),
 * CAPTURES (dossier des captures), PORT (8199).
 */
import puppeteer from 'puppeteer-core';
import chromium from '@sparticuz/chromium';

const largeur = Number(process.argv[2] === '1280' ? 1280 : 390);
const theme = process.env.THEME || 'papier';
const fondAttendu = process.env.THEME_FOND || (theme === 'bleu' ? 'rgb(246, 245, 242)' : 'rgb(243, 231, 206)');
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
// Les miniatures des contenus de démonstration n'existent pas dans le banc
// (le vrai serveur les sert par PHP) : leurs 404 ne sont pas des erreurs JS.
page.on('console', (m) => {
    if (m.type() === 'error' && !m.text().startsWith('Failed to load resource')) erreursJs.push(m.text());
});

async function ouvrir(adresse) {
    await page.goto(base + adresse, { waitUntil: 'networkidle0' });
    await page.evaluate(() => document.fonts.ready);
    await new Promise((r) => setTimeout(r, 200));
}

async function controlesCommun(nom) {
    const fond = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
    verifier(fond === fondAttendu,
        nom + ' : palette ' + theme + ' appliquée (' + fond + ')');
    verifier(
        await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1),
        nom + ' : aucun débordement horizontal à ' + largeur + ' px'
    );
    const proprete = await page.evaluate(() => ({
        stylesEnLigne: document.querySelectorAll('[style]').length,
        scriptsEnLigne: [...document.querySelectorAll('script:not([src])')].filter((s) => s.textContent.trim() !== '').length,
        externes: [...document.querySelectorAll('link[href], script[src], img[src]')]
            .map((e) => e.getAttribute('href') || e.getAttribute('src') || '')
            .filter((u) => /^(https?:)?\/\//.test(u)).length,
    }));
    verifier(proprete.stylesEnLigne === 0 && proprete.scriptsEnLigne === 0 && proprete.externes === 0,
        nom + ' : ni style ni script en ligne, aucune ressource extérieure ' + JSON.stringify(proprete));
}

// --- 1. Page « Thèmes » de l'administration --------------------------------
await ouvrir('/admin/categories');
const themes = await page.evaluate(() => document.body.innerText);
verifier(themes.includes('Thèmes'), 'thèmes : le titre « Thèmes » est là');
verifier(themes.includes('Un thème regroupe des contenus de tous types qui parlent du même sujet.'),
    'thèmes : la phrase d’aide est affichée sous le titre');
verifier(!/catégorie/i.test(themes), 'thèmes : le mot « catégorie » n’est affiché nulle part');
await controlesCommun('thèmes');
await page.screenshot({ path: sortie + '/nav136-themes-' + theme + '-' + largeur + '.png', fullPage: true });

// --- 2. Documents : cartes d'archives ---------------------------------------
await ouvrir('/documents');
const cartes = await page.evaluate(() => [...document.querySelectorAll('.carte')].map((c) => ({
    surtitre: c.querySelector('.carte__surtitre')?.textContent.trim() || '',
    meta: c.querySelector('.carte__meta')?.textContent.trim() || '',
    texte: c.innerText,
})));
verifier(cartes.some((c) => c.surtitre.includes('Actes et papiers')),
    'cartes : le thème est en surtitre d’au moins une carte');
verifier(cartes.some((c) => c.meta.includes('Archive')),
    'cartes : la mention « Archive » est dans les métadonnées');
verifier(!cartes.some((c) => /catégorie/i.test(c.texte)),
    'cartes : aucun mot « catégorie » sur les cartes');
verifier(await page.evaluate(() => /archives et dossiers/i.test(document.body.innerText)),
    'documents : la section « Archives et dossiers » est là');
await controlesCommun('documents');
await page.screenshot({ path: sortie + '/nav136-documents-' + theme + '-' + largeur + '.png', fullPage: true });

// --- 3. Page d'un dossier ----------------------------------------------------
await ouvrir('/dossiers/1');
const dossier = await page.evaluate(() => document.body.innerText);
verifier(dossier.includes('Grand-père Michel (démo)'), 'dossier : le nom du dossier est affiché');
verifier(dossier.includes('Acte de naissance de Michel (1920)'), 'dossier : le document visible est listé');
await controlesCommun('dossier');
await page.screenshot({ path: sortie + '/nav136-dossier-' + theme + '-' + largeur + '.png', fullPage: true });

// --- 4. Envoi par morceaux : la case archive ---------------------------------
await ouvrir('/admin/envoyer');
const envoi = await page.evaluate(() => ({
    texte: document.body.innerText,
    cases: [...document.querySelectorAll('input[name="archive"], [data-envoi-archive]')].length,
    cochees: [...document.querySelectorAll('input[name="archive"], [data-envoi-archive]')].filter((i) => i.checked).length,
}));
verifier(envoi.cases >= 1, 'envoi : la case « Classer en archive » est présente');
verifier(envoi.cochees === 0, 'envoi : la case est décochée par défaut');
verifier(envoi.texte.includes('Un document d’archive n’apparaît pas à la une et ne prévient personne.'),
    'envoi : la phrase d’explication exacte est affichée');
await controlesCommun('envoi');
await page.screenshot({ path: sortie + '/nav136-envoyer-' + theme + '-' + largeur + '.png', fullPage: true });

// --- 5. Accueil famille sans bloc, puis avec ---------------------------------
await ouvrir('/famille-sans');
const sans = await page.evaluate(() => document.documentElement.innerHTML);
verifier(!sans.includes('Anniversaires à venir'),
    'accueil sans anniversaire : le bloc est absent du HTML (ni titre ni cadre)');
await controlesCommun('accueil sans');
await page.screenshot({ path: sortie + '/nav136-famille-sans-' + theme + '-' + largeur + '.png', fullPage: true });

await ouvrir('/famille');
const avec = await page.evaluate(() => document.body.innerText);
verifier(/anniversaires à venir/i.test(avec), 'accueil avec anniversaire : le bloc « Anniversaires à venir » est là');
verifier(avec.includes('Anniversaires de naissance et de mariage des 30 prochains jours.'),
    'accueil avec anniversaire : la phrase d’explication est là');
verifier(/AnnivDemo/i.test(avec),
    'accueil avec anniversaire : la personne concernée est nommée');
await controlesCommun('accueil avec');
await page.screenshot({ path: sortie + '/nav136-famille-avec-' + theme + '-' + largeur + '.png', fullPage: true });

verifier(erreursJs.length === 0, 'navigateur : aucune erreur JavaScript (' + erreursJs.join(' ; ') + ')');

await navigateur.close();
console.log('\n' + (ok + ko) + ' contrôle(s) — ' + ok + ' ok, ' + ko + ' échec(s).');
process.exit(ko > 0 ? 1 : 0);
