<?php
/**
 * Gestion des vidéos (administration).
 *
 * @var array $videos
 */
?>
<div class="section__entete">
    <h1>Vidéos</h1>
    <div class="actions">
        <a class="bouton" href="/admin/importer">Ajouter depuis le dossier arrivée</a>
        <a class="bouton bouton--secondaire" href="/admin/documents/nouveau">Envoyer un fichier</a>
    </div>
</div>

<p class="discret">
    Les vidéos « à la une » apparaissent en premier dans l’onglet Vidéos, puis par
    numéro d’ordre croissant (0 = en premier), puis les plus récentes. La visibilité
    (public, familles, branches, privé) se règle dans « Modifier ».
</p>

<?php if ($videos === []): ?>
    <div class="panneau panneau--centre">
        <p>Aucune vidéo pour le moment. Déposez un fichier MP4 dans le dossier arrivée,
           puis importez-le depuis « Dossier arrivée ».</p>
    </div>
<?php else: ?>
    <div class="tableau-defilant">
        <table class="tableau">
            <thead>
                <tr>
                    <th>Vidéo</th>
                    <th>Visibilité</th>
                    <th>À la une</th>
                    <th>Ordre</th>
                    <th><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($videos as $video): ?>
                <?php $formId = 'video-' . (int) $video['id']; ?>
                <tr>
                    <td>
                        <a href="/videos/<?= (int) $video['id'] ?>"><?= e($video['title']) ?></a><br>
                        <span class="discret"><?= e(format_size((int) $video['size'])) ?></span>
                    </td>
                    <td>
                        <span class="<?= e(visibility_class((string) $video['visibility'])) ?>">
                            <?= e(visibility_short((string) $video['visibility'])) ?>
                        </span>
                    </td>
                    <td>
                        <input type="checkbox" form="<?= e($formId) ?>" name="featured" value="1"
                               aria-label="À la une : <?= e($video['title']) ?>"<?= checked((int) $video['featured'] === 1) ?>>
                    </td>
                    <td>
                        <input type="number" form="<?= e($formId) ?>" name="sort_order" min="0" max="9999"
                               value="<?= (int) $video['sort_order'] ?>" class="champ-nombre"
                               aria-label="Ordre : <?= e($video['title']) ?>">
                    </td>
                    <td>
                        <form id="<?= e($formId) ?>" method="post" action="/admin/videos/<?= (int) $video['id'] ?>"
                              class="formulaire-en-ligne">
                            <?= csrf_field() ?>
                            <button type="submit" class="bouton bouton--secondaire">Enregistrer</button>
                            <a class="lien" href="/admin/documents/<?= (int) $video['id'] ?>/modifier">Modifier</a>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
