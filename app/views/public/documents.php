<?php
/**
 * Liste des documents.
 *
 * @var array  $documents, $categories, $pagination, $dossiers
 * @var string $q, $type, $archives
 * @var int    $categorie, $total
 */
$types = ['' => 'Tous les types', 'photo' => 'Photos', 'video' => 'Vidéos', 'document' => 'Documents et PDF'];
?>
<section class="section">
    <div class="section__entete">
        <div>
            <h1>Documents</h1>
            <p class="aide">Photos, vidéos, sons et papiers de la famille. Ceux classés en archive sont rangés dans les dossiers ci-dessous.</p>
        </div>
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
            <label for="theme">Thème</label>
            <select id="theme" name="theme">
                <option value="">Tous</option>
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
        <div class="champ">
            <label for="archives">Archives</label>
            <select id="archives" name="archives">
                <option value=""<?= selected(($archives ?? '') === '') ?>>Tout montrer</option>
                <option value="1"<?= selected(($archives ?? '') === '1') ?>>Archives seulement</option>
                <option value="0"<?= selected(($archives ?? '') === '0') ?>>Journal seulement</option>
            </select>
        </div>
        <div class="champ champ--bouton">
            <button type="submit" class="bouton">Filtrer</button>
            <?php if ($q !== '' || $categorie > 0 || $type !== '' || ($archives ?? '') !== ''): ?>
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

    <?php /* Archives et dossiers (1.36.0) : seuls les dossiers ayant au
             moins un document visible pour ce compte sont listés — ni leur
             nom ni leur nombre ne fuient. */ ?>
    <?php if (($dossiers ?? []) !== []): ?>
        <section class="section" aria-labelledby="archives-dossiers-titre">
            <div class="section__entete">
                <h2 id="archives-dossiers-titre">Archives et dossiers</h2>
                <a href="/documents?archives=1">Toutes les archives →</a>
            </div>
            <div class="grille-cartes">
                <?php foreach ($dossiers as $dossier): ?>
                    <article class="carte">
                        <a class="carte__lien" href="/dossiers/<?= (int) $dossier['id'] ?>">
                            <div class="carte__corps">
                                <h3 class="carte__titre"><?= e((string) $dossier['name']) ?></h3>
                                <?php if (trim((string) ($dossier['description'] ?? '')) !== ''): ?>
                                    <p class="carte__meta"><?= e(text_summary((string) $dossier['description'], 140)) ?></p>
                                <?php endif; ?>
                                <p class="carte__meta"><?= (int) $dossier['nb'] ?> document<?= (int) $dossier['nb'] > 1 ? 's' : '' ?> d’archive</p>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</section>
