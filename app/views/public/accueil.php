<?php
/**
 * Page d'accueil publique.
 *
 * Comme l'espace famille, elle se compose dans
 * « Administration → Composition des pages » : les blocs de l'en-tête
 * (« également publié », chiffres, mot sur l'espace réservé) puis les
 * rubriques, dans l'ordre choisi. Les trois entrées sont toujours là : ce
 * sont les portes du site.
 *
 * @var array $documents
 * @var array $photos
 * @var array $pages
 * @var array $personnes
 * @var int   $nbDocs, $nbPages, $nbPersonnes
 */
$siteName = site_setting('name');
$hero     = composition_colonne('public', 'hero');
$pleine   = composition_colonne('public', 'pleine');
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

    <?php foreach ($hero as $bloc): ?>

        <?php if ($bloc === 'entrees'): ?>
            <?php /* Deux portes, pas une de plus : « Consulter » pour découvrir,
                     « Se connecter » pour entrer dans l'espace de la famille.
                     La seconde est mise en avant : c'est celle qu'on cherche.
                     Les autres rubriques ne sont proposées que si elles
                     contiennent vraiment quelque chose de public. */ ?>
            <p class="actions actions--portes">
                <a class="bouton bouton--large" href="/documents">Consulter</a>
                <?php if (!is_logged_in()): ?>
                    <a class="bouton bouton--accent bouton--large bouton--porte" href="/connexion">Se connecter</a>
                <?php endif; ?>
            </p>

        <?php elseif ($bloc === 'egalement'): ?>
            <?php
            // Rubriques supplémentaires : seulement celles qui ont du contenu
            // public (une seule fonction, mise en cache pour la requête). Pour
            // un compte connecté, le menu complet suffit : on n'ajoute rien.
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

        <?php elseif ($bloc === 'chiffres'): ?>
            <p class="hero__chiffres" aria-label="Le site en chiffres">
                <span><strong><?= (int) $nbDocs ?></strong> document<?= (int) $nbDocs > 1 ? 's' : '' ?> accessible<?= (int) $nbDocs > 1 ? 's' : '' ?></span>
                <span><strong><?= (int) $nbPages ?></strong> page<?= (int) $nbPages > 1 ? 's' : '' ?> d’information</span>
            </p>

        <?php endif; ?>

    <?php endforeach; ?>
</section>

<?php foreach ($pleine as $bloc): ?>

    <?php if ($bloc === 'une'): ?>
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

    <?php elseif ($bloc === 'documents'): ?>
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

    <?php elseif ($bloc === 'lire'): ?>
        <?php if ($pages !== []): ?>
            <section class="section">
                <?php /* « À lire » (page d'accueil) et « À lire aussi » (bas
                         d'article) sont deux rubriques différentes : seul le
                         titre de la seconde est réglable. */ ?>
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

    <?php endif; ?>

<?php endforeach; ?>
