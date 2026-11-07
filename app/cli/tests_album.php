<?php
/**
 * Essais — « Ajouter des photos » directement dans un album (1.27.0).
 *
 * Ce que l'on vérifie ici :
 *   - les boutons : présents sur la page de l'album (administration et page
 *     publique) pour un administrateur, jamais pour un visiteur, un lecteur ou
 *     un journaliste ;
 *   - l'envoi : un fichier déposé devient une photo DE CET album, à la fin,
 *     avec la visibilité de l'album et le titre tiré du nom du fichier ;
 *   - les doublons : signalés comme ailleurs, jamais supprimés, et « Garder
 *     quand même » ajoute bien la photo à l'album ;
 *   - les droits : administrateur + jeton CSRF, album vérifié, photo qui doit
 *     être dans l'album pour être corrigée ou retirée ;
 *   - la visibilité : une photo privée reste invisible dans un album public ;
 *   - le double clic : un seul document par fichier reçu.
 *
 * Tout se passe dans une transaction annulée (la base est rendue intacte) et
 * les fichiers d'essai sont effacés à la fin.
 */

declare(strict_types=1);

/** Album d'essai, supprimé par le rollback. */
function tests_album_creer(string $titre, string $visibilite = 'public'): array
{
    $id = db_insert('albums', [
        'title'             => $titre,
        'slug'              => 'essai-album-' . bin2hex(random_bytes(5)),
        'description'       => null,
        'cover_document_id' => null,
        'visibility'        => $visibilite,
        'position'          => 0,
        'created_by'        => null,
        'created_at'        => time(),
        'updated_at'        => time(),
    ]);

    return find_album($id) ?? [];
}

/**
 * Photo d'essai : un vrai PNG (1 × 1) complété de données aléatoires.
 *
 * Les octets aléatoires donnent une empreinte unique à chaque fichier (sauf
 * quand on veut justement deux fois le même) et dépassent la taille d'un
 * morceau, pour que l'assemblage soit vraiment exercé.
 */
function tests_album_photo(int $octets = 200_000): string
{
    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    );

    $chemin = storage_path('tmp') . '/essai-photo-' . bin2hex(random_bytes(6)) . '.png';
    file_put_contents($chemin, $png . random_bytes(max(1, $octets - strlen($png))));

    return $chemin;
}

/** Joue le protocole d'envoi avec une destination « album ». */
function tests_album_jouer(
    int $compte,
    string $nom,
    string $chemin,
    int $albumId,
    array $morceaux = [],
    bool $terminer = true
): array {
    return tests_envoi_jouer($compte, $nom, $chemin, $morceaux, true, $terminer, $albumId);
}

/** Toute la famille « album ». */
function tests_album(): void
{
    echo "Albums — ajouter des photos directement (envoi par morceaux)\n";

    $admin       = tests_envoi_compte('admin');
    $autreAdmin  = tests_envoi_compte('admin');
    $branche     = tests_profils_branche('Essai album');
    $lecteur     = tests_profils_compte('branche', $branche);
    $journaliste = tests_profils_compte('personne');

    // Morceaux d'un mégaoctet : les essais restent rapides.
    $morceauAvant = (string) site_setting('envoi_morceau_mo');
    save_site_setting('envoi_morceau_mo', '1');

    $avantDossiers = tests_envoi_dossiers();
    $avantFichiers = tests_envoi_fichiers();

    try {
        db_transaction(static function () use ($admin, $autreAdmin, $lecteur, $journaliste): void {
            tests_album_pages($admin, $autreAdmin, $lecteur, $journaliste);
            tests_album_envoi($admin);
            tests_album_doublons($admin);
            tests_album_droits($admin, $autreAdmin, $lecteur, $journaliste);
            tests_album_correction($admin);
            tests_album_visibilite($admin);
            tests_album_documents_existants($admin, $autreAdmin, $lecteur, $journaliste);

            // Tout est annulé : la base du site est rendue intacte.
            throw new RuntimeException('fin du test, annulation');
        });
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'fin du test, annulation') {
            throw $e;
        }
    } finally {
        save_site_setting('envoi_morceau_mo', $morceauAvant);

        foreach (array_diff(tests_envoi_dossiers(), $avantDossiers) as $dossier) {
            foreach (glob($dossier . '/*') ?: [] as $fichier) {
                if (is_file($fichier)) {
                    @unlink($fichier);
                }
            }
            @rmdir($dossier);
        }
        foreach (array_diff(tests_envoi_fichiers(), $avantFichiers) as $fichier) {
            @unlink($fichier);
        }
    }
}

/** 1. Le bouton « Ajouter des photos » et la page d'ajout. */
function tests_album_pages(int $admin, int $autre, int $lecteur, int $journaliste): void
{
    $album = tests_album_creer('Album des essais', 'public');
    $id    = (int) ($album['id'] ?? 0);
    $slug  = (string) ($album['slug'] ?? '');

    verifier('album : un album d’essai a été créé', $id > 0 && $slug !== '');

    $formulaire = tests_envoi_requete('GET', '/admin/albums/' . $id . '/modifier', $admin);
    verifier(
        'album : la page de modification propose « Ajouter des photos »',
        $formulaire['code'] === 200
            && str_contains($formulaire['corps'], 'Ajouter des photos')
            && str_contains($formulaire['corps'], '/admin/albums/' . $id . '/photos'),
        (string) $formulaire['code']
    );

    $publique = tests_envoi_requete('GET', '/albums/' . $slug, $admin);
    verifier(
        'album : la page publique propose « Ajouter des photos » à un administrateur',
        $publique['code'] === 200
            && str_contains($publique['corps'], 'Ajouter des photos')
            && str_contains($publique['corps'], '/admin/albums/' . $id . '/photos'),
        (string) $publique['code']
    );

    foreach (['lecteur' => $lecteur, 'journaliste' => $journaliste] as $nom => $compte) {
        $page = tests_envoi_requete('GET', '/albums/' . $slug, $compte);
        verifier(
            'album : aucun bouton d’ajout pour un ' . $nom,
            $page['code'] === 200 && !str_contains($page['corps'], 'Ajouter des photos'),
            (string) $page['code']
        );
    }

    $visiteur = tests_envoi_requete('GET', '/albums/' . $slug, null);
    verifier(
        'album : aucun bouton d’ajout pour un visiteur',
        $visiteur['code'] === 200 && !str_contains($visiteur['corps'], 'Ajouter des photos'),
        (string) $visiteur['code']
    );

    $page = tests_envoi_requete('GET', '/admin/albums/' . $id . '/photos', $admin);
    verifier('album : la page d’ajout s’ouvre à un administrateur', $page['code'] === 200, (string) $page['code']);
    verifier_contient('album : la page propose de déposer des photos', 'Déposer des photos', $page['corps']);
    verifier_contient('album : le bloc d’envoi vise cet album', 'data-envoi-album="' . $id . '"', $page['corps']);
    verifier(
        'album : le sélecteur de fichiers n’accepte que des photos',
        str_contains($page['corps'], 'accept=".jpg,.jpeg,.png,.webp,.gif"'),
        'accept attendu : .jpg,.jpeg,.png,.webp,.gif'
    );
    verifier_contient('album : un lien « Voir l’album » est là', 'Voir l’album', $page['corps']);
    verifier(
        'album : la page d’ajout n’a ni style ni script en ligne',
        !str_contains($page['corps'], ' style=') && !str_contains($page['corps'], '<script>'),
        ''
    );

    $pageLecteur = tests_envoi_requete('GET', '/admin/albums/' . $id . '/photos', $lecteur);
    verifier('album : la page d’ajout est fermée à un lecteur (403)', $pageLecteur['code'] === 403, (string) $pageLecteur['code']);

    $pageJournaliste = tests_envoi_requete('GET', '/admin/albums/' . $id . '/photos', $journaliste);
    verifier(
        'album : la page d’ajout est fermée à un journaliste (403)',
        $pageJournaliste['code'] === 403,
        (string) $pageJournaliste['code']
    );

    $pageVisiteur = tests_envoi_requete('GET', '/admin/albums/' . $id . '/photos', null);
    verifier(
        'album : la page d’ajout renvoie un visiteur vers la connexion',
        $pageVisiteur['code'] === 303 && str_contains($pageVisiteur['location'], '/connexion'),
        $pageVisiteur['code'] . ' ' . $pageVisiteur['location']
    );

    $inconnu = tests_envoi_requete('GET', '/admin/albums/999999/photos', $admin);
    verifier('album : une page d’ajout pour un album inconnu répond 404', $inconnu['code'] === 404, (string) $inconnu['code']);
}

/** 2. L'envoi : une photo de plus dans l'album, sans autre formulaire. */
function tests_album_envoi(int $admin): void
{
    $album = tests_album_creer('Vacances 1965', 'public');
    $id    = (int) ($album['id'] ?? 0);

    $photo    = tests_album_photo(200_000);
    $resultat = tests_album_jouer($admin, 'plage-de-1965.png', $photo, $id);
    @unlink($photo);

    $envoi = $resultat['envoi'] ?? null;
    verifier(
        'envoi album : l’envoi garde l’album de destination',
        is_array($envoi) && (int) ($envoi['album_id'] ?? 0) === $id && (string) $envoi['state'] === 'termine',
        (string) ($envoi['state'] ?? '—')
    );

    $doc = is_array($envoi) ? db_one('SELECT * FROM documents WHERE rel_path = ?', [(string) $envoi['rel_path']]) : null;
    verifier(
        'envoi album : la photo devient un document de type photo',
        $doc !== null && (string) $doc['kind'] === 'photo',
        (string) ($doc['kind'] ?? '—')
    );
    verifier(
        'envoi album : le titre est le nom du fichier sans extension',
        $doc !== null && (string) $doc['title'] === 'plage-de-1965',
        (string) ($doc['title'] ?? '—')
    );
    verifier(
        'envoi album : la photo prend la visibilité de l’album',
        $doc !== null && (string) $doc['visibility'] === 'public',
        (string) ($doc['visibility'] ?? '—')
    );
    verifier(
        'envoi album : la photo est reconnue (dimensions lues)',
        $doc !== null && (int) $doc['width'] === 1 && (int) $doc['height'] === 1,
        (int) ($doc['width'] ?? 0) . ' × ' . (int) ($doc['height'] ?? 0)
    );
    verifier(
        'envoi album : l’envoi est rattaché à la photo',
        is_array($envoi) && $doc !== null && (int) $envoi['document_id'] === (int) $doc['id'],
        (int) ($envoi['document_id'] ?? 0) . ' / ' . (int) ($doc['id'] ?? 0)
    );
    verifier(
        'envoi album : la photo est ajoutée à la fin de l’album',
        $doc !== null && album_photo_ids($id) === [(int) $doc['id']],
        implode(',', album_photo_ids($id))
    );

    // Une deuxième photo arrive après la première, jamais devant.
    $photo2 = tests_album_photo(150_000);
    $second = tests_album_jouer($admin, 'la-mer.png', $photo2, $id);
    @unlink($photo2);
    $doc2 = isset($second['envoi']['document_id'])
        ? db_one('SELECT id FROM documents WHERE id = ?', [(int) $second['envoi']['document_id']])
        : null;
    verifier(
        'envoi album : une deuxième photo vient après la première',
        $doc !== null && $doc2 !== null && album_photo_ids($id) === [(int) $doc['id'], (int) $doc2['id']],
        implode(',', album_photo_ids($id))
    );

    // Ce que voit le visiteur : une photo publique dans un album public.
    $page = tests_envoi_requete('GET', '/albums/' . (string) $album['slug'], null);
    verifier(
        'envoi album : le visiteur voit la photo publique de l’album public',
        $page['code'] === 200
            && str_contains($page['corps'], 'plage-de-1965')
            && str_contains($page['corps'], 'la-mer'),
        (string) $page['code']
    );

    // La page d'ajout liste les photos, avec de quoi corriger titre et date.
    $liste = tests_envoi_requete('GET', '/admin/albums/' . $id . '/photos', $admin);
    verifier(
        'envoi album : la liste des photos ajoutées est là, avec ses formulaires',
        str_contains($liste['corps'], 'plage-de-1965')
            && str_contains($liste['corps'], 'photos/titre')
            && str_contains($liste['corps'], 'photos/retirer')
            && str_contains($liste['corps'], 'id="photos-ajoutees"'),
        (string) $liste['code']
    );
}

/** 3. Les doublons : signalés, jamais supprimés, « Garder » les range dans l'album. */
function tests_album_doublons(int $admin): void
{
    $album = tests_album_creer('Album des doublons', 'famille');
    $id    = (int) ($album['id'] ?? 0);

    $photo = tests_album_photo(120_000);
    $un     = tests_album_jouer($admin, 'fete.png', $photo, $id);
    $deux   = tests_album_jouer($admin, 'fete-copie.png', $photo, $id);
    $trois  = tests_album_jouer($admin, 'fete-bis.png', $photo, $id);
    @unlink($photo);

    verifier(
        'doublon album : le même fichier est signalé, pas supprimé',
        ($deux['terminer']['etat'] ?? '') === 'doublon'
            && (int) ($deux['terminer']['doublon']['id'] ?? 0) > 0
            && ($trois['terminer']['etat'] ?? '') === 'doublon',
        (string) ($deux['terminer']['etat'] ?? '—')
    );
    verifier(
        'doublon album : aucune photo ajoutée tant que la personne n’a pas choisi',
        count(album_photo_ids($id)) === 1,
        (string) count(album_photo_ids($id))
    );

    $garde = is_array($deux['envoi'] ?? null) ? envoi_decider($deux['envoi'], 'garder') : [];
    verifier(
        'doublon album : « Garder quand même » crée la photo et l’ajoute à l’album',
        ($garde['etat'] ?? '') === 'termine' && count(album_photo_ids($id)) === 2,
        (string) ($garde['etat'] ?? '—') . ' — ' . count(album_photo_ids($id)) . ' photo(s)'
    );

    $ignore = is_array($trois['envoi'] ?? null) ? envoi_decider($trois['envoi'], 'ignorer') : [];
    verifier(
        'doublon album : « Ignorer » n’ajoute rien et ne touche pas aux photos existantes',
        ($ignore['etat'] ?? '') === 'annule'
            && count(album_photo_ids($id)) === 2
            && (int) db_value('SELECT COUNT(*) FROM documents WHERE sha256 = ?', [(string) $un['envoi']['sha256']], -1) === 2,
        (string) ($ignore['etat'] ?? '—')
    );
}

/** 4. Droits : administrateur, jeton CSRF, album vérifié, liste par compte. */
function tests_album_droits(int $admin, int $autre, int $lecteur, int $journaliste): void
{
    $album = tests_album_creer('Album des droits', 'public');
    $id    = (int) ($album['id'] ?? 0);

    $commande = static fn (string $nom): array => [
        'nom'    => $nom,
        'taille' => '20000',
        'type'   => 'image/png',
        'album'  => (string) $id,
    ];

    $visiteur = tests_envoi_requete('POST', '/admin/envoyer/init', null, $commande('a.png'), [
        'X-Requested-With' => 'XMLHttpRequest',
    ]);
    verifier('droits album : un visiteur ne peut pas déposer (401)', $visiteur['code'] === 401, (string) $visiteur['code']);

    $_SESSION = ['account_id' => $lecteur];
    $jetonLecteur = csrf_token();
    $refusLecteur = tests_envoi_requete('POST', '/admin/envoyer/init', $lecteur, $commande('a.png') + ['_csrf' => $jetonLecteur], [
        'X-Requested-With' => 'XMLHttpRequest',
    ]);
    verifier('droits album : un lecteur ne peut pas déposer (403)', $refusLecteur['code'] === 403, (string) $refusLecteur['code']);

    $_SESSION = ['account_id' => $journaliste];
    $jetonJournaliste = csrf_token();
    $refusJournaliste = tests_envoi_requete('POST', '/admin/envoyer/init', $journaliste, $commande('a.png') + ['_csrf' => $jetonJournaliste], [
        'X-Requested-With' => 'XMLHttpRequest',
    ]);
    verifier(
        'droits album : un journaliste ne peut pas ranger dans un album (403)',
        $refusJournaliste['code'] === 403,
        (string) $refusJournaliste['code']
    );

    $sansJeton = tests_envoi_requete('POST', '/admin/envoyer/init', $admin, $commande('a.png'), [
        'X-Requested-With' => 'XMLHttpRequest',
    ]);
    verifier('droits album : sans jeton CSRF, rien ne commence (403)', $sansJeton['code'] === 403, (string) $sansJeton['code']);

    $_SESSION = ['account_id' => $admin];
    $jeton = csrf_token();

    $albumInconnu = tests_envoi_requete('POST', '/admin/envoyer/init', $admin, [
        '_csrf' => $jeton, 'nom' => 'a.png', 'taille' => '20000', 'album' => '999999',
    ], ['X-Requested-With' => 'XMLHttpRequest']);
    $jsonInconnu = tests_envoi_json($albumInconnu['corps']);
    verifier(
        'droits album : un album inconnu est refusé, avec un message clair',
        $albumInconnu['code'] === 400 && ($jsonInconnu['code'] ?? '') === 'album',
        $albumInconnu['corps']
    );

    $_SESSION = ['account_id' => $admin];
    $jeton = csrf_token();
    $pasUnePhoto = tests_envoi_requete('POST', '/admin/envoyer/init', $admin, [
        '_csrf' => $jeton, 'nom' => 'lettre.txt', 'taille' => '20000', 'album' => (string) $id,
    ], ['X-Requested-With' => 'XMLHttpRequest']);
    $jsonTexte = tests_envoi_json($pasUnePhoto['corps']);
    verifier(
        'droits album : un fichier qui n’est pas une photo est refusé',
        $pasUnePhoto['code'] === 400 && str_contains((string) ($jsonTexte['erreur'] ?? ''), 'photos'),
        $pasUnePhoto['corps']
    );

    // La liste « photos ajoutées » est celle du compte connecté.
    $photo = tests_album_photo(100_000);
    tests_album_jouer($admin, 'photo-du-compte.png', $photo, $id);
    @unlink($photo);

    $pageAdmin = tests_envoi_requete('GET', '/admin/albums/' . $id . '/photos', $admin);
    verifier_contient('droits album : l’expéditeur retrouve sa photo dans la liste', 'photo-du-compte', $pageAdmin['corps']);

    $pageAutre = tests_envoi_requete('GET', '/admin/albums/' . $id . '/photos', $autre);
    verifier(
        'droits album : un autre administrateur ne voit pas la liste de l’expéditeur',
        $pageAutre['code'] === 200 && !str_contains($pageAutre['corps'], 'photo-du-compte'),
        (string) $pageAutre['code']
    );

    // Corriger une photo qui n'est pas dans cet album : refus.
    $horsAlbum = db_insert('documents', [
        'title'         => 'Photo hors album',
        'description'   => null,
        'doc_date'      => null,
        'doc_date_sort' => null,
        'category_id'   => null,
        'kind'          => 'photo',
        'visibility'    => 'prive',
        'rel_path'      => 'medias/2026/11/hors-album.jpg',
        'thumb_path'    => null,
        'original_name' => 'hors-album.jpg',
        'mime'          => 'image/jpeg',
        'ext'           => 'jpg',
        'size'          => 100,
        'width'         => null,
        'height'        => null,
        'sha256'        => str_repeat('f', 64),
        'is_attachment' => 0,
        'created_by'    => $admin,
        'created_at'    => time(),
        'updated_at'    => time(),
    ]);

    $_SESSION = ['account_id' => $admin];
    $jeton = csrf_token();
    $refusTitre = tests_envoi_requete('POST', '/admin/albums/' . $id . '/photos/titre', $admin, [
        '_csrf' => $jeton, 'document_id' => (string) $horsAlbum, 'title' => 'Piraté',
    ]);
    $titreApres = (string) db_value('SELECT title FROM documents WHERE id = ?', [$horsAlbum], '—');
    verifier(
        'droits album : on ne corrige pas une photo qui n’est pas dans l’album',
        $refusTitre['code'] === 303 && $titreApres === 'Photo hors album',
        $refusTitre['code'] . ' — ' . $titreApres
    );

    // Sans jeton CSRF, ni le titre ni le retrait ne passent.
    $avant = count(album_photo_ids($id));
    $sansJetonTitre = tests_envoi_requete('POST', '/admin/albums/' . $id . '/photos/titre', $admin, [
        'document_id' => (string) $horsAlbum, 'title' => 'Piraté',
    ]);
    $sansJetonRetrait = tests_envoi_requete('POST', '/admin/albums/' . $id . '/photos/retirer', $admin, [
        'document_id' => (string) $horsAlbum,
    ]);
    verifier(
        'droits album : sans jeton CSRF, les deux commandes sont refusées (403)',
        $sansJetonTitre['code'] === 403
            && $sansJetonRetrait['code'] === 403
            && count(album_photo_ids($id)) === $avant,
        $sansJetonTitre['code'] . ' / ' . $sansJetonRetrait['code']
    );
}

/** 5. Corriger le titre et la date, puis retirer de l'album (sans supprimer). */
function tests_album_correction(int $admin): void
{
    $album = tests_album_creer('Album à corriger', 'public');
    $id    = (int) ($album['id'] ?? 0);

    $photo = tests_album_photo(90_000);
    $envoi = tests_album_jouer($admin, 'a-corriger.png', $photo, $id);
    @unlink($photo);

    $docId = (int) ($envoi['envoi']['document_id'] ?? 0);
    verifier('correction album : la photo d’essai existe', $docId > 0);

    $_SESSION = ['account_id' => $admin];
    $jeton = csrf_token();
    $maj = tests_envoi_requete('POST', '/admin/albums/' . $id . '/photos/titre', $admin, [
        '_csrf'       => $jeton,
        'document_id' => (string) $docId,
        'title'       => 'La plage de 1965',
        'doc_date'    => '1965',
    ]);
    $doc = db_one('SELECT * FROM documents WHERE id = ?', [$docId]);
    verifier(
        'correction album : le titre et la date se corrigent depuis la page',
        $doc !== null
            && (string) $doc['title'] === 'La plage de 1965'
            && (string) $doc['doc_date'] === '1965'
            && (int) $doc['doc_date_sort'] === 1965
            && $maj['code'] === 303
            && $maj['location'] === '/admin/albums/' . $id . '/photos',
        $maj['code'] . ' ' . $maj['location'] . ' — ' . (string) ($doc['title'] ?? '—')
    );

    $vide = tests_envoi_requete('POST', '/admin/albums/' . $id . '/photos/titre', $admin, [
        '_csrf' => $jeton, 'document_id' => (string) $docId, 'title' => '   ',
    ]);
    $garde = (string) db_value('SELECT title FROM documents WHERE id = ?', [$docId], '—');
    verifier(
        'correction album : un titre vide laisse le titre existant',
        $vide['code'] === 303 && $garde === 'La plage de 1965',
        $garde
    );

    // La photo devient la couverture : le retrait doit aussi l'oublier.
    db_update('albums', $id, ['cover_document_id' => $docId]);
    $fichier = storage_absolute((string) ($doc['rel_path'] ?? ''));

    $_SESSION = ['account_id' => $admin];
    $jeton = csrf_token();
    $retrait = tests_envoi_requete('POST', '/admin/albums/' . $id . '/photos/retirer', $admin, [
        '_csrf' => $jeton, 'document_id' => (string) $docId,
    ]);
    $apres = find_album($id);
    verifier(
        'retrait album : « Retirer de l’album » ne supprime pas le document',
        $retrait['code'] === 303
            && album_photo_ids($id) === []
            && db_one('SELECT id FROM documents WHERE id = ?', [$docId]) !== null
            && is_file($fichier),
        $retrait['code'] . ' — album : ' . implode(',', album_photo_ids($id))
    );
    verifier(
        'retrait album : la couverture est oubliée quand sa photo quitte l’album',
        $apres !== null && $apres['cover_document_id'] === null,
        (string) ($apres['cover_document_id'] ?? '—')
    );

    // Depuis la 1.30.0, la page propose aussi les documents qui NE SONT PAS
    // dans l'album : la photo retirée y reparaît donc, comme candidate. On
    // regarde la liste des photos ajoutées, celle que le retrait doit vider.
    $liste = tests_envoi_requete('GET', '/admin/albums/' . $id . '/photos', $admin);
    $debutListe = strpos((string) $liste['corps'], 'id="photos-ajoutees"');
    $sectionListe = $debutListe === false ? '' : substr((string) $liste['corps'], $debutListe);
    verifier(
        'retrait album : la photo retirée quitte la liste de la page',
        $sectionListe !== '' && !str_contains($sectionListe, 'La plage de 1965'),
        (string) $liste['code']
    );
}

/**
 * 7. Verser dans l'album des documents déjà présents (1.30.0).
 *
 * Ce qui est vérifié : la section et ses filtres, l'ajout multiple dans l'ordre
 * de la sélection, en fin de liste ; les doublons et les identifiants inconnus
 * ignorés sans erreur ; la visibilité des documents conservée (un document
 * privé reste invisible pour un lecteur, même dans un album famille) ;
 * l'avertissement après l'ajout et l'action séparée qui donne la visibilité de
 * l'album ; les droits et le jeton CSRF.
 */
function tests_album_document(string $titre, string $visibilite = 'public', string $kind = 'photo'): int
{
    return db_insert('documents', [
        'title'         => $titre,
        'description'   => null,
        'doc_date'      => null,
        'doc_date_sort' => null,
        'category_id'   => null,
        'kind'          => $kind,
        'visibility'    => $visibilite,
        'rel_path'      => 'medias/2026/11/essai-' . bin2hex(random_bytes(4)) . '.jpg',
        'thumb_path'    => null,
        'original_name' => 'essai.jpg',
        'mime'          => 'image/jpeg',
        'ext'           => 'jpg',
        'size'          => 100,
        'width'         => null,
        'height'        => null,
        'sha256'        => str_repeat('e', 64),
        'is_attachment' => 0,
        'created_by'    => null,
        'created_at'    => time(),
        'updated_at'    => time(),
    ]);
}

/** 7. Documents déjà présents : la page, l'ajout, la visibilité, les droits. */
function tests_album_documents_existants(int $admin, int $autre, int $lecteur, int $journaliste): void
{
    $album = tests_album_creer('Album documents existants', 'famille');
    $id    = (int) ($album['id'] ?? 0);
    $slug  = (string) ($album['slug'] ?? '');

    // Trois documents d'essai : deux publics, un privé.
    $public1 = tests_album_document('Déjà là 1 ' . bin2hex(random_bytes(2)), 'public');
    $public2 = tests_album_document('Déjà là 2 ' . bin2hex(random_bytes(2)), 'public');
    $prive   = tests_album_document('Restée privée ' . bin2hex(random_bytes(2)), 'prive');

    // Un document déjà dans l'album, pour l'ordre et les doublons.
    $dedans = tests_album_document('Déjà dans l’album ' . bin2hex(random_bytes(2)), 'public');
    album_ajouter_documents($id, [$dedans]);

    // --- 1. La page : section, filtres, grille, badges -----------------------
    $page = tests_envoi_requete('GET', '/admin/albums/' . $id . '/photos', $admin);
    verifier(
        'documents existants : la section « Ajouter depuis les documents du site » est là',
        $page['code'] === 200
            && str_contains($page['corps'], 'Ajouter depuis les documents du site')
            && str_contains($page['corps'], 'id="ajouter-documents"'),
        (string) $page['code']
    );
    verifier(
        'documents existants : une grille de vignettes à cocher',
        str_contains($page['corps'], 'class="grille-selection"')
            && str_contains($page['corps'], 'name="documents[]"')
            && str_contains($page['corps'], 'class="choix-document__case"')
    );
    verifier(
        'documents existants : chaque élément porte un badge de visibilité',
        str_contains($page['corps'], 'badge badge-public')
            && str_contains($page['corps'], 'badge badge-prive')
    );
    verifier(
        'documents existants : les filtres sont un formulaire GET, sans JavaScript',
        str_contains($page['corps'], '<form method="get" action="/admin/albums/' . $id . '/photos"')
            && str_contains($page['corps'], 'name="filtres"') === false
            && str_contains($page['corps'], 'id="f-pas-album"')
            && str_contains($page['corps'], 'name="visibilite"')
            && str_contains($page['corps'], 'name="categorie"')
    );
    verifier(
        'documents existants : « pas encore dans cet album » est coché par défaut',
        preg_match('/id="f-pas-album" name="pas_album" value="1"\s+checked/', $page['corps']) === 1,
        'case non cochée par défaut'
    );
    verifier(
        'documents existants : aucun style ni script en ligne',
        !str_contains($page['corps'], ' style=') && !str_contains($page['corps'], '<script>')
    );

    // Décochée, la case montre aussi ceux qui sont déjà dans l'album.
    $tous = tests_envoi_requete('GET', '/admin/albums/' . $id . '/photos?type=&pas_album=0', $admin);
    verifier(
        'documents existants : la case décochée montre aussi les documents déjà dans l’album',
        str_contains($tous['corps'], 'Déjà dans l’album')
            && str_contains($tous['corps'], 'class="choix-document choix-document--dedans"'),
        'aucun élément marqué « déjà dans l’album »'
    );
    verifier(
        'documents existants : un document déjà dans l’album n’est pas cochable',
        preg_match('/<input type="checkbox" disabled>/', $tous['corps']) === 1,
        'la case reste active'
    );

    // Recherche et filtre de visibilité.
    $recherche = tests_envoi_requete('GET', '/admin/albums/' . $id . '/photos?q=Rest%C3%A9e', $admin);
    verifier(
        'documents existants : la recherche par titre filtre la grille',
        str_contains($recherche['corps'], 'Restée privée')
            && !str_contains($recherche['corps'], 'Déjà là 1'),
        'la recherche ne filtre pas'
    );

    // --- 2. Un seul clic = un seul ajout, dans l'ordre de la sélection ------
    $_SESSION = ['account_id' => $admin];
    $jeton = csrf_token();
    $ajout = tests_envoi_requete('POST', '/admin/albums/' . $id . '/photos/ajouter', $admin, [
        '_csrf'      => $jeton,
        'documents'  => [(string) $public1, (string) $public2, (string) $prive],
    ]);
    $ordre = array_map(
        static fn (array $ligne): int => (int) $ligne['document_id'],
        db_all('SELECT document_id FROM album_documents WHERE album_id = ? ORDER BY position', [$id])
    );
    verifier(
        'documents existants : trois documents ajoutés en fin de liste, dans l’ordre de la sélection',
        $ordre === [$dedans, $public1, $public2, $prive],
        implode(',', $ordre)
    );
    verifier(
        'documents existants : l’ajout renvoie vers la page, avec les identifiants ajoutés',
        $ajout['code'] === 303
            && str_contains((string) $ajout['location'], '/admin/albums/' . $id . '/photos?ajoutes=')
            && str_contains((string) $ajout['location'], '#ajouter-documents'),
        (string) $ajout['location']
    );
    verifier(
        'documents existants : les documents ajoutés gardent leur visibilité',
        (string) db_value('SELECT visibility FROM documents WHERE id = ?', [$prive], '') === 'prive'
            && (string) db_value('SELECT visibility FROM documents WHERE id = ?', [$public1], '') === 'public'
    );

    // --- 3. Doublons et identifiants inconnus : ignorés, sans erreur --------
    $avant = count(album_photo_ids($id));
    $_SESSION = ['account_id' => $admin];
    $jeton = csrf_token();
    $doublon = tests_envoi_requete('POST', '/admin/albums/' . $id . '/photos/ajouter', $admin, [
        '_csrf'     => $jeton,
        'documents' => [(string) $public1, (string) $prive, '999999'],
    ]);
    verifier(
        'documents existants : les doublons et un identifiant inconnu sont ignorés sans erreur',
        $doublon['code'] === 303 && count(album_photo_ids($id)) === $avant,
        $doublon['code'] . ' — ' . count(album_photo_ids($id)) . ' photo(s)'
    );
    $contenu = implode(' ', array_column($_SESSION['_flash'] ?? [], 'message'));
    verifier(
        'documents existants : le message dit que rien n’a été ajouté',
        str_contains($contenu, 'Aucun document n’a été ajouté') || str_contains($contenu, 'déjà dans l’album'),
        $contenu
    );
    unset($_SESSION['_flash']);

    // --- 4. La visibilité conservée se voit dans l'album -------------------
    $pageLecteur = tests_envoi_requete('GET', '/albums/' . $slug, $lecteur);
    verifier(
        'documents existants : un lecteur voit les documents publics ajoutés à l’album',
        $pageLecteur['code'] === 200 && str_contains($pageLecteur['corps'], 'Déjà là 1'),
        (string) $pageLecteur['code']
    );
    verifier(
        'documents existants : un document privé ajouté à un album famille reste invisible au lecteur',
        !str_contains($pageLecteur['corps'], 'Restée privée'),
        'le document privé apparaît dans l’album pour un lecteur'
    );

    // --- 5. L'avertissement après l'ajout, et l'action séparée -------------
    $apres = tests_envoi_requete(
        'GET',
        '/admin/albums/' . $id . '/photos?ajoutes=' . $prive . '&type=',
        $admin
    );
    verifier(
        'documents existants : après l’ajout, la page dit clairement que la photo reste privée',
        str_contains($apres['corps'], '1 photo reste privée : la famille ne la verra pas dans l’album'),
        'avertissement absent'
    );
    verifier(
        'documents existants : l’avertissement propose « Leur donner la visibilité de l’album »',
        str_contains($apres['corps'], 'Leur donner la visibilité de l’album')
            && str_contains($apres['corps'], '/admin/albums/' . $id . '/photos/visibilite')
            && str_contains($apres['corps'], 'data-confirmation=')
    );
    verifier(
        'documents existants : rien n’est changé automatiquement par l’ajout',
        (string) db_value('SELECT visibility FROM documents WHERE id = ?', [$prive], '') === 'prive'
    );

    // Action séparée : sans jeton, refusée.
    $sansJeton = tests_envoi_requete('POST', '/admin/albums/' . $id . '/photos/visibilite', $admin, [
        'documents' => [(string) $prive],
    ]);
    verifier(
        'documents existants : sans jeton CSRF, la visibilité ne change pas (403)',
        $sansJeton['code'] === 403
            && (string) db_value('SELECT visibility FROM documents WHERE id = ?', [$prive], '') === 'prive',
        (string) $sansJeton['code']
    );

    $_SESSION = ['account_id' => $admin];
    $jeton = csrf_token();
    $donne = tests_envoi_requete('POST', '/admin/albums/' . $id . '/photos/visibilite', $admin, [
        '_csrf'     => $jeton,
        'documents' => [(string) $prive],
    ]);
    verifier(
        'documents existants : l’action donne la visibilité de l’album au document choisi',
        $donne['code'] === 303
            && (string) db_value('SELECT visibility FROM documents WHERE id = ?', [$prive], '') === 'famille',
        (string) db_value('SELECT visibility FROM documents WHERE id = ?', [$prive], '?')
    );
    unset($_SESSION['_flash']);

    $pageLecteur2 = tests_envoi_requete('GET', '/albums/' . $slug, $lecteur);
    verifier(
        'documents existants : le lecteur voit le document une fois la visibilité donnée',
        str_contains($pageLecteur2['corps'], 'Restée privée'),
        'le document reste invisible pour le lecteur'
    );

    // --- 6. « Tout ajouter sur cette page » (sans JavaScript) --------------
    $album2 = tests_album_creer('Album tout ajouter', 'public');
    $id2    = (int) ($album2['id'] ?? 0);
    $_SESSION = ['account_id' => $admin];
    $jeton = csrf_token();
    $tout = tests_envoi_requete('POST', '/admin/albums/' . $id2 . '/photos/ajouter?type=', $admin, [
        '_csrf' => $jeton,
        'tout'  => '1',
    ]);
    $nbAlbum2 = count(album_photo_ids($id2));
    verifier(
        'documents existants : « Tout ajouter sur cette page » range tous les documents affichés',
        $tout['code'] === 303 && $nbAlbum2 > 0,
        $tout['code'] . ' — ' . $nbAlbum2 . ' document(s)'
    );
    unset($_SESSION['_flash']);

    // --- 7. Droits --------------------------------------------------------
    $_SESSION = ['account_id' => $lecteur];
    $jetonLecteur = csrf_token();
    $refusLecteur = tests_envoi_requete('POST', '/admin/albums/' . $id . '/photos/ajouter', $lecteur, [
        '_csrf' => $jetonLecteur, 'documents' => [(string) $public1],
    ]);
    verifier(
        'documents existants : un lecteur ne peut pas ajouter (403)',
        $refusLecteur['code'] === 403,
        (string) $refusLecteur['code']
    );

    $_SESSION = ['account_id' => $journaliste];
    $jetonJournaliste = csrf_token();
    $refusJournaliste = tests_envoi_requete('POST', '/admin/albums/' . $id . '/photos/ajouter', $journaliste, [
        '_csrf' => $jetonJournaliste, 'documents' => [(string) $public1],
    ]);
    verifier(
        'documents existants : un journaliste ne peut pas ajouter (403)',
        $refusJournaliste['code'] === 403,
        (string) $refusJournaliste['code']
    );

    $refusVisiteur = tests_envoi_requete('POST', '/admin/albums/' . $id . '/photos/ajouter', null, [
        'documents' => [(string) $public1],
    ]);
    verifier(
        'documents existants : un visiteur est renvoyé vers la connexion',
        $refusVisiteur['code'] === 303 && str_contains((string) $refusVisiteur['location'], '/connexion'),
        $refusVisiteur['code'] . ' ' . (string) $refusVisiteur['location']
    );

    verifier(
        'documents existants : un autre administrateur a les mêmes droits',
        tests_envoi_requete('GET', '/admin/albums/' . $id . '/photos', $autre)['code'] === 200
    );
}

/** 6. Visibilité, double clic et reprise. */
function tests_album_visibilite(int $admin): void
{
    $album = tests_album_creer('Album public', 'public');
    $id    = (int) ($album['id'] ?? 0);

    $photo = tests_album_photo(80_000);
    $envoi = tests_album_jouer($admin, 'photo-publique.png', $photo, $id);
    @unlink($photo);
    $docId = (int) ($envoi['envoi']['document_id'] ?? 0);

    $visiteur = tests_envoi_requete('GET', '/albums/' . (string) $album['slug'], null);
    verifier(
        'visibilité album : le visiteur voit une photo publique d’un album public',
        $visiteur['code'] === 200 && str_contains($visiteur['corps'], 'photo-publique'),
        (string) $visiteur['code']
    );

    db_update('documents', $docId, ['visibility' => 'prive']);
    $visiteur2 = tests_envoi_requete('GET', '/albums/' . (string) $album['slug'], null);
    verifier(
        'visibilité album : une photo privée reste invisible, même dans un album public',
        $visiteur2['code'] === 200 && !str_contains($visiteur2['corps'], 'photo-publique'),
        (string) $visiteur2['code']
    );

    $pageAdmin = tests_envoi_requete('GET', '/albums/' . (string) $album['slug'], $admin);
    verifier(
        'visibilité album : l’administrateur, lui, voit la photo privée',
        str_contains($pageAdmin['corps'], 'photo-publique'),
        (string) $pageAdmin['code']
    );

    $prive = tests_album_creer('Album privé', 'prive');
    $ferme = tests_envoi_requete('GET', '/albums/' . (string) $prive['slug'], null);
    verifier(
        'visibilité album : un album privé reste fermé au visiteur (404)',
        $ferme['code'] === 404,
        (string) $ferme['code']
    );

    // Un fichier déposé deux fois de suite ne crée qu'une photo.
    $photo2 = tests_album_photo(70_000);
    $partiel = tests_album_jouer($admin, 'double-clic.png', $photo2, $id, [], false);
    $envoiPartiel = envoi_par_id((int) ($partiel['envoi']['id'] ?? 0));
    $un     = is_array($envoiPartiel) ? envoi_terminer($envoiPartiel) : [];
    $deux   = is_array($envoiPartiel) ? envoi_terminer(envoi_par_id((int) $envoiPartiel['id']) ?? $envoiPartiel) : [];
    $sha    = (string) ($un['envoi']['sha256'] ?? '');
    $nombre = (int) db_value('SELECT COUNT(*) FROM documents WHERE sha256 = ?', [$sha], -1);
    @unlink($photo2);
    verifier(
        'envoi album : un double clic ne crée pas deux photos',
        ($un['ok'] ?? false) === true && ($deux['ok'] ?? true) === false && $nombre === 1,
        'documents : ' . $nombre . ' — ' . (string) ($deux['error'] ?? '')
    );

    // La reprise retrouve la destination.
    $photo3 = tests_album_photo(60_000);
    tests_album_jouer($admin, 'reprise.png', $photo3, $id, [0], false);
    $meme  = envoi_a_reprendre($admin, 'reprise.png', (int) filesize($photo3), $id);
    $autre = envoi_a_reprendre($admin, 'reprise.png', (int) filesize($photo3), null);
    @unlink($photo3);
    verifier(
        'envoi album : la reprise retrouve l’envoi fait pour cet album, et lui seul',
        $meme !== null && (int) $meme['album_id'] === $id && $autre === null,
        'même album : ' . ($meme !== null ? 'oui' : 'non') . ', autre : ' . ($autre !== null ? 'oui' : 'non')
    );

    // Le script sait l'album, filtre les photos et recharge la page une fois.
    $script = (string) @file_get_contents(HDF_ROOT . '/public/assets/envoi.js');
    verifier(
        'envoi album : le script envoie l’album et n’accepte que des photos',
        str_contains($script, "donnees.append('album', this.album)")
            && str_contains($script, 'this.album && this.types.length')
            && str_contains($script, "getAttribute('data-envoi-album')"),
        ''
    );
    verifier(
        'envoi album : la page se recharge une fois pour montrer les photos ajoutées',
        str_contains($script, 'window.location.reload()')
            && str_contains($script, 'this.rechargeFaite'),
        ''
    );
}
