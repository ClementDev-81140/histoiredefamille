<?php
/**
 * Formulaire d'ajout / modification d'une vidéo.
 *
 * @var array|null $video
 * @var array      $categories, $branches, $videoBranches, $erreurs, $old
 * @var string     $tags
 * @var int        $maxBytes
 */
$isEdit = $video !== null;
$v = static function (string $key, mixed $default = '') use ($old, $video, $isEdit) {
    $base = $isEdit && isset($video[$key]) ? $video[$key] : $default;
    return old_value($old, $key, $base);
};
$selectedBranches = array_map('intval', (array) old_value($old, 'branches', $videoBranches));
$visibility = (string) $v('visibility', 'prive');
$enAvant    = (string) $v('is_featured', $isEdit ? (int) $video['is_featured'] : 0) === '1';
$telecharger = (string) $v('allow_download', $isEdit ? (int) $video['allow_download'] : 1) === '1';
$dureeActuelle = $isEdit && !empty($video['duration_seconds'])
    ? video_duration_short((int) $video['duration_seconds'])
    : '';
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/admin/videos">Vidéos</a>
    <span aria-hidden="true">/</span>
    <span><?= $isEdit ? 'Modifier' : 'Nouvelle' ?></span>
</nav>

<h1><?= e($titre) ?></h1>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul>
            <?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!$isEdit): ?>
    <p class="note">
        <?= e(format_size($maxBytes)) ?> au plus ; au-delà,
        <a href="/admin/importer">dossier arrivée</a>.
    </p>
<?php endif; ?>

<form method="post" enctype="multipart/form-data"
      action="<?= $isEdit ? '/admin/videos/' . (int) $video['id'] : '/admin/videos/nouvelle' ?>"
      class="formulaire panneau">
    <?= csrf_field() ?>

    <div class="champ">
        <label for="title">Titre <span class="obligatoire">*</span></label>
        <input type="text" id="title" name="title" maxlength="200" required value="<?= e((string) $v('title')) ?>">
    </div>

    <div class="champ">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="5"><?= e((string) $v('description')) ?></textarea>
        <p class="aide">
            Texte libre. Mise en forme légère : <code>**gras**</code>, <code>*italique*</code>,
            listes avec <code>-</code>, liens <code>[texte](https://…)</code>.
        </p>
    </div>

    <div class="ligne-champs">
        <div class="champ">
            <label for="doc_date">Date du souvenir</label>
            <input type="text" id="doc_date" name="doc_date" maxlength="100"
                   placeholder="ex. 1962, Noël 1978, 12/06/1954" value="<?= e((string) $v('doc_date')) ?>">
        </div>

        <div class="champ">
            <label for="duration">Durée</label>
            <input type="text" id="duration" name="duration" maxlength="20"
                   placeholder="ex. 12:30 ou 1:02:03"
                   value="<?= e((string) old_value($old, 'duration', $dureeActuelle)) ?>">
            <p class="aide">Facultative. Le fichier n’est pas analysé (aucun transcodage sur le Pi).</p>
        </div>
    </div>

    <div class="ligne-champs">
        <div class="champ">
            <label for="category_id">Catégorie</label>
            <select id="category_id" name="category_id">
                <option value="0">Sans catégorie</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>"
                        <?= selected((int) $v('category_id', 0) === (int) $category['id']) ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="champ">
            <label for="display_order">Ordre d’affichage</label>
            <input type="number" id="display_order" name="display_order" min="0" max="9999"
                   value="<?= e((string) $v('display_order', 0)) ?>">
            <p class="aide">
                0 = pas de classement (les plus récentes d’abord). Un petit nombre passe devant.
                Les vidéos mises en avant restent toujours en tête.
            </p>
        </div>
    </div>

    <div class="champ">
        <label for="tags">Étiquettes</label>
        <input type="text" id="tags" name="tags" maxlength="400"
               placeholder="mariage, village, 1914" value="<?= e((string) old_value($old, 'tags', $tags)) ?>">
        <p class="aide">Séparez les étiquettes par des virgules.</p>
    </div>

    <fieldset class="champ">
        <legend>Visibilité <span class="obligatoire">*</span></legend>
        <p class="aide">
            Par défaut, un contenu est <strong>privé</strong> (administrateurs uniquement).
            « Privé » n’apparaît jamais dans l’espace famille.
        </p>

        <?php foreach (visibility_levels() as $value => $label): ?>
            <label class="radio">
                <input type="radio" name="visibility" value="<?= e($value) ?>"
                       data-visibilite="<?= e($value) ?>"<?= checked($visibility === $value) ?>>
                <?= e($label) ?>
            </label>
        <?php endforeach; ?>

        <div class="branches" data-bloc-branches>
            <?php if ($branches === []): ?>
                <p class="note">Aucune branche n’existe encore. Créez-en une dans « Branches &amp; comptes ».</p>
            <?php else: ?>
                <?php foreach ($branches as $branch): ?>
                    <label class="case">
                        <input type="checkbox" name="branches[]" value="<?= (int) $branch['id'] ?>"
                            <?= checked(in_array((int) $branch['id'], $selectedBranches, true)) ?>>
                        <?= e($branch['name']) ?>
                        <span class="discret">(<?= e($branch['code']) ?>)</span>
                        <?php if ((int) $branch['see_all'] === 1): ?>
                            <span class="badge badge-famille">voit tout</span>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </fieldset>

    <fieldset class="champ">
        <legend>Affichage</legend>

        <label class="case">
            <input type="checkbox" name="is_featured" value="1" <?= checked($enAvant) ?>>
            Mettre en avant (en tête de l’onglet Vidéos)
        </label>

        <label class="case">
            <input type="checkbox" name="allow_download" value="1" <?= checked($telecharger) ?>>
            Autoriser le bouton « Télécharger »
        </label>
    </fieldset>


    <?php
    // Même composant que la page « Envoyer des fichiers » : un gros film passe
    // par morceaux. Le fichier rejoint ce formulaire une fois assemblé.
    $envoiOptions = [
        'creer' => false,
        'titre' => 'Fichier volumineux (envoi par morceaux)',
        'aide'  => 'Facultatif : pour remplacer le champ « Fichier » quand le film dépasse la limite de l’envoi simple.',
    ];
    require __DIR__ . '/partials/envoi.php';
    ?>
    <div class="champ">
        <label for="fichier"><?= $isEdit ? 'Remplacer le fichier' : 'Fichier vidéo' ?><?= $isEdit ? '' : ' *' ?></label>
        <input type="file" id="fichier" name="fichier" <?= $isEdit ? '' : 'required' ?>
               accept="<?= e('.' . implode(',.', allowed_extensions())) ?>,video/*">
        <?php if ($isEdit): ?>
            <p class="aide">
                Fichier actuel : <?= e((string) $video['original_name']) ?>
                (<?= e(format_size((int) $video['size'])) ?>, <?= e((string) $video['mime']) ?>).
                Laissez vide pour le conserver.
            </p>
        <?php else: ?>
            <p class="aide">
                MP4 (H.264 + AAC) est le format lu partout : s’il est déjà dans ce
                format, il est publié tel quel. Un autre format (MOV, AVI, MKV,
                WebM…) est converti automatiquement en arrière-plan, et la vidéo
                n’est visible qu’une fois la conversion terminée.
                Formats acceptés : <?= e(implode(', ', allowed_extensions())) ?>.
            </p>
        <?php endif; ?>
    </div>

    <p class="actions">
        <button type="submit" class="bouton"><?= $isEdit ? 'Enregistrer' : 'Envoyer la vidéo' ?></button>
        <a class="bouton bouton--secondaire" href="/admin/videos">Annuler</a>
        <?php if ($isEdit): ?>
            <a class="bouton bouton--secondaire" href="/videos/<?= (int) $video['id'] ?>">Voir la fiche</a>
        <?php endif; ?>
    </p>
</form>

<?php if ($isEdit): ?>
    <form method="post" action="/admin/videos/<?= (int) $video['id'] ?>/supprimer"
          class="formulaire panneau panneau--danger"
          data-confirmation="Supprimer définitivement cette vidéo et son fichier ?">
        <?= csrf_field() ?>
        <p class="aide">
            La suppression efface la fiche, ses étiquettes et le fichier vidéo du disque.
            Cette action est définitive.
        </p>
        <p class="actions">
            <button type="submit" class="bouton bouton--danger">Supprimer cette vidéo</button>
        </p>
    </form>
<?php endif; ?>
