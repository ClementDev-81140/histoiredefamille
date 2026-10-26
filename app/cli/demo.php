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
    'birth_date' => '1901', 'birth_place' => 'Exempleville (fictif)',
    'death_date' => '1975', 'death_place' => 'Exempleville (fictif)',
    'notes'      => "Personnage **fictif** créé pour la démonstration.\n\n- Agriculteur\n- Aimait la photographie",
]);
$marie = demo_person([
    'first_name' => 'Marie', 'last_name' => 'Leroy', 'sex' => 'F',
    'birth_date' => '1905', 'birth_place' => 'Village-d’Exemple (fictif)',
    'death_date' => '1982',
    'notes'      => 'Personnage fictif de démonstration.',
]);
$paul  = demo_person([
    'first_name' => 'Paul', 'last_name' => 'Martin', 'sex' => 'M',
    'birth_date' => '1930', 'death_date' => '2005',
    'birth_place' => 'Exempleville (fictif)',
]);
$anne  = demo_person([
    'first_name' => 'Anne', 'last_name' => 'Martin', 'sex' => 'F',
    'birth_date' => '1935',
]);
$suzanne = demo_person([
    'first_name' => 'Suzanne', 'last_name' => 'Durand', 'sex' => 'F',
    'birth_date' => '1932',
]);
$claire = demo_person([
    'first_name' => 'Claire', 'last_name' => 'Martin', 'sex' => 'F',
    'birth_date' => '1960',
]);
$luc = demo_person([
    'first_name' => 'Luc', 'last_name' => 'Martin', 'sex' => 'M',
    'birth_date' => '1963',
]);
$prive = demo_person([
    'first_name' => 'Fiche', 'last_name' => 'Privée',
    'notes'      => 'Exemple de fiche visible uniquement des administrateurs.',
    'visibility' => 'prive',
]);

// Couples : (conjoint 1, conjoint 2, date, lieu, enfants)
$couples = [
    [$jean, $marie, '1928', 'Exempleville (fictif)', [$paul, $anne]],
    [$paul, $suzanne, '1958', null, [$claire, $luc]],
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

/** Insère un document de démonstration à partir d'un fichier du stockage. */
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
        'kind'          => str_starts_with($mime, 'image/') ? 'photo' : 'document',
        'visibility'    => $data['visibility'],
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

echo "  · 5 documents de démonstration créés\n";

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
