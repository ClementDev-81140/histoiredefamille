<?php /** @var array $plan */ ?>
<h1>Vérifier le lien</h1>
<p><?= e($plan['phrase']) ?></p>
<?php if ($plan['famille'] > 0 && in_array($plan['type'], ['parent', 'enfant'], true)): ?>
<p>La famille indiquée ci-dessus sera utilisée.</p>
<?php else: ?>
<p>Une nouvelle famille sera créée pour ce lien ; les familles existantes ne seront pas modifiées.</p>
<?php endif; ?>
<form method="post" action="/arbre/<?= (int) $plan['id'] ?>/famille/apercu" class="formulaire panneau famille-apercu-actions">
    <?= csrf_field() ?>
    <input type="hidden" name="personne" value="<?= (int) $plan['autre'] ?>">
    <input type="hidden" name="lien" value="<?= e($plan['type']) ?>">
    <input type="hidden" name="signature" value="<?= e($signature) ?>">
    <?php if ($plan['options'] !== []): ?>
    <fieldset class="champ"><legend>Choisir une famille</legend>
        <?php foreach ($plan['options'] as $option): ?>
            <label class="radio"><input type="radio" name="choix_famille" value="<?= (int) $option['id'] ?>" <?= checked($plan['famille'] === $option['id']) ?>> Avec <?= e($option['nom']) ?></label>
        <?php endforeach; ?>
        <label class="radio"><input type="radio" name="choix_famille" value="0" <?= checked($plan['famille'] === 0) ?>> Sans conjoint</label>
    </fieldset>
    <p class="aide">Après un changement, appuyez sur « Mettre à jour l’aperçu » avant de confirmer.</p>
    <button class="bouton bouton--secondaire" type="submit">Mettre à jour l’aperçu</button>
    <?php elseif (in_array($plan['type'], ['parent', 'enfant'], true)): ?>
    <input type="hidden" name="choix_famille" value="0">
    <?php endif; ?>
    <p class="actions">
        <button class="bouton" type="submit" formaction="/arbre/<?= (int) $plan['id'] ?>/famille/confirmer">Confirmer</button>
        <a class="bouton bouton--secondaire" href="/arbre/<?= (int) $plan['id'] ?>#famille-ajout">Annuler</a>
    </p>
</form>
