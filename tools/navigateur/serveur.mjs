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

const pages = {
    '/admin/videos/nouvelle': readFileSync(process.env.PAGE_VIDEO || '/tmp/video.html', 'utf8'),
    '/admin/documents/nouveau': readFileSync(process.env.PAGE_DOCUMENT || '/tmp/document.html', 'utf8'),
};

// Page « Envoyer des fichiers » : optionnelle (elle n'est utile qu'au contrôle
// des deux boutons de la liste des fichiers reçus).
try {
    pages['/admin/envoyer'] = readFileSync(process.env.PAGE_ENVOYER || '/tmp/envoyer.html', 'utf8');
} catch (e) {
    // Pas de page rendue : l'adresse répondra 404, comme avant.
}

const morceauOctets = 1048576;
const token = 'a'.repeat(32);

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
            return json(reponse, {
                ok: true,
                envoi: {
                    token, nom: 'film.mp4', taille: 65536, morceau: morceauOctets,
                    morceaux: 1, recus: [], manquants: [0], complet: false, etat: 'en_cours',
                },
            });
        }
        if (chemin === '/admin/envoyer/morceau') {
            return json(reponse, { ok: true, recu: true, deja: false, numero: 0 });
        }
        if (chemin === '/admin/envoyer/etat') {
            return json(reponse, {
                ok: true,
                envoi: { token, morceaux: 1, morceau: morceauOctets, recus: [], complet: false, etat: 'en_cours' },
            });
        }
        if (chemin === '/admin/envoyer/terminer') {
            return json(reponse, {
                ok: true, etat: 'termine',
                envoi: { token, taille: 65536, original_name: 'film.mp4', kind: 'video', mime: 'video/mp4', ext: 'mp4' },
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
