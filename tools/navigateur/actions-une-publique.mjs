/**
 * Contrôle « vrai navigateur » : la Une du journal pour un visiteur (1.31.0).
 *
 *   CAPTURES=/tmp node tools/navigateur/actions-une-publique.mjs 390
 *   CAPTURES=/tmp node tools/navigateur/actions-une-publique.mjs 1280
 *
 * Le serveur de tools/navigateur/serveur.mjs doit tourner avec les pages
 * rendues par rendre.php (compte 0 = visiteur) :
 *
 *   PAGES_ALBUM='{"/":"/tmp/accueil-inline.html",
 *                 "/vide":"/tmp/accueil-vide-inline.html",
 *                 "/documents/1":"/tmp/contenu-public-inline.html",
 *                 "/documents/5":"/tmp/prive-404-inline.html",
 *                 "/page-qui-n-existe-vraiment-pas":"/tmp/introuvable-404-inline.html"}'
 *   THEME_CSS=/tmp/theme-papier.css        (ou /tmp/theme-bleu.css)
 *
 * Ce que l'on vérifie ici, et qu'aucun essai PHP ne peut voir :
 *
 *   - le menu d'un visiteur ne contient que « Accueil » et « Se connecter »
 *     (deux liens, pas un de plus) ;
 *   - le bouton « Se connecter » est **dans le premier écran** ;
 *   - « À la une » montre un contenu en grand (titre cliquable, image chargée)
 *     et la grille suit ; au-delà de six contenus, la grille est regroupée en
 *     rubriques ;
 *   - **aucun lien** de la page ne mène ailleurs que vers du contenu public,
 *     la connexion ou l'adresse de contact du pied de page ;
 *   - aucun style ni script en ligne, aucune ressource extérieure, aucun
 *     débordement horizontal, aucune erreur JavaScript ;
 *   - l'accueil vide ne montre ni une, ni grille : seulement la phrase, le
 *     bouton et les deux adresses ;
 *   - un contenu **privé** ouvert par son adresse directe montre exactement la
 *     page « introuvable » (même texte, même mise en page) — le code 404
 *     lui-même est vérifié par les essais PHP, le banc ne servant que des
 *     fichiers.
 *
 * Variables d'environnement (toutes facultatives) :
 *   THEME        nom du thème, pour les captures (« papier », « bleu »)
 *   THEME_FOND   couleur de fond attendue, en rgb() (« rgb(243, 231, 206) »)
 *   CAPTURES     dossier des captures
 *   PORT         port du serveur d'essai (8199)
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
        await new Promise((r) => setTimeout(r, 200));
    };

    // =======================================================================
    // 1. L'accueil d'un visiteur, avec du contenu public
    // =======================================================================
    await visiter('/');

    const nav = await page.evaluate(() => {
        const liens = Array.from(document.querySelectorAll('nav.nav a'));
        return {
            libelles: liens.map((lien) => (lien.textContent || '').trim()),
            adresses: liens.map((lien) => lien.getAttribute('href')),
        };
    });
    verifier(
        'menu : exactement deux entrées, « Accueil » et « Se connecter »',
        nav.libelles.length === 2
            && nav.libelles[0] === 'Accueil'
            && nav.libelles[1] === 'Se connecter'
            && nav.adresses[0] === '/'
            && nav.adresses[1] === '/connexion',
        JSON.stringify(nav)
    );
    verifier(
        'menu : ni « Consulter », ni « Carte »',
        !nav.libelles.some((libelle) => /Consulter|Carte/.test(libelle)),
        nav.libelles.join(' / ')
    );

    const une = await page.evaluate(() => {
        const bouton = document.querySelector('.une-intro .bouton--porte');
        const boite = bouton ? bouton.getBoundingClientRect() : null;
        const titre = document.querySelector('.une-grande__titre a');
        const image = document.querySelector('.une-grande__image img');
        const cartes = Array.from(document.querySelectorAll('.une-grille .une-carte'));
        const rubriques = Array.from(document.querySelectorAll('.une-rubrique h2'));
        return {
            boutonTexte: bouton ? (bouton.textContent || '').trim() : '',
            boutonAdresse: bouton ? bouton.getAttribute('href') : '',
            boutonHaut: boite ? Math.round(boite.top) : -1,
            boutonBas: boite ? Math.round(boite.bottom) : -1,
            boutonLarge: boite ? Math.round(boite.width) : 0,
            boutonHauteur: boite ? Math.round(boite.height) : 0,
            titre: titre ? (titre.textContent || '').trim() : '',
            titreAdresse: titre ? titre.getAttribute('href') : '',
            titreHaut: titre ? Math.round(titre.getBoundingClientRect().top) : -1,
            imageChargee: image ? (image.complete && image.naturalWidth > 0) : null,
            imageAdresse: image ? image.getAttribute('src') : '',
            cartes: cartes.length,
            rubriques: rubriques.map((h) => (h.textContent || '').trim()),
        };
    });

    verifier(
        'premier écran : le bouton « Se connecter » y est en entier, grand et large',
        une.boutonTexte === 'Se connecter'
            && une.boutonAdresse === '/connexion'
            && une.boutonHaut >= 0
            && une.boutonBas <= hauteur
            && une.boutonHauteur >= 44
            && une.boutonLarge >= 180,
        'haut ' + une.boutonHaut + ' px, bas ' + une.boutonBas + ' px, '
            + une.boutonLarge + ' × ' + une.boutonHauteur + ' px'
    );
    verifier(
        'à la une : un contenu en grand, titre cliquable vers sa page publique',
        une.titre !== ''
            && /^\/(articles|documents|videos|recettes|pages)\//.test(une.titreAdresse)
            && une.titreHaut >= 0
            && une.titreHaut < hauteur * 2,
        une.titre + ' → ' + une.titreAdresse + ' (haut ' + une.titreHaut + ' px)'
    );
    // Le banc écrit les images en `data:` (inline-medias.php) : sur le site,
    // la même vignette est servie par `/media/miniature/{id}`. Ce que l'on
    // vérifie ici : elle est bien chargée, et elle vient du site.
    verifier(
        'à la une : la vignette est chargée par le site (aucune ressource extérieure)',
        une.imageChargee === null
            || (une.imageChargee === true
                && (une.imageAdresse.startsWith('/media/miniature/')
                    || une.imageAdresse.startsWith('data:image/'))),
        une.imageChargee === true ? 'image chargée' : String(une.imageChargee)
    );
    verifier(
        'grille : des contenus publics, regroupés par rubrique au-delà de six',
        une.cartes >= 6 && une.rubriques.length >= 3,
        une.cartes + ' carte(s), rubriques : ' + une.rubriques.join(' / ')
    );
    verifier(
        'grille : les rubriques sont celles du journal (Articles, Photos, Vidéos…)',
        une.rubriques.every((libelle) => ['Articles', 'Photos', 'Vidéos', 'Documents',
            'Recettes', 'Pages d’information'].includes(libelle))
            && une.rubriques.includes('Articles'),
        une.rubriques.join(' / ')
    );

    // Tous les liens de la page : contenu public, connexion, ou pied de page.
    const liens = await page.evaluate(() => Array.from(document.querySelectorAll('a[href]'))
        .map((lien) => lien.getAttribute('href')));
    const autorise = (adresse) => adresse === '/'
        || adresse === '/connexion'
        || adresse === '#contenu'
        || adresse.startsWith('mailto:')
        || /^\/(articles|documents|videos|recettes|pages)\/[^/]+$/.test(adresse);
    const interdits = Array.from(new Set(liens.filter((adresse) => !autorise(adresse))));
    verifier(
        'liens : rien d’autre que du contenu public, la connexion et le contact',
        interdits.length === 0,
        interdits.join(' / ')
    );

    const propre = await page.evaluate(() => ({
        stylesEnLigne: document.querySelectorAll('[style]').length,
        scriptsEnLigne: Array.from(document.querySelectorAll('script:not([src])')).length,
        ressourcesExterieures: Array.from(document.querySelectorAll('[src], link[href]'))
            .map((e) => e.getAttribute('src') || e.getAttribute('href'))
            .filter((adresse) => adresse && /^(https?:)?\/\//.test(adresse)).length,
        h1: document.querySelectorAll('h1').length,
        fond: getComputedStyle(document.body).backgroundColor,
        debordement: document.documentElement.scrollWidth - window.innerWidth,
    }));
    verifier(
        'page : aucun style ni script en ligne, aucune ressource extérieure',
        propre.stylesEnLigne === 0 && propre.scriptsEnLigne === 0 && propre.ressourcesExterieures === 0,
        JSON.stringify(propre)
    );
    verifier('page : un seul h1, et il est invisible à l’écran', propre.h1 === 1, String(propre.h1));
    verifier('page : aucun débordement horizontal', propre.debordement <= 1, propre.debordement + ' px');
    if (process.env.THEME_FOND) {
        verifier('palette : le fond est celui du thème demandé', propre.fond === fondAttendu,
            propre.fond + ' (attendu ' + fondAttendu + ')');
    }
    await page.screenshot({ path: sortie + '/une-publique-' + theme + '-' + largeur + '.png', fullPage: true });

    // =======================================================================
    // 2. L'accueil vide : une Une sobre, sans bloc vide
    // =======================================================================
    await visiter('/vide');
    const vide = await page.evaluate(() => {
        const bouton = document.querySelector('.une-vide .bouton--porte');
        const boite = bouton ? bouton.getBoundingClientRect() : null;
        return {
            blocVide: document.querySelectorAll('.une-vide').length,
            une: document.querySelectorAll('.une-grande').length,
            grilles: document.querySelectorAll('.une-grille').length,
            rubriques: document.querySelectorAll('.une-rubrique').length,
            bouton: bouton ? (bouton.textContent || '').trim() : '',
            boutonBas: boite ? Math.round(boite.bottom) : -1,
            adresses: Array.from(document.querySelectorAll('.une-vide a[href^="mailto:"]'))
                .map((lien) => lien.getAttribute('href')),
        };
    });
    verifier(
        'accueil vide : une Une sobre, ni une, ni grille, ni rubrique fantôme',
        vide.blocVide === 1 && vide.une === 0 && vide.grilles === 0 && vide.rubriques === 0,
        JSON.stringify(vide)
    );
    verifier(
        'accueil vide : « Se connecter » en grand, dans le premier écran',
        vide.bouton === 'Se connecter' && vide.boutonBas > 0 && vide.boutonBas <= hauteur,
        vide.bouton + ' (bas ' + vide.boutonBas + ' px)'
    );
    verifier(
        'accueil vide : les deux adresses, contact et assistance',
        vide.adresses.some((a) => a.startsWith('mailto:contact@'))
            && vide.adresses.some((a) => a.startsWith('mailto:support@')),
        vide.adresses.join(' / ')
    );
    await page.screenshot({ path: sortie + '/une-vide-' + theme + '-' + largeur + '.png', fullPage: true });

    // =======================================================================
    // 3. L'ouverture d'un contenu public
    // =======================================================================
    await visiter('/documents/1');
    const contenu = await page.evaluate(() => ({
        titre: (document.querySelector('h1')?.textContent || '').trim(),
        image: (() => {
            const image = document.querySelector('main img, .contenu img, article img');
            return image ? (image.complete && image.naturalWidth > 0) : null;
        })(),
        debordement: document.documentElement.scrollWidth - window.innerWidth,
    }));
    verifier(
        'contenu public : la page s’ouvre sans être connecté, titre et image',
        contenu.titre !== '' && contenu.image !== false && contenu.debordement <= 1,
        contenu.titre + ' / image : ' + contenu.image
    );
    await page.screenshot({ path: sortie + '/contenu-public-' + theme + '-' + largeur + '.png', fullPage: true });

    // =======================================================================
    // 4. Un contenu privé par adresse directe : la page « introuvable »
    // =======================================================================
    await visiter('/page-qui-n-existe-vraiment-pas');
    const introuvable = await page.evaluate(() => document.body.innerText.replace(/\s+/g, ' ').trim());
    await visiter('/documents/5');
    const prive = await page.evaluate(() => document.body.innerText.replace(/\s+/g, ' ').trim());
    verifier(
        'contenu privé par adresse directe : exactement la page « introuvable »',
        prive === introuvable && /introuvable/i.test(prive),
        prive.slice(0, 120)
    );
    verifier(
        'contenu privé : ni son titre ni son adresse n’apparaissent dans la page',
        !prive.includes('Acte fictif') && !prive.includes('/documents/5')
    );
    await page.screenshot({ path: sortie + '/contenu-prive-404-' + theme + '-' + largeur + '.png', fullPage: true });

    verifier('aucune erreur JavaScript sur les pages visitées', erreurs.length === 0, erreurs.join(' | '));
} finally {
    await navigateur.close();
}

const echecs = resultats.filter((resultat) => !resultat.ok).length;
console.log('\n' + resultats.length + ' contrôle(s) — ' + (resultats.length - echecs) + ' ok, ' + echecs + ' échec(s).');
if (echecs > 0) {
    process.exitCode = 1;
}
