<?php
/**
 * Import depuis le dossier arrivée.
 *
 * @var array  $files, $categories, $branches, $erreurs, $old, $allowed
 * @var string $incoming
 */
?>
<h1>Importer depuis le dossier arrivée</h1>

<p class="note">
    Déposez les gros fichiers (vidéos, archives photo) directement sur le Raspberry Pi
    dans le dossier <code><?= e($incoming) ?></code> via SFTP ou le partage Samba,
    puis importez-les ici. Ils n’y sont pas accessibles par le site tant qu’ils ne sont
    pas importés.
</p>

<div class="message message--info">
    <p><strong>Vidéos :</strong> elles doivent être au format <strong>MP4 (H.264 + AAC)</strong>,
    préparées à l’avance. Le serveur ne convertit aucun fichier.</p>
    <p class="note">La procédure de dépôt (SFTP/Samba) est décrite dans le README, section « Vidéos ».</p>
</div>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if ($files === []): ?>
    <div class="panneau panneau--centre">
        <p>Le dossier arrivée est vide.</p>
        <p class="discret">Formats acceptés à l’import : <?= e(implode(', ', $allowed)) ?>.</p>
    </div>
<?php else: ?>
    <p class="discret"><?= count($files) ?> fichier(s) en attente.</p>

    <?php foreach ($files as $file): ?>
        <?php
        $defaultTitle = pathinfo($file['name'], PATHINFO_FILENAME);
        $isVideo = in_array($file['ext'], ['mp4', 'm4v'], true);
        ?>
        <section class="panneau">
            <div class="section__entete">
                <h2><?= e($file['name']) ?></h2>
                <span class="badge badge-<?= $isVideo ? 'famille' : 'public' ?>">
                    <?= e(strtoupper($file['ext'])) ?>
                </span>
            </div>
            <p class="discret">
                <?= e(format_size((int) $file['size'])) ?> — déposé le
                <?= e(format_date((int) $file['modified'], true)) ?>
            </p>

            <form method="post" action="/admin/importer" class="formulaire">
                <?= csrf_field() ?>
                <input type="hidden" name="fichier" value="<?= e($file['name']) ?>">

                <div class="ligne-champs">
                    <div class="champ">
                        <label for="title-<?= e($file['name']) ?>">Titre</label>
                        <input type="text" id="title-<?= e($file['name']) ?>" name="title"
                               maxlength="200" value="<?= e($defaultTitle) ?>">
                    </div>
                    <div class="champ">
                        <label for="doc_date-<?= e($file['name']) ?>">Date</label>
                        <input type="text" id="doc_date-<?= e($file['name']) ?>" name="doc_date"
                               maxlength="100" placeholder="ex. 1968">
                    </div>
                    <div class="champ">
                        <label for="category-<?= e($file['name']) ?>">Catégorie</label>
                        <select id="category-<?= e($file['name']) ?>" name="category_id">
                            <option value="0">Sans catégorie</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="champ">
                    <label for="description-<?= e($file['name']) ?>">Description</label>
                    <textarea id="description-<?= e($file['name']) ?>" name="description" rows="2"></textarea>
                </div>

                <div class="champ">
                    <label for="tags-<?= e($file['name']) ?>">Étiquettes</label>
                    <input type="text" id="tags-<?= e($file['name']) ?>" name="tags" maxlength="400"
                           placeholder="séparées par des virgules">
                </div>

                <fieldset class="champ">
                    <legend>Visibilité</legend>
                    <?php foreach (visibility_levels() as $value => $label): ?>
                        <label class="radio">
                            <input type="radio" name="visibility" value="<?= e($value) ?>"
                                   data-visibilite="<?= e($value) ?>"
                                   <?= checked($value === 'prive') ?>>
                            <?= e($label) ?>
                        </label>
                    <?php endforeach; ?>

                    <div class="branches" data-bloc-branches>
                        <?php foreach ($branches as $branch): ?>
                            <label class="case">
                                <input type="checkbox" name="branches[]" value="<?= (int) $branch['id'] ?>">
                                <?= e($branch['name']) ?> <span class="discret">(<?= e($branch['code']) ?>)</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <p class="actions">
                    <button type="submit" class="bouton">Importer ce fichier</button>
                </p>
            </form>

            <form method="post" action="/admin/importer/supprimer" class="formulaire-en-ligne"
                  data-confirmation="Supprimer ce fichier du dossier arrivée sans l’importer ?">
                <?= csrf_field() ?>
                <input type="hidden" name="fichier" value="<?= e($file['name']) ?>">
                <button type="submit" class="lien-bouton lien-bouton--danger">Supprimer du dossier</button>
            </form>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
