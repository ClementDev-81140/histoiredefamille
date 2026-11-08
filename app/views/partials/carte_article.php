<?php
/**
 * Un article dans une liste (accueil, /articles, « à lire aussi »).
 *
 * @var array $article
 * @var bool  $grand  (facultatif) version « à la une », en grand
 */
$grand   = !empty($grand);
$image   = article_image($article);
// La photo de l'article à la une peut être masquée (réglage « Composition des
// pages ») : la carte reste, sans image ni plaque de repli.
if ($grand && !composition_une_photo()) {
    $image = null;
}
// Un article sans photo (ou dont la photo n'est pas visible) n'affiche
// AUCUN cadre : ni carré, ni lettre, ni plaque de repli. La mise en page se
// réorganise d'elle-même (classe « sans-image » sur la carte).
//
// Le résumé est nettoyé de sa mise en forme légère : un aperçu ne doit pas
// afficher « **gras** » ni « ## Titre » (même règle que les documents).
// Sa longueur suit le réglage « Réglages de la une » (court, moyen, long).
$resume  = trim((string) $article['summary']) !== ''
    ? text_to_plain((string) $article['summary'])
    : text_summary((string) $article['body'], composition_longueur_resume($grand));

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
// 1.34.0 : l'étiquette de décennie n'existe que pour un article de souvenir,
// sous la forme « Souvenir des années 1950 » ; un article d'actualité n'en a pas.
$decennieSouvenir = article_decennie_souvenir($article);
if ($decennieSouvenir > 0) {
    $surtitre[] = decennie_souvenir_libelle($decennieSouvenir);
}
// 1.36.0 : le type (« Article ») s'affiche en petit, jamais en double — si
// le surtitre (le thème) contient déjà ce mot, on ne le répète pas, et un
// thème qui n'est que le type (« Articles ») disparaît du surtitre.
$surtitre = array_values(array_filter(
    $surtitre,
    static fn (string $mot): bool => mb_strtolower(rtrim($mot, 's')) !== 'article'
));
$typeDejaLa = false;
foreach ($surtitre as $mot) {
    if (mb_stripos($mot, 'article') !== false) {
        $typeDejaLa = true;
    }
}
?>
<?php $classes = 'article-carte' . ($grand ? ' article-carte--une' : '') . ($image === null ? ' article-carte--sans-image' : ''); ?>
<article class="<?= e($classes) ?>">
    <?php if ($image !== null): ?>
        <a class="article-carte__image" href="/articles/<?= e($article['slug']) ?>" tabindex="-1" aria-hidden="true">
            <img class="<?= e((string) ($image['aspect'] ?? '')) ?>" src="<?= e($image['thumb']) ?>" alt="" loading="lazy">
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
            <?php if (!$typeDejaLa): ?><span class="article-carte__type">Article</span> · <?php endif; ?><?php if ($grand): ?>
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
