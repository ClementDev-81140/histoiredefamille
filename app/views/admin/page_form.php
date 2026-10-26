<?php
/**
 * Formulaire de page d'informations.
 *
 * @var array|null $page
 * @var array      $branches, $pageBranches, $erreurs, $old
 */
$isEdit = $page !== null;
$v = static function (string $key, mixed $default = '') use ($old, $page, $isEdit) {
    $base = $isEdit && isset($page[$key]) ? $page[$key] : $default;
    return old_value($old, $key, $base);
};
$selectedBranches = array_map('intval', (array) old_value($old, 'branches', $pageBranches));
$visibility  = (string) $v('visibility', 'prive');
$isPublished = (bool) old_value($old, 'is_published', $isEdit ? (int) $page['is_published'] === 1 : false);
$inNav       = (bool) old_value($old, 'show_in_nav', $isEdit ? (int) $page['show_in_nav'] === 1 : false);
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/admin/pages">Pages</a>
    <span aria-hidden="true">/</span>
    <span><?= $isEdit ? 'Modifier' : 'Nouvelle page' ?></span>
</nav>

<h1><?= e($titre) ?></h1>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= $isEdit ? '/admin/pages/' . (int) $page['id'] : '/admin/pages/nouvelle' ?>"
      class="formulaire panneau">
    <?= csrf_field() ?>

    <div class="ligne-champs">
        <div class="champ">
            <label for="title">Titre <span class="obligatoire">*</span></label>
            <input type="text" id="title" name="title" maxlength="200" required
                   value="<?= e((string) $v('title')) ?>">
        </div>

        <div class="champ">
            <label for="slug">Adresse de la page</label>
            <input type="text" id="slug" name="slug" maxlength="80" pattern="[a-z0-9][a-z0-9-]*"
                   value="<?= e((string) $v('slug')) ?>" placeholder="histoire-de-la-famille">
            <p class="aide">Laissez vide pour la générer depuis le titre. URL : <code>/pages/…</code></p>
        </div>
    </div>

    <div class="champ">
        <label for="body">Texte</label>
        <textarea id="body" name="body" rows="18"><?= e((string) $v('body')) ?></textarea>
        <p class="aide">
            Mise en forme légère : <code>## titre</code>, <code>**gras**</code>,
            <code>*italique*</code>, listes avec <code>-</code> ou <code>1.</code>,
            liens <code>[texte](https://…)</code>. Le HTML brut n’est pas interprété.
        </p>
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

    <fieldset class="champ">
        <legend>Publication</legend>
        <label class="case">
            <input type="checkbox" name="is_published" value="1"<?= checked($isPublished) ?>>
            Page publiée (visible selon la visibilité choisie)
        </label>
        <label class="case">
            <input type="checkbox" name="show_in_nav" value="1"<?= checked($inNav) ?>>
            Afficher le lien dans le menu principal
        </label>

        <div class="champ champ--petit">
            <label for="position">Ordre d’affichage</label>
            <input type="number" id="position" name="position" min="0" max="999"
                   value="<?= (int) $v('position', 0) ?>">
        </div>
    </fieldset>

    <p class="actions">
        <button type="submit" class="bouton"><?= $isEdit ? 'Enregistrer' : 'Créer la page' ?></button>
        <a class="bouton bouton--secondaire" href="/admin/pages">Annuler</a>
        <?php if ($isEdit && (int) $page['is_published'] === 1): ?>
            <a class="bouton bouton--secondaire" href="/pages/<?= e($page['slug']) ?>">Voir la page</a>
        <?php endif; ?>
    </p>
</form>
