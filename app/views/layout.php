<?php
/**
 * Mise en page publique.
 *
 * @var string|null $titre
 * @var string      $contenu
 */
$siteName   = site_setting('name');
$siteSub    = site_setting('subtitle');

try {
    $account  = current_account();
    $navPages = nav_pages();
} catch (Throwable) {
    // La base peut être momentanément indisponible : la page d'erreur doit
    // quand même pouvoir s'afficher.
    $account  = null;
    $navPages = [];
}
$flashes    = take_flashes();
$pageTitle  = !empty($titre) ? $titre . ' — ' . $siteName : $siteName;
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e(site_setting('description')) ?>">
    <meta name="robots" content="<?= is_logged_in() ? 'noindex, nofollow' : 'index, follow' ?>">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<a class="lien-evitement" href="#contenu">Aller au contenu principal</a>

<header class="entete">
    <div class="conteneur entete__barre">
        <a class="entete__titre" href="/">
            <span class="entete__nom"><?= e($siteName) ?></span>
            <?php if ($siteSub !== ''): ?>
                <span class="entete__sous-titre"><?= e($siteSub) ?></span>
            <?php endif; ?>
        </a>

        <nav class="nav" aria-label="Navigation principale">
            <a href="/">Accueil</a>
            <a href="/documents">Documents</a>
            <a href="/arbre">Arbre</a>
            <?php foreach ($navPages as $navPage): ?>
                <a href="/pages/<?= e($navPage['slug']) ?>"><?= e($navPage['title']) ?></a>
            <?php endforeach; ?>

            <?php if ($account === null): ?>
                <a class="nav__accent" href="/connexion">Espace famille</a>
            <?php else: ?>
                <a class="nav__accent" href="<?= $account['role'] === 'admin' ? '/admin' : '/famille' ?>">
                    <?= $account['role'] === 'admin' ? 'Administration' : 'Espace famille' ?>
                </a>
                <form method="post" action="/deconnexion" class="nav__form">
                    <?= csrf_field() ?>
                    <button type="submit" class="lien-bouton">Déconnexion</button>
                </form>
            <?php endif; ?>
        </nav>
    </div>

    <?php if ($account !== null): ?>
        <div class="conteneur bandeau-compte">
            Connecté en tant que <strong><?= e($account['username']) ?></strong>
            <?php if ($account['role'] === 'famille' && !empty($account['branch_name'])): ?>
                — branche <strong><?= e($account['branch_name']) ?></strong>
                <?php if ((int) ($account['branch_see_all'] ?? 0) === 1): ?>
                    <span class="badge badge-famille">voit toutes les branches</span>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($account['role'] === 'admin'): ?>
                <span class="badge badge-public">administrateur</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</header>

<main id="contenu" class="conteneur contenu-principal">
    <?php require __DIR__ . '/partials/flashes.php'; ?>
    <?= $contenu ?>
</main>

<footer class="pied">
    <div class="conteneur pied__contenu">
        <p>
            <?= e($siteName) ?>
            <?php if ($siteSub !== ''): ?> — <?= e($siteSub) ?><?php endif; ?>
        </p>
        <p class="pied__note">
            Site privé de famille. Les documents sont protégés et accessibles
            uniquement selon les droits définis par les administrateurs.
        </p>
    </div>
</footer>

<script src="/assets/app.js" defer></script>
</body>
</html>
