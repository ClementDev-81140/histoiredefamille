<?php
/**
 * Créer ou corriger un album (journaliste autorisé, 1.37.0).
 *
 * @var array|null $album
 * @var array      $photos, $photosChoisies, $branches, $branchesChoisies, $erreurs, $old
 * @var callable   $peutBranches
 */
$v = static function (string $cle, mixed $defaut = '') use ($old, $album): mixed {
    $base = $album !== null && isset($album[$cle]) ? $album[$cle] : $defaut;
    return old_value($old, $cle, $base);
};
$action = $album !== null ? '/albums/' . (int) $album['id'] . '/modifier' : '/albums/nouveau';
$visibilite = (string) $v('visibility', 'famille');
$choisies = array_map('intval', (array) old_value($old, 'branches', $branchesChoisies));
$photosCochees = array_map('intval', (array) old_value($old, 'photos', $photosChoisies));
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/albums">Albums</a>
    <span aria-hidden="true">/</span>
    <span><?= $album !== null ? 'Corriger l’album' : 'Nouvel album' ?></span>
</nav>

<h1><?= e($titre) ?></h1>
<p class="aide">
    L’album est publié directement, avec la visibilité choisie. L’ancienne version
    est gardée dans l’historique tenu pour les administrateurs. Vous ne pouvez rien supprimer d’ici.
</p>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" class="formulaire panneau">
    <?= csrf_field() ?>

    <div class="champ">
        <label for="title">Titre de l’album</label>
        <input type="text" id="title" name="title" maxlength="200" value="<?= e((string) $v('title')) ?>" required>
    </div>

    <div class="champ">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="5"><?= e((string) $v('description')) ?></textarea>
    </div>

    <fieldset class="champ">
        <legend>Photos de l’album</legend>
        <p class="aide">Seules les photos que votre compte peut déjà voir sont proposées (les 200 plus récentes).</p>
        <?php if ($photos === []): ?>
            <p class="aide">Aucune photo visible pour votre compte pour le moment.</p>
        <?php else: ?>
            <?php foreach ($photos as $photo): ?>
                <label class="case">
                    <input type="checkbox" name="photos[]" value="<?= (int) $photo['id'] ?>"
                        <?= checked(in_array((int) $photo['id'], $photosCochees, true)) ?>>
                    <?= e((string) ($photo['title'] ?? 'Photo')) ?>
                </label>
            <?php endforeach; ?>
        <?php endif; ?>
    </fieldset>

    <fieldset class="champ">
        <legend>Qui pourra voir cet album ?</legend>
        <?php foreach (visibility_levels() as $valeur => $libelle): ?>
            <label class="radio">
                <input type="radio" name="visibility" value="<?= e($valeur) ?>"
                       <?= checked($visibilite === $valeur) ?>>
                <?= e($libelle) ?>
            </label>
        <?php endforeach; ?>
        <p class="aide">Si vous choisissez « Branches précises », cochez les branches (celles de votre périmètre seulement) :</p>
        <?php foreach ($branches as $branche): ?>
            <?php if ($peutBranches((int) $branche['id'])): ?>
                <label class="case">
                    <input type="checkbox" name="branches[]" value="<?= (int) $branche['id'] ?>"
                        <?= checked(in_array((int) $branche['id'], $choisies, true)) ?>>
                    <?= e($branche['name']) ?>
                </label>
            <?php endif; ?>
        <?php endforeach; ?>
    </fieldset>

    <p class="actions">
        <button type="submit" class="bouton">Enregistrer</button>
        <a class="bouton bouton--secondaire" href="/albums">Annuler</a>
    </p>
</form>
