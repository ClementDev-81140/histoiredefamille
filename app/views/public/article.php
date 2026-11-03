<?php
/**
 * Lecture d'un article, mise en page d'article de presse.
 *
 * @var array      $article, $branches, $branchesNoms, $others
 * @var array|null $image
 * @var string     $auteur
 * @var int        $millesime, $decennie
 */
$publie = $article['status'] === 'publie' && (int) $article['published_at'] <= time();

$surtitre = [];
if (trim((string) $article['category']) !== '') {
    $surtitre[] = (string) $article['category'];
}
foreach ($branchesNoms ?? [] as $nom) {
    if (trim((string) $nom) !== '') {
        $surtitre[] = (string) $nom;
    }
}
if ((int) $decennie > 0) {
    $surtitre[] = 'Années ' . decennie_libelle((int) $decennie);
}
?><nav class="fil" aria-label="Fil d’Ariane">
    <a href="/articles">Articles</a>
    <span aria-hidden="true">/</span>
    <span><?= e($article['title']) ?></span>
</nav>

<?php if (is_admin() && !$publie): ?>
    <p class="note">
        Aperçu réservé aux administrateurs :
        <?= $article['status'] === 'brouillon' ? 'cet article est un brouillon.' : 'cet article est programmé pour le ' . e(french_long_date((int) $article['published_at'], false)) . '.' ?>
    </p>
<?php endif; ?>

<article class="section article article--presse">
    <header class="article__entete">
        <p class="article__meta article__surtitre">
            <?= e(implode(' · ', $surtitre)) ?>
        </p>
        <h1 class="article__titre"><?= e($article['title']) ?></h1>
        <p class="article__signature">
            <?php if (trim((string) $auteur) !== ''): ?>
                Par <?= e((string) $auteur) ?> —
            <?php endif; ?>
            <?= e(french_long_date((int) $article['published_at'])) ?>
        </p>
        <?php if (trim((string) $article['summary']) !== ''): ?>
            <?php /* Le chapô est un texte simple : on retire la mise en forme
                     légère éventuellement saisie (astérisques, titres). */ ?>
            <p class="article__chapo"><?= e(text_to_plain((string) $article['summary'])) ?></p>
        <?php endif; ?>
        <?php if (is_logged_in()): ?>
            <p class="article__visibilite">
                <span class="<?= e(visibility_class((string) $article['visibility'])) ?>">
                    <?= e(visibility_label((string) $article['visibility'])) ?>
                </span>
            </p>
        <?php endif; ?>
    </header>

    <?php if ($image !== null): ?>
        <figure class="article__image">
            <img src="<?= e($image['full']) ?>" alt="<?= e($article['title']) ?>">
        </figure>
    <?php endif; ?>

    <?php /* Corps du journal : justifié, avec césure et lettrine (style.css).
             Sur grand écran seulement, il passe en deux colonnes. */ ?>
    <div class="texte article__texte texte--presse">
        <?= render_text($article['body']) ?>
    </div>

    <p class="article__fin" aria-hidden="true"></p>

    <?php if (is_admin()): ?>
        <?php if ($branches !== []): ?>
            <p>
                Branches autorisées :
                <?php foreach ($branches as $branchId): ?>
                    <?php $branch = find_branch($branchId); ?>
                    <?php if ($branch): ?><span class="etiquette"><?= e($branch['name']) ?></span><?php endif; ?>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
        <p class="actions">
            <a class="bouton bouton--secondaire" href="/admin/articles/<?= (int) $article['id'] ?>">Modifier</a>
        </p>
    <?php endif; ?>
</article>

<?php if ($others !== []): ?>
    <section class="section">
        <h2 class="encart__titre encart__titre--section"><?= e(apparence_titre_encart('lire')) ?></h2>
        <div class="articles-colonnes">
            <?php foreach ($others as $article): ?>
                <?php $grand = false; require __DIR__ . '/../partials/carte_article.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
