<?php
/**
 * Formulaire d'une recette.
 *
 * @var array|null $recette
 * @var array      $photos, $branches, $recetteBranches, $erreurs, $old
 */
$isEdit = $recette !== null;
$v = static function (string $key, mixed $default = '') use ($old, $recette, $isEdit) {
    $base = $isEdit && isset($recette[$key]) ? $recette[$key] : $default;
    return old_value($old, $key, $base);
};
$selectedBranches = array_map('intval', (array) old_value($old, 'branches', $recetteBranches));
$visibility = (string) $v('visibility', 'prive');
$photoId    = (int) $v('photo_id', (int) ($recette['photo_document_id'] ?? 0));
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/admin/recettes">Recettes</a>
    <span aria-hidden="true">/</span>
    <span><?= $isEdit ? 'Modifier' : 'Nouvelle recette' ?></span>
</nav>

<h1><?= e($titre) ?></h1>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post"
      action="<?= $isEdit ? '/admin/recettes/' . (int) $recette['id'] : '/admin/recettes/nouvelle' ?>"
      class="formulaire panneau">
    <?= csrf_field() ?>

    <div class="ligne-champs">
        <div class="champ">
            <label for="title">Titre <span class="obligatoire">*</span></label>
            <input type="text" id="title" name="title" maxlength="200" required
                   value="<?= e((string) $v('title')) ?>" placeholder="Gâteau au chocolat de Mamie">
        </div>
        <div class="champ">
            <label for="origin">Origine</label>
            <input type="text" id="origin" name="origin" maxlength="120"
                   value="<?= e((string) $v('origin')) ?>" placeholder="Recette de Mamie, cuisine du Sud-Ouest…">
        </div>
    </div>

    <div class="ligne-champs">
        <div class="champ champ--petit">
            <label for="servings">Quantités</label>
            <input type="text" id="servings" name="servings" maxlength="60"
                   value="<?= e((string) $v('servings')) ?>" placeholder="6 personnes">
        </div>
        <div class="champ">
            <label for="photo_id">Photo</label>
            <select id="photo_id" name="photo_id">
                <option value="0">Aucune</option>
                <?php foreach ($photos as $photo): ?>
                    <option value="<?= (int) $photo['id'] ?>"<?= selected($photoId === (int) $photo['id']) ?>>
                        <?= e($photo['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="aide">Une photo déjà envoyée aux documents : le plat, le carnet, la recette manuscrite.</p>
        </div>
    </div>

    <div class="champ">
        <label for="ingredients">Ingrédients <span class="obligatoire">*</span></label>
        <textarea id="ingredients" name="ingredients" rows="8" required placeholder="250 g de farine&#10;3 œufs&#10;1 pincée de sel"><?= e((string) $v('ingredients')) ?></textarea>
        <p class="aide">Un ingrédient par ligne. Les tirets en début de ligne sont acceptés.</p>
    </div>

    <div class="champ">
        <label for="preparation">Préparation</label>
        <textarea id="preparation" name="preparation" rows="12"><?= e((string) $v('preparation')) ?></textarea>
        <p class="aide">
            Mise en forme légère : <code>## titre</code>, <code>**gras**</code>,
            <code>*italique*</code>, listes avec <code>-</code> ou <code>1.</code>.
            Un saut de ligne sépare deux paragraphes.
        </p>
    </div>

    <div class="champ">
        <label for="notes">Astuce et souvenir</label>
        <textarea id="notes" name="notes" rows="4"><?= e((string) $v('notes')) ?></textarea>
        <p class="aide">Le petit secret de famille, une variante, une anecdote.</p>
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
    </fieldset>

    <?php
    // Catégories du type « recette » (1.32.0).
    $categoriesCases = categories_list('recette');
    $kindCategorie   = 'Recettes';
    require __DIR__ . '/partials/categories_cases.php';
    ?>

    <p class="actions">
        <button type="submit" class="bouton"><?= $isEdit ? 'Enregistrer' : 'Créer la recette' ?></button>
        <a class="bouton bouton--secondaire" href="/admin/recettes">Annuler</a>
        <?php if ($isEdit): ?>
            <a class="bouton bouton--secondaire" href="/recettes/<?= e($recette['slug']) ?>">Voir la recette</a>
        <?php endif; ?>
    </p>
</form>
