<?php
/**
 * Formulaire d'article.
 *
 * @var array|null $article
 * @var array      $branches, $articleBranches, $photos, $categories, $erreurs, $old
 */
$isEdit = $article !== null;
$v = static function (string $key, mixed $default = '') use ($old, $article, $isEdit) {
    $base = $isEdit && isset($article[$key]) ? $article[$key] : $default;
    return old_value($old, $key, $base);
};
$selectedBranches = array_map('intval', (array) old_value($old, 'branches', $articleBranches));
$visibility = (string) $v('visibility', 'prive');
$status     = (string) $v('status', 'brouillon');
$imageId    = (int) $v('image_id', 0);

// Date de publication au format « datetime-local ».
$ts = $isEdit ? (int) $article['published_at'] : time();
$publishedAt = (string) old_value($old, 'published_at', date('Y-m-d\TH:i', $ts));
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/admin/articles">Articles</a>
    <span aria-hidden="true">/</span>
    <span><?= $isEdit ? 'Modifier' : 'Nouvel article' ?></span>
</nav>

<h1><?= e($titre) ?></h1>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= $isEdit ? '/admin/articles/' . (int) $article['id'] : '/admin/articles/nouveau' ?>"
      class="formulaire panneau">
    <?= csrf_field() ?>

    <div class="ligne-champs">
        <div class="champ">
            <label for="title">Titre <span class="obligatoire">*</span></label>
            <input type="text" id="title" name="title" maxlength="200" required
                   value="<?= e((string) $v('title')) ?>">
        </div>
        <div class="champ">
            <label for="slug">Adresse de l’article</label>
            <input type="text" id="slug" name="slug" maxlength="80" pattern="[a-z0-9][a-z0-9-]*"
                   value="<?= e((string) $v('slug')) ?>" placeholder="noel-1985">
            <p class="aide">Laissez vide pour la générer depuis le titre. URL : <code>/articles/…</code></p>
        </div>
    </div>

    <div class="ligne-champs">
        <div class="champ">
            <label for="category">Rubrique</label>
            <input type="text" id="category" name="category" maxlength="60" list="rubriques"
                   value="<?= e((string) $v('category')) ?>" placeholder="Souvenirs, Nouvelles, Recettes…">
            <datalist id="rubriques">
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e((string) $cat['category']) ?>">
                <?php endforeach; ?>
            </datalist>
        </div>
        <div class="champ">
            <label for="modele">Modèle de mise en page</label>
            <?php $modeleChoisi = (string) $v('modele', ''); ?>
            <select id="modele" name="modele">
                <?php foreach (article_modeles() as $cleModele => $libelleModele): ?>
                    <option value="<?= e($cleModele) ?>"<?= selected($modeleChoisi === $cleModele) ?>>
                        <?= e($libelleModele) ?><?= $cleModele === '' ? ' (' . e(article_modeles()[article_modele_defaut()]) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="aide">
                « Journal » : la mise en page d’aujourd’hui. « Simple » : une seule colonne,
                sans ornement. « Grande photo » : la photo en pleine largeur, puis le texte.
                « Comme le site » suit le réglage de « Composition des pages ».
            </p>
        </div>
    </div>

    <div class="ligne-champs">
        <div class="champ">
            <label for="image_id">Image à la une</label>
            <select id="image_id" name="image_id">
                <option value="0">Aucune</option>
                <?php foreach ($photos as $photo): ?>
                    <option value="<?= (int) $photo['id'] ?>"<?= selected($imageId === (int) $photo['id']) ?>>
                        <?= e($photo['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="aide">Une photo déjà ajoutée aux documents. Elle doit être au moins aussi visible que l’article.</p>
        </div>

    <div class="champ">
        <label for="summary">Résumé (chapô)</label>
        <textarea id="summary" name="summary" rows="3" maxlength="500"><?= e((string) $v('summary')) ?></textarea>
        <p class="aide">Une ou deux phrases affichées sous le titre et dans les listes. 500 caractères maximum.</p>
    </div>

    <div class="champ">
        <label for="body">Texte</label>
        <textarea id="body" name="body" rows="20"><?= e((string) $v('body')) ?></textarea>
        <p class="aide">
            Mise en forme légère : <code>## titre</code>, <code>**gras**</code>,
            <code>*italique*</code>, listes avec <code>-</code> ou <code>1.</code>,
            liens <code>[texte](https://…)</code>. Le HTML brut n’est pas interprété.
        </p>
    </div>

    <fieldset class="champ">
        <legend>Visibilité</legend>
        <?php foreach (visibility_levels() as $value => $label): ?>
            <label class="radio">
                <input type="radio" name="visibility" value="<?= e($value) ?>"
                       data-visibilite="<?= e($value) ?>"<?= checked($visibility === $value) ?>>
                <?= e($label) ?>
            </label>
        <?php endforeach; ?>

        <div class="branches" data-bloc-branches>
            <?php foreach ($branches as $branch): ?>
                <label class="case">
                    <input type="checkbox" name="branches[]" value="<?= (int) $branch['id'] ?>"
                        <?= checked(in_array((int) $branch['id'], $selectedBranches, true)) ?>>
                    <?= e($branch['name']) ?> <span class="discret">(<?= e($branch['code']) ?>)</span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <fieldset class="champ">
        <legend>Publication</legend>
        <label class="radio">
            <input type="radio" name="status" value="brouillon"<?= checked($status !== 'publie') ?>>
            Brouillon (visible seulement par les administrateurs)
        </label>
        <label class="radio">
            <input type="radio" name="status" value="publie"<?= checked($status === 'publie') ?>>
            Publié à la date ci-dessous
        </label>

        <div class="champ champ--petit">
            <label for="published_at">Date de publication</label>
            <input type="datetime-local" id="published_at" name="published_at" value="<?= e($publishedAt) ?>">
            <p class="aide">Une date dans le futur programme la publication : l’article apparaîtra tout seul.</p>
        </div>

        <?php
        // La case n'apparaît que pour un article publié (un brouillon ne
        // prévient personne). À la première publication, elle est cochée.
        $prevenir = array_key_exists('notify', $old)
            ? (string) old_value($old, 'notify', '1') === '1'
            : (int) ($article['notify'] ?? 1) === 1;
        ?>
        <?php if (!site_courriels_actifs()): ?>
            <?php /* 1.28.0 : le site n'envoie aucun courriel ; la case n'a plus
                     d'objet, et l'article paraît sans prévenir personne. */ ?>
            <p class="aide">
                Les courriels sont désactivés : l’article paraîtra dans le journal, sans
                notification. Il apparaît dans <strong>Nouveautés</strong> et dans la gazette
                du mois, que chacun lit en ligne.
            </p>
        <?php elseif ($status === 'publie'): ?>
            <label class="case">
                <input type="checkbox" name="notify" value="1" <?= checked($prevenir) ?>>
                Prévenir les inscrits par courriel
            </label>
            <p class="aide">
                Un seul message par personne et par article, uniquement aux personnes qui ont
                coché « Prévenez-moi des nouveaux articles » et qui ont le droit de lire cet
                article. Si la date est dans le futur, le message part le jour de la publication.
            </p>
        <?php else: ?>
            <p class="aide">
                Un brouillon ne prévient personne. En publiant l’article, une case
                « Prévenir les inscrits » apparaîtra.
            </p>
        <?php endif; ?>
    </fieldset>

    <?php
    // Thèmes du type « article » (1.32.0) : plusieurs choix possibles.
    $categoriesCases = categories_list('article');
    $kindCategorie   = 'Articles';
    require __DIR__ . '/partials/categories_cases.php';
    ?>

    <p class="actions">
        <button type="submit" class="bouton"><?= $isEdit ? 'Enregistrer' : 'Créer l’article' ?></button>
        <a class="bouton bouton--secondaire" href="/admin/articles">Annuler</a>
        <?php if ($isEdit): ?>
            <a class="bouton bouton--secondaire" href="/articles/<?= e($article['slug']) ?>">Voir l’article</a>
        <?php endif; ?>
    </p>
</form>
