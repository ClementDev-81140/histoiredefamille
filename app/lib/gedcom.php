<?php
/**
 * Import GEDCOM (format 5.5 / 5.5.1).
 *
 * L'analyseur est volontairement tolérant : un fichier incomplet ne doit pas
 * faire échouer l'import. Les anomalies sont collectées dans « warnings »
 * et présentées à l'administrateur avant l'import définitif.
 *
 * IMPORTANT : les personnes importées sont créées avec la visibilité
 * « famille » (toutes les familles), JAMAIS « public ».
 */

declare(strict_types=1);

/** Limite de sécurité : nombre de lignes analysées. */
const GEDCOM_MAX_LINES = 500000;

/**
 * Lit et analyse un fichier GEDCOM.
 *
 * @return array{ok:bool, error:string, encoding:string, persons:array, families:array, warnings:array}
 */
function gedcom_parse_file(string $path): array
{
    $result = [
        'ok' => false, 'error' => '', 'encoding' => 'UTF-8',
        'persons' => [], 'families' => [], 'warnings' => [],
    ];

    if (!is_file($path) || !is_readable($path)) {
        $result['error'] = 'Fichier illisible.';
        return $result;
    }

    $maxBytes = (int) config('uploads.max_gedcom_mb', 25) * 1024 * 1024;
    if (filesize($path) > $maxBytes) {
        $result['error'] = 'Fichier GEDCOM trop volumineux.';
        return $result;
    }

    $raw = (string) file_get_contents($path);
    if (trim($raw) === '') {
        $result['error'] = 'Le fichier est vide.';
        return $result;
    }

    // --- Encodage -----------------------------------------------------------
    // GEDCOM est censé être en UTF-8, mais beaucoup de fichiers anciens sont
    // en ANSI/ISO-8859-1/Windows-1252 : on tente une conversion.
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
    if (!preg_match('//u', $raw)) {
        $converted = @mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        if (is_string($converted)) {
            $raw = $converted;
            $result['encoding'] = 'Windows-1252 (converti en UTF-8)';
            $result['warnings'][] = 'Le fichier n’était pas en UTF-8 : il a été converti depuis Windows-1252. Vérifiez les accents après import.';
        }
    }

    $raw = str_replace(["\r\n", "\r"], "\n", $raw);
    $lines = explode("\n", $raw);
    if (count($lines) > GEDCOM_MAX_LINES) {
        $result['error'] = 'Fichier GEDCOM trop long à analyser.';
        return $result;
    }

    // --- Analyse ligne par ligne -------------------------------------------
    // On construit des enregistrements : chaque niveau 0 ouvre un enregistrement,
    // les lignes suivantes (niveau 1, 2…) sont regroupées par étiquette.
    $records = [];
    $current = null;
    $badLines = 0;

    foreach ($lines as $lineNumber => $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (!preg_match('/^(\d+)\s+(?:(@[^@\s]+@)\s+)?([A-Za-z0-9_]+)(?:\s+(.*))?$/', $line, $m)) {
            $badLines++;
            continue;
        }

        $level = (int) $m[1];
        $xref  = $m[2] ?? '';
        $tag   = strtoupper($m[3]);
        $value = isset($m[4]) ? trim($m[4]) : '';

        if ($level === 0) {
            if ($current !== null) {
                $records[] = $current;
            }
            $current = ['xref' => $xref, 'tag' => $tag, 'value' => $value, 'items' => []];
            continue;
        }

        if ($current === null) {
            // Ligne orpheline avant tout enregistrement : on ignore.
            $badLines++;
            continue;
        }

        $current['items'][] = [
            'level' => $level,
            'tag'   => $tag,
            'value' => $value,
            'xref'  => $xref,
        ];
    }
    if ($current !== null) {
        $records[] = $current;
    }

    if ($badLines > 0) {
        $result['warnings'][] = sprintf(
            '%d ligne(s) non reconnue(s) ont été ignorées (fichier incomplet ?).',
            $badLines
        );
    }

    $hasGedcomHeader = false;
    $persons = [];
    $families = [];

    foreach ($records as $record) {
        if ($record['tag'] === 'HEAD') {
            $hasGedcomHeader = true;
            foreach ($record['items'] as $item) {
                if ($item['tag'] === 'CHAR' && stripos($item['value'], 'ANSEL') === 0) {
                    $result['warnings'][] = 'Le fichier est encodé en ANSEL : certains caractères accentués peuvent être incorrects.';
                }
                if ($item['tag'] === 'CHAR' && stripos($item['value'], 'UTF-8') !== 0) {
                    $result['warnings'][] = 'Encodage déclaré dans le fichier : ' . $item['value'] . '.';
                }
            }
            continue;
        }

        // Les références GEDCOM sont encadrées d'arobases (@I1@) : on les
        // normalise (I1) pour pouvoir relier personnes et familles.
        $xref = gedcom_normalize_xref($record['xref']);

        if ($record['tag'] === 'INDI' && $xref !== '') {
            $persons[$xref] = gedcom_parse_person($record, $result);
            continue;
        }

        if ($record['tag'] === 'FAM' && $xref !== '') {
            $families[$xref] = gedcom_parse_family($record);
            continue;
        }
    }

    if (!$hasGedcomHeader) {
        $result['warnings'][] = 'En-tête GEDCOM (HEAD) absent : le fichier est peut-être incomplet.';
    }
    if ($persons === []) {
        $result['error'] = 'Aucune personne (INDI) trouvée dans le fichier.';
        return $result;
    }

    // Contrôles de cohérence : les liens vers des personnes inconnues sont ignorés.
    foreach ($families as $ref => &$family) {
        foreach (['husband', 'wife'] as $role) {
            if ($family[$role] !== '' && !isset($persons[$family[$role]])) {
                $result['warnings'][] = sprintf('Famille %s : conjoint introuvable (%s), lien ignoré.', $ref, $family[$role]);
                $family[$role] = '';
            }
        }
        $children = [];
        foreach ($family['children'] as $childRef) {
            if (isset($persons[$childRef])) {
                $children[] = $childRef;
            } else {
                $result['warnings'][] = sprintf('Famille %s : enfant introuvable (%s), lien ignoré.', $ref, $childRef);
            }
        }
        $family['children'] = $children;
    }
    unset($family);

    // Déduplication des avertissements identiques.
    $result['warnings'] = array_values(array_unique($result['warnings']));
    $result['persons']  = $persons;
    $result['families'] = $families;
    $result['ok']       = true;
    return $result;
}

/** Extrait les informations d'un enregistrement INDI. */
function gedcom_parse_person(array $record, array &$result): array
{
    $person = [
        'xref'         => gedcom_normalize_xref($record['xref']),
        'first_name'   => '',
        'last_name'    => '',
        'sex'          => '',
        'birth_date'   => '',
        'birth_place'  => '',
        'death_date'   => '',
        'death_place'  => '',
        'notes'        => '',
        'famc'         => [],
        'fams'         => [],
    ];

    $currentEvent = null;

    foreach ($record['items'] as $item) {
        switch ($item['tag']) {
            case 'NAME':
                if ($person['last_name'] === '' && $person['first_name'] === '') {
                    [$given, $surname] = gedcom_split_name($item['value']);
                    $person['first_name'] = $given;
                    $person['last_name']  = $surname;
                }
                break;

            case 'SEX':
                $sex = strtoupper($item['value']);
                $person['sex'] = in_array($sex, ['M', 'F'], true) ? $sex : '';
                break;

            case 'BIRT':
            case 'DEAT':
                $currentEvent = $item['tag'] === 'BIRT' ? 'birth' : 'death';
                break;

            case 'DATE':
                if ($currentEvent !== null) {
                    $person[$currentEvent . '_date'] = gedcom_clean($item['value']);
                }
                break;

            case 'PLAC':
                if ($currentEvent !== null) {
                    $person[$currentEvent . '_place'] = gedcom_clean($item['value']);
                }
                break;

            case 'NOTE':
                $note = gedcom_clean($item['value']);
                if ($note !== '') {
                    $person['notes'] = trim($person['notes'] . "\n" . $note);
                }
                break;

            case 'FAMC':
                if (preg_match('/@([^@]+)@/', $item['value'], $m)) {
                    $person['famc'][] = $m[1];
                }
                break;

            case 'FAMS':
                if (preg_match('/@([^@]+)@/', $item['value'], $m)) {
                    $person['fams'][] = $m[1];
                }
                break;

            case '_UID':
            case 'RFN':
            case 'REFN':
                break;

            default:
                break;
        }
    }

    $person['famc'] = array_values(array_unique($person['famc']));
    $person['fams'] = array_values(array_unique($person['fams']));

    if ($person['first_name'] === '' && $person['last_name'] === '') {
        $result['warnings'][] = 'Une personne n’a pas de nom dans le fichier : elle sera nommée « Sans nom ».';
    }

    return $person;
}

/** Extrait les informations d'un enregistrement FAM. */
function gedcom_parse_family(array $record): array
{
    $family = [
        'xref'           => gedcom_normalize_xref($record['xref']),
        'husband'        => '',
        'wife'           => '',
        'children'       => [],
        'marriage_date'  => '',
        'marriage_place' => '',
        'notes'          => '',
    ];

    $inMarriage = false;

    foreach ($record['items'] as $item) {
        switch ($item['tag']) {
            case 'HUSB':
            case 'WIFE':
            case 'CHIL':
                if (preg_match('/@([^@]+)@/', $item['value'], $m)) {
                    if ($item['tag'] === 'HUSB') {
                        $family['husband'] = $m[1];
                    } elseif ($item['tag'] === 'WIFE') {
                        $family['wife'] = $m[1];
                    } else {
                        $family['children'][] = $m[1];
                    }
                }
                break;
            case 'MARR':
                $inMarriage = true;
                break;
            case 'DATE':
                if ($inMarriage) {
                    $family['marriage_date'] = gedcom_clean($item['value']);
                }
                break;
            case 'PLAC':
                if ($inMarriage) {
                    $family['marriage_place'] = gedcom_clean($item['value']);
                }
                break;
            case 'NOTE':
                $family['notes'] = gedcom_clean($item['value']);
                break;
        }
    }

    $family['children'] = array_values(array_unique($family['children']));
    return $family;
}

/**
 * « Jean Pierre /DUPONT/ » → ['Jean Pierre', 'DUPONT'].
 * Gère aussi les noms sans barres obliques.
 *
 * @return array{0:string, 1:string}
 */
function gedcom_split_name(string $value): array
{
    $value = trim($value);
    if ($value === '') {
        return ['', ''];
    }

    if (preg_match('#^(.*?)\s*/([^/]*)/\s*(.*)$#u', $value, $m)) {
        $given   = trim($m[1] . ' ' . $m[3]);
        $surname = trim($m[2]);
        return [gedcom_clean($given), gedcom_clean($surname)];
    }

    // Pas de barres : « DUPONT Jean » ou « Jean DUPONT ».
    $parts = preg_split('/\s+/u', $value) ?: [];
    if (count($parts) >= 2) {
        $last = array_pop($parts);
        return [gedcom_clean(implode(' ', $parts)), gedcom_clean($last)];
    }

    return [gedcom_clean($value), ''];
}

/** Normalise une référence GEDCOM : « @I1@ » → « I1 ». */
function gedcom_normalize_xref(string $value): string
{
    return trim(trim($value), '@');
}

/** Nettoie une valeur GEDCOM (marqueurs techniques retirés). */
function gedcom_clean(string $value): string
{
    $value = preg_replace('/@[^@]+@/', '', $value) ?? $value; // renvois supprimés
    return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
}

/**
 * Importe les données analysées dans la base.
 *
 * Les doublons sont repérés grâce à « gedcom_id » : un fichier peut être
 * réimporté sans créer de personnes en double.
 *
 * @return array{persons:int, persons_skipped:int, families:int, links:int, warnings:array}
 */
function gedcom_import(array $parsed, ?int $accountId): array
{
    $stats = [
        'persons' => 0, 'persons_skipped' => 0, 'families' => 0,
        'links' => 0, 'warnings' => [],
    ];

    if (empty($parsed['ok'])) {
        $stats['warnings'][] = $parsed['error'] ?? 'Analyse impossible.';
        return $stats;
    }

    $now = time();

    db_transaction(static function () use ($parsed, $accountId, $now, &$stats): void {
        $map = []; // xref GEDCOM → id en base

        foreach ($parsed['persons'] as $xref => $person) {
            $existing = db_one('SELECT id FROM persons WHERE gedcom_id = ?', [(string) $xref]);
            if ($existing) {
                $map[$xref] = (int) $existing['id'];
                $stats['persons_skipped']++;
                continue;
            }

            $id = db_insert('persons', [
                'gedcom_id'   => (string) $xref,
                'first_name'  => $person['first_name'] !== '' ? $person['first_name'] : 'Sans nom',
                'last_name'   => $person['last_name'],
                'sex'         => $person['sex'] !== '' ? $person['sex'] : null,
                'birth_date'  => $person['birth_date'] !== '' ? $person['birth_date'] : null,
                'birth_place' => $person['birth_place'] !== '' ? $person['birth_place'] : null,
                'death_date'  => $person['death_date'] !== '' ? $person['death_date'] : null,
                'death_place' => $person['death_place'] !== '' ? $person['death_place'] : null,
                'notes'       => $person['notes'] !== '' ? $person['notes'] : null,
                // Jamais « public » automatiquement.
                'visibility'  => 'famille',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            $map[$xref]       = $id;
            $stats['persons']++;
        }

        foreach ($parsed['families'] as $familyXref => $family) {
            $husbandId = $family['husband'] !== '' ? ($map[$family['husband']] ?? null) : null;
            $wifeId    = $family['wife'] !== '' ? ($map[$family['wife']] ?? null) : null;

            $childrenIds = [];
            foreach ($family['children'] as $childXref) {
                if (isset($map[$childXref])) {
                    $childrenIds[] = $map[$childXref];
                }
            }

            if ($husbandId === null && $wifeId === null && $childrenIds === []) {
                continue; // famille sans membre exploitable
            }

            // Une famille qui n'a ni conjoint ni enfant affichable est ignorée.
            $existing = db_one('SELECT id FROM families WHERE gedcom_id = ?', [(string) $familyXref]);
            if ($existing) {
                $familyId = (int) $existing['id'];
            } else {
                $familyId = db_insert('families', [
                    'gedcom_id'      => (string) $familyXref,
                    'husband_id'     => $husbandId,
                    'wife_id'        => $wifeId,
                    'marriage_date'  => $family['marriage_date'] !== '' ? $family['marriage_date'] : null,
                    'marriage_place' => $family['marriage_place'] !== '' ? $family['marriage_place'] : null,
                    'notes'          => $family['notes'] !== '' ? $family['notes'] : null,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
                $stats['families']++;
            }

            foreach ($childrenIds as $childId) {
                db_exec(
                    'INSERT OR IGNORE INTO family_children (family_id, person_id) VALUES (?, ?)',
                    [$familyId, $childId]
                );
                $stats['links']++;
            }
        }
    });

    log_activity($accountId, 'import_gedcom', sprintf(
        'Import GEDCOM : %d personne(s) ajoutée(s), %d ignorée(s), %d famille(s), %d lien(s).',
        $stats['persons'],
        $stats['persons_skipped'],
        $stats['families'],
        $stats['links']
    ));

    return $stats;
}

// ---------------------------------------------------------------------------
// Export GEDCOM (l'import existe depuis le début, l'export arrive ici)
// ---------------------------------------------------------------------------

/**
 * Nettoie un texte pour un fichier GEDCOM : pas de retour à la ligne dans une
 * valeur (il couperait l'enregistrement), « @ » doublé comme le veut la norme.
 * Fonction pure, testable sans base.
 */
function gedcom_export_text(string $texte): string
{
    $texte = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $texte);
    $texte = str_replace('@', '@@', $texte);
    $texte = preg_replace('/ {2,}/', ' ', $texte) ?? $texte;

    return trim($texte);
}

/** Mois français → abréviation GEDCOM. */
function gedcom_mois_abbr(): array
{
    return [
        1 => 'JAN', 2 => 'FEB', 3 => 'MAR', 4 => 'APR', 5 => 'MAY', 6 => 'JUN',
        7 => 'JUL', 8 => 'AUG', 9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DEC',
    ];
}

/**
 * Convertit une date écrite par la famille en date GEDCOM.
 *
 * « 14 mars 1901 » → « 14 MAR 1901 », « 1928-06-12 » → « 12 JUN 1928 »,
 * « juin 1928 » → « JUN 1928 », « vers 1930 » → « ABT 1930 »,
 * « 1901 » → « 1901 ». Une date incompréhensible est laissée telle quelle.
 * Fonction pure.
 */
function gedcom_export_date(?string $date): string
{
    $brut = trim((string) $date);
    if ($brut === '') {
        return '';
    }

    $approximatif = (bool) preg_match('/\b(vers|environ|about|abt)\b/iu', $brut);
    $parts = date_parts($brut);

    if ($parts === null) {
        return gedcom_export_text($brut);
    }

    $mois = gedcom_mois_abbr()[$parts['mois']] ?? '';

    if ($parts['jour'] > 0 && $mois !== '') {
        $valeur = sprintf('%d %s %d', $parts['jour'], $mois, $parts['annee']);
    } elseif ($mois !== '') {
        $valeur = sprintf('%s %d', $mois, $parts['annee']);
    } else {
        $valeur = (string) $parts['annee'];
    }

    return $approximatif ? 'ABT ' . $valeur : $valeur;
}

/** Écrit une ligne GEDCOM (niveau, étiquette, valeur). */
function gedcom_export_line(int $niveau, string $tag, string $valeur = ''): string
{
    $valeur = $valeur !== '' ? ' ' . $valeur : '';
    return $niveau . ' ' . $tag . $valeur . "\r\n";
}

/**
 * Écrit tout l'arbre au format GEDCOM 5.5.1 sur la sortie standard.
 *
 * L'écriture se fait personne par personne : même avec des milliers de fiches,
 * le Pi n'a jamais le fichier entier en mémoire.
 */
function gedcom_export_stream(): void
{
    $compteur = 0;

    echo gedcom_export_line(0, 'HEAD');
    echo gedcom_export_line(1, 'SOUR', 'HistoireDeFamille');
    echo gedcom_export_line(2, 'NAME', 'Histoire de famille');
    echo gedcom_export_line(2, 'VERS', '1.7');
    echo gedcom_export_line(1, 'DATE', gedcom_export_date(date('Y-m-d')));
    echo gedcom_export_line(1, 'GEDC');
    echo gedcom_export_line(2, 'VERS', '5.5.1');
    echo gedcom_export_line(2, 'FORM', 'LINEAGE-LINKED');
    echo gedcom_export_line(1, 'CHAR', 'UTF-8');

    $famillesDe = [];
    foreach (db_all('SELECT family_id, person_id FROM family_children') as $lien) {
        $famillesDe[(int) $lien['person_id']][] = (int) $lien['family_id'];
    }

    $unionsDe = [];
    foreach (db_all('SELECT id, husband_id, wife_id FROM families') as $famille) {
        foreach ([$famille['husband_id'], $famille['wife_id']] as $conjoint) {
            if ($conjoint !== null) {
                $unionsDe[(int) $conjoint][] = (int) $famille['id'];
            }
        }
    }

    foreach (db_all('SELECT * FROM persons ORDER BY id') as $personne) {
        $id = (int) $personne['id'];
        echo gedcom_export_line(0, '@I' . $id . '@', 'INDI');

        // La norme veut le nom de famille en majuscules : « Jean /MARTIN/ ».
        $nom = mb_strtoupper(gedcom_export_text((string) $personne['last_name']), 'UTF-8');
        $prenom = gedcom_export_text((string) $personne['first_name']);
        echo gedcom_export_line(1, 'NAME', trim($prenom . ' /' . $nom . '/'));
        if ($prenom !== '') {
            echo gedcom_export_line(2, 'GIVN', $prenom);
        }
        if ($nom !== '') {
            echo gedcom_export_line(2, 'SURN', $nom);
        }
        if (in_array($personne['sex'], ['M', 'F'], true)) {
            echo gedcom_export_line(1, 'SEX', (string) $personne['sex']);
        }

        if (trim((string) $personne['birth_date']) !== '' || trim((string) $personne['birth_place']) !== '') {
            echo gedcom_export_line(1, 'BIRT');
            if (trim((string) $personne['birth_date']) !== '') {
                echo gedcom_export_line(2, 'DATE', gedcom_export_date((string) $personne['birth_date']));
            }
            if (trim((string) $personne['birth_place']) !== '') {
                echo gedcom_export_line(2, 'PLAC', gedcom_export_text((string) $personne['birth_place']));
            }
        }

        if (trim((string) $personne['death_date']) !== '' || trim((string) $personne['death_place']) !== '') {
            echo gedcom_export_line(1, 'DEAT');
            if (trim((string) $personne['death_date']) !== '') {
                echo gedcom_export_line(2, 'DATE', gedcom_export_date((string) $personne['death_date']));
            }
            if (trim((string) $personne['death_place']) !== '') {
                echo gedcom_export_line(2, 'PLAC', gedcom_export_text((string) $personne['death_place']));
            }
        }

        foreach ($unionsDe[$id] ?? [] as $familleId) {
            echo gedcom_export_line(1, 'FAMS', '@F' . $familleId . '@');
        }
        foreach ($famillesDe[$id] ?? [] as $familleId) {
            echo gedcom_export_line(1, 'FAMC', '@F' . $familleId . '@');
        }

        if (trim((string) $personne['notes']) !== '') {
            echo gedcom_export_line(1, 'NOTE', gedcom_export_text((string) $personne['notes']));
        }

        if (++$compteur % 200 === 0 && !class_exists('HdfTestArret', false)) {
            flush();
        }
    }

    foreach (db_all('SELECT * FROM families ORDER BY id') as $famille) {
        $id = (int) $famille['id'];
        echo gedcom_export_line(0, '@F' . $id . '@', 'FAM');
        if ($famille['husband_id'] !== null) {
            echo gedcom_export_line(1, 'HUSB', '@I' . (int) $famille['husband_id'] . '@');
        }
        if ($famille['wife_id'] !== null) {
            echo gedcom_export_line(1, 'WIFE', '@I' . (int) $famille['wife_id'] . '@');
        }

        if (trim((string) $famille['marriage_date']) !== '' || trim((string) $famille['marriage_place']) !== '') {
            echo gedcom_export_line(1, 'MARR');
            if (trim((string) $famille['marriage_date']) !== '') {
                echo gedcom_export_line(2, 'DATE', gedcom_export_date((string) $famille['marriage_date']));
            }
            if (trim((string) $famille['marriage_place']) !== '') {
                echo gedcom_export_line(2, 'PLAC', gedcom_export_text((string) $famille['marriage_place']));
            }
        }

        foreach (db_all('SELECT person_id FROM family_children WHERE family_id = ?', [$id]) as $enfant) {
            echo gedcom_export_line(1, 'CHIL', '@I' . (int) $enfant['person_id'] . '@');
        }

        if (trim((string) $famille['notes']) !== '') {
            echo gedcom_export_line(1, 'NOTE', gedcom_export_text((string) $famille['notes']));
        }
    }

    echo gedcom_export_line(0, 'TRLR');
}
