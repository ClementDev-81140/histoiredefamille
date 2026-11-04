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
    if (!in_array($kind, ['article', 'document', 'recipe'], true) || !proposition_migration_faite()) {
        return '1 = 1';
    }

    return $alias . ".moderation_state = 'publie'";
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
