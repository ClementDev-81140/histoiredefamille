/**
 * Contrôle « vrai navigateur » : adresses affichées et écrans masqués (1.28.0).
 *
 *   CAPTURES=/tmp node tools/navigateur/actions-contacts.mjs 390
 *   CAPTURES=/tmp node tools/navigateur/actions-contacts.mjs 1280
 *
 * Le serveur de tools/navigateur/serveur.mjs doit tourner avec les pages
 * rendues par rendre.php :
 *
 *   PAGES_ALBUM='{"/":"/tmp/accueil.html","/connexion":"/tmp/connexion.html",
 *                 "/mon-compte":"/tmp/moncompte.html",
 *                 "/page-qui-n-existe-vraiment-pas":"/tmp/erreur404.html"}'
 *   THEME_CSS=/tmp/theme-papier.css        (ou /tmp/theme-bleu.css)
 *
 * On vérifie ici ce qu'aucun essai PHP ne peut voir : que les adresses sont
 * réellement **lisibles et cliquables** à l'écran (lien `mailto:` ordinaire,
 * sans onclick, sans style en ligne), qu'elles tiennent dans la largeur à
 * 390 px comme à 1280 px, que le contraste est suffisant dans les deux
 * palettes (papier, bleu), et que l'écran « Mon compte » n'offre plus ni champ
 * d'adresse ni case de consentement.
 *
 * Variables d'environnement (toutes facultatives) :
 *   THEME        nom du thème, pour les captures (« papier », « bleu »)
 *   THEME_FOND   couleur de fond attendue, en rgb() (« rgb(243, 231, 206) »)
 *   CAPTURES     dossier des captures
 *   PORT         port du serveur d'essai (8199)
 */
import puppeteer from 'puppeteer-core';
import chromium from '@sparticuz/chromium';

const largeur      = Number(process.argv[2] || 1280);
const sortie       = process.env.CAPTURES || '/tmp';
const port         = Number(process.env.PORT || 8199);
const theme        = process.env.THEME || 'papier';
const fondAttendu  = process.env.THEME_FOND || 'rgb(243, 231, 206)';
const base         = 'http://127.0.0.1:' + port;

const resultats = [];
const verifier = (nom, condition, detail = '') => {
    resultats.push({ nom, ok: !!condition });
    console.log((condition ? '  ok      ' : '  ÉCHEC   ') + nom + (detail ? ' — ' + detail : ''));
};

/** Luminance relative d'une couleur rgb() ou rgba(). */
function luminance(couleur) {
    const nombres = String(couleur).match(/[\d.]+/g) || [];
    const composantes = nombres.slice(0, 3).map((valeur) => {
        const c = Number(valeur) / 255;
        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });
    if (composantes.length !== 3) {
        return null;
    }
    return 0.2126 * composantes[0] + 0.7152 * composantes[1] + 0.0722 * composantes[2];
}

/** Contraste entre deux couleurs (1 à 21). */
function contraste(a, b) {
    const la = luminance(a);
    const lb = luminance(b);
    if (la === null || lb === null) {
        return 0;
    }
    const clair = Math.max(la, lb);
    const sombre = Math.min(la, lb);
    return (clair + 0.05) / (sombre + 0.05);
}

const navigateur = await puppeteer.launch({
    executablePath: await chromium.executablePath(),
    args: [...chromium.args, '--no-sandbox', '--disable-dev-shm-usage'],
    headless: true,
    defaultViewport: { width: largeur, height: 900 },
});

try {
    const page = await navigateur.newPage();
    await page.setCacheEnabled(false);
    const erreurs = [];
    page.on('pageerror', (erreur) => erreurs.push(String(erreur)));

    const visiter = async (adresse) => {
        await page.goto(base + adresse, { waitUntil: 'load' });
        await page.evaluate(() => document.fonts.ready);
    };

    /** Lien mailto d'une page, avec ses défauts éventuels. */
    const liensMailto = () => page.evaluate(() => Array.from(document.querySelectorAll('a[href^="mailto:"]'))
        .map((lien) => ({
            adresse: lien.getAttribute('href'),
            texte: (lien.textContent || '').trim(),
            style: lien.getAttribute('style'),
            onclick: lien.getAttribute('onclick'),
            visible: lien.getBoundingClientRect().width > 0 && lien.getBoundingClientRect().height > 0,
        })));

    // =======================================================================
    // Accueil public
    // =======================================================================
    await visiter('/');
    const liensAccueil = await liensMailto();
    const ecrire = liensAccueil.find((lien) => lien.adresse.startsWith('mailto:contact@'));
    const blocEcrire = await page.evaluate(() => {
        const bloc = document.querySelector('.hero__ecrire');
        return bloc ? (bloc.textContent || '').replace(/\s+/g, ' ').trim() : '';
    });
    verifier(
        'accueil : « Nous écrire » est un lien mailto vers l’adresse de contact, visible',
        ecrire !== undefined && ecrire.visible && ecrire.texte.includes('contact@')
            && blocEcrire.includes('Nous écrire'),
        ecrire ? ecrire.adresse + ' / « ' + blocEcrire + ' »' : 'aucun lien de contact'
    );
    verifier(
        'accueil : le lien est un lien ordinaire (ni onclick, ni style en ligne)',
        ecrire !== undefined && ecrire.onclick === null && ecrire.style === null,
        ecrire ? 'onclick=' + ecrire.onclick + ' style=' + ecrire.style : 'lien absent'
    );

    const pied = await page.evaluate(() => {
        const ligne = document.querySelector('.pied__contact');
        const lien = ligne ? ligne.querySelector('a') : null;
        if (!lien) {
            return null;
        }
        const style = getComputedStyle(lien);
        const boite = lien.getBoundingClientRect();
        return {
            texte: (ligne.textContent || '').replace(/\s+/g, ' ').trim(),
            adresse: lien.getAttribute('href'),
            couleur: style.color,
            fond: getComputedStyle(document.body).backgroundColor,
            largeur: boite.width,
            dedans: boite.left >= 0 && boite.right <= window.innerWidth + 1,
        };
    });
    verifier(
        'pied de page : « Une question ? » et l’adresse de contact, en texte lisible',
        pied !== null && pied.texte.includes('contact@') && pied.texte.includes('question'),
        pied ? '« ' + pied.texte + ' »' : 'ligne absente'
    );
    verifier(
        'pied de page : l’adresse est un lien mailto qui tient dans la largeur',
        pied !== null && pied.adresse.startsWith('mailto:contact@') && pied.dedans,
        pied ? pied.adresse + ', largeur ' + Math.round(pied.largeur) + ' px' : 'ligne absente'
    );
    verifier(
        'pied de page : le lien reste lisible dans la palette « ' + theme + ' » (contraste ≥ 4,5)',
        pied !== null && contraste(pied.couleur, pied.fond) >= 4.5,
        pied ? pied.couleur + ' sur ' + pied.fond + ' = ' + contraste(pied.couleur, pied.fond).toFixed(2) : 'ligne absente'
    );

    const fondPage = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
    verifier(
        'palette : le fond est bien celui du thème « ' + theme + ' »',
        fondPage === fondAttendu, fondPage + ' (attendu ' + fondAttendu + ')'
    );

    const debordement = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    verifier(
        'mise en page : aucun débordement horizontal à ' + largeur + ' px',
        debordement <= 1, debordement + ' px'
    );

    await page.screenshot({ path: sortie + '/contacts-' + theme + '-' + largeur + '-accueil.png', fullPage: false });

    // =======================================================================
    // Page de connexion (visiteur)
    // =======================================================================
    await visiter('/connexion');
    const liensConnexion = await liensMailto();
    const aide = liensConnexion.find((lien) => lien.adresse.startsWith('mailto:support@'));
    verifier(
        'connexion : « Mot de passe oublié ou besoin d’un compte ? » mène à l’assistance',
        aide !== undefined && aide.visible && aide.texte.includes('support@')
            && aide.onclick === null && aide.style === null,
        aide ? aide.adresse + ' / « ' + aide.texte + ' »' : 'aucun lien d’assistance'
    );
    const phraseConnexion = await page.evaluate(() => {
        const texte = document.body.innerText;
        return texte.includes('Mot de passe oublié ou besoin d’un compte ?');
    });
    verifier('connexion : la phrase d’aide est écrite en toutes lettres', phraseConnexion);
    await page.screenshot({ path: sortie + '/contacts-' + theme + '-' + largeur + '-connexion.png', fullPage: false });

    // =======================================================================
    // Mon compte : plus de section « Mes courriels »
    // =======================================================================
    await visiter('/mon-compte');
    const liensCompte = await liensMailto();
    const aideCompte = liensCompte.find((lien) => lien.adresse.startsWith('mailto:support@'));
    verifier(
        'mon compte : « Besoin d’aide ? » et l’adresse d’assistance',
        aideCompte !== undefined && aideCompte.visible && aideCompte.texte.includes('support@'),
        aideCompte ? aideCompte.adresse : 'aucun lien d’assistance'
    );
    const champsCourriel = await page.evaluate(() => ({
        email: document.querySelectorAll('input[type="email"], input[name="email"]').length,
        accords: document.querySelectorAll('input[name="notify_articles"], input[name="notify_gazette"]').length,
        section: document.body.innerText.includes('Mes courriels'),
    }));
    verifier(
        'mon compte : ni champ d’adresse, ni case de consentement, ni section « Mes courriels »',
        champsCourriel.email === 0 && champsCourriel.accords === 0 && champsCourriel.section === false,
        JSON.stringify(champsCourriel)
    );
    await page.screenshot({ path: sortie + '/contacts-' + theme + '-' + largeur + '-mon-compte.png', fullPage: false });

    // =======================================================================
    // Page d'erreur (404)
    // =======================================================================
    await visiter('/page-qui-n-existe-vraiment-pas');
    const liensErreur = await liensMailto();
    const aideErreur = liensErreur.find((lien) => lien.adresse.startsWith('mailto:support@'));
    verifier(
        'page d’erreur : une ligne vers l’assistance, en lien mailto',
        aideErreur !== undefined && aideErreur.visible,
        aideErreur ? aideErreur.adresse : 'aucun lien d’assistance'
    );
    const piedErreur = await page.evaluate(() => {
        const lien = document.querySelector('.pied__contact a');
        return lien ? (lien.textContent || '').trim() : '';
    });
    verifier(
        'page d’erreur : le pied de page garde l’adresse de contact',
        piedErreur.includes('contact@'), piedErreur.replace(/\s+/g, ' ')
    );
    await page.screenshot({ path: sortie + '/contacts-' + theme + '-' + largeur + '-erreur.png', fullPage: false });

    verifier('aucune erreur JavaScript sur les pages visitées', erreurs.length === 0, erreurs.join(' | '));
} finally {
    await navigateur.close();
}

const echecs = resultats.filter((resultat) => !resultat.ok).length;
console.log('\n' + resultats.length + ' contrôle(s) — ' + (resultats.length - echecs) + ' ok, ' + echecs + ' échec(s).');
if (echecs > 0) {
    process.exitCode = 1;
}
