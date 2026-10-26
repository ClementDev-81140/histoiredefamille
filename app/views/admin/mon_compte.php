<?php
/**
 * Compte personnel de l'administrateur.
 *
 * @var array $erreurs, $account
 */
?>
<h1>Mon compte</h1>

<section class="panneau">
    <h2>Informations</h2>
    <dl class="fiche">
        <dt>Identifiant</dt><dd><?= e((string) $account['username']) ?></dd>
        <dt>Nom affiché</dt><dd><?= e((string) $account['display_name']) ?></dd>
        <dt>Rôle</dt><dd>Administrateur</dd>
        <dt>Dernière connexion</dt><dd><?= e(format_date((int) ($account['last_login_at'] ?? 0), true)) ?></dd>
    </dl>
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

    <form method="post" action="/admin/mon-compte" class="formulaire">
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
            <p class="aide">10 caractères minimum. Une phrase de passe est idéale.</p>
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
