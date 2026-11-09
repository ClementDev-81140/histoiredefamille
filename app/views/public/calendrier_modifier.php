<?php
/**
 * Modifier un événement depuis le calendrier (journaliste autorisé, 1.37.0).
 *
 * @var array $event, $branches, $branchesChoisies, $erreurs, $old
 * @var bool  $direct
 */
$v = static fn (string $cle, mixed $defaut = '') => old_value($old, $cle, $event[$cle] ?? $defaut);
$visibilite = (string) $v('visibility', 'prive');
$choisies = array_map('intval', (array) old_value($old, 'branches', $branchesChoisies));
?>
<section class="section">
    <div class="section__entete">
        <h1>Modifier l’événement</h1>
        <a class="bouton bouton--secondaire" href="/calendrier">Retour au calendrier</a>
    </div>

    <?php if (!empty($direct)): ?>
        <p class="note">
            Vous publiez directement, sans validation : la correction s’applique
            tout de suite et elle est notée dans le journal tenu pour les
            administrateurs. L’ancienne version reste gardée.
        </p>
    <?php else: ?>
        <p class="note">
            Votre correction part en <strong>attente de validation</strong> :
            l’événement n’apparaît plus tant qu’un administrateur ne l’a pas validé.
        </p>
    <?php endif; ?>

    <?php if ($erreurs !== []): ?>
        <div class="message message--erreur">
            <ul><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" action="/calendrier/<?= (int) $event['id'] ?>/modifier" class="formulaire panneau">
        <?= csrf_field() ?>
        <input type="hidden" name="start_time" value="<?= e((string) ($event['start_time'] ?? '')) ?>">
        <input type="hidden" name="end_date" value="<?= e((string) ($event['end_date'] ?? '')) ?>">
        <input type="hidden" name="end_time" value="<?= e((string) ($event['end_time'] ?? '')) ?>">
        <input type="hidden" name="annual" value="<?= (int) ($event['annual'] ?? 0) ?>">

        <div class="champ">
            <label for="title">Titre <span class="obligatoire">*</span></label>
            <input type="text" id="title" name="title" maxlength="120" required
                   value="<?= e((string) $v('title')) ?>">
        </div>

        <div class="ligne-champs">
            <div class="champ">
                <label for="start_date">Date <span class="obligatoire">*</span></label>
                <input type="date" id="start_date" name="start_date" required
                       value="<?= e((string) $v('start_date')) ?>">
            </div>
            <div class="champ">
                <label for="type">Type</label>
                <select id="type" name="type">
                    <option value="">Non précisé</option>
                    <?php foreach (evenement_types() as $cle => $libelle): ?>
                        <option value="<?= e($cle) ?>"<?= selected((string) $v('type') === $cle) ?>><?= e($libelle) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="champ">
            <label for="place">Lieu</label>
            <input type="text" id="place" name="place" maxlength="120"
                   value="<?= e((string) $v('place')) ?>">
        </div>

        <div class="champ">
            <label for="description">Note</label>
            <textarea id="description" name="description" rows="4"><?= e((string) $v('description')) ?></textarea>
        </div>

        <fieldset class="champ">
            <legend>Qui pourra le voir ?</legend>
            <?php foreach (visibility_levels() as $valeur => $libelle): ?>
                <label class="radio">
                    <input type="radio" name="visibility" value="<?= e($valeur) ?>"
                           <?= checked($visibilite === $valeur) ?>>
                    <?= e($libelle) ?>
                </label>
            <?php endforeach; ?>
            <?php if ($branches !== []): ?>
                <p class="aide">Si vous choisissez « Branches précises », cochez les branches (celles de votre périmètre seulement) :</p>
                <?php foreach ($branches as $branche): ?>
                    <label class="case">
                        <input type="checkbox" name="branches[]" value="<?= (int) $branche['id'] ?>"
                            <?= checked(in_array((int) $branche['id'], $choisies, true)) ?>>
                        <?= e($branche['name']) ?>
                    </label>
                <?php endforeach; ?>
            <?php endif; ?>
        </fieldset>

        <p class="actions">
            <button type="submit" class="bouton">Enregistrer</button>
            <a class="bouton bouton--secondaire" href="/calendrier">Annuler</a>
        </p>
    </form>
</section>
