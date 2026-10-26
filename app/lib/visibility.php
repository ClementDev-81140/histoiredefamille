<?php
/**
 * Gestion de la visibilité des contenus.
 *
 * Quatre niveaux :
 *   public   → tout le monde, sans compte ;
 *   famille  → tous les comptes de famille connectés ;
 *   branches → uniquement les branches cochées ;
 *   prive    → administrateurs uniquement (valeur par défaut, jamais public).
 *
 * Un contenu sans niveau explicite est traité comme « prive ».
 */

declare(strict_types=1);

/** Niveaux proposés dans les formulaires (ordre d'affichage). */
function visibility_levels(): array
{
    return [
        'public'   => 'Public (tout le monde)',
        'famille'  => 'Toutes les familles (comptes connectés)',
        'branches' => 'Branches précises',
        'prive'    => 'Privé (administrateurs seulement)',
    ];
}

/** Libellé lisible d'un niveau. */
function visibility_label(?string $level): string
{
    $levels = visibility_levels();
    return $levels[$level ?? ''] ?? 'Privé';
}

/** Libellé court (badges). */
function visibility_short(?string $level): string
{
    return match ($level) {
        'public'   => 'Public',
        'famille'  => 'Familles',
        'branches' => 'Branches',
        default    => 'Privé',
    };
}

/** Classe CSS associée à un niveau de visibilité. */
function visibility_class(?string $level): string
{
    return 'badge badge-' . match ($level) {
        'public'   => 'public',
        'famille'  => 'famille',
        'branches' => 'branches',
        default    => 'prive',
    };
}

/**
 * Condition SQL filtrant les contenus visibles par l'utilisateur courant.
 *
 * @param string $kind  type de contenu : 'document', 'page' ou 'person'
 * @param string $alias alias SQL de la table de contenu (ex. « d »)
 * @param array  $params paramètres de la requête, complétés par référence
 */
function visibility_clause(string $kind, string $alias, array &$params): string
{
    static $sequence = 0;
    $sequence++;

    $account = current_account();
    $a       = $alias;

    // Les administrateurs voient tout, y compris les contenus privés.
    if ($account !== null && $account['role'] === 'admin') {
        return '1 = 1';
    }

    if ($account === null) {
        // Visiteur non connecté : uniquement le public.
        return $a . ".visibility = 'public'";
    }

    // Compte de famille : public + toutes les familles + ses branches.
    $conditions = [
        $a . ".visibility = 'public'",
        $a . ".visibility = 'famille'",
    ];

    $branches = accessible_branch_ids();
    if ($branches !== []) {
        $placeholders = [];
        foreach ($branches as $index => $branchId) {
            $name = ':vis' . $sequence . '_' . $index;
            $placeholders[] = $name;
            $params[$name]  = $branchId;
        }
        $kindParam = ':vkind' . $sequence;
        $params[$kindParam] = $kind;

        $conditions[] = sprintf(
            "%s.visibility = 'branches' AND EXISTS (
                SELECT 1 FROM content_branches cb
                WHERE cb.kind = %s AND cb.content_id = %s.id
                  AND cb.branch_id IN (%s)
            )",
            $a,
            $kindParam,
            $a,
            implode(', ', $placeholders)
        );
    }

    return '(' . implode(' OR ', $conditions) . ')';
}

/**
 * Vérifie en PHP (hors SQL) si l'utilisateur peut voir un contenu,
 * à partir de son niveau et de ses branches.
 */
function can_view(string $visibility, array $contentBranchIds = []): bool
{
    $level = $visibility !== '' ? $visibility : 'prive';

    if ($level === 'public') {
        return true;
    }

    $account = current_account();
    if ($account === null) {
        return false;
    }
    if ($account['role'] === 'admin') {
        return true;
    }
    if ($level === 'famille') {
        return true;
    }
    if ($level === 'branches') {
        if ((int) ($account['branch_see_all'] ?? 0) === 1) {
            return true;
        }
        return $account['branch_id'] !== null
            && in_array((int) $account['branch_id'], array_map('intval', $contentBranchIds), true);
    }
    return false; // prive
}

/** Branches associées à un contenu. */
function content_branch_ids(string $kind, int $contentId): array
{
    $rows = db_all(
        'SELECT branch_id FROM content_branches WHERE kind = ? AND content_id = ?',
        [$kind, $contentId]
    );
    return array_map(static fn ($r) => (int) $r['branch_id'], $rows);
}

/** Remplace les branches associées à un contenu. */
function set_content_branches(string $kind, int $contentId, array $branchIds): void
{
    db_exec('DELETE FROM content_branches WHERE kind = ? AND content_id = ?', [$kind, $contentId]);

    $valid = [];
    foreach (db_all('SELECT id FROM branches') as $row) {
        $valid[] = (int) $row['id'];
    }

    $seen = [];
    foreach ($branchIds as $branchId) {
        $branchId = (int) $branchId;
        if ($branchId <= 0 || !in_array($branchId, $valid, true) || isset($seen[$branchId])) {
            continue;
        }
        $seen[$branchId] = true;
        db_exec(
            'INSERT OR IGNORE INTO content_branches (kind, content_id, branch_id) VALUES (?, ?, ?)',
            [$kind, $contentId, $branchId]
        );
    }
}

/** Valide un niveau reçu d'un formulaire (valeur inconnue → « prive »). */
function sanitize_visibility(?string $value): string
{
    return array_key_exists((string) $value, visibility_levels()) ? (string) $value : 'prive';
}

/**
 * Vérifie qu'un document est visible ; renvoie la ligne complète ou null.
 * (Utilisé par les scripts qui servent les fichiers.)
 */
function find_viewable_document(int $id): ?array
{
    $doc = db_one('SELECT * FROM documents WHERE id = ?', [$id]);
    if (!$doc) {
        return null;
    }
    if (!can_view((string) $doc['visibility'], content_branch_ids('document', $id))) {
        return null;
    }
    return $doc;
}

/** Vérifie qu'une page est visible. */
function find_viewable_page(int $id): ?array
{
    $page = db_one('SELECT * FROM pages WHERE id = ?', [$id]);
    if (!$page) {
        return null;
    }
    if ((int) $page['is_published'] !== 1 && !is_admin()) {
        return null;
    }
    if (!can_view((string) $page['visibility'], content_branch_ids('page', $id))) {
        return null;
    }
    return $page;
}

/** Vérifie qu'une personne de l'arbre est visible. */
function find_viewable_person(int $id): ?array
{
    $person = db_one('SELECT * FROM persons WHERE id = ?', [$id]);
    if (!$person) {
        return null;
    }
    if (!can_view((string) $person['visibility'], content_branch_ids('person', $id))) {
        return null;
    }
    return $person;
}
