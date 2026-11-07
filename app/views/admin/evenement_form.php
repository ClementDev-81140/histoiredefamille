<?php
/**
 * Formulaire d'un événement du calendrier (création ou modification).
 *
 * @var array|null $evenement
 * @var array      $branches, $evBranches, $erreurs, $old
 */
$isEdit = $evenement !== null;
$v = static function (string $cle, mixed $defaut = '') use ($old, $evenement, $isEdit) {
    $base = $isEdit && isset($evenement[$cle]) ? $evenement[$cle] : $defaut;
    return old_value($old, $cle, $base);
};
$visibilite = (string) $v('visibility', 'prive');
$branchesChoisies = array_map('intval', (array) old_value($old, 'branches', $evBranches));
$annuel = (string) old_value($old, 'annual', (string) ($evenement['annual'] ?? '0')) === '1';
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/admin">Administration</a>
    <span aria-hidden="true">/</span>
    <a href="/admin/calendrier">Calendrier</a>
    <span aria-hidden="true">/</span>
    <span><?= $isEdit ? 'Modifier' : 'Nouvel événement' ?></span>
</nav>

<h1><?= e($titre) ?></h1>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post"
      action="<?= $isEdit ? '/admin/calendrier/' . (int) $evenement['id'] . '/modifier' : '/admin/calendrier/nouveau' ?>"
      class="formulaire panneau">
    <?= csrf_field() ?>

    <div class="champ">
        <label for="title">Titre <span class="obligatoire">*</span></label>
        <input type="text" id="title" name="title" maxlength="120" required
               value="<?= e((string) $v('title')) ?>">
    </div>

    <div class="ligne-champs">
        <div class="champ">
            <label for="start_date">Date de début <span class="obligatoire">*</span></label>
            <input type="date" id="start_date" name="start_date" required
                   value="<?= e((string) $v('start_date')) ?>">
        </div>
        <div class="champ">
            <label for="start_time">Heure (facultatif)</label>
            <input type="time" id="start_time" name="start_time" value="<?= e((string) $v('start_time')) ?>">
        </div>
    </div>

    <div class="ligne-champs">
        <div class="champ">
            <label for="end_date">Date de fin (facultatif)</label>
            <input type="date" id="end_date" name="end_date" value="<?= e((string) $v('end_date')) ?>">
        </div>
        <div class="champ">
            <label for="end_time">Heure de fin (facultatif)</label>
            <input type="time" id="end_time" name="end_time" value="<?= e((string) $v('end_time')) ?>">
        </div>
    </div>

    <div class="champ">
        <label for="place">Lieu (texte libre)</label>
        <input type="text" id="place" name="place" maxlength="120" value="<?= e((string) $v('place')) ?>">
    </div>

    <div class="champ">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="5"><?= e((string) $v('description')) ?></textarea>
    </div>

    <label class="case">
        <input type="checkbox" name="annual" value="1" <?= checked($annuel) ?>>
        Cet événement revient chaque année à la même date
    </label>

    <fieldset class="champ">
        <legend>Visibilité <span class="obligatoire">*</span></legend>
        <p class="aide">
            Par défaut, un événement est <strong>privé</strong> (administrateurs uniquement).
        </p>
        <?php foreach (visibility_levels() as $valeur => $libelle): ?>
            <label class="radio">
                <input type="radio" name="visibility" value="<?= e($valeur) ?>"
                       data-visibilite="<?= e($valeur) ?>" <?= checked($visibilite === $valeur) ?>>
                <?= e($libelle) ?>
            </label>
        <?php endforeach; ?>

        <div class="branches" data-bloc-branches>
            <?php if ($branches === []): ?>
                <p class="note">Aucune branche n’existe encore.</p>
            <?php else: ?>
                <?php foreach ($branches as $branche): ?>
                    <label class="case">
                        <input type="checkbox" name="branches[]" value="<?= (int) $branche['id'] ?>"
                            <?= checked(in_array((int) $branche['id'], $branchesChoisies, true)) ?>>
                        <?= e($branche['name']) ?>
                        <span class="discret">(<?= e($branche['code']) ?>)</span>
                    </label>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </fieldset>

    <p class="actions">
        <button type="submit" class="bouton"><?= $isEdit ? 'Enregistrer' : 'Créer l’événement' ?></button>
        <a class="bouton bouton--secondaire" href="/admin/calendrier">Annuler</a>
    </p>
</form>
