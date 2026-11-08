<?php
/**
 * Événements du calendrier (1.32.0).
 *
 * Le calendrier ne montrait jusqu'ici que les dates de l'arbre (naissances,
 * mariages, décès). Cette bibliothèque ajoute de VRAIS événements de famille :
 * fête, réunion, voyage, messe du souvenir… saisis dans l'administration.
 *
 * Règles tenues ici :
 *   - un nouvel événement est PRIVÉ par défaut (administrateurs seulement) ;
 *   - la visibilité suit les mêmes quatre niveaux que le reste du site, les
 *     branches passant par content_branches (kind = 'event') ;
 *   - un événement annuel revient chaque année, à la même date ;
 *   - un événement sans répétition n'apparaît que dans son année.
 *
 * 1.34.0 — propositions des journalistes : un événement peut naître
 * « en_attente » (colonne status, migration 022). Il est alors invisible
 * PARTOUT (calendrier, listes, compteurs), y compris pour les autres
 * journalistes ; seul son auteur le revoit (sa liste « en attente ») et les
 * administrateurs le valident, le modifient ou le suppriment depuis
 * Administration → Calendrier. La validation ne change ni sa visibilité
 * ni ses droits : elle le fait simplement passer à « publie ».
 */

declare(strict_types=1);

/** Types d'événement proposés dans le formulaire (clé => libellé). */
function evenement_types(): array
{
    return [
        'fete'         => 'Fête de famille',
        'reunion'      => 'Réunion',
        'voyage'       => 'Voyage',
        'souvenir'     => 'Messe du souvenir',
        'anniversaire' => 'Anniversaire',
        'autre'        => 'Autre événement',
    ];
}

/** Libellé d'un type d'événement ('' si le type est vide ou inconnu). */
function evenement_type_libelle(string $type): string
{
    return evenement_types()[$type] ?? '';
}

/** Champs d'un événement, tels que le formulaire les envoie. */
function evenement_valeurs(array $saisie): array
{
    $type = trim((string) ($saisie['type'] ?? ''));
    if (!array_key_exists($type, evenement_types())) {
        $type = '';
    }

    return [
        'type'        => $type,
        'title'       => trim((string) ($saisie['title'] ?? '')),
        'start_date'  => trim((string) ($saisie['start_date'] ?? '')),
        'start_time'  => trim((string) ($saisie['start_time'] ?? '')),
        'end_date'    => trim((string) ($saisie['end_date'] ?? '')),
        'end_time'    => trim((string) ($saisie['end_time'] ?? '')),
        'place'       => trim((string) ($saisie['place'] ?? '')),
        'description' => trim((string) ($saisie['description'] ?? '')),
        'visibility'  => sanitize_visibility((string) ($saisie['visibility'] ?? 'prive')),
        'annual'      => (string) ($saisie['annual'] ?? '') === '1' ? 1 : 0,
        'branches'    => array_values(array_unique(array_filter(array_map('intval', (array) ($saisie['branches'] ?? []))))),
    ];
}

/**
 * Vérifie une saisie et range les valeurs.
 *
 * @return array{0: array, 1: list<string>} valeurs, erreurs
 */
function evenement_valider(array $saisie): array
{
    $valeurs = evenement_valeurs($saisie);
    $erreurs = [];

    if ($valeurs['title'] === '' || mb_strlen($valeurs['title']) > 120) {
        $erreurs[] = 'Le titre de l’événement est obligatoire (120 caractères maximum).';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeurs['start_date']) !== 1
        || date_parts($valeurs['start_date']) === null) {
        $erreurs[] = 'La date de début est obligatoire (format AAAA-MM-JJ).';
        $valeurs['start_date'] = '';
    }
    foreach (['start_time' => 'L’heure de début', 'end_time' => 'L’heure de fin'] as $cle => $libelle) {
        if ($valeurs[$cle] !== '' && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $valeurs[$cle]) !== 1) {
            $erreurs[] = $libelle . ' doit s’écrire HH:MM (par exemple 15:00).';
            $valeurs[$cle] = '';
        }
    }
    if ($valeurs['end_date'] !== '') {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeurs['end_date']) !== 1 || date_parts($valeurs['end_date']) === null) {
            $erreurs[] = 'La date de fin doit s’écrire AAAA-MM-JJ.';
            $valeurs['end_date'] = '';
        } elseif ($valeurs['start_date'] !== '' && $valeurs['end_date'] < $valeurs['start_date']) {
            $erreurs[] = 'La date de fin ne peut pas précéder la date de début.';
        }
    }
    if ($valeurs['end_time'] !== '' && $valeurs['end_date'] === '' && $valeurs['start_time'] !== ''
        && $valeurs['end_time'] < $valeurs['start_time']) {
        $erreurs[] = 'L’heure de fin ne peut pas précéder l’heure de début du même jour.';
    }
    if (mb_strlen($valeurs['place']) > 120) {
        $erreurs[] = 'Le lieu est limité à 120 caractères.';
    }
    if (mb_strlen($valeurs['description']) > 2000) {
        $erreurs[] = 'La description est limitée à 2000 caractères.';
    }
    if (!array_key_exists($valeurs['visibility'], visibility_levels())) {
        $valeurs['visibility'] = 'prive';
    }
    if ($valeurs['visibility'] === 'branches' && $valeurs['branches'] === []) {
        $erreurs[] = 'Visibilité « Branches précises » : cochez au moins une branche.';
    }

    return [$valeurs, $erreurs];
}

/** Un événement par identifiant (même privé : l'appelant vérifie les droits). */
function evenement_par_id(int $id): ?array
{
    return db_one('SELECT * FROM events WHERE id = ?', [$id]);
}

/**
 * L'événement est-il visible par la personne qui regarde ?
 *
 * Les administrateurs voient tout ; un visiteur ne voit que le public ; un
 * compte de famille voit « famille » et ses branches.
 */
function evenement_visible(array $evenement): bool
{
    // 1.34.0 : un événement en attente de validation n'existe pour personne
    // dans les affichages (ni calendrier, ni listes, ni compteurs). Une base
    // pas encore migrée n'a pas la colonne : elle vaut « publie ».
    if ((string) ($evenement['status'] ?? 'publie') !== 'publie') {
        return false;
    }

    return can_view(
        (string) ($evenement['visibility'] ?? 'prive'),
        content_branch_ids('event', (int) ($evenement['id'] ?? 0))
    );
}

/**
 * Les événements qu'un compte a proposés et qui attendent validation.
 * Ils ne sont montrés qu'à leur auteur (et, dans l'administration, aux
 * administrateurs) : c'est la seule liste où un événement en attente
 * apparaît.
 *
 * @return list<array>
 */
function evenements_en_attente_de(int $compteId): array
{
    if ($compteId <= 0) {
        return [];
    }

    try {
        return db_all(
            "SELECT * FROM events WHERE status = 'en_attente' AND created_by = ? ORDER BY start_date, title COLLATE NOCASE",
            [$compteId]
        );
    } catch (Throwable) {
        return []; // base pas encore migrée (colonne status absente)
    }
}

/** Valide un événement en attente : il passe à « publie », droits inchangés. */
function evenement_valider_publication(int $id): void
{
    db_update('events', $id, ['status' => 'publie', 'updated_at' => time()]);
}

/** Événements visibles, du plus proche au plus lointain (calendrier, listes). */
function evenements_visibles(int $limite = 200): array
{
    $lignes = db_all('SELECT * FROM events ORDER BY start_date, start_time, title COLLATE NOCASE LIMIT ' . max(1, min(2000, $limite)));

    return array_values(array_filter($lignes, 'evenement_visible'));
}

/** Crée un événement et rend son identifiant (0 si la saisie est refusée). */
function evenement_creer(array $valeurs, ?int $compteId = null, string $statut = 'publie'): int
{
    $id = db_insert('events', [
        'status'      => $statut === 'en_attente' ? 'en_attente' : 'publie',
        'type'        => (string) ($valeurs['type'] ?? ''),
        'title'       => (string) $valeurs['title'],
        'start_date'  => (string) $valeurs['start_date'],
        'start_time'  => $valeurs['start_time'] !== '' ? (string) $valeurs['start_time'] : null,
        'end_date'    => $valeurs['end_date'] !== '' ? (string) $valeurs['end_date'] : null,
        'end_time'    => $valeurs['end_time'] !== '' ? (string) $valeurs['end_time'] : null,
        'place'       => (string) $valeurs['place'],
        'description' => (string) $valeurs['description'],
        'visibility'  => (string) $valeurs['visibility'],
        'annual'      => (int) $valeurs['annual'],
        'created_by'  => $compteId,
        'created_at'  => time(),
        'updated_at'  => time(),
    ]);

    set_content_branches('event', $id, $valeurs['branches']);

    return $id;
}

/** Modifie un événement existant. */
function evenement_modifier(int $id, array $valeurs): void
{
    db_update('events', $id, [
        'type'        => (string) ($valeurs['type'] ?? ''),
        'title'       => (string) $valeurs['title'],
        'start_date'  => (string) $valeurs['start_date'],
        'start_time'  => $valeurs['start_time'] !== '' ? (string) $valeurs['start_time'] : null,
        'end_date'    => $valeurs['end_date'] !== '' ? (string) $valeurs['end_date'] : null,
        'end_time'    => $valeurs['end_time'] !== '' ? (string) $valeurs['end_time'] : null,
        'place'       => (string) $valeurs['place'],
        'description' => (string) $valeurs['description'],
        'visibility'  => (string) $valeurs['visibility'],
        'annual'      => (int) $valeurs['annual'],
        'updated_at'  => time(),
    ]);

    set_content_branches('event', $id, $valeurs['branches']);
}

/** Supprime un événement et ses liaisons de branches. */
function evenement_supprimer(int $id): void
{
    db_exec("DELETE FROM content_branches WHERE kind = 'event' AND content_id = ?", [$id]);
    db_exec('DELETE FROM events WHERE id = ?', [$id]);
}

/**
 * Les événements visibles d'un mois, groupés par jour.
 *
 * @param int $mois    mois demandé (1 à 12)
 * @param int $annee   année du calendrier affiché (pour les événements non
 *                     annuels, qui n'existent qu'une fois)
 * @return array<int, list<array>>
 */
function evenements_du_mois(int $mois, int $annee): array
{
    if ($mois < 1 || $mois > 12) {
        return [];
    }

    $jours = [];
    foreach (evenements_visibles(500) as $evenement) {
        if (!evenement_visible($evenement)) {
            continue;
        }
        $debut = date_parts((string) $evenement['start_date']);
        if ($debut === null || $debut['mois'] !== $mois || $debut['jour'] < 1) {
            continue;
        }
        $annuel = (int) $evenement['annual'] === 1;
        if (!$annuel && $debut['annee'] !== $annee) {
            continue;
        }
        $jours[$debut['jour']][] = $evenement;
    }

    ksort($jours);

    return $jours;
}

/**
 * Prochaine occurrence d'un événement à partir d'aujourd'hui (ou null).
 *
 * Un événement annuel revient chaque année ; les autres n'ont qu'une date.
 */
function evenement_prochaine_occurrence(array $evenement, ?DateTimeImmutable $aujourdhui = null): ?DateTimeImmutable
{
    $debut = date_parts((string) ($evenement['start_date'] ?? ''));
    if ($debut === null || $debut['jour'] < 1 || $debut['mois'] < 1) {
        return null;
    }
    $aujourdhui = $aujourdhui ?? new DateTimeImmutable('today');

    $annees = (int) $evenement['annual'] === 1
        ? [(int) $aujourdhui->format('Y'), (int) $aujourdhui->format('Y') + 1]
        : [$debut['annee']];

    foreach ($annees as $annee) {
        $date = DateTimeImmutable::createFromFormat(
            '!Y-n-j',
            sprintf('%04d-%d-%d', $annee, $debut['mois'], $debut['jour'])
        );
        if ($date === false) {
            continue;
        }
        if ($date >= $aujourdhui) {
            return $date;
        }
    }

    return null;
}

/**
 * Événements visibles dans les prochains jours, du plus proche au plus
 * lointain (bloc « Dans la famille »).
 *
 * @return list<array{id:int, titre:string, date:string, jour:int, mois:int, annee:int, heure:string, lieu:string}>
 */
function evenements_prochains(int $jours = 30, int $limite = 20, ?DateTimeImmutable $aujourdhui = null): array
{
    $aujourdhui = $aujourdhui ?? new DateTimeImmutable('today');
    $fin        = $aujourdhui->modify('+' . max(1, $jours) . ' days');
    $prochains  = [];

    foreach (evenements_visibles(500) as $evenement) {
        $date = evenement_prochaine_occurrence($evenement, $aujourdhui);
        if ($date === null || $date > $fin) {
            continue;
        }
        $prochains[] = [
            'id'     => (int) $evenement['id'],
            'titre'  => (string) $evenement['title'],
            'date'   => $date->format('Y-m-d'),
            'jour'   => (int) $date->format('j'),
            'mois'   => (int) $date->format('n'),
            'annee'  => (int) $date->format('Y'),
            'heure'  => (string) ($evenement['start_time'] ?? ''),
            'lieu'   => (string) $evenement['place'],
        ];
    }

    usort($prochains, static fn (array $a, array $b): int => [$a['date'], $a['titre']] <=> [$b['date'], $b['titre']]);

    return array_slice($prochains, 0, max(1, $limite));
}
