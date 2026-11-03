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
        <label for="devise">Devise du journal (facultatif)</label>
        <input type="text" id="devise" name="devise" maxlength="120" value="<?= e($val('devise')) ?>">
        <p class="aide">
            Affichée entre guillemets sous le titre, au centre (par exemple
            « Mémoire des Bertillon et des Vasseur »). Vide, elle reprend le sous-titre.
        </p>
    </div>

    <div class="champ">
        <label for="fondee">Année de fondation (facultatif)</label>
        <input type="text" id="fondee" name="fondee" maxlength="4" inputmode="numeric" value="<?= e($val('fondee')) ?>">
        <p class="aide">Quatre chiffres, affichés à gauche sous le titre : « Fondée en 1889 ».</p>
    </div>

    <div class="champ">
        <label for="description">Description (page d’accueil)</label>
        <textarea id="description" name="description" rows="3" maxlength="500"><?= e($val('description')) ?></textarea>
    </div>

    <div class="champ">
        <label for="adresse">Adresse publique du site</label>
        <input type="url" id="adresse" name="adresse" maxlength="190"
               placeholder="https://histoiredefamille.eu" value="<?= e($val('adresse')) ?>">
        <p class="aide">
            Elle sert dans les messages d’accès envoyés aux personnes
            (« Adresse : https://… »). Sans valeur, l’adresse du fichier
            <code>config.local.php</code> est utilisée.
        </p>
    </div>

    <div class="champ">
        <label for="contact">Adresse de contact (facultatif)</label>
        <input type="email" id="contact" name="contact" maxlength="190" value="<?= e($val('contact')) ?>">
        <p class="aide">Utilisée uniquement pour indiquer à qui demander un accès.</p>
    </div>

    <fieldset class="champ">
        <legend>Tchat de la famille</legend>

        <div class="tchat-reglage">
            <label class="case">
                <input type="checkbox" id="tchat_actif" name="tchat_actif" value="1"
                       <?= checked($val('tchat_actif') === '1') ?>>
                Ouvrir le tchat pour les personnes connectées
            </label>
            <p class="aide">
                Quand le tchat est fermé, l’onglet disparaît de la navigation et personne
                ne peut lire ni écrire. Les messages déjà envoyés sont conservés.
            </p>
        </div>

        <div class="tchat-reglage">
            <label for="tchat_conservation_mois">Conservation des messages</label>
            <select id="tchat_conservation_mois" name="tchat_conservation_mois">
                <?php foreach ([0 => 'Jamais (conservation illimitée)', 12 => '1 an', 6 => '6 mois', 3 => '3 mois'] as $mois => $libelle): ?>
                    <option value="<?= (int) $mois ?>"<?= selected($val('tchat_conservation_mois') === (string) $mois) ?>><?= e($libelle) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="aide">
                Un message plus ancien que cette durée est effacé automatiquement par la
                tâche d’entretien de la nuit du dimanche (<code>app/cli/maintenance.php</code>).
                Valeur par défaut : 1 an.
            </p>
        </div>
    </fieldset>

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
