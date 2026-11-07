<?php
/**
 * Formulaire d'ajout / modification d'un document.
 *
 * @var array|null $doc
 * @var array      $categories, $branches, $docBranches, $erreurs, $old
 * @var string     $tags
 * @var int        $maxBytes
 */
$isEdit = $doc !== null;
$v = static function (string $key, mixed $default = '') use ($old, $doc, $isEdit) {
    $base = $isEdit && isset($doc[$key]) ? $doc[$key] : $default;
    return old_value($old, $key, $base);
};
$selectedBranches = array_map('intval', (array) old_value($old, 'branches', $docBranches));
$visibility = (string) $v('visibility', 'prive');
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/admin/documents">Documents</a>
    <span aria-hidden="true">/</span>
    <span><?= $isEdit ? 'Modifier' : 'Nouveau' ?></span>
</nav>

<h1><?= e($titre) ?></h1>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul>
            <?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!$isEdit): ?>
    <p class="note">
        <?= e(format_size($maxBytes)) ?> au plus par le navigateur ; au-delà,
        <a href="/admin/importer">dossier arrivée</a>.
    </p>
<?php endif; ?>

<form method="post" enctype="multipart/form-data"
      action="<?= $isEdit ? '/admin/documents/' . (int) $doc['id'] . '/modifier' : '/admin/documents/nouveau' ?>"
      class="formulaire panneau">
    <?= csrf_field() ?>

    <div class="champ">
        <label for="title">Titre <span class="obligatoire">*</span></label>
        <input type="text" id="title" name="title" maxlength="200" required value="<?= e((string) $v('title')) ?>">
    </div>

    <div class="champ">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="5"><?= e((string) $v('description')) ?></textarea>
        <p class="aide">
            Texte libre. Mise en forme légère : <code>**gras**</code>, <code>*italique*</code>,
            listes avec <code>-</code>, liens <code>[texte](https://…)</code>.
        </p>
    </div>

    <div class="ligne-champs">
        <div class="champ">
            <label for="doc_date">Date du document</label>
            <input type="text" id="doc_date" name="doc_date" maxlength="100"
                   placeholder="ex. 1912, vers 1930, 12/06/1954" value="<?= e((string) $v('doc_date')) ?>">
        </div>

    </div>

    <?php
    // Catégories du type de contenu (1.32.0) : plusieurs choix possibles.
    $kindCategorie = match ((string) ($doc['kind'] ?? '')) {
        'photo' => 'photo',
        'video' => 'video',
        default => 'document',
    };
    $categoriesCases = categories_pour_kinds([$kindCategorie, 'document', 'photo', 'video']);
    $categorieIds    = $categorieIds ?? [];
    require __DIR__ . '/partials/categories_cases.php';
    ?>

    <?php
    // Aspect « photo ancienne » (1.32.0) : filtre d'affichage seulement, le
    // fichier n'est jamais modifié. Chaque photo peut s'écarter du réglage.
    $aspectChoisi = (string) $v('vintage_aspect', 'suivre');
    if (!array_key_exists($aspectChoisi, APPARENCE_ASPECT_CHOIX)) {
        $aspectChoisi = 'suivre';
    }
    ?>
    <div class="champ">
        <label for="vintage_aspect">Aspect « photo ancienne »</label>
        <select id="vintage_aspect" name="vintage_aspect">
            <?php foreach (APPARENCE_ASPECT_CHOIX as $valeur => $libelle): ?>
                <option value="<?= e($valeur) ?>"<?= selected($aspectChoisi === $valeur) ?>><?= e($libelle) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="aide">
            Le réglage du site se trouve dans <a href="/admin/apparence#aspect-photo">Apparence du journal</a>.
            Ce choix ne concerne que l'affichage : le fichier d'origine reste intact, et le
            téléchargement comme l'impression donnent la photo en couleurs.
        </p>
    </div>

    <div class="champ">
        <label for="tags">Étiquettes</label>
        <input type="text" id="tags" name="tags" maxlength="400"
               placeholder="mariage, village, 1914" value="<?= e((string) old_value($old, 'tags', $tags)) ?>">
        <p class="aide">Séparez les étiquettes par des virgules.</p>
    </div>

    <fieldset class="champ">
        <legend>Visibilité <span class="obligatoire">*</span></legend>
        <p class="aide">
            Par défaut, un contenu est <strong>privé</strong> (administrateurs uniquement).
            Choisissez explicitement qui peut le voir.
        </p>

        <?php foreach (visibility_levels() as $value => $label): ?>
            <label class="radio">
                <input type="radio" name="visibility" value="<?= e($value) ?>"
                       data-visibilite="<?= e($value) ?>"<?= checked($visibility === $value) ?>>
                <?= e($label) ?>
            </label>
        <?php endforeach; ?>

        <div class="branches" data-bloc-branches>
            <?php if ($branches === []): ?>
                <p class="note">Aucune branche n’existe encore. Créez-en une dans « Branches &amp; comptes ».</p>
            <?php else: ?>
                <?php foreach ($branches as $branch): ?>
                    <label class="case">
                        <input type="checkbox" name="branches[]" value="<?= (int) $branch['id'] ?>"
                            <?= checked(in_array((int) $branch['id'], $selectedBranches, true)) ?>>
                        <?= e($branch['name']) ?>
                        <span class="discret">(<?= e($branch['code']) ?>)</span>
                        <?php if ((int) $branch['see_all'] === 1): ?>
                            <span class="badge badge-famille">voit tout</span>
                        <?php endif; ?>
                        <?php if ((int) $branch['is_active'] !== 1): ?>
                            <span class="badge badge-prive">inactive</span>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </fieldset>


    <?php
    // Même composant que la page « Envoyer des fichiers » : un gros film passe
    // par morceaux. Le fichier rejoint ce formulaire une fois assemblé.
    $envoiOptions = [
        'creer' => false,
        'titre' => 'Fichier volumineux (envoi par morceaux)',
        'aide'  => 'Facultatif : pour remplacer le champ « Fichier » quand le film dépasse la limite de l’envoi simple.',
    ];
    require __DIR__ . '/partials/envoi.php';
    ?>
    <?php // Fichier déjà assemblé par l'envoi par morceaux : le script le remplit,
           // et il survit à une erreur de formulaire (titre vide, par exemple). ?>
    <input type="hidden" name="envoi" value="<?= e((string) old_value($old, 'envoi', '')) ?>">

    <div class="champ">
        <label for="fichier"><?= $isEdit ? 'Remplacer le fichier' : 'Fichier' ?><?= $isEdit ? '' : ' *' ?></label>
        <input type="file" id="fichier" name="fichier" <?= $isEdit ? '' : 'required' ?>
               accept="<?= e('.' . implode(',.', allowed_extensions())) ?>">
        <?php if ($isEdit): ?>
            <p class="aide">
                Fichier actuel : <?= e((string) $doc['original_name']) ?>
                (<?= e(format_size((int) $doc['size'])) ?>).
                Laissez vide pour le conserver.
            </p>
        <?php else: ?>
            <p class="aide">
                Formats acceptés : <?= e(implode(', ', allowed_extensions())) ?>.
                Une vidéo déjà en MP4 (H.264 + AAC) est publiée telle quelle ; dans un
                autre format (.mov, .avi, .mkv…), elle est convertie automatiquement
                après l’envoi et reste invisible jusque-là.
            </p>
        <?php endif; ?>
    </div>

    <?php if ($isEdit && (string) ($doc['kind'] ?? '') === 'video'): ?>
        <div class="champ">
            <label for="couverture">Image de couverture</label>
            <input type="file" id="couverture" name="couverture" accept=".jpg,.jpeg,.png,.webp">
            <p class="aide">
                Facultatif. Une image extraite de la vidéo est créée automatiquement (à 1 seconde,
                ou à 10 % de la durée pour un film très court). Envoyer une image ici la remplace :
                c'est elle que verront les listes et le lecteur.
                <?php if (trim((string) ($doc['thumb_path'] ?? '')) !== ''): ?>
                    Une image d'aperçu existe déjà : la nouvelle la remplacera.
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <p class="actions">
        <?php // id : cible du lien « Aller au bouton d’enregistrement » (envoi par morceaux). ?>
        <button type="submit" class="bouton" id="enregistrer"><?= $isEdit ? 'Enregistrer' : 'Envoyer le document' ?></button>
        <a class="bouton bouton--secondaire" href="<?= $isEdit ? '/documents/' . (int) $doc['id'] : '/admin/documents' ?>">Annuler</a>
    </p>
</form>
