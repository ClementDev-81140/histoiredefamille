<?php
/**
 * Liste des personnes (administration de l'arbre).
 *
 * @var array  $persons, $pagination
 * @var string $q
 * @var int    $total, $nbCouples
 */
?>
<div class="section__entete">
    <h1>Arbre généalogique</h1>
    <span class="actions">
        <a class="bouton" href="/admin/arbre/personne/nouvelle">Ajouter une personne</a>
        <a class="bouton bouton--secondaire" href="/admin/arbre/couples">Couples (<?= (int) $nbCouples ?>)</a>
        <a class="bouton bouton--secondaire" href="/admin/arbre/import">Importer GEDCOM</a>
    </span>
</div>

<form method="get" action="/admin/arbre" class="filtres">
    <div class="champ">
        <label for="q">Rechercher</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Nom ou prénom">
    </div>
    <div class="champ champ--bouton">
        <button type="submit" class="bouton">Rechercher</button>
        <a class="lien" href="/admin/arbre">Tout</a>
    </div>
</form>

<p class="discret"><?= (int) $total ?> personne(s) — <?= (int) $nbCouples ?> couple(s)</p>

<?php if ($persons === []): ?>
    <div class="panneau panneau--centre">
        <p>Aucune personne.</p>
        <p>
            <a class="bouton" href="/admin/arbre/personne/nouvelle">Ajouter une personne</a>
            <a class="bouton bouton--secondaire" href="/admin/arbre/import">Importer un GEDCOM</a>
        </p>
    </div>
<?php else: ?>
    <div class="tableau-defilant">
        <table class="tableau">
            <thead>
                <tr>
                    <th scope="col">Nom</th>
                    <th scope="col">Prénom</th>
                    <th scope="col">Naissance</th>
                    <th scope="col">Décès</th>
                    <th scope="col">Visibilité</th>
                    <th scope="col">Liens</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($persons as $person): ?>
                    <tr>
                        <td><?= e((string) $person['last_name']) ?></td>
                        <td><?= e((string) $person['first_name']) ?></td>
                        <td><?= e((string) ($person['birth_date'] ?? '—')) ?></td>
                        <td><?= e((string) ($person['death_date'] ?? '—')) ?></td>
                        <td><span class="<?= e(visibility_class((string) $person['visibility'])) ?>">
                            <?= e(visibility_short((string) $person['visibility'])) ?></span></td>
                        <td>
                            <?= (int) $person['nb_unions'] ?> union(s)
                            · <?= (int) $person['nb_familles_parentes'] ?> famille(s) de parents
                        </td>
                        <td class="colonne-actions">
                            <a href="/arbre/<?= (int) $person['id'] ?>">Fiche</a>
                            <a href="/admin/arbre/personne/<?= (int) $person['id'] ?>">Modifier</a>
                            <form method="post" action="/admin/arbre/personne/<?= (int) $person['id'] ?>/supprimer"
                                  class="formulaire-en-ligne"
                                  data-confirmation="Supprimer cette personne ? Ses liens de parenté seront retirés.">
                                <?= csrf_field() ?>
                                <button type="submit" class="lien-bouton lien-bouton--danger">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php require __DIR__ . '/../partials/pagination.php'; ?>
<?php endif; ?>
