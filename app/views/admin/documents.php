<?php
/**
 * Liste des documents (administration).
 *
 * @var array  $documents, $pagination
 * @var string $q, $type, $visibility
 * @var int    $total
 */
?>
<div class="section__entete">
    <h1>Documents</h1>
    <a class="bouton" href="/admin/documents/nouveau">Ajouter un document</a>
</div>

<form method="get" action="/admin/documents" class="filtres">
    <div class="champ">
        <label for="q">Recherche</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>">
    </div>
    <div class="champ">
        <label for="type">Type</label>
        <select id="type" name="type">
            <option value="">Tous</option>
            <option value="photo"<?= selected($type === 'photo') ?>>Photos</option>
            <option value="video"<?= selected($type === 'video') ?>>Vidéos</option>
            <option value="document"<?= selected($type === 'document') ?>>Documents / PDF</option>
        </select>
    </div>
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
        <a class="lien" href="/admin/documents">Réinitialiser</a>
    </div>
</form>

<p class="discret"><?= (int) $total ?> document(s)</p>

<?php if ($documents === []): ?>
    <div class="panneau panneau--centre">
        <p>Aucun document.</p>
        <p><a class="bouton" href="/admin/documents/nouveau">Envoyer un premier fichier</a></p>
    </div>
<?php else: ?>
    <div class="tableau-defilant">
        <table class="tableau">
            <thead>
                <tr>
                    <th scope="col">Titre</th>
                    <th scope="col">Type</th>
                    <th scope="col">Catégorie</th>
                    <th scope="col">Visibilité</th>
                    <th scope="col">Taille</th>
                    <th scope="col">Ajouté le</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $doc): ?>
                    <tr>
                        <td>
                            <a href="/documents/<?= (int) $doc['id'] ?>"><?= e($doc['title']) ?></a>
                            <?php if ((int) $doc['is_attachment'] === 1): ?>
                                <span class="etiquette">photo de fiche</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e(document_kind_label((string) $doc['kind'], (string) $doc['ext'])) ?></td>
                        <td><?= e((string) ($doc['category_name'] ?? '—')) ?></td>
                        <td><span class="<?= e(visibility_class((string) $doc['visibility'])) ?>">
                            <?= e(visibility_short((string) $doc['visibility'])) ?></span></td>
                        <td><?= e(format_size((int) $doc['size'])) ?></td>
                        <td><?= e(format_date((int) $doc['created_at'])) ?></td>
                        <td class="colonne-actions">
                            <a href="/admin/documents/<?= (int) $doc['id'] ?>/modifier">Modifier</a>
                            <form method="post" action="/admin/documents/<?= (int) $doc['id'] ?>/supprimer"
                                  class="formulaire-en-ligne"
                                  data-confirmation="Supprimer définitivement ce document et son fichier ?">
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
