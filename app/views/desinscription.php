<?php
/**
 * Page publique de désinscription (lien reçu par courriel).
 *
 * @var bool       $valide, $fait
 * @var string     $titre, $type, $jeton, $message
 * @var array|null $compte
 */
?>
<section class="panneau panneau--etroit">
    <h1>Me désinscrire</h1>

    <?php if (!$valide): ?>
        <div class="message message--erreur" role="alert">
            <?= e($message) ?>
        </div>
        <p class="actions">
            <a class="bouton" href="/connexion">Se connecter</a>
        </p>
    <?php elseif ($fait): ?>
        <div class="message message--succes" role="status">
            C’est fait : vous ne recevrez plus <?= e(desinscription_libelle($type)) ?>.
        </div>
        <p>
            Les autres courriels éventuels restent inchangés. Vous pouvez à tout moment
            modifier vos choix dans « Mon compte ».
        </p>
        <p class="actions">
            <a class="bouton" href="/">Retour à l’accueil</a>
        </p>
    <?php else: ?>
        <p>
            Bonjour<?= trim((string) ($compte['display_name'] ?? '')) !== '' ? ' ' . e((string) $compte['display_name']) : '' ?>,
        </p>
        <p>
            Vous allez cesser de recevoir <strong><?= e(desinscription_libelle($type)) ?></strong>
            à l’adresse <code><?= e(courriel_masquer((string) ($compte['email'] ?? ''))) ?></code>.
        </p>
        <p class="note">
            Aucun autre réglage ne change, et vous pouvez revenir en arrière à tout moment
            depuis « Mon compte ».
        </p>

        <form method="post" action="/desinscription" class="formulaire">
            <?= csrf_field() ?>
            <input type="hidden" name="type" value="<?= e($type) ?>">
            <input type="hidden" name="jeton" value="<?= e($jeton) ?>">
            <p class="actions">
                <button type="submit" class="bouton">Confirmer la désinscription</button>
            </p>
        </form>
    <?php endif; ?>
</section>
