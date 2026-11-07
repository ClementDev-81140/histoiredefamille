/**
 * Contrôle « vrai navigateur » : la une dans le premier écran (1.29.0).
 *
 *   node tools/navigateur/essai-une.mjs 1280
 *   node tools/navigateur/essai-une.mjs 390
 *
 * Le serveur de tools/navigateur/serveur.mjs doit tourner (`/` renvoyant la
 * page d'accueil **d'un compte connecté**, rendue par rendre.php et convertie
 * par inline-medias.php, avec `THEME_CSS=` la feuille du thème voulu).
 *
 * Ce que l'on mesure, et que rien d'autre ne peut voir : la position
 * verticale du titre de la une dans la page, en pixels. Un essai PHP lit le
 * HTML et l'ordre des blocs ; il ne sait pas où le titre tombe à l'écran. Ici,
 * on exige :
 *
 *   - à 1280 × 800 : le titre **et** la photo commencent dans le premier écran
 *     (haut < 800 px) et la photo y tient entièrement ;
 *   - à 390 × 800 : le titre entier tient dans le premier écran (haut < 800 px,
 *     bas ≤ 800 px) ;
 *   - « Dans la famille » n'est jamais avant la une — et à 1280 px il est dans
 *     une colonne à droite (bord gauche au-delà du milieu de la page) ;
 *   - ni la ligne de chiffres, ni « Nous écrire », ni la phrase d'accueil ne
 *     sont là pour un compte connecté ;
 *   - sur téléphone, le bloc « Dans la famille » est replié (`<details>` fermé)
 *     quand il dépasse trois lignes, et reste lisible sans JavaScript.
 *
 * La hauteur de fenêtre est 800 px : c'est le premier écran demandé.
 */
import puppeteer from 'puppeteer-core';
import chromium from '@sparticuz/chromium';

const largeur = Number(process.argv[2] || 1280);
const hauteur = Number(process.env.HAUTEUR || 800);
const port    = Number(process.env.PORT || 8199);
const base    = 'http://127.0.0.1:' + port;
const ecran   = largeur >= 1024 ? 'grand écran' : 'téléphone';

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
    await page.goto(base + '/', { waitUntil: 'load' });
    await page.evaluate(() => document.fonts.ready);
    await new Promise((r) => setTimeout(r, 250));

    const vue = await page.evaluate(() => {
        const boite = (selecteur) => {
            const e = document.querySelector(selecteur);
            if (!e) return null;
            const r = e.getBoundingClientRect();
            return {
                haut: Math.round(r.top + window.scrollY),
                bas: Math.round(r.bottom + window.scrollY),
                gauche: Math.round(r.left),
                largeurPage: document.documentElement.clientWidth,
            };
        };
        const repli = document.querySelector('.accueil-famille__repli');
        return {
            titre: boite('.article-carte--une .article-carte__titre'),
            // Le bloc de la une (titre de rubrique compris) : « Dans la
            // famille » ne doit jamais commencer avant lui.
            blocUne: boite('.accueil-journal__une') || boite('.article-carte--une'),
            photo: boite('.article-carte--une .article-carte__image'),
            famille: boite('.dans-la-famille'),
            appareil: document.querySelector('.article-carte--une') ? 'oui' : 'non',
            chiffres: !!document.querySelector('.hero__chiffres'),
            ecrire: !!document.querySelector('.hero__ecrire'),
            phrase: !!document.querySelector('.hero__texte'),
            repli: repli ? { present: true, ouvert: repli.open, visible: repli.offsetParent !== null } : { present: false },
            // Le contenu du bloc est écrit deux fois (repli du téléphone et
            // version ouverte) : on compte les lignes d'un seul des deux.
            lignes: document.querySelectorAll('.accueil-famille__bloc .liste-personnes li').length,
            debordement: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        };
    });

    verifier('la page porte un article à la une', vue.titre !== null);
    verifier('une photo accompagne la une', vue.photo !== null);

    if (vue.titre !== null) {
        verifier('le titre de la une commence dans le premier écran (' + hauteur + ' px)',
            vue.titre.haut < hauteur, 'haut = ' + vue.titre.haut + ' px');
        // Le titre entier doit être lisible sans défiler, au moins sur téléphone,
        // où il n'y a pas la photo à côté pour gagner de la place.
        if (largeur < 1024) {
            verifier('le titre de la une tient entièrement dans le premier écran',
                vue.titre.bas <= hauteur, 'bas = ' + vue.titre.bas + ' px');
        }
    }

    if (vue.photo !== null) {
        verifier('la photo de la une commence dans le premier écran',
            vue.photo.haut < hauteur, 'haut = ' + vue.photo.haut + ' px');
        if (largeur >= 1024) {
            verifier('la photo de la une tient entièrement dans le premier écran',
                vue.photo.bas <= hauteur, 'bas = ' + vue.photo.bas + ' px');
        }
    }

    if (vue.famille !== null && vue.blocUne !== null) {
        verifier('« Dans la famille » vient après la une, jamais avant',
            vue.famille.haut >= vue.blocUne.haut,
            'famille = ' + vue.famille.haut + ' px, une = ' + vue.blocUne.haut + ' px');
        if (largeur >= 1024) {
            verifier('« Dans la famille » est dans une colonne à droite sur ' + ecran,
                vue.famille.gauche > Math.round(vue.famille.largeurPage / 2),
                'gauche = ' + vue.famille.gauche + ' px sur ' + vue.famille.largeurPage + ' px');
        }
    }

    verifier('la page ne déborde pas horizontalement', vue.debordement <= 1,
        vue.debordement + ' px de trop');

    verifier('la ligne de chiffres a quitté l’accueil d’un compte connecté', !vue.chiffres);
    verifier('« Nous écrire » a quitté l’accueil d’un compte connecté', !vue.ecrire);
    verifier('la phrase d’accueil longue a quitté l’accueil d’un compte connecté', !vue.phrase);

    verifier('« Dans la famille » montre cinq lignes au plus', vue.lignes <= 5, vue.lignes + ' ligne(s)');

    if (vue.repli.present) {
        verifier('au-delà de trois lignes, le bloc est replié sans JavaScript',
            vue.repli.ouvert === false, 'ouvert = ' + vue.repli.ouvert);
        if (largeur < 1024) {
            verifier('sur téléphone, le repli remplace le bloc ouvert', vue.repli.visible === true);
        } else {
            verifier('sur ' + ecran + ', le bloc reste ouvert (le repli est masqué)', vue.repli.visible === false);
        }
    }

    // Le repli s'ouvre au clic, sans JavaScript du site : c'est le
    // comportement natif de <details>. On le vérifie pour de vrai (clic de
    // souris), puis on le referme pour la capture.
    if (vue.repli.present && largeur < 1024) {
        await page.$eval('.accueil-famille__repli summary', (e) => e.scrollIntoView({ block: 'center' }));
        await page.click('.accueil-famille__repli summary');
        await new Promise((r) => setTimeout(r, 150));
        const ouvert = await page.evaluate(() => {
            const d = document.querySelector('.accueil-famille__repli');
            const li = d.querySelector('.liste-personnes li');
            return { open: d.open, li: !!li && li.offsetParent !== null };
        });
        verifier('un clic sur « Dans la famille » ouvre le bloc (natif, sans JavaScript)',
            ouvert.open === true && ouvert.li === true);
        await page.click('.accueil-famille__repli summary');
        await page.evaluate(() => window.scrollTo(0, 0));
    }

    // Une capture de la fenêtre (pas de la page entière) : c'est le premier
    // écran, celui que la personne voit sans défiler.
    if (process.env.CAPTURE) {
        await page.screenshot({ path: process.env.CAPTURE, fullPage: false });
        console.log('  capture : ' + process.env.CAPTURE);
    }
} finally {
    await navigateur.close();
}

const echecs = resultats.filter((r) => !r.ok);
console.log('\n' + resultats.length + ' vérification(s) — ' + (resultats.length - echecs.length)
    + ' ok, ' + echecs.length + ' échec(s).');
process.exit(echecs.length === 0 ? 0 : 1);
