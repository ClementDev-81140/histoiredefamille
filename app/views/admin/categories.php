<?php
/**
 * Gestion des thèmes.
 *
 * @var array $categories, $erreurs, $old
 */
?>
<h1>Thèmes</h1>
<p class="aide">Un thème regroupe des contenus de tous types qui parlent du même sujet.</p>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" action="/admin/categories/nouvelle" class="formulaire panneau">
    <?= csrf_field() ?>
    <h2>Nouveau thème</h2>
    <div class="ligne-champs">
        <div class="champ">
            <label for="name">Nom</label>
            <input type="text" id="name" name="name" maxlength="80" required
                   value="<?= e((string) old_value($old, 'name', '')) ?>" placeholder="ex. Photos de mariage">
        </div>
        <div class="champ champ--petit">
            <label for="position">Ordre</label>
            <input type="number" id="position" name="position" min="0" max="999" value="0">
        </div>
        <div class="champ champ--bouton">
            <button type="submit" class="bouton">Créer</button>
        </div>
    </div>

    <fieldset class="champ">
        <legend>Types de contenu</legend>
        <p class="aide">
            Un thème peut servir aux articles, aux documents, aux photos, aux albums,
            aux vidéos et aux recettes. Tout est coché par défaut. Aucune case cochée
            vaut « tous les types ».
        </p>
        <?php foreach (CATEGORY_KINDS as $type => $libelle): ?>
            <label class="case">
                <input type="checkbox" name="kinds[]" value="<?= e($type) ?>" checked>
                <?= e($libelle) ?>
            </label>
        <?php endforeach; ?>
    </fieldset>
</form>

<section class="section">
    <h2>Thèmes existants</h2>
    <?php if ($categories === []): ?>
        <p class="discret">Aucun thème pour le moment.</p>
    <?php else: ?>
        <div class="tableau-defilant">
            <table class="tableau">
                <thead>
                    <tr>
                        <th scope="col">Nom</th>
                        <th scope="col">Adresse</th>
                        <th scope="col">Ordre</th>
                        <th scope="col">Types</th>
                        <th scope="col">Contenus</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td>
                                <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/modifier"
                                      class="formulaire-en-ligne">
                                    <?= csrf_field() ?>
                                    <input type="text" name="name" value="<?= e($category['name']) ?>"
                                           maxlength="80" aria-label="Nom de le thème" required>
                                    <input type="number" name="position" value="<?= (int) $category['position'] ?>"
                                           min="0" max="999" class="champ-nombre" aria-label="Ordre">
                                    <button type="submit" class="lien-bouton">Renommer</button>
                                </form>
                            </td>
                            <td><code>/documents?theme=<?= (int) $category['id'] ?></code></td>
                            <td><?= (int) $category['position'] ?></td>
                            <td class="colonne-types">
                                <?php $typesCat = category_kinds($category); ?>
                                <?= e(count($typesCat) === count(CATEGORY_KINDS)
                                    ? 'Tous'
                                    : implode(', ', array_map(static fn (string $t): string => CATEGORY_KINDS[$t], $typesCat))) ?>
                                <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/modifier"
                                      class="formulaire-en-ligne formulaire-types">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="name" value="<?= e($category['name']) ?>">
                                    <input type="hidden" name="position" value="<?= (int) $category['position'] ?>">
                                    <?php foreach (CATEGORY_KINDS as $type => $libelle): ?>
                                        <label class="case case--fine">
                                            <input type="checkbox" name="kinds[]" value="<?= e($type) ?>"
                                                <?= checked(in_array($type, $typesCat, true)) ?>>
                                            <?= e($libelle) ?>
                                        </label>
                                    <?php endforeach; ?>
                                    <button type="submit" class="lien-bouton">Appliquer</button>
                                </form>
                            </td>
                            <td><?= (int) $category['nb'] ?></td>
                            <td class="colonne-actions">
                                <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/supprimer"
                                      class="formulaire-en-ligne"
                                      data-confirmation="Supprimer ce thème ? Les documents seront conservés sans thème.">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="lien-bouton lien-bouton--danger">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
