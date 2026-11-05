<?php
/**
 * « Mon compte » : informations et changement de son propre mot de passe.
 *
 * La même page sert à tous les comptes (personne, branche, administrateur).
 *
 * @var array      $erreurs, $account, $courriel
 * @var array|null $branche
 * @var bool       $courrielOk
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

<section class="panneau" id="courriels">
    <h2>Mes courriels</h2>

    <?php if (!$courriel['possible']): ?>
        <p class="note">
            Un compte de branche est partagé par plusieurs personnes : il ne peut pas recevoir
            de courriel personnel. Demandez un compte « personne » à un administrateur si vous
            souhaitez être prévenu des nouveaux articles.
        </p>
    <?php else: ?>
        <p class="note">
            Tout est facultatif. Votre adresse n’est jamais affichée sur le site.
        </p>

        <form method="post" action="/mon-compte/courriels" class="formulaire">
            <?= csrf_field() ?>

            <div class="champ">
                <label for="email">Mon adresse électronique</label>
                <input type="email" id="email" name="email" maxlength="190"
                       autocomplete="email" value="<?= e($courriel['email']) ?>">
                <p class="aide">
                    Laissez vide pour ne rien recevoir. Pour changer ou retirer une adresse,
                    votre mot de passe actuel est demandé plus bas.
                </p>
            </div>

            <fieldset class="champ">
                <legend>Ce que je veux recevoir</legend>

                <label class="case">
                    <input type="checkbox" name="notify_articles" value="1" <?= checked($courriel['articles']) ?>>
                    Prévenez-moi des nouveaux articles
                </label>
                <p class="aide">
                    Un seul message par article, et seulement pour les articles publiés
                    <strong>après le moment où vous cochez</strong>.
                </p>

                <label class="case">
                    <input type="checkbox" name="notify_gazette" value="1" <?= checked($courriel['gazette']) ?>>
                    Je veux recevoir la gazette du mois
                </label>
                <p class="aide">Un numéro par mois, en début de mois, avec un lien pour le lire en ligne.</p>
            </fieldset>

            <div class="champ">
                <label for="current_password_courriel">Mot de passe actuel</label>
                <input type="password" id="current_password_courriel" name="current_password"
                       autocomplete="current-password">
                <p class="aide">
                    Nécessaire seulement si vous changez ou retirez votre adresse.
                </p>
            </div>

            <p class="actions">
                <button type="submit" class="bouton">Enregistrer mes courriels</button>
            </p>
        </form>

        <?php if (!$courrielOk): ?>
            <div class="message message--info">
                Le serveur d’envoi n’est pas encore configuré : vos choix sont enregistrés, mais
                aucun courriel ne partira tant que l’administrateur n’aura pas rempli
                <code>config.local.php</code> (voir README, « Envoyer des courriels »).
            </div>
        <?php endif; ?>

        <p class="aide">
            Vous pouvez aussi vous désinscrire en un clic depuis n’importe quel courriel reçu.
            Chaque message contient ce lien, sans avoir besoin de vous connecter.
        </p>
    <?php endif; ?>
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
