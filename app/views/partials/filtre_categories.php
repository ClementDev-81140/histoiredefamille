<?php
/**
 * Filtre « Thème » d'une liste (1.32.0).
 *
 * @var array  $filtreCategories  thèmes visibles, avec leur compteur
 * @var int    $filtreCategorie   celle qui est demandée (0 = toutes)
 * @var string $filtreAction      adresse de la liste
 * @var string $filtreParam       nom du paramètre d'adresse (« categorie »)
 */
$filtreCategories = (array) ($filtreCategories ?? []);
$filtreCategorie  = (int) ($filtreCategorie ?? 0);
$filtreAction     = (string) ($filtreAction ?? '');
$filtreParam      = (string) ($filtreParam ?? 'theme');

if ($filtreCategories === []) {
    return;
}
?>
<form method="get" action="<?= e($filtreAction) ?>" class="formulaire-en-ligne filtre-categories">
    <label for="filtre-categorie">Thème</label>
    <select id="filtre-categorie" name="<?= e($filtreParam) ?>">
        <option value="0">Tous les thèmes</option>
        <?php foreach ($filtreCategories as $categorie): ?>
            <option value="<?= (int) $categorie['id'] ?>"
                <?= selected($filtreCategorie === (int) $categorie['id']) ?>>
                <?= e((string) $categorie['name']) ?> (<?= (int) $categorie['nb'] ?>)
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="bouton bouton--secondaire">Filtrer</button>
</form>
