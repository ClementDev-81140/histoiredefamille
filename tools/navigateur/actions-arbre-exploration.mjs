/**
 * Contrôle « vrai navigateur » de la 1.35.0 : exploration de l'arbre
 * (zoom, déplacement, recentrage) et parenté lisible.
 *
 *   CAPTURES=/tmp node tools/navigateur/actions-arbre-exploration.mjs 1280
 *   CAPTURES=/tmp node tools/navigateur/actions-arbre-exploration.mjs 390
 *
 * Le serveur de tools/navigateur/serveur.mjs doit tourner avec les pages
 * rendues par rendre.php (arbre complet, huit enfants, un enfant) :
 *
 *   PAGES_ALBUM='{"/arbre-complet":"/tmp/arbre-complet.html",
 *                 "/arbre-huit":"/tmp/arbre-huit.html",
 *                 "/arbre-un":"/tmp/arbre-un.html"}'
 *   THEME_CSS=/tmp/theme-papier.css  (ou theme-bleu.css)
 *
 * Ce que l'on vérifie, et qu'aucun essai PHP ne peut voir :
 *   - la barre d'outils visible (JavaScript chargé), ses boutons, le niveau
 *     en % ; sans JavaScript, elle est cachée et l'arbre est complet ;
 *   - les propriétés CSS personnalisées posées par l'API CSSOM passent la
 *     CSP : le transform calculé du dessin change vraiment ;
 *   - zoomer, dézoomer, flèches, centrer, « voir tout », molette, glisser ;
 *   - le pincement à deux doigts, **synthétisé** par le protocole CDP
 *     (touchStart/touchMove à deux points) : il faudra le confirmer sur un
 *     vrai téléphone ;
 *   - au chargement, la personne sélectionnée est au centre du cadre et, à
 *     390 px, la génération des parents est au moins en partie visible ;
 *   - pas de scroll horizontal de la page, pas de piège au clavier (Tab
 *     sort du cadre), focus visible, prefers-reduced-motion respecté ;
 *   - relations écrites sur les cartes, trait du couple, traits SVG,
 *     aucune carte superposée ; aucune erreur JavaScript.
 *
 * Variables : THEME (papier|bleu), THEME_FOND (rgb attendu), CAPTURES, PORT.
 */
import puppeteer from 'puppeteer-core';
import chromium from '@sparticuz/chromium';

const largeur = Number(process.argv[2] || 1280);
const hauteur = Number(process.env.HAUTEUR || 800);
const sortie = process.env.CAPTURES || '/tmp';
const port = Number(process.env.PORT || 8199);
const theme = process.env.THEME || 'papier';
const fondAttendu = process.env.THEME_FOND || (theme === 'bleu' ? 'rgb(246, 245, 242)' : 'rgb(243, 231, 206)');
const base = 'http://127.0.0.1:' + port;

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
    defaultViewport: { width: largeur, height: hauteur },
});

const page = await navigateur.newPage();
await page.setCacheEnabled(false);
const erreurs = [];
page.on('pageerror', (e) => erreurs.push(String(e)));
page.on('console', (m) => { if (m.type() === 'error') erreurs.push(m.text()); });

const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const aller = async (adresse) => {
    await page.goto(base + adresse, { waitUntil: 'load' });
    await page.evaluate(() => document.fonts.ready);
    await attendre(350);
};
const etat = () => page.evaluate(() => {
    const dessin = document.querySelector('[data-explo-dessin]');
    const cadre = document.querySelector('[data-explo-cadre]');
    const centre = document.querySelector('[data-explo-centre]');
    const rect = (el) => { const b = el.getBoundingClientRect(); return { x: b.x, y: b.y, w: b.width, h: b.height, cx: b.x + b.width / 2, cy: b.y + b.height / 2 }; };
    return {
        niveau: document.querySelector('[data-explo-niveau]')?.textContent.trim(),
        zoomVar: dessin?.style.getPropertyValue('--explo-zoom'),
        xVar: dessin?.style.getPropertyValue('--explo-x'),
        yVar: dessin?.style.getPropertyValue('--explo-y'),
        transform: dessin ? getComputedStyle(dessin).transform : null,
        cadre: cadre ? rect(cadre) : null,
        centre: centre ? rect(centre) : null,
        barreVisible: (() => { const b = document.querySelector('[data-explo-barre]'); return b ? getComputedStyle(b).display !== 'none' : false; })(),
        actif: document.querySelector('[data-arbre-exploration]')?.classList.contains('arbre-explo--actif'),
    };
});
const cliquer = async (action) => {
    await page.click('[data-explo="' + action + '"]');
    await attendre(250);
};

await aller('/arbre-complet');

// --- La barre et l'état initial -------------------------------------------
let e = await etat();
verifier('barre d’outils visible une fois le script chargé', e.barreVisible && e.actif);
verifier('le niveau de zoom est affiché en pourcentage', /^\d+ %$/.test(e.niveau || ''), e.niveau);
verifier('CSP : la propriété CSSOM --explo-zoom est posée et le transform suit',
    !!e.zoomVar && e.transform !== 'none' && e.transform !== null, 'zoom=' + e.zoomVar + ' transform=' + e.transform);
const styles = await page.evaluate(() => Array.from(document.querySelectorAll('[style]')).map((el) => el.getAttribute('data-explo-dessin') !== null));
verifier('un seul élément porte un style (le dessin, posé par le script)', styles.length === 1 && styles[0] === true,
    styles.length + ' élément(s)');
verifier('la personne sélectionnée est centrée dans le cadre au chargement',
    Math.abs(e.centre.cx - e.cadre.cx) < 45 && Math.abs(e.centre.cy - e.cadre.cy) < 45,
    'écarts ' + Math.round(e.centre.cx - e.cadre.cx) + '/' + Math.round(e.centre.cy - e.cadre.cy) + ' px');
const parentsVisibles = await page.evaluate(() => {
    const cadre = document.querySelector('[data-explo-cadre]').getBoundingClientRect();
    return Array.from(document.querySelectorAll('[data-relation="parent"]')).some((el) => {
        const b = el.getBoundingClientRect();
        return b.bottom > cadre.top && b.top < cadre.bottom && b.right > cadre.left && b.left < cadre.right;
    });
});
verifier('la génération des parents est au moins en partie visible au départ', parentsVisibles);
verifier('aucun défilement horizontal de la page', await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1));

// --- Relations et traits ----------------------------------------------------
const relations = await page.evaluate(() => ({
    textes: Array.from(document.querySelectorAll('.arbre-chene__relation')).map((el) => el.textContent.trim()),
    couple: document.querySelectorAll('.arbre-chene__noeud--couple').length,
    lignes: document.querySelectorAll('.arbre-chene__traits line').length,
    ariaConjoint: document.querySelector('[aria-label$=", conjointe"], [aria-label$=", conjoint"]')?.getAttribute('aria-label') || '',
}));
verifier('les relations sont écrites sur les cartes (conjointe, parent, grand-parent…)',
    relations.textes.includes('Conjointe') && relations.textes.includes('Parent')
    && relations.textes.includes('Grand-parent') && relations.textes.includes('Petit-enfant'),
    relations.textes.join(', '));
verifier('le nom accessible de la conjointe dit « conjointe »', /, conjointe$/.test(relations.ariaConjoint), relations.ariaConjoint);
verifier('le trait du couple et les traits parents-enfants sont présents',
    relations.couple >= 1 && relations.lignes >= 4, relations.couple + ' couple(s), ' + relations.lignes + ' segment(s)');

// --- Zoom boutons ------------------------------------------------------------
const n0 = parseInt(e.niveau, 10);
await cliquer('zoom-plus');
e = await etat();
verifier('Zoomer augmente le niveau', parseInt(e.niveau, 10) > n0, n0 + ' → ' + e.niveau);
await cliquer('zoom-moins');
await cliquer('zoom-moins');
e = await etat();
verifier('Dézoomer diminue le niveau', parseInt(e.niveau, 10) < n0, '→ ' + e.niveau);

// --- Flèches ------------------------------------------------------------------
await cliquer('centrer');
const x0 = parseFloat((await etat()).xVar);
await cliquer('droite');
const x1 = parseFloat((await etat()).xVar);
await cliquer('gauche');
const x2 = parseFloat((await etat()).xVar);
verifier('les flèches déplacent l’arbre dans les deux sens', x1 < x0 && x2 > x1, x0 + ' → ' + x1 + ' → ' + x2);

// --- Centrer -------------------------------------------------------------------
await cliquer('droite'); await cliquer('bas');
await cliquer('centrer');
e = await etat();
verifier('« Centrer » ramène la personne sélectionnée au centre, zoom conservé',
    Math.abs(e.centre.cx - e.cadre.cx) < 45 && Math.abs(e.centre.cy - e.cadre.cy) < 45,
    'écarts ' + Math.round(e.centre.cx - e.cadre.cx) + '/' + Math.round(e.centre.cy - e.cadre.cy) + ' px, niveau ' + e.niveau);

// --- Voir tout -------------------------------------------------------------------
await cliquer('tout');
const tout = await page.evaluate(() => {
    const cadre = document.querySelector('[data-explo-cadre]').getBoundingClientRect();
    const cartes = Array.from(document.querySelectorAll('.arbre-chene__noeud'));
    const dedans = cartes.every((el) => {
        const b = el.getBoundingClientRect();
        return b.left >= cadre.left - 2 && b.right <= cadre.right + 2 && b.top >= cadre.top - 2 && b.bottom <= cadre.bottom + 2;
    });
    return { dedans, nombre: cartes.length };
});
e = await etat();
verifier('« Voir tout » fait tenir les ' + tout.nombre + ' cartes dans le cadre', tout.dedans, 'niveau ' + e.niveau);

// --- Molette -----------------------------------------------------------------------
// Comme un utilisateur : on amène d'abord le cadre dans l'écran.
await page.evaluate(() => document.querySelector('[data-explo-cadre]').scrollIntoView({ block: 'center' }));
await attendre(200);
await cliquer('centrer');
const avantMolette = parseInt((await etat()).niveau, 10);
const boiteCadre = await page.evaluate(() => {
    const b = document.querySelector('[data-explo-cadre]').getBoundingClientRect();
    return { x: b.x + b.width / 2, y: b.y + b.height / 2 };
});
await page.mouse.move(boiteCadre.x, boiteCadre.y);
await page.mouse.wheel({ deltaY: -600 });
await attendre(250);
const apresMolette = parseInt((await etat()).niveau, 10);
verifier('la molette zoome', apresMolette > avantMolette, avantMolette + ' → ' + apresMolette);

// --- Glisser -------------------------------------------------------------------------
const avantGlisser = await etat();
await page.mouse.move(boiteCadre.x, boiteCadre.y);
await page.mouse.down();
await page.mouse.move(boiteCadre.x + 70, boiteCadre.y + 30, { steps: 8 });
await page.mouse.up();
await attendre(250);
const apresGlisser = await etat();
verifier('le glisser déplace l’arbre',
    parseFloat(apresGlisser.xVar) !== parseFloat(avantGlisser.xVar)
    || parseFloat(apresGlisser.yVar) !== parseFloat(avantGlisser.yVar),
    avantGlisser.xVar + '/' + avantGlisser.yVar + ' → ' + apresGlisser.xVar + '/' + apresGlisser.yVar);

// --- Pincement (synthétisé par CDP, à confirmer sur un vrai téléphone) ----------------
await cliquer('centrer');
const avantPince = parseInt((await etat()).niveau, 10);
try {
    const session = await page.createCDPSession();
    const cx = boiteCadre.x;
    const cy = boiteCadre.y;
    await session.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [
        { x: cx - 40, y: cy, id: 1 }, { x: cx + 40, y: cy, id: 2 }] });
    await session.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [
        { x: cx - 90, y: cy, id: 1 }, { x: cx + 90, y: cy, id: 2 }] });
    await session.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
    await attendre(250);
    const apresPince = parseInt((await etat()).niveau, 10);
    verifier('le pincement à deux doigts zoome (geste synthétisé par CDP)', apresPince > avantPince,
        avantPince + ' → ' + apresPince);
} catch (erreur) {
    verifier('le pincement à deux doigts zoome (geste synthétisé par CDP)', false, String(erreur).slice(0, 120));
}

// --- Clavier : pas de piège, focus visible ----------------------------------------------
await page.evaluate(() => document.querySelector('[data-explo-cadre]').focus());
await page.keyboard.press('Tab');
const sorti = await page.evaluate(() => document.activeElement !== document.querySelector('[data-explo-cadre]'));
verifier('Tab sort du cadre (pas de piège au clavier)', sorti);
const focusBouton = await page.evaluate(() => {
    const b = document.querySelector('[data-explo="zoom-plus"]');
    b.focus();
    const s = getComputedStyle(b);
    return { actif: document.activeElement === b, contour: s.outlineStyle !== 'none' && parseFloat(s.outlineWidth) > 0 };
});
verifier('les boutons reçoivent le focus avec un contour visible', focusBouton.actif && focusBouton.contour);

// --- Sans JavaScript -----------------------------------------------------------------------
const pageSansJs = await navigateur.newPage();
await pageSansJs.setJavaScriptEnabled(false);
await pageSansJs.setViewport({ width: largeur, height: hauteur });
await pageSansJs.goto(base + '/arbre-complet', { waitUntil: 'load' });
const sansJs = await pageSansJs.evaluate(() => ({
    barreVisible: (() => { const b = document.querySelector('[data-explo-barre]'); return b ? getComputedStyle(b).display !== 'none' : true; })(),
    cartes: document.querySelectorAll('.arbre-chene__noeud').length,
    actif: document.querySelector('[data-arbre-exploration]')?.classList.contains('arbre-explo--actif'),
}));
verifier('sans JavaScript : barre cachée, arbre complet (' + sansJs.cartes + ' cartes)',
    !sansJs.barreVisible && !sansJs.actif && sansJs.cartes >= 10);
await pageSansJs.close();

// --- Mouvement réduit ------------------------------------------------------------------------
await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
await aller('/arbre-complet');
const reduit = await page.evaluate(() => ({
    anime: document.querySelector('[data-arbre-exploration]')?.classList.contains('arbre-explo--anime'),
    transition: getComputedStyle(document.querySelector('[data-explo-dessin]')).transitionDuration,
}));
verifier('prefers-reduced-motion : aucune animation de transform', !reduit.anime || reduit.transition === '0s',
    'transition ' + reduit.transition);
await page.emulateMediaFeatures([]);

// --- Superpositions et hauteur du cadre ---------------------------------------------------------
await aller('/arbre-huit');
const huit = await page.evaluate(() => {
    const cartes = Array.from(document.querySelectorAll('.arbre-chene__noeud')).map((el) => {
        const b = el.getBoundingClientRect(); return { x: b.x, y: b.y, w: b.width, h: b.height, t: el.textContent.trim().slice(0, 18) };
    });
    let chevauchements = 0;
    for (let i = 0; i < cartes.length; i++) for (let j = i + 1; j < cartes.length; j++) {
        const a = cartes[i]; const b = cartes[j];
        if (a.x < b.x + b.w - 0.5 && b.x < a.x + a.w - 0.5 && a.y < b.y + b.h - 0.5 && b.y < a.y + a.h - 0.5) chevauchements++;
    }
    const cadre = document.querySelector('[data-explo-cadre]');
    return { nombre: cartes.length, chevauchements, hauteurCadre: cadre.clientHeight };
});
verifier('huit enfants : ' + huit.nombre + ' cartes sans superposition', huit.chevauchements === 0 && huit.nombre >= 8);
verifier('le cadre fait entre 320 et 640 px de haut', huit.hauteurCadre >= 320 && huit.hauteurCadre <= 640,
    huit.hauteurCadre + ' px');

// --- Propreté --------------------------------------------------------------------------------------
const propre = await page.evaluate(() => ({
    scriptsEnLigne: Array.from(document.querySelectorAll('script:not([src])')).length,
    etrangers: Array.from(document.querySelectorAll('[src], link[href]'))
        .map((el) => el.getAttribute('src') || el.getAttribute('href'))
        .filter((a) => a && /^(https?:)?\/\//.test(a) && !a.startsWith(location.origin)).length,
    fond: getComputedStyle(document.body).backgroundColor,
    debordement: document.documentElement.scrollWidth - window.innerWidth,
}));
verifier('palette ' + theme + ' appliquée', propre.fond === fondAttendu, propre.fond);
verifier('aucun script en ligne, aucune ressource extérieure, aucun scroll horizontal',
    propre.scriptsEnLigne === 0 && propre.etrangers === 0 && propre.debordement <= 1, JSON.stringify(propre));
verifier('aucune erreur JavaScript', erreurs.length === 0, erreurs.join(' / '));

await page.evaluate(() => document.activeElement?.blur());
await aller('/arbre-complet');
await page.screenshot({ path: sortie + '/arbre-exploration-' + theme + '-' + largeur + '.png' });
console.log('  capture arbre-exploration-' + theme + '-' + largeur + '.png');

await navigateur.close();
console.log('\n' + (ok + ko) + ' contrôle(s) — ' + ok + ' ok, ' + ko + ' échec(s).');
process.exit(ko === 0 ? 0 : 1);
