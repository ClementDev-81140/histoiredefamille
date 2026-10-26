<?php
/**
 * Arbre généalogique : répertoire et explorateur.
 *
 * @var array      $persons, $pagination, $letters
 * @var string     $q, $lettre
 * @var array|null $root
 * @var int        $total
 */
$alphabet = range('A', 'Z');
?>
<section class="section">
    <div class="section__entete">
        <h1>Arbre généalogique</h1>
        <?php if (is_admin()): ?>
            <span class="actions">
                <a class="bouton" href="/admin/arbre/personne/nouvelle">Ajouter une personne</a>
                <a class="bouton bouton--secondaire" href="/admin/arbre/couples">Couples</a>
                <a class="bouton bouton--secondaire" href="/admin/arbre/import">Importer GEDCOM</a>
            </span>
        <?php endif; ?>
    </div>

    <form method="get" action="/arbre" class="filtres">
        <div class="champ">
            <label for="q">Rechercher une personne</label>
            <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Nom ou prénom">
        </div>
        <div class="champ champ--bouton">
            <button type="submit" class="bouton">Rechercher</button>
            <?php if ($q !== '' || $lettre !== ''): ?>
                <a class="lien" href="/arbre">Tout afficher</a>
            <?php endif; ?>
        </div>
    </form>

    <nav class="lettres" aria-label="Parcourir par initiale">
        <a href="/arbre<?= $q !== '' ? '?q=' . rawurlencode($q) : '' ?>"
           class="<?= $lettre === '' ? 'actif' : '' ?>">Toutes</a>
        <?php foreach ($alphabet as $letter): ?>
            <?php if (in_array($letter, $letters, true)): ?>
                <a href="/arbre?lettre=<?= $letter ?><?= $q !== '' ? '&q=' . rawurlencode($q) : '' ?>"
                   class="<?= $lettre === $letter ? 'actif' : '' ?>"><?= $letter ?></a>
            <?php else: ?>
                <span class="inactif"><?= $letter ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <p class="discret"><?= (int) $total ?> personne<?= (int) $total > 1 ? 's' : '' ?></p>

    <?php if ($root !== null): ?>
        <section class="panneau explorateur" id="explorateur-arbre"
                 data-racine="<?= (int) $root['id'] ?>"
                 data-nom="<?= e(person_full_name($root)) ?>">
            <div class="section__entete">
                <h2>Explorateur : <?= e(person_full_name($root)) ?></h2>
                <a href="/arbre/<?= (int) $root['id'] ?>">Voir la fiche</a>
            </div>
            <p class="note" id="explorateur-chargement">Chargement de l’arbre…</p>
            <div id="explorateur-contenu" class="explorateur__contenu"></div>
            <noscript>
                <p>
                    L’affichage graphique nécessite JavaScript. Les liens ci-dessous
                    permettent de naviguer de fiche en fiche :
                    <a href="/arbre/<?= (int) $root['id'] ?>">fiche de <?= e(person_full_name($root)) ?></a>.
                </p>
            </noscript>
        </section>
    <?php endif; ?>

    <?php if ($persons === []): ?>
        <div class="panneau panneau--centre">
            <p>Aucune personne ne correspond à cette recherche.</p>
        </div>
    <?php else: ?>
        <table class="tableau">
            <caption class="sr-only">Liste des personnes</caption>
            <thead>
                <tr>
                    <th scope="col">Nom</th>
                    <th scope="col">Prénom</th>
                    <th scope="col">Dates</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($persons as $person): ?>
                    <tr>
                        <td><?= e((string) $person['last_name']) ?></td>
                        <td><?= e((string) $person['first_name']) ?></td>
                        <td><?= e(person_dates($person)) ?></td>
                        <td class="colonne-actions">
                            <a href="/arbre/<?= (int) $person['id'] ?>">Fiche</a>
                            <a href="/arbre?racine=<?= (int) $person['id'] ?>">Arbre</a>
                            <?php if (is_admin()): ?>
                                <a href="/admin/arbre/personne/<?= (int) $person['id'] ?>">Modifier</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php require __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</section>
