<?php
/**
 * Page d'erreur générique.
 *
 * @var string      $titre
 * @var string      $message
 * @var string|null $details
 */
?>
<section class="panneau panneau--centre">
    <h1><?= e($titre ?? 'Erreur') ?></h1>
    <p><?= e($message ?? '') ?></p>

    <?php if (!empty($details) && config('debug')): ?>
        <pre class="erreur-details"><?= e((string) $details) ?></pre>
    <?php endif; ?>

    <p class="actions">
        <a class="bouton" href="/">Retour à l’accueil</a>
        <?php if (!is_logged_in()): ?>
            <a class="bouton bouton--secondaire" href="/connexion">Se connecter</a>
        <?php endif; ?>
    </p>
</section>
