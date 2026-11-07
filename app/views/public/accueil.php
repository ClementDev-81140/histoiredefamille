<?php
/**
 * Page d'accueil publique.
 *
 * Deux pages en une, jamais mélangées :
 *
 *   - **un visiteur** voit la Une du journal (1.31.0) : le fichier
 *     `accueil_visiteur.php`, qui présente tout le contenu public, du plus
 *     récent au plus ancien. Le contrôleur s'arrête là pour lui ;
 *   - **un compte connecté** voit la page composée dans
 *     « Administration → Composition des pages » : depuis la 1.29.0, la une
 *     d'abord (bandeau, article principal, puis « Dans la famille » en colonne
 *     à droite sur grand écran), puis les derniers documents et « À lire ».
 *
 * Le reste de ce fichier ne concerne donc que les comptes connectés.
 *
 * @var array $documents
 * @var array $pages
 * @var array $dansLaFamille
 * @var array|null $uneArticle   article principal (compte connecté)
 * @var string $salutation
 */
$siteName = site_setting('name');
$pleine   = composition_colonne('public', 'pleine');
?>
<?php /* Le nom et le sous-titre figurent déjà dans l'en-tête du journal :
         on garde un h1 lisible par les lecteurs d'écran et les moteurs de
         recherche, mais invisible à l'écran (évite le doublon). Il reste en
         haut de page pour tout le monde : la une, elle, porte un h2. */ ?>
<h1 class="sr-only"><?= e($siteName) ?></h1>

<?php /* Un visiteur voit la Une du journal (1.31.0) : tout le contenu public,
         du plus récent au plus ancien, avec la porte d'entrée de la famille.
         La suite de ce fichier ne concerne que les comptes connectés. */ ?>
<?php if (!is_logged_in()): ?>
    <?php require __DIR__ . '/accueil_visiteur.php'; ?>
    <?php return; ?>
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
/* Blocs de la partie pleine largeur. « une » et « famille » sont déjà en haut
   de page (voir plus haut) : on ne les rejoue pas ici. */
$pleineAffichee = array_values(array_diff($pleine, ['une', 'famille']));
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
