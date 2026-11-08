<?php
/**
 * En-tête « journal » commun au site et à l'administration (1.35.2) : bandeau
 * (date, édition, prix), titre, devise, menu principal et bandeau du compte.
 *
 * Attend, comme layout.php : $account, $navPages, $tchatNonLus, $navClass et,
 * facultatif, $salutation.
 */
?>
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

        <?php /* Une seule ligne de bienvenue, discrète, sous la date (1.29.0) :
                 le prénom de la personne connectée. Facultative — un compte
                 sans prénom n'a rien ici — et volontairement courte, pour ne
                 pas repousser la une hors du premier écran. */ ?>
        <?php if (($salutation ?? '') !== ''): ?>
            <p class="entete__salutation"><?= e($salutation) ?></p>
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
        // Menu : depuis la 1.31.0, un visiteur n'a que « Accueil » et
        // « Se connecter » (l'accueil public présente tout le contenu public) ;
        // un compte de famille ou un administrateur garde le menu complet.
        // La règle est dans app/lib/rubriques.php, testée automatiquement.
        // Ces rubriques facultatives ne servent qu'au menu d'un compte
        // connecté : inutile d'interroger la base pour un visiteur (trois
        // requêtes de moins sur chaque page publique).
        $menu = menu_rubriques($account, $account === null ? [] : [
            'recettes'    => recipes_exist(),
            'temoignages' => audio_exist(),
            'tchat'       => tchat_actif(),
            // La carte n'apparaît que si elle est ouverte (réglage) et si elle
            // a quelque chose à montrer. Désactivée par défaut.
            'carte'       => carte_active() && carte_exist(),
        ]);
        // Le tchat reste en dernier, après les pages d'information, comme avant.
        $menuTchat   = array_values(array_filter($menu, static fn (array $e): bool => $e['cle'] === 'tchat'));
        $menuAvant   = array_values(array_filter($menu, static fn (array $e): bool => $e['cle'] !== 'tchat'));
        ?>
        <nav class="nav" aria-label="Navigation principale">
            <?php foreach ($menuAvant as $entree): ?>
                <a href="<?= e($entree['href']) ?>"<?= $navClass($entree['href']) ?>><?= e($entree['libelle']) ?></a>
            <?php endforeach; ?>
            <?php /* Les pages d'information ne sont plus dans le menu public
                     (1.31.0) : un visiteur a « Accueil » et « Se connecter », et
                     retrouve les pages publiques dans la Une de l'accueil. Un
                     compte connecté les garde ici. */ ?>
            <?php if ($account !== null): ?>
                <?php foreach ($navPages as $navPage): ?>
                    <a href="/pages/<?= e($navPage['slug']) ?>"<?= $navClass('/pages/' . $navPage['slug']) ?>><?= e($navPage['title']) ?></a>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php foreach ($menuTchat as $entree): ?>
                <a href="<?= e($entree['href']) ?>"<?= $navClass($entree['href']) ?>><?= e($entree['libelle']) ?><?php if ($tchatNonLus > 0): ?><span class="pastille" aria-label="nouveaux messages"><?= $tchatNonLus > 99 ? '99+' : (int) $tchatNonLus ?></span><?php endif; ?></a>
            <?php endforeach; ?>

            <?php if ($account === null): ?>
                <!-- La porte d'entrée : « Se connecter », mise en avant dans
                     l'en-tête. Depuis la 1.31.0, c'est la seconde et dernière
                     entrée du menu d'un visiteur : l'accueil fait office de
                     « Consulter », il présente tout le contenu public. -->
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
