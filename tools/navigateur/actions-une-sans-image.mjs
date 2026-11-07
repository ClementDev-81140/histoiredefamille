/**
 * Contrôle « vrai navigateur » de la 1.32.1 : l'accueil public sans tuiles vides.
 *
 *   CAPTURES=/tmp node tools/navigateur/actions-une-sans-image.mjs 1280
 *   CAPTURES=/tmp node tools/navigateur/actions-une-sans-image.mjs 390
 *
 * Le serveur de tools/navigateur/serveur.mjs doit tourner avec la page rendue
 * par rendre.php :
 *
 *   PAGES_ALBUM='{"/":"/tmp/accueil-sans-image.html"}' \
 *   THEME_CSS=/tmp/theme-papier.css   (ou theme-bleu.css) \
 *   CAPTURES=/tmp node tools/navigateur/serveur.mjs
 *
 * Ce que l'on regarde ici, et qu'aucun essai PHP ne peut voir :
 *
 *   - un contenu **sans image** de la grille donne une **carte texte seule** :
 *     aucun `<img>`, aucune vignette, aucun bloc de rubrique, et **aucune
 *     tuile grise** (aucun descendant de la carte n'a la couleur
 *     `--papier-moyen`) ;
 *   - cette carte porte bien du texte : titre, date et extrait lisibles, sur
 *     toute la largeur de sa colonne ;
 *   - la une **sans image** occupe **toute la largeur** : le bloc de texte est
 *     aussi large que l'article, aucune colonne vide à droite ;
 *   - une **vidéo sans miniature** garde le repère de lecture de la 1.32.0 :
 *     l'icône est visible, la vignette est sur `--surface` (pas de gris), et
 *     aucune balise d'image n'est là ;
 *   - ni style ni script en ligne, aucune ressource extérieure ;
 *   - toutes les images de la page sont réellement chargées ;
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
page.on('console', (m) => { if (m.type() === 'error') erreursJs.push(m.text()); });

await page.goto(base + '/', { waitUntil: 'networkidle0' });
await page.evaluate(() => document.fonts.ready);
await new Promise((r) => setTimeout(r, 200));

// --- 1. Palette et largeur ------------------------------------------------
const fond = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
verifier(fond === fondAttendu,
    'palette ' + theme + ' : le fond du document est ' + fond + ' (attendu ' + fondAttendu + ')');
verifier(
    await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1),
    'accueil : aucun débordement horizontal à ' + largeur + ' px'
);

// --- 2. La une sans image occupe toute la largeur -------------------------
const une = await page.evaluate(() => {
    const article = document.querySelector('.une-grande__article');
    if (!article) { return null; }
    const texte = article.querySelector('.une-grande__texte');
    const r = article.getBoundingClientRect();
    const t = texte ? texte.getBoundingClientRect() : null;
    return {
        sansImage: article.classList.contains('une-grande__article--sans-image'),
        largeurArticle: Math.round(r.width),
        largeurTexte: t ? Math.round(t.width) : 0,
        images: article.querySelectorAll('img').length,
        zones: article.querySelectorAll('.une-grande__image').length,
        colonnes: getComputedStyle(article).gridTemplateColumns,
        titre: (article.querySelector('.une-grande__titre') || {}).textContent || '',
        titreVisible: (() => {
            const el = article.querySelector('.une-grande__titre');
            if (!el) { return false; }
            const b = el.getBoundingClientRect();
            const s = getComputedStyle(el);
            return b.width > 100 && b.height > 20 && s.visibility !== 'hidden' && s.display !== 'none';
        })(),
    };
});
verifier(une !== null, 'accueil : la une publique est présente');
if (une) {
    verifier(une.sansImage, 'une : la une sans image porte « une-grande__article--sans-image »');
    verifier(une.images === 0 && une.zones === 0,
        'une : aucune image ni zone d’image dans la une (' + une.images + ' img, ' + une.zones + ' zone)');
    verifier(une.largeurTexte >= une.largeurArticle - 2,
        'une : le texte occupe toute la largeur (' + une.largeurTexte + ' px sur ' + une.largeurArticle + ' px)');
    verifier(!une.colonnes.includes(' '),
        'une : une seule colonne (' + une.colonnes + ') — aucune zone vide à droite');
    verifier(une.titreVisible && une.titre.trim() !== '', 'une : le titre est visible (' + une.titre.trim() + ')');
}

// --- 3. Les cartes texte de la grille : texte seul, aucune tuile ----------
const grille = await page.evaluate(() => {
    const echelle = document.createElement('div');
    echelle.style.color = 'var(--papier-moyen)';
    document.body.appendChild(echelle);
    const gris = getComputedStyle(echelle).color;
    echelle.remove();

    return Array.from(document.querySelectorAll('.une-carte')).map((carte) => {
        const descendants = Array.from(carte.querySelectorAll('*'));
        const tuiles = descendants.filter((el) => {
            const s = getComputedStyle(el);
            if (s.backgroundColor === gris) { return true; }
            // Une image de fond posée en `url(...)` serait aussi une tuile.
            return s.backgroundImage !== 'none' && s.backgroundImage.startsWith('url(');
        }).map((el) => el.className || el.tagName);
        const r = carte.getBoundingClientRect();
        const titre = carte.querySelector('.une-carte__titre');
        const meta = carte.querySelector('.une-carte__meta');
        return {
            classes: Array.from(carte.classList),
            images: carte.querySelectorAll('img').length,
            vignettes: carte.querySelectorAll('[class*="vignette"]').length,
            types: carte.querySelectorAll('.une-carte__type').length,
            vides: carte.querySelectorAll('.une-carte__vide').length,
            tuiles: tuiles,
            largeur: Math.round(r.width),
            hauteur: Math.round(r.height),
            titreVisible: (() => {
                if (!titre) { return false; }
                const b = titre.getBoundingClientRect();
                return b.width > 40 && b.height > 10 && titre.textContent.trim() !== '';
            })(),
            titreLargeur: titre ? Math.round(titre.getBoundingClientRect().width) : 0,
            meta: meta ? meta.textContent.trim() : '',
            resume: (carte.querySelector('.une-carte__resume') || { textContent: '' }).textContent.trim(),
            lecture: (() => {
                const icone = carte.querySelector('.une-carte__lecture');
                if (!icone) { return null; }
                const b = icone.getBoundingClientRect();
                const s = getComputedStyle(icone);
                return { largeur: Math.round(b.width), hauteur: Math.round(b.height), couleur: s.color, visible: s.visibility !== 'hidden' && b.width > 4 };
            })(),
            fondVignette: (() => {
                const v = carte.querySelector('.une-carte__vignette');
                return v ? getComputedStyle(v).backgroundColor : null;
            })(),
        };
    });
});

const cartesTexte = grille.filter((c) => c.classes.includes('une-carte--texte'));
const cartesImage = grille.filter((c) => !c.classes.includes('une-carte--texte') && !c.classes.includes('une-carte--video'));
const cartesVideo = grille.filter((c) => c.classes.includes('une-carte--video'));

verifier(cartesTexte.length >= 2, 'grille : ' + cartesTexte.length + ' carte(s) texte seule(s) (au moins 2 attendues)');
verifier(cartesImage.length >= 1, 'grille : ' + cartesImage.length + ' carte(s) avec image, inchangée(s)');

verifier(cartesTexte.every((c) => c.images === 0),
    'grille texte : aucune balise d’image dans les cartes sans image');
verifier(cartesTexte.every((c) => c.vignettes === 0),
    'grille texte : aucune vignette (pas de zone d’image réservée)');
verifier(cartesTexte.every((c) => c.types === 0),
    'grille texte : aucun bloc de rubrique sous la carte');
verifier(cartesTexte.every((c) => c.vides === 0),
    'grille texte : aucune tuile « une-carte__vide »');
verifier(cartesTexte.every((c) => c.tuiles.length === 0),
    'grille texte : aucune tuile grise (' + JSON.stringify(cartesTexte.map((c) => c.tuiles)) + ')');
verifier(cartesTexte.every((c) => c.titreVisible && c.titreLargeur >= c.largeur * 0.8),
    'grille texte : le titre est visible et prend la largeur de la carte (' +
        cartesTexte.map((c) => c.titreLargeur + '/' + c.largeur).join(' ') + ')');
verifier(cartesTexte.every((c) => c.meta !== ''),
    'grille texte : chaque carte porte sa date (' + cartesTexte.map((c) => c.meta).join(' | ') + ')');
verifier(cartesTexte.every((c) => c.resume !== ''),
    'grille texte : chaque carte porte son extrait');
verifier(cartesTexte.every((c) => c.hauteur >= 60 && c.hauteur <= 400),
    'grille texte : la carte garde une hauteur de texte, sans boîte vide (' +
        cartesTexte.map((c) => c.hauteur + ' px').join(', ') + ')');

// --- 4. Une vidéo sans miniature garde le repère de lecture ---------------
if (cartesVideo.length > 0) {
    const v = cartesVideo[0];
    verifier(v.images === 0, 'grille vidéo : aucune balise d’image (aucune image cassée)');
    verifier(v.lecture !== null && v.lecture.visible && v.lecture.hauteur >= 20,
        'grille vidéo : l’icône de lecture est visible (' + JSON.stringify(v.lecture) + ')');
    verifier(v.fondVignette !== null && v.fondVignette !== 'rgb(128, 128, 128)',
        'grille vidéo : la vignette est sur la couleur du site, pas un carré gris (' + v.fondVignette + ')');
    verifier(v.tuiles.length === 0, 'grille vidéo : aucune tuile grise');
} else {
    souci('grille vidéo : aucune carte vidéo dans la page rendue (banc incomplet)');
}

// --- 5. Rien d'en ligne, rien d'extérieur, images chargées ----------------
const enLigne = await page.evaluate(() => ({
    styles: document.querySelectorAll('[style]').length,
    scripts: Array.from(document.querySelectorAll('script')).filter((s) => !s.src).length,
    etrangers: Array.from(document.querySelectorAll('[src], link[href]'))
        .map((el) => el.getAttribute('src') || el.getAttribute('href') || '')
        .filter((u) => /^(https?:)?\/\//i.test(u) && !u.startsWith(location.origin)).length,
    images: Array.from(document.images).map((i) => i.complete && i.naturalWidth > 0),
}));
verifier(enLigne.styles === 0, 'accueil : aucun attribut style= (' + enLigne.styles + ')');
verifier(enLigne.scripts === 0, 'accueil : aucun script en ligne (' + enLigne.scripts + ')');
verifier(enLigne.etrangers === 0, 'accueil : aucune ressource extérieure (' + enLigne.etrangers + ')');
verifier(enLigne.images.every(Boolean),
    'accueil : les ' + enLigne.images.length + ' image(s) sont réellement chargées');
verifier(erreursJs.length === 0, 'accueil : aucune erreur JavaScript (' + erreursJs.join(' / ') + ')');

// --- 6. Captures ----------------------------------------------------------
const nom = (theme === 'bleu' ? '-bleu' : '') + '.png';
const prefixe = largeur === 1280 ? 'ordinateur-' : 'mobile-';
await page.screenshot({ path: sortie + '/' + prefixe + 'accueil-sans-image' + nom, fullPage: true });
console.log('  capture ' + prefixe + 'accueil-sans-image' + nom);

await navigateur.close();
console.log('\n' + (ok + ko) + ' contrôle(s) — ' + ok + ' ok, ' + ko + ' échec(s).');
process.exit(ko === 0 ? 0 : 1);
