<?php
/**
 * Mise en page de l'administration.
 *
 * @var string|null $titre
 * @var string      $contenu
 */
$siteName  = site_setting('name');

try {
    $account = current_account();
} catch (Throwable) {
    $account = null;
}
if ($account === null) {
    $account = ['username' => '', 'display_name' => ''];
}
$flashes   = take_flashes();
$current   = $_SERVER['REQUEST_URI'] ?? '/admin';
$current   = parse_url($current, PHP_URL_PATH) ?: '/admin';
$pageTitle = (!empty($titre) ? $titre . ' — ' : '') . 'Administration — ' . $siteName;

/** Lien de menu avec état actif. */
$navLink = static function (string $href, string $label, bool $exact = false) use ($current): string {
    $active = $exact ? $current === $href : str_starts_with($current, $href);
    return '<a href="' . e($href) . '"' . ($active ? ' class="actif" aria-current="page"' : '') . '>'
        . e($label) . '</a>';
};
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="page-admin">
<a class="lien-evitement" href="#contenu">Aller au contenu principal</a>

<header class="entete entete--admin">
    <div class="conteneur entete__barre">
        <a class="entete__titre" href="/admin">
            <span class="entete__nom"><?= e($siteName) ?></span>
            <span class="entete__sous-titre">Administration</span>
        </a>
        <nav class="nav" aria-label="Navigation d'administration">
            <a href="/" target="_blank" rel="noopener">Voir le site</a>
            <a href="/admin/mon-compte"><?= e($account['display_name'] ?? $account['username'] ?? '') ?></a>
            <form method="post" action="/deconnexion" class="nav__form">
                <?= csrf_field() ?>
                <button type="submit" class="lien-bouton">Déconnexion</button>
            </form>
        </nav>
    </div>
</header>

<div class="conteneur admin-corps">
    <aside class="admin-menu" aria-label="Menu de l'administration">
        <?= $navLink('/admin', 'Tableau de bord', true) ?>

        <p class="admin-menu__titre">Partie publique</p>
        <?= $navLink('/admin/pages', 'Pages d’informations') ?>
        <?= $navLink('/admin/documents', 'Documents & photos') ?>
        <?= $navLink('/admin/categories', 'Catégories') ?>

        <p class="admin-menu__titre">Partie famille</p>
        <?= $navLink('/admin/branches', 'Branches & comptes') ?>
        <?= $navLink('/admin/arbre', 'Arbre généalogique') ?>
        <?= $navLink('/admin/importer', 'Dossier arrivée') ?>

        <p class="admin-menu__titre">Système</p>
        <?= $navLink('/admin/reglages', 'Réglages') ?>
        <?= $navLink('/admin/admins', 'Administrateurs') ?>
        <?= $navLink('/admin/etat', 'État du site') ?>
    </aside>

    <main id="contenu" class="admin-contenu">
        <?php require __DIR__ . '/partials/flashes.php'; ?>
        <?= $contenu ?>
    </main>
</div>

<footer class="pied pied--admin">
    <div class="conteneur">
        <p class="pied__note">
            Les comptes administrateurs sont personnels. Chaque action est enregistrée dans le journal.
        </p>
    </div>
</footer>

<script src="/assets/app.js" defer></script>
</body>
</html>
