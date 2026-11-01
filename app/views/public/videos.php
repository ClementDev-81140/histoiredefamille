<?php
/**
 * Onglet « Vidéos » : liste des vidéos accessibles au compte connecté.
 *
 * @var array $videos, $pagination
 * @var int   $total, $nbFeatured
 */
?>
<section class="section">
    <div class="section__entete">
        <h1>Vidéos</h1>
        <?php if (is_admin()): ?>
            <span class="actions">
                <a class="bouton" href="/admin/videos">Gérer les vidéos</a>
            </span>
        <?php endif; ?>
    </div>

    <p class="videos-intro">
        <?= (int) $total ?> vidéo<?= (int) $total > 1 ? 's' : '' ?>
        <?php if ($nbFeatured > 0): ?>
            — dont <?= (int) $nbFeatured ?> mise<?= $nbFeatured > 1 ? 's' : '' ?> en avant
        <?php endif; ?>.
        Les vidéos sont lues directement, sans conversion : le fichier doit être
        au format MP4 (H.264 + AAC).
    </p>

    <?php if ($videos === []): ?>
        <div class="panneau panneau--centre">
            <p class="discret">
                Aucune vidéo pour le moment.
                <?php if (is_admin()): ?>
                    Les administrateurs peuvent en ajouter depuis
                    <a href="/admin/videos">la gestion des vidéos</a>
                    ou <a href="/admin/importer">le dossier arrivée</a>.
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <div class="grille-cartes">
            <?php foreach ($videos as $video): ?>
                <?php require __DIR__ . '/../partials/carte_video.php'; ?>
            <?php endforeach; ?>
        </div>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</section>
