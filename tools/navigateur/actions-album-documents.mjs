/**
 * Contrôle « vrai navigateur » : verser dans un album des documents déjà
 * présents sur le site (1.30.0).
 *
 *   ALBUM_ID=2 SKU_ALBUM=album-banc-130 node tools/navigateur/actions-album-documents.mjs 1280
 *   ALBUM_ID=2 SKU_ALBUM=album-banc-130 node tools/navigateur/actions-album-documents.mjs 390
 *
 * Le banc (tools/navigateur/serveur.mjs) sert des pages **rendues par le
 * routeur du site** : la page « Ajouter des photos » avant l'ajout, la même
 * après l'ajout (avec `?ajoutes=`), l'album public vu par un administrateur, et
 * l'album public vu par un lecteur — avant puis après le changement de
 * visibilité (servi sous `<album>/lecteur` et `<album>/lecteur-apres` : le banc
 * sert des pages par leur adresse, et ces deux-là ne sont pas des adresses du
 * site, seulement des rendus différents de la même page). Les deux formulaires d'ajout et de visibilité sont notés par le
 * banc (`capture-album-ajouter.txt`, `capture-album-visibilite.txt`) : ici, on
 * vérifie le NAVIGATEUR (cases cochées, boutons, corps posté, dialogues).
 *
 * Ce que l'on exige :
 *   - la section « Ajouter depuis les documents du site » est là, avec sa
 *     grille à cocher et ses filtres, sans style ni script en ligne ;
 *   - on peut cocher deux photos et les ajouter : le formulaire part avec le
 *     jeton CSRF et **exactement** les deux identifiants cochés ;
 *   - « Tout cocher sur cette page » coche bien les cases (JavaScript de
 *     confort) et « Tout ajouter sur cette page » part avec `tout=1` ;
 *   - les deux photos ajoutées se voient dans l'album ;
 *   - une photo privée ajoutée à un album « famille » reste **invisible** pour
 *     un lecteur tant qu'on ne lui a pas donné la visibilité de l'album ;
 *   - après l'ajout, la page dit clairement que la photo reste privée et le
 *     bouton « Leur donner la visibilité de l'album » demande confirmation.
 */
import puppeteer from 'puppeteer-core';
import chromium from '@sparticuz/chromium';
import { readFileSync } from 'node:fs';

const largeur = Number(process.argv[2] || 1280);
const port    = Number(process.env.PORT || 8199);
const sortie  = process.env.CAPTURES || '/tmp';
const albumId = Number(process.env.ALBUM_ID || 2);
const slug    = process.env.SKU_ALBUM || 'album-banc-130';
const base    = 'http://127.0.0.1:' + port;

const resultats = [];
const verifier = (nom, condition, detail = '') => {
    resultats.push({ nom, ok: !!condition });
    console.log((condition ? '  ok      ' : '  ÉCHEC   ') + nom + (detail ? ' — ' + detail : ''));
};

const lire = (fichier) => {
    try {
        return readFileSync(sortie + '/' + fichier, 'utf8');
    } catch (e) {
        return '';
    }
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
    page.on('dialog', (dialogue) => dialogue.accept());

    // --- 1. La page « Ajouter des photos » : la section et sa grille --------
    await page.goto(base + '/admin/albums/' + albumId + '/photos', { waitUntil: 'load' });
    await page.evaluate(() => document.fonts.ready);

    const section = await page.evaluate(() => {
        const bloc = document.getElementById('ajouter-documents');
        const cases = bloc ? bloc.querySelectorAll('input[type="checkbox"][name="documents[]"]') : [];
        const dedans = bloc ? bloc.querySelectorAll('.choix-document--dedans').length : 0;
        const filtres = document.getElementById('filtres-documents');
        const pasAlbum = document.getElementById('f-pas-album');
        return {
            presente: !!bloc,
            cases: cases.length,
            dedans,
            filtresGet: filtres ? filtres.getAttribute('method').toLowerCase() : '',
            pasAlbumCoche: pasAlbum ? pasAlbum.checked : null,
            style: document.querySelectorAll('[style]').length,
            script: document.querySelectorAll('script:not([src])').length,
            debordement: document.documentElement.scrollWidth - document.documentElement.clientWidth,
            titres: bloc ? Array.from(bloc.querySelectorAll('.choix-document__titre')).map((e) => e.textContent.trim()) : [],
        };
    });

    verifier('la section « Ajouter depuis les documents du site » est là', section.presente);
    verifier('la grille propose au moins deux documents à cocher', section.cases >= 2, section.cases + ' case(s)');
    verifier('les filtres sont un formulaire GET', section.filtresGet === 'get');
    verifier('« pas encore dans cet album » est coché au départ', section.pasAlbumCoche === true);
    verifier('aucun style ni script en ligne dans la page', section.style === 0 && section.script === 0,
        section.style + ' style(s), ' + section.script + ' script(s)');
    verifier('la page ne déborde pas horizontalement', section.debordement <= 1, section.debordement + ' px de trop');

    if (process.env.CAPTURE_PHOTOS) {
        await page.screenshot({ path: process.env.CAPTURE_PHOTOS, fullPage: true });
        console.log('  capture : ' + process.env.CAPTURE_PHOTOS);
    }

    // --- 2. Cocher deux photos, puis « Ajouter la sélection » --------------
    const cochees = await page.evaluate(() => {
        const cases = Array.from(
            document.querySelectorAll('#ajouter-documents input[type="checkbox"][name="documents[]"]')
        ).slice(0, 2);
        cases.forEach((c) => { c.checked = true; });
        return cases.map((c) => c.value);
    });
    verifier('deux cases peuvent être cochées', cochees.length === 2, cochees.join(', '));

    await Promise.all([
        page.waitForNavigation({ waitUntil: 'load' }).catch(() => null),
        page.click('#selection-documents button[type="submit"]'),
    ]);
    await new Promise((r) => setTimeout(r, 300));

    // Le formulaire part en `application/x-www-form-urlencoded` : on relit le
    // corps comme le fait le serveur.
    const poste = new URLSearchParams(lire('capture-album-ajouter.txt'));
    const idsPostes = poste.getAll('documents[]');
    verifier('le formulaire d’ajout part avec le jeton CSRF',
        (poste.get('_csrf') || '').length >= 10,
        poste.toString() ? '' : 'aucune requête reçue');
    verifier('le formulaire part avec exactement les deux documents cochés',
        idsPostes.length === 2 && idsPostes.join(',') === cochees.join(','),
        idsPostes.join(', '));

    // --- 3. « Tout cocher » puis « Tout ajouter sur cette page » ----------
    await page.goto(base + '/admin/albums/' + albumId + '/photos', { waitUntil: 'load' });
    await page.click('[data-tout-cocher="selection-documents"]');
    const toutCoche = await page.evaluate(() => {
        const cases = Array.from(
            document.querySelectorAll('#ajouter-documents input[type="checkbox"][name="documents[]"]')
        );
        return { total: cases.length, cochees: cases.filter((c) => c.checked).length };
    });
    verifier('« Tout cocher sur cette page » coche toutes les cases',
        toutCoche.total > 0 && toutCoche.cochees === toutCoche.total,
        toutCoche.cochees + ' / ' + toutCoche.total);

    await Promise.all([
        page.waitForNavigation({ waitUntil: 'load' }).catch(() => null),
        page.click('#selection-documents button[name="tout"]'),
    ]);
    await new Promise((r) => setTimeout(r, 300));
    const posteTout = new URLSearchParams(lire('capture-album-ajouter.txt'));
    verifier('« Tout ajouter sur cette page » part avec tout=1', posteTout.get('tout') === '1',
        posteTout.toString() ? '' : 'aucune requête reçue');

    // --- 4. Les deux photos sont dans l'album -----------------------------
    await page.goto(base + '/albums/' + slug, { waitUntil: 'load' });
    const albumAdmin = await page.evaluate(() => document.body.textContent);
    verifier('l’album montre la première photo ajoutée', albumAdmin.includes('Photo publique du banc 1.30'));
    verifier('l’album montre la photo privée à l’administrateur', albumAdmin.includes('Photo privée du banc 1.30'));

    if (process.env.CAPTURE_ALBUM) {
        await page.screenshot({ path: process.env.CAPTURE_ALBUM, fullPage: true });
        console.log('  capture : ' + process.env.CAPTURE_ALBUM);
    }

    // --- 5. Un lecteur ne voit pas la photo privée ------------------------
    await page.goto(base + '/albums/' + slug + '/lecteur', { waitUntil: 'load' });
    const albumLecteur = await page.evaluate(() => document.body.textContent);
    verifier('un lecteur voit la photo publique ajoutée', albumLecteur.includes('Photo publique du banc 1.30'));
    verifier('un lecteur ne voit pas la photo privée de l’album famille',
        !albumLecteur.includes('Photo privée du banc 1.30'));

    // --- 6. Après le changement de visibilité, il la voit -----------------
    await page.goto(base + '/albums/' + slug + '/lecteur-apres', { waitUntil: 'load' });
    const albumApres = await page.evaluate(() => document.body.textContent);
    verifier('après « Leur donner la visibilité de l’album », le lecteur voit la photo',
        albumApres.includes('Photo privée du banc 1.30'));

    // --- 7. L'avertissement et l'action séparée ---------------------------
    // Page servie après un ajout (elle porte `?ajoutes=…` et l'avertissement) :
    // le banc la sert sous une adresse propre à ce rendu.
    await page.goto(base + (process.env.CHEMIN_APRES || '/admin/albums/' + albumId + '/photos/apres'),
        { waitUntil: 'load' });
    const avertissement = await page.evaluate(() => {
        const bloc = document.getElementById('visibilite-a-corriger');
        return {
            present: !!bloc,
            texte: bloc ? bloc.textContent.replace(/\s+/g, ' ').trim() : '',
            bouton: !!document.querySelector('#visibilite-a-corriger button[type="submit"]'),
            confirmation: !!document.querySelector('#visibilite-a-corriger form[data-confirmation]'),
        };
    });
    verifier('après l’ajout, la page dit que la photo reste privée',
        avertissement.present && /reste privée/.test(avertissement.texte), avertissement.texte.slice(0, 90));
    verifier('l’avertissement propose « Leur donner la visibilité de l’album »',
        avertissement.bouton && /Leur donner la visibilité de l’album/.test(avertissement.texte));

    if (process.env.CAPTURE_AVERTISSEMENT) {
        await page.screenshot({ path: process.env.CAPTURE_AVERTISSEMENT, fullPage: true });
        console.log('  capture : ' + process.env.CAPTURE_AVERTISSEMENT);
    }

    await Promise.all([
        page.waitForNavigation({ waitUntil: 'load' }).catch(() => null),
        page.click('#visibilite-a-corriger button[type="submit"]'),
    ]);
    await new Promise((r) => setTimeout(r, 300));
    const posteVisi = new URLSearchParams(lire('capture-album-visibilite.txt'));
    verifier('la confirmation du navigateur est demandée avant de changer la visibilité', avertissement.confirmation);
    verifier('l’action part avec les documents concernés et le jeton CSRF',
        posteVisi.getAll('documents[]').join(',') === '11' && (posteVisi.get('_csrf') || '').length >= 10,
        posteVisi.toString());

    // --- 8. La liste des photos ajoutées est toujours là ------------------
    // « Retirer de l'album » (avec sa confirmation) vit dans cette liste : elle
    // n'apparaît qu'après un envoi de photos. Les essais PHP la couvrent
    // (`tests_album`), ici on vérifie que la page ne l'a pas perdue.
    await page.goto(base + '/admin/albums/' + albumId + '/photos', { waitUntil: 'load' });
    const listePhotos = await page.evaluate(() => {
        const section = document.getElementById('photos-ajoutees');
        return {
            present: !!section,
            titre: section ? section.textContent.replace(/\s+/g, ' ').trim().slice(0, 60) : '',
        };
    });
    verifier('la liste « Photos ajoutées à cet album » est toujours là',
        listePhotos.present && /Photos ajoutées à cet album/.test(listePhotos.titre), listePhotos.titre);

    verifier('aucune erreur JavaScript dans les pages', erreurs.length === 0, erreurs.join(' | '));
} finally {
    await navigateur.close();
}

const echecs = resultats.filter((r) => !r.ok);
console.log('\n' + resultats.length + ' vérification(s) — ' + (resultats.length - echecs.length)
    + ' ok, ' + echecs.length + ' échec(s).');
process.exit(echecs.length === 0 ? 0 : 1);
