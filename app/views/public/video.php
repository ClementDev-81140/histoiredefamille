<?php
/**
 * Fiche d'une vidéo, avec lecteur HTML5.
 *
 * Le fichier est servi par /video/{id} (script PHP) : lecture par morceaux
 * (HTTP Range), jamais chargé en entier en mémoire, droits vérifiés avant
 * l'ouverture du fichier.
 *
 * @var array  $video, $branches, $conversion
 * @var bool   $downloadable, $saturation
 * @var int    $maxStreams
 */
$duree = video_duration_label(isset($video['duration_seconds']) ? (int) $video['duration_seconds'] : null);
$poster = thumbnail_url($video);
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/videos">Vidéos</a>
    <span aria-hidden="true">/</span>
    <span><?= e($video['title']) ?></span>
</nav>

<article class="section document">
    <div class="section__entete">
        <h1><?= e($video['title']) ?></h1>
        <span class="actions">
            <span class="<?= e(visibility_class((string) $video['visibility'])) ?>">
                <?= e(visibility_label((string) $video['visibility'])) ?>
            </span>
            <?php if ((int) ($video['is_featured'] ?? 0) === 1): ?>
                <span class="badge badge-branches">Mise en avant</span>
            <?php endif; ?>
        </span>
    </div>

    <?php if ((string) ($conversion['phrase'] ?? '') !== '' && is_admin()): ?>
        <div class="message message--info">
            <strong>Conversion :</strong> <?= e((string) $conversion['phrase']) ?>
            <a href="/admin/conversions">Voir la file d’attente</a>.
        </div>
    <?php endif; ?>

    <?php if ($saturation): ?>
        <div class="video-indisponible">
            <strong>Trop de personnes regardent des vidéos en ce moment.</strong>
            Le Raspberry Pi ne peut pas diffuser plus de <?= (int) $maxStreams ?>
            vidéos en même temps. Réessayez dans une minute.
        </div>
    <?php else: ?>
        <video class="video-joueur" controls preload="metadata" playsinline
               <?php if ($poster !== null): ?>poster="<?= e($poster) ?>"<?php endif; ?>
               src="<?= e(video_url($video)) ?>"
               data-video-erreur="video-erreur-<?= (int) $video['id'] ?>">
            Votre navigateur ne permet pas la lecture de cette vidéo.
            <a href="/f/<?= (int) $video['id'] ?>">Ouvrir le fichier</a>.
        </video>

        <div class="video-indisponible" id="video-erreur-<?= (int) $video['id'] ?>" hidden>
            <strong>La vidéo n’a pas pu être lue.</strong>
            Soit la limite de lectures simultanées est atteinte (réessayez dans
            une minute), soit le fichier n’est pas au format MP4 (H.264 + AAC) :
            dans ce cas, <?php if ($downloadable): ?><a href="<?= e(download_url($video)) ?>">téléchargez-le</a><?php else: ?>demandez à un administrateur de le reconvertir<?php endif; ?>.
        </div>
    <?php endif; ?>

    <p class="video-meta">
        <?php if (trim((string) $video['doc_date']) !== ''): ?>
            <?= e($video['doc_date']) ?>
        <?php endif; ?>
        <?php if (trim((string) $video['doc_date']) !== '' && $duree !== ''): ?> · <?php endif; ?>
        <?php if ($duree !== ''): ?><?= e($duree) ?><?php endif; ?>
        <?php if (trim((string) $video['doc_date']) === '' && $duree === ''): ?>
            Ajoutée le <?= e(format_date((int) $video['created_at'], true)) ?>
        <?php endif; ?>
    </p>

    <div class="actions">
        <?php if ($downloadable): ?>
            <a class="bouton bouton--secondaire" href="<?= e(download_url($video)) ?>">Télécharger</a>
        <?php else: ?>
            <span class="discret">Téléchargement désactivé par l’administrateur.</span>
        <?php endif; ?>
        <a class="bouton bouton--secondaire" href="/videos">← Retour aux vidéos</a>
        <?php if (is_admin()): ?>
            <a class="bouton bouton--secondaire" href="/admin/videos/<?= (int) $video['id'] ?>">Modifier</a>
        <?php endif; ?>
    </div>

    <?php if (trim((string) $video['description']) !== ''): ?>
        <div class="texte video-description">
            <?= render_text($video['description']) ?>
        </div>
    <?php endif; ?>

    <dl class="fiche">
        <dt>Format</dt>
        <dd><?= e(strtoupper((string) $video['ext'])) ?> — <?= e((string) $video['mime']) ?></dd>
        <dt>Taille</dt>
        <dd><?= e(format_size((int) $video['size'])) ?></dd>
        <dt>Ajoutée le</dt>
        <dd><?= e(format_date((int) $video['created_at'], true)) ?></dd>
        <?php if ($branches !== []): ?>
            <dt>Branches concernées</dt>
            <dd>
                <?php foreach ($branches as $nomBranche): ?>
                    <span class="etiquette"><?= e($nomBranche) ?></span>
                <?php endforeach; ?>
            </dd>
        <?php endif; ?>
    </dl>
</article>
