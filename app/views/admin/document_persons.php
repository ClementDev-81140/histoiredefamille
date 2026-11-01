<?php
/**
 * Personnes identifiées sur un document (photo, témoignage audio…).
 *
 * @var array  $doc, $identifiees, $trouves
 * @var string $q
 */
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/admin/documents">Documents</a>
    <span aria-hidden="true">/</span>
    <a href="/documents/<?= (int) $doc['id'] ?>"><?= e($doc['title']) ?></a>
    <span aria-hidden="true">/</span>
    <span>Personnes</span>
</nav>

<h1>Personnes sur « <?= e($doc['title']) ?> »</h1>

<p class="discret">
    Les personnes indiquées ici apparaissent sur la fiche du document et sur
    leur propre fiche (« Photos et documents »). Seuls les noms visibles par le
    visiteur sont affichés publiquement.
</p>

<div class="deux-colonnes">
    <section class="panneau">
        <h2>Personnes identifiées</h2>
        <?php if ($identifiees === []): ?>
            <p class="discret">Aucune personne pour le moment.</p>
        <?php else: ?>
            <ul class="liste-personnes liste-personnes--actions">
                <?php foreach ($identifiees as $personne): ?>
                    <li>
                        <span>
                            <a href="/arbre/<?= (int) $personne['id'] ?>"><?= e(person_full_name($personne)) ?></a>
                            <span class="discret"><?= e(person_dates($personne)) ?></span>
                        </span>
                        <form method="post" action="/admin/documents/<?= (int) $doc['id'] ?>/personnes/retirer"
                              class="formulaire-en-ligne">
                            <?= csrf_field() ?>
                            <input type="hidden" name="person_id" value="<?= (int) $personne['id'] ?>">
                            <button type="submit" class="bouton bouton--secondaire">Retirer</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panneau">
        <h2>Ajouter une personne</h2>
        <form method="get" action="/admin/documents/<?= (int) $doc['id'] ?>/personnes" class="filtres">
            <div class="champ">
                <label for="q">Nom ou prénom</label>
                <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Martin, Jean…">
            </div>
            <div class="champ champ--bouton">
                <button type="submit" class="bouton">Chercher</button>
            </div>
        </form>

        <?php if ($q !== '' && $trouves === []): ?>
            <p class="discret">Aucune personne ne correspond à « <?= e($q) ?> ».</p>
        <?php elseif ($trouves === []): ?>
            <p class="discret">Tapez un nom pour retrouver une personne de l’arbre.</p>
        <?php else: ?>
            <ul class="liste-personnes liste-personnes--actions">
                <?php foreach ($trouves as $personne): ?>
                    <li>
                        <span>
                            <a href="/arbre/<?= (int) $personne['id'] ?>"><?= e(person_full_name($personne)) ?></a>
                            <span class="discret"><?= e(person_dates($personne)) ?></span>
                        </span>
                        <form method="post" action="/admin/documents/<?= (int) $doc['id'] ?>/personnes/ajouter"
                              class="formulaire-en-ligne">
                            <?= csrf_field() ?>
                            <input type="hidden" name="person_id" value="<?= (int) $personne['id'] ?>">
                            <button type="submit" class="bouton">Ajouter</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<p class="actions">
    <a class="bouton bouton--secondaire" href="/documents/<?= (int) $doc['id'] ?>">Voir le document</a>
    <a class="bouton bouton--secondaire" href="/admin/documents/<?= (int) $doc['id'] ?>/modifier">Modifier les informations</a>
</p>
