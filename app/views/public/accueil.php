<?php
/**
 * Page d'accueil publique.
 *
 * @var array $documents
 * @var array $photos
 * @var array $pages
 * @var array $personnes
 * @var int   $nbDocs, $nbPages, $nbPersonnes
 */
$siteName = site_setting('name');
?>
<section class="hero">
    <?php /* Le nom et le sous-titre figurent déjà dans l'en-tête du journal :
             on garde un h1 lisible par les lecteurs d'écran et les moteurs de
             recherche, mais invisible à l'écran (évite le doublon). */ ?>
    <h1 class="sr-only"><?= e($siteName) ?></h1>
    <?php /* Le sous-titre est déjà dans l'en-tête du journal : on ne
             l'affiche pas une deuxième fois ici. La phrase d'accueil est
             réglable dans « Apparence du journal » ; vide, elle reprend la
             description du site. */ ?>
    <?php if (apparence_accueil_phrase() !== ''): ?>
        <p class="hero__texte"><?= e(apparence_accueil_phrase()) ?></p>
    <?php endif; ?>

    <?php /* Trois entrées, pas une de plus : le site se consulte d'abord
             en privé. Les autres rubriques ne sont proposées que si elles
             contiennent vraiment quelque chose de public. */ ?>
    <p class="actions">
        <a class="bouton" href="/documents">Parcourir les documents</a>
        <a class="bouton bouton--secondaire" href="/arbre">Voir l’arbre généalogique</a>
        <?php if (!is_logged_in()): ?>
            <a class="bouton bouton--secondaire" href="/connexion">Espace famille</a>
        <?php endif; ?>
    </p>

    <?php
    // Rubriques supplémentaires : seulement celles qui ont du contenu public
    // (une seule fonction, mise en cache pour la requête). Pour un compte
    // connecté, le menu complet suffit : on n'ajoute rien ici.
    $autres = [];
    if (menu_public_reduit()) {
        $autres = [
            'articles'   => ['/articles', 'Articles'],
            'videos'     => ['/videos', 'Vidéos'],
            'albums'     => ['/albums', 'Albums'],
            'calendrier' => ['/calendrier', 'Calendrier'],
            'recherche'  => ['/recherche', 'Recherche'],
        ];
        $autres = array_filter($autres, static fn (string $cle): bool => rubrique_publique($cle), ARRAY_FILTER_USE_KEY);
    }
    ?>
    <?php if ($autres !== []): ?>
        <nav class="hero__rubriques" aria-label="Autres rubriques publiques">
            <p class="hero__rubriques-titre">Également publié</p>
            <ul>
                <?php foreach ($autres as $lien): ?>
                    <li><a href="<?= e($lien[0]) ?>"><?= e($lien[1]) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
    <?php endif; ?>

    <p class="hero__chiffres" aria-label="Le site en chiffres">
        <span><strong><?= (int) $nbDocs ?></strong> document<?= (int) $nbDocs > 1 ? 's' : '' ?> accessible<?= (int) $nbDocs > 1 ? 's' : '' ?></span>
        <span><strong><?= (int) $nbPages ?></strong> page<?= (int) $nbPages > 1 ? 's' : '' ?> d’information</span>
        <span><strong><?= (int) $nbPersonnes ?></strong> personne<?= (int) $nbPersonnes > 1 ? 's' : '' ?> dans l’arbre</span>
    </p>

    <?php if (!is_logged_in()): ?>
        <p class="note">
            Une partie du site est réservée aux familles : connectez-vous avec
            l’identifiant et le mot de passe de votre branche pour voir les
            photos, vidéos et documents privés.
        </p>
    <?php endif; ?>
</section>

<?php if ($articles !== []): ?>
    <section class="section">
        <div class="section__entete">
            <h2><?= e(apparence_titre_encart('une')) ?></h2>
            <a href="/articles">Tous les articles →</a>
        </div>
        <div class="articles-liste">
            <?php foreach ($articles as $i => $article): ?>
                <?php $grand = ($i === 0); ?>
                <?php require __DIR__ . '/../partials/carte_article.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($documents !== []): ?>
    <section class="section">
        <div class="section__entete">
            <h2>Derniers documents</h2>
            <a href="/documents">Tout voir →</a>
        </div>
        <div class="grille-cartes">
            <?php foreach ($documents as $doc): ?>
                <?php require __DIR__ . '/../partials/carte_document.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($pages !== []): ?>
    <section class="section">
        <h2>À lire</h2>
        <div class="grille-pages">
            <?php foreach ($pages as $page): ?>
                <article class="panneau">
                    <h3><a href="/pages/<?= e($page['slug']) ?>"><?= e($page['title']) ?></a></h3>
                    <p><?= e(text_summary($page['body'], 200)) ?></p>
                    <p><a href="/pages/<?= e($page['slug']) ?>">Lire la suite →</a></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($personnes !== []): ?>
    <section class="section">
        <div class="section__entete">
            <h2>Dans l’arbre</h2>
            <a href="/arbre">Rechercher une personne →</a>
        </div>
        <ul class="liste-personnes">
            <?php foreach ($personnes as $personne): ?>
                <li>
                    <a href="/arbre/<?= (int) $personne['id'] ?>"><?= e(person_full_name($personne)) ?></a>
                    <?php $dates = person_dates($personne); ?>
                    <?php if ($dates !== ''): ?><span class="discret"><?= e($dates) ?></span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
