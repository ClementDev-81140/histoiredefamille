<?php
/**
 * Données de DÉMONSTRATION (fictives) pour tester le site.
 *
 * Tous les noms, dates et documents créés ici sont inventés.
 * Les images sont générées à la volée ; aucun fichier réel n'est inclus.
 *
 * Utilisation :
 *   php app/cli/demo.php          (ajoute les données de démonstration)
 *   php app/cli/demo.php --force  (ajoute même si des contenus existent)
 *
 * Pour tout supprimer : supprimez la base et relancez les migrations
 * (voir README, section « Repartir de zéro »).
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

if (!is_cli()) {
    http_response_code(403);
    exit('Commande réservée à la ligne de commande.');
}

if (!db_is_installed()) {
    cli_err("La base n'est pas encore initialisée. Lancez d'abord :\n  php app/cli/migrate.php\n");
    exit(1);
}

$force = in_array('--force', $argv ?? [], true);

echo "Histoire de famille — données de démonstration (fictives)\n";
echo "--------------------------------------------------------\n\n";

$existingPersons = (int) db_value('SELECT COUNT(*) FROM persons', [], 0);
$existingDocs    = (int) db_value('SELECT COUNT(*) FROM documents', [], 0);

if (!$force && ($existingPersons > 0 || $existingDocs > 0)) {
    cli_err("La base contient déjà des données. Utilisez --force pour ajouter quand même.\n");
    exit(1);
}

$now = time();
$passwords = [];

// ---------------------------------------------------------------------------
// Branches et comptes
// ---------------------------------------------------------------------------
$branchesToCreate = [
    ['code' => 'MARTIN', 'name' => 'Famille Martin (démo)', 'see_all' => 1,
     'description' => 'Branche de démonstration qui voit toutes les branches.'],
    ['code' => 'DURAND', 'name' => 'Famille Durand (démo)', 'see_all' => 0,
     'description' => 'Branche de démonstration.'],
    ['code' => 'LEROY', 'name' => 'Famille Leroy (démo)', 'see_all' => 0,
     'description' => 'Branche de démonstration.'],
];

$branchIds = [];
foreach ($branchesToCreate as $spec) {
    $existing = db_one('SELECT * FROM branches WHERE code = ?', [$spec['code']]);
    if ($existing) {
        $branchIds[$spec['code']] = (int) $existing['id'];
        echo "  · branche {$spec['code']} déjà présente\n";
        continue;
    }

    $id = db_insert('branches', [
        'code'        => $spec['code'],
        'name'        => $spec['name'],
        'description' => $spec['description'],
        'see_all'     => $spec['see_all'],
        'is_active'   => 1,
        'created_at'  => $now,
        'updated_at'  => $now,
    ]);
    $branchIds[$spec['code']] = $id;

    $password = password_generate();
    $passwords[] = ['username' => $spec['code'], 'password' => $password, 'name' => $spec['name']];

    db_insert('accounts', [
        'username'      => $spec['code'],
        'display_name'  => $spec['name'],
        'password_hash' => password_make($password),
        'role'          => 'famille',
        'branch_id'     => $id,
        'is_active'     => 1,
        'created_at'    => $now,
        'updated_at'    => $now,
    ]);
    echo "  · branche {$spec['code']} créée\n";
}

// ---------------------------------------------------------------------------
// Comptes « personne » (démonstration)
// ---------------------------------------------------------------------------
// Deux personnes de la branche Martin et une de la branche Durand : de quoi
// voir la liste des comptes, l'écran d'aperçu et « Bonjour Élise ».
if ((int) db_value("SELECT COUNT(*) FROM accounts WHERE account_type = 'personne'", [], 0) === 0) {
    $personnes = [
        ['prenom' => 'Élise', 'nom' => 'Martin', 'branche' => 'MARTIN', 'see_all' => 0],
        ['prenom' => 'Jean-Paul', 'nom' => 'Le Gall', 'branche' => 'MARTIN', 'see_all' => 1],
        ['prenom' => 'Paul', 'nom' => 'Durand', 'branche' => 'DURAND', 'see_all' => 0],
    ];

    $reserves = [];
    foreach ($personnes as $personne) {
        if (!isset($branchIds[$personne['branche']])) {
            continue;
        }

        $identifiant = compte_identifiant_libre(
            compte_identifiant_base($personne['prenom'], $personne['nom']),
            0,
            $reserves
        );
        $reserves[] = $identifiant;

        $password = password_generate();
        db_insert('accounts', [
            'username'      => $identifiant,
            'display_name'  => $personne['prenom'],
            'password_hash' => password_make($password),
            'role'          => 'famille',
            'branch_id'     => $branchIds[$personne['branche']],
            'is_active'     => 1,
            'account_type'  => 'personne',
            'first_name'    => $personne['prenom'],
            'last_name'     => $personne['nom'],
            'see_all'       => $personne['see_all'],
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        $passwords[] = [
            'username' => $identifiant,
            'password' => $password,
            'name'     => $personne['prenom'] . ' ' . $personne['nom'] . ' (personne)',
        ];
        echo "  · personne " . $identifiant . " créée\n";
    }
}

// ---------------------------------------------------------------------------
// Catégories
// ---------------------------------------------------------------------------
$categoryIds = [];
$categories = [
    ['Portraits (démo)', 1],
    ['Actes et papiers (démo)', 2],
    ['Photos de famille (démo)', 3],
    ['Correspondance (démo)', 4],
];

foreach ($categories as [$name, $position]) {
    $existing = db_one('SELECT id FROM categories WHERE name = ?', [$name]);
    if ($existing) {
        $categoryIds[$name] = (int) $existing['id'];
        continue;
    }
    $categoryIds[$name] = db_insert('categories', [
        'name'       => $name,
        'slug'       => slugify($name),
        'position'   => $position,
        'created_at' => $now,
    ]);
}
echo "  · " . count($categoryIds) . " catégorie(s) prêtes\n";

// ---------------------------------------------------------------------------
// Personnes et couples
// ---------------------------------------------------------------------------
/** Crée une personne de démonstration. */
function demo_person(array $data): int
{
    $now = time();
    return db_insert('persons', [
        'first_name'  => $data['first_name'],
        'last_name'   => $data['last_name'],
        'sex'         => $data['sex'] ?? null,
        'birth_date'  => $data['birth_date'] ?? null,
        'birth_year'  => extract_year($data['birth_date'] ?? null),
        'birth_place' => $data['birth_place'] ?? null,
        'death_date'  => $data['death_date'] ?? null,
        'death_year'  => extract_year($data['death_date'] ?? null),
        'death_place' => $data['death_place'] ?? null,
        'notes'       => $data['notes'] ?? null,
        'visibility'  => $data['visibility'] ?? 'famille',
        'created_at'  => $now,
        'updated_at'  => $now,
    ]);
}

$jean  = demo_person([
    'first_name' => 'Jean', 'last_name' => 'Martin', 'sex' => 'M',
    'birth_date' => '14 mars 1901', 'birth_place' => 'Exempleville (fictif)',
    'death_date' => '2 février 1975', 'death_place' => 'Exempleville (fictif)',
    'notes'      => "Personnage **fictif** créé pour la démonstration.\n\n- Agriculteur\n- Aimait la photographie",
]);
$marie = demo_person([
    'first_name' => 'Marie', 'last_name' => 'Leroy', 'sex' => 'F',
    'birth_date' => '2 août 1905', 'birth_place' => 'Village-d’Exemple (fictif)',
    'death_date' => '18 novembre 1982',
    'notes'      => 'Personnage fictif de démonstration.',
]);
$paul  = demo_person([
    'first_name' => 'Paul', 'last_name' => 'Martin', 'sex' => 'M',
    'birth_date' => '9 janvier 1930', 'death_date' => '30 avril 2005',
    'birth_place' => 'Exempleville (fictif)',
]);
$anne  = demo_person([
    'first_name' => 'Anne', 'last_name' => 'Martin', 'sex' => 'F',
    'birth_date' => '21 juillet 1935',
]);
$suzanne = demo_person([
    'first_name' => 'Suzanne', 'last_name' => 'Durand', 'sex' => 'F',
    'birth_date' => '30 mai 1932',
]);
$claire = demo_person([
    'first_name' => 'Claire', 'last_name' => 'Martin', 'sex' => 'F',
    'birth_date' => '5 avril 1960',
]);
$luc = demo_person([
    'first_name' => 'Luc', 'last_name' => 'Martin', 'sex' => 'M',
    'birth_date' => '17 novembre 1963',
]);
$prive = demo_person([
    'first_name' => 'Fiche', 'last_name' => 'Privée',
    'notes'      => 'Exemple de fiche visible uniquement des administrateurs.',
    'visibility' => 'prive',
]);

// Couples : (conjoint 1, conjoint 2, date, lieu, enfants)
$couples = [
    [$jean, $marie, '12 juin 1928', 'Exempleville (fictif)', [$paul, $anne]],
    [$paul, $suzanne, '3 septembre 1958', null, [$claire, $luc]],
];

foreach ($couples as [$a, $b, $date, $place, $children]) {
    $familyId = db_insert('families', [
        'husband_id'     => $a,
        'wife_id'        => $b,
        'marriage_date'  => $date,
        'marriage_place' => $place,
        'created_at'     => $now,
        'updated_at'     => $now,
    ]);
    foreach ($children as $childId) {
        db_exec('INSERT OR IGNORE INTO family_children (family_id, person_id) VALUES (?, ?)', [$familyId, $childId]);
    }
}

// Une personne réservée à la branche MARTIN (test de visibilité).
db_exec("UPDATE persons SET visibility = 'branches' WHERE id = ?", [$anne]);
set_content_branches('person', $anne, [$branchIds['MARTIN']]);

// La fiche « privée » reste privée.
unset($prive);

echo "  · 8 personnes et 2 couples créés\n";

// ---------------------------------------------------------------------------
// Pages
// ---------------------------------------------------------------------------
function demo_page(array $data): int
{
    $now = time();
    $pageId = db_insert('pages', [
        'slug'         => $data['slug'],
        'title'        => $data['title'],
        'body'         => $data['body'],
        'visibility'   => $data['visibility'],
        'is_published' => 1,
        'show_in_nav'  => $data['nav'] ?? 0,
        'position'     => $data['position'] ?? 0,
        'created_at'   => $now,
        'updated_at'   => $now,
    ]);
    if (($data['visibility'] ?? '') === 'branches' && !empty($data['branches'])) {
        set_content_branches('page', $pageId, $data['branches']);
    }
    return $pageId;
}

if (db_one("SELECT id FROM pages WHERE slug = 'bienvenue'") === null) {
    demo_page([
        'slug'       => 'bienvenue',
        'title'      => 'Bienvenue (démo)',
        'nav'        => 1,
        'position'   => 1,
        'visibility' => 'public',
        'body'       => "Cette page est **publique** : elle est visible par tout le monde.\n\n"
            . "## À propos de ce site\n\n"
            . "Ce site rassemble les documents, les photos, les vidéos et l’arbre généalogique "
            . "d’une famille. Une partie est publique, une autre est réservée aux familles.\n\n"
            . "- Les documents **publics** sont visibles par tous\n"
            . "- Les documents **famille** nécessitent un compte\n"
            . "- Certains contenus sont réservés à une **branche** précise\n",
    ]);
    echo "  · page « Bienvenue » créée\n";
}

if (db_one("SELECT id FROM pages WHERE slug = 'chronique-fictive'") === null) {
    demo_page([
        'slug'       => 'chronique-fictive',
        'title'      => 'Chronique familiale (démo, réservée)',
        'nav'        => 1,
        'position'   => 2,
        'visibility' => 'famille',
        'body'       => "## Une histoire inventée\n\n"
            . "Ce texte est réservé aux comptes de famille connectés.\n\n"
            . "1. Les origines\n2. Les années de guerre\n3. Les retrouvailles\n\n"
            . "*Tout ceci est fictif et sert uniquement à tester le site.*\n",
    ]);
    echo "  · page « Chronique familiale » créée\n";
}

if (db_one("SELECT id FROM pages WHERE slug = 'notes-branche-martin'") === null) {
    demo_page([
        'slug'       => 'notes-branche-martin',
        'title'      => 'Notes de la branche Martin (démo)',
        'visibility' => 'branches',
        'branches'   => [$branchIds['MARTIN']],
        'position'   => 3,
        'body'       => "Page visible uniquement par la branche MARTIN (démonstration).\n",
    ]);
    echo "  · page réservée à une branche créée\n";
}

// ---------------------------------------------------------------------------
// Documents (images générées à la volée)
// ---------------------------------------------------------------------------
/** Crée une image de démonstration et renvoie son chemin relatif. */
function demo_image(string $title, string $subtitle, array $couleur): string
{
    $width  = 900;
    $height = 640;
    $image  = imagecreatetruecolor($width, $height);
    $fond   = imagecolorallocate($image, $couleur[0], $couleur[1], $couleur[2]);
    $blanc  = imagecolorallocate($image, 255, 255, 255);
    $sombre = imagecolorallocate($image, 35, 45, 55);

    imagefilledrectangle($image, 0, 0, $width, $height, $fond);
    imagefilledrectangle($image, 40, 40, $width - 40, $height - 40, $blanc);
    imagefilledrectangle($image, 40, $height - 140, $width - 40, $height - 40, $blanc);

    imagestring($image, 5, 80, 120, $title, $sombre);
    imagestring($image, 4, 80, 160, $subtitle, $sombre);
    imagestring($image, 3, 80, $height - 100, 'Image generee — demonstration (fictive)', $sombre);

    $rel  = storage_target_path('jpg');
    $abs  = storage_absolute($rel);
    imagejpeg($image, $abs, 85);
    imagedestroy($image);
    @chmod($abs, 0640);

    return $rel;
}

/** Construit un petit PDF valide (une page, texte simple). */
function demo_pdf(string $texte): string
{
    $content = "BT /F1 14 Tf 40 700 Td (" . str_replace(['(', ')'], '', $texte) . ") Tj ET";
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] '
            . '/Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
        '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream",
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }

    $xrefPosition = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefPosition . "\n%%EOF\n";

    return $pdf;
}

/** Construit un petit fichier WAV (4 secondes de notes douces, sans voix). */
function demo_audio(): string
{
    $frequence = 8000;          // suffisant pour un son de démonstration
    $duree     = 4;             // secondes
    $echantillons = $frequence * $duree;
    $donnees   = '';

    // Une mélodie très simple (deux notes), avec fondu d'entrée et de sortie.
    for ($i = 0; $i < $echantillons; $i++) {
        $t     = $i / $frequence;
        $note  = $t < 2 ? 392.0 : 329.63;             // sol4 puis mi4
        $gain  = 0.22 * sin(M_PI * min(1, $i / ($frequence * 0.15)))
                       * sin(M_PI * min(1, ($echantillons - $i) / ($frequence * 0.4)));
        $valeur = (int) round(127 + 120 * $gain * sin(2 * M_PI * $note * $t));
        $donnees .= chr(max(0, min(255, $valeur)));
    }

    $entete = 'RIFF' . pack('V', 36 + strlen($donnees)) . 'WAVE'
        . 'fmt ' . pack('V', 16) . pack('v', 1) . pack('v', 1)
        . pack('V', $frequence) . pack('V', $frequence) . pack('v', 1) . pack('v', 8)
        . 'data' . pack('V', strlen($donnees));

    $rel = storage_target_path('wav');
    $abs = storage_absolute($rel);
    file_put_contents($abs, $entete . $donnees);
    @chmod($abs, 0640);

    return $rel;
}

/**
 * Insère un document de démonstration à partir d'un fichier du stockage.
 *
 * @param array $data champs de la fiche ; les clés « kind », « is_featured »,
 *                    « display_order », « allow_download » et
 *                    « duration_seconds » sont facultatives.
 */
function demo_document(array $data, string $relPath, string $ext, string $mime, int $size): int
{
    $now = time();

    $thumb = null;
    $width = null;
    $height = null;
    if (str_starts_with($mime, 'image/')) {
        $sizeInfo = @getimagesize(storage_absolute($relPath));
        if (is_array($sizeInfo)) {
            $width  = (int) $sizeInfo[0];
            $height = (int) $sizeInfo[1];
        }
        $thumbRel = storage_target_thumb_path();
        $thumbResult = make_thumbnail(storage_absolute($relPath), storage_absolute($thumbRel));
        $thumb = $thumbResult['ok'] ? $thumbRel : null;
    }

    $docId = db_insert('documents', [
        'title'         => $data['title'],
        'description'   => $data['description'] ?? null,
        'doc_date'      => $data['doc_date'] ?? null,
        'doc_date_sort' => extract_year($data['doc_date'] ?? null),
        'category_id'   => $data['category_id'] ?? null,
        'kind'          => $data['kind'] ?? (str_starts_with($mime, 'image/') ? 'photo' : 'document'),
        'visibility'    => $data['visibility'],
        'is_featured'   => (int) ($data['is_featured'] ?? 0),
        'display_order' => (int) ($data['display_order'] ?? 0),
        'allow_download' => (int) ($data['allow_download'] ?? 1),
        'duration_seconds' => $data['duration_seconds'] ?? null,
        'rel_path'      => $relPath,
        'thumb_path'    => $thumb,
        'original_name' => $data['original_name'],
        'mime'          => $mime,
        'ext'           => $ext,
        'size'          => $size,
        'width'         => $width,
        'height'        => $height,
        'is_attachment' => 0,
        'created_at'    => $now,
        'updated_at'    => $now,
    ]);

    if (($data['visibility'] ?? '') === 'branches' && !empty($data['branches'])) {
        set_content_branches('document', $docId, $data['branches']);
    }
    if (!empty($data['tags'])) {
        save_document_tags($docId, $data['tags']);
    }

    return $docId;
}

echo "  · création des documents de démonstration…\n";

// 1. Portrait public
$rel = demo_image('Portrait fictif', 'Jean Martin, 1928 (personnage invente)', [200, 190, 175]);
demo_document([
    'title'       => 'Portrait de Jean Martin (démo)',
    'description' => "Photo **fictive** générée automatiquement pour la démonstration.\n\nElle illustre un document public.",
    'doc_date'    => '1928',
    'category_id' => $categoryIds['Portraits (démo)'],
    'visibility'  => 'public',
    'original_name' => 'portrait-demo.jpg',
    'tags'        => 'portrait, démonstration, 1928',
], $rel, 'jpg', 'image/jpeg', (int) filesize(storage_absolute($rel)));

// 2. Photo de mariage réservée aux familles
$rel = demo_image('Mariage fictif', 'Paul et Suzanne, 1958 (personnages inventes)', [180, 195, 205]);
demo_document([
    'title'       => 'Mariage de Paul et Suzanne (démo)',
    'description' => 'Photo de démonstration visible par tous les comptes de famille.',
    'doc_date'    => '1958',
    'category_id' => $categoryIds['Photos de famille (démo)'],
    'visibility'  => 'famille',
    'original_name' => 'mariage-demo.jpg',
    'tags'        => 'mariage, démonstration',
], $rel, 'jpg', 'image/jpeg', (int) filesize(storage_absolute($rel)));

// 3. Photo réservée à la branche MARTIN
$rel = demo_image('Reunion de famille', '1965 — visible par la branche MARTIN', [205, 190, 180]);
demo_document([
    'title'       => 'Réunion de famille 1965 (démo, branche Martin)',
    'description' => 'Document visible uniquement par la branche MARTIN.',
    'doc_date'    => '1965',
    'category_id' => $categoryIds['Photos de famille (démo)'],
    'visibility'  => 'branches',
    'branches'    => [$branchIds['MARTIN']],
    'original_name' => 'reunion-demo.jpg',
    'tags'        => 'famille, démonstration',
], $rel, 'jpg', 'image/jpeg', (int) filesize(storage_absolute($rel)));

// 4. Lettre (fichier texte)
$lettre = "Lettre fictive de démonstration\n\n"
    . "Ma chère Anne,\n\n"
    . "Ce fichier texte sert à tester le téléchargement de documents\n"
    . "qui ne s'affichent pas dans le navigateur.\n\n"
    . "Tout ceci est inventé.\n";
$rel = storage_target_path('txt');
file_put_contents(storage_absolute($rel), $lettre);
@chmod(storage_absolute($rel), 0640);
demo_document([
    'title'       => 'Lettre fictive de Marie (démo)',
    'description' => 'Fichier texte de démonstration (téléchargement).',
    'doc_date'    => '1950',
    'category_id' => $categoryIds['Correspondance (démo)'],
    'visibility'  => 'famille',
    'original_name' => 'lettre-demo.txt',
    'tags'        => 'lettre, démonstration',
], $rel, 'txt', 'text/plain', (int) filesize(storage_absolute($rel)));

// 5. PDF privé (visible des seuls administrateurs)
$pdf = demo_pdf('Acte fictif de demonstration - visible administrateurs uniquement');
$rel = storage_target_path('pdf');
file_put_contents(storage_absolute($rel), $pdf);
@chmod(storage_absolute($rel), 0640);
demo_document([
    'title'       => 'Acte fictif (démo, privé)',
    'description' => 'Ce PDF est privé : seuls les administrateurs le voient. Utile pour vérifier les droits.',
    'doc_date'    => '1931',
    'category_id' => $categoryIds['Actes et papiers (démo)'],
    'visibility'  => 'prive',
    'original_name' => 'acte-demo.pdf',
    'tags'        => 'acte, démonstration',
], $rel, 'pdf', 'application/pdf', (int) filesize(storage_absolute($rel)));

// 6. Témoignage sonore (petit fichier WAV généré, 4 secondes)
$rel = demo_audio();
demo_document([
    'title'       => 'Témoignage fictif de Jean (démo)',
    'description' => "Enregistrement **fictif** de quatre secondes, généré automatiquement : "
        . "il sert à montrer la rubrique « Témoignages ».",
    'doc_date'    => '1980',
    'category_id' => $categoryIds['Photos de famille (démo)'] ?? null,
    'visibility'  => 'famille',
    'kind'        => 'document',
    'original_name' => 'temoignage-demo.wav',
    'tags'        => 'témoignage, démonstration',
], $rel, 'wav', 'audio/wav', (int) filesize(storage_absolute($rel)));

echo "  · 6 documents de démonstration créés\n";

// ---------------------------------------------------------------------------
// Vidéos (un vrai fichier MP4 de 2 secondes, généré à l'avance)
// ---------------------------------------------------------------------------
/**
 * Écrit le petit film de démonstration dans le stockage et renvoie son chemin
 * relatif. Le fichier est un MP4 H.264 + AAC de 2 secondes : il sert à vérifier
 * la lecture en flux (HTTP Range) sans embarquer de vraie vidéo de famille.
 */
function demo_video_file(): string
{
    $base64 =         'AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAbYbW9vdgAAAGxtdmhkAAAAAAAAAAAAAAAAAAAD6AAAB9AAAQAA'
        . 'AQAAAAAAAAAAAAAAAAEAAAAAAAAAAAAAAAAAAAABAAAAAAAAAAAAAAAAAABAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
        . 'AAAAAwAAAqp0cmFrAAAAXHRraGQAAAADAAAAAAAAAAAAAAABAAAAAAAAB9AAAAAAAAAAAAAAAAAAAAAAAAEAAAAAAAAAAAAA'
        . 'AAAAAAABAAAAAAAAAAAAAAAAAABAAAAAAMAAAACQAAAAAAAkZWR0cwAAABxlbHN0AAAAAAAAAAEAAAfQAAAAAAABAAAAAAIi'
        . 'bWRpYQAAACBtZGhkAAAAAAAAAAAAAAAAAAAwAAAAYABVxAAAAAAALWhkbHIAAAAAAAAAAHZpZGUAAAAAAAAAAAAAAABWaWRl'
        . 'b0hhbmRsZXIAAAABzW1pbmYAAAAUdm1oZAAAAAEAAAAAAAAAAAAAACRkaW5mAAAAHGRyZWYAAAAAAAAAAQAAAAx1cmwgAAAA'
        . 'AQAAAY1zdGJsAAAAuXN0c2QAAAAAAAAAAQAAAKlhdmMxAAAAAAAAAAEAAAAAAAAAAAAAAAAAAAAAAMAAkABIAAAASAAAAAAA'
        . 'AAABFExhdmM2MS4zLjEwMCBsaWJ4MjY0AAAAAAAAAAAAAAAAGP//AAAAL2F2Y0MBQsAL/+EAF2dCwAvZAwTsBEAAAAMAQAAA'
        . 'AwMDxQqSAQAFaMuAxLIAAAAQcGFzcAAAAAEAAAABAAAAFGJ0cnQAAAAAAABBQAAAQUAAAAAYc3R0cwAAAAAAAAABAAAADAAA'
        . 'CAAAAAAUc3RzcwAAAAAAAAABAAAAAQAAABxzdHNjAAAAAAAAAAEAAAABAAAAAQAAAAEAAABEc3RzegAAAAAAAAAAAAAADAAA'
        . 'CDwAAAC/AAAAqQAAALgAAADeAAAA1gAAAOAAAADNAAAAsAAAAI8AAACiAAAAsgAAAEBzdGNvAAAAAAAAAAwAAAcbAAAPYwAA'
        . 'EC4AABDfAAARowAAEo0AABNrAAAUVwAAFSwAABXoAAAWgwAAFy0AAANZdHJhawAAAFx0a2hkAAAAAwAAAAAAAAAAAAAAAgAA'
        . 'AAAAAAfQAAAAAAAAAAAAAAABAQAAAAABAAAAAAAAAAAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAAJGVk'
        . 'dHMAAAAcZWxzdAAAAAAAAAABAAAH0AAABAAAAQAAAAAC0W1kaWEAAAAgbWRoZAAAAAAAAAAAAAAAAAAAPoAAAIEAVcQAAAAA'
        . 'AC1oZGxyAAAAAAAAAABzb3VuAAAAAAAAAAAAAAAAU291bmRIYW5kbGVyAAAAAnxtaW5mAAAAEHNtaGQAAAAAAAAAAAAAACRk'
        . 'aW5mAAAAHGRyZWYAAAAAAAAAAQAAAAx1cmwgAAAAAQAAAkBzdGJsAAAAfnN0c2QAAAAAAAAAAQAAAG5tcDRhAAAAAAAAAAEA'
        . 'AAAAAAAAAAABABAAAAAAPoAAAAAAADZlc2RzAAAAAAOAgIAlAAIABICAgBdAFQAAAAAAPoAAAAI5BYCAgAUUCFblAAaAgIAB'
        . 'AgAAABRidHJ0AAAAAAAAPoAAAAI5AAAAIHN0dHMAAAAAAAAAAgAAACAAAAQAAAAAAQAAAQAAAACIc3RzYwAAAAAAAAAKAAAA'
        . 'AQAAAAEAAAABAAAAAgAAAAMAAAABAAAABAAAAAIAAAABAAAABQAAAAMAAAABAAAABwAAAAIAAAABAAAACAAAAAMAAAABAAAA'
        . 'CQAAAAIAAAABAAAACgAAAAMAAAABAAAADAAAAAIAAAABAAAADQAAAAMAAAABAAAAmHN0c3oAAAAAAAAAAAAAACEAAAATAAAA'
        . 'BAAAAAQAAAAEAAAABAAAAAQAAAAEAAAABAAAAAQAAAAEAAAABAAAAAQAAAAEAAAABAAAAAQAAAAEAAAABAAAAAQAAAAEAAAA'
        . 'BAAAAAQAAAAEAAAABAAAAAQAAAAEAAAABAAAAAQAAAAEAAAABAAAAAQAAAAEAAAABAAAAAQAAABEc3RjbwAAAAAAAAANAAAH'
        . 'CAAAD1cAABAiAAAQ1wAAEZcAABKBAAATYwAAFEsAABUkAAAV3AAAFncAABclAAAX3wAAABpzZ3BkAQAAAHJvbGwAAAACAAAA'
        . 'Af//AAAAHHNiZ3AAAAAAcm9sbAAAAAEAAAAhAAAAAQAAAGF1ZHRhAAAAWW1ldGEAAAAAAAAAIWhkbHIAAAAAAAAAAG1kaXJh'
        . 'cHBsAAAAAAAAAAAAAAAALGlsc3QAAAAkqXRvbwAAABxkYXRhAAAAAQAAAABMYXZmNjEuMS4xMDAAAAAIZnJlZQAAEOttZGF0'
        . '3ABMYXZjNjEuMy4xMDAAAjBADgAAAnAGBf//bNxF6b3m2Ui3lizYINkj7u94MjY0IC0gY29yZSAxNjQgcjMxOTEgNDYxM2Fj'
        . 'MyAtIEguMjY0L01QRUctNCBBVkMgY29kZWMgLSBDb3B5bGVmdCAyMDAzLTIwMjQgLSBodHRwOi8vd3d3LnZpZGVvbGFuLm9y'
        . 'Zy94MjY0Lmh0bWwgLSBvcHRpb25zOiBjYWJhYz0wIHJlZj0zIGRlYmxvY2s9MTowOjAgYW5hbHlzZT0weDE6MHgxMTEgbWU9'
        . 'aGV4IHN1Ym1lPTcgcHN5PTEgcHN5X3JkPTEuMDA6MC4wMCBtaXhlZF9yZWY9MSBtZV9yYW5nZT0xNiBjaHJvbWFfbWU9MSB0'
        . 'cmVsbGlzPTEgOHg4ZGN0PTAgY3FtPTAgZGVhZHpvbmU9MjEsMTEgZmFzdF9wc2tpcD0xIGNocm9tYV9xcF9vZmZzZXQ9LTIg'
        . 'dGhyZWFkcz0zIGxvb2thaGVhZF90aHJlYWRzPTEgc2xpY2VkX3RocmVhZHM9MCBucj0wIGRlY2ltYXRlPTEgaW50ZXJsYWNl'
        . 'ZD0wIGJsdXJheV9jb21wYXQ9MCBjb25zdHJhaW5lZF9pbnRyYT0wIGJmcmFtZXM9MCB3ZWlnaHRwPTAga2V5aW50PTI1MCBr'
        . 'ZXlpbnRfbWluPTYgc2NlbmVjdXQ9NDAgaW50cmFfcmVmcmVzaD0wIHJjX2xvb2thaGVhZD00MCByYz1jcmYgbWJ0cmVlPTEg'
        . 'Y3JmPTM4LjAgcWNvbXA9MC42MCBxcG1pbj0wIHFwbWF4PTY5IHFwc3RlcD00IGlwX3JhdGlvPTEuNDAgYXE9MToxLjAwAIAA'
        . 'AAXEZYiEBHyYoAAjvxEFAOACJxAvEAALg3/eRSIsICcN+EncBDjh886H8IgyiBAAEQGFGYDM2oG2zLpwAC9VWGpibADc4OiM'
        . 'WBuNJUpI3J/KH4aOBuAu6vrwkXAMEIAAgLqAAIEgYP4fK/6gf6VLeDUVf6gYcRHA4AReDAtEAALA8RBSKAxBkOAMRigACXFH'
        . 'AAEmuOAAII0RBQDgBF4gLRAACwPDEe/wXYSH/wlNBKdADDdDzLnvwAwzoNCPnv9T8DzmceY84dAHvNs/JDwuGAQIAEACilEX'
        . 'JyfghkcenJ0DQdSOA7s7sA9Pagx3LuhEQDgAicQJYOAAXAkRBSKBmREv8C/aQoIAi4EC9b1wAM2/5Xz4LEJkQAAiEMDO/Rq3'
        . '6ACXqph1idQn2ogoQ344//4KxYIHwP3n/hEiEEAATDlkt7/tv/2KUlIkkGHMwyB6JAODU9PT08z78OsP9hoJLPCLu98QCeIA'
        . 'AIAIA2OCaEHBNDAZe7MApaAFkAhgXcnQw4JDTHhDmlsPwAvqun3+bxJAEA6jgVpy3R5t0cf8gziH7vFDMVVs9MEdfZvsTQ7D'
        . 'YItJ/VbvWwJdjqTff/tgBPyrm3cAoyn+r+7eW4VF4VEdgjjGNaenp6enp///2CuKvyI3zQs0L+mn0gwwCJr09D8f2APqqHfs'
        . 'gCEefVmvr83/6fYIBfwANN06/Jd7/2t8LQoIgBfs+iH35GoxLGB9VO7EA6aTPY5zxTAf3+9/TBDT09PT09P/8Bhow6CQAbKt'
        . '1RvQwCOlah2Kj476AYX29lL8IJIQAAgFWJdEu4PBaDwWp9Ifw+w9OO0R1eYC7ZanHvXT0wS09PT09PTwkwTC8PRvn4CQU0+8'
        . '/8AkGTX2msW2+8/8xjNv7X2p6f+FPhwoe8AGceTVsn/yA7qpu9L+9l5K3qyemCGnp6enp6fimEcPsaCQAlI6RqHYqPnz5fmH'
        . 'AGQZNb7Xeyx8Qg0IAARAEQnonuDweg8Hun/Bvh/YT4ARM+6f57xj9fXl5lvtH09//48IdNih9VEJoDgxsWxJM4gEyIEzSkOg'
        . 'Q4R4kAR8uRV3HhP01ence3K7OHY/ldVA5jcSBMg6AjOIEyDwGZFoRQPn+mLYrAcSMpYCjAwABINwdAjIOgRnB0EZB0BGfh+O'
        . 'AIFonWICSETACB9vxACZFoZAzp2xAmfh/wDAIdE6vu4gABcHg9GDwGZB4BmcSATIkCZ/+AYBDpd3xW4RG7C54OQAzIPAGZxI'
        . 'TIgEyLRuQM6/bFWKZwJvqYIAAjIDHPB4GZB4GZxITIt/KHHYMOeABkbtkvGij3Atg4pbiABMir0xAAmbfxwwBBs09/th/cD5'
        . '50xS/+H/AIdLgrctOaigrFVZLqXRMgYqoRaP/gGAQ70lxPFGKPmUuhLomVipdH/15WNr7+a4b2OVnLKzlqpmbQK6j/8MAhDf'
        . 'zW/cGUhe8OkcsOkcuZB1HUA6R1AXV//8alqpYMCJAQF3t/GpYtGCJVAggvez4tX+H+AYBDp8t8fZWDWgM6uw8ylh5lLewNvo'
        . 'G30f/AMAh3lofMQ44smd2HmKWDzLou0rQiaBag1kBYMIF+72OqKc6gPOZYMVLKKl26Vov//7DvDuW8GN74GzVZXA8bSyipYv'
        . 'CWdAH5qqq5RUsoqWIhU8AYggAFEQABAXmjgOc0AOBZAYEFdM5wP5rOOIy7+WOEhOIEEspFScevwmLkAIi2rFR2SD8AEi/rC4'
        . 'qiw/0+w2JFH86KBsAAICgVDAAJ4DINi6KnvuxkBTCVip7s0wOFeBiDBAOAAQZjoe9KhTFgOAkYImA4ABAAAQX4MAAWAAECS/'
        . 'X7khV48syzNCwNkmQMAARIBQOBCLC6E//+EZpBWxW+AMI8SNg94ATTNxEZp9DgREREReC8VDqCBjoEPoh7I22HYYABWEhAQg'
        . 'FZzEE/qA5ts/ADAMN5ghH66BxuNwsGZPwZs8DBwnAMAATU50EdzAARggBwEYIAcBGCAHAAAAu0GaOAj4nkDUV4EDiwhquq+C'
        . 'g+q6rEqj0YO5ZA+/0xMzgQK99CTJ8EYrVa4OKO+wcwUQRlxWK65YJBGq8ywTfoWVZ9CipwZUVuCyGOazXw0y0lH8NaAtPw5T'
        . '2P+CqqfCFfbsHleZXg5og6uDKj/y/5ee38E39nL4NPlhzxr3x/AkWqeS//HnlvCuX/wdMOctlsfAF/hXL8GFZ5JIbF4e0YAo'
        . 'VN4AkbF0UDvwBkBTCVnT34PddBFzgloLPJ8BGCAHARggBwEYIAcAAAClQZpUAj4EA7xPgoBZquqlRV8EYmouvKo9GmQHz/Ez'
        . 'IkmeCj+HD6qZfEJkQmfhBEcDOrtiEz/BR/BGfd5Mu5xETL9sQmeCKj9+HO7lTi2Lf/OIKnFtP+C7hBlElg7AMcG3LbvBty/E'
        . 'nmRVl0u8jUtF/61HP1g04JN7mR3yeAsQR82DXky4qegI/wQirZf6ODXt9gqowi78E9VVa1GgoTyXfoOu8FuWARggBwEYIAcA'
        . 'AAC0QZpgEfAkl/8JSgu1W/BBOLY/iZkleWCQm7pVw5/Uyfgk7iu54Lv4cPquDL8VYq/goFarqqZdULKjL6jFPwSCtRflXBVy'
        . '//hg+k/BW5ZKH4R7hG/srBliT8lWXW+Tb7OWv5dCzLvBYwcTkPFh+VuX3WCYMgkJjvlvnVgulPtDXobUO5f/y6Xcul0YwYQL'
        . '7u5TH6jAdl8Gn9EAAG8GkE9RdRdVrIn6/BDp0tMe9+g760L7r65YARggBwEYIAcBGCAHAAAA2kGagBHwJPhsGGqlRViZlP78'
        . 'HFDUVntEY/Uy/w31UycVYq/+GO77vky/bEJngw/gkPqqZfOK5l+ITLflONi5tE9++Fc8wuU0KX9eDqcYlX8W+CzhxlCGZn/+'
        . 'XS7kCXeCz0N3g25egLQ05TIjy6FmX/g8qO2+UEU88Bv4NuX38IgkJjvnW5+ev5dHZfBNOVv5W5fwcQ5zYNeQPn/A8yly//gk'
        . '3uOA7aQFTIciAL+B7Uv4+F9aqsmCXEvl0u/vvJDY3DxowBEDfwBI2TIwO/AEisXZQO/g91/gnyfgARggBwEYIAcBGCAHAAAA'
        . '0kGaoBHwJPhsF2qpV+LYmZ+HBe7pV+LacIIkA5VxCZ0xMz8OdVMo4hMiEz/1MoTf/HjDvV96LOLaeKWnwIH8OH1XiKX4hMiE'
        . 'zCCGSoy0xb22+DL+hZU14JocEai8Y6YmZ/gqo75v/H77/fe8tzIA+Oy27wOkculAtZFyALwWegKTPTL8LMvugVQYDVrw/Pi5'
        . 'tT/gqgj5sGvOYQUXEjgkDrV7m2X/0N3gz6iLvIwT1F6qL1LE8nhsXh40YAoAuAJGyZGB34AkbF2cDv4LdehrngjyfgEYIAcB'
        . 'GCAHAAAA3EGawBHwXF/wQAWMMAiBsXisXvB4yPLY0QMstgChJosS7weC2MeCDrUYXwQAV/wrwuHxOHwGCBFlseIyvLYDoUkh'
        . 'PeDwe9+hSVfBQfd93MiriSgj7ux4MOX/wYVw3hzCP6I34IxOovEy5aMKbwYUPy/BIKxcU4gI14KqN/wVc3/j9gw97lvyAbjs'
        . 'vy6B0jl8Hwk5zrH+DFS0sFWz/j8Frp27fICgf4Lp/f4ccfgmhvw7lm/j3uX/7UYDJqsDMPy+DD3o1h0guWta1LB7/WXdffp3'
        . '+17+/rQXc8E+T8ABGCAHARggBwEYIAcAAADJQZrgEfAk+cFiVfiZkW/hs+7pV+JmRbhDgNcSEyKX/VTJ+GybuZGWITP7wRYX'
        . '8HH8MCdV1XmX4hMirhBCHA+bi2KX8ewmHcsAxuDW7/4YHai+qlRVpiZnTFuvBNBITVYlXBxVsOOIH//5dLo2gJbHZfl0DpHL'
        . '7SOMCvwVfEM9Ab+HGn5fEfjawTQR+HcsmUIKXg5A5EqqNLm028Ew1feZnMiv+Fcvr8E5Vqqrll7677znAFAFwESTRjvwBI2T'
        . 'IwO/YK/egzVYJ8nWARggBwEYIAcAAACsQZsAEfAleCMFG7mQrx8oaZYycSEyLf8PcfMAj3z8MgmX08HQIyn4IULSjwcfw2TV'
        . 'Sq8W/hBCCkgPn9MTM/wbfwUF1XVeVV4Jp6VfpiZngs4cxX//l0uiKg77jsu1h3QHSOXe8WiiS/jKuJfwXYuj+Ln+Dbl/j1qs'
        . 'EUEfh3Lcmvzk//ghFWziB/6v4hIqJcnnKAKALgShyYb8ASNi6KB38FYqvOPafT7/wR5PwAEYIAcBGCAHARggBwAAAItBmyAQ'
        . '8CT4JAWbuZFXzny7YhM4t+JG+CbvBXghBH3dm/w4J1F+Zftir4KP6MVZ5aEFTwZfwUF1F9VjH4JO7pVwVf1HPy+BO/aOL283'
        . 'wXP41WK9RgOy//og4O8Fg1e3zAsnmZR/CuXwgygraB2JPg25ZW5dakrPAFAFwJQ5MN+AJGyZGB36Bb8FIQngARggBwEYIAcB'
        . 'GCAHAAAAnkGbQBDwJJf8HUk4LF/TEzP0Wvi/BV/BH3e5+Cg+q6rERS4KKEV+fF0xb/55U4tp/wbfw31X/EzKeLYt/PSr8TMi'
        . '3wUV/+o57wUDUJqsFUN82DXkBv4NuX+P8k5F/Lpd+o47WDDqJanYE4aHPHfbfy6XZPbf8CaKPMxWYWZfgxUv4NIIt77JLznA'
        . 'FAFwJQ5MN+AJGyZGB37BT6BSEJq8ARggBwEYIAcAAACuQZtgP8CT6BUZFXzzKO2Kv49iwEj1vwo57fb4LuPiAEPvfhkYdviQ'
        . 'Jlv4YLquqpl+KsQmfo3MvqVKwcfwR9V0q+CTu7Hgoor9YJOqjgB/QFAai1VAVCBvmwt8y/Bty+T0q8FJIRVxwbUSv1HBvBVP'
        . 'QEf4Qitn8oc6Q75O/8Exfsl8F0F2NzlMiHBty/KKl/BlBJd70QvrvzwBQBcCU6Yb8BEkyMd/Bf6BaMmvQRbAARggBwEYIAcB'
        . 'GCAH';

    $rel = storage_target_path('mp4');
    file_put_contents(storage_absolute($rel), base64_decode($base64, true) ?: '');
    @chmod(storage_absolute($rel), 0640);

    return $rel;
}

$videoRel   = demo_video_file();
$videoSize  = (int) filesize(storage_absolute($videoRel));

// 1. Vidéo « famille », mise en avant.
demo_document([
    'title'       => 'Film de famille fictif (démo)',
    'description' => "Petit film **fictif** de deux secondes, généré pour la démonstration.\n\n"
        . "Il sert à vérifier la lecture dans le navigateur (lecture par morceaux, sans conversion).",
    'doc_date'    => '1958',
    'category_id' => $categoryIds['Photos de famille (démo)'],
    'visibility'  => 'famille',
    'kind'        => 'video',
    'is_featured' => 1,
    'duration_seconds' => 2,
    'original_name' => 'film-demo.mp4',
    'tags'        => 'film, démonstration',
], $videoRel, 'mp4', 'video/mp4', $videoSize);

// 2. Vidéo réservée à la branche MARTIN, sans téléchargement.
$videoRel2  = demo_video_file();
demo_document([
    'title'       => 'Souvenir de la branche Martin (démo)',
    'description' => 'Vidéo fictive visible uniquement par la branche MARTIN. '
        . 'Le téléchargement est volontairement désactivé pour tester le cas.',
    'doc_date'    => '1965',
    'category_id' => $categoryIds['Photos de famille (démo)'],
    'visibility'  => 'branches',
    'branches'    => [$branchIds['MARTIN']],
    'kind'        => 'video',
    'display_order' => 1,
    'allow_download' => 0,
    'duration_seconds' => 2,
    'original_name' => 'souvenir-demo.mp4',
    'tags'        => 'film, démonstration',
], $videoRel2, 'mp4', 'video/mp4', (int) filesize(storage_absolute($videoRel2)));

// 3. Vidéo publique (visible sans connexion, comme un document public).
$videoRel4 = demo_video_file();
demo_document([
    'title'       => 'Vidéo publique de démonstration',
    'description' => 'Vidéo fictive **publique** : elle est visible sans connexion, '
        . 'comme les documents marqués « Public ». Sert à vérifier l’onglet Vidéos '
        . 'depuis un navigateur non connecté.',
    'doc_date'    => '1975',
    'category_id' => $categoryIds['Photos de famille (démo)'],
    'visibility'  => 'public',
    'kind'        => 'video',
    'duration_seconds' => 2,
    'original_name' => 'video-publique-demo.mp4',
    'tags'        => 'film, démonstration',
], $videoRel4, 'mp4', 'video/mp4', (int) filesize(storage_absolute($videoRel4)));

// 4. Vidéo privée (administrateurs uniquement).
$videoRel3  = demo_video_file();
demo_document([
    'title'       => 'Vidéo privée (démo)',
    'description' => 'Vidéo fictive privée : seuls les administrateurs la voient. '
        . 'Utile pour vérifier qu’un compte de famille obtient bien une page « introuvable ».',
    'doc_date'    => '1970',
    'category_id' => $categoryIds['Photos de famille (démo)'],
    'visibility'  => 'prive',
    'kind'        => 'video',
    'duration_seconds' => 2,
    'original_name' => 'video-privee-demo.mp4',
    'tags'        => 'film, démonstration',
], $videoRel3, 'mp4', 'video/mp4', (int) filesize(storage_absolute($videoRel3)));

echo "  · 4 vidéos de démonstration créées\n";

// ---------------------------------------------------------------------------
// Albums photos et personnes identifiées (lot 3)
// ---------------------------------------------------------------------------
if ((int) db_value('SELECT COUNT(*) FROM albums', [], 0) === 0) {
    $idDocument = static function (string $titre): int {
        $doc = db_one('SELECT id FROM documents WHERE title = ? ORDER BY id LIMIT 1', [$titre]);
        return $doc !== null ? (int) $doc['id'] : 0;
    };

    $portrait = $idDocument('Portrait de Jean Martin (démo)');
    $mariage  = $idDocument('Mariage de Paul et Suzanne (démo)');
    $reunion  = $idDocument('Réunion de famille 1965 (démo, branche Martin)');

    $albumId = db_insert('albums', [
        'title'             => 'Souvenirs de la branche Martin (démo)',
        'slug'              => 'souvenirs-branche-martin-demo',
        'description'       => "Trois photos fictives pour montrer le fonctionnement des albums.\n\n"
            . 'Un album ne copie aucun fichier : il range des photos déjà envoyées.',
        'cover_document_id' => $mariage > 0 ? $mariage : null,
        'visibility'        => 'famille',
        'position'          => 0,
        'created_at'        => $now,
        'updated_at'        => $now,
    ]);
    set_album_photos($albumId, array_values(array_filter([$mariage, $portrait, $reunion])));

    // Qui figure sur chaque photo (noms fictifs, comme le reste de la démo).
    if ($portrait > 0) {
        set_document_persons($portrait, [$jean]);
    }
    if ($mariage > 0) {
        set_document_persons($mariage, [$paul, $suzanne]);
    }
    if ($reunion > 0) {
        set_document_persons($reunion, [$jean, $marie, $paul, $anne, $suzanne, $claire]);
    }

    // La personne qui parle dans le témoignage sonore.
    $temoignage = $idDocument('Témoignage fictif de Jean (démo)');
    if ($temoignage > 0) {
        set_document_persons($temoignage, [$jean]);
    }

    echo "  · album de démonstration créé (3 photos, 6 personnes identifiées)\n";
}

// ---------------------------------------------------------------------------
// Recettes de famille (lot 5)
// ---------------------------------------------------------------------------
if ((int) db_value('SELECT COUNT(*) FROM recipes', [], 0) === 0) {
    $photoRecette = db_one("SELECT id FROM documents WHERE kind = 'photo' AND title LIKE '%Réunion%' LIMIT 1");

    db_insert('recipes', [
        'title'             => 'Gâteau au chocolat de Mamie (démo)',
        'slug'              => 'gateau-au-chocolat-de-mamie-demo',
        'origin'            => 'Recette fictive de la branche Martin',
        'servings'          => '8 personnes',
        'ingredients'       => "200 g de chocolat noir\n150 g de beurre\n4 œufs\n150 g de sucre\n"
            . "80 g de farine\n1 pincée de sel",
        'preparation'       => "## La veille\n\nFaire fondre le chocolat avec le beurre à feu très doux.\n\n"
            . "## Le jour même\n\n1. Préchauffer le four à 180 °C.\n2. Battre les œufs avec le sucre.\n"
            . "3. Ajouter le chocolat fondu, puis la farine.\n4. Cuire 25 minutes et laisser tiédir.",
        'notes'             => 'Se mange tiède, avec un peu de crème. **Recette inventée** pour la démonstration.',
        'photo_document_id' => $photoRecette !== null ? (int) $photoRecette['id'] : null,
        'visibility'        => 'famille',
        'created_at'        => $now,
        'updated_at'        => $now,
    ]);

    db_insert('recipes', [
        'title'       => 'Soupe du potager (démo)',
        'slug'        => 'soupe-du-potager-demo',
        'origin'      => 'Cuisine de tous les jours',
        'servings'    => '4 personnes',
        'ingredients' => "3 pommes de terre\n2 carottes\n1 poireau\n1 litre d'eau\nSel, poivre",
        'preparation' => "Éplucher les légumes et les couper en morceaux.\n\n"
            . "Couvrir d'eau et laisser cuire 30 minutes.\n\nMixer, saler, poivrer.",
        'notes'       => 'Meilleure réchauffée le lendemain.',
        'visibility'  => 'public',
        'created_at'  => $now,
        'updated_at'  => $now,
    ]);

    echo "  · 2 recettes de démonstration créées\n";
}

// ---------------------------------------------------------------------------
// Articles : huit textes fictifs pour juger la mise en page du journal
// ---------------------------------------------------------------------------
// Trois branches, des longueurs différentes, avec et sans photo : de quoi
// voir la une, les colonnes et les lettrines comme sur le vrai site.
if ((int) db_value('SELECT COUNT(*) FROM articles', [], 0) === 0) {
    $adminId = db_value("SELECT id FROM accounts WHERE role = 'admin' ORDER BY id LIMIT 1", [], null);

    $photos = [];
    foreach ([
        'portrait' => 'Portrait de Jean Martin%',
        'mariage'  => 'Mariage de Paul et Suzanne%',
        'reunion'  => 'Réunion de famille 1965%',
    ] as $cle => $motif) {
        $photos[$cle] = db_value(
            "SELECT id FROM documents WHERE kind = 'photo' AND title LIKE ? LIMIT 1",
            [$motif],
            null
        );
    }

    $textes = [
        [
            'slug'       => 'mariage-de-paul-et-suzanne-1958',
            'title'      => 'Le mariage de Paul et Suzanne, un 14 juin 1958',
            'summary'    => 'Deux cents invités, un accordéon, et une averse mémorable : le récit reconstitué de la noce, à partir des photos retrouvées dans le grenier.',
            'category'   => 'Souvenirs',
            'visibility' => 'public',
            'branche'    => null,
            'photo'      => 'mariage',
            'jours'      => 1,
            'body'       => "On avait sorti les chaises de la salle des fêtes dès le matin. La mairie ouvrait à dix heures et la famille était déjà là depuis une heure, à s'occuper les mains comme on pouvait : les hommes portaient les tables, les femmes disposaient les fleurs coupées la veille dans le jardin de la tante Berthe.\n\n"
                . "## Une averse et un accordéon\n\n"
                . "La cérémonie fut courte. À la sortie, le ciel tomba d'un coup, et tout le monde se retrouva serré sous l'auvent du café, en riant, les chapeaux dégoulinants. C'est là que le photographe prit la photo que nous avons retrouvée : on y voit Paul qui protège Suzanne d'un pan de sa veste, geste qu'il répéta, paraît-il, pendant cinquante-deux ans.\n\n"
                . "Le repas dura jusqu'à la nuit. L'accordéoniste, un cousin de la branche maternelle, joua la même valse quatre fois de suite parce que personne ne savait plus quoi demander. On servit le gâteau avec les fraises du jardin, et comme il n'y avait pas assez d'assiettes, les enfants mangèrent debout, dehors, sous les platanes.\n\n"
                . "## Ce que racontent les photos\n\n"
                . "Sur les huit clichés qui nous restent, trois seulement sont nets. Ce sont pourtant ceux qui disent le plus : la table des grands-parents, la danse, et cette voiture décorée de rubans qui n'a jamais voulu démarrer. La légende familiale assure qu'on est rentrés à pied, en chantant, sur les trois kilomètres.",
        ],
        [
            'slug'       => 'une-lettre-de-marie-1950',
            'title'      => 'Une lettre de Marie, retrouvée dans un livre de comptes',
            'summary'    => 'Glissée entre deux pages, une lettre de 1950 raconte le quotidien d’une ferme au sortir de la guerre.',
            'category'   => 'Correspondance',
            'visibility' => 'famille',
            'branche'    => null,
            'photo'      => null,
            'jours'      => 3,
            'body'       => "Ma chère Anne,\n\n"
                . "Le foin est rentré avant la pluie, ce qui nous a valu deux jours de dos cassé et une bonne soupe. Les enfants ont repris l'école lundi, avec des sabots neufs qui font un bruit d'enfer sur les pavés.\n\n"
                . "Je t'écris sur le dos du livre de comptes, parce que le papier à lettres est resté chez la mercière. Ne le dis pas à ton frère, il dirait encore que je ne range rien.\n\n"
                . "Dis-moi si tu viens à la Toussaint : on gardera la chambre du haut, celle qui a la cheminée. Embrasse les petits pour moi.",
        ],
        [
            'slug'       => 'reunion-de-famille-1965',
            'title'      => 'La réunion de 1965, en trois souvenirs',
            'summary'    => 'Trois témoins, trois versions de la même journée d’août.',
            'category'   => 'Souvenirs',
            'visibility' => 'branches',
            'branche'    => 'MARTIN',
            'photo'      => 'reunion',
            'jours'      => 6,
            'body'       => "Il y a eu la version courte, celle des albums : une belle journée, beaucoup de monde, on a mangé dehors.\n\n"
                . "Puis la version longue, racontée par ceux qui étaient là : la voiture en panne à mi-chemin, la glacière oubliée, et le cousin qui avait apporté son projecteur sans les bobines.\n\n"
                . "Enfin la version des enfants, la plus juste : on avait le droit de courir dans le pré, et personne ne nous a grondés avant le soir.",
        ],
        [
            'slug'       => 'le-portrait-de-jean-1928',
            'title'      => 'Le portrait de Jean, 1928 : ce qu’il ne montre pas',
            'summary'    => 'Un portrait d’atelier, une chemise trop grande, et l’histoire de ceux qui ont posé avant lui.',
            'category'   => 'Portraits',
            'visibility' => 'public',
            'branche'    => null,
            'photo'      => 'portrait',
            'jours'      => 9,
            'body'       => "Sur la photo, il a vingt-trois ans et il regarde légèrement à gauche, comme on le demandait alors. La chemise est trop grande aux épaules : c'était celle du père, et l'atelier prêtait le reste.\n\n"
                . "## L'atelier du bas de la rue\n\n"
                . "Le photographe s'appelait Monsieur R. Il travaillait à la lumière du jour, sous une verrière, et facturait trois poses pour le prix d'une. Les familles y passaient en fin de matinée, quand la lumière était la plus douce. On gardait une épreuve, on en donnait une, la troisième restait chez lui.\n\n"
                . "C'est sans doute pour cela que le même visage se retrouve, à vingt ans d'écart, dans des albums qui ne se connaissaient pas.\n\n"
                . "## Ce que la photo ne dit pas\n\n"
                . "Ce jour-là, sa sœur attendait un enfant. Il ne le saura qu'en rentrant, deux mois plus tard, et c'est cette nouvelle — pas le portrait — qui fut conservée dans les papiers de la famille.",
        ],
        [
            'slug'       => 'une-soupe-pour-l-hiver',
            'title'      => 'Une soupe pour l’hiver',
            'summary'    => 'La recette qu’on se passait de ferme en ferme, et les deux détails qui changent tout.',
            'category'   => 'La table',
            'visibility' => 'public',
            'branche'    => null,
            'photo'      => null,
            'jours'      => 14,
            'body'       => "Trois légumes, un oignon, et un peu de patience. La recette est courte, mais on ne la réussit qu'après l'avoir ratée une fois.\n\n"
                . "Le premier détail : ne jamais faire bouillir tout de suite. Le second : couper les légumes gros, ils tiendront mieux au chaud et la soupe sera meilleure le lendemain.",
        ],
        [
            'slug'       => 'vacances-a-arcachon-1980',
            'title'      => 'Vacances à Arcachon, août 1980',
            'summary'    => 'Une semaine de pluie, trois parties de cartes, et la photo que tout le monde réclame.',
            'category'   => 'Vacances',
            'visibility' => 'famille',
            'branche'    => 'DURAND',
            'photo'      => null,
            'jours'      => 21,
            'body'       => "On avait loué la maison à deux familles, ce qui voulait dire deux cuisines et une seule salle de bain. L'organisation tenait du chef-d'œuvre.\n\n"
                . "Il a plu cinq jours sur sept. On a joué aux cartes, on a refait le monde, on a mangé des huîtres sous l'auvent en regardant la mer grise. Sur la photo que tout le monde réclame, personne ne regarde l'objectif : c'est bien pour cela qu'elle est la meilleure.",
        ],
        [
            'slug'       => 'les-carnets-de-leon-1931',
            'title'      => 'Les carnets de Léon, 1931',
            'summary'    => 'Quatre pages arrachées, une écriture serrée, et le récit d’un hiver passé à réparer des toits.',
            'category'   => 'Carnets',
            'visibility' => 'branches',
            'branche'    => 'LEROY',
            'photo'      => null,
            'jours'      => 12,
            'body'       => "Le carnet commence au milieu d'une phrase : « …et comme la tuile manquait, on a mis une ardoise. » On ne saura jamais ce qui précède.\n\n"
                . "Quatre pages suivent, très serrées, où il est question de pluie, de clous, et d'un voisin qui prêtait son échelle contre un litre de vin. C'est tout l'hiver 1931, et c'est déjà beaucoup.",
        ],
        [
            'slug'       => 'chronique-du-mois',
            'title'      => 'Chronique du mois : ce qui a changé sur le site',
            'summary'    => 'Nouveaux albums, une vidéo restaurée, et un appel à témoignages pour le numéro de Noël.',
            'category'   => 'Nouvelles',
            'visibility' => 'famille',
            'branche'    => null,
            'photo'      => null,
            'jours'      => 30,
            'body'       => "Trois albums ont été complétés ce mois-ci, dont un consacré aux portraits de l'atelier du bas de la rue.\n\n"
                . "Nous cherchons des témoignages sur l'hiver 1954 : si vous avez des souvenirs écrits ou enregistrés, la rédaction attend vos plis.",
        ],
    ];

    $crees = 0;
    foreach ($textes as $texte) {
        $articleId = db_insert('articles', [
            'slug'         => $texte['slug'],
            'title'        => $texte['title'],
            'summary'      => $texte['summary'],
            'body'         => $texte['body'],
            'category'     => $texte['category'],
            'image_id'     => $texte['photo'] !== null ? ($photos[$texte['photo']] ?? null) : null,
            'visibility'   => $texte['visibility'],
            'status'       => 'publie',
            'published_at' => $now - ($texte['jours'] * 86400),
            'author_id'    => $adminId !== null ? (int) $adminId : null,
            'created_at'   => $now - ($texte['jours'] * 86400),
            'updated_at'   => $now - ($texte['jours'] * 86400),
        ]);

        if ($texte['branche'] !== null && isset($branchIds[$texte['branche']])) {
            set_content_branches('article', $articleId, [$branchIds[$texte['branche']]]);
        }

        $crees++;
    }

    echo "  · " . $crees . " articles de démonstration créés\n";
}

// ---------------------------------------------------------------------------
// Tchat : trois messages fictifs (pour voir l'onglet en démonstration)
// ---------------------------------------------------------------------------
if ((int) db_value('SELECT COUNT(*) FROM chat_messages', [], 0) === 0) {
    $messages = [
        ['MARTIN', 'Jean', 'Bonjour à toutes et à tous ! Ce salon sert à échanger les nouvelles de la famille.', 3600 * 30],
        ['DURAND', 'Marie', 'Très bonne idée. J’ai retrouvé des photos de vacances, je vous les montrerai bientôt.', 3600 * 26],
        ['LEROY', 'Paul', 'Quelqu’un sait comment fonctionne le bouton « voir les messages plus anciens » ?', 3600 * 4],
    ];

    foreach ($messages as [$code, $prenom, $texte, $anciennete]) {
        $compte = db_one('SELECT id FROM accounts WHERE username = ?', [$code]);
        db_insert('chat_messages', [
            'account_id'    => $compte !== null ? (int) $compte['id'] : null,
            'author_label'  => $code . '/' . $prenom,
            'author_branch' => $code,
            'body'          => $texte,
            'created_at'    => $now - $anciennete,
        ]);
    }
    echo "  · 3 messages de tchat de démonstration créés\n";
}

// ---------------------------------------------------------------------------
// Résumé
// ---------------------------------------------------------------------------
echo "\nTerminé.\n\n";

if ($passwords !== []) {
    echo "Mots de passe des comptes de famille créés (à noter maintenant) :\n";
    foreach ($passwords as $compte) {
        printf("  %-8s %s   (%s)\n", $compte['username'], $compte['password'], $compte['name']);
    }
    echo "\n";
}

if ((int) db_value("SELECT COUNT(*) FROM accounts WHERE role = 'admin'", [], 0) === 0) {
    echo "Aucun administrateur : créez-en un avec\n  php app/cli/create-admin.php\n\n";
}

echo "Rappel : ces contenus sont FICTIFS et servent uniquement aux tests.\n";
