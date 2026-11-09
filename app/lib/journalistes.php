<?php
/**
 * Droits réglables des journalistes (1.37.0).
 *
 * Deux réglages par journaliste, modifiables à tout moment par un
 * administrateur dans la fiche du compte :
 *
 *   - **Contrôle** : « avec validation » (défaut, comportement historique :
 *     ses ajouts restent en attente, invisibles de tous, jusqu'à validation)
 *     ou « sans validation » (publication directe, journalisée) ;
 *   - **Périmètre** : « toutes les branches » (défaut, comportement
 *     historique) ou une sélection de branches cochées.
 *
 * La règle « ce contenu relève d'une branche » est définie ici, une seule
 * fois, et documentée dans docs/droits-journalistes.md :
 *
 *   1. le rattachement explicite du contenu à des branches
 *      (`content_branches`, celui qui sert déjà à la visibilité
 *      « Branches précises » et aux éditions) ;
 *   2. à défaut, la branche de son auteur (articles.author_id,
 *      documents/recipes/events/albums.created_by) ;
 *   3. les fiches personnes n'ont pas d'auteur : seul le rattachement
 *      explicite compte. Une personne sans rattachement ne relève d'aucune
 *      branche en particulier : seul un journaliste « toutes les branches »
 *      peut la corriger.
 *
 * Aucun droit de LECTURE n'est touché : la lecture reste régie par
 * `visibility_clause()` / `can_view()` et la branche du compte, comme avant.
 * Le périmètre ne règle que l'ÉCRITURE (créer, publier, modifier). Et dans
 * tous les cas : un journaliste ne supprime rien, ne touche ni aux comptes,
 * ni aux réglages, ni aux validations des autres.
 */

declare(strict_types=1);

/** La migration 024 est-elle appliquée ? (Sinon : comportement actuel.) */
function journaliste_migration_faite(): bool
{
    static $fait = null;
    if ($fait !== null) {
        return $fait;
    }
    $colonnes = array_column(db_all('PRAGMA table_info(accounts)'), 'name');
    return $fait = in_array('journaliste_validation', $colonnes, true)
        && in_array('journaliste_toutes_branches', $colonnes, true);
}

/**
 * Réglages d'un journaliste.
 *
 * @return array{validation: bool, toutes: bool, branches: list<int>}
 */
function journaliste_reglages(?array $compte): array
{
    $defaut = ['validation' => true, 'toutes' => true, 'branches' => []];

    if ($compte === null || !compte_est_journaliste($compte) || !journaliste_migration_faite()) {
        return $defaut;
    }

    $branches = [];
    foreach (db_all('SELECT branch_id FROM journaliste_branches WHERE account_id = ? ORDER BY branch_id', [(int) $compte['id']]) as $ligne) {
        $branches[] = (int) $ligne['branch_id'];
    }

    return [
        'validation' => (int) ($compte['journaliste_validation'] ?? 1) !== 0,
        'toutes'     => (int) ($compte['journaliste_toutes_branches'] ?? 1) !== 0,
        'branches'   => $branches,
    ];
}

/** Ce journaliste publie-t-il seulement après validation ? */
function journaliste_avec_validation(?array $compte = null): bool
{
    return journaliste_reglages($compte ?? current_account())['validation'];
}

/**
 * Branches du périmètre d'écriture du journaliste.
 *
 * @return list<int>|null null = toutes les branches
 */
function journaliste_perimetre(?array $compte = null): ?array
{
    $reglages = journaliste_reglages($compte ?? current_account());
    return $reglages['toutes'] ? null : $reglages['branches'];
}

/** Ce journaliste peut-il écrire pour cette branche ? */
function journaliste_dans_perimetre(int $branchId, ?array $compte = null): bool
{
    $perimetre = journaliste_perimetre($compte);
    return $perimetre === null || in_array($branchId, $perimetre, true);
}

/** Champ « auteur » d'un type de contenu (pour la règle de branche). */
function journaliste_champ_auteur(string $kind): ?string
{
    return match ($kind) {
        'article' => 'author_id',
        'document', 'recipe', 'event', 'album' => 'created_by',
        default => null,
    };
}

/** Table d'un type de contenu (types d'écriture des journalistes). */
function journaliste_table(string $kind): string
{
    return match ($kind) {
        'article' => 'articles',
        'document' => 'documents',
        'recipe' => 'recipes',
        'event' => 'events',
        'album' => 'albums',
        'person' => 'persons',
        default => '',
    };
}

/**
 * Branches dont relève un contenu (règle documentée ci-dessus).
 *
 * @return list<int>
 */
function contenu_releve_branches(string $kind, array $row): array
{
    $explicites = content_branch_ids($kind, (int) ($row['id'] ?? 0));
    if ($explicites !== []) {
        return $explicites;
    }

    $champ = journaliste_champ_auteur($kind);
    if ($champ !== null && (int) ($row[$champ] ?? 0) > 0) {
        $auteur = db_one('SELECT branch_id FROM accounts WHERE id = ?', [(int) $row[$champ]]);
        if ($auteur !== null && $auteur['branch_id'] !== null) {
            return [(int) $auteur['branch_id']];
        }
    }

    return [];
}

/** Ce contenu est-il « à lui » (auteur ou déposant = ce compte) ? */
function contenu_est_le_sien(string $kind, array $row, array $compte): bool
{
    $id = (int) $compte['id'];
    if ((int) ($row['submitted_by'] ?? 0) === $id) {
        return true;
    }
    $champ = journaliste_champ_auteur($kind);
    return $champ !== null && (int) ($row[$champ] ?? 0) === $id;
}

/**
 * Ce compte peut-il modifier ce contenu ?
 *
 * Administrateur : oui. Journaliste : ses propres contenus, et ceux qui
 * relèvent d'une branche de son périmètre (« toutes les branches » = tout).
 * Lecteur et visiteur : jamais.
 */
function journaliste_peut_modifier(string $kind, array $row, ?array $compte = null): bool
{
    $compte = $compte ?? current_account();
    if ($compte === null) {
        return false;
    }
    if (($compte['role'] ?? '') === 'admin') {
        return true;
    }
    if (!compte_est_journaliste($compte)) {
        return false;
    }
    if (contenu_est_le_sien($kind, $row, $compte)) {
        return true;
    }
    // Un contenu que le compte ne peut même pas voir ne doit pas fuiter,
    // pas même par son formulaire de modification.
    if (!visibilite_compte($compte, (string) ($row['visibility'] ?? 'prive'), content_branch_ids($kind, (int) ($row['id'] ?? 0)))) {
        return false;
    }
    $perimetre = journaliste_perimetre($compte);
    if ($perimetre === null) {
        return true;
    }
    return array_intersect(contenu_releve_branches($kind, $row), $perimetre) !== [];
}

/**
 * Branches qu'un journaliste peut choisir pour un contenu « Branches
 * précises » : son périmètre (toutes les branches actives si « toutes »).
 *
 * @return list<int>
 */
function journaliste_branches_choisissables(?array $compte = null): array
{
    $compte = $compte ?? current_account();
    $perimetre = journaliste_perimetre($compte);
    if ($perimetre !== null) {
        return $perimetre;
    }
    return array_map(static fn (array $b): int => (int) $b['id'], branches_list(true));
}

/**
 * Les branches choisies sont-elles toutes dans le périmètre ?
 *
 * @param list<int> $branches
 */
function journaliste_branches_permises(array $branches, ?array $compte = null): bool
{
    $compte = $compte ?? current_account();
    if ($compte !== null && ($compte['role'] ?? '') === 'admin') {
        return true;
    }
    $perimetre = journaliste_perimetre($compte);
    if ($perimetre === null) {
        return true;
    }
    return array_diff($branches, $perimetre) === [];
}

/**
 * Garde la version précédente d'un contenu modifié directement.
 *
 * Seules les colonnes texte/visibilité utiles sont conservées (jamais de
 * mot de passe ni de donnée de compte : il n'y en a pas dans ces tables).
 *
 * @param list<string> $colonnes
 */
function contenu_historique_enregistrer(string $kind, array $avant, int $accountId, string $action, array $colonnes): void
{
    if (!journaliste_migration_faite() || (int) ($avant['id'] ?? 0) <= 0) {
        return;
    }
    $extrait = [];
    foreach ($colonnes as $colonne) {
        if (array_key_exists($colonne, $avant)) {
            $extrait[$colonne] = $avant[$colonne];
        }
    }
    try {
        db_insert('contenu_historique', [
            'kind'       => $kind,
            'content_id' => (int) $avant['id'],
            'account_id' => $accountId > 0 ? $accountId : null,
            'action'     => $action,
            'avant'      => json_encode($extrait, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
            'created_at' => time(),
        ]);
    } catch (Throwable) {
        // L'historique ne doit jamais faire échouer une modification.
    }
}

/** Colonnes gardées dans l'historique, par type de contenu. */
function contenu_historique_colonnes(string $kind): array
{
    return match ($kind) {
        'article' => ['title', 'summary', 'body', 'category', 'visibility'],
        'document' => ['title', 'description', 'doc_date', 'visibility', 'est_archive', 'dossier_id'],
        'recipe' => ['title', 'origin', 'servings', 'ingredients', 'preparation', 'notes', 'visibility'],
        'event' => ['type', 'title', 'start_date', 'start_time', 'end_date', 'end_time', 'place', 'description', 'visibility', 'annual'],
        'album' => ['title', 'description', 'visibility'],
        'person' => ['first_name', 'last_name', 'sex', 'birth_date', 'birth_place', 'death_date', 'death_place', 'notes', 'visibility'],
        default => ['title', 'visibility'],
    };
}

/**
 * Note une action d'un journaliste « sans validation » dans le journal
 * d'activité (visible des seuls administrateurs).
 */
function journaliste_journaliser(string $action, string $kind, int $contentId, string $titre, ?array $compte = null): void
{
    $compte = $compte ?? current_account();
    if ($compte === null) {
        return;
    }
    log_activity(
        (int) $compte['id'],
        'journaliste_' . $action,
        $kind . ':' . $contentId . ' — ' . $titre
    );
}

/**
 * Actions du journal qui concernent les journalistes « sans validation ».
 *
 * @return list<array>
 */
function journaliste_journal(int $limite = 100): array
{
    return db_all(
        "SELECT al.*, a.username, a.display_name
         FROM activity_log al
         LEFT JOIN accounts a ON a.id = al.account_id
         WHERE al.action LIKE 'journaliste\\_%' ESCAPE '\\'
         ORDER BY al.created_at DESC, al.id DESC
         LIMIT " . max(1, $limite)
    );
}
