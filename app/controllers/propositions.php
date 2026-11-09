<?php
/**
 * Espace « Mes propositions » des journalistes.
 *
 * Un journaliste (compte personnel) propose un article, un document (photo,
 * vidéo, son, PDF…) ou une recette. Rien n'est publié ici :
 *
 *   - la proposition naît « en attente » (ou « brouillon » s'il la garde pour
 *     lui) et **privée** ;
 *   - elle n'est visible que de son auteur et des administrateurs ;
 *   - c'est l'administrateur qui choisit la visibilité en validant, ou qui
 *     refuse avec un motif.
 *
 * Les droits sont tenus par require_proposer() : un lecteur reçoit 403, un
 * visiteur est renvoyé vers la connexion. Un journaliste ne peut toucher que
 * SES propositions (le contrôle est refait à chaque écriture).
 *
 * L'envoi par morceaux est disponible : les fichiers passent par
 * /propositions/fichier/*, et non par les adresses d'administration. Le
 * fichier assemblé reste « prêt » : il n'est rattaché à une fiche qu'à
 * l'enregistrement de la proposition.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Mes propositions
// ---------------------------------------------------------------------------

/** Liste des propositions de l'auteur connecté. */
function propositions_index(): void
{
    require_proposer();
    $compte = current_account();

    // Contenus d'autres comptes qu'il peut corriger (son périmètre).
    $corrigables = [];
    if (compte_est_journaliste($compte)) {
        foreach (['article', 'document', 'recette'] as $typeCorr) {
            $infoCorr = proposition_type($typeCorr);
            if ($infoCorr === null) {
                continue;
            }
            $lignes = db_all(
                'SELECT * FROM ' . $infoCorr['table'] . " WHERE moderation_state = 'publie' ORDER BY updated_at DESC LIMIT 200"
            );
            foreach ($lignes as $ligneCorr) {
                if (proposition_appartient($ligneCorr, $compte)) {
                    continue;
                }
                if (journaliste_peut_modifier(proposition_genre($typeCorr), $ligneCorr, $compte)) {
                    $corrigables[] = ['type' => $typeCorr, 'label' => $infoCorr['label'], 'ligne' => $ligneCorr];
                }
                if (count($corrigables) >= 50) {
                    break 2;
                }
            }
        }
    }

    render('propositions/index', [
        'titre'        => 'Mes propositions',
        'propositions' => propositions_de((int) $compte['id']),
        'corrigables'  => $corrigables,
        'echecs'       => form_errors(),
    ], 'layout');
}

/**
 * Formulaire d'une proposition (création ou modification).
 *
 * @param array $params {type: string, id?: int}
 */
function proposition_form(array $params = []): void
{
    require_proposer();

    $type = (string) ($params['type'] ?? '');
    $info = proposition_type($type);
    if ($info === null) {
        http_response_code(404);
        render('erreur', ['titre' => 'Page introuvable', 'message' => 'Ce type de proposition n’existe pas.']);
        return;
    }

    $id  = (int) ($params['id'] ?? 0);
    $row = $id > 0 ? proposition_obtenir($type, $id) : null;

    if ($id > 0 && ($row === null || !proposition_peut_editer($type, $row, current_account()))) {
        http_response_code(404);
        render('erreur', ['titre' => 'Proposition introuvable', 'message' => 'Cette proposition n’existe pas.']);
        return;
    }

    // Une modification déjà en attente ? C'est elle que l'auteur reprend,
    // plutôt que d'ouvrir un second projet sur le même contenu publié.
    if ($row !== null && (string) ($row['moderation_state'] ?? '') === 'publie') {
        $version = proposition_version_en_attente($type, $id);
        if ($version !== null) {
            flash('info', 'Une modification de ce contenu attend déjà la relecture : vous la reprenez ici.');
            redirect('/propositions/' . $type . '/' . (int) $version['id']);
            return;
        }
    }

    // Fichiers prêts du journaliste (envoi par morceaux déjà assemblé).
    $prets = db_all(
        "SELECT * FROM uploads WHERE account_id = ? AND state = 'pret' ORDER BY updated_at DESC LIMIT 20",
        [(int) current_account()['id']]
    );

    $compteForm = current_account();
    $directForm = $compteForm !== null && compte_est_journaliste($compteForm) && !journaliste_avec_validation($compteForm);

    render('propositions/form', [
        'titre'    => ($row !== null ? 'Modifier' : 'Proposer') . ' — ' . mb_strtolower($info['label']),
        'type'     => $type,
        'info'     => $info,
        'ligne'    => $row,
        'categorie' => $row['category'] ?? '',
        'prets'    => $prets,
        'erreurs'  => form_errors(),
        'old'      => form_old(),
        // 1.37.0 : publication directe et périmètre.
        'publicationDirecte' => $directForm,
        'estJournaliste'     => $compteForm !== null && compte_est_journaliste($compteForm),
        'branchesChoisissables' => $directForm
            ? array_values(array_filter(branches_list(true), static fn (array $b): bool => journaliste_dans_perimetre((int) $b['id'], $compteForm)))
            : [],
        'branchesContenu' => $row !== null ? content_branch_ids(proposition_genre($type), (int) $row['id']) : [],
    ], 'layout');
}

/** La proposition appartient-elle à ce compte ? */
function proposition_appartient(array $row, ?array $compte): bool
{
    return $compte !== null
        && (int) ($row['submitted_by'] ?? 0) > 0
        && (int) $row['submitted_by'] === (int) $compte['id'];
}

/** Type de contenu (« recette ») → genre de contenu_branches (« recipe »). */
function proposition_genre(string $type): string
{
    return $type === 'recette' ? 'recipe' : $type;
}

/**
 * Ce compte peut-il ouvrir ce contenu en édition par les propositions ?
 *
 * Le sien : toujours. Celui d'un autre : seulement un journaliste, et
 * seulement si le contenu relève d'une branche de son périmètre (un
 * administrateur passe par l'administration, jamais par ici). Un lecteur
 * est déjà écarté par require_proposer().
 */
function proposition_peut_editer(string $type, array $row, ?array $compte): bool
{
    if (proposition_appartient($row, $compte)) {
        return true;
    }
    if ($compte === null || !compte_est_journaliste($compte)) {
        return false;
    }
    return journaliste_peut_modifier(proposition_genre($type), $row, $compte);
}

/**
 * Enregistre une proposition (création ou modification).
 *
 * @param array $params {type: string, id?: int}
 */
function proposition_save(array $params = []): void
{
    require_proposer();
    csrf_check();

    $type = (string) ($params['type'] ?? '');
    $info = proposition_type($type);
    if ($info === null) {
        http_response_code(404);
        render('erreur', ['titre' => 'Page introuvable', 'message' => 'Ce type de proposition n’existe pas.']);
        return;
    }

    $id    = (int) ($params['id'] ?? 0);
    $row   = $id > 0 ? proposition_obtenir($type, $id) : null;
    $compte = current_account();
    $back  = $id > 0 ? '/propositions/' . $type . '/' . $id : '/propositions/nouvelle/' . $type;

    if ($id > 0 && ($row === null || !proposition_peut_editer($type, $row, $compte))) {
        http_response_code(404);
        render('erreur', ['titre' => 'Proposition introuvable', 'message' => 'Cette proposition n’existe pas.']);
        return;
    }

    // Même règle à l'enregistrement : une seule version en attente par contenu.
    if ($row !== null && (string) ($row['moderation_state'] ?? '') === 'publie') {
        $version = proposition_version_en_attente($type, $id);
        if ($version !== null) {
            redirect('/propositions/' . $type . '/' . (int) $version['id']);
            return;
        }
    }

    $brouillon = post('action') === 'brouillon';
    $etat      = $brouillon ? 'brouillon' : 'attente';
    $suggeree  = sanitize_visibility(post('suggested_visibility'));

    // Publication directe (1.37.0) : un journaliste réglé « sans validation »
    // publie tout de suite, avec la visibilité qu'il choisit. Les autres
    // chemins (avec validation, administrateur, brouillon) sont inchangés.
    $publicationDirecte = !$brouillon
        && compte_est_journaliste($compte)
        && !journaliste_avec_validation($compte);
    $branchesChoisies = array_values(array_unique(array_filter(array_map('intval', post_array('branches')))));
    if ($publicationDirecte && $suggeree === 'branches' && $branchesChoisies === []) {
        $erreursPre = ['Visibilité « Branches précises » : cochez au moins une branche.'];
        form_fail($erreursPre, array_merge($_POST, ['suggested_visibility' => $suggeree]), $back);
    }
    if ($publicationDirecte && $suggeree === 'branches' && !journaliste_branches_permises($branchesChoisies, $compte)) {
        form_fail(['Vous ne pouvez publier que pour les branches de votre périmètre.'], array_merge($_POST, ['suggested_visibility' => $suggeree]), $back);
    }

    $erreurs = [];
    $donnees = [];
    $titre   = '';
    $envoi   = null;
    $fichier = null;
    $depose  = null;

    switch ($type) {
        case 'article':
            $titre = mb_substr(trim(post('title')), 0, 200, 'UTF-8');
            if ($titre === '') {
                $erreurs[] = 'Le titre est obligatoire.';
            }
            $donnees = [
                'title'      => $titre !== '' ? $titre : 'Sans titre',
                'summary'    => mb_substr(trim(post('summary')), 0, 500, 'UTF-8'),
                'body'       => post('body'),
                'category'   => mb_substr(trim(post('category')), 0, 60, 'UTF-8'),
                'status'     => 'publie',
                'published_at' => $row !== null ? (int) $row['published_at'] : time(),
                'author_id'  => (int) $compte['id'],
                'updated_at' => time(),
            ];
            break;

        case 'document':
            $titre = mb_substr(trim(post('title')), 0, 200, 'UTF-8');
            if ($titre === '') {
                $erreurs[] = 'Le titre est obligatoire.';
            }

            // Deux chemins, comme dans l'administration : un fichier envoyé
            // d'un coup (≤ 50 Mo) ou un fichier assemblé par morceaux.
            $depose  = $_FILES['fichier'] ?? null;
            $aDepose = is_array($depose) && (int) ($depose['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            $envoi   = null;
            $fichier = null;

            if ($row === null) {
                if ($aDepose) {
                    $valide = validate_uploaded_file($depose);
                    if (!$valide['ok']) {
                        $erreurs[] = $valide['error'];
                    } else {
                        $garde = store_uploaded_file($valide);
                        if (!$garde['ok']) {
                            $erreurs[] = $garde['error'];
                        } else {
                            $fichier = $valide + $garde;
                        }
                    }
                } else {
                    $envoi = envoi_fichier_pret((int) $compte['id'], post('envoi'));
                    if ($envoi === null) {
                        $erreurs[] = 'Choisissez le fichier à proposer (envoi simple ou par morceaux).';
                    }
                }
            }

            $date = trim(post('doc_date'));
            $donnees = [
                'est_archive'   => !empty($_POST['archive']) ? 1 : 0,
                'title'         => $titre !== '' ? $titre : 'Sans titre',
                'description'   => trim(post('description')) !== '' ? mb_substr(trim(post('description')), 0, 2000, 'UTF-8') : null,
                'doc_date'      => $date !== '' ? mb_substr($date, 0, 100, 'UTF-8') : null,
                'doc_date_sort' => extract_year($date),
                'category_id'   => null,
                'updated_at'    => time(),
            ];
            if ($envoi !== null) {
                $donnees += envoi_champs_document($envoi);
            } elseif ($fichier !== null) {
                $donnees += [
                    'rel_path'      => (string) $fichier['rel_path'],
                    'thumb_path'    => $fichier['thumb_path'] !== '' ? (string) $fichier['thumb_path'] : null,
                    'original_name' => mb_substr((string) $depose['name'], 0, 200, 'UTF-8'),
                    'mime'          => (string) $fichier['mime'],
                    'ext'           => (string) $fichier['ext'],
                    'size'          => (int) $fichier['size'],
                    'kind'          => (string) $fichier['kind'],
                    'width'         => $fichier['width'],
                    'height'        => $fichier['height'],
                ];
            }
            if ($envoi !== null || $fichier !== null) {
                $donnees['created_by']    = (int) $compte['id'];
                $donnees['created_at']    = time();
                $donnees['is_attachment'] = 0;
            }
            break;

        case 'recette':
            $titre = mb_substr(trim(post('title')), 0, 200, 'UTF-8');
            if ($titre === '') {
                $erreurs[] = 'Le titre est obligatoire.';
            }
            $ingredients = trim(post('ingredients'));
            if ($ingredients === '') {
                $erreurs[] = 'Indiquez au moins les ingrédients.';
            }
            $donnees = [
                'title'       => $titre !== '' ? $titre : 'Sans titre',
                'origin'      => mb_substr(trim(post('origin')), 0, 120, 'UTF-8') ?: null,
                'servings'    => mb_substr(trim(post('servings')), 0, 60, 'UTF-8') ?: null,
                'ingredients' => $ingredients,
                'preparation' => post('preparation'),
                'notes'       => mb_substr(trim(post('notes')), 0, 2000, 'UTF-8') ?: null,
                'updated_at'  => time(),
            ];
            break;
    }

    if ($erreurs !== []) {
        form_fail($erreurs, array_merge($_POST, ['suggested_visibility' => $suggeree]), $back);
    }

    // Fichier assemblé par morceaux : réservé une seule fois, ici, avant
    // d'écrire quoi que ce soit (un double envoi ne peut pas le réutiliser).
    if ($envoi !== null && !envoi_reserver($envoi)) {
        form_fail(['Ce fichier vient d’être utilisé pour une autre fiche. Redéposez-le.'], array_merge($_POST, ['suggested_visibility' => $suggeree]), $back);
    }

    // La proposition naît privée : elle ne devient jamais publique ici.
    // (Sauf publication directe d'un journaliste « sans validation » : la
    // visibilité choisie s'applique tout de suite, et c'est journalisé.)
    $donnees['visibility']            = $row !== null ? (string) $row['visibility'] : 'prive';
    if ($publicationDirecte) {
        $donnees['visibility'] = $suggeree;
    }
    $donnees['moderation_state']      = $publicationDirecte ? 'publie' : $etat;
    $donnees['moderation_note']       = '';
    if (!($publicationDirecte && $row !== null && !proposition_appartient($row, $compte))) {
        $donnees['submitted_by']          = (int) $compte['id'];
        $donnees['submitted_at']          = time();
    }
    $donnees['suggested_visibility']  = $suggeree;

    // Modifier une proposition déjà validée ne touche PAS au texte publié :
    // la modification devient une version en attente (migration 017), que les
    // lecteurs ne voient pas avant la validation. Sans la colonne des versions
    // (base non migrée), on retombe sur l'ancien comportement : la ligne
    // repasse « en attente » et disparaît des listes publiques.
    $remiseEnAttente = $row !== null && (string) ($row['moderation_state'] ?? '') === 'publie';
    $creerVersion    = $remiseEnAttente && proposition_versions_faites() && !$publicationDirecte;
    if ($publicationDirecte && $remiseEnAttente) {
        // L'ancien texte n'est pas perdu : il part dans l'historique.
        contenu_historique_enregistrer(
            proposition_genre($type),
            $row,
            (int) $compte['id'],
            proposition_appartient($row, $compte) ? 'modification_directe' : 'modification_directe_autre',
            contenu_historique_colonnes(proposition_genre($type))
        );
    }
    $donnees['replaces_id'] = null;
    if ($remiseEnAttente) {
        $donnees['validated_by'] = null;
        $donnees['validated_at'] = null;
    }

    if ($type === 'article' || $type === 'recette') {
        $slug = slugify((string) $donnees['title']);
        if ($slug === '') {
            $slug = $type === 'article' ? 'article' : 'recette';
        }
        $table = proposition_table($type);
        if ($creerVersion) {
            // Une version est une ligne à part : elle a besoin d'une adresse
            // libre, mais celle de l'original ne bougera pas (les liens déjà
            // envoyés continuent de fonctionner après la validation).
            $slug = mb_substr($slug, 0, 150, 'UTF-8') . '-version-' . bin2hex(random_bytes(3));
        }
        $base  = $slug;
        $index = 2;
        while (db_one('SELECT id FROM ' . $table . ' WHERE slug = ? AND id <> ?', [$slug, $id]) !== null) {
            $slug  = $base . '-' . $index;
            $index++;
        }
        $donnees['slug'] = $slug;
    }

    if ($creerVersion) {
        // La ligne d'origine garde son texte et sa visibilité : seuls les
        // administrateurs voient la version dans « À valider ».
        $contenu = [];
        foreach (proposition_colonnes_contenu($type) as $colonne) {
            if (array_key_exists($colonne, $donnees)) {
                $contenu[$colonne] = $donnees[$colonne];
            }
        }
        foreach (['rel_path', 'thumb_path', 'original_name', 'mime', 'ext', 'size', 'kind', 'width', 'height'] as $colonne) {
            if (array_key_exists($colonne, $donnees)) {
                $contenu[$colonne] = $donnees[$colonne];
            }
        }
        // L'adresse de la version : libre, celle de l'original ne change pas.
        if (isset($donnees['slug'])) {
            $contenu['slug'] = $donnees['slug'];
        }
        $propositionId = proposition_creer_version(
            $type,
            $row,
            $contenu,
            ['suggested_visibility' => $suggeree]
        );
        if ($propositionId <= 0) {
            // La version n'a pas pu être écrite : on ne touche PAS au contenu
            // publié (le remplacer serait pire que de refuser la modification).
            flash('erreur', 'Votre modification n’a pas pu être enregistrée. Le texte publié n’a pas été touché : réessayez.');
            redirect('/propositions');
            return;
        }
    }

    if (!$creerVersion && $id > 0) {
        db_update($info['table'], $id, $donnees);
        $propositionId = $id;
    } elseif (!$creerVersion || $propositionId === 0) {
        // La date de création n'est jamais laissée vide : la base la réclame.
        $donnees['created_at'] = $donnees['created_at'] ?? time();
        $propositionId = db_insert($info['table'], $donnees);
    }

    if ($type === 'document' && $propositionId > 0 && ($envoi !== null || $fichier !== null)) {
        if ($envoi !== null) {
            envoi_marquer_utilise($envoi, $propositionId);
        }
        conversion_planifier_document($propositionId);
    }

    if ($publicationDirecte && $propositionId > 0) {
        set_content_branches(proposition_genre($type), $propositionId, $suggeree === 'branches' ? $branchesChoisies : []);
        journaliste_journaliser(
            $id > 0 ? 'modification' : 'publication',
            proposition_genre($type),
            $propositionId,
            (string) $donnees['title'],
            $compte
        );
    }

    log_activity(
        (int) $compte['id'],
        'proposition_enregistree',
        $info['label'] . ' « ' . $donnees['title'] . ' » (' . moderation_label($publicationDirecte ? 'publie' : $etat) . ')'
    );

    if ($publicationDirecte) {
        flash('succes', $id > 0
            ? 'Modification publiée directement : elle est visible selon la visibilité choisie.'
            : 'Publié directement : il est visible selon la visibilité choisie.');
    } elseif ($etat === 'attente') {
        // Un seul courriel par proposition et par administrateur.
        proposition_prevenir_admins($type, $propositionId);
        flash(
            'succes',
            $creerVersion
                ? 'Modification enregistrée : elle attend la relecture d’un administrateur. '
                    . 'Le texte déjà publié reste en ligne, inchangé, jusque-là.'
                : ($remiseEnAttente
                    ? 'Proposition modifiée : elle repasse en attente de validation, et redevient invisible pour les autres.'
                    : 'Votre proposition est en attente de validation : elle n’est visible que de vous et des administrateurs.')
        );
    } else {
        flash('succes', 'Brouillon enregistré : il n’est visible que de vous.');
    }

    redirect('/propositions');
}

/**
 * Retire une proposition en attente (l'auteur renonce).
 *
 * Elle disparaît aussitôt de « À valider » et de « Mes propositions », et ses
 * fichiers sont effacés s'ils ne servent plus à rien. Un contenu déjà publié
 * n'est jamais retiré par ce chemin.
 */
function proposition_retrait(array $params = []): void
{
    require_proposer();
    csrf_check();

    $type  = (string) ($params['type'] ?? '');
    $id    = (int) ($params['id'] ?? 0);
    $info  = proposition_type($type);
    $compte = current_account();

    if ($info === null || $id <= 0) {
        http_response_code(404);
        render('erreur', ['titre' => 'Page introuvable', 'message' => 'Cette proposition n’existe pas.']);
        return;
    }

    $row = proposition_obtenir($type, $id);
    if ($row === null || !proposition_appartient($row, $compte)) {
        http_response_code(404);
        render('erreur', ['titre' => 'Proposition introuvable', 'message' => 'Cette proposition n’existe pas.']);
        return;
    }

    $retrait = proposition_retirer($type, $id, (int) $compte['id']);
    if (!$retrait['ok']) {
        flash('erreur', $retrait['error']);
    } else {
        flash('succes', 'Proposition retirée. Elle n’attend plus de relecture et ses fichiers ont été rangés.');
    }

    redirect('/propositions');
}

// ---------------------------------------------------------------------------
// Envoi par morceaux, pour les journalistes
// ---------------------------------------------------------------------------
//
// Ces cinq adresses font exactement le même travail que /admin/envoyer/*,
// avec deux différences : elles acceptent un journaliste, et le fichier
// assemblé ne crée JAMAIS de fiche (il reste « prêt », à rattacher à une
// proposition). Les pages d'administration, elles, restent fermées.

/** Active le mode « journaliste » le temps d'un appel aux fonctions d'envoi. */
function propositions_mode_envoi(): void
{
    require_proposer();
    $GLOBALS['hdf_envoi_journaliste'] = true;
}

/** Annonce un fichier (réponse JSON). */
function propositions_fichier_init(): void
{
    propositions_mode_envoi();
    admin_envoi_init();
}

/** Reçoit un morceau (réponse JSON). */
function propositions_fichier_morceau(): void
{
    propositions_mode_envoi();
    admin_envoi_morceau();
}

/** Dit ce qui est déjà reçu (réponse JSON). */
function propositions_fichier_etat(): void
{
    propositions_mode_envoi();
    admin_envoi_etat();
}

/** Assemble le fichier : il reste « prêt », sans fiche (réponse JSON). */
function propositions_fichier_terminer(): void
{
    propositions_mode_envoi();
    admin_envoi_terminer();
}

/** Abandonne un envoi (supprime les morceaux). */
function propositions_fichier_annuler(): void
{
    propositions_mode_envoi();
    admin_envoi_annuler();
}
