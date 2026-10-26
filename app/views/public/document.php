<?php
/**
 * Fiche d'un document.
 *
 * @var array      $doc, $tags, $branches, $related
 * @var array|null $category
 */
$kind      = (string) $doc['kind'];
$isPhoto   = $kind === 'photo';
$isVideo   = $kind === 'video';
$isPdf     = strtolower((string) $doc['ext']) === 'pdf';
$thumb     = thumbnail_url($doc);
$canAdmin  = is_admin();
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/documents">Documents</a>
    <span aria-hidden="true">/</span>
    <span><?= e($doc['title']) ?></span>
</nav>

<article class="section document">
    <div class="section__entete">
        <h1><?= e($doc['title']) ?></h1>
        <span class="<?= e(visibility_class((string) $doc['visibility'])) ?>">
            <?= e(visibility_label((string) $doc['visibility'])) ?>
        </span>
    </div>

    <div class="document__corps">
        <div class="document__apercu">
            <?php if ($isPhoto): ?>
                <img class="document__image" src="<?= e(file_url($doc)) ?>"
                     alt="<?= e($doc['title']) ?>">
            <?php elseif ($isVideo): ?>
                <video class="document__video" controls preload="metadata"
                       poster="<?= $thumb !== null ? e($thumb) : '' ?>"
                       src="<?= e(video_url($doc)) ?>">
                    Votre navigateur ne permet pas la lecture de cette vidéo.
                    <a href="<?= e(file_url($doc)) ?>">Ouvrir le fichier</a>.
                </video>
                <p class="note">
                    Lecture directe sans conversion. Si la vidéo ne démarre pas, le fichier
                    n’est probablement pas au format MP4 (H.264 + AAC) : dans ce cas,
                    <a href="<?= e(download_url($doc)) ?>">téléchargez-le</a>.
                </p>
            <?php elseif ($isPdf): ?>
                <div class="panneau panneau--centre">
                    <p class="document__icone" aria-hidden="true">PDF</p>
                    <p><a class="bouton" href="<?= e(file_url($doc)) ?>" target="_blank" rel="noopener">
                        Ouvrir le PDF dans un nouvel onglet
                    </a></p>
                </div>
            <?php else: ?>
                <div class="panneau panneau--centre">
                    <p class="document__icone" aria-hidden="true"><?= e(strtoupper((string) $doc['ext'])) ?></p>
                    <p><a class="bouton" href="<?= e(download_url($doc)) ?>">Télécharger le fichier</a></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="document__infos">
            <h2>Informations</h2>
            <dl class="fiche">
                <?php if (trim((string) $doc['doc_date']) !== ''): ?>
                    <dt>Date</dt><dd><?= e($doc['doc_date']) ?></dd>
                <?php endif; ?>
                <?php if ($category !== null): ?>
                    <dt>Catégorie</dt>
                    <dd><a href="/documents?categorie=<?= (int) $category['id'] ?>"><?= e($category['name']) ?></a></dd>
                <?php endif; ?>
                <dt>Format</dt>
                <dd><?= e(strtoupper((string) $doc['ext'])) ?> — <?= e((string) $doc['mime']) ?></dd>
                <dt>Taille</dt><dd><?= e(format_size((int) $doc['size'])) ?></dd>
                <?php if (!empty($doc['width']) && !empty($doc['height'])): ?>
                    <dt>Dimensions</dt><dd><?= (int) $doc['width'] ?> × <?= (int) $doc['height'] ?> px</dd>
                <?php endif; ?>
                <dt>Ajouté le</dt><dd><?= e(format_date((int) $doc['created_at'], true)) ?></dd>
                <?php if ((int) $doc['downloaded_count'] > 0): ?>
                    <dt>Téléchargements</dt><dd><?= (int) $doc['downloaded_count'] ?></dd>
                <?php endif; ?>
            </dl>

            <?php if (trim((string) $doc['description']) !== ''): ?>
                <h2>Description</h2>
                <div class="texte">
                    <?= render_text($doc['description']) ?>
                </div>
            <?php endif; ?>

            <?php if ($tags !== []): ?>
                <h2>Étiquettes</h2>
                <p class="etiquettes">
                    <?php foreach ($tags as $tag): ?>
                        <a class="etiquette" href="/documents?q=<?= rawurlencode($tag) ?>"><?= e($tag) ?></a>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>

            <?php if ($canAdmin && $branches !== []): ?>
                <h2>Branches autorisées</h2>
                <p>
                    <?php foreach ($branches as $branchId): ?>
                        <?php $branch = find_branch($branchId); ?>
                        <?php if ($branch): ?>
                            <span class="etiquette"><?= e($branch['name']) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>

            <p class="actions">
                <?php if (!$isPhoto): ?>
                    <a class="bouton bouton--secondaire" href="<?= e(download_url($doc)) ?>">Télécharger</a>
                <?php endif; ?>
                <?php if ($canAdmin): ?>
                    <a class="bouton bouton--secondaire" href="/admin/documents/<?= (int) $doc['id'] ?>/modifier">Modifier</a>
                    <form method="post" action="/admin/documents/<?= (int) $doc['id'] ?>/supprimer"
                          class="formulaire-en-ligne"
                          data-confirmation="Supprimer définitivement ce document ?">
                        <?= csrf_field() ?>
                        <button type="submit" class="bouton bouton--danger">Supprimer</button>
                    </form>
                <?php endif; ?>
            </p>
        </div>
    </div>
</article>

<?php if ($related !== []): ?>
    <section class="section">
        <h2>Dans la même catégorie</h2>
        <div class="grille-cartes">
            <?php foreach ($related as $doc): ?>
                <?php require __DIR__ . '/../partials/carte_document.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
