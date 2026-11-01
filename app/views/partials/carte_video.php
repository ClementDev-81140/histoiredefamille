<?php
/**
 * Carte d'une vidéo dans l'onglet « Vidéos ».
 *
 * @var array $video
 */
$thumb    = thumbnail_url($video);
$duree    = video_duration_short(isset($video['duration_seconds']) ? (int) $video['duration_seconds'] : null);
$annee    = trim((string) ($video['doc_date'] ?? ''));
$miseEnAvant = (int) ($video['is_featured'] ?? 0) === 1;
$resume   = trim((string) ($video['description'] ?? ''));
?>
<article class="carte<?= $miseEnAvant ? ' carte--mise-en-avant' : '' ?>">
    <a class="carte__lien" href="<?= e(video_page_url($video)) ?>">
        <div class="carte__apercu carte__apercu--video">
            <?php if ($thumb !== null): ?>
                <img src="<?= e($thumb) ?>" alt="" loading="lazy" width="480" height="360">
            <?php else: ?>
                <span class="carte__type">Vidéo</span>
            <?php endif; ?>
            <span class="carte__lecture" aria-hidden="true">▶</span>
            <?php if ($duree !== ''): ?>
                <span class="carte__duree"><?= e($duree) ?></span>
            <?php endif; ?>
        </div>
        <div class="carte__corps">
            <h3 class="carte__titre"><?= e($video['title']) ?></h3>
            <p class="carte__meta">
                <?php if ($annee !== ''): ?><?= e($annee) ?><?php endif; ?>
                <?php if ($annee !== '' && $duree !== ''): ?> · <?php endif; ?>
                <?php if ($duree !== ''): ?><?= e(video_duration_label(isset($video['duration_seconds']) ? (int) $video['duration_seconds'] : null)) ?><?php endif; ?>
            </p>
            <?php if ($resume !== ''): ?>
                <p class="carte__meta"><?= e(text_summary($resume, 110)) ?></p>
            <?php endif; ?>
            <?php if (is_logged_in()): ?>
                <p class="carte__visibilite">
                    <span class="<?= e(visibility_class((string) $video['visibility'])) ?>">
                        <?= e(visibility_short((string) $video['visibility'])) ?>
                    </span>
                </p>
            <?php endif; ?>
        </div>
    </a>
    <?php if ($miseEnAvant): ?>
        <p class="carte__mise-en-avant">Mise en avant</p>
    <?php endif; ?>
</article>
