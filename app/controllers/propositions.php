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

    render('propositions/index', [
        'titre'        => 'Mes propositions',
        'propositions' => propositions_de((int) $compte['id']),
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

    if ($id > 0 && ($row === null || !proposition_appartient($row, current_account()))) {
        http_response_code(404);
        render('erreur', ['titre' => 'Proposition introuvable', 'message' => 'Cette proposition n’existe pas.']);
        return;
    }

    // Fichiers prêts du journaliste (envoi par morceaux déjà assemblé).
    $prets = db_all(
        "SELECT * FROM uploads WHERE account_id = ? AND state = 'pret' ORDER BY updated_at DESC LIMIT 20",
        [(int) current_account()['id']]
    );

    render('propositions/form', [
        'titre'    => ($row !== null ? 'Modifier' : 'Proposer') . ' — ' . mb_strtolower($info['label']),
        'type'     => $type,
        'info'     => $info,
        'ligne'    => $row,
        'categorie' => $row['category'] ?? '',
        'prets'    => $prets,
        'erreurs'  => form_errors(),
        'old'      => form_old(),
    ], 'layout');
}

/** La proposition appartient-elle à ce compte ? */
function proposition_appartient(array $row, ?array $compte): bool
{
    return $compte !== null
        && (int) ($row['submitted_by'] ?? 0) > 0
        && (int) $row['submitted_by'] === (int) $compte['id'];
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

    if ($id > 0 && ($row === null || !proposition_appartient($row, $compte))) {
        http_response_code(404);
        render('erreur', ['titre' => 'Proposition introuvable', 'message' => 'Cette proposition n’existe pas.']);
        return;
    }

    $brouillon = post('action') === 'brouillon';
    $etat      = $brouillon ? 'brouillon' : 'attente';
    $suggeree  = sanitize_visibility(post('suggested_visibility'));

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

    // La proposition naît privée : elle ne devient jamais publique ici.
    $donnees['visibility']            = $row !== null ? (string) $row['visibility'] : 'prive';
    $donnees['moderation_state']      = $etat;
    $donnees['moderation_note']       = '';
    $donnees['submitted_by']          = (int) $compte['id'];
    $donnees['submitted_at']          = time();
    $donnees['suggested_visibility']  = $suggeree;

    // Modifier une proposition déjà validée la remet « en attente » : elle
    // redevient invisible pour les autres jusqu'à la prochaine validation.
    // La trace de l'ancienne validation reste au journal d'actions.
    $remiseEnAttente = $row !== null && (string) ($row['moderation_state'] ?? '') === 'publie';
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
        $base  = $slug;
        $index = 2;
        while (db_one('SELECT id FROM ' . $table . ' WHERE slug = ? AND id <> ?', [$slug, $id]) !== null) {
            $slug  = $base . '-' . $index;
            $index++;
        }
        $donnees['slug'] = $slug;
    }

    if ($id > 0) {
        db_update($info['table'], $id, $donnees);
        $propositionId = $id;
    } else {
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

    log_activity(
        (int) $compte['id'],
        'proposition_enregistree',
        $info['label'] . ' « ' . $donnees['title'] . ' » (' . moderation_label($etat) . ')'
    );

    if ($etat === 'attente') {
        // Un seul courriel par proposition et par administrateur.
        proposition_prevenir_admins($type, $propositionId);
        flash(
            'succes',
            $remiseEnAttente
                ? 'Proposition modifiée : elle repasse en attente de validation, et redevient invisible pour les autres.'
                : 'Votre proposition est en attente de validation : elle n’est visible que de vous et des administrateurs.'
        );
    } else {
        flash('succes', 'Brouillon enregistré : il n’est visible que de vous.');
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
