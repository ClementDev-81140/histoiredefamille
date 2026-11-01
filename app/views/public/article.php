<?php
/**
 * Lecture d'un article.
 *
 * @var array      $article, $branches, $others
 * @var array|null $image
 */
$publie = $article['status'] === 'publie' && (int) $article['published_at'] <= time();
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/articles">Articles</a>
    <span aria-hidden="true">/</span>
    <span><?= e($article['title']) ?></span>
</nav>

<?php if (is_admin() && !$publie): ?>
    <p class="note">
        Aperçu réservé aux administrateurs :
        <?= $article['status'] === 'brouillon' ? 'cet article est un brouillon.' : 'cet article est programmé pour le ' . e(format_date_long((int) $article['published_at'], false)) . '.' ?>
    </p>
<?php endif; ?>

<article class="section article">
    <header class="article__entete">
        <p class="article__meta">
            <?php if ($article['category'] !== ''): ?>
                <a class="article__rubrique" href="/articles?categorie=<?= rawurlencode((string) $article['category']) ?>"><?= e($article['category']) ?></a> ·
            <?php endif; ?>
            <?= e(format_date_long((int) $article['published_at'])) ?>
        </p>
        <h1><?= e($article['title']) ?></h1>
        <?php if (trim((string) $article['summary']) !== ''): ?>
            <p class="article__chapo"><?= e($article['summary']) ?></p>
        <?php endif; ?>
        <?php if (is_logged_in()): ?>
            <p>
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

    <div class="texte article__texte">
        <?= render_text($article['body']) ?>
    </div>

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
        <h2>À lire aussi</h2>
        <div class="articles-liste">
            <?php foreach ($others as $article): ?>
                <?php require __DIR__ . '/../partials/carte_article.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
