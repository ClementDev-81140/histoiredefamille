<?php
/**
 * Mise en page publique — présentation « journal ».
 *
 * @var string|null $titre
 * @var string      $contenu
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

<header class="entete">
    <div class="conteneur entete__barre">
        <?php
        // Bandeau supérieur façon gazette : date écrite par PHP (aucune
        // dépendance à l'extension intl), édition et prix réglables dans
        // « Administration → Apparence du journal ». Une case décochée le
        // masque ; un champ vide garde le texte d'aujourd'hui.
        if (apparence_actif('montrer_bandeau')): ?>
        <p class="entete__date">
            <span><?= e(french_long_date()) ?></span>
            <span class="entete__edition"><?= e(apparence_texte('bandeau_milieu')) ?></span>
            <span><?= e(apparence_texte('bandeau_droite')) ?></span>
        </p>
        <?php endif; ?>

        <a class="entete__titre" href="/">
            <span class="entete__nom"><?= e($siteName) ?></span>
        </a>

        <?php
        // Ligne de conduite du journal : fondation à gauche, devise au
        // centre, numéro d'édition à droite (filet fin au-dessus, règle
        // double en dessous : voir style.css). Chaque élément peut être
        // masqué dans « Apparence du journal ».
        $devise = apparence_actif('montrer_devise') ? gazette_devise() : '';
        $fondee = apparence_actif('montrer_fondee') ? gazette_fondee() : '';
        $numero = apparence_numero();
        ?>
<?php if ($devise !== '' || $fondee !== '' || $numero !== ''): ?>
        <p class="entete__devise">
            <span class="entete__fondee"><?= $fondee !== '' ? 'Fondée en ' . e($fondee) : '' ?></span>
            <span class="entete__devise-texte"><?= $devise !== '' ? '« ' . e($devise) . ' »' : '' ?></span>
            <span class="entete__numero"><?= e($numero) ?></span>
        </p>
<?php endif; ?>

        <?php
        // Menu : réduit pour un visiteur (seulement ce qui a du contenu
        // public), complet pour un compte de famille ou un administrateur.
        // La règle est dans app/lib/rubriques.php, testée automatiquement.
        $menu = menu_rubriques($account, [
            'recettes'    => recipes_exist(),
            'temoignages' => audio_exist(),
            'tchat'       => $account !== null && tchat_actif(),
            // La carte n'apparaît que si elle a quelque chose à montrer.
            'carte'       => carte_exist(),
        ]);
        // Le tchat reste en dernier, après les pages d'information, comme avant.
        $menuTchat   = array_values(array_filter($menu, static fn (array $e): bool => $e['cle'] === 'tchat'));
        $menuAvant   = array_values(array_filter($menu, static fn (array $e): bool => $e['cle'] !== 'tchat'));
        ?>
        <nav class="nav" aria-label="Navigation principale">
            <?php foreach ($menuAvant as $entree): ?>
                <a href="<?= e($entree['href']) ?>"<?= $navClass($entree['href']) ?>><?= e($entree['libelle']) ?></a>
            <?php endforeach; ?>
            <?php foreach ($navPages as $navPage): ?>
                <a href="/pages/<?= e($navPage['slug']) ?>"<?= $navClass('/pages/' . $navPage['slug']) ?>><?= e($navPage['title']) ?></a>
            <?php endforeach; ?>
            <?php foreach ($menuTchat as $entree): ?>
                <a href="<?= e($entree['href']) ?>"<?= $navClass($entree['href']) ?>><?= e($entree['libelle']) ?><?php if ($tchatNonLus > 0): ?><span class="pastille" aria-label="nouveaux messages"><?= $tchatNonLus > 99 ? '99+' : (int) $tchatNonLus ?></span><?php endif; ?></a>
            <?php endforeach; ?>

            <?php if ($account === null): ?>
                <!-- La porte d'entrée : « Se connecter », mise en avant dans
                     l'en-tête. « Consulter » est déjà l'entrée du menu. -->
                <a class="nav__accent nav__accent--connexion" href="/connexion">Se connecter</a>
            <?php else: ?>
                <a class="nav__accent" href="<?= $account['role'] === 'admin' ? '/admin' : '/famille' ?>">
                    <?= $account['role'] === 'admin' ? 'Administration' : 'Espace famille' ?>
                </a>
                <?php if (est_journaliste()): ?>
                    <a href="/propositions"<?= $navClass('/propositions') ?>>Mes propositions</a>
                <?php endif; ?>
                <a href="/mon-compte"<?= $navClass('/mon-compte') ?>>Mon compte</a>
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
                <?php if (can_see_all()): ?>
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
    </div>
</footer>

<script src="<?= e(asset_url('/assets/app.js')) ?>" defer></script>
</body>
</html>
