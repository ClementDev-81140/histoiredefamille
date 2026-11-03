<?php
/**
 * « Mon compte » : informations et changement de son propre mot de passe.
 *
 * La même page sert à tous les comptes (personne, branche, administrateur).
 *
 * @var array      $erreurs, $account
 * @var array|null $branche
 */
$type = compte_type($account);
?>
<h1>Mon compte</h1>

<section class="panneau">
    <h2>Informations</h2>
    <dl class="fiche">
        <dt>Nom</dt>
        <dd><?= e(compte_nom_complet($account)) ?></dd>
        <dt>Identifiant</dt>
        <dd><code><?= e((string) $account['username']) ?></code></dd>
        <dt>Type de compte</dt>
        <dd><?= e(compte_type_libelle($account)) ?></dd>
        <?php if ($branche !== null): ?>
            <dt>Branche</dt>
            <dd><?= e((string) $branche['name']) ?></dd>
        <?php endif; ?>
        <dt>Droits</dt>
        <dd>
            <?= can_see_all()
                ? 'Toutes les branches'
                : 'Contenus publics, toutes les familles, et votre branche' ?>
        </dd>
        <dt>Dernière connexion</dt>
        <dd><?= e(format_date((int) ($account['last_login_at'] ?? 0), true)) ?></dd>
    </dl>
    <p class="note">
        Pour changer de nom, de prénom, d’identifiant ou de branche, demandez à un
        administrateur : ces informations ne se modifient pas ici.
    </p>
</section>

<section class="panneau">
    <h2>Changer mon mot de passe</h2>

    <?php if ($erreurs !== []): ?>
        <div class="message message--erreur">
            <ul>
                <?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="/mon-compte" class="formulaire">
        <?= csrf_field() ?>

        <div class="champ">
            <label for="current_password">Mot de passe actuel</label>
            <input type="password" id="current_password" name="current_password"
                   autocomplete="current-password" required>
        </div>

        <div class="champ">
            <label for="new_password">Nouveau mot de passe</label>
            <input type="password" id="new_password" name="new_password"
                   autocomplete="new-password" minlength="10" required>
            <p class="aide">
                10 caractères minimum. Une phrase de passe est idéale (par exemple
                « les tuiles de 1958 »). Il ne doit pas être identique à votre identifiant.
            </p>
        </div>

        <div class="champ">
            <label for="confirm_password">Confirmer le nouveau mot de passe</label>
            <input type="password" id="confirm_password" name="confirm_password"
                   autocomplete="new-password" minlength="10" required>
        </div>

        <p class="actions">
            <button type="submit" class="bouton">Modifier le mot de passe</button>
        </p>
    </form>
</section>
