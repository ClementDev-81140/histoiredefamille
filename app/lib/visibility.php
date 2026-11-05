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
        // Visiteur non connecté : uniquement le public (et, pour un document,
        // seulement s'il est prêt — voir plus bas).
        $visiteur = $a . ".visibility = 'public'";
        if ($kind === 'document') {
            return '(' . $visiteur . ' AND ' . conversion_pretes_sql($a) . ')';
        }
        return $visiteur;
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

    $visible = '(' . implode(' OR ', $conditions) . ')';

    // Une proposition non validée (article, document, recette) n'est montrée
    // à personne dans les listes : la condition ci-dessous ne fait que
    // RÉTRÉCIR ce qui est visible. Les administrateurs sont déjà sortis plus
    // haut, et l'auteur retrouve ses propositions dans « Mes propositions »
    // (ou par une adresse directe, voir find_viewable_article()).
    $visible = '(' . $visible . ' AND ' . proposition_clause($kind, $a, $params) . ')';

    // Une vidéo encore dans la file de conversion — ou dont la conversion a
    // échoué — n'est pas montrée aux comptes de famille : son lecteur
    // afficherait une erreur (ou un format qu'aucun navigateur ne lit). Cette condition
    // ne fait que RÉTRÉCIR ce qui est visible : aucun droit n'est ouvert, et
    // les administrateurs (plus haut) continuent de tout voir.
    if ($kind === 'document') {
        $visible = '(' . $visible . ' AND ' . conversion_pretes_sql($a) . ')';
    }

    return $visible;
}

/**
 * Vérifie en PHP (hors SQL) si l'utilisateur peut voir un contenu,
 * à partir de son niveau et de ses branches.
 */
function can_view(string $visibility, array $contentBranchIds = []): bool
{
    return visibilite_compte(current_account(), $visibility, $contentBranchIds);
}

/**
 * Même règle que can_view(), mais pour un compte donné (sans se connecter).
 *
 * Utile pour les courriels : on vérifie ce que verrait une personne avant de
 * lui envoyer quoi que ce soit. C'est exactement la même règle, écrite une
 * seule fois : can_view() appelle cette fonction avec le compte courant.
 */
function visibilite_compte(?array $account, string $visibility, array $contentBranchIds = []): bool
{
    $level = $visibility !== '' ? $visibility : 'prive';

    if ($level === 'public') {
        return true;
    }
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
        if (account_sees_all($account)) {
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
    // Un auteur retrouve toujours sa proposition, même privée.
    if (!proposition_mienne(current_account(), 'document', $doc)
        && !can_view((string) $doc['visibility'], content_branch_ids('document', $id))) {
        return null;
    }
    // Une vidéo encore dans la file de conversion (ou en échec) n'existe pas,
    // pour la famille : même réponse qu'un document interdit (404).
    // L'administrateur, lui, la voit et sa fiche indique l'avancement ; son
    // auteur la retrouve aussi (droit de lecture sur son propre dépôt).
    if (!is_admin() && !conversion_document_pret($doc)
        && !conversion_auteur_du_document($doc, current_account())) {
        return null;
    }
    // Même règle pour une proposition en attente : elle ne se sert pas par
    // une adresse directe, sauf à son auteur et aux administrateurs.
    if (!proposition_visible(current_account(), 'document', $doc)) {
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

/**
 * Groupe de droits d'un compte : « tout », « branche:ID », « famille » ou
 * « visiteur ».
 *
 * Deux personnes du même groupe voient exactement les mêmes contenus. C'est
 * ce qui permet de préparer un seul numéro de gazette ou un seul dessin de
 * carte par groupe, sans jamais deviner les droits : on les lit ici, une
 * seule fois, à partir de la même règle que visibility_clause().
 */
function groupe_droits(?array $compte): string
{
    if ($compte === null) {
        return 'visiteur';
    }
    // Administrateur : le rôle fait foi partout dans le site ; le type de
    // compte est conservé pour les bases d'avant la migration 008.
    if (($compte['role'] ?? '') === 'admin'
        || ($compte['account_type'] ?? '') === 'admin'
        || (int) ($compte['see_all'] ?? 0) === 1) {
        return 'tout';
    }
    $branche = (int) ($compte['branch_id'] ?? 0);

    return $branche > 0 ? 'branche:' . $branche : 'famille';
}
