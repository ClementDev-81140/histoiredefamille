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

    <p class="note">
        Utilisez l’identifiant de votre branche (en majuscules) et le mot de passe
        communiqué par un administrateur. Les comptes sont créés uniquement par
        les administrateurs : il n’y a pas d’inscription publique.
    </p>

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
        Mot de passe oublié ? Contactez un administrateur : il peut en générer un
        nouveau en un clic. Après 5 échecs, la connexion est bloquée 15 minutes.
    </p>
</section>
