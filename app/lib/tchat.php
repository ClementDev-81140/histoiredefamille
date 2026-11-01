<?php
/**
 * Tchat de la famille : un seul salon commun aux comptes connectés.
 *
 * Aucune technologie lourde : les messages sont stockés en SQLite, lus par
 * interrogation périodique (toutes les 5 s, ralentie à 30 s quand rien ne
 * se passe) et envoyés par `fetch` avec jeton CSRF. Tout le JavaScript est
 * dans `public/assets/app.js` (aucun script en ligne : CSP « self »).
 *
 * Le texte est TOUJOURS affiché échappé (`e()`), puis enrichi : les adresses
 * web deviennent des liens sûrs (`rel="noopener noreferrer"`, jamais de
 * `javascript:`), les sauts de ligne sont conservés.
 */

declare(strict_types=1);

/** Le tchat est-il ouvert ? (réglable dans Administration → Réglages) */
function tchat_actif(): bool
{
    $valeur = site_setting('tchat_actif');
    // Réglage absent = comportement par défaut : ouvert.
    return $valeur === '' ? true : $valeur === '1';
}

/**
 * Durée de conservation des messages, en mois.
 * 0 = conservation sans limite. Valeur inconnue = 12 mois (un an).
 */
function tchat_conservation_mois(): int
{
    $valeur = (int) site_setting('tchat_conservation_mois');
    return in_array($valeur, [0, 3, 6, 12], true) ? $valeur : 12;
}

/**
 * Nettoie un prénom saisi : lettres, espace et tiret uniquement, 1 à 30
 * caractères, espaces multiples réduits, casse remise proprement
 * (« jean-pierre » → « Jean-Pierre », « JEAN » → « Jean »).
 *
 * @return string prénom prêt à l'affichage, ou '' si la saisie est refusée
 */
function tchat_nettoyer_prenom(string $brut): string
{
    $nom = preg_replace('/\s+/u', ' ', trim($brut)) ?? '';
    if ($nom === '' || !preg_match('/^[\p{L}][\p{L} \-]{0,29}$/u', $nom)) {
        return '';
    }

    // Casse propre, mot par mot (l'espace et le tiret séparent les mots).
    $morceaux = preg_split('/([ \-])/u', $nom, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    foreach ($morceaux as $index => $morceau) {
        if ($index % 2 === 1 || $morceau === '') {
            continue; // séparateur
        }
        $morceaux[$index] = mb_strtoupper(mb_substr($morceau, 0, 1, 'UTF-8'), 'UTF-8')
            . mb_strtolower(mb_substr($morceau, 1, null, 'UTF-8'), 'UTF-8');
    }

    $propre = implode('', $morceaux);

    return mb_strlen($propre, 'UTF-8') <= 30 ? $propre : '';
}

/** Prénom choisi par la personne connectée (gardé en session, modifiable). */
function tchat_prenom(): string
{
    return (string) ($_SESSION['tchat_prenom'] ?? '');
}

/**
 * Signature d'un message : « BRANCHE/Prénom », ou « ADMIN/nom affiché »
 * pour un administrateur. Chaîne vide si le prénom n'est pas encore choisi.
 */
function tchat_signature(array $account): string
{
    if (($account['role'] ?? '') === 'admin') {
        $nom = trim((string) ($account['display_name'] ?? ''));
        if ($nom === '') {
            $nom = (string) ($account['username'] ?? 'administrateur');
        }
        return 'ADMIN/' . $nom;
    }

    $code = (string) ($account['branch_code'] ?? '');
    if ($code === '') {
        $code = (string) ($account['username'] ?? '');
    }
    $prenom = tchat_prenom();

    return $prenom !== '' ? mb_strtoupper($code, 'UTF-8') . '/' . $prenom : '';
}

/**
 * Transforme une adresse détectée en lien sûr, ou renvoie null si elle doit
 * rester du simple texte.
 *
 * Seuls http et https sont acceptés : « javascript: », « data: » ou un
 * schéma farfelu ne deviennent jamais un lien. Le texte du lien comme sa
 * cible sont échappés ICI, une seule fois.
 */
function tchat_lien_adresse(string $adresse): ?string
{
    // « www.exemple.fr » fonctionne sans que l'on tape « https:// ».
    $href = preg_match('#^www\.#i', $adresse) === 1 ? 'https://' . $adresse : $adresse;

    $parties = parse_url($href);
    if (!is_array($parties) || !isset($parties['scheme'], $parties['host'])) {
        return null;
    }
    if (!in_array(strtolower((string) $parties['scheme']), ['http', 'https'], true)) {
        return null;
    }
    if ((string) $parties['host'] === '' || preg_match('/[\x00-\x20]/', $href) === 1) {
        return null;
    }

    return '<a href="' . e($href) . '" rel="noopener noreferrer" target="_blank">'
        . e($adresse) . '</a>';
}

/**
 * Met en forme le texte d'un message pour l'affichage.
 *
 * 1. les adresses sont repérées sur le texte BRUT (avant échappement), en
 *    s'arrêtant sur les espaces, les guillemets et les chevrons ;
 * 2. chaque morceau — texte normal, adresse, ponctuation finale — est échappé
 *    une seule fois : un « & » tapé reste un « & », et un guillemet de fin
 *    n'est jamais collé au bout du lien ;
 * 3. seules les adresses http et https deviennent des liens externes sûrs
 *    (« rel="noopener noreferrer" ») ;
 * 4. les retours à la ligne sont conservés.
 */
function tchat_texte(string $corps): string
{
    $brut = trim($corps);

    // Guillemets et chevrons terminent l'adresse : ils ne font pas partie du
    // lien (c'est ce qui produisait « &quot; » collé à la fin).
    $motif = '#(?<![\w@/])(?:https?://|www\.)[^\s<>"\'«»]+#iu';

    $sortie   = '';
    $position = 0;

    if (preg_match_all($motif, $brut, $trouvees, PREG_OFFSET_CAPTURE) > 0) {
        foreach ($trouvees[0] as [$trouvee, $debut]) {
            $longueur = strlen($trouvee);
            $adresse  = $trouvee;

            // Ponctuation collée en fin de phrase : elle reste hors du lien.
            $fin = '';
            while ($adresse !== '' && preg_match('/[.,;:!?…)\]}]$/u', $adresse) === 1) {
                $fin     = mb_substr($adresse, -1, 1, 'UTF-8') . $fin;
                $adresse = mb_substr($adresse, 0, -1, 'UTF-8');
            }

            $sortie  .= e(substr($brut, $position, $debut - $position));
            $sortie  .= tchat_lien_adresse($adresse) ?? e($adresse);
            $sortie  .= e($fin);
            $position = $debut + $longueur;
        }
    }

    $sortie .= e(substr($brut, $position));

    return nl2br($sortie, false);
}

/**
 * Messages du salon, du plus ancien au plus récent.
 *
 * @param int      $limite nombre maximum de messages
 * @param int|null $avant  ne garder que les messages plus anciens que cet identifiant
 * @param int|null $apres  ne garder que les messages plus récents que cet identifiant
 */
function tchat_messages(int $limite = 50, ?int $avant = null, ?int $apres = null): array
{
    $limite = max(1, min(200, $limite));
    $ou     = [];
    $params = [];

    if ($avant !== null) {
        $ou[]             = 'id < :avant';
        $params[':avant'] = $avant;
    }
    if ($apres !== null) {
        $ou[]             = 'id > :apres';
        $params[':apres'] = $apres;
    }

    $where = $ou === [] ? '' : ' WHERE ' . implode(' AND ', $ou);

    if ($apres !== null) {
        // Nouveaux messages : on les veut dans l'ordre d'arrivée.
        return db_all(
            "SELECT * FROM chat_messages{$where} ORDER BY id ASC LIMIT {$limite}",
            $params
        );
    }

    $lignes = db_all(
        "SELECT * FROM chat_messages{$where} ORDER BY id DESC LIMIT {$limite}",
        $params
    );

    return array_reverse($lignes);
}

/** Date (AAAA-MM-JJ) d'un message, pour placer les séparateurs de jour. */
function tchat_jour(int $messageId): string
{
    $horodatage = db_value('SELECT created_at FROM chat_messages WHERE id = ?', [$messageId]);
    return $horodatage === null ? '' : date('Y-m-d', (int) $horodatage);
}

/** Libellé d'un séparateur de jour : « Aujourd’hui, dimanche 1er novembre 2026 ». */
function tchat_jour_libelle(string $jour): string
{
    $ts = strtotime($jour . ' 12:00:00');
    if ($ts === false) {
        return $jour;
    }
    $texte = french_long_date($ts);
    if ($jour === date('Y-m-d')) {
        return 'Aujourd’hui, ' . $texte;
    }
    if ($jour === date('Y-m-d', strtotime('-1 day'))) {
        return 'Hier, ' . $texte;
    }
    return $texte;
}

/**
 * Prépare l'affichage d'une suite de messages (séparateurs de jour compris).
 *
 * Un seul endroit produit le HTML : la page du tchat, l'envoi d'un message et
 * l'interrogation périodique passent tous par ici, donc l'aspect ne peut pas
 * diverger entre le premier affichage et les messages arrivés ensuite.
 *
 * @param array  $messages      messages triés du plus ancien au plus récent
 * @param string $jourPrecedent jour du dernier message déjà affiché (AAAA-MM-JJ)
 * @return array{html:string, dernier:int, jour:string, nombre:int}
 */
function tchat_bloc_html(array $messages, string $jourPrecedent = ''): array
{
    $account = current_account();
    $moiId   = $account !== null ? (int) $account['id'] : 0;
    $admin   = is_admin();

    $html        = '';
    $jourCourant = $jourPrecedent;
    $dernier     = 0;

    foreach ($messages as $message) {
        $jour = date('Y-m-d', (int) $message['created_at']);
        if ($jour !== $jourCourant) {
            $html .= '<h3 class="tchat__jour"><span>' . e(tchat_jour_libelle($jour)) . '</span></h3>';
            $jourCourant = $jour;
        }
        $estMoi = $moiId > 0 && (int) $message['account_id'] === $moiId;

        ob_start();
        require dirname(__DIR__) . '/views/partials/message_tchat.php';
        $html .= (string) ob_get_clean();

        $dernier = (int) $message['id'];
    }

    return [
        'html'    => $html,
        'dernier' => $dernier,
        'jour'    => $jourCourant,
        'nombre'  => count($messages),
    ];
}

/**
 * Nombre de messages arrivés depuis la dernière visite de la personne.
 *
 * Une seule requête légère par page : c'est ce qui alimente la pastille
 * de la navigation. Aucune boucle, aucun sondage permanent.
 */
function tchat_non_lus(): int
{
    if (!tchat_actif() || !is_logged_in()) {
        return 0;
    }
    $account = current_account();
    if ($account === null) {
        return 0;
    }

    $vu = (int) ($_SESSION['tchat_vu'] ?? 0);
    if ($vu === 0) {
        // Première page vue : on part de maintenant, sans compter l'historique.
        $_SESSION['tchat_vu'] = time();
        return 0;
    }

    return (int) db_value(
        'SELECT COUNT(*) FROM chat_messages
         WHERE created_at > ? AND (account_id IS NULL OR account_id <> ?)',
        [$vu, (int) $account['id']],
        0
    );
}

/** Marque les messages comme vus (appelé en ouvrant le tchat). */
function tchat_marquer_vu(): void
{
    $_SESSION['tchat_vu'] = time();
}

/**
 * Limitation d'envoi : 1 message par seconde et 30 par minute et par compte.
 *
 * @return array{ok:bool, message:string}
 */
function tchat_peut_ecrire(array $account): array
{
    $id = (int) $account['id'];

    $dernier = db_value('SELECT MAX(created_at) FROM chat_messages WHERE account_id = ?', [$id]);
    if ($dernier !== null) {
        $attente = 1 - (time() - (int) $dernier);
        if ($attente > 0) {
            return ['ok' => false, 'message' => 'Patientez une seconde avant d’envoyer un autre message.'];
        }
    }

    $recent = (int) db_value(
        'SELECT COUNT(*) FROM chat_messages WHERE account_id = ? AND created_at > ?',
        [$id, time() - 60],
        0
    );
    if ($recent >= 30) {
        return [
            'ok'      => false,
            'message' => 'Vous avez envoyé 30 messages en une minute. Réessayez dans un moment.',
        ];
    }

    return ['ok' => true, 'message' => ''];
}

/**
 * Supprime les messages plus anciens que la durée de conservation.
 * Appelé par app/cli/maintenance.php (tâche hebdomadaire du dimanche).
 * Retourne le nombre de messages supprimés (0 si conservation illimitée).
 */
function tchat_purger(): int
{
    $mois = tchat_conservation_mois();
    if ($mois <= 0) {
        return 0;
    }

    // 30,44 jours par mois : le résultat reste très proche du calendrier.
    $limite = time() - (int) round($mois * 30.44 * 86400);

    return db_exec('DELETE FROM chat_messages WHERE created_at < ?', [$limite]);
}

/** Libellé de la durée de conservation (pour les réglages). */
function tchat_conservation_libelle(int $mois): string
{
    return match ($mois) {
        0       => 'Jamais (conservation illimitée)',
        3       => '3 mois',
        6       => '6 mois',
        default => '1 an',
    };
}
