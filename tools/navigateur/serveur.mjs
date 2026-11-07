/**
 * Petit serveur pour le contrôle « vrai navigateur ».
 *
 * Il sert les VRAIES pages rendues par le routeur du site (fichiers produits
 * par rendre.php) et les VRAIS scripts de public/assets. Les adresses du
 * protocole d'envoi par morceaux répondent ce que répond le site (mêmes clés
 * JSON, mêmes codes), sans écrire sur le disque : ce que l'on vérifie ici est
 * le comportement du NAVIGATEUR (champ « Fichier », champ caché, lien-ancre,
 * envoi du formulaire au clic, boutons de la liste des fichiers reçus). Le
 * protocole, la base et les droits sont couverts par
 * `php app/cli/tests.php envoi`.
 *
 *   PAGE_VIDEO=/tmp/video.html PAGE_DOCUMENT=/tmp/document.html \
 *   PAGE_ENVOYER=/tmp/envoyer.html CAPTURES=/tmp node tools/navigateur/serveur.mjs
 *
 * Pour le contrôle des albums (1.27.0) :
 *
 *   PAGES_ALBUM='{"/admin/albums/7/photos":"/tmp/photos.html"}' \
 *   THEME_CSS=/tmp/theme-papier.css CAPTURES=/tmp node tools/navigateur/serveur.mjs
 *
 * `THEME_CSS` sert la feuille /theme.css **rendue par le site** (la palette
 * dépend d'un réglage) : sans elle, le navigateur ne reçoit que style.css et
 * l'on ne peut pas vérifier les couleurs. `PAGES_ALBUM` ajoute des pages
 * rendues par rendre.php, à l'adresse exacte qu'elles ont sur le site.
 *
 * Écoute sur 127.0.0.1:8199 (port modifiable par PORT).
 */
import { createServer } from 'node:http';
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const racine = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const dossierPublic = racine + '/public';
const sortie = process.env.CAPTURES || '/tmp';
const port   = Number(process.env.PORT || 8199);

const pages = {};

// Pages du formulaire d'envoi : facultatives (le contrôle des albums n'en a
// pas besoin). Une page absente répond 404, comme avant.
for (const [adresse, variable, defaut] of [
    ['/admin/videos/nouvelle', 'PAGE_VIDEO', '/tmp/video.html'],
    ['/admin/documents/nouveau', 'PAGE_DOCUMENT', '/tmp/document.html'],
]) {
    try {
        pages[adresse] = readFileSync(process.env[variable] || defaut, 'utf8');
    } catch (e) {
        // Page non rendue : elle ne sera pas servie.
    }
}

// Page « Envoyer des fichiers » : optionnelle (elle n'est utile qu'au contrôle
// des deux boutons de la liste des fichiers reçus).
try {
    pages['/admin/envoyer'] = readFileSync(process.env.PAGE_ENVOYER || '/tmp/envoyer.html', 'utf8');
} catch (e) {
    // Pas de page rendue : l'adresse répondra 404, comme avant.
}

// Pages d'album (contrôle 1.27.0) : une carte adresse → fichier rendu.
try {
    const carte = JSON.parse(process.env.PAGES_ALBUM || '{}');
    for (const [adresse, fichier] of Object.entries(carte)) {
        pages[adresse] = readFileSync(String(fichier), 'utf8');
    }
} catch (e) {
    // Pas de pages d'album : le contrôle des albums ne sera pas joué.
}

// Feuille /theme.css rendue par le site : la palette est un réglage, elle ne
// peut pas être servie depuis public/ (le fichier n'y existe pas).
let themeCss = process.env.THEME_CSS ? null : null;
try {
    if (process.env.THEME_CSS) {
        themeCss = readFileSync(process.env.THEME_CSS, 'utf8');
    }
} catch (e) {
    themeCss = null;
}

const morceauOctets = 1048576;
const token = 'a'.repeat(32);

// Un champ d'un corps multipart (les formulaires du script d'envoi en sont).
function champ(corps, nom) {
    const trouve = String(corps).match(new RegExp('name="' + nom + '"\\r\\n\\r\\n([\\s\\S]*?)\\r\\n--'));
    return trouve ? trouve[1] : '';
}

// Réponse « init » calculée sur le fichier réellement déposé : c'est ce qui
// permet de vérifier que le navigateur envoie bien l'album demandé.
function envoiDepuisCorps(corps) {
    const nom = champ(corps, 'nom') || 'film.mp4';
    const taille = Number(champ(corps, 'taille')) || 65536;
    const album = champ(corps, 'album');
    const morceaux = Math.max(1, Math.ceil(taille / morceauOctets));
    return {
        token, nom, taille, morceau: morceauOctets, morceaux,
        recus: [], manquants: Array.from({ length: morceaux }, (un, i) => i),
        complet: false, etat: 'en_cours', album: album === '' ? null : Number(album),
    };
}

const types = {
    '.css': 'text/css', '.js': 'application/javascript', '.svg': 'image/svg+xml',
    '.woff2': 'font/woff2', '.woff': 'font/woff', '.png': 'image/png', '.ico': 'image/x-icon',
};

function json(reponse, donnees, code = 200) {
    const corps = JSON.stringify(donnees);
    reponse.writeHead(code, { 'content-type': 'application/json', 'content-length': Buffer.byteLength(corps) });
    reponse.end(corps);
}

createServer((requete, reponse) => {
    const chemin = new URL(requete.url, 'http://localhost').pathname;
    let corps = '';
    requete.on('data', (bloc) => { corps += bloc; });
    requete.on('end', () => {
        // --- Protocole d'envoi par morceaux (réponses du site) -------------
        if (chemin === '/admin/envoyer/init') {
            // On note ce qui est demandé : le contrôle vérifie que l'album est
            // bien transmis par le navigateur.
            writeFileSync(sortie + '/capture-envoi-init.txt', corps);
            return json(reponse, { ok: true, envoi: envoiDepuisCorps(corps) });
        }
        if (chemin === '/admin/envoyer/morceau') {
            return json(reponse, { ok: true, recu: true, deja: false, numero: 0 });
        }
        if (chemin === '/admin/envoyer/etat') {
            const envoi = envoiDepuisCorps(corps);
            return json(reponse, {
                ok: true,
                envoi: { token: envoi.token, morceaux: envoi.morceaux, morceau: envoi.morceau,
                    recus: [], complet: false, etat: 'en_cours' },
            });
        }
        if (chemin === '/admin/envoyer/terminer') {
            const envoi = envoiDepuisCorps(corps);
            const extension = envoi.nom.includes('.') ? envoi.nom.split('.').pop().toLowerCase() : '';
            const photo = ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(extension);
            writeFileSync(sortie + '/capture-envoi-terminer.txt', corps);
            return json(reponse, {
                ok: true, etat: 'termine',
                envoi: {
                    token: envoi.token, taille: envoi.taille, original_name: envoi.nom,
                    kind: photo ? 'photo' : 'video',
                    mime: photo ? 'image/png' : 'video/mp4', ext: extension,
                },
            });
        }
        if (chemin === '/admin/envoyer/annuler' || chemin === '/admin/envoyer/decision') {
            return json(reponse, { ok: true, etat: 'annule' });
        }

        // --- Les deux actions de la liste des fichiers reçus --------------
        // On note ce qui est posté, puis on renvoie vers la page : c'est le
        // serveur PHP qui décidera, ici on ne vérifie que le navigateur.
        if (requete.method === 'POST'
            && (chemin === '/admin/envoyer/creer-document' || chemin === '/admin/envoyer/supprimer')) {
            writeFileSync(sortie + '/capture-action' + (chemin.endsWith('supprimer') ? '-supprimer' : '-creer') + '.txt', corps);
            reponse.writeHead(303, { location: '/admin/envoyer' });
            return reponse.end('');
        }

        // --- Formulaires de la page « Ajouter des photos » ----------------
        // Titre/date et « Retirer de l'album » : formulaires ordinaires, on
        // note ce qui est posté puis on revient sur la page, comme le site.
        if (requete.method === 'POST'
            && /^\/admin\/albums\/\d+\/photos\/(titre|retirer)$/.test(chemin)) {
            const fin = chemin.endsWith('retirer') ? 'album-retirer' : 'album-titre';
            writeFileSync(sortie + '/capture-' + fin + '.txt', corps);
            reponse.writeHead(303, { location: chemin.replace(/\/(titre|retirer)$/, '') });
            return reponse.end('');
        }

        // --- Enregistrement du formulaire : on note ce qui est posté -------
        if (requete.method === 'POST' && (chemin in pages)) {
            const nom = chemin.includes('documents') ? 'document' : 'video';
            writeFileSync(sortie + '/capture-' + nom + '.txt', corps);
            reponse.writeHead(303, { location: '/admin/videos' });
            return reponse.end('');
        }

        if (chemin in pages) {
            reponse.writeHead(200, { 'content-type': 'text/html; charset=utf-8' });
            return reponse.end(pages[chemin]);
        }

        // --- Feuille de style du thème, rendue par le site -----------------
        if (chemin === '/theme.css' && themeCss !== null) {
            reponse.writeHead(200, { 'content-type': 'text/css; charset=utf-8' });
            return reponse.end(themeCss);
        }

        // --- Fichiers statiques du site -----------------------------------
        if (chemin.startsWith('/assets/') || chemin === '/theme.css' || chemin === '/favicon.ico') {
            try {
                const donnees = readFileSync(dossierPublic + chemin);
                const extension = chemin.slice(chemin.lastIndexOf('.'));
                reponse.writeHead(200, { 'content-type': types[extension] || 'application/octet-stream' });
                return reponse.end(donnees);
            } catch (e) {
                reponse.writeHead(404);
                return reponse.end('');
            }
        }

        reponse.writeHead(404, { 'content-type': 'text/plain' });
        reponse.end('introuvable');
    });
}).listen(port, '127.0.0.1', () => console.log('serveur d’essai sur ' + port));
