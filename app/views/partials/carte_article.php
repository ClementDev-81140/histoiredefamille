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

// Surtitre : rubrique, branche (si l'article est réservé à des branches) et
// décennie racontée. Les trois informations restent séparées par « · ».
$surtitre = [];
if (trim((string) $article['category']) !== '') {
    $surtitre[] = (string) $article['category'];
}
$brancheNom = trim((string) ($article['branche_nom'] ?? ''));
if ($brancheNom !== '') {
    $surtitre[] = $brancheNom;
}
$surtitre[] = decennie_libelle(article_decennie($article));

// Lettre de la plaque d'image de repli (pas de photo) : la rubrique, sinon
// le titre, sinon « G » comme gazette.
$lettre = mb_substr((string) $article['category'], 0, 1, 'UTF-8');
if ($lettre === '') {
    $lettre = mb_substr(trim((string) $article['title']), 0, 1, 'UTF-8');
}
if ($lettre === '') {
    $lettre = 'G';
}
?>
<article class="article-carte<?= $grand ? ' article-carte--une' : '' ?>">
    <?php if ($image !== null): ?>
        <a class="article-carte__image" href="/articles/<?= e($article['slug']) ?>" tabindex="-1" aria-hidden="true">
            <img src="<?= e($image['thumb']) ?>" alt="" loading="lazy">
        </a>
    <?php else: ?>
        <?php /* Plaque de repli : papier sépia et initiale en gothique, comme
                 une vignette de journal quand la photo manque. */ ?>
        <a class="article-carte__image article-carte__plaque" href="/articles/<?= e($article['slug']) ?>" tabindex="-1" aria-hidden="true">
            <span class="plaque__lettre"><?= e($lettre) ?></span>
        </a>
    <?php endif; ?>
    <div class="article-carte__corps">
        <p class="article-carte__meta article-carte__surtitre">
            <?php if ($surtitre !== []): ?>
                <?= e(implode(' · ', $surtitre)) ?>
            <?php endif; ?>
            <?php if (is_logged_in() && (string) $article['visibility'] !== 'public'): ?>
                <span class="<?= e(visibility_class((string) $article['visibility'])) ?>">
                    <?= e(visibility_short((string) $article['visibility'])) ?>
                </span>
            <?php endif; ?>
        </p>
        <h3 class="article-carte__titre">
            <a href="/articles/<?= e($article['slug']) ?>"><?= e($article['title']) ?></a>
        </h3>
        <?php $auteur = trim((string) ($article['auteur'] ?? '')); ?>
        <p class="article-carte__signature">
            <?php if ($grand): ?>
                <?php if ($auteur !== ''): ?>Par <?= e($auteur) ?> —<?php endif; ?>
                <?= e(french_long_date((int) $article['published_at'], false)) ?>
            <?php elseif ($auteur !== ''): ?>
                — <?= e($auteur) ?>
            <?php else: ?>
                <?= e(french_long_date((int) $article['published_at'], false)) ?>
            <?php endif; ?>
        </p>
        <p class="article-carte__resume"><?= e($resume) ?></p>
        <p class="article-carte__suite"><a href="/articles/<?= e($article['slug']) ?>">Lire la suite →</a></p>
    </div>
</article>
