<?php
/**
 * Ajouter (administrateur) ou proposer (journaliste) un événement (1.34.0).
 *
 * Formulaire volontairement simple : titre, date, type, visibilité — privée
 * par défaut — et une note facultative. Le formulaire complet (heures, fin,
 * répétition annuelle, branches) reste dans Administration → Calendrier.
 *
 * @var bool  $estAdmin
 * @var array $enAttente, $branches, $erreurs, $old
 */
$v = static fn (string $cle, mixed $defaut = '') => old_value($old, $cle, $defaut);
$visibilite = (string) $v('visibility', 'prive');
$branchesChoisies = array_map('intval', (array) ($old['branches'] ?? []));
?>
<section class="section">
    <div class="section__entete">
        <h1><?= $estAdmin ? 'Ajouter un événement' : 'Proposer un événement' ?></h1>
        <p class="actions">
            <a class="bouton bouton--secondaire" href="/calendrier">Retour au calendrier</a>
            <?php if ($estAdmin): ?>
                <a class="bouton bouton--secondaire" href="/admin/calendrier">Tous les événements</a>
            <?php endif; ?>
        </p>
    </div>

    <?php if (!$estAdmin): ?>
        <p class="note">
            Votre événement part en <strong>attente de validation</strong> : il n'apparaît
            nulle part — ni dans le calendrier, ni ailleurs — tant qu'un administrateur
            ne l'a pas validé.
        </p>
    <?php endif; ?>

    <?php if ($erreurs !== []): ?>
        <div class="message message--erreur">
            <ul><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" action="/calendrier/ajouter" class="formulaire panneau">
        <?= csrf_field() ?>

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
            <label for="place">Lieu (facultatif)</label>
            <input type="text" id="place" name="place" maxlength="120"
                   value="<?= e((string) $v('place')) ?>">
        </div>

        <div class="champ">
            <label for="description">Note (facultatif)</label>
            <textarea id="description" name="description" rows="4"><?= e((string) $v('description')) ?></textarea>
        </div>

        <fieldset class="champ">
            <legend>Qui peut le voir</legend>
            <?php foreach (visibility_levels() as $valeur => $libelle): ?>
                <label class="radio">
                    <input type="radio" name="visibility" value="<?= e($valeur) ?>"
                        <?= checked($visibilite === $valeur) ?>>
                    <?= e($libelle) ?>
                </label>
            <?php endforeach; ?>
            <p class="aide">Privée par défaut : seuls les administrateurs le voient.</p>
        </fieldset>

        <?php if ($branches !== []): ?>
            <fieldset class="champ">
                <legend>Branches (seulement pour « Branches précises »)</legend>
                <?php foreach ($branches as $branche): ?>
                    <label class="case">
                        <input type="checkbox" name="branches[]" value="<?= (int) $branche['id'] ?>"
                            <?= checked(in_array((int) $branche['id'], $branchesChoisies, true)) ?>>
                        <?= e($branche['name']) ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>
        <?php endif; ?>

        <p class="actions">
            <button type="submit" class="bouton"><?= $estAdmin ? 'Ajouter au calendrier' : 'Proposer' ?></button>
        </p>
    </form>

    <?php if ($enAttente !== []): ?>
        <h2>En attente de validation</h2>
        <ul class="liste">
            <?php foreach ($enAttente as $evenement): ?>
                <li>
                    <strong><?= e((string) $evenement['title']) ?></strong>
                    <span class="discret"><?= e((string) $evenement['start_date']) ?> — en attente</span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
