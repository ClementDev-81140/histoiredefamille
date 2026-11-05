<?php
/**
 * Filtre « Visibilité » des listes de l'administration.
 *
 * Un simple formulaire GET : l'adresse garde le filtre (`?visibilite=public`),
 * ce qui permet d'y renvoyer depuis le tableau de bord, et la liste se partage
 * telle quelle. Aucun JavaScript.
 *
 * @var string      $action     adresse de la liste (ex. « /admin/articles »)
 * @var string|null $visibility valeur choisie ('' = toutes)
 */
$visibility = $visibility ?? '';
?>
<form method="get" action="<?= e($action) ?>" class="filtres filtres--visibilite">
    <div class="champ">
        <label for="visibilite">Visibilité</label>
        <select id="visibilite" name="visibilite">
            <option value="">Toutes</option>
            <?php foreach (visibility_levels() as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= selected($visibility === $value) ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="champ champ--bouton">
        <button type="submit" class="bouton">Filtrer</button>
        <a class="lien" href="<?= e($action) ?>">Réinitialiser</a>
    </div>
</form>
