<?php
/**
 * Mise en page publique — présentation « journal ».
 *
 * @var string|null $titre
 * @var string      $contenu
 * @var string|null $salutation  une ligne de bienvenue (accueil connecté)
 */
$siteName   = site_setting('name');
$siteSub    = site_setting('subtitle');

try {
    $account  = current_account();
    $navPages = nav_pages();
    // Pastille du tchat : une seule petite requête par page, aucun sondage.
    $tchatNonLus = $account !== null ? tchat_non_lus() : 0;
} catch (Throwable) {
    // La base peut être momentanément indisponible : la page d'erreur doit
    // quand même pouvoir s'afficher.
    $account  = null;
    $navPages = [];
    $tchatNonLus = 0;
}
$flashes    = take_flashes();
$pageTitle  = !empty($titre) ? $titre . ' — ' . $siteName : $siteName;

// Un chemin courant pour souligner l'onglet actif (aide à s'orienter).
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$navClass = static function (string $href) use ($currentPath): string {
    $active = $href === '/'
        ? $currentPath === '/'
        : str_starts_with($currentPath, $href);
    return $active ? ' class="actif" aria-current="page"' : '';
};
?>
<!doctype html>
<html lang="fr"<?= apparence_gothique_disponible() ? ' class="police-titre"' : '' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <?php /* La description affichée, jamais l'ancienne phrase qui parlait de
             l'arbre : voir apparence_description_site(). */ ?>
    <meta name="description" content="<?= e(apparence_description_site()) ?>">
    <meta name="robots" content="<?= is_logged_in() ? 'noindex, nofollow' : 'index, follow' ?>">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/style.css')) ?>">
<?php if (apparence_feuille_active()): ?>
    <?php /* Couleurs choisies par les administrateurs : feuille calculée par
             PHP, liée APRÈS style.css (aucun style en ligne, aucune ressource
             externe). Silencieuse tant que rien n'a été personnalisé. */ ?>
    <link rel="stylesheet" href="/theme.css?v=<?= apparence_version() ?>">
<?php endif; ?>
    <link rel="preload" href="/assets/fonts/old-standard-tt-400.woff2" as="font" type="font/woff2" crossorigin>
</head>
<body>
<a class="lien-evitement" href="#contenu">Aller au contenu principal</a>

<?php require __DIR__ . '/partials/entete_journal.php'; ?>

<main id="contenu" class="conteneur contenu-principal">
    <?php /* En-tête visible uniquement à l'impression : une feuille imprimée
             doit dire de quel site elle vient. */ ?>
    <p class="impression-seule">
        <?= e($siteName) ?><?= $siteSub !== '' ? ' — ' . e($siteSub) : '' ?>
        · <?= e(french_long_date()) ?>
    </p>
    <?php require __DIR__ . '/partials/flashes.php'; ?>
    <?= $contenu ?>
</main>

<footer class="pied">
    <div class="conteneur pied__contenu">
        <p>
            <?= e($siteName) ?>
            <?php if ($siteSub !== ''): ?> — <?= e($siteSub) ?><?php endif; ?>
        </p>
        <p class="pied__note"><?= e(apparence_pied()) ?></p>
        <?php /* Un lien mailto ordinaire : le site n'envoie aucun courriel, il
                 affiche seulement l'adresse (réglable dans « Réglages »). */ ?>
        <p class="pied__contact">
            Une question ?
            <a href="mailto:<?= e(site_adresse('contact')) ?>"><?= e(site_adresse('contact')) ?></a>
        </p>
    </div>
</footer>

<script src="<?= e(asset_url('/assets/app.js')) ?>" defer></script>
</body>
</html>
