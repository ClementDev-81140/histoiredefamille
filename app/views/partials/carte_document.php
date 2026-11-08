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
// 1.36.0 : le surtitre est le thème (s'il y en a un) ; le type s'affiche en
// petit dans les métadonnées, sans répéter le mot déjà montré dans l'aperçu.
$themesCarte = themes_noms_pour($kind, (int) $doc['id']);
$typeDejaAffiche = $thumb === null && !$isVideo;
?>
<article class="carte">
    <a class="carte__lien" href="<?= e(document_url($doc)) ?>">
        <div class="carte__apercu carte__apercu--<?= e($kind) ?><?= $thumb === null && $isVideo ? ' carte__apercu--sans-image' : '' ?>">
            <?php if ($thumb !== null): ?>
                <img class="<?= e(aspect_photo_classe($doc)) ?>" src="<?= e($thumb) ?>" alt="" loading="lazy" width="480" height="360">
            <?php elseif (!$isVideo): ?>
                <span class="carte__type"><?= e(document_kind_label($kind, (string) $doc['ext'])) ?></span>
            <?php endif; ?>
            <?php if ($isVideo): ?>
                <span class="carte__lecture" aria-hidden="true">▶</span>
            <?php endif; ?>
        </div>
        <div class="carte__corps">
            <?php if ($themesCarte !== []): ?>
                <p class="carte__surtitre"><?= e(implode(' · ', $themesCarte)) ?></p>
            <?php endif; ?>
            <h3 class="carte__titre"><?= e($doc['title']) ?></h3>
            <p class="carte__meta">
                <?php if (!$typeDejaAffiche): ?><?= e(document_kind_label($kind, (string) $doc['ext'])) ?> · <?php endif; ?><?php if ($dates !== ''): ?><?= e($dates) ?> · <?php endif; ?><?= e(format_size((int) $doc['size'])) ?><?php if (document_est_archive($doc)): ?> · Archive<?php endif; ?>
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
