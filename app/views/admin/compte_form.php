<?php
/**
 * Modifier un compte personnel.
 *
 * @var array $compte, $erreurs, $old, $branches
 */
$val = static fn (string $cle, string $defaut = ''): string => (string) old_value($old, $cle, $compte[$cle] ?? $defaut);
$type = compte_type($compte);
$profilActuel = $type === 'personne' ? 'journaliste' : 'lecteur';
$profilChoisi = (string) old_value($old, 'profil', $profilActuel);
?>
<div class="section__entete">
    <div>
        <h1>Modifier <?= e(compte_nom_complet($compte)) ?></h1>
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

    <div class="champ">
        <label for="profil">Profil</label>
        <select id="profil" name="profil">
            <?php foreach ($profils as $cle => $libelle): ?>
                <option value="<?= e($cle) ?>"<?= selected($profilChoisi === $cle) ?>><?= e($libelle) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="aide">
            <strong>Lecteur</strong> : <?= e(compte_profil_resume('branche')) ?><br>
            <strong>Journaliste</strong> : <?= e(compte_profil_resume('personne')) ?>
        </p>
    </div>

    <div class="ligne-champs">
        <div class="champ">
            <label for="nom">Nom</label>
            <input type="text" id="nom" name="nom" maxlength="40"
                   autocomplete="family-name" value="<?= e($val($profilChoisi === 'journaliste' ? 'last_name' : 'display_name') ) ?>">
        </div>
        <div class="champ">
            <label for="prenom">Prénom</label>
            <input type="text" id="prenom" name="prenom" maxlength="40"
                   autocomplete="given-name" value="<?= e($val('first_name')) ?>">
            <p class="aide">Journaliste : le prénom s’affiche dans « Bonjour … » et comme signature.</p>
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

    <?php /* 1.28.0 : le site n'envoie aucun courriel par défaut. Ni adresse,
             ni case de consentement : rien à saisir, et les valeurs déjà
             enregistrées ne sont ni montrées ni modifiées. */ ?>
    <?php if (site_courriels_actifs()): ?>
    <fieldset class="champ">
        <legend>Courriels</legend>
        <p class="aide">
            Ces cases ne se cochent qu’à la demande de la personne.
        </p>

        <div class="champ">
            <label for="email">Adresse électronique</label>
            <input type="email" id="email" name="email" maxlength="190" autocomplete="off"
                   value="<?= e($val('email')) ?>">
            <p class="aide">
                Changer cette adresse remet les deux cases ci-dessous à zéro : une adresse
                saisie par un administrateur ne vaut pas accord.
            </p>
        </div>

        <label class="case">
            <input type="checkbox" name="notify_articles" value="1"
                <?= checked((int) $val('notify_articles', '0') === 1) ?>>
            Prévenez-moi des nouveaux articles
        </label>
        <p class="aide">
            La personne ne recevra que les articles publiés après le moment où cette case est
            cochée : cocher ici ne renvoie pas les anciens articles.
        </p>

        <label class="case">
            <input type="checkbox" name="notify_gazette" value="1"
                <?= checked((int) $val('notify_gazette', '0') === 1) ?>>
            Je veux recevoir la gazette du mois
        </label>

        <?php if ((string) ($compte['email'] ?? '') !== ''): ?>
            <p class="aide">
                Dernière préférence enregistrée par la personne :
                articles <?= (int) ($compte['notify_articles'] ?? 0) === 1 ? 'oui' : 'non' ?>,
                gazette <?= (int) ($compte['notify_gazette'] ?? 0) === 1 ? 'oui' : 'non' ?>.
            </p>
        <?php endif; ?>
    </fieldset>
    <?php endif; ?>

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
