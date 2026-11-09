<?php
/**
 * Journal des journalistes (administrateurs seulement, 1.37.0).
 *
 * Liste les actions des journalistes « sans validation » (publications et
 * modifications directes notées dans activity_log) et permet de dépublier
 * en un clic le contenu concerné : sa visibilité passe à « privé ». Le
 * contenu n'est jamais supprimé, et la dépublication est elle-même notée.
 */

declare(strict_types=1);

/** Affiche le journal des actions directes des journalistes. */
function admin_journalistes(): void
{
    require_admin();
    $entrees = db_all(
        "SELECT al.id, al.action, al.details, al.created_at, a.username
         FROM activity_log al
         LEFT JOIN accounts a ON a.id = al.account_id
         WHERE al.action LIKE 'journaliste_%' AND al.action <> 'journaliste_reglages'
         ORDER BY al.id DESC LIMIT 200"
    );

    $lignes = [];
    foreach ($entrees as $entree) {
        // Détails écrits par journaliste_journaliser() : « article:12 — Titre »
        $details = (string) $entree['details'];
        $genre = '';
        $id    = 0;
        $titre = $details;
        if (preg_match('/^(\w+):(\d+) — (.*)$/s', $details, $m) === 1) {
            $genre = $m[1];
            $id    = (int) $m[2];
            $titre = $m[3];
        }
        $encorePublie = $id > 0 && contenu_encore_visible($genre, $id);
        $lignes[] = [
            'entree'  => $entree,
            'genre'   => $genre,
            'id'      => $id,
            'titre'   => $titre,
            'depubliable' => $encorePublie,
        ];
    }

    render('admin/journalistes', [
        'titre'  => 'Journal des journalistes',
        'lignes' => $lignes,
    ], 'layout_admin');
}

/** Dit si le contenu existe encore et n'est pas déjà en privé. */
function contenu_encore_visible(string $genre, int $id): bool
{
    $tables = [
        'article'  => 'articles',
        'document' => 'documents',
        'recette'  => 'recipes',
        'album'    => 'albums',
        'event'    => 'events',
        'person'   => 'persons',
    ];
    $table = $tables[$genre] ?? null;
    if ($table === null) {
        return false;
    }
    $ligne = db_one("SELECT visibility FROM {$table} WHERE id = ?", [$id]);
    return $ligne !== null && (string) $ligne['visibility'] !== 'prive';
}

/** Dépublie un contenu du journal : visibilité « privé », jamais supprimé. */
function admin_journaliste_depublier(array $params = []): void
{
    require_admin();
    csrf_check();

    $genre = (string) ($params['type'] ?? '');
    $id    = (int) ($params['id'] ?? 0);
    $tables = [
        'article'  => 'articles',
        'document' => 'documents',
        'recette'  => 'recipes',
        'album'    => 'albums',
        'event'    => 'events',
        'person'   => 'persons',
    ];
    $table = $tables[$genre] ?? null;
    $ligne = $table !== null ? db_one("SELECT id, visibility FROM {$table} WHERE id = ?", [$id]) : null;

    if ($ligne === null) {
        flash('erreur', 'Contenu introuvable, rien n’a été changé.');
        redirect('/admin/journalistes');
    }

    db_update($table, $id, ['visibility' => 'prive', 'updated_at' => time()]);
    log_activity((int) current_account()['id'], 'journaliste_depublie', $genre . ' #' . $id);
    flash('succes', 'Le contenu est repassé en privé. Il n’est pas supprimé : vous pouvez le rouvrir depuis son édition.');
    redirect('/admin/journalistes');
}
