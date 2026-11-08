<?php
/**
 * Cases « Thèmes » d'un formulaire de contenu (1.32.0).
 *
 * @var array  $categoriesCases thèmes qui s'appliquent à ce type de contenu
 * @var array  $categorieIds  celles déjà cochées
 * @var string $kindCategorie libellé du type (« Articles », « Photos »…)
 */
$kindCategorie = (string) ($kindCategorie ?? 'ce type de contenu');
$categorieIds  = array_map('intval', (array) ($categorieIds ?? []));
$categoriesCases = (array) ($categoriesCases ?? []);
?>
<fieldset class="champ">
    <legend>Thèmes</legend>
    <p class="aide">
        Seuls les thèmes prévus pour <?= e(mb_strtolower($kindCategorie)) ?> sont proposées.
        Plusieurs choix sont possibles.
    </p>
    <?php if ($categoriesCases === []): ?>
        <p class="note">
            Aucun thème ne s’applique encore à <?= e(mb_strtolower($kindCategorie)) ?>.
            Ils se créent dans <a href="/admin/categories">Thèmes</a>.
        </p>
    <?php else: ?>
        <?php foreach ($categoriesCases as $categorie): ?>
            <label class="case">
                <input type="checkbox" name="categories[]" value="<?= (int) $categorie['id'] ?>"
                    <?= checked(in_array((int) $categorie['id'], $categorieIds, true)) ?>>
                <?= e($categorie['name']) ?>
            </label>
        <?php endforeach; ?>
    <?php endif; ?>
</fieldset>
