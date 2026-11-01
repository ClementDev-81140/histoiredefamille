<?php
/**
 * Lecteur d'une vidéo.
 *
 * @var array $doc, $branches, $others
 */
$thumb = thumbnail_url($doc);
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/videos">Vidéos</a>
    <span aria-hidden="true">/</span>
    <span><?= e($doc['title']) ?></span>
</nav>

<article class="section video-page">
    <div class="section__entete">
        <h1><?= e($doc['title']) ?></h1>
        <?php if (is_logged_in()): ?>
            <span class="<?= e(visibility_class((string) $doc['visibility'])) ?>">
                <?= e(visibility_label((string) $doc['visibility'])) ?>
            </span>
        <?php endif; ?>
    </div>

    <div class="video-page__lecteur">
        <video class="document__video" controls preload="metadata" playsinline
               poster="<?= $thumb !== null ? e($thumb) : '' ?>"
               src="<?= e(video_url($doc)) ?>">
            Votre navigateur ne permet pas la lecture de cette vidéo.
        </video>
        <p class="note">
            Si la vidéo ne démarre pas tout de suite, patientez une minute puis rechargez la page :
            le nombre de lectures simultanées est limité pour ne pas surcharger le serveur.
            <?php if (is_logged_in()): ?>
                Vous pouvez aussi <a href="<?= e(download_url($doc)) ?>">télécharger le fichier</a>.
            <?php endif; ?>
        </p>
    </div>

    <dl class="fiche">
        <?php if (trim((string) $doc['doc_date']) !== ''): ?>
            <dt>Date</dt><dd><?= e($doc['doc_date']) ?></dd>
        <?php endif; ?>
        <dt>Taille</dt><dd><?= e(format_size((int) $doc['size'])) ?></dd>
        <dt>Ajoutée le</dt><dd><?= e(format_date((int) $doc['created_at'])) ?></dd>
    </dl>

    <?php if (trim((string) $doc['description']) !== ''): ?>
        <div class="texte">
            <?= render_text($doc['description']) ?>
        </div>
    <?php endif; ?>

    <?php if (is_admin()): ?>
        <?php if ($branches !== []): ?>
            <p>
                Branches autorisées :
                <?php foreach ($branches as $branchId): ?>
                    <?php $branch = find_branch($branchId); ?>
                    <?php if ($branch): ?><span class="etiquette"><?= e($branch['name']) ?></span><?php endif; ?>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
        <p class="actions">
            <a class="bouton bouton--secondaire" href="/admin/documents/<?= (int) $doc['id'] ?>/modifier">Modifier</a>
            <a class="bouton bouton--secondaire" href="/admin/videos">Gérer les vidéos</a>
        </p>
    <?php endif; ?>
</article>

<?php if ($others !== []): ?>
    <section class="section">
        <h2>À voir aussi</h2>
        <div class="grille-cartes">
            <?php foreach ($others as $doc): ?>
                <?php require __DIR__ . '/../partials/carte_video.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
