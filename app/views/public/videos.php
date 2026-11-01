<?php
/**
 * Liste des vidéos.
 *
 * @var array  $videos, $pagination
 * @var string $q
 * @var int    $total
 */
?>
<section class="section">
    <div class="section__entete">
        <h1>Vidéos</h1>
        <?php if (is_admin()): ?>
            <a class="bouton" href="/admin/videos">Gérer les vidéos</a>
        <?php endif; ?>
    </div>

    <form method="get" action="/videos" class="filtres filtres--simple" role="search">
        <div class="champ">
            <label for="q">Recherche</label>
            <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Titre, description…">
        </div>
        <div class="champ champ--bouton">
            <button type="submit" class="bouton">Rechercher</button>
            <?php if ($q !== ''): ?>
                <a class="lien" href="/videos">Réinitialiser</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($videos === []): ?>
        <div class="panneau panneau--centre">
            <?php if (!is_logged_in()): ?>
                <p>Les vidéos de la famille sont réservées aux membres connectés.</p>
                <p><a class="bouton" href="/connexion?next=%2Fvideos">Se connecter</a></p>
            <?php elseif ($q !== ''): ?>
                <p>Aucune vidéo ne correspond à cette recherche.</p>
            <?php else: ?>
                <p>Aucune vidéo n’est disponible pour le moment.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="discret">
            <?= (int) $total ?> vidéo<?= (int) $total > 1 ? 's' : '' ?>
            <?php if (!is_logged_in()): ?>
                — seules les vidéos publiques sont affichées.
                <a href="/connexion?next=%2Fvideos">Se connecter</a> pour voir les autres.
            <?php endif; ?>
        </p>

        <div class="grille-cartes">
            <?php foreach ($videos as $doc): ?>
                <?php require __DIR__ . '/../partials/carte_video.php'; ?>
            <?php endforeach; ?>
        </div>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</section>
