<?php
/**
 * Carte d'une vidéo dans la liste /videos.
 *
 * @var array $doc
 */
$thumb = thumbnail_url($doc);
$date  = trim((string) ($doc['doc_date'] ?? ''));
?>
<article class="carte carte--video">
    <a class="carte__lien" href="/videos/<?= (int) $doc['id'] ?>">
        <div class="carte__apercu carte__apercu--video">
            <?php if ($thumb !== null): ?>
                <img src="<?= e($thumb) ?>" alt="" loading="lazy" width="480" height="360">
            <?php else: ?>
                <span class="carte__type">Vidéo</span>
            <?php endif; ?>
            <span class="carte__lecture" aria-hidden="true">▶</span>
        </div>
        <div class="carte__corps">
            <?php if ((int) ($doc['featured'] ?? 0) === 1): ?>
                <p class="carte__meta carte__meta--une">À la une</p>
            <?php endif; ?>
            <h3 class="carte__titre"><?= e($doc['title']) ?></h3>
            <p class="carte__meta">
                <?php if ($date !== ''): ?><?= e($date) ?> · <?php endif; ?>
                <?= e(format_size((int) $doc['size'])) ?>
            </p>
            <?php if (trim((string) $doc['description']) !== ''): ?>
                <p class="carte__meta"><?= e(excerpt((string) $doc['description'], 110)) ?></p>
            <?php endif; ?>
            <?php if (is_logged_in()): ?>
                <p class="carte__visibilite">
                    <span class="<?= e(visibility_class((string) $doc['visibility'])) ?>">
                        <?= e(visibility_short((string) $doc['visibility'])) ?>
                    </span>
                </p>
            <?php endif; ?>
        </div>
    </a>
</article>
