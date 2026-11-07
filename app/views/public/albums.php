<?php
/**
 * Liste des albums photos.
 *
 * @var array $albums, $pagination
 * @var int   $total
 */
?>
<section class="section">
    <div class="section__entete">
        <h1>Albums photos</h1>
        <?php if (is_admin()): ?>
            <a class="bouton" href="/admin/albums/nouveau">Créer un album</a>
        <?php endif; ?>
    </div>

    <p class="discret">
        <?= (int) $total ?> album<?= (int) $total > 1 ? 's' : '' ?>
        <?php if (!is_logged_in()): ?>
            — les albums réservés aux familles ne sont pas affichés.
            <a href="/connexion">Se connecter</a>
        <?php endif; ?>
    </p>

    <?php
    $filtreCategories = $categories ?? [];
    $filtreCategorie  = (int) ($categorie ?? 0);
    $filtreAction     = '/albums';
    $filtreParam      = 'categorie';
    require __DIR__ . '/../partials/filtre_categories.php';
    ?>

    <?php if ($albums === []): ?>
        <div class="panneau panneau--centre">
            <p>Aucun album pour le moment.</p>
        </div>
    <?php else: ?>
        <div class="grille-albums">
            <?php foreach ($albums as $album): ?>
                <?php $couverture = $album['couverture']; ?>
                <article class="album-carte">
                    <a class="album-carte__lien" href="<?= e(album_url($album)) ?>">
                        <div class="album-carte__apercu">
                            <?php if ($couverture !== null && thumbnail_url($couverture) !== null): ?>
                                <img class="<?= e(aspect_photo_classe($couverture)) ?>" src="<?= e(thumbnail_url($couverture)) ?>" alt=""
                                     loading="lazy" width="480" height="360">
                            <?php else: ?>
                                <span class="album-carte__vide">Album</span>
                            <?php endif; ?>
                        </div>
                        <div class="album-carte__corps">
                            <h2 class="album-carte__titre"><?= e($album['title']) ?></h2>
                            <p class="album-carte__meta">
                                <?= (int) $album['nb_photos'] ?> photo<?= (int) $album['nb_photos'] > 1 ? 's' : '' ?>
                            </p>
                            <?php if (trim((string) $album['description']) !== ''): ?>
                                <p class="album-carte__texte"><?= e(text_summary((string) $album['description'], 120)) ?></p>
                            <?php endif; ?>
                            <?php if (is_logged_in()): ?>
                                <p><span class="<?= e(visibility_class((string) $album['visibility'])) ?>">
                                    <?= e(visibility_short((string) $album['visibility'])) ?>
                                </span></p>
                            <?php endif; ?>
                        </div>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</section>
