<?php
/**
 * Fiches personnes des journalistes (1.37.0).
 *
 * Un journaliste « sans validation » peut ajouter et corriger les fiches
 * personnes — et les liens de parenté simples — des branches de son
 * périmètre, sans passer par l'administration (qui reste fermée). Un
 * journaliste « toutes les branches » peut corriger toute fiche. Aucune
 * suppression ici.
 *
 * Il n'existe pas de file de validation pour les personnes : pour un
 * journaliste « avec validation », la création comme la correction sont
 * donc refusées (403) depuis la 1.37.1, plutôt que directes. Pour un
 * journaliste « sans validation », la correction est directe, toujours
 * tracée (historique) et journalisée comme ses autres actions.
 * Les personnes proposées dans les listes de liens sont seulement celles
 * que le compte peut déjà voir : aucun nom ne fuit par ce formulaire.
 */

declare(strict_types=1);

/** Garde commune : journaliste (ou administrateur), connecté. */
function journaliste_personne_garde(): array
{
    require_login();
    $compte = current_account();
    if ($compte === null || (($compte['role'] ?? '') !== 'admin' && !compte_est_journaliste($compte))) {
        http_response_code(403);
        render('erreur', [
            'titre'   => 'Accès refusé',
            'message' => 'Seuls les journalistes peuvent corriger les fiches personnes par ici.',
        ]);
        hdf_terminer();
    }

    // 1.37.1 : les fiches personnes n'ont pas de file d'attente. Un
    // journaliste « avec validation » ne peut donc ni en créer ni en
    // corriger directement : refus net (403), plutôt qu'une modification
    // qui court-circuiterait la validation. Choix noté dans
    // docs/droits-journalistes.md.
    if (($compte['role'] ?? '') !== 'admin' && journaliste_avec_validation($compte)) {
        http_response_code(403);
        render('erreur', [
            'titre'   => 'Fiches personnes non modifiables directement',
            'message' => 'Votre réglage est « avec validation » : les fiches personnes ne peuvent pas être '
                . 'ajoutées ni corrigées directement. Demandez à un administrateur de le faire, '
                . 'ou de passer votre compte « sans validation ».',
        ]);
        hdf_terminer();
    }

    return $compte;
}

/** Personnes que le compte peut voir (pour les listes de liens). */
function journaliste_personnes_visibles(): array
{
    $params = [];
    $clause = visibility_clause('person', 'p', $params);
    return db_all(
        "SELECT p.id, p.first_name, p.last_name FROM persons p
         WHERE {$clause} ORDER BY p.last_name COLLATE NOCASE, p.first_name COLLATE NOCASE LIMIT 500",
        $params
    );
}

/** Formulaire d'une fiche personne (création ou correction). */
function journaliste_personne_form(array $params = []): void
{
    $compte = journaliste_personne_garde();
    $id     = (int) ($params['id'] ?? 0);
    $person = $id > 0 ? db_one('SELECT * FROM persons WHERE id = ?', [$id]) : null;

    if ($id > 0 && ($person === null || !journaliste_peut_modifier('person', $person, $compte))) {
        http_response_code(404);
        render('erreur', ['titre' => 'Personne introuvable', 'message' => 'Cette fiche n’existe pas, ou elle ne relève pas des branches où vous pouvez écrire.']);
        return;
    }

    render('public/personne_modifier', [
        'titre'    => $person !== null ? 'Corriger la fiche' : 'Nouvelle fiche personne',
        'person'   => $person,
        'personnes' => journaliste_personnes_visibles(),
        'branches' => branches_list(true),
        'branchesChoisies' => $person !== null ? content_branch_ids('person', $id) : [],
        'peutBranches' => static fn (int $b): bool => ($compte['role'] ?? '') === 'admin' || journaliste_dans_perimetre($b, $compte),
        'erreurs'  => form_errors(),
        'old'      => form_old(),
    ]);
}

/** Enregistrement d'une fiche personne (jamais de suppression). */
function journaliste_personne_save(array $params = []): void
{
    $compte = journaliste_personne_garde();
    csrf_check();

    $id     = (int) ($params['id'] ?? 0);
    $person = $id > 0 ? db_one('SELECT * FROM persons WHERE id = ?', [$id]) : null;
    $back   = $id > 0 ? '/arbre/' . $id . '/modifier' : '/arbre/nouvelle-personne';

    if ($id > 0 && ($person === null || !journaliste_peut_modifier('person', $person, $compte))) {
        http_response_code(404);
        render('erreur', ['titre' => 'Personne introuvable', 'message' => 'Cette fiche n’existe pas, ou elle ne relève pas des branches où vous pouvez écrire.']);
        return;
    }

    $estAdmin = ($compte['role'] ?? '') === 'admin';
    $old = [
        'first_name'  => post('first_name'),
        'last_name'   => post('last_name'),
        'sex'         => post('sex'),
        'birth_date'  => post('birth_date'),
        'birth_place' => post('birth_place'),
        'death_date'  => post('death_date'),
        'death_place' => post('death_place'),
        'notes'       => post('notes'),
        'visibility'  => sanitize_visibility(post('visibility')),
        'branches'    => array_map('intval', post_array('branches')),
        'conjoint_id' => (int) post('conjoint_id'),
        'parent_id'   => (int) post('parent_id'),
        'enfant_id'   => (int) post('enfant_id'),
    ];

    $errors = [];
    if ($old['first_name'] === '' && $old['last_name'] === '') {
        $errors[] = 'Indiquez au moins un prénom ou un nom.';
    }
    if (mb_strlen($old['first_name']) > 100 || mb_strlen($old['last_name']) > 100) {
        $errors[] = 'Le prénom et le nom sont limités à 100 caractères.';
    }
    if (!in_array($old['sex'], ['', 'M', 'F'], true)) {
        $old['sex'] = '';
    }
    if (mb_strlen($old['notes']) > 20000) {
        $errors[] = 'Les notes sont trop longues.';
    }
    $visibility = sanitize_visibility($old['visibility']);
    $branches   = array_values(array_unique(array_filter($old['branches'])));
    if ($visibility === 'branches' && $branches === []) {
        $errors[] = 'Visibilité « Branches précises » : cochez au moins une branche.';
    }
    if (!$estAdmin && $visibility === 'branches' && !journaliste_branches_permises($branches, $compte)) {
        $errors[] = 'Vous ne pouvez rattacher une fiche qu’aux branches de votre périmètre.';
    }
    foreach (['conjoint_id', 'parent_id', 'enfant_id'] as $lien) {
        $cible = $old[$lien];
        if ($cible > 0 && ($cible === $id || find_viewable_person($cible) === null)) {
            $errors[] = 'Une des personnes choisies pour un lien n’existe pas ou n’est pas visible pour votre compte.';
        }
    }

    if ($errors !== []) {
        form_fail($errors, $old, $back);
    }

    $data = [
        'first_name'  => $old['first_name'],
        'last_name'   => $old['last_name'],
        'sex'         => $old['sex'] !== '' ? $old['sex'] : null,
        'birth_date'  => $old['birth_date'] !== '' ? mb_substr($old['birth_date'], 0, 100) : null,
        'birth_year'  => extract_year($old['birth_date']),
        'birth_place' => $old['birth_place'] !== '' ? mb_substr($old['birth_place'], 0, 200) : null,
        'death_date'  => $old['death_date'] !== '' ? mb_substr($old['death_date'], 0, 100) : null,
        'death_year'  => extract_year($old['death_date']),
        'death_place' => $old['death_place'] !== '' ? mb_substr($old['death_place'], 0, 200) : null,
        'notes'       => $old['notes'] !== '' ? $old['notes'] : null,
        'visibility'  => $visibility,
        'updated_at'  => time(),
    ];

    if ($id > 0) {
        if (!$estAdmin) {
            contenu_historique_enregistrer('person', $person, (int) $compte['id'], 'modification_directe', contenu_historique_colonnes('person'));
        }
        db_update('persons', $id, $data);
        $personId = $id;
    } else {
        $data['created_at'] = time();
        $personId = db_insert('persons', $data);
    }
    set_content_branches('person', $personId, $visibility === 'branches' ? $branches : []);

    // --- Liens de parenté simples (ajouts seulement, jamais de retrait) ---
    $maintenant = time();
    $famillePour = static function (int $personneId, string $sexe) use ($maintenant): int {
        $famille = db_one(
            'SELECT id FROM families WHERE husband_id = ? OR wife_id = ? ORDER BY id LIMIT 1',
            [$personneId, $personneId]
        );
        if ($famille !== null) {
            return (int) $famille['id'];
        }
        return db_insert('families', [
            'husband_id' => $sexe === 'F' ? null : $personneId,
            'wife_id'    => $sexe === 'F' ? $personneId : null,
            'created_at' => $maintenant,
            'updated_at' => $maintenant,
        ]);
    };

    if ($old['conjoint_id'] > 0) {
        $conjoint = db_one('SELECT id, sex FROM persons WHERE id = ?', [$old['conjoint_id']]);
        $deja = db_one(
            'SELECT id FROM families WHERE (husband_id = ? AND wife_id = ?) OR (husband_id = ? AND wife_id = ?)',
            [$personId, $old['conjoint_id'], $old['conjoint_id'], $personId]
        );
        if ($conjoint !== null && $deja === null) {
            $moiFemme = ($data['sex'] ?? '') === 'F';
            db_insert('families', [
                'husband_id' => $moiFemme ? $old['conjoint_id'] : $personId,
                'wife_id'    => $moiFemme ? $personId : $old['conjoint_id'],
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ]);
        }
    }
    if ($old['parent_id'] > 0) {
        $parent = db_one('SELECT id, sex FROM persons WHERE id = ?', [$old['parent_id']]);
        if ($parent !== null) {
            $familleId = $famillePour((int) $parent['id'], (string) ($parent['sex'] ?? ''));
            db_exec('INSERT OR IGNORE INTO family_children (family_id, person_id) VALUES (?, ?)', [$familleId, $personId]);
        }
    }
    if ($old['enfant_id'] > 0) {
        $familleId = $famillePour($personId, (string) ($data['sex'] ?? ''));
        db_exec('INSERT OR IGNORE INTO family_children (family_id, person_id) VALUES (?, ?)', [$familleId, $old['enfant_id']]);
    }

    if (!$estAdmin) {
        log_activity((int) $compte['id'], $id > 0 ? 'personne_corrigee' : 'personne_ajoutee', person_full_name($data) . ' (' . $personId . ')');
        if (!journaliste_avec_validation($compte)) {
            journaliste_journaliser($id > 0 ? 'modification' : 'publication', 'person', $personId, person_full_name($data), $compte);
        }
    }

    flash('succes', $id > 0 ? 'Fiche corrigée.' : 'Fiche ajoutée à l’arbre.');
    redirect('/arbre/' . $personId);
}
