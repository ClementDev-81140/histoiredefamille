<?php
/**
 * Ajouter ou corriger une fiche personne (journaliste autorisé, 1.37.0).
 *
 * @var array|null $person
 * @var array      $personnes, $branches, $branchesChoisies, $erreurs, $old
 * @var callable   $peutBranches
 */
$v = static function (string $cle, mixed $defaut = '') use ($old, $person): mixed {
    $base = $person !== null && isset($person[$cle]) ? $person[$cle] : $defaut;
    return old_value($old, $cle, $base);
};
$action = $person !== null ? '/arbre/' . (int) $person['id'] . '/modifier' : '/arbre/nouvelle-personne';
$visibilite = (string) $v('visibility', 'famille');
$choisies = array_map('intval', (array) old_value($old, 'branches', $branchesChoisies));
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/arbre">Arbre généalogique</a>
    <span aria-hidden="true">/</span>
    <span><?= $person !== null ? 'Corriger la fiche' : 'Nouvelle fiche' ?></span>
</nav>

<h1><?= e($titre) ?></h1>
<p class="aide">
    La fiche est enregistrée tout de suite. L’ancienne version est gardée dans
    l’historique tenu pour les administrateurs. Vous ne pouvez rien supprimer d’ici.
</p>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" class="formulaire panneau">
    <?= csrf_field() ?>

    <div class="ligne-champs">
        <div class="champ">
            <label for="first_name">Prénom</label>
            <input type="text" id="first_name" name="first_name" maxlength="100" value="<?= e((string) $v('first_name')) ?>">
        </div>
        <div class="champ">
            <label for="last_name">Nom</label>
            <input type="text" id="last_name" name="last_name" maxlength="100" value="<?= e((string) $v('last_name')) ?>">
        </div>
        <div class="champ">
            <label for="sex">Sexe</label>
            <select id="sex" name="sex">
                <option value=""<?= selected((string) $v('sex') === '') ?>>Non précisé</option>
                <option value="M"<?= selected((string) $v('sex') === 'M') ?>>Homme</option>
                <option value="F"<?= selected((string) $v('sex') === 'F') ?>>Femme</option>
            </select>
        </div>
    </div>

    <div class="ligne-champs">
        <div class="champ">
            <label for="birth_date">Naissance (date ou texte)</label>
            <input type="text" id="birth_date" name="birth_date" maxlength="100" value="<?= e((string) $v('birth_date')) ?>">
        </div>
        <div class="champ">
            <label for="birth_place">Lieu de naissance</label>
            <input type="text" id="birth_place" name="birth_place" maxlength="200" value="<?= e((string) $v('birth_place')) ?>">
        </div>
    </div>

    <div class="ligne-champs">
        <div class="champ">
            <label for="death_date">Décès (date ou texte)</label>
            <input type="text" id="death_date" name="death_date" maxlength="100" value="<?= e((string) $v('death_date')) ?>">
        </div>
        <div class="champ">
            <label for="death_place">Lieu de décès</label>
            <input type="text" id="death_place" name="death_place" maxlength="200" value="<?= e((string) $v('death_place')) ?>">
        </div>
    </div>

    <div class="champ">
        <label for="notes">Notes</label>
        <textarea id="notes" name="notes" rows="6"><?= e((string) $v('notes')) ?></textarea>
    </div>

    <fieldset class="champ">
        <legend>Qui pourra voir cette fiche ?</legend>
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

    <fieldset class="champ">
        <legend>Liens de parenté (facultatif, un ajout à la fois)</legend>
        <p class="aide">Seules les personnes que votre compte peut déjà voir sont proposées.</p>
        <div class="champ">
            <label for="conjoint_id">Ajouter un conjoint</label>
            <select id="conjoint_id" name="conjoint_id">
                <option value="0">— aucun —</option>
                <?php foreach ($personnes as $autre): ?>
                    <?php if ($person === null || (int) $autre['id'] !== (int) $person['id']): ?>
                        <option value="<?= (int) $autre['id'] ?>"><?= e(person_full_name($autre)) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="champ">
            <label for="parent_id">Ajouter un père ou une mère (cette fiche devient son enfant)</label>
            <select id="parent_id" name="parent_id">
                <option value="0">— aucun —</option>
                <?php foreach ($personnes as $autre): ?>
                    <?php if ($person === null || (int) $autre['id'] !== (int) $person['id']): ?>
                        <option value="<?= (int) $autre['id'] ?>"><?= e(person_full_name($autre)) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="champ">
            <label for="enfant_id">Ajouter un enfant à cette fiche</label>
            <select id="enfant_id" name="enfant_id">
                <option value="0">— aucun —</option>
                <?php foreach ($personnes as $autre): ?>
                    <?php if ($person === null || (int) $autre['id'] !== (int) $person['id']): ?>
                        <option value="<?= (int) $autre['id'] ?>"><?= e(person_full_name($autre)) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
    </fieldset>

    <p class="actions">
        <button type="submit" class="bouton">Enregistrer</button>
        <a class="bouton bouton--secondaire" href="/arbre">Annuler</a>
    </p>
</form>
