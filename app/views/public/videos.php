<?php
/**
 * Onglet « Vidéos » : liste des vidéos que le visiteur a le droit de voir
 * (les publiques pour tout le monde, les autres selon le compte connecté).
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
        <?php if ((int) $total === 0): ?>
            Aucune vidéo publique pour le moment.
        <?php else: ?>
            <?= (int) $total ?> vidéo<?= (int) $total > 1 ? 's' : '' ?>
            <?php if ($nbFeatured > 0): ?>
                — dont <?= (int) $nbFeatured ?> mise<?= $nbFeatured > 1 ? 's' : '' ?> en avant
            <?php endif; ?>.
        <?php endif; ?>
        <?php if (!is_logged_in() && (int) $total > 0): ?>
            Une partie des vidéos est réservée aux familles :
            <a href="/connexion?next=%2Fvideos">connectez-vous</a> pour les voir.
        <?php endif; ?>
    </p>

    <?php if ($videos === []): ?>
        <div class="panneau panneau--centre">
            <p class="discret">
                Aucune vidéo<?= is_logged_in() ? '' : ' publique' ?> pour le moment.
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
