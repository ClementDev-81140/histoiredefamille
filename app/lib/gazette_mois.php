<?php
/**
 * Gazette du mois : contenu automatique, validation, envoi.
 *
 * Principes tenus par ce fichier :
 *
 *  1. Un numéro par GROUPE DE DROITS. Un groupe est un ensemble de personnes
 *     qui voient exactement les mêmes choses. On ne devine pas les droits :
 *     le contenu est calculé en se mettant à la place d'un compte réel du
 *     groupe, avec les fonctions de visibilité du site (visibility_clause,
 *     can_view). Un élément que cette personne ne verrait pas ne peut donc
 *     pas se retrouver dans son numéro.
 *  2. Le contenu est figé au moment de la préparation : l'aperçu de
 *     l'administration et le courriel envoyé montrent exactement le même
 *     numéro.
 *  3. Numéro vide = rien : on ne prépare pas, on n'envoie pas.
 *  4. Un seul envoi par mois et par groupe, garanti par un index unique.
 *  5. Aucun envoi pendant une visite du site : la gazette remplit la file,
 *     la commande app/cli/envoyer-courriels.php fait partir les messages.
 */

declare(strict_types=1);

/** Longueur maximale du mot de l'éditeur. */
const GAZETTE_MOT_MAX = 600;

/** Nombre d'éléments gardés par encart (le reste reste sur le site). */
const GAZETTE_MAX_PAR_ENCART = 12;

/** Délai de grâce, en jours, avant l'envoi automatique sans validation. */
const GAZETTE_GRACE_JOURS = 3;

// ---------------------------------------------------------------------------
// Mois et libellés
// ---------------------------------------------------------------------------

/** Premier jour du mois écoulé, en « AAAA-MM ». */
function gazette_mois_ecoule(?int $maintenant = null): string
{
    $maintenant = $maintenant ?? time();
    return date('Y-m', strtotime('first day of last month', $maintenant));
}

/** « 2026-10 » → « octobre 2026 ». */
function gazette_mois_libelle(string $periode): string
{
    if (preg_match('/^(\d{4})-(\d{2})$/', $periode, $m) !== 1) {
        return $periode;
    }
    $mois = [
        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin',
        7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
    ];
    $numero = (int) $m[2];
    if ($numero < 1 || $numero > 12) {
        return $periode;
    }
    return $mois[$numero] . ' ' . $m[1];
}

/**
 * Bornes du mois raconté : [début, fin[ en secondes.
 *
 * @return array{debut: int, fin: int}
 */
function gazette_mois_bornes(string $periode): array
{
    if (preg_match('/^(\d{4})-(\d{2})$/', $periode, $m) !== 1) {
        return ['debut' => 0, 'fin' => 0];
    }
    $debut = mktime(0, 0, 0, (int) $m[2], 1, (int) $m[1]);

    return ['debut' => (int) $debut, 'fin' => (int) strtotime('+1 month', (int) $debut)];
}

// ---------------------------------------------------------------------------
// Groupes de droits
// ---------------------------------------------------------------------------

/**
 * Groupe de droits d'un compte (même règle que la carte des lieux).
 *
 * Le calcul vit dans visibility.php : une seule définition pour tout le site.
 */
function gazette_groupe_de_compte(array $compte): string
{
    return groupe_droits($compte);
}

/** Libellé lisible d'un groupe de droits. */
function gazette_groupe_libelle(string $groupe): string
{
    if ($groupe === 'tout') {
        return 'Les personnes qui voient tout';
    }
    if ($groupe === 'famille') {
        return 'Toute la famille connectée';
    }
    if (str_starts_with($groupe, 'branche:')) {
        $branche = find_branch((int) substr($groupe, 8));
        return $branche !== null ? 'La branche ' . (string) $branche['name'] : 'Une branche';
    }
    return $groupe;
}

/**
 * Groupes de droits à servir, avec leurs destinataires.
 *
 * Seules les personnes qui ont une adresse ET coché « Je veux recevoir la
 * gazette » comptent : sans destinataire, pas de numéro.
 *
 * @return array<string, array{groupe: string, comptes: list<int>, representant: int}>
 */
function gazette_profils(): array
{
    $lignes = db_all(
        "SELECT * FROM accounts
         WHERE is_active = 1 AND email <> '' AND notify_gazette = 1
         ORDER BY id"
    );

    $groupes = [];
    foreach ($lignes as $compte) {
        $groupe = gazette_groupe_de_compte($compte);
        if (!isset($groupes[$groupe])) {
            $groupes[$groupe] = ['groupe' => $groupe, 'comptes' => [], 'representant' => (int) $compte['id']];
        }
        $groupes[$groupe]['comptes'][] = (int) $compte['id'];
    }

    return $groupes;
}

/** Exécute un calcul « comme si » le compte donné était connecté. */
function gazette_comme_compte(int $compteId, callable $calcul): mixed
{
    $avant = $_SESSION['account_id'] ?? null;
    $_SESSION['account_id'] = $compteId;
    try {
        return $calcul();
    } finally {
        if ($avant === null) {
            unset($_SESSION['account_id']);
        } else {
            $_SESSION['account_id'] = $avant;
        }
    }
}

// ---------------------------------------------------------------------------
// Contenu d'un numéro
// ---------------------------------------------------------------------------

/**
 * Construit l'instantané du mois, vu par un compte donné.
 *
 * @return array{periode: string, libelle: string, sections: list<array>, chiffres: list<array>}
 */
function gazette_contenu(string $periode, int $compteId): array
{
    $bornes = gazette_mois_bornes($periode);

    $contenu = gazette_comme_compte($compteId, static function () use ($periode, $bornes): array {
        $sections = [];

        // 1. Les articles publiés dans le mois.
        $params   = [];
        $clause   = article_visible_clause('a', $params);
        $params[':gd0'] = $bornes['debut'];
        $params[':gf0'] = $bornes['fin'];
        $articles = db_all(
            "SELECT a.* FROM articles a
             WHERE {$clause} AND a.published_at >= :gd0 AND a.published_at < :gf0
             ORDER BY a.published_at DESC LIMIT " . GAZETTE_MAX_PAR_ENCART,
            $params
        );
        $items = [];
        foreach ($articles as $article) {
            $items[] = [
                'cle'    => 'article:' . (int) $article['id'],
                'titre'  => (string) $article['title'],
                'meta'   => trim((string) $article['category']),
                'date'   => format_date((int) $article['published_at']),
                'resume' => mb_substr(trim((string) $article['summary']), 0, 200),
                'url'    => '/articles/' . rawurlencode((string) $article['slug']),
            ];
        }
        $sections[] = ['cle' => 'articles', 'items' => $items];

        // 2. Les photos et les albums arrivés dans le mois.
        $params = [];
        $clause = visibility_clause('document', 'd', $params);
        $params[':gd1'] = $bornes['debut'];
        $params[':gf1'] = $bornes['fin'];
        $photos = db_all(
            "SELECT d.* FROM documents d
             WHERE {$clause} AND d.kind = 'photo' AND d.is_attachment = 0
               AND d.created_at >= :gd1 AND d.created_at < :gf1
             ORDER BY d.created_at DESC LIMIT " . GAZETTE_MAX_PAR_ENCART,
            $params
        );
        $items = [];
        foreach ($photos as $photo) {
            $items[] = [
                'cle'   => 'photo:' . (int) $photo['id'],
                'titre' => (string) $photo['title'],
                'meta'  => '',
                'date'  => format_date((int) $photo['created_at']),
                'url'   => '/documents/' . (int) $photo['id'],
            ];
        }
        $params = [];
        $clause = album_visible_clause('al', $params);
        $params[':gd2'] = $bornes['debut'];
        $params[':gf2'] = $bornes['fin'];
        $albums = db_all(
            "SELECT al.* FROM albums al
             WHERE {$clause} AND al.created_at >= :gd2 AND al.created_at < :gf2
             ORDER BY al.created_at DESC LIMIT " . GAZETTE_MAX_PAR_ENCART,
            $params
        );
        foreach ($albums as $album) {
            $items[] = [
                'cle'   => 'album:' . (int) $album['id'],
                'titre' => (string) $album['title'],
                'meta'  => 'Album',
                'date'  => format_date((int) $album['created_at']),
                'url'   => '/albums/' . rawurlencode((string) $album['slug']),
            ];
        }
        $sections[] = ['cle' => 'photos', 'items' => $items];

        // 3. Les vidéos.
        $params = [];
        $clause = visibility_clause('document', 'd', $params);
        $params[':gd3'] = $bornes['debut'];
        $params[':gf3'] = $bornes['fin'];
        $videos = db_all(
            "SELECT d.* FROM documents d
             WHERE {$clause} AND d.kind = 'video' AND d.is_attachment = 0
               AND d.created_at >= :gd3 AND d.created_at < :gf3
             ORDER BY d.created_at DESC LIMIT " . GAZETTE_MAX_PAR_ENCART,
            $params
        );
        $items = [];
        foreach ($videos as $video) {
            $items[] = [
                'cle'   => 'video:' . (int) $video['id'],
                'titre' => (string) $video['title'],
                'meta'  => '',
                'date'  => format_date((int) $video['created_at']),
                'url'   => '/videos/' . (int) $video['id'],
            ];
        }
        $sections[] = ['cle' => 'videos', 'items' => $items];

        // 4. Les témoignages sonores.
        $params = [];
        $clause = visibility_clause('document', 'd', $params);
        $params[':gd4'] = $bornes['debut'];
        $params[':gf4'] = $bornes['fin'];
        $audios = db_all(
            "SELECT d.* FROM documents d
             WHERE {$clause} AND d.mime LIKE 'audio/%'
               AND d.created_at >= :gd4 AND d.created_at < :gf4
             ORDER BY d.created_at DESC LIMIT " . GAZETTE_MAX_PAR_ENCART,
            $params
        );
        $items = [];
        foreach ($audios as $audio) {
            $items[] = [
                'cle'   => 'temoignage:' . (int) $audio['id'],
                'titre' => (string) $audio['title'],
                'meta'  => '',
                'date'  => format_date((int) $audio['created_at']),
                'url'   => '/temoignages',
            ];
        }
        $sections[] = ['cle' => 'temoignages', 'items' => $items];

        // 5. Les recettes.
        $params = [];
        $clause = recipe_visible_clause('r', $params);
        $params[':gd5'] = $bornes['debut'];
        $params[':gf5'] = $bornes['fin'];
        $recettes = db_all(
            "SELECT r.* FROM recipes r
             WHERE {$clause} AND r.created_at >= :gd5 AND r.created_at < :gf5
             ORDER BY r.created_at DESC LIMIT " . GAZETTE_MAX_PAR_ENCART,
            $params
        );
        $items = [];
        foreach ($recettes as $recette) {
            $items[] = [
                'cle'   => 'recette:' . (int) $recette['id'],
                'titre' => (string) $recette['title'],
                'meta'  => trim((string) $recette['origin']),
                'date'  => format_date((int) $recette['created_at']),
                'url'   => '/recettes/' . rawurlencode((string) $recette['slug']),
            ];
        }
        $sections[] = ['cle' => 'recettes', 'items' => $items];

        // 6. Les personnes ajoutées à l'arbre.
        $params = [];
        $clause = visibility_clause('person', 'p', $params);
        $params[':gd6'] = $bornes['debut'];
        $params[':gf6'] = $bornes['fin'];
        $personnes = db_all(
            "SELECT p.* FROM persons p
             WHERE {$clause} AND p.created_at >= :gd6 AND p.created_at < :gf6
             ORDER BY p.created_at DESC LIMIT " . GAZETTE_MAX_PAR_ENCART,
            $params
        );
        $items = [];
        foreach ($personnes as $personne) {
            $items[] = [
                'cle'   => 'personne:' . (int) $personne['id'],
                'titre' => person_full_name($personne),
                'meta'  => person_dates($personne),
                'date'  => '',
                'url'   => '/arbre/' . (int) $personne['id'],
            ];
        }
        $sections[] = ['cle' => 'personnes', 'items' => $items];

        // 7. Les petites annonces (anniversaires des 30 prochains jours).
        $items = [];
        foreach (gazette_annonces(6) as $annonce) {
            $items[] = [
                'cle'   => 'annonce:' . md5((string) $annonce['texte']),
                'titre' => (string) $annonce['texte'],
                'meta'  => '',
                'date'  => $annonce['jour'] . '/' . $annonce['mois'],
                'url'   => (string) $annonce['lien'],
            ];
        }
        $sections[] = ['cle' => 'annonces', 'items' => $items];

        // 8. Les chiffres du mois.
        $chiffres = [];
        foreach ($sections as $section) {
            if ($section['items'] !== []) {
                $chiffres[] = ['libelle' => gazette_section_titre($section['cle']), 'valeur' => count($section['items'])];
            }
        }
        $chiffres[] = ['libelle' => 'Personnes dans l’arbre', 'valeur' => count_visible('persons', 'person')];

        return [
            'periode'  => $periode,
            'libelle'  => gazette_mois_libelle($periode),
            'sections' => $sections,
            'chiffres' => $chiffres,
        ];
    });

    return $contenu;
}

/** Titre réglable d'un encart de la gazette. */
function gazette_section_titre(string $cle): string
{
    $defauts = [
        'articles'    => 'Les articles du mois',
        'photos'      => 'Photos et albums',
        'videos'      => 'Vidéos',
        'temoignages' => 'Témoignages sonores',
        'recettes'    => 'Recettes',
        'personnes'   => 'Nouvelles personnes',
        'annonces'    => 'Petites annonces',
    ];

    if ($cle === 'annonces') {
        return apparence_titre_encart('annonces');
    }

    return apparence_titre_encart('gazette_' . $cle, $defauts[$cle] ?? $cle);
}

/**
 * Le numéro a-t-il au moins un élément ? (sinon : rien à envoyer)
 *
 * Les « petites annonces » ne comptent pas : elles sont toujours là (les
 * anniversaires du mois qui vient) et ne font pas un numéro. Un mois où il ne
 * s'est rien passé ne produit donc aucun envoi.
 */
function gazette_contenu_vide(array $contenu): bool
{
    foreach ((array) ($contenu['sections'] ?? []) as $section) {
        if (($section['cle'] ?? '') === 'annonces') {
            continue;
        }
        if (($section['items'] ?? []) !== []) {
            return false;
        }
    }
    return true;
}

/** Le numéro a-t-il au moins un élément après retrait ? */
function gazette_apres_retrait(array $contenu, array $retires): array
{
    foreach ($contenu['sections'] as $index => $section) {
        $contenu['sections'][$index]['items'] = array_values(array_filter(
            (array) $section['items'],
            static fn (array $item): bool => !in_array((string) $item['cle'], $retires, true)
        ));
    }
    return $contenu;
}

/** Clés des éléments d'un numéro (pour retirer / remettre). */
function gazette_cles(array $contenu): array
{
    $cles = [];
    foreach ((array) ($contenu['sections'] ?? []) as $section) {
        foreach ((array) $section['items'] as $item) {
            $cles[(string) $item['cle']] = (string) $item['titre'];
        }
    }
    return $cles;
}

// ---------------------------------------------------------------------------
// Préparation
// ---------------------------------------------------------------------------

/**
 * Prépare les numéros du mois écoulé, un par groupe de droits.
 *
 * @return array{prepares: int, groupes: list<string>, rien: bool}
 */
function gazette_preparer(?int $maintenant = null): array
{
    $maintenant = $maintenant ?? time();
    $periode    = gazette_mois_ecoule($maintenant);

    $resultat = ['prepares' => 0, 'groupes' => [], 'rien' => false];

    foreach (gazette_profils() as $groupe => $profil) {
        $existe = db_value(
            'SELECT id FROM gazettes WHERE periode = ? AND groupe = ?',
            [$periode, $groupe],
            null
        );
        if ($existe !== null) {
            continue;
        }

        $contenu = gazette_contenu($periode, (int) $profil['representant']);
        if (gazette_contenu_vide($contenu)) {
            $resultat['rien'] = true;
            continue;
        }

        $maintenant = time();
        db_insert('gazettes', [
            'periode'    => $periode,
            'groupe'     => $groupe,
            'status'     => 'a_valider',
            'mot'        => '',
            'contenu'    => json_encode($contenu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'retire'     => '[]',
            'created_at' => $maintenant,
            'updated_at' => $maintenant,
        ]);

        $resultat['prepares']++;
        $resultat['groupes'][] = gazette_groupe_libelle($groupe);
    }

    return $resultat;
}

// ---------------------------------------------------------------------------
// Lecture
// ---------------------------------------------------------------------------

/** Une ligne de gazette, avec son contenu décodé. */
function gazette_ligne(array $ligne): array
{
    $ligne['contenu_array'] = json_decode((string) $ligne['contenu'], true) ?: ['sections' => [], 'chiffres' => []];
    $ligne['retires']       = json_decode((string) $ligne['retire'], true) ?: [];
    if (!is_array($ligne['retires'])) {
        $ligne['retires'] = [];
    }
    $ligne['apercu'] = gazette_apres_retrait($ligne['contenu_array'], $ligne['retires']);

    return $ligne;
}

function gazette_trouver(int $id): ?array
{
    $ligne = db_one('SELECT * FROM gazettes WHERE id = ?', [$id]);
    return $ligne === null ? null : gazette_ligne($ligne);
}

/** @return list<array> */
function gazette_liste(?string $periode = null): array
{
    $lignes = $periode === null
        ? db_all("SELECT * FROM gazettes ORDER BY periode DESC, groupe")
        : db_all('SELECT * FROM gazettes WHERE periode = ? ORDER BY groupe', [$periode]);

    return array_map('gazette_ligne', $lignes);
}

/** Numéro d'un mois pour un compte donné (ou null). */
function gazette_pour_compte(array $compte, string $periode): ?array
{
    $ligne = db_one(
        'SELECT * FROM gazettes WHERE periode = ? AND groupe = ?',
        [$periode, gazette_groupe_de_compte($compte)]
    );
    if ($ligne === null) {
        return null;
    }

    $gazette = gazette_ligne($ligne);

    // Un numéro encore « à valider » n'est lisible que par l'administration
    // (aperçu) ; tout le monde le lit une fois envoyé.
    $admin = compte_type($compte) === 'admin';
    if ($gazette['status'] === 'passe' && !$admin) {
        return null;
    }

    return $gazette;
}

// ---------------------------------------------------------------------------
// Geste de l'éditeur
// ---------------------------------------------------------------------------

/** Retire (ou remet) un élément du numéro. */
function gazette_retirer(int $id, string $cle, bool $retirer = true): bool
{
    $gazette = gazette_trouver($id);
    if ($gazette === null || $gazette['status'] !== 'a_valider') {
        return false;
    }
    if (!array_key_exists($cle, gazette_cles($gazette['contenu_array']))) {
        return false;
    }

    $retires = $gazette['retires'];
    if ($retirer) {
        if (!in_array($cle, $retires, true)) {
            $retires[] = $cle;
        }
    } else {
        $retires = array_values(array_filter($retires, static fn (string $c): bool => $c !== $cle));
    }

    db_update('gazettes', $id, [
        'retire'     => json_encode(array_values($retires), JSON_UNESCAPED_UNICODE),
        'updated_at' => time(),
    ]);

    return true;
}

/** Mot de l'éditeur (600 caractères, texte simple). */
function gazette_mot(int $id, string $texte): bool
{
    $gazette = gazette_trouver($id);
    if ($gazette === null || $gazette['status'] !== 'a_valider') {
        return false;
    }
    $texte = trim(preg_replace('/\s+/u', ' ', $texte) ?? '');
    if (mb_strlen($texte) > GAZETTE_MOT_MAX) {
        return false;
    }

    db_update('gazettes', $id, ['mot' => $texte, 'updated_at' => time()]);
    return true;
}

// ---------------------------------------------------------------------------
// Envoi
// ---------------------------------------------------------------------------

/** Message de la gazette pour un compte. */
function courriel_message_gazette(array $gazette, array $compte): array
{
    $contenu = $gazette['apercu'];
    $lien    = courriel_adresse_site() . '/gazette/' . rawurlencode((string) $gazette['periode']);

    $paragraphes = [];
    if (trim((string) $gazette['mot']) !== '') {
        $paragraphes[] = trim((string) $gazette['mot']);
    }

    foreach ((array) $contenu['sections'] as $section) {
        $items = (array) ($section['items'] ?? []);
        if ($items === []) {
            continue;
        }
        $lignes = [gazette_section_titre((string) $section['cle']) . ' :'];
        foreach (array_slice($items, 0, 6) as $item) {
            $ligne = '• ' . (string) $item['titre'];
            if (trim((string) $item['meta']) !== '') {
                $ligne .= ' (' . (string) $item['meta'] . ')';
            }
            $lignes[] = $ligne;
        }
        if (count($items) > 6) {
            $lignes[] = '… et ' . (count($items) - 6) . ' autre(s), à lire en ligne.';
        }
        $paragraphes[] = implode("\n", $lignes);
    }

    $titre = 'La gazette de ' . gazette_mois_libelle((string) $gazette['periode']);

    $rendu = courriel_habiller($titre, $paragraphes, [
        'lien'           => $lien,
        'libelle_lien'   => 'Lire la gazette en ligne',
        'desinscription' => courriel_lien_desinscription((int) $compte['id'], 'gazette'),
        'sur_titre'      => 'Gazette du mois',
        'pied'           => 'Vous recevez ce message parce que vous avez coché « Je veux recevoir la gazette du mois ».',
    ]);

    return [
        'subject' => $titre,
        'text'    => $rendu['text'],
        'html'    => $rendu['html'],
    ];
}

/**
 * Met le numéro en file pour les personnes de son groupe.
 *
 * @return int nombre de messages mis en file (0 = rien à envoyer)
 */
function gazette_envoyer(int $id, ?callable $trace = null): int
{
    $gazette = gazette_trouver($id);
    if ($gazette === null || $gazette['status'] !== 'a_valider') {
        return 0;
    }
    if (gazette_contenu_vide($gazette['apercu'])) {
        return 0;
    }

    $periode = (string) $gazette['periode'];
    $groupe  = (string) $gazette['groupe'];
    $mis     = 0;

    foreach ((gazette_profils()[$groupe]['comptes'] ?? []) as $compteId) {
        $compte = db_one('SELECT * FROM accounts WHERE id = ?', [$compteId]);
        if ($compte === null) {
            continue;
        }
        $message = courriel_message_gazette($gazette, $compte);
        $idFile  = courriel_enfiler([
            'account_id' => $compteId,
            'kind'       => 'gazette',
            'recipient'  => (string) $compte['email'],
            'subject'    => $message['subject'],
            'text'       => $message['text'],
            'html'       => $message['html'],
            'dedupe'     => 'gazette:' . $periode . ':' . $compteId,
        ]);
        if ($idFile > 0) {
            $mis++;
            if ($trace !== null) {
                $trace('Gazette ' . gazette_mois_libelle($periode) . ' : message préparé pour '
                    . courriel_masquer((string) $compte['email']));
            }
        }
    }

    if ($mis > 0) {
        db_update('gazettes', $id, [
            'status'     => 'envoye',
            'sent_at'    => time(),
            'updated_at' => time(),
        ]);
        log_activity(null, 'gazette_envoyee', 'Gazette de ' . gazette_mois_libelle($periode)
            . ' mise en file pour ' . $mis . ' personne(s) (' . gazette_groupe_libelle($groupe) . ')');
    }

    return $mis;
}

/** « Passer ce mois-ci » : le numéro ne partira pas. */
function gazette_passer(int $id): bool
{
    $gazette = gazette_trouver($id);
    if ($gazette === null || $gazette['status'] !== 'a_valider') {
        return false;
    }

    db_update('gazettes', $id, ['status' => 'passe', 'updated_at' => time()]);
    log_activity(null, 'gazette_passee', 'Gazette de '
        . gazette_mois_libelle((string) $gazette['periode']) . ' passée ('
        . gazette_groupe_libelle((string) $gazette['groupe']) . ')');

    return true;
}

// ---------------------------------------------------------------------------
// Envoi automatique (désactivé par défaut)
// ---------------------------------------------------------------------------

function gazette_auto_active(): bool
{
    return site_setting('gazette_auto') === '1';
}

function gazette_auto_regler(bool $actif): void
{
    save_site_setting('gazette_auto', $actif ? '1' : '0');
}

/** Date à partir de laquelle l'envoi automatique est permis (1er + 3 jours). */
function gazette_grace(string $periode): int
{
    $bornes = gazette_mois_bornes($periode);
    return (int) strtotime('+' . GAZETTE_GRACE_JOURS . ' days', $bornes['fin']);
}

/**
 * Envoie les numéros restés « à valider » au-delà du délai de grâce, si
 * l'envoi automatique est activé.
 *
 * @return int nombre de numéros envoyés
 */
function gazette_valider_auto(?int $maintenant = null, ?callable $trace = null): int
{
    if (!gazette_auto_active()) {
        return 0;
    }
    $maintenant = $maintenant ?? time();

    $envoyes = 0;
    foreach (db_all("SELECT * FROM gazettes WHERE status = 'a_valider' ORDER BY periode, groupe") as $ligne) {
        if ($maintenant < gazette_grace((string) $ligne['periode'])) {
            continue;
        }
        $mis = gazette_envoyer((int) $ligne['id'], $trace);
        if ($mis > 0) {
            $envoyes++;
        }
    }

    return $envoyes;
}
