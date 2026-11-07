<?php
/**
 * Une vignette de la Une publique (1.31.0, revue en 1.32.1).
 *
 * Trois formes, selon ce que le contenu peut montrer :
 *
 *   - **avec image** : la vignette, le repère de rubrique, le titre, la date ;
 *   - **vidéo sans image** : la vignette de remplacement de la 1.32.0 — une
 *     icône de lecture sur le papier, jamais un carré gris ni une image
 *     cassée (une vidéo peut recevoir son image extraite par ffmpeg ; tant
 *     qu'elle n'en a pas, c'est cette vignette-là qui s'affiche) ;
 *   - **sans image** : une **carte texte seule** — titre, date, extrait —
 *     sans zone d'image, sans tuile grise et sans bloc de rubrique, pour que
 *     la grille ne montre aucun trou.
 *
 * Attend `$item` (voir une_publique_items()). Aucun lien vers autre chose que
 * la page publique du contenu. Chaque contenu n'apparaît qu'une fois.
 *
 * @var array $item
 */
$image             = $item['image'] ?? null;
$estVideoSansImage = $image === null && (string) ($item['type'] ?? '') === 'videos';
?>
<?php if ($image !== null): ?>
    <li class="une-carte">
        <a class="une-carte__lien" href="<?= e($item['url']) ?>">
            <span class="une-carte__vignette">
                <img src="<?= e($image) ?>" alt="" width="320" height="240" loading="lazy">
            </span>
            <span class="une-carte__type"><?= e($item['rubrique']) ?></span>
            <span class="une-carte__titre"><?= e($item['titre']) ?></span>
            <span class="une-carte__meta">
                <span class="discret"><?= e($item['date_texte']) ?></span>
                <span class="discret"><?= e($item['genre']) ?></span>
            </span>
        </a>
    </li>
<?php elseif ($estVideoSansImage): ?>
    <li class="une-carte une-carte--video">
        <a class="une-carte__lien" href="<?= e($item['url']) ?>">
            <span class="une-carte__vignette une-carte__vignette--video" aria-hidden="true">
                <span class="une-carte__lecture">▶</span>
            </span>
            <span class="une-carte__type"><?= e($item['rubrique']) ?></span>
            <span class="une-carte__titre"><?= e($item['titre']) ?></span>
            <span class="une-carte__meta">
                <span class="discret"><?= e($item['date_texte']) ?></span>
                <span class="discret"><?= e($item['genre']) ?></span>
            </span>
        </a>
    </li>
<?php else: ?>
    <li class="une-carte une-carte--texte">
        <a class="une-carte__lien" href="<?= e($item['url']) ?>">
            <span class="une-carte__titre"><?= e($item['titre']) ?></span>
            <?php if ((string) ($item['resume'] ?? '') !== ''): ?>
                <span class="une-carte__resume"><?= e($item['resume']) ?></span>
            <?php endif; ?>
            <span class="une-carte__meta">
                <span class="discret"><?= e($item['date_texte']) ?></span>
            </span>
        </a>
    </li>
<?php endif; ?>
