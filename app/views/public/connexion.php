<?php
/**
 * Formulaire de connexion.
 *
 * @var string $next
 * @var string $identifiant
 */
?>
<section class="panneau panneau--etroit">
    <h1>Connexion à l’espace famille</h1>


    <form method="post" action="/connexion" class="formulaire">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">

        <div class="champ">
            <label for="username">Identifiant</label>
            <input type="text" id="username" name="username" required autocomplete="username"
                   autocapitalize="characters" value="<?= e($identifiant) ?>">
        </div>

        <div class="champ">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>

        <p class="actions">
            <button type="submit" class="bouton">Se connecter</button>
        </p>
    </form>

    <p class="note">
        Mot de passe oublié ou besoin d’un compte ? Écrivez à
        <a href="mailto:<?= e(site_adresse('support')) ?>"><?= e(site_adresse('support')) ?></a>.
        Un administrateur vous répond : il peut ouvrir un compte et donner un mot de passe
        provisoire. Après 5 échecs, la connexion est bloquée 15 minutes.
    </p>
</section>
