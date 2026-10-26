<?php
/**
 * Gestion des catégories.
 *
 * @var array $categories, $erreurs, $old
 */
?>
<h1>Catégories</h1>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" action="/admin/categories/nouvelle" class="formulaire panneau">
    <?= csrf_field() ?>
    <h2>Nouvelle catégorie</h2>
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
</form>

<section class="section">
    <h2>Catégories existantes</h2>
    <?php if ($categories === []): ?>
        <p class="discret">Aucune catégorie pour le moment.</p>
    <?php else: ?>
        <div class="tableau-defilant">
            <table class="tableau">
                <thead>
                    <tr>
                        <th scope="col">Nom</th>
                        <th scope="col">Adresse</th>
                        <th scope="col">Ordre</th>
                        <th scope="col">Documents</th>
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
                                           maxlength="80" aria-label="Nom de la catégorie" required>
                                    <input type="number" name="position" value="<?= (int) $category['position'] ?>"
                                           min="0" max="999" class="champ-nombre" aria-label="Ordre">
                                    <button type="submit" class="lien-bouton">Renommer</button>
                                </form>
                            </td>
                            <td><code>/documents?categorie=<?= (int) $category['id'] ?></code></td>
                            <td><?= (int) $category['position'] ?></td>
                            <td><?= (int) $category['nb'] ?></td>
                            <td class="colonne-actions">
                                <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/supprimer"
                                      class="formulaire-en-ligne"
                                      data-confirmation="Supprimer cette catégorie ? Les documents seront conservés sans catégorie.">
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
