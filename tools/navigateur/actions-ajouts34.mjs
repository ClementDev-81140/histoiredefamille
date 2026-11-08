/**
 * Contrôle « vrai navigateur » : les ajouts de la 1.34.0.
 *
 *   CAPTURES=/tmp node tools/navigateur/actions-ajouts34.mjs 390
 *   CAPTURES=/tmp node tools/navigateur/actions-ajouts34.mjs 1280
 *
 * Le serveur de tools/navigateur/serveur.mjs doit tourner avec les pages
 * rendues par rendre.php :
 *
 *   PAGES_ALBUM='{"/article-actu":"/tmp/article-actu.html",
 *                 "/article-souvenir":"/tmp/article-souvenir.html",
 *                 "/calendrier":"/tmp/calendrier-admin.html",
 *                 "/fiche":"/tmp/fiche-sans-deces.html"}'
 *   THEME_CSS=/tmp/theme-papier.css        (ou /tmp/theme-bleu.css)
 *
 * Ce que l'on vérifie, et qu'aucun essai PHP ne peut voir :
 *   - la page d'un article d'actualité ne montre aucune étiquette de
 *     décennie au-dessus du titre (celle d'un souvenir dit « Souvenir
 *     des années … ») ;
 *   - le calendrier d'un administrateur montre le bouton « Ajouter un
 *     événement », parle d'anniversaires (jamais de naissances) et ne
 *     montre aucun décès sans le réglage ;
 *   - la fiche d'une personne sans date de décès n'a aucune ligne
 *     « Décès » ;
 *   - aucun style ni script en ligne, aucune ressource extérieure,
 *     aucun débordement horizontal, aucune erreur JavaScript.
 *
 * Variables : THEME (papier|bleu), THEME_FOND (rgb attendu), CAPTURES, PORT.
 */
import puppeteer from 'puppeteer-core';
import chromium from '@sparticuz/chromium';

const largeur     = Number(process.argv[2] || 1280);
const hauteur     = Number(process.env.HAUTEUR || 800);
const sortie      = process.env.CAPTURES || '/tmp';
const port        = Number(process.env.PORT || 8199);
const theme       = process.env.THEME || 'papier';
const fondAttendu = process.env.THEME_FOND || 'rgb(243, 231, 206)';
const base        = 'http://127.0.0.1:' + port;

const resultats = [];
const verifier = (nom, condition, detail = '') => {
    resultats.push({ nom, ok: !!condition });
    console.log((condition ? '  ok      ' : '  ÉCHEC   ') + nom + (detail ? ' — ' + detail : ''));
};

const navigateur = await puppeteer.launch({
    executablePath: await chromium.executablePath(),
    args: [...chromium.args, '--no-sandbox', '--disable-dev-shm-usage'],
    headless: true,
    defaultViewport: { width: largeur, height: hauteur },
});

try {
    const page = await navigateur.newPage();
    await page.setCacheEnabled(false);
    const erreurs = [];
    page.on('pageerror', (erreur) => erreurs.push(String(erreur)));

    const visiter = async (adresse) => {
        await page.goto(base + adresse, { waitUntil: 'load' });
        await page.evaluate(() => document.fonts.ready);
        await page.evaluate(() => document.activeElement?.blur());
        await new Promise((r) => setTimeout(r, 200));
    };

    const propre = () => page.evaluate(() => ({
        stylesEnLigne: document.querySelectorAll('[style]').length,
        scriptsEnLigne: Array.from(document.querySelectorAll('script:not([src])')).length,
        etrangers: Array.from(document.querySelectorAll('[src], link[href]'))
            .map((e) => e.getAttribute('src') || e.getAttribute('href'))
            .filter((a) => a && /^(https?:)?\/\//.test(a)).length,
        fond: getComputedStyle(document.body).backgroundColor,
        debordement: document.documentElement.scrollWidth - window.innerWidth,
    }));

    const verifierPropre = async (nom) => {
        const p = await propre();
        verifier(nom + ' : aucun style ni script en ligne, aucune ressource extérieure',
            p.stylesEnLigne === 0 && p.scriptsEnLigne === 0 && p.etrangers === 0, JSON.stringify(p));
        verifier(nom + ' : aucun débordement horizontal', p.debordement <= 1, p.debordement + ' px');
        if (process.env.THEME_FOND) {
            verifier(nom + ' : le fond est celui du thème', p.fond === fondAttendu, p.fond);
        }
    };

    // --- 1. Article d'actualité : aucune étiquette de décennie --------------
    await visiter('/article-actu');
    const actu = await page.evaluate(() => {
        const surtitre = document.querySelector('.article__surtitre');
        const titre = document.querySelector('.article__titre');
        return {
            surtitre: surtitre ? (surtitre.textContent || '').trim() : '',
            titreHaut: titre ? Math.round(titre.getBoundingClientRect().top) : -1,
        };
    });
    verifier('article d’actualité : aucune étiquette de décennie au-dessus du titre',
        !/années/i.test(actu.surtitre) && actu.titreHaut >= 0,
        'surtitre : « ' + actu.surtitre + ' »');
    await verifierPropre('article d’actualité');
    await page.screenshot({ path: sortie + '/article-sans-etiquette-' + theme + '-' + largeur + '.png', fullPage: true });

    // --- 1b. Article de souvenir : « Souvenir des années … » ------------------
    await visiter('/article-souvenir');
    const souvenir = await page.evaluate(() => {
        const surtitre = document.querySelector('.article__surtitre');
        return surtitre ? (surtitre.textContent || '').trim() : '';
    });
    verifier('article de souvenir : l’étiquette dit « Souvenir des années 1920 »',
        souvenir.includes('Souvenir des années 1920'), 'surtitre : « ' + souvenir + ' »');

    // --- 2. Calendrier administrateur : bouton, anniversaires, pas de décès --
    await visiter('/calendrier');
    const calendrier = await page.evaluate(() => {
        const texte = document.body.innerText;
        const bouton = Array.from(document.querySelectorAll('a.bouton'))
            .find((a) => (a.textContent || '').trim() === 'Ajouter un événement');
        const boite = bouton ? bouton.getBoundingClientRect() : null;
        return {
            bouton: !!bouton,
            boutonVisible: boite ? (boite.width > 0 && boite.height > 0) : false,
            anniversaire: texte.includes('Anniversaire de'),
            mariage: texte.includes('Anniversaire de mariage de'),
            naissance: texte.includes('Naissance'),
            deces: texte.includes('Décès de') || texte.includes('In memoriam'),
        };
    });
    verifier('calendrier : le bouton « Ajouter un événement » est là et visible',
        calendrier.bouton && calendrier.boutonVisible);
    verifier('calendrier : des anniversaires, jamais de naissances',
        calendrier.anniversaire && !calendrier.naissance,
        'anniversaire ' + calendrier.anniversaire + ', naissance ' + calendrier.naissance);
    verifier('calendrier : anniversaires de mariage présents', calendrier.mariage);
    verifier('calendrier : aucun décès sans le réglage', !calendrier.deces);
    await verifierPropre('calendrier');
    await page.screenshot({ path: sortie + '/calendrier-bouton-' + theme + '-' + largeur + '.png', fullPage: true });

    // --- 3. Fiche sans date de décès : aucune ligne « Décès » ----------------
    await visiter('/fiche');
    const fiche = await page.evaluate(() => {
        const termes = Array.from(document.querySelectorAll('.fiche dt')).map((dt) => (dt.textContent || '').trim());
        return { termes, texte: document.body.innerText.includes('Décès :') };
    });
    verifier('fiche : aucune ligne « Décès » sans date de décès',
        !fiche.termes.includes('Décès') && !fiche.texte, 'repères : ' + fiche.termes.join(' / '));
    await verifierPropre('fiche');
    await page.screenshot({ path: sortie + '/fiche-sans-deces-' + theme + '-' + largeur + '.png', fullPage: true });

    verifier('aucune erreur JavaScript sur les pages visitées', erreurs.length === 0, erreurs.join(' | '));
} finally {
    await navigateur.close();
}

const echecs = resultats.filter((r) => !r.ok).length;
console.log('\n' + resultats.length + ' contrôle(s) — ' + (resultats.length - echecs) + ' ok, ' + echecs + ' échec(s).');
if (echecs > 0) {
    process.exitCode = 1;
}
