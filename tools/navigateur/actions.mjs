/**
 * Contrôle « vrai navigateur » : les deux actions de la liste des fichiers
 * reçus (« Créer le document », « Supprimer »).
 *
 *   node tools/navigateur/actions.mjs 390
 *   node tools/navigateur/actions.mjs 1280
 *
 * Le serveur de tools/navigateur/serveur.mjs doit tourner, et la page
 * /admin/envoyer doit avoir été rendue par rendre.php avec un envoi « prêt »
 * (voir README.md). On vérifie ici ce qu'aucun essai PHP ne peut voir : la
 * place du tableau à l'écran, les deux boutons, la confirmation au clic, le
 * contenu réellement posté.
 */
import puppeteer from 'puppeteer-core';
import chromium from '@sparticuz/chromium';
import { readFileSync } from 'node:fs';

const largeur = Number(process.argv[2] || 1280);
const sortie  = process.env.CAPTURES || '/tmp';
const port    = Number(process.env.PORT || 8199);

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

    await page.goto('http://127.0.0.1:' + port + '/admin/envoyer', { waitUntil: 'load' });

    const debordement = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    verifier('aucun défilement horizontal de la page', debordement <= 0, 'débordement : ' + debordement);

    const colonnes = await page.$$eval('table.tableau thead th', (n) => n.map((t) => t.textContent.trim()));
    verifier('le tableau des fichiers reçus a une colonne « Actions »', colonnes.includes('Actions'), colonnes.join(' / '));

    const boutons = await page.$$eval('tbody tr td:last-child button', (n) => n.map((b) => b.textContent.trim()));
    verifier(
        'les deux boutons sont là, dans cet ordre',
        boutons.length === 2 && boutons[0] === 'Créer le document' && boutons[1] === 'Supprimer',
        boutons.join(' / ')
    );

    const formulaires = await page.$$eval('tbody tr td:last-child form', (n) =>
        n.map((f) => ({
            action: f.getAttribute('action'),
            methode: (f.getAttribute('method') || '').toLowerCase(),
            confirmation: f.getAttribute('data-confirmation'),
            csrf: !!f.querySelector('input[name="_csrf"]'),
            token: (f.querySelector('input[name="token"]') || {}).value || '',
            autresChamps: f.querySelectorAll('input:not([name="_csrf"]):not([name="token"])').length,
        }))
    );
    verifier(
        'chaque action est un POST vers sa route, avec le jeton CSRF et le jeton d’envoi',
        formulaires.length === 2
            && formulaires.every((f) => f.methode === 'post' && f.csrf && /^[a-f0-9]{32}$/.test(f.token))
            && formulaires[0].action === '/admin/envoyer/creer-document'
            && formulaires[1].action === '/admin/envoyer/supprimer',
        JSON.stringify(formulaires.map((f) => f.action))
    );
    verifier(
        'aucun champ caché en plus : rien du navigateur ne désigne un fichier',
        formulaires.every((f) => f.autresChamps === 0),
        JSON.stringify(formulaires.map((f) => f.autresChamps))
    );
    verifier(
        'la suppression seule porte la confirmation',
        formulaires[1].confirmation !== null && formulaires[0].confirmation === null
    );

    const hauteur = await page.$eval('tbody tr', (t) => Math.round(t.getBoundingClientRect().height));
    verifier('la ligne reste lisible (pas de tableau étiré)', hauteur < 260, hauteur + ' px');

    await page.screenshot({ path: sortie + '/page-envoyer-' + largeur + '.png', fullPage: true });

    // --- Clic sur « Supprimer » : la confirmation s'ouvre, puis on refuse.
    let message = '';
    page.once('dialog', async (d) => {
        message = d.message();
        await d.dismiss();
    });
    const avant = page.url();
    const supprimer = (await page.$$('tbody tr td:last-child button'))[1];
    await supprimer.click();
    await new Promise((r) => setTimeout(r, 600));
    verifier(
        '« Supprimer » demande confirmation, en nommant le fichier',
        message.includes('Supprimer définitivement') && message.includes('irréversible'),
        message
    );
    verifier('refuser la confirmation ne poste rien', page.url() === avant);

    // --- Clic sur « Créer le document » : le formulaire part, avec ses jetons.
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'load' }),
        (await page.$$('tbody tr td:last-child button'))[0].click(),
    ]);
    verifier('« Créer le document » poste le formulaire', page.url().endsWith('/admin/envoyer'), 'url : ' + page.url());

    const corps = readFileSync(sortie + '/capture-action-creer.txt', 'utf8');
    verifier(
        'le corps posté ne contient que le jeton CSRF et le jeton d’envoi',
        corps.includes('_csrf=') && /token=[a-f0-9]{32}/.test(corps)
            && !corps.includes('path') && !corps.includes('chemin') && !corps.includes('/'),
        corps
    );

    verifier('aucune erreur JavaScript', erreurs.length === 0, erreurs.join(' | '));
} finally {
    await navigateur.close();
}

const echecs = resultats.filter((r) => r.ok !== true).length;
console.log('\n' + largeur + ' px — ' + (resultats.length - echecs) + '/' + resultats.length + ' vérifications');
process.exit(echecs === 0 ? 0 : 1);
