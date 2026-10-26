<?php
/**
 * Formulaire de personne (arbre).
 *
 * @var array|null $person, $photo
 * @var array      $branches, $personBranches, $erreurs, $old, $couples
 */
$isEdit = $person !== null;
$v = static function (string $key, mixed $default = '') use ($old, $person, $isEdit) {
    $base = $isEdit && isset($person[$key]) ? $person[$key] : $default;
    return old_value($old, $key, $base);
};
$selectedBranches = array_map('intval', (array) old_value($old, 'branches', $personBranches));
$visibility = (string) $v('visibility', 'famille');
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/admin/arbre">Arbre</a>
    <span aria-hidden="true">/</span>
    <span><?= $isEdit ? 'Modifier' : 'Nouvelle personne' ?></span>
</nav>

<h1><?= e($titre) ?></h1>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data"
      action="<?= $isEdit ? '/admin/arbre/personne/' . (int) $person['id'] : '/admin/arbre/personne/nouvelle' ?>"
      class="formulaire panneau">
    <?= csrf_field() ?>

    <div class="ligne-champs">
        <div class="champ">
            <label for="first_name">Prénom(s)</label>
            <input type="text" id="first_name" name="first_name" maxlength="100"
                   value="<?= e((string) $v('first_name')) ?>">
        </div>
        <div class="champ">
            <label for="last_name">Nom</label>
            <input type="text" id="last_name" name="last_name" maxlength="100"
                   value="<?= e((string) $v('last_name')) ?>">
        </div>
        <div class="champ champ--petit">
            <label for="sex">Sexe</label>
            <select id="sex" name="sex">
                <option value="">—</option>
                <option value="M"<?= selected((string) $v('sex') === 'M') ?>>Masculin</option>
                <option value="F"<?= selected((string) $v('sex') === 'F') ?>>Féminin</option>
            </select>
        </div>
    </div>

    <div class="ligne-champs">
        <div class="champ">
            <label for="birth_date">Date de naissance</label>
            <input type="text" id="birth_date" name="birth_date" maxlength="100"
                   placeholder="ex. 12/03/1901, 1901, vers 1901"
                   value="<?= e((string) $v('birth_date')) ?>">
        </div>
        <div class="champ">
            <label for="birth_place">Lieu de naissance</label>
            <input type="text" id="birth_place" name="birth_place" maxlength="200"
                   value="<?= e((string) $v('birth_place')) ?>">
        </div>
    </div>

    <div class="ligne-champs">
        <div class="champ">
            <label for="death_date">Date de décès</label>
            <input type="text" id="death_date" name="death_date" maxlength="100"
                   value="<?= e((string) $v('death_date')) ?>">
        </div>
        <div class="champ">
            <label for="death_place">Lieu de décès</label>
            <input type="text" id="death_place" name="death_place" maxlength="200"
                   value="<?= e((string) $v('death_place')) ?>">
        </div>
    </div>

    <div class="champ">
        <label for="notes">Notes</label>
        <textarea id="notes" name="notes" rows="6"><?= e((string) $v('notes')) ?></textarea>
        <p class="aide">Souvenirs, métier, anecdotes… (mise en forme légère acceptée).</p>
    </div>

    <div class="champ">
        <label for="photo">Photo (facultatif)</label>
        <input type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png,.webp,.gif">
        <?php if ($photo !== null): ?>
            <p class="aide">
                Photo actuelle : <?= e((string) $photo['original_name']) ?>.
                Envoyer un fichier la remplacera.
            </p>
            <label class="case">
                <input type="checkbox" name="remove_photo" value="1">
                Supprimer la photo actuelle
            </label>
        <?php endif; ?>
    </div>

    <fieldset class="champ">
        <legend>Visibilité de la fiche</legend>
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
            Par défaut « Toutes les familles » : les personnes importées ne sont jamais
            publiques automatiquement.
        </p>
    </fieldset>

    <p class="actions">
        <button type="submit" class="bouton"><?= $isEdit ? 'Enregistrer' : 'Créer la personne' ?></button>
        <a class="bouton bouton--secondaire" href="/admin/arbre">Annuler</a>
        <?php if ($isEdit): ?>
            <a class="bouton bouton--secondaire" href="/arbre/<?= (int) $person['id'] ?>">Voir la fiche</a>
        <?php endif; ?>
    </p>
</form>

<?php if ($isEdit): ?>
    <section class="panneau">
        <div class="section__entete">
            <h2>Liens de parenté</h2>
            <a class="bouton bouton--secondaire" href="/admin/arbre/couple/nouveau">Ajouter un couple</a>
        </div>
        <p class="note">
            Les parents, conjoints et enfants se gèrent dans « Couples » : un couple
            rassemble deux conjoints et leurs enfants.
        </p>

        <?php if ($couples === []): ?>
            <p class="discret">Cette personne n’est encore liée à aucun couple.</p>
        <?php else: ?>
            <ul class="liste-personnes">
                <?php foreach ($couples as $couple): ?>
                    <li>
                        <a href="/admin/arbre/couple/<?= (int) $couple['id'] ?>">Couple #<?= (int) $couple['id'] ?></a>
                        <?php
                        $members = [];
                        foreach ([$couple['husband_id'], $couple['wife_id']] as $memberId) {
                            if ($memberId) {
                                $member = db_one('SELECT * FROM persons WHERE id = ?', [(int) $memberId]);
                                if ($member) {
                                    $members[] = person_full_name($member);
                                }
                            }
                        }
                        ?>
                        — <?= e($members !== [] ? implode(' & ', $members) : 'sans conjoint') ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
<?php endif; ?>
