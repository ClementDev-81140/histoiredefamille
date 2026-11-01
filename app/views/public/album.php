<?php
/**
 * Un album photos et ses photos.
 *
 * @var array $album, $photos, $personnes, $pagination
 * @var int   $total
 */
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/albums">Albums photos</a>
    <span aria-hidden="true">/</span>
    <span><?= e($album['title']) ?></span>
</nav>

<section class="section album">
    <div class="section__entete">
        <h1><?= e($album['title']) ?></h1>
        <div class="actions">
            <button type="button" class="bouton bouton--secondaire" data-imprimer>Imprimer</button>
            <?php if (is_admin()): ?>
                <a class="bouton bouton--secondaire"
                   href="/admin/albums/<?= (int) $album['id'] ?>/modifier">Modifier</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (is_logged_in()): ?>
        <p><span class="<?= e(visibility_class((string) $album['visibility'])) ?>">
            <?= e(visibility_label((string) $album['visibility'])) ?>
        </span></p>
    <?php endif; ?>

    <?php if (trim((string) $album['description']) !== ''): ?>
        <div class="texte album__description">
            <?= render_text((string) $album['description']) ?>
        </div>
    <?php endif; ?>

    <p class="discret"><?= (int) $total ?> photo<?= (int) $total > 1 ? 's' : '' ?></p>

    <?php if ($photos === []): ?>
        <div class="panneau panneau--centre">
            <p>Cet album ne contient encore aucune photo.</p>
        </div>
    <?php else: ?>
        <div class="grille-album">
            <?php foreach ($photos as $photo): ?>
                <?php
                $idents = $personnes[(int) $photo['id']] ?? [];
                $legende = album_people_line($idents);
                $thumb = thumbnail_url($photo);
                ?>
                <figure class="vignette">
                    <a class="vignette__lien" href="<?= e(document_url($photo)) ?>">
                        <?php if ($thumb !== null): ?>
                            <img src="<?= e($thumb) ?>" alt="<?= e($photo['title']) ?>"
                                 loading="lazy" width="480" height="360">
                        <?php else: ?>
                            <span class="vignette__vide">Photo</span>
                        <?php endif; ?>
                    </a>
                    <figcaption class="vignette__legende">
                        <a href="<?= e(document_url($photo)) ?>"><?= e($photo['title']) ?></a>
                        <?php if (trim((string) $photo['doc_date']) !== ''): ?>
                            <span class="discret"><?= e($photo['doc_date']) ?></span>
                        <?php endif; ?>
                        <?php if ($legende !== ''): ?>
                            <span class="vignette__personnes"><?= e($legende) ?></span>
                        <?php endif; ?>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</section>
