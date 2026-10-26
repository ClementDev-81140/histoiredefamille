<?php
/**
 * Liste des documents.
 *
 * @var array  $documents, $categories, $pagination
 * @var string $q, $type
 * @var int    $categorie, $total
 */
$types = ['' => 'Tous les types', 'photo' => 'Photos', 'video' => 'Vidéos', 'document' => 'Documents et PDF'];
?>
<section class="section">
    <div class="section__entete">
        <h1>Documents</h1>
        <?php if (is_admin()): ?>
            <a class="bouton" href="/admin/documents/nouveau">Ajouter un document</a>
        <?php endif; ?>
    </div>

    <form method="get" action="/documents" class="filtres" role="search">
        <div class="champ">
            <label for="q">Recherche</label>
            <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Titre, description, étiquette…">
        </div>
        <div class="champ">
            <label for="categorie">Catégorie</label>
            <select id="categorie" name="categorie">
                <option value="">Toutes</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>"<?= selected($categorie === (int) $cat['id']) ?>>
                        <?= e($cat['name']) ?> (<?= (int) $cat['nb'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="champ">
            <label for="type">Type</label>
            <select id="type" name="type">
                <?php foreach ($types as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= selected($type === $value) ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="champ champ--bouton">
            <button type="submit" class="bouton">Filtrer</button>
            <?php if ($q !== '' || $categorie > 0 || $type !== ''): ?>
                <a class="lien" href="/documents">Réinitialiser</a>
            <?php endif; ?>
        </div>
    </form>

    <p class="discret">
        <?= (int) $total ?> document<?= (int) $total > 1 ? 's' : '' ?>
        <?php if (!is_logged_in()): ?>
            — les contenus réservés aux familles ne sont pas affichés.
            <a href="/connexion">Se connecter</a>
        <?php endif; ?>
    </p>

    <?php if ($documents === []): ?>
        <div class="panneau panneau--centre">
            <p>Aucun document ne correspond à cette recherche.</p>
        </div>
    <?php else: ?>
        <div class="grille-cartes">
            <?php foreach ($documents as $doc): ?>
                <?php require __DIR__ . '/../partials/carte_document.php'; ?>
            <?php endforeach; ?>
        </div>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</section>
