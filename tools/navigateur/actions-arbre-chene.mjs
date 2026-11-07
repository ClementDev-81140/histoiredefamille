/**
 * Contrôle « vrai navigateur » de la 1.33.0 : l'arbre « chêne gravé ».
 *
 *   CAPTURES=/tmp node tools/navigateur/actions-arbre-chene.mjs 1280
 *   CAPTURES=/tmp node tools/navigateur/actions-arbre-chene.mjs 390
 *
 * Le serveur de tools/navigateur/serveur.mjs doit tourner avec les pages
 * rendues par rendre.php — un cas à **un enfant** et un cas à **huit enfants**
 * (les deux extrêmes demandés) :
 *
 *   PAGES_ALBUM='{"/arbre-huit":"/tmp/arbre-huit.html",
 *                 "/arbre-un":"/tmp/arbre-un.html"}' \
 *   CAPTURES=/tmp node tools/navigateur/serveur.mjs
 *
 * Ce que l'on regarde ici, et qu'aucun essai PHP ne peut voir :
 *
 *   - **aucune carte ne se superpose à une autre**, ni à 1280 px ni à 390 px,
 *     sur le cas à un enfant comme sur celui à huit ; c'est la mesure des
 *     rectangles, pas une impression ;
 *   - sur téléphone, les cartes sont **en colonne** (ancêtres, tronc,
 *     descendants) et la **gravure passe au-dessus** du dessin ;
 *   - sur grand écran, la gravure est le **fond** du dessin, entièrement
 *     chargée (fond calculé, pas d'image cassée) ;
 *   - le texte des cartes garde un **contraste suffisant** sur son papier
 *     (rapport ≥ 4,5), et les légendes de génération aussi ;
 *   - chaque carte est **un lien atteignable au clavier**, avec un contour
 *     visible quand il a le focus ;
 *   - aucun attribut `style`, aucun script en ligne, aucune ressource
 *     extérieure, aucun débordement horizontal, aucune erreur JavaScript ;
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
const pages = [
    { url: '/arbre-huit', nom: 'arbre-huit-enfants' },
    { url: '/arbre-un', nom: 'arbre-un-enfant' },
];

let ok = 0;
let ko = 0;
const souci = (texte) => { console.log('  ÉCHEC   ' + texte); ko++; };
const bien = (texte) => { console.log('  ok      ' + texte); ok++; };
const verifier = (condition, texte) => (condition ? bien(texte) : souci(texte));

/** Rapport de contraste WCAG entre deux couleurs « rgb(r, g, b) ». */
function contraste(a, b) {
    const lire = (c) => (c.match(/\d+/g) || ['0', '0', '0']).slice(0, 3).map(Number);
    const lum = (c) => {
        const [r, g, b] = lire(c).map((v) => {
            const s = v / 255;
            return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    };
    const l1 = Math.max(lum(a), lum(b));
    const l2 = Math.min(lum(a), lum(b));
    return (l1 + 0.05) / (l2 + 0.05);
}

const navigateur = await puppeteer.launch({
    executablePath: await chromium.executablePath(),
    args: [...chromium.args, '--no-sandbox', '--disable-dev-shm-usage'],
    headless: true,
});

const page = await navigateur.newPage();
await page.setViewport({ width: largeur, height: 900, deviceScaleFactor: 1 });
const erreursJs = [];
page.on('pageerror', (e) => erreursJs.push(String(e)));
page.on('console', (m) => { if (m.type() === 'error') { erreursJs.push(m.text()); } });

for (const cible of pages) {
    console.log('\n— ' + cible.url + ' à ' + largeur + ' px, palette ' + theme);

    await page.goto(base + cible.url, { waitUntil: 'networkidle0' });
    await page.evaluate(() => document.fonts.ready);
    await new Promise((r) => setTimeout(r, 200));

    const vue = await page.evaluate(() => {
        const rect = (el) => {
            const b = el.getBoundingClientRect();
            return { x: b.x, y: b.y, w: b.width, h: b.height };
        };
        const cartes = Array.from(document.querySelectorAll('.arbre-chene__noeud'));
        const gravure = document.querySelector('.arbre-chene__gravure img');
        const dessin = document.querySelector('.arbre-chene__dessin');
        const image2 = gravure ? getComputedStyle(document.querySelector('.arbre-chene__gravure')) : null;

        return {
            fond: getComputedStyle(document.body).backgroundColor,
            cartes: cartes.map((c) => {
                const lien = c.querySelector('a[href]');
                const nom = c.querySelector('.arbre-chene__nom');
                const styleCarte = getComputedStyle(c);
                const styleNom = nom ? getComputedStyle(nom) : null;
                return {
                    rect: rect(c),
                    lien: lien ? lien.getAttribute('href') : null,
                    texte: nom ? nom.textContent.trim() : '',
                    fondCarte: styleCarte.backgroundColor,
                    couleurNom: styleNom ? styleNom.color : null,
                    texteCentre: styleCarte.textAlign,
                };
            }),
            legendes: Array.from(document.querySelectorAll('.arbre-chene__legende, .arbre-chene__partie h2')).map((l) => {
                const s = getComputedStyle(l);
                return { couleur: s.color, fond: s.backgroundColor, texte: l.textContent.trim().slice(0, 30) };
            }),
            gravure: gravure ? {
                chargee: gravure.complete && gravure.naturalWidth > 0,
                rect: rect(gravure),
                affichee: getComputedStyle(document.querySelector('.arbre-chene__gravure')).display !== 'none',
            } : null,
            dessin: dessin ? {
                rect: rect(dessin),
                fondImage: getComputedStyle(dessin).backgroundImage,
            } : null,
            stylesEnLigne: document.querySelectorAll('[style]').length,
            scriptsEnLigne: Array.from(document.querySelectorAll('script')).filter((s) => !s.src).length,
            etrangers: Array.from(document.querySelectorAll('[src], link[href]'))
                .map((el) => el.getAttribute('src') || el.getAttribute('href') || '')
                .filter((u) => /^(https?:)?\/\//i.test(u) && !u.startsWith(location.origin)).length,
            debordement: document.documentElement.scrollWidth > window.innerWidth + 1,
        };
    });

    verifier(vue.fond === fondAttendu,
        'palette ' + theme + ' : fond du document ' + vue.fond + ' (attendu ' + fondAttendu + ')');
    verifier(!vue.debordement, 'aucun débordement horizontal à ' + largeur + ' px');
    verifier(vue.cartes.length >= 3, vue.cartes.length + ' cartes dans l’arbre');

    // --- Aucune superposition de cartes -----------------------------------
    const chevauchements = [];
    for (let i = 0; i < vue.cartes.length; i++) {
        for (let j = i + 1; j < vue.cartes.length; j++) {
            const a = vue.cartes[i].rect;
            const b = vue.cartes[j].rect;
            const touche = a.x < b.x + b.w - 0.5 && b.x < a.x + a.w - 0.5
                && a.y < b.y + b.h - 0.5 && b.y < a.y + a.h - 0.5;
            if (touche) {
                chevauchements.push(vue.cartes[i].texte + ' / ' + vue.cartes[j].texte);
            }
        }
    }
    verifier(chevauchements.length === 0,
        'aucune carte ne se superpose à une autre (' + chevauchements.join(', ') + ')');

    // --- Gravure ----------------------------------------------------------
    if (largeur >= 1024) {
        verifier(vue.gravure === null || vue.gravure.affichee === false,
            'grand écran : le bandeau de la gravure laisse la place au fond');
        verifier(/arbre-chene\.(webp|jpg)/.test(vue.dessin ? vue.dessin.fondImage : ''),
            'grand écran : la gravure est le fond du dessin (' +
                (vue.dessin ? vue.dessin.fondImage.slice(0, 60) : 'aucun') + '…)');
        verifier(vue.dessin !== null && vue.dessin.rect.h >= 600,
            'grand écran : le dessin garde les proportions de la gravure (' +
                (vue.dessin ? Math.round(vue.dessin.rect.h) + ' px de haut' : 'aucun') + ')');
    } else {
        verifier(vue.gravure !== null && vue.gravure.chargee,
            'téléphone : la gravure est chargée en bandeau (pas d’image cassée)');
        verifier(vue.gravure !== null && vue.gravure.rect.y + vue.gravure.rect.h <= vue.dessin.rect.y + 1,
            'téléphone : la gravure est au-dessus de l’arbre, pas derrière les cartes');
    }

    // --- Disposition : colonne sur téléphone, rangées sur grand écran -----
    const deuxCartes = vue.cartes.filter((c) => c.texte !== '');
    if (largeur < 1024) {
        const xs = new Set(deuxCartes.map((c) => Math.round(c.rect.x)));
        verifier(xs.size === 1,
            'téléphone : les cartes sont en colonne (x = ' + Array.from(xs).join(', ') + ')');
        verifier(deuxCartes.every((c) => c.rect.w > largeur * 0.6),
            'téléphone : chaque carte prend la largeur disponible');
        verifier(deuxCartes.every((c) => c.texteCentre === 'left' || c.texteCentre === 'center'),
            'téléphone : le texte des cartes reste lisible (aligné)');
    } else {
        const lignes = new Set(deuxCartes.map((c) => Math.round(c.rect.y)));
        verifier(lignes.size < deuxCartes.length,
            'grand écran : les cartes se répartissent en rangées (' + lignes.size + ' rangées)');
        verifier(deuxCartes.every((c) => c.rect.x >= vue.dessin.rect.x - 1
            && c.rect.x + c.rect.w <= vue.dessin.rect.x + vue.dessin.rect.w + 1),
            'grand écran : aucune carte ne sort du dessin');
    }

    // --- Contraste --------------------------------------------------------
    const pires = deuxCartes
        .map((c) => ({ texte: c.texte, rapport: contraste(c.couleurNom, c.fondCarte) }))
        .sort((a, b) => a.rapport - b.rapport);
    verifier(pires.length > 0 && pires[0].rapport >= 4.5,
        'contraste des noms sur leur carte ≥ 4,5 (' +
            (pires.length ? pires[0].rapport.toFixed(2) + ' pour « ' + pires[0].texte + ' »' : 'aucun') + ')');
    // Les légendes sont posées sur la gravure : elles doivent avoir leur propre
    // papier, opaque, sinon le contraste dépend des hachures qu'elles couvrent.
    const legendesOpaques = vue.legendes.length > 0
        && vue.legendes.every((l) => l.fond && !/rgba\(.*,\s*0(\.\d+)?\)$/.test(l.fond));
    verifier(legendesOpaques, 'les légendes de génération ont un fond opaque (' + vue.legendes.length + ' légende(s))');
    const rapportLegendes = vue.legendes.map((l) => contraste(l.couleur, l.fond || 'rgb(0,0,0)'));
    verifier(rapportLegendes.length > 0 && Math.min(...rapportLegendes) >= 4.5,
        'contraste des légendes de génération ≥ 4,5 (' +
        (rapportLegendes.length ? Math.min(...rapportLegendes).toFixed(2) : 'aucune') + ')');

    // --- Clavier ----------------------------------------------------------
    verifier(deuxCartes.every((c) => typeof c.lien === 'string' && c.lien.startsWith('/arbre/')),
        'chaque carte mène à une fiche (/arbre/{id})');
    const focus = await page.evaluate(() => {
        const lien = document.querySelector('.arbre-chene__noeud a[href]');
        if (!lien) { return null; }
        lien.focus();
        const style = getComputedStyle(lien);
        return {
            actif: document.activeElement === lien,
            contour: style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) > 0,
        };
    });
    verifier(focus !== null && focus.actif, 'la première carte reçoit le focus au clavier');
    verifier(focus !== null && focus.contour, 'le focus se voit (contour dessiné)');

    // --- Sécurité et propreté ---------------------------------------------
    verifier(vue.stylesEnLigne === 0, 'aucun attribut style= (' + vue.stylesEnLigne + ')');
    verifier(vue.scriptsEnLigne === 0, 'aucun script en ligne (' + vue.scriptsEnLigne + ')');
    verifier(vue.etrangers === 0, 'aucune ressource extérieure (' + vue.etrangers + ')');
    verifier(erreursJs.length === 0, 'aucune erreur JavaScript (' + erreursJs.join(' / ') + ')');

    // --- Capture ----------------------------------------------------------
    // On retire le focus du dernier essai : la capture ne doit pas montrer un
    // contour qui n'existe pas au repos.
    await page.evaluate(() => { if (document.activeElement) { document.activeElement.blur(); } });
    const suffixe = (theme === 'bleu' ? '-bleu' : '') + '.png';
    const prefixe = largeur === 1280 ? 'ordinateur-' : 'mobile-';
    const fichier = sortie + '/' + prefixe + cible.nom + suffixe;
    await page.screenshot({ path: fichier, fullPage: true });
    console.log('  capture ' + prefixe + cible.nom + suffixe);
}

await navigateur.close();
console.log('\n' + (ok + ko) + ' contrôle(s) — ' + ok + ' ok, ' + ko + ' échec(s).');
process.exit(ko === 0 ? 0 : 1);
