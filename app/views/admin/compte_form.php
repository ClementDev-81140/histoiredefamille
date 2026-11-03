<?php
/**
 * Modifier un compte personnel.
 *
 * @var array $compte, $erreurs, $old, $branches
 */
$val = static fn (string $cle, string $defaut = ''): string => (string) old_value($old, $cle, $compte[$cle] ?? $defaut);
$type = compte_type($compte);
?>
<div class="section__entete">
    <div>
        <h1>Modifier <?= e(compte_nom_complet($compte)) ?></h1>
        <p class="note">
            L’identifiant et le mot de passe sont personnels : la personne peut changer son mot
            de passe elle-même dans « Mon compte ».
        </p>
    </div>
    <a class="lien" href="/admin/comptes">← Retour aux comptes</a>
</div>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul>
            <?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="/admin/comptes/<?= (int) $compte['id'] ?>" class="formulaire panneau">
    <?= csrf_field() ?>

    <div class="ligne-champs">
        <div class="champ">
            <label for="nom">Nom</label>
            <input type="text" id="nom" name="nom" maxlength="40" required
                   autocomplete="family-name" value="<?= e($val('last_name')) ?>">
        </div>
        <div class="champ">
            <label for="prenom">Prénom</label>
            <input type="text" id="prenom" name="prenom" maxlength="40" required
                   autocomplete="given-name" value="<?= e($val('first_name')) ?>">
            <p class="aide">C’est ce prénom qui s’affiche dans « Bonjour … » et comme signature.</p>
        </div>
    </div>

    <div class="champ">
        <label for="identifiant">Identifiant</label>
        <input type="text" id="identifiant" name="identifiant" maxlength="30" required
               pattern="[a-z][a-z0-9._\-]{2,29}" value="<?= e($val('username')) ?>">
        <p class="aide">
            Lettres minuscules, chiffres, point, tiret ou souligné (3 à 30 caractères).
            Le changer oblige la personne à utiliser le nouveau pour se connecter.
        </p>
    </div>

    <div class="champ">
        <label for="branche">Branche</label>
        <select id="branche" name="branche" required>
            <?php foreach ($branches as $branche): ?>
                <option value="<?= (int) $branche['id'] ?>"
                    <?= selected((int) $val('branch_id') === (int) $branche['id']) ?>>
                    <?= e($branche['name']) ?><?= (int) $branche['is_active'] === 1 ? '' : ' (désactivée)' ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <label class="case">
        <input type="checkbox" name="see_all" value="1" <?= checked((int) $val('see_all', '0') === 1) ?>>
        Voit toutes les branches
    </label>
    <p class="aide">
        Sans cette case, la personne voit les contenus publics, ceux ouverts à toutes les
        familles et ceux de sa branche.
    </p>

    <p class="actions">
        <button type="submit" class="bouton">Enregistrer</button>
        <a class="bouton bouton--secondaire" href="/admin/comptes">Annuler</a>
    </p>
</form>

<section class="panneau">
    <h2>Ce que ce compte ne change pas ici</h2>
    <ul class="liste-simple">
        <li>
            <strong>Mot de passe</strong> : la personne le change elle-même dans « Mon compte ».
            Vous pouvez en générer un nouveau depuis la liste des comptes
            (« Nouveau mot de passe »).
        </li>
        <li>
            <strong>État du compte</strong> : actif ou désactivé depuis la liste des comptes.
        </li>
    </ul>
</section>
