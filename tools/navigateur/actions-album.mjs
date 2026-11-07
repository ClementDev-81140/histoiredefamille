/**
 * Contrôle « vrai navigateur » : ajouter des photos dans un album (1.27.0).
 *
 *   CAPTURES=/tmp node tools/navigateur/actions-album.mjs 390
 *   CAPTURES=/tmp node tools/navigateur/actions-album.mjs 1280
 *
 * Le serveur de tools/navigateur/serveur.mjs doit tourner avec :
 *
 *   PAGES_ALBUM='{"/admin/albums/7/modifier":"/tmp/album-modifier.html", …}'
 *   THEME_CSS=/tmp/theme-papier.css        (ou /tmp/theme-bleu.css)
 *
 * On vérifie ici ce qu'aucun essai PHP ne peut voir : la place du bouton
 * « Ajouter des photos » à l'écran, son absence pour un visiteur, le bloc
 * d'envoi qui n'accepte que des photos, le contenu réellement posté par les
 * formulaires de la liste, la confirmation au clic, la feuille de style du
 * thème (couleurs) et la mise en page d'impression.
 *
 * Variables d'environnement (toutes facultatives) :
 *   ALBUM_ID      identifiant de l'album d'essai (7 par défaut)
 *   SKU_ADMIN     slug de l'album rendu pour un administrateur
 *   SKU_VISITEUR  slug de l'album rendu pour un visiteur
 *   THEME         nom du thème, pour les captures (« papier », « bleu »)
 *   THEME_FOND    couleur de fond attendue, en rgb() (« rgb(243, 231, 206) »)
 */
import puppeteer from 'puppeteer-core';
import chromium from '@sparticuz/chromium';
import { readFileSync, existsSync } from 'node:fs';

const largeur = Number(process.argv[2] || 1280);
const sortie  = process.env.CAPTURES || '/tmp';
const port    = Number(process.env.PORT || 8199);

const albumId      = Number(process.env.ALBUM_ID || 7);
const skuAdmin     = process.env.SKU_ADMIN || 'album-admin';
const skuVisiteur  = process.env.SKU_VISITEUR || 'album-visiteur';
const theme        = process.env.THEME || 'papier';
const fondAttendu  = process.env.THEME_FOND || 'rgb(243, 231, 206)';

const urlModifier  = '/admin/albums/' + albumId + '/modifier';
const urlPhotos    = '/admin/albums/' + albumId + '/photos';
const urlAdmin     = '/albums/' + skuAdmin;
const urlVisiteur  = '/albums/' + skuVisiteur;
const base         = 'http://127.0.0.1:' + port;

const resultats = [];
const verifier = (nom, condition, detail = '') => {
    resultats.push({ nom, ok: !!condition });
    console.log((condition ? '  ok      ' : '  ÉCHEC   ') + nom + (detail ? ' — ' + detail : ''));
};

// Fichiers déposés : une photo (acceptée) et un film (refusé localement).
const photoEssai = sortie + '/photo-essai.png';
const filmEssai  = sortie + '/film-essai.mp4';

const navigateur = await puppeteer.launch({
    executablePath: await chromium.executablePath(),
    args: [...chromium.args, '--no-sandbox', '--disable-dev-shm-usage'],
    headless: true,
    defaultViewport: { width: largeur, height: 900 },
});

try {
    const page = await navigateur.newPage();
    // Le profil du navigateur d'essai garde un cache HTTP : sans cela, une
    // feuille de style réécrite depuis le dernier passage ne serait pas relue.
    await page.setCacheEnabled(false);
    const erreurs = [];
    page.on('pageerror', (e) => erreurs.push(String(e)));

    const visiter = (adresse) => page.goto(base + adresse, { waitUntil: 'load' });

    // --- 1. Le formulaire d'album : le bouton, bien visible -------------------
    await visiter(urlModifier);

    const bouton = await page.$eval('main a[href="' + urlPhotos + '"]', (a) => ({
        texte: a.textContent.trim(),
        largeur: Math.round(a.getBoundingClientRect().width),
        hauteur: Math.round(a.getBoundingClientRect().height),
        visible: a.getBoundingClientRect().width > 0 && a.getBoundingClientRect().height > 0,
    })).catch(() => null);

    verifier(
        'le formulaire de l’album montre « Ajouter des photos », assez grand pour être vu',
        bouton !== null && bouton.texte === 'Ajouter des photos' && bouton.visible
            && bouton.hauteur >= 30 && bouton.largeur >= 120,
        bouton ? JSON.stringify(bouton) : 'bouton absent'
    );

    const debordementModifier = await page.evaluate(
        () => document.documentElement.scrollWidth - window.innerWidth
    );
    verifier('le formulaire de l’album ne déborde pas', debordementModifier <= 0, debordementModifier + ' px');

    await page.screenshot({ path: sortie + '/album-modifier-' + theme + '-' + largeur + '.png', fullPage: true });

    // --- 2. Le bouton mène à la page d'envoi ---------------------------------
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('main a[href="' + urlPhotos + '"]')]);
    verifier('le bouton ouvre la page « Ajouter des photos »', new URL(page.url()).pathname === urlPhotos, page.url());

    // --- 3. Le bloc d'envoi : photos seulement --------------------------------
    const zone = await page.$eval('[data-envoi]', (section) => ({
        album: section.getAttribute('data-envoi-album'),
        versAlbum: section.getAttribute('data-envoi-vers-album'),
        lot: section.querySelector('[data-envoi-vers-lot]') !== null,
        accept: (section.querySelector('input[data-envoi-champ]') || {}).accept || '',
        multiple: (section.querySelector('input[data-envoi-champ]') || {}).multiple || false,
        zoneVisible: (() => {
            const z = section.querySelector('[data-envoi-zone]');
            return z !== null && z.getBoundingClientRect().height > 40;
        })(),
    })).catch(() => null);

    verifier(
        'la page d’envoi est celle de l’album : zone de dépôt, album transmis, « Voir l’album »',
        zone !== null && zone.album === String(albumId) && zone.zoneVisible
            && zone.versAlbum === urlAdmin && !zone.lot,
        zone ? JSON.stringify(zone) : 'bloc absent'
    );
    verifier(
        'le champ de fichier n’accepte que des photos, plusieurs à la fois',
        zone !== null && zone.multiple === true
            && zone.accept.split(',').map((a) => a.trim()).join(',') === '.jpg,.jpeg,.png,.webp,.gif',
        zone ? zone.accept : ''
    );

    const debordementPhotos = await page.evaluate(
        () => document.documentElement.scrollWidth - window.innerWidth
    );
    verifier('la page d’envoi ne déborde pas', debordementPhotos <= 0, debordementPhotos + ' px');

    // --- 4. La liste des photos ajoutées : deux formulaires ordinaires -------
    const lignes = await page.$$eval('#photos-ajoutees tbody tr', (trs) => trs.length);
    const formulaireTitre = await page.$eval('form[action="' + urlPhotos + '/titre"]', (f) => ({
        methode: (f.getAttribute('method') || '').toLowerCase(),
        csrf: !!f.querySelector('input[name="_csrf"]'),
        document: (f.querySelector('input[name="document_id"]') || {}).value || '',
        titre: !!f.querySelector('input[name="title"]'),
        date: !!f.querySelector('input[name="doc_date"]'),
        autres: f.querySelectorAll('input:not([name="_csrf"]):not([name="document_id"]):not([name="title"]):not([name="doc_date"])').length,
    })).catch(() => null);
    const formulaireRetirer = await page.$eval('form[action="' + urlPhotos + '/retirer"]', (f) => ({
        methode: (f.getAttribute('method') || '').toLowerCase(),
        csrf: !!f.querySelector('input[name="_csrf"]'),
        confirmation: f.getAttribute('data-confirmation'),
        bouton: ((f.querySelector('button') || {}).textContent || '').trim(),
    })).catch(() => null);

    verifier(
        'chaque photo ajoutée a un titre et une date modifiables, en formulaire ordinaire',
        lignes === 1 && formulaireTitre !== null && formulaireTitre.methode === 'post'
            && formulaireTitre.csrf && formulaireTitre.titre && formulaireTitre.date
            && /^\d+$/.test(formulaireTitre.document) && formulaireTitre.autres === 0,
        JSON.stringify({ lignes, formulaireTitre })
    );
    const texteRetirer = formulaireRetirer === null
        ? ''
        : (formulaireRetirer.bouton + ' ' + (formulaireRetirer.confirmation || '')).toLowerCase();
    verifier(
        '« Retirer de l’album » est un POST avec jeton, confirmation et document désigné',
        formulaireRetirer !== null && formulaireRetirer.methode === 'post' && formulaireRetirer.csrf
            && texteRetirer.includes('retirer de l’album')
            && texteRetirer.includes('n’est pas supprimé'),
        JSON.stringify(formulaireRetirer)
    );

    await page.screenshot({ path: sortie + '/album-photos-' + theme + '-' + largeur + '.png', fullPage: true });

    // --- 5. Refuser la confirmation de « Retirer » ne poste rien -------------
    let question = '';
    page.once('dialog', async (d) => {
        question = d.message();
        await d.dismiss();
    });
    const avant = page.url();
    await page.click('form[action="' + urlPhotos + '/retirer"] button');
    await new Promise((r) => setTimeout(r, 600));
    verifier(
        '« Retirer de l’album » demande confirmation, en nommant ce qui reste',
        question.includes('Retirer cette photo de l’album') && question.includes('n’est pas supprimé'),
        question
    );
    verifier(
        'refuser la confirmation ne poste rien',
        page.url() === avant && !existsSync(sortie + '/capture-album-retirer.txt')
    );

    // --- 6. Enregistrer le titre et la date : ce qui part réellement ---------
    await page.$eval('input[name="title"]', (i) => { i.value = 'Noces de 1962'; });
    await page.$eval('input[name="doc_date"]', (i) => { i.value = '1962-06-16'; });
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'load' }),
        page.click('form[action="' + urlPhotos + '/titre"] button'),
    ]);
    const corpsTitre = readFileSync(sortie + '/capture-album-titre.txt', 'utf8');
    verifier(
        'le formulaire de titre poste le jeton, le document, le titre et la date — rien d’autre',
        corpsTitre.includes('_csrf=') && /document_id=\d+/.test(corpsTitre)
            && corpsTitre.includes('title=Noces') && corpsTitre.includes('doc_date=1962')
            && !corpsTitre.includes('path=') && !corpsTitre.includes('chemin'),
        corpsTitre.replace(/[a-f0-9]{32}/g, '…')
    );

    // --- 7. Déposer une vraie photo : l'album part avec l'envoi, une seule recharge
    await page.goto(base + urlPhotos, { waitUntil: 'load' });
    let recharges = 0;
    // On compte les chargements de la page APRÈS celui-ci : une seule photo
    // reçue doit produire UN rechargement, pas une boucle.
    page.on('request', (r) => {
        if (r.resourceType() === 'document' && new URL(r.url()).pathname === urlPhotos) {
            recharges++;
        }
    });

    const champ = await page.$('input[data-envoi-champ]');
    await champ.uploadFile(photoEssai);
    await page.waitForFunction(
        () => !!document.querySelector('[data-envoi-liste] li[data-etat="termine"]'),
        { timeout: 20000 }
    );
    const corpsInit = readFileSync(sortie + '/capture-envoi-init.txt', 'utf8');
    verifier(
        'le navigateur envoie l’album demandé avec le fichier',
        /name="album"\r\n\r\n\d+\r\n/.test(corpsInit) && corpsInit.includes('\r\n' + albumId + '\r\n'),
        corpsInit.replace(/[a-f0-9]{32}/g, '…').replace(/\r\n/g, ' ')
    );

    // La page se recharge une fois, pour montrer la liste tenue par le serveur.
    await new Promise((r) => setTimeout(r, 2600));
    verifier(
        'la page se recharge une seule fois après l’envoi (le serveur affiche alors la liste)',
        recharges === 1,
        recharges + ' rechargement(s)'
    );

    // --- 8. Un film déposé dans un album est refusé, sans rien envoyer -------
    const initAvant = readFileSync(sortie + '/capture-envoi-init.txt', 'utf8');
    const champ2 = await page.$('input[data-envoi-champ]');
    await champ2.uploadFile(filmEssai);
    await new Promise((r) => setTimeout(r, 800));
    const refus = await page.$eval('[data-envoi-liste]', (liste) => liste.textContent);
    const initApres = readFileSync(sortie + '/capture-envoi-init.txt', 'utf8');
    verifier(
        'un film déposé sur la page d’album est refusé, sans aucun envoi',
        refus.includes('n’accepte que des photos') && initApres === initAvant,
        refus.trim().slice(0, 120)
    );

    // --- 9. La page publique de l'album : bouton pour l'admin, jamais sinon --
    await visiter(urlAdmin);
    const boutonPublicAdmin = await page.$eval('main a[href="' + urlPhotos + '"]', (a) => a.textContent.trim()).catch(() => '');
    verifier(
        'sur l’album public, un administrateur voit « Ajouter des photos »',
        boutonPublicAdmin === 'Ajouter des photos',
        boutonPublicAdmin || 'bouton absent'
    );
    const debordementPublic = await page.evaluate(
        () => document.documentElement.scrollWidth - window.innerWidth
    );
    verifier('l’album public ne déborde pas', debordementPublic <= 0, debordementPublic + ' px');
    await page.screenshot({ path: sortie + '/album-public-admin-' + theme + '-' + largeur + '.png', fullPage: true });

    await visiter(urlVisiteur);
    const visiteurs = await page.$$eval('main a[href*="/photos"]', (as) => as.map((a) => a.textContent.trim()));
    verifier(
        'un visiteur ne voit jamais « Ajouter des photos »',
        visiteurs.every((t) => t !== 'Ajouter des photos') && !visiteurs.includes('Ajouter des photos'),
        JSON.stringify(visiteurs)
    );
    await page.screenshot({ path: sortie + '/album-public-visiteur-' + theme + '-' + largeur + '.png', fullPage: true });

    // --- 10. La feuille du thème est bien celle du réglage -------------------
    const couleurs = await page.evaluate(() => {
        const style = window.getComputedStyle(document.body);
        return { fond: style.backgroundColor, texte: style.color };
    });
    verifier(
        'la palette du thème « ' + theme + ' » est appliquée',
        couleurs.fond === fondAttendu,
        couleurs.fond + ' (attendu ' + fondAttendu + ')'
    );

    // --- 11. Impression : la page reste lisible, le bouton reste là ----------
    await visiter(urlPhotos);
    await page.emulateMediaType('print');
    const debordementImpression = await page.evaluate(
        () => document.documentElement.scrollWidth - window.innerWidth
    );
    const boutonImpression = await page.$$eval('main a', (as) => as.some((a) => a.textContent.trim() === 'Voir l’album'));
    verifier(
        'à l’impression, la page tient dans la largeur',
        debordementImpression <= 0,
        debordementImpression + ' px'
    );
    verifier('à l’impression, le lien « Voir l’album » reste écrit', boutonImpression);
    await page.screenshot({ path: sortie + '/album-impression-' + theme + '-' + largeur + '.png', fullPage: true });
    await page.emulateMediaType('screen');

    verifier('aucune erreur JavaScript', erreurs.length === 0, erreurs.join(' | '));
} finally {
    await navigateur.close();
}

const echecs = resultats.filter((r) => r.ok !== true).length;
console.log('\n' + theme + ' ' + largeur + ' px — '
    + (resultats.length - echecs) + '/' + resultats.length + ' vérifications');
process.exit(echecs === 0 ? 0 : 1);
