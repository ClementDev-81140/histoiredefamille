<?php
/**
 * Un article dans une liste (accueil, /articles, « à lire aussi »).
 *
 * @var array $article
 * @var bool  $grand  (facultatif) version « à la une », en grand
 */
$grand   = !empty($grand);
$image   = article_image($article);
// Le résumé est nettoyé de sa mise en forme légère : un aperçu ne doit pas
// afficher « **gras** » ni « ## Titre » (même règle que les documents).
$resume  = trim((string) $article['summary']) !== ''
    ? text_to_plain((string) $article['summary'])
    : text_summary((string) $article['body'], $grand ? 320 : 180);
?>
<article class="article-carte<?= $grand ? ' article-carte--une' : '' ?>">
    <?php if ($image !== null): ?>
        <a class="article-carte__image" href="/articles/<?= e($article['slug']) ?>" tabindex="-1" aria-hidden="true">
            <img src="<?= e($image['thumb']) ?>" alt="" loading="lazy">
        </a>
    <?php endif; ?>
    <div class="article-carte__corps">
        <p class="article-carte__meta">
            <?php if ($article['category'] !== ''): ?>
                <span class="article-carte__rubrique"><?= e($article['category']) ?></span> ·
            <?php endif; ?>
            <?= e(french_long_date((int) $article['published_at'], false)) ?>
            <?php if (is_logged_in() && (string) $article['visibility'] !== 'public'): ?>
                <span class="<?= e(visibility_class((string) $article['visibility'])) ?>">
                    <?= e(visibility_short((string) $article['visibility'])) ?>
                </span>
            <?php endif; ?>
        </p>
        <h3 class="article-carte__titre">
            <a href="/articles/<?= e($article['slug']) ?>"><?= e($article['title']) ?></a>
        </h3>
        <p class="article-carte__resume"><?= e($resume) ?></p>
        <p class="article-carte__suite"><a href="/articles/<?= e($article['slug']) ?>">Lire la suite →</a></p>
    </div>
</article>
