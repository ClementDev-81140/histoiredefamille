/**
 * Contrôle « vrai navigateur » : envoi par morceaux, puis enregistrement du
 * formulaire sans toucher au champ « Fichier ».
 *
 *   node tools/navigateur/essai.mjs 390 video
 *   node tools/navigateur/essai.mjs 1280 document
 *
 * Le serveur de tools/navigateur/serveur.mjs doit tourner. Chromium est fourni
 * par puppeteer / @sparticuz/chromium (voir README.md).
 */
import puppeteer from 'puppeteer-core';
import chromium from '@sparticuz/chromium';
import { readFileSync } from 'node:fs';

const largeur  = Number(process.argv[2] || 1280);
const demande  = process.argv[3] || 'video';
const sortie   = process.env.CAPTURES || '/tmp';
const port     = Number(process.env.PORT || 8199);

const cibles = {
    video: {
        url: '/admin/videos/nouvelle',
        libelle: 'Envoyer la vidéo',
        fichier: process.env.FICHIER_VIDEO || '/tmp/film-essai.mp4',
    },
    document: {
        url: '/admin/documents/nouveau',
        libelle: 'Envoyer le document',
        fichier: process.env.FICHIER_DOCUMENT || '/tmp/lettre-essai.pdf',
    },
};
const cible = cibles[demande] || cibles.video;

const resultats = [];
const verifier = (nom, condition, detail = '') => {
    resultats.push({ nom, ok: !!condition });
    console.log((condition ? '  ok      ' : '  ÉCHEC   ') + nom + (detail ? ' — ' + detail : ''));
};

const navigateur = await puppeteer.launch({
    executablePath: await chromium.executablePath(),
    args: [...chromium.args, '--no-sandbox', '--disable-dev-shm-usage'],
    headless: true,
    defaultViewport: { width: largeur, height: 900 },
});

try {
    const page = await navigateur.newPage();
    const erreurs = [];
    page.on('pageerror', (e) => erreurs.push(String(e)));

    await page.goto('http://127.0.0.1:' + port + cible.url, { waitUntil: 'load' });
    await page.waitForSelector('[data-envoi]');

    const requisAvant = await page.$eval('input[type="file"][name="fichier"]', (n) => n.hasAttribute('required'));
    verifier('au départ, la case « Fichier » est obligatoire', requisAvant);

    // Dépôt du fichier dans le bloc d'envoi (comme un glisser-déposer).
    const champ = await page.$('#envoi-fichiers');
    await champ.uploadFile(cible.fichier);

    await page.waitForFunction(() => document.body.textContent.includes('Le fichier est prêt'), { timeout: 30000 });

    const etat = await page.evaluate(() => {
        const form = document.querySelector('form.formulaire');
        const statut = document.querySelector('.envoi-ligne__statut');
        const ancre = document.querySelector('.envoi-ligne__boutons a');
        const bouton = form.querySelector('button[type="submit"]');
        const fichier = form.querySelector('input[type="file"][name="fichier"]');
        const cache = form.querySelector('input[name="envoi"]');
        return {
            message: statut ? statut.textContent : '',
            ancre: ancre ? { texte: ancre.textContent, href: ancre.getAttribute('href') } : null,
            cible: bouton ? bouton.id : '',
            requis: fichier ? fichier.hasAttribute('required') : null,
            jeton: cache ? cache.value : '',
            doublons: document.querySelectorAll('#enregistrer').length,
        };
    });

    verifier('le message nomme le vrai bouton', etat.message.includes('« ' + cible.libelle + ' »'), etat.message);
    verifier('le lien « Aller au bouton d’enregistrement » vise le bouton',
        etat.ancre !== null && etat.ancre.texte.includes('Aller au bouton d’enregistrement')
        && etat.ancre.href === '#' + etat.cible && etat.cible === 'enregistrer',
        JSON.stringify(etat.ancre) + ' → #' + etat.cible);
    verifier('la case « Fichier » n’est plus obligatoire après l’envoi', etat.requis === false);
    verifier('le champ caché « envoi » porte le jeton (32 caractères)', /^[a-f0-9]{32}$/.test(etat.jeton), etat.jeton);
    verifier('un seul élément porte l’identifiant « enregistrer »', etat.doublons === 1, etat.doublons);

    // Le lien-ancre amène le bouton dans l'écran (défilement natif, sans script).
    await page.click('.envoi-ligne__boutons a');
    await new Promise((r) => setTimeout(r, 400));
    const visible = await page.evaluate(() => {
        const cadre = document.querySelector('#enregistrer').getBoundingClientRect();
        return cadre.top >= 0 && cadre.bottom <= window.innerHeight;
    });
    verifier('le clic sur le lien amène le bouton dans l’écran', visible);

    const mesure = await page.evaluate(() => ({
        ecran: document.documentElement.clientWidth,
        defilement: document.documentElement.scrollWidth,
    }));
    verifier('aucun défilement horizontal de page', mesure.defilement <= mesure.ecran + 1,
        mesure.defilement + ' / ' + mesure.ecran);

    await page.screenshot({ path: sortie + '/navigateur-' + demande + '-' + largeur + '.png' });

    // Message de validation du navigateur pour la case « Fichier » : vide.
    const validation = await page.$eval('input[type="file"][name="fichier"]', (n) => n.validationMessage || '');
    verifier('le navigateur ne réclame plus de fichier dans la case « Fichier »', validation === '', validation);

    // On remplit ce que remplit une personne, puis on clique sur le bouton,
    // sans rien sélectionner dans la case « Fichier ».
    await page.type('#title', 'Film venu par morceaux');
    await page.click('input[name="visibility"][value="prive"]').catch(() => null);

    await Promise.all([
        page.waitForNavigation({ waitUntil: 'load' }).catch(() => null),
        page.click('#enregistrer'),
    ]);
    await new Promise((r) => setTimeout(r, 800));

    let capture = '';
    try {
        capture = readFileSync(sortie + '/capture-' + demande + '.txt', 'utf8');
    } catch (e) {
        capture = '';
    }

    verifier('le formulaire est bien parti (aucun blocage « Veuillez sélectionner un fichier »)',
        capture !== '', capture === '' ? 'aucune requête reçue' : 'requête reçue');
    verifier('le formulaire posté contient le jeton « envoi »',
        /name="envoi"\r\n\r\n[a-f0-9]{32}/.test(capture));
    verifier('aucun fichier n’a été joint par la case « Fichier » (part vide seulement)',
        !/name="fichier"; filename="[^"]+"/.test(capture));
    verifier('le titre et la visibilité partent avec le formulaire',
        /name="title"/.test(capture) && /name="visibility"/.test(capture));
    verifier('aucune erreur JavaScript dans la page', erreurs.length === 0, erreurs.join(' | '));
} finally {
    await navigateur.close();
}

const echecs = resultats.filter((r) => !r.ok);
console.log('\n' + resultats.length + ' vérification(s) — ' + (resultats.length - echecs.length)
    + ' ok, ' + echecs.length + ' échec(s).');
process.exit(echecs.length === 0 ? 0 : 1);
