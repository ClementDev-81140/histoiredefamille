<?php
/**
 * Formulaire d'album photo.
 *
 * @var array|null $album
 * @var array      $photos, $choisies, $branches, $albumBranches, $erreurs, $old
 */
$isEdit = $album !== null;
$v = static function (string $key, mixed $default = '') use ($old, $album, $isEdit) {
    $base = $isEdit && isset($album[$key]) ? $album[$key] : $default;
    return old_value($old, $key, $base);
};
$selectedBranches = array_map('intval', (array) old_value($old, 'branches', $albumBranches));
$visibility = (string) $v('visibility', 'prive');
$coches     = array_map('intval', (array) old_value($old, 'photos', $choisies));
$couverture = (int) $v('couverture', (int) ($album['cover_document_id'] ?? 0));
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/admin/albums">Albums</a>
    <span aria-hidden="true">/</span>
    <span><?= $isEdit ? 'Modifier' : 'Nouvel album' ?></span>
</nav>

<h1><?= e($titre) ?></h1>

<?php if ($isEdit): ?>
    <p class="actions">
        <a class="bouton" href="/admin/albums/<?= (int) $album['id'] ?>/photos">Ajouter des photos</a>
        <a class="bouton bouton--secondaire" href="/albums/<?= e($album['slug']) ?>">Voir l’album</a>
    </p>
<?php endif; ?>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post"
      action="<?= $isEdit ? '/admin/albums/' . (int) $album['id'] . '/modifier' : '/admin/albums/nouveau' ?>"
      class="formulaire panneau">
    <?= csrf_field() ?>

    <div class="ligne-champs">
        <div class="champ">
            <label for="title">Titre <span class="obligatoire">*</span></label>
            <input type="text" id="title" name="title" maxlength="200" required
                   value="<?= e((string) $v('title')) ?>">
        </div>
        <div class="champ champ--petit">
            <label for="position">Ordre d’affichage</label>
            <input type="number" id="position" name="position" value="<?= (int) $v('position', 0) ?>">
            <p class="aide">Les petits nombres passent devant.</p>
        </div>
    </div>

    <div class="champ">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4" maxlength="5000"><?= e((string) $v('description')) ?></textarea>
        <p class="aide">Quelques mots sur cet album : une époque, un voyage, une fête de famille.</p>
    </div>

    <fieldset class="champ">
        <legend>Visibilité</legend>
        <?php foreach (visibility_levels() as $value => $label): ?>
            <label class="radio">
                <input type="radio" name="visibility" value="<?= e($value) ?>"
                       data-visibilite="<?= e($value) ?>"<?= checked($visibility === $value) ?>>
                <?= e($label) ?>
            </label>
        <?php endforeach; ?>

        <div class="branches" data-bloc-branches>
            <?php foreach ($branches as $branch): ?>
                <label class="case">
                    <input type="checkbox" name="branches[]" value="<?= (int) $branch['id'] ?>"
                        <?= checked(in_array((int) $branch['id'], $selectedBranches, true)) ?>>
                    <?= e($branch['name']) ?> <span class="discret">(<?= e($branch['code']) ?>)</span>
                </label>
            <?php endforeach; ?>
        </div>
        <p class="aide">
            Les photos gardent leur propre visibilité : une photo privée reste
            invisible, même dans un album public.
        </p>
    </fieldset>

    <fieldset class="champ">
        <legend>Photos de l’album</legend>

        <div class="champ champ--petit">
            <label for="couverture">Photo de couverture</label>
            <select id="couverture" name="couverture">
                <option value="0">Automatique (la première photo de l’album)</option>
                <?php foreach ($photos as $photo): ?>
                    <option value="<?= (int) $photo['id'] ?>"<?= selected($couverture === (int) $photo['id']) ?>>
                        <?= e($photo['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($photos === []): ?>
            <p class="discret">
                Aucune photo pour le moment.
                <a href="/admin/documents/nouveau">Envoyer une première photo</a>.
            </p>
        <?php else: ?>
            <p class="aide">
                Cochez les photos à ranger dans cet album (les 200 plus récentes
                sont proposées). L’ordre suit l’ordre d’affichage de cette liste.
                <?php if ($isEdit): ?>
                    Pour chercher parmi <strong>tous</strong> les documents du site (photos et
                    autres), avec des filtres et une pagination :
                    <a href="/admin/albums/<?= (int) $album['id'] ?>/photos#ajouter-documents">choisir
                    dans les documents du site</a>.
                <?php endif; ?>
            </p>
            <ul class="choix-photos">
                <?php foreach ($photos as $photo): ?>
                    <li class="choix-photo">
                        <label class="choix-photo__case">
                            <input type="checkbox" name="photos[]" value="<?= (int) $photo['id'] ?>"
                                <?= checked(in_array((int) $photo['id'], $coches, true)) ?>>
                            <?php if (thumbnail_url($photo) !== null): ?>
                                <img src="<?= e(thumbnail_url($photo)) ?>" alt="" loading="lazy"
                                     width="120" height="90">
                            <?php else: ?>
                                <span class="choix-photo__vide">Photo</span>
                            <?php endif; ?>
                            <span class="choix-photo__titre"><?= e($photo['title']) ?></span>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </fieldset>

    <?php
    // Catégories du type « album » (1.32.0).
    $categoriesCases = categories_list('album');
    $kindCategorie   = 'Albums';
    require __DIR__ . '/partials/categories_cases.php';
    ?>

    <p class="actions">
        <button type="submit" class="bouton"><?= $isEdit ? 'Enregistrer' : 'Créer l’album' ?></button>
        <a class="bouton bouton--secondaire" href="/admin/albums">Annuler</a>
        <?php if ($isEdit): ?>
            <a class="bouton bouton--secondaire" href="/albums/<?= e($album['slug']) ?>">Voir l’album</a>
        <?php endif; ?>
    </p>
</form>
