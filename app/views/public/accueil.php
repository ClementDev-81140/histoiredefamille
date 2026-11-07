<?php
/**
 * Page d'accueil publique.
 *
 * Comme l'espace famille, elle se compose dans
 * « Administration → Composition des pages » : les blocs de l'en-tête
 * (« également publié », chiffres) puis les rubriques, dans l'ordre choisi.
 * Les deux portes (« Consulter », « Se connecter ») ne s'affichent que pour
 * un visiteur.
 *
 * Depuis la 1.29.0, un compte connecté voit d'abord la une : bandeau du
 * journal, article principal, puis « Dans la famille » (en colonne à droite
 * sur grand écran), puis les derniers documents et « À lire ». La phrase
 * d'accueil longue, la ligne de chiffres et « Nous écrire » ne s'affichent
 * plus que pour un visiteur : elles poussaient l'article hors du premier
 * écran, alors que l'adresse de contact reste dans le pied de page.
 *
 * @var array $documents
 * @var array $photos
 * @var array $pages
 * @var array $personnes
 * @var array $dansLaFamille
 * @var array|null $uneArticle   article principal (compte connecté)
 * @var string $salutation
 * @var int   $nbDocs, $nbPages, $nbPersonnes
 */
$siteName = site_setting('name');
$hero     = composition_colonne('public', 'hero');
$pleine   = composition_colonne('public', 'pleine');
?>
<?php /* Le nom et le sous-titre figurent déjà dans l'en-tête du journal :
         on garde un h1 lisible par les lecteurs d'écran et les moteurs de
         recherche, mais invisible à l'écran (évite le doublon). Il reste en
         haut de page pour tout le monde : la une, elle, porte un h2. */ ?>
<h1 class="sr-only"><?= e($siteName) ?></h1>

<?php /* Un compte connecté n'a pas besoin de la vitrine : sa page commence par
         la une (plus bas). Pour un visiteur, rien ne change. */ ?>
<?php if (!is_logged_in()): ?>
<section class="hero">
    <?php /* Le sous-titre est déjà dans l'en-tête du journal : on ne
             l'affiche pas une deuxième fois ici. La phrase d'accueil est
             réglable dans « Apparence du journal » ; vide, elle reprend la
             description du site. */ ?>
    <?php if (apparence_accueil_phrase() !== ''): ?>
        <p class="hero__texte"><?= e(apparence_accueil_phrase()) ?></p>
    <?php endif; ?>

    <?php foreach ($hero as $bloc): ?>

        <?php if ($bloc === 'entrees'): ?>
            <?php /* Deux portes pour un visiteur : « Consulter » pour découvrir,
                     « Se connecter » pour entrer dans l'espace de la famille.
                     La seconde est mise en avant : c'est celle qu'on cherche.
                     Un compte connecté, lui, n'a plus besoin de ces portes :
                     le menu lui donne déjà toutes les rubriques. */ ?>
            <?php if (!is_logged_in()): ?>
                <p class="actions actions--portes">
                    <a class="bouton bouton--large" href="/documents">Consulter</a>
                    <a class="bouton bouton--accent bouton--large bouton--porte" href="/connexion">Se connecter</a>
                </p>
            <?php endif; ?>

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

    <?php /* Adresse affichée, jamais utilisée par le site pour envoyer : un
             simple lien mailto, que chacun peut aussi copier à la main. */ ?>
    <p class="hero__ecrire">
        Nous écrire :
        <a href="mailto:<?= e(site_adresse('contact')) ?>"><?= e(site_adresse('contact')) ?></a>
    </p>
</section>
<?php endif; ?>

<?php if (is_logged_in()): ?>
    <?php
    /* La une d'abord (1.29.0). `$uneArticle` est calculé par le contrôleur :
       le plus récent des articles que cette personne peut lire, déjà filtré par
       ses droits. */
    $articleUne     = $uneArticle ?? null;
    $familleVisible = !empty($dansLaFamille) && !dans_la_famille_vide($dansLaFamille);
    // Cinq lignes visibles au plus, et repli sur téléphone au-delà de trois.
    $familleMax = 5;
    ?>
    <div class="accueil-journal<?= ($articleUne !== null && $familleVisible) ? ' accueil-journal--deux' : '' ?>">
        <?php if ($articleUne !== null): ?>
            <div class="accueil-journal__une">
                <?php /* Le bloc « une » de la composition est respecté ; s'il est
                         masqué, l'article principal s'affiche quand même, sous
                         un simple repère de rubrique. */ ?>
                <?php $uneComposee = in_array('une', $pleine, true); ?>
                <section class="section"<?= $uneComposee ? '' : ' aria-label="' . e(apparence_titre_encart('une')) . '"' ?>>
                    <?php if ($uneComposee): ?>
                        <div class="section__entete">
                            <h2><?= e(apparence_titre_encart('une')) ?></h2>
                            <a href="/articles">Tous les articles →</a>
                        </div>
                    <?php else: ?>
                        <h2><?= e(apparence_titre_encart('une')) ?></h2>
                    <?php endif; ?>
                    <div class="articles-liste">
                        <?php $article = $articleUne; $grand = true; ?>
                        <?php require __DIR__ . '/../partials/carte_article.php'; ?>
                        <?php foreach (array_slice($articles, 1) as $article): ?>
                            <?php $grand = false; ?>
                            <?php require __DIR__ . '/../partials/carte_article.php'; ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        <?php endif; ?>

        <?php if ($familleVisible): ?>
            <?php /* Colonne de droite sur grand écran, juste dessous sinon —
                     jamais avant la une. */ ?>
            <aside class="accueil-journal__famille" aria-label="Dans la famille">
                <?php require __DIR__ . '/../partials/dans_la_famille.php'; ?>
            </aside>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
/* Blocs de la partie pleine largeur. Pour un compte connecté, « une » et
   « famille » sont déjà en haut de page (voir plus haut) : on ne les rejoue
   pas ici. Pour un visiteur, « une » est la rubrique des articles ; le bloc
   « famille » ne le concerne jamais. */
$pleineAffichee = is_logged_in() ? array_values(array_diff($pleine, ['une', 'famille'])) : $pleine;
?>
<?php foreach ($pleineAffichee as $bloc): ?>

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
