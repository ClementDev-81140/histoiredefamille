<?php
/**
 * Banc des captures : convertit les images d'une page rendue en `data:`.
 *
 *   HDF_CONFIG=/chemin/config.local.php php tools/navigateur/inline-medias.php \
 *       /tmp/accueil.html /tmp/accueil-images.html
 *
 * Les photos et les fichiers ne passent pas par `public/` : le site les sert
 * par PHP (`/media/miniature/{id}`, `/f/{id}`), que le petit serveur du banc ne
 * connaît pas. Cette commande lit le fichier réel dans le stockage et l'écrit
 * dans la page en `data:` — la capture montre donc les vraies images, sans
 * Apache. Elle ne touche ni aux feuilles de style ni aux scripts.
 *
 * Outil de développement seulement : hors de `public/`, jamais appelé par le
 * site.
 */
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$source = $argv[1] ?? '';
$cible  = $argv[2] ?? '';
$html   = (string) @file_get_contents($source);
if ($html === '') { fwrite(STDERR, "source vide\n"); exit(1); }

$racine = (string) config('storage.root');

$remplacer = static function (array $m) use ($racine): string {
    $adresse = $m[2];
    $fichier = null;
    if (preg_match('#^/media/miniature/(\d+)$#', $adresse, $t)) {
        $rel = (string) db_value('SELECT thumb_path FROM documents WHERE id = ?', [(int) $t[1]], '');
        if ($rel !== '') { $fichier = $racine . '/' . $rel; }
    } elseif (preg_match('#^/f/(\d+)$#', $adresse, $t)) {
        $rel = (string) db_value('SELECT rel_path FROM documents WHERE id = ?', [(int) $t[1]], '');
        if ($rel !== '') { $fichier = $racine . '/' . $rel; }
    } else {
        return $m[0];
    }
    if ($fichier === null || !is_file($fichier)) { return $m[0]; }
    $octets = (string) file_get_contents($fichier);
    $mime = match (strtolower(pathinfo($fichier, PATHINFO_EXTENSION))) {
        'jpg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'pdf' => 'application/pdf',
        default => 'application/octet-stream',
    };
    return $m[1] . '="data:' . $mime . ';base64,' . base64_encode($octets) . '"';
};

$html = (string) preg_replace_callback('#(src|href)="(/(?:media|f)/[^"]+)"#', $remplacer, $html);

// Les feuilles et scripts servis par le banc suffisent ; on n'y touche pas.
file_put_contents($cible, $html);
echo $cible, ' : ', strlen($html), " octets\n";
