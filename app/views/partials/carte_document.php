<?php
/**
 * Carte d'un document dans une liste.
 *
 * @var array $doc
 */
$thumb   = thumbnail_url($doc);
$kind    = (string) $doc['kind'];
$isVideo = $kind === 'video';
$dates   = trim((string) ($doc['doc_date'] ?? ''));
?>
<article class="carte">
    <a class="carte__lien" href="<?= e(document_url($doc)) ?>">
        <div class="carte__apercu carte__apercu--<?= e($kind) ?>">
            <?php if ($thumb !== null): ?>
                <img src="<?= e($thumb) ?>" alt="" loading="lazy" width="480" height="360">
            <?php else: ?>
                <span class="carte__type"><?= e(document_kind_label($kind, (string) $doc['ext'])) ?></span>
            <?php endif; ?>
            <?php if ($isVideo): ?>
                <span class="carte__lecture" aria-hidden="true">▶</span>
            <?php endif; ?>
        </div>
        <div class="carte__corps">
            <h3 class="carte__titre"><?= e($doc['title']) ?></h3>
            <p class="carte__meta">
                <?= e(document_kind_label($kind, (string) $doc['ext'])) ?>
                <?php if ($dates !== ''): ?> · <?= e($dates) ?><?php endif; ?>
                · <?= e(format_size((int) $doc['size'])) ?>
            </p>
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
