<?php
/**
 * Compléter tout un lot d'un coup, puis ajuster fichier par fichier.
 *
 * @var array $documents, $categories, $albums, $branches, $erreurs, $old
 */
$val = static function (string $cle, mixed $defaut = '') use ($old): mixed {
    return old_value($old, $cle, $defaut);
};
?>
<div class="section__entete">
    <h1>Compléter un lot</h1>
    <a class="bouton bouton--secondaire" href="/admin/envoyer">Envoyer d’autres fichiers</a>
</div>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul>
            <?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($documents === []): ?>
    <div class="panneau panneau--centre">
        <p>Aucun fichier reçu récemment à compléter.</p>
        <p><a class="bouton" href="/admin/envoyer">Envoyer des fichiers</a></p>
    </div>
<?php else: ?>
    <p class="note">
        Tant que vous n’avez pas validé, tout reste <strong>privé</strong>.
    </p>

    <form method="post" action="/admin/envoyer/lot" class="formulaire panneau">
        <?= csrf_field() ?>

        <h2>Pour tout le lot</h2>

        <div class="ligne-champs">
            <div class="champ">
                <label for="kind">Type de contenu</label>
                <select id="kind" name="kind">
                    <option value="">Comme chaque fichier (recommandé)</option>
                    <option value="photo"<?= selected((string) $val('kind') === 'photo') ?>>Photo</option>
                    <option value="video"<?= selected((string) $val('kind') === 'video') ?>>Vidéo</option>
                    <option value="document"<?= selected((string) $val('kind') === 'document') ?>>Document</option>
                </select>
            </div>

            <?php
            // Thèmes appliquées à TOUT le lot (1.32.0) : plusieurs choix,
            // et chaque fichier ne reçoit que celles de son type.
            $categoriesCases = categories_pour_kinds(['document', 'photo', 'video']);
            $categorieIds    = array_map('intval', post_array('categories'));
            ?>
            <fieldset class="champ">
                <legend>Thèmes du lot</legend>
                <p class="aide">
                    Elles s'appliquent à tous les fichiers cochés ci-dessous. Chaque fichier ne
                    garde que les thèmes prévus pour son type.
                </p>
                <?php foreach ($categoriesCases as $categorie): ?>
                    <label class="case">
                        <input type="checkbox" name="categories[]" value="<?= (int) $categorie['id'] ?>"
                            <?= checked(in_array((int) $categorie['id'], $categorieIds, true)) ?>>
                        <?= e((string) $categorie['name']) ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <div class="champ">
                <label for="album_id">Ajouter à un album</label>
                <select id="album_id" name="album_id">
                    <option value="0">Aucun album</option>
                    <?php foreach ($albums as $album): ?>
                        <option value="<?= (int) $album['id'] ?>"
                            <?= selected((string) $val('album_id') === (string) $album['id']) ?>>
                            <?= e((string) $album['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <fieldset class="champ">
            <legend>Visibilité du lot</legend>
            <?php $visChoisie = (string) $val('visibility', 'prive'); ?>
            <?php foreach (visibility_levels() as $valeur => $libelle): ?>
                <label class="radio">
                    <input type="radio" name="visibility" value="<?= e($valeur) ?>"
                        <?= checked($visChoisie === $valeur) ?>>
                    <?= e($libelle) ?>
                </label>
            <?php endforeach; ?>
            <p class="aide">
                Par défaut « Privé » : personne d’autre que les administrateurs ne voit
                ces fichiers. Vous pourrez changer la visibilité plus tard.
            </p>

            <div class="branches" data-bloc-branches hidden>
                <p class="aide">Cochez les branches autorisées.</p>
                <?php $branchesChoisies = array_map('intval', (array) $val('branches', [])); ?>
                <?php foreach ($branches as $branch): ?>
                    <label class="case">
                        <input type="checkbox" name="branches[]" value="<?= (int) $branch['id'] ?>"
                            <?= checked(in_array((int) $branch['id'], $branchesChoisies, true)) ?>>
                        <?= e((string) $branch['name']) ?>
                        <span class="discret">(<?= e((string) $branch['code']) ?>)</span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <h2>Fichier par fichier</h2>

        <div class="tableau-defilant">
            <table class="tableau tableau--lot">
                <thead>
                    <tr>
                        <th scope="col">Garder</th>
                        <th scope="col">Fichier</th>
                        <th scope="col">Titre</th>
                        <th scope="col">Date</th>
                        <th scope="col">Visibilité</th>
                        <th scope="col">Personnes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $document): ?>
                        <?php $id = (int) $document['id']; ?>
                        <tr>
                            <td>
                                <label class="case">
                                    <input type="checkbox" name="documents[]" value="<?= $id ?>" checked>
                                    <span class="sr-only">Garder ce fichier</span>
                                </label>
                            </td>
                            <td>
                                <?= e((string) $document['original_name']) ?>
                                <span class="discret discret--bloc">
                                    <?= e(format_size((int) $document['size'])) ?> ·
                                    <?= e(ucfirst((string) $document['kind'])) ?>
                                </span>
                            </td>
                            <td>
                                <label class="sr-only" for="titre-<?= $id ?>">Titre</label>
                                <input type="text" id="titre-<?= $id ?>" name="titre_<?= $id ?>"
                                       maxlength="200" value="<?= e((string) $document['title']) ?>">
                            </td>
                            <td>
                                <label class="sr-only" for="date-<?= $id ?>">Date</label>
                                <input type="text" id="date-<?= $id ?>" name="date_<?= $id ?>"
                                       maxlength="100" placeholder="ex. 1954"
                                       value="<?= e((string) ($document['doc_date'] ?? '')) ?>">
                            </td>
                            <td>
                                <label class="sr-only" for="vis-<?= $id ?>">Visibilité</label>
                                <select id="vis-<?= $id ?>" name="visibilite_<?= $id ?>">
                                    <option value="">Comme le lot</option>
                                    <?php foreach (visibility_levels() as $valeur => $libelle): ?>
                                        <option value="<?= e($valeur) ?>"<?= selected((string) $document['visibility'] === $valeur) ?>>
                                            <?= e(visibility_short($valeur)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <a href="/admin/documents/<?= $id ?>/personnes">Identifier</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="actions">
            <button type="submit" class="bouton">Enregistrer le lot</button>
            <a class="bouton bouton--secondaire" href="/admin/envois">Plus tard</a>
        </p>
    </form>
<?php endif; ?>
