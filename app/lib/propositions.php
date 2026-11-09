<?php
/**
 * Propositions des journalistes, validées par les administrateurs.
 *
 * Un journaliste (compte personnel) peut proposer des articles, des documents
 * (photos, vidéos, sons, PDF…) et des recettes. Une proposition :
 *
 *   - naît « en attente » et **privée** — jamais publiée directement ;
 *   - n'est visible que de son auteur (dans « Mes propositions ») et des
 *     administrateurs (dans « À valider » et partout ailleurs) ;
 *   - ne choisit pas sa visibilité : elle peut en **suggérer** une, mais c'est
 *     l'administrateur qui décide en validant ;
 *   - peut être refusée, avec un motif obligatoire, que l'auteur lit ;
 *   - peut être modifiée tant qu'elle n'est pas validée.
 *
 * La règle de visibilité est écrite **une seule fois** (proposition_clause en
 * SQL, proposition_visible en PHP) : les listes, la recherche, les flux, la
 * carte, la gazette, les courriels et le service des fichiers s'appuient tous
 * dessus. Aucun droit n'est ouvert par ce module : il ne fait que rétrécir ce
 * qui est visible.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Vocabulaire
// ---------------------------------------------------------------------------

/** États de validation d'une proposition (clé technique → libellé). */
function moderation_states(): array
{
    return [
        'brouillon' => 'Brouillon',
        'attente'   => 'En attente',
        'publie'    => 'Publié',
        'refuse'    => 'Refusé',
    ];
}

/** Libellé lisible d'un état (un état inconnu est traité comme « en attente »). */
function moderation_label(?string $etat): string
{
    $etats = moderation_states();

    return $etats[$etat ?? ''] ?? $etats['attente'];
}

/** Classe de badge (aucune classe existante n'est renommée). */
function moderation_class(?string $etat): string
{
    return 'badge badge-' . match ($etat) {
        'publie'    => 'public',
        'refuse'    => 'prive',
        'brouillon' => 'branches',
        default     => 'famille',
    };
}

/**
 * Types de contenus qui passent par la validation.
 *
 * Clé = nom employé dans les URL et les écrans ; valeur = table réellement
 * concernée. La liste est fermée : aucune requête ne fabrique un nom de table
 * à partir d'une saisie.
 */
function proposition_types(): array
{
    return [
        'article'  => ['table' => 'articles', 'kind' => 'article', 'label' => 'Article', 'pluriel' => 'Articles'],
        'document' => ['table' => 'documents', 'kind' => 'document', 'label' => 'Document', 'pluriel' => 'Documents'],
        'recette'  => ['table' => 'recipes', 'kind' => 'recipe', 'label' => 'Recette', 'pluriel' => 'Recettes'],
    ];
}

/** Type connu ? (sinon null — jamais d'exception à cause d'une URL). */
function proposition_type(string $type): ?array
{
    return proposition_types()[$type] ?? null;
}

/** Table d'un type de proposition, ou '' si le type est inconnu. */
function proposition_table(string $type): string
{
    return (string) (proposition_types()[$type]['table'] ?? '');
}

/**
 * Les colonnes de validation existent-elles ?
 *
 * Une base qui n'a pas encore reçu la migration 016 continue de fonctionner :
 * les propositions sont alors simplement indisponibles, sans erreur.
 */
function proposition_migration_faite(): bool
{
    static $fait = null;
    if ($fait !== null) {
        return $fait;
    }

    try {
        $colonnes = array_column(db_all('PRAGMA table_info(documents)'), 'name');
        $fait = in_array('moderation_state', $colonnes, true);
    } catch (Throwable) {
        $fait = false;
    }

    return $fait;
}

/**
 * La colonne des versions (migration 017) est-elle en place ?
 *
 * Sans elle, le site continue exactement comme avant : une modification
 * s'applique directement à la ligne (voir proposition_save()).
 */
function proposition_versions_faites(): bool
{
    static $fait = null;
    if ($fait !== null) {
        return $fait;
    }

    try {
        $colonnes = array_column(db_all('PRAGMA table_info(articles)'), 'name');
        $fait = in_array('replaces_id', $colonnes, true);
    } catch (Throwable) {
        $fait = false;
    }

    return $fait;
}

/**
 * Les colonnes de contenu d'un type de proposition (celles qu'une version
 * porte, et celles qui sont recopiées sur l'original lors de la validation).
 *
 * `slug` n'en fait pas partie : l'adresse d'un contenu publié ne change
 * jamais, même quand son titre est corrigé. Les liens déjà envoyés à la
 * famille continuent donc de fonctionner.
 */
function proposition_colonnes_contenu(string $type): array
{
    return match ($type) {
        'article'  => ['title', 'summary', 'body', 'category'],
        'document' => ['title', 'description', 'doc_date', 'doc_date_sort', 'category_id'],
        'recette'  => ['title', 'origin', 'servings', 'ingredients', 'preparation', 'notes'],
        default    => [],
    };
}

// ---------------------------------------------------------------------------
// Lecture : qui voit une proposition ?
// ---------------------------------------------------------------------------

/**
 * Condition SQL : les contenus visibles dans les listes, la recherche, les
 * flux et les courriels.
 *
 * Elle s'ajoute à la clause de visibilité habituelle (elle ne la remplace
 * jamais) et ne laisse passer que les contenus **publiés**. Les
 * administrateurs, eux, voient tout : ils sont sortis plus haut par
 * visibility_clause().
 *
 * Un auteur ne passe pas par ici : il retrouve ses propositions dans « Mes
 * propositions », et il peut ouvrir leur page ou leur fichier par une adresse
 * directe (voir proposition_mienne et proposition_visible). Une proposition en
 * attente n'apparaît donc **dans aucune liste**, pas même celle de son auteur :
 * c'est le sens de « invisible jusqu'à la validation ».
 *
 * @param array $params complété par référence (aucun paramètre ici)
 */
function proposition_clause(string $kind, string $alias, array &$params): string
{
    // Albums (1.37.1) : même principe depuis la migration 025.
    if ($kind === 'album') {
        return album_moderation_faite() ? $alias . ".moderation_state = 'publie'" : '1 = 1';
    }
    if (!in_array($kind, ['article', 'document', 'recipe'], true) || !proposition_migration_faite()) {
        return '1 = 1';
    }

    return $alias . ".moderation_state = 'publie'";
}

/** La migration 025 (albums en attente) est-elle appliquée ? */
function album_moderation_faite(): bool
{
    static $fait = null;
    if ($fait !== null) {
        return $fait;
    }

    try {
        $colonnes = array_column(db_all('PRAGMA table_info(albums)'), 'name');
        $fait = in_array('moderation_state', $colonnes, true);
    } catch (Throwable) {
        $fait = false;
    }

    return $fait;
}

/**
 * Ce compte est-il l'auteur de cette proposition ?
 *
 * Un auteur voit toujours ce qu'il a déposé, même privé : c'est le seul moyen
 * pour lui de relire son texte avant la validation (et de lire le motif d'un
 * refus). Aucun autre droit n'est ouvert par cette fonction.
 */
function proposition_mienne(?array $account, string $kind, array $row): bool
{
    if ($account === null
        || !in_array($kind, ['article', 'document', 'recipe'], true)
        || !proposition_migration_faite()) {
        return false;
    }

    return (int) ($row['submitted_by'] ?? 0) > 0
        && (int) $row['submitted_by'] === (int) $account['id'];
}

/**
 * Même règle, en PHP : ce compte peut-il voir cette ligne ?
 *
 * Utilisé là où la ligne est déjà chargée (fichiers servis, miniatures,
 * flux vidéo, aperçus) : c'est le garde-fou des accès directs.
 */
function proposition_visible(?array $account, string $kind, array $row): bool
{
    if (!in_array($kind, ['article', 'document', 'recipe'], true) || !proposition_migration_faite()) {
        return true;
    }

    if (($account['role'] ?? '') === 'admin') {
        return true;
    }

    if ((string) ($row['moderation_state'] ?? 'publie') === 'publie') {
        return true;
    }

    // Ni un visiteur ni un autre compte ne voit une proposition en attente ;
    // seul son auteur, par une adresse directe, peut la relire.
    return proposition_mienne($account, $kind, $row);
}

// ---------------------------------------------------------------------------
// Lecture : propositions d'un journaliste, file d'attente des administrateurs
// ---------------------------------------------------------------------------

/**
 * Titre et date d'une ligne, quel que soit son type (articles, documents et
 * recettes n'ont pas les mêmes colonnes).
 */
function proposition_titre(array $row, string $type): string
{
    $titre = trim((string) ($row['title'] ?? ''));
    if ($titre === '' && $type === 'document') {
        $titre = trim((string) ($row['original_name'] ?? ''));
    }
    if ($titre === '') {
        $titre = 'Sans titre';
    }

    return $titre;
}

/** Date à afficher pour une ligne (date de publication ou de dépôt). */
function proposition_date(array $row): int
{
    foreach (['submitted_at', 'updated_at', 'created_at'] as $cle) {
        $valeur = (int) ($row[$cle] ?? 0);
        if ($valeur > 0) {
            return $valeur;
        }
    }

    return 0;
}

/**
 * Propositions d'un journaliste, tous types confondus, les plus récentes
 * d'abord.
 *
 * @return list<array{type:string, row:array}>
 */
function propositions_de(int $compteId): array
{
    if (!proposition_migration_faite() || $compteId <= 0) {
        return [];
    }

    $lignes = [];
    foreach (proposition_types() as $type => $info) {
        $rows = db_all(
            'SELECT * FROM ' . $info['table'] . ' WHERE submitted_by = ? ORDER BY updated_at DESC LIMIT 100',
            [$compteId]
        );
        foreach ($rows as $row) {
            $lignes[] = ['type' => $type, 'row' => $row];
        }
    }

    usort($lignes, static fn (array $a, array $b): int => proposition_date($b['row']) <=> proposition_date($a['row']));

    return $lignes;
}

/**
 * File d'attente de l'administration : tout ce qui attend une décision.
 *
 * @return list<array{type:string, row:array}>
 */
function propositions_en_attente(): array
{
    if (!proposition_migration_faite()) {
        return [];
    }

    $lignes = [];
    foreach (proposition_types() as $type => $info) {
        $rows = db_all(
            "SELECT * FROM " . $info['table'] . " WHERE moderation_state = 'attente' ORDER BY submitted_at ASC, id ASC LIMIT 200"
        );
        foreach ($rows as $row) {
            $lignes[] = ['type' => $type, 'row' => $row];
        }
    }

    usort($lignes, static fn (array $a, array $b): int => proposition_date($a['row']) <=> proposition_date($b['row']));

    return $lignes;
}

/** Nombre de propositions en attente (compteur du menu et du tableau de bord). */
function propositions_a_valider(): int
{
    if (!proposition_migration_faite()) {
        return 0;
    }

    $total = 0;
    foreach (proposition_types() as $info) {
        $total += (int) db_value(
            'SELECT COUNT(*) FROM ' . $info['table'] . " WHERE moderation_state = 'attente'",
            [],
            0
        );
    }

    return $total;
}

/** Une proposition précise, ou null (type inconnu ou identifiant absent). */
function proposition_obtenir(string $type, int $id): ?array
{
    $table = proposition_table($type);
    if ($table === '' || $id <= 0 || !proposition_migration_faite()) {
        return null;
    }

    return db_one('SELECT * FROM ' . $table . ' WHERE id = ?', [$id]);
}

/** Nom affiché d'un auteur (sans jamais exposer son identifiant de connexion). */
function proposition_auteur(array $row): string
{
    $id = (int) ($row['submitted_by'] ?? 0);
    if ($id <= 0) {
        return 'Auteur inconnu';
    }

    $compte = db_one('SELECT * FROM accounts WHERE id = ?', [$id]);

    return $compte !== null ? compte_nom_complet($compte) : 'Auteur inconnu';
}

// ---------------------------------------------------------------------------
// Écriture : déposer, modifier, valider, refuser
// ---------------------------------------------------------------------------

/**
 * Marque un contenu comme proposition « en attente ».
 *
 * Utilisé à la création par un journaliste, et à chaque modification d'un
 * contenu qui lui appartient. La visibilité n'est jamais publique ici.
 *
 * @param array $extra colonnes supplémentaires à écrire en même temps
 */
function proposition_soumettre(string $type, int $id, ?int $auteurId, string $visibiliteSuggeree = '', array $extra = []): void
{
    $table = proposition_table($type);
    if ($table === '' || $id <= 0 || !proposition_migration_faite()) {
        return;
    }

    $donnees = array_merge([
        'moderation_state'     => 'attente',
        'moderation_note'      => '',
        'submitted_by'         => $auteurId,
        'submitted_at'         => time(),
        'suggested_visibility' => $visibiliteSuggeree,
        'updated_at'           => time(),
    ], $extra);

    db_update($table, $id, $donnees);
}

/**
 * Valide une proposition : l'administrateur choisit la visibilité réelle.
 *
 * Le contenu devient « publié » ; il entre alors dans les listes, la
 * recherche, la carte et la gazette selon la visibilité choisie — exactement
 * comme un contenu créé par un administrateur.
 */
function proposition_valider(string $type, int $id, string $visibilite, int $adminId): array
{
    $table = proposition_table($type);
    if ($table === '' || !proposition_migration_faite()) {
        return ['ok' => false, 'error' => 'Type de proposition inconnu.'];
    }

    $niveau = sanitize_visibility($visibilite);
    $ligne  = db_one('SELECT * FROM ' . $table . ' WHERE id = ?', [$id]);
    if ($ligne === null) {
        return ['ok' => false, 'error' => 'Proposition introuvable.'];
    }

    // Version d'un contenu déjà publié : c'est l'ORIGINAL qui est mis à jour
    // (le texte publié ne change donc qu'à cet instant précis), et la ligne de
    // version disparaît. Si l'original a été supprimé entre-temps, la version
    // prend simplement sa place (elle devient publiée).
    $remplace = (int) ($ligne['replaces_id'] ?? 0);
    if ($remplace > 0) {
        return proposition_publier_version($type, $ligne, $niveau, $adminId);
    }

    db_update($table, $id, [
        'moderation_state' => 'publie',
        'moderation_note'  => '',
        'validated_by'     => $adminId,
        'validated_at'     => time(),
        'visibility'       => $niveau,
        'updated_at'       => time(),
    ]);

    log_activity($adminId, 'proposition_validee', $type . ' « ' . proposition_titre($ligne, $type) . ' » — ' . visibility_label($niveau));

    return ['ok' => true, 'error' => '', 'visibilite' => $niveau];
}

/**
 * Refuse une proposition, avec un motif obligatoire.
 *
 * Le contenu reste en base (l'auteur le voit, avec le motif), mais il n'est
 * jamais publié. S'il avait déjà été publié un jour, la version en ligne reste
 * visible : on ne dépublie pas un contenu par un refus de relecture.
 */
function proposition_refuser(string $type, int $id, string $motif, int $adminId): array
{
    $table = proposition_table($type);
    if ($table === '' || !proposition_migration_faite()) {
        return ['ok' => false, 'error' => 'Type de proposition inconnu.'];
    }

    $motif = trim($motif);
    if (mb_strlen($motif, 'UTF-8') < 3) {
        return ['ok' => false, 'error' => 'Indiquez un motif de refus (au moins 3 caractères).'];
    }

    $ligne = db_one('SELECT * FROM ' . $table . ' WHERE id = ?', [$id]);
    if ($ligne === null) {
        return ['ok' => false, 'error' => 'Proposition introuvable.'];
    }

    db_update($table, $id, [
        'moderation_state' => 'refuse',
        'moderation_note'  => mb_substr($motif, 0, 500, 'UTF-8'),
        'validated_by'     => $adminId,
        'validated_at'     => null,
        'updated_at'       => time(),
    ]);

    log_activity($adminId, 'proposition_refusee', $type . ' « ' . proposition_titre($ligne, $type) . ' » — ' . $motif);

    return ['ok' => true, 'error' => ''];
}

// ---------------------------------------------------------------------------
// Versions : modifier un contenu déjà publié
// ---------------------------------------------------------------------------

/**
 * Enregistre une modification comme VERSION EN ATTENTE d'un contenu publié.
 *
 * L'original garde son texte (les lecteurs ne voient rien changer) ; la
 * version, elle, porte le nouveau texte et attend la relecture. Un
 * administrateur la valide — c'est alors l'original qui est mis à jour — ou la
 * refuse — et rien ne bouge côté public.
 *
 * @param array $contenu champs de contenu déjà validés par l'appelant
 * @return int identifiant de la version (0 si l'écriture a échoué)
 */
function proposition_creer_version(string $type, array $row, array $contenu, array $extra = []): int
{
    $table = proposition_table($type);
    if ($table === '' || !proposition_migration_faite() || !proposition_versions_faites()) {
        return 0;
    }

    // On part de la ligne d'origine : elle porte toutes les colonnes
    // obligatoires du type (published_at, status, kind…). Le contenu de la
    // modification et les champs de validation écrasent ensuite ce qu'il faut.
    $donnees = $row;
    unset($donnees['id']);

    $donnees = array_merge($donnees, $contenu, [
        'moderation_state'     => 'attente',
        'moderation_note'      => '',
        'visibility'           => 'prive',
        'submitted_by'         => (int) ($row['submitted_by'] ?? 0),
        'submitted_at'         => time(),
        'validated_by'         => null,
        'validated_at'         => null,
        'suggested_visibility' => (string) ($extra['suggested_visibility'] ?? ''),
        'replaces_id'          => (int) $row['id'],
        'created_at'           => time(),
        'updated_at'           => time(),
    ]);

    // Un document partage le fichier de son original : la version ne crée un
    // second exemplaire que si un nouveau fichier a été déposé.
    if ($type === 'document') {
        $donnees['created_by']    = (int) ($row['created_by'] ?? 0);
        $donnees['is_attachment'] = 0;
    }

    // Un article est signé par son auteur : c'est le journaliste qui propose
    // la modification, pas le compte qui avait signé la première version.
    if ($type === 'article' && (int) ($row['submitted_by'] ?? 0) > 0) {
        $donnees['author_id'] = (int) $row['submitted_by'];
    }

    try {
        return db_insert($table, $donnees);
    } catch (Throwable $e) {
        // Une image de une supprimée entre-temps ferait échouer la clé
        // étrangère : la version part sans image plutôt que pas du tout.
        unset($donnees['image_id']);
        try {
            return db_insert($table, $donnees);
        } catch (Throwable) {
            error_log('Version de proposition non enregistrée (' . $type . ') : ' . $e->getMessage());
            return 0;
        }
    }
}

/**
 * Publie une version : le texte de la version remplace celui de l'original.
 *
 * La version elle-même est supprimée (ce n'est pas un contenu, seulement un
 * projet de modification) ; ses fichiers éventuels passent à l'original.
 */
function proposition_publier_version(string $type, array $version, string $niveau, int $adminId): array
{
    $table    = proposition_table($type);
    $original = db_one('SELECT * FROM ' . $table . ' WHERE id = ?', [(int) $version['replaces_id']]);

    if ($original === null) {
        // L'original a disparu : la version devient le contenu, telle quelle.
        db_update($table, (int) $version['id'], [
            'moderation_state' => 'publie',
            'moderation_note'  => '',
            'validated_by'     => $adminId,
            'validated_at'     => time(),
            'visibility'       => $niveau,
            'replaces_id'      => null,
            'updated_at'       => time(),
        ]);
        log_activity($adminId, 'proposition_validee', $type . ' « ' . proposition_titre($version, $type)
            . ' » — ' . visibility_label($niveau) . ' (version, original disparu)');
        return ['ok' => true, 'error' => '', 'visibilite' => $niveau];
    }

    $maj = ['updated_at' => time()];
    foreach (proposition_colonnes_contenu($type) as $colonne) {
        if (array_key_exists($colonne, $version)) {
            $maj[$colonne] = $version[$colonne];
        }
    }
    // Le fichier d'une version de document n'est repris que s'il diffère :
    // sinon on garde celui de l'original (aucun fichier dupliqué).
    if ($type === 'document') {
        foreach (['rel_path', 'thumb_path', 'original_name', 'mime', 'ext', 'size', 'kind', 'width', 'height'] as $colonne) {
            if (array_key_exists($colonne, $version) && (string) $version[$colonne] !== (string) $original[$colonne]) {
                $maj[$colonne] = $version[$colonne];
            }
        }
    }

    db_transaction(static function () use ($table, $original, $version, $maj, $niveau, $adminId): void {
        db_update($table, (int) $original['id'], array_merge($maj, [
            'moderation_state' => 'publie',
            'moderation_note'  => '',
            'validated_by'     => $adminId,
            'validated_at'     => time(),
            'visibility'       => $niveau,
        ]));
        db_exec('DELETE FROM ' . $table . ' WHERE id = ?', [(int) $version['id']]);
    });

    // L'ancien fichier de l'original n'est effacé que s'il n'est plus
    // référencé nulle part (ni par une autre fiche, ni par une version).
    if ($type === 'document'
        && isset($maj['rel_path'])
        && (string) $maj['rel_path'] !== (string) $original['rel_path']) {
        proposition_effacer_fichier_si_orphelin((string) $original['rel_path']);
        proposition_effacer_fichier_si_orphelin((string) ($original['thumb_path'] ?? ''));
    }

    log_activity($adminId, 'proposition_validee', $type . ' « ' . proposition_titre($version, $type)
        . ' » — nouvelle version, ' . visibility_label($niveau));

    return ['ok' => true, 'error' => '', 'visibilite' => $niveau];
}

/**
 * La version en attente d'un contenu publié, s'il y en a déjà une.
 *
 * Une seule version à la fois : modifier deux fois de suite le même contenu
 * publié ne doit pas créer deux projets concurrents. Si une version existe,
 * c'est ELLE que l'auteur modifie.
 */
function proposition_version_en_attente(string $type, int $originalId): ?array
{
    $table = proposition_table($type);
    if ($table === '' || $originalId <= 0 || !proposition_versions_faites()) {
        return null;
    }

    return db_one(
        "SELECT * FROM {$table} WHERE replaces_id = ? AND moderation_state IN ('attente', 'brouillon')
         ORDER BY id DESC LIMIT 1",
        [$originalId]
    );
}

/** Efface un fichier du stockage s'il n'est plus référencé par aucune fiche. */
function proposition_effacer_fichier_si_orphelin(string $rel): void
{
    $rel = trim($rel);
    if ($rel === '') {
        return;
    }
    $utilise = (int) db_value(
        'SELECT COUNT(*) FROM documents WHERE rel_path = ? OR thumb_path = ?',
        [$rel, $rel],
        0
    );
    if ($utilise === 0) {
        storage_delete($rel);
    }
}

/**
 * Retire une proposition en attente (l'auteur renonce).
 *
 * Trois garde-fous :
 *   - seul son auteur peut le faire ;
 *   - un contenu déjà publié n'est jamais retiré par ce chemin (il faudrait le
 *     dépublier, ce qui est un geste d'administrateur) ;
 *   - les fichiers déposés pour cette proposition sont effacés s'ils ne sont
 *     plus utilisés par aucune fiche : un envoi retiré ne laisse pas
 *     d'orphelin sur la carte SD.
 *
 * @return array{ok:bool, error:string}
 */
function proposition_retirer(string $type, int $id, int $compteId): array
{
    $table = proposition_table($type);
    if ($table === '' || !proposition_migration_faite()) {
        return ['ok' => false, 'error' => 'Type de proposition inconnu.'];
    }

    $ligne = db_one('SELECT * FROM ' . $table . ' WHERE id = ?', [$id]);
    if ($ligne === null) {
        return ['ok' => false, 'error' => 'Proposition introuvable.'];
    }
    if ((int) ($ligne['submitted_by'] ?? 0) !== $compteId || $compteId <= 0) {
        return ['ok' => false, 'error' => 'Cette proposition n’est pas la vôtre.'];
    }
    if ((string) ($ligne['moderation_state'] ?? 'attente') === 'publie') {
        return ['ok' => false, 'error' => 'Cette proposition est publiée : demandez à un administrateur de la retirer.'];
    }

    $fichiers = [];
    if ($type === 'document') {
        $fichiers[] = (string) ($ligne['rel_path'] ?? '');
        $fichiers[] = (string) ($ligne['thumb_path'] ?? '');
    }

    db_exec('DELETE FROM ' . $table . ' WHERE id = ?', [$id]);

    foreach (array_unique(array_filter($fichiers)) as $rel) {
        proposition_effacer_fichier_si_orphelin($rel);
    }

    log_activity($compteId, 'proposition_retiree', $type . ' « ' . proposition_titre($ligne, $type) . ' »');

    return ['ok' => true, 'error' => ''];
}

// ---------------------------------------------------------------------------
// Courriels
// ---------------------------------------------------------------------------

/**
 * Prévient les administrateurs qu'une proposition attend (un seul courriel
 * par proposition).
 *
 * Rien n'est envoyé si les courriels ne sont pas configurés : la file
 * d'attente suffit, et la page « À valider » le montre.
 */
function proposition_prevenir_admins(string $type, int $id): int
{
    if (!courriel_configure() || !proposition_migration_faite()) {
        return 0;
    }

    $ligne = proposition_obtenir($type, $id);
    if ($ligne === null) {
        return 0;
    }

    $titre  = proposition_titre($ligne, $type);
    $auteur = proposition_auteur($ligne);
    $lien   = site_adresse_publique() . '/admin/a-valider';
    $libelle = proposition_types()[$type]['label'] ?? 'Proposition';

    $texte = "Une proposition attend votre décision.\n\n"
        . $libelle . ' : ' . $titre . "\n"
        . 'Proposé par : ' . $auteur . "\n"
        . 'À examiner : ' . $lien . "\n\n"
        . "Tant qu'elle n'est pas validée, elle n'est visible que de son auteur et des administrateurs.";

    $envoyes = 0;
    foreach (db_all("SELECT * FROM accounts WHERE role = 'admin' AND is_active = 1 AND email <> ''") as $admin) {
        $adresse = courriel_adresse_normaliser((string) $admin['email']);
        if (!courriel_adresse_valide($adresse)) {
            continue;
        }

        $idCourriel = courriel_enfiler([
            'account_id' => (int) $admin['id'],
            'kind'       => 'proposition',
            'recipient'  => $adresse,
            'subject'    => 'À valider : ' . $titre,
            'text'       => $texte,
            'dedupe'     => 'proposition-' . $type . '-' . $id . '-' . (int) $admin['id'],
        ]);
        if ($idCourriel > 0) {
            $envoyes++;
        }
    }

    return $envoyes;
}

/**
 * Prévient les lecteurs qu'un contenu vient d'être publié.
 *
 * Réutilise la notification d'articles déjà en place (elle respecte les
 * droits de visibilité et les accords de chacun) : un document ou une recette
 * validé ne déclenche donc aucun courriel, comme avant.
 */
function proposition_prevenir_lecteurs(string $type, int $id): int
{
    if ($type !== 'article') {
        return 0;
    }

    $article = db_one('SELECT * FROM articles WHERE id = ?', [$id]);
    if ($article === null) {
        return 0;
    }

    return courriel_notifier_article($article);
}
