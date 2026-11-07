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

// Alerte de sauvegarde : affichée en haut de TOUTES les pages de
// l'administration (clé absente, sauvegarde trop ancienne ou en échec).
// Un défaut de lecture ne doit jamais empêcher d'ouvrir l'administration.
try {
    $alerteSauvegarde = sauvegarde_alerte();
} catch (Throwable) {
    $alerteSauvegarde = null;
}

// Compteur « À valider » : nombre de propositions qui attendent une décision.
// Un défaut de lecture ne doit jamais empêcher d'ouvrir l'administration.
try {
    $aValider = propositions_a_valider();
} catch (Throwable) {
    $aValider = 0;
}

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
    <link rel="stylesheet" href="<?= e(asset_url('/assets/style.css')) ?>">
<?php if (apparence_feuille_active()): ?>
    <link rel="stylesheet" href="/theme.css?v=<?= apparence_version() ?>">
<?php endif; ?>
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
            <a href="/mon-compte"<?= str_starts_with($current, '/mon-compte') ? ' class="actif" aria-current="page"' : '' ?>>Mon compte</a>
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
        <a href="/admin/a-valider"<?= str_starts_with($current, '/admin/a-valider') ? ' class="actif" aria-current="page"' : '' ?>>À valider<?php if ($aValider > 0): ?><span class="pastille" aria-label="propositions en attente"><?= $aValider > 99 ? '99+' : (int) $aValider ?></span><?php endif; ?></a>

        <?php /* Le menu range le travail par nature, plus par « partie » du
                 site : une même page sert souvent les deux (un article public,
                 un document de famille). Les intitulés de groupe ne parlent
                 donc ni de « public » ni de « famille ». */ ?>

        <p class="admin-menu__titre">Contenus</p>
        <?= $navLink('/admin/articles', 'Articles') ?>
        <?= $navLink('/admin/pages', 'Pages d’informations') ?>
        <?= $navLink('/admin/documents', 'Documents & photos') ?>
        <?= $navLink('/admin/albums', 'Albums photos') ?>
        <?= $navLink('/admin/videos', 'Vidéos') ?>
        <?= $navLink('/admin/recettes', 'Recettes') ?>
        <?= $navLink('/admin/categories', 'Catégories') ?>

        <p class="admin-menu__titre">Envois</p>
        <?= $navLink('/admin/envoyer', 'Envoyer des fichiers') ?>
        <?= $navLink('/admin/importer', 'Dossier arrivée') ?>
        <?= $navLink('/admin/conversions', 'Conversions') ?>
        <?= $navLink('/admin/envois', 'Mes envois') ?>

        <p class="admin-menu__titre">Personnes</p>
        <?= $navLink('/admin/arbre', 'Arbre généalogique') ?>
        <?= $navLink('/admin/comptes', 'Comptes') ?>
        <?= $navLink('/admin/branches', 'Branches & comptes') ?>
        <?= $navLink('/admin/admins', 'Administrateurs') ?>
        <?= $navLink('/admin/lieux', 'Lieux à placer') ?>

        <p class="admin-menu__titre">Journal</p>
        <?php if (site_courriels_actifs()): ?>
            <?= $navLink('/admin/courriels', 'Courriels') ?>
        <?php endif; ?>
        <?= $navLink('/admin/gazette', 'Gazette du mois') ?>
        <?= $navLink('/admin/apparence', 'Apparence du journal') ?>
        <?= $navLink('/admin/composition', 'Composition des pages') ?>

        <p class="admin-menu__titre">Système</p>
        <?= $navLink('/admin/reglages', 'Réglages') ?>
        <?= $navLink('/admin/connexions', 'Journal des connexions') ?>
        <?= $navLink('/admin/etat', 'État du site') ?>
    </aside>

    <main id="contenu" class="admin-contenu">
        <?php /* La page « État du site » montre l'alerte dans son propre cadre,
                 en détail : pas la peine de l'afficher deux fois de suite. */ ?>
        <?php if ($alerteSauvegarde !== null && $current !== '/admin/etat'): ?>
            <div class="message message--erreur" role="alert">
                <strong>Sauvegarde :</strong>
                <?= e($alerteSauvegarde['message']) ?>
                <a href="/admin/etat">Vérifier l’état du site</a>
            </div>
        <?php endif; ?>
        <?php require __DIR__ . '/partials/flashes.php'; ?>
        <?= $contenu ?>
    </main>
</div>

<footer class="pied pied--admin">
    <div class="conteneur">
        <p class="pied__note">
            Les comptes administrateurs sont personnels. Chaque action est enregistrée dans le journal.
        </p>
        <p class="pied__contact">
            Une question ?
            <a href="mailto:<?= e(site_adresse('contact')) ?>"><?= e(site_adresse('contact')) ?></a>
        </p>
    </div>
</footer>

<script src="<?= e(asset_url('/assets/app.js')) ?>" defer></script>
<script src="<?= e(asset_url('/assets/envoi.js')) ?>" defer></script>
</body>
</html>
