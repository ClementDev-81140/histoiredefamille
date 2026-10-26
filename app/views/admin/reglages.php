<?php
/**
 * Réglages du site.
 *
 * @var array $erreurs, $old, $values
 */
$val = static fn (string $key, string $default = ''): string => (string) old_value($old, $key, $values[$key] ?? $default);
?>
<h1>Réglages du site</h1>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul>
            <?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="/admin/reglages" class="formulaire panneau">
    <?= csrf_field() ?>

    <div class="champ">
        <label for="name">Nom du site</label>
        <input type="text" id="name" name="name" maxlength="100" required value="<?= e($val('name')) ?>">
        <p class="aide">Affiché dans l’en-tête et le titre des pages.</p>
    </div>

    <div class="champ">
        <label for="subtitle">Sous-titre</label>
        <input type="text" id="subtitle" name="subtitle" maxlength="150" value="<?= e($val('subtitle')) ?>">
    </div>

    <div class="champ">
        <label for="description">Description (page d’accueil)</label>
        <textarea id="description" name="description" rows="3" maxlength="500"><?= e($val('description')) ?></textarea>
    </div>

    <div class="champ">
        <label for="contact">Adresse de contact (facultatif)</label>
        <input type="email" id="contact" name="contact" maxlength="190" value="<?= e($val('contact')) ?>">
        <p class="aide">Utilisée uniquement pour indiquer à qui demander un accès.</p>
    </div>

    <p class="actions">
        <button type="submit" class="bouton">Enregistrer</button>
        <a class="bouton bouton--secondaire" href="/">Voir le résultat</a>
    </p>
</form>

<section class="panneau">
    <h2>Information</h2>
    <p>
        Ces réglages remplacent les valeurs du fichier <code>config.local.php</code>
        (sauf si le champ est laissé vide). Les chemins, la base de données et les
        limites techniques se modifient dans <code>config.local.php</code>.
    </p>
</section>
