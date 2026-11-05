<?php
/**
 * Liste des recettes (administration).
 *
 * @var array $recettes
 */
?>
<div class="section__entete">
    <h1>Recettes</h1>
    <a class="bouton" href="/admin/recettes/nouvelle">Nouvelle recette</a>
</div>

<?php if ($recettes === []): ?>
    <div class="panneau panneau--centre">
        <p>Aucune recette pour le moment.</p>
        <p><a class="bouton" href="/admin/recettes/nouvelle">Écrire la première recette</a></p>
    </div>
<?php else: ?>
    <div class="tableau-defilant">
        <table class="tableau">
            <thead>
                <tr>
                    <th scope="col">Titre</th>
                    <th scope="col">Origine</th>
                    <th scope="col">Visibilité</th>
                    <th scope="col">Adresse</th>
                    <th scope="col">Modifiée le</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recettes as $recette): ?>
                    <tr>
                        <td><a href="/recettes/<?= e($recette['slug']) ?>"><?= e($recette['title']) ?></a></td>
                        <td><?= e((string) ($recette['origin'] ?? '—')) ?></td>
                        <td><span class="<?= e(visibility_class((string) $recette['visibility'])) ?>">
                            <?= e(visibility_short((string) $recette['visibility'])) ?></span></td>
                        <td><code>/recettes/<?= e($recette['slug']) ?></code></td>
                        <td><?= e(format_date((int) $recette['updated_at'])) ?></td>
                        <td class="colonne-actions">
                            <a class="bouton bouton--secondaire" href="/admin/recettes/<?= (int) $recette['id'] ?>">Modifier</a>
                            <form method="post" action="/admin/recettes/<?= (int) $recette['id'] ?>/supprimer"
                                  class="formulaire-en-ligne"
                                  data-confirmation="Supprimer cette recette ?">
                                <?= csrf_field() ?>
                                <button type="submit" class="bouton bouton--danger">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
