<?php
/**
 * Filtre « Catégorie » d'une liste de l'administration (1.32.0).
 *
 * Formulaire GET : l'adresse garde le filtre (`?categorie=3`), les autres
 * filtres de la page (visibilité, recherche…) sont conservés en champs
 * cachés. Aucun JavaScript.
 *
 * @var array  $filtreCategories catégories du type, avec leur compteur
 * @var int    $filtreCategorie  celle qui est demandée (0 = toutes)
 * @var string $action           adresse de la liste
 * @var array  $filtreAutres     autres filtres à conserver (nom => valeur)
 */
$filtreCategories = (array) ($filtreCategories ?? []);
$filtreCategorie  = (int) ($filtreCategorie ?? 0);
$action           = (string) ($action ?? '');
$filtreAutres     = (array) ($filtreAutres ?? []);

if ($filtreCategories === []) {
    return;
}
?>
<form method="get" action="<?= e($action) ?>" class="filtres filtres--categories">
    <?php foreach ($filtreAutres as $nom => $valeur): ?>
        <?php if ((string) $valeur !== ''): ?>
            <input type="hidden" name="<?= e((string) $nom) ?>" value="<?= e((string) $valeur) ?>">
        <?php endif; ?>
    <?php endforeach; ?>
    <div class="champ">
        <label for="categorie">Catégorie</label>
        <select id="categorie" name="categorie">
            <option value="0">Toutes</option>
            <?php foreach ($filtreCategories as $categorie): ?>
                <option value="<?= (int) $categorie['id'] ?>"
                    <?= selected($filtreCategorie === (int) $categorie['id']) ?>>
                    <?= e((string) $categorie['name']) ?> (<?= (int) $categorie['nb'] ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="champ champ--bouton">
        <button type="submit" class="bouton">Filtrer</button>
        <a class="lien" href="<?= e($action) ?>">Réinitialiser</a>
    </div>
</form>
