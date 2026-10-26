<?php
/**
 * Formulaire de couple.
 *
 * @var array|null $family
 * @var array      $persons, $childIds, $erreurs, $old
 */
$isEdit = $family !== null;
$v = static function (string $key, mixed $default = '') use ($old, $family, $isEdit) {
    $base = $isEdit && isset($family[$key]) ? $family[$key] : $default;
    return old_value($old, $key, $base);
};
$selectedChildren = array_map('intval', (array) old_value($old, 'children', $childIds));
$husbandId = (int) $v('husband_id', 0);
$wifeId    = (int) $v('wife_id', 0);

$option = static function (array $person, int $selectedId): string {
    $label = person_full_name($person);
    $year  = person_birth_year($person);
    if ($year !== null) {
        $label .= ' (' . $year . ')';
    }
    return '<option value="' . (int) $person['id'] . '"' . ($selectedId === (int) $person['id'] ? ' selected' : '') . '>'
        . e($label) . '</option>';
};
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/admin/arbre/couples">Couples</a>
    <span aria-hidden="true">/</span>
    <span><?= $isEdit ? 'Modifier' : 'Nouveau couple' ?></span>
</nav>

<h1><?= e($titre) ?></h1>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if ($persons === []): ?>
    <div class="panneau panneau--centre">
        <p>Il n’y a encore aucune personne dans l’arbre.</p>
        <p><a class="bouton" href="/admin/arbre/personne/nouvelle">Ajouter une personne</a></p>
    </div>
<?php else: ?>
    <form method="post" action="<?= $isEdit ? '/admin/arbre/couple/' . (int) $family['id'] : '/admin/arbre/couple/nouveau' ?>"
          class="formulaire panneau">
        <?= csrf_field() ?>

        <div class="ligne-champs">
            <div class="champ">
                <label for="husband_id">Conjoint 1</label>
                <select id="husband_id" name="husband_id" data-filtre-cible="husband_id">
                    <option value="0">— aucun —</option>
                    <?php foreach ($persons as $person): ?>
                        <?= $option($person, $husbandId) ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="champ">
                <label for="wife_id">Conjoint 2</label>
                <select id="wife_id" name="wife_id" data-filtre-cible="wife_id">
                    <option value="0">— aucun —</option>
                    <?php foreach ($persons as $person): ?>
                        <?= $option($person, $wifeId) ?>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <p class="aide">
            L’ordre des conjoints n’a pas d’importance pour l’affichage.
            Astuce : utilisez la recherche du navigateur (Ctrl+F) pour trouver un nom
            dans la liste.
        </p>

        <div class="ligne-champs">
            <div class="champ">
                <label for="marriage_date">Date du mariage</label>
                <input type="text" id="marriage_date" name="marriage_date" maxlength="100"
                       value="<?= e((string) $v('marriage_date')) ?>">
            </div>
            <div class="champ">
                <label for="marriage_place">Lieu du mariage</label>
                <input type="text" id="marriage_place" name="marriage_place" maxlength="200"
                       value="<?= e((string) $v('marriage_place')) ?>">
            </div>
        </div>

        <div class="champ">
            <label for="children">Enfants du couple</label>
            <input type="search" class="filtre-liste" data-filtre-liste="children"
                   placeholder="Filtrer la liste des personnes…" aria-label="Filtrer la liste des enfants">
            <select id="children" name="children[]" multiple size="12">
                <?php foreach ($persons as $person): ?>
                    <option value="<?= (int) $person['id'] ?>"
                        <?= in_array((int) $person['id'], $selectedChildren, true) ? ' selected' : '' ?>>
                        <?= e(person_full_name($person)) ?><?php $y = person_birth_year($person); ?><?= $y !== null ? ' (' . $y . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="aide">
                Maintenez Ctrl (ou Cmd) pour sélectionner plusieurs enfants.
                Sur mobile, appuyez longuement pour sélectionner.
            </p>
        </div>

        <div class="champ">
            <label for="notes">Notes sur le couple</label>
            <textarea id="notes" name="notes" rows="3"><?= e((string) $v('notes')) ?></textarea>
        </div>

        <p class="actions">
            <button type="submit" class="bouton"><?= $isEdit ? 'Enregistrer' : 'Créer le couple' ?></button>
            <a class="bouton bouton--secondaire" href="/admin/arbre/couples">Annuler</a>
        </p>
    </form>
<?php endif; ?>
