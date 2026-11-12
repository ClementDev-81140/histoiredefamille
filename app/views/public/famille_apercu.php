<?php /** @var array $plan */ ?>
<h1>Vérifier le lien</h1>
<p><?= e($plan['phrase']) ?></p>
<p>Une nouvelle famille sera créée pour ce lien ; les familles existantes ne seront pas modifiées.</p>
<form method="post" action="/arbre/<?= (int) $plan['id'] ?>/famille/confirmer" class="actions famille-apercu-actions">
    <?= csrf_field() ?>
    <input type="hidden" name="personne" value="<?= (int) $plan['autre'] ?>">
    <input type="hidden" name="lien" value="<?= e($plan['type']) ?>">
    <input type="hidden" name="signature" value="<?= e($signature) ?>">
    <button class="bouton" type="submit">Confirmer</button>
    <a class="bouton bouton--secondaire" href="/arbre/<?= (int) $plan['id'] ?>#famille-ajout">Annuler</a>
</form>
