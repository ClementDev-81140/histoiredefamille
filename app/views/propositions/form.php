<?php
/**
 * Formulaire d'une proposition (article, document, recette).
 *
 * Le journaliste ne choisit pas la visibilité : il peut seulement la
 * suggérer. Le contenu reste privé et invisible jusqu'à la validation.
 *
 * @var string     $type
 * @var array      $info
 * @var array|null $ligne
 * @var array      $prets, $erreurs, $old
 */
$isEdit  = $ligne !== null;
$v = static function (string $cle, mixed $defaut = '') use ($old, $ligne): mixed {
    $base = $ligne !== null && isset($ligne[$cle]) ? $ligne[$cle] : $defaut;
    return old_value($old, $cle, $base);
};
$suggestion = sanitize_visibility((string) $v('suggested_visibility', 'prive'));
$etat       = (string) ($ligne['moderation_state'] ?? '');
$action     = $isEdit ? '/propositions/' . $type . '/' . (int) $ligne['id'] : '/propositions/nouvelle/' . $type;
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/propositions">Mes propositions</a>
    <span aria-hidden="true">/</span>
    <span><?= $isEdit ? 'Modifier' : 'Proposer' ?></span>
</nav>

<h1><?= e($titre) ?></h1>

<?php if ($etat === 'refuse' && (string) ($ligne['moderation_note'] ?? '') !== ''): ?>
    <div class="message message--erreur">
        <p>Proposition refusée. Motif : <?= e((string) $ligne['moderation_note']) ?></p>
        <p>Vous pouvez la corriger et la proposer de nouveau.</p>
    </div>
<?php endif; ?>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" class="formulaire panneau" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <?php if ($type === 'article'): ?>
        <div class="champ">
            <label for="title">Titre <span class="obligatoire">*</span></label>
            <input type="text" id="title" name="title" maxlength="200" required
                   value="<?= e((string) $v('title')) ?>">
        </div>
        <div class="champ">
            <label for="category">Rubrique</label>
            <input type="text" id="category" name="category" maxlength="60"
                   value="<?= e((string) $v('category')) ?>" placeholder="Nouvelles de la famille">
        </div>
        <div class="champ">
            <label for="summary">Chapeau</label>
            <textarea id="summary" name="summary" rows="3" maxlength="500"><?= e((string) $v('summary')) ?></textarea>
        </div>
        <div class="champ">
            <label for="body">Texte de l’article</label>
            <textarea id="body" name="body" rows="16"><?= e((string) $v('body')) ?></textarea>
            <p class="aide">
                Mise en forme légère : <code>## titre</code>, <code>**gras**</code>,
                <code>*italique*</code>, listes avec <code>-</code> ou <code>1.</code>.
            </p>
        </div>

    <?php elseif ($type === 'document'): ?>
        <div class="champ">
            <label for="title">Titre <span class="obligatoire">*</span></label>
            <input type="text" id="title" name="title" maxlength="200" required
                   value="<?= e((string) $v('title')) ?>">
        </div>
        <div class="champ">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4" maxlength="2000"><?= e((string) $v('description')) ?></textarea>
        </div>
        <div class="champ">
            <label class="case">
                <input type="checkbox" name="archive" value="1"<?= checked(!empty($v('archive', 0))) ?>>
                Classer en archive
            </label>
            <p class="aide">Un document d’archive n’apparaît pas à la une et ne prévient personne. On le retrouve dans les dossiers d’archives et par la recherche. La proposition reste soumise à la validation d’un administrateur, comme les autres.</p>
        </div>

        <div class="champ champ--petit">
            <label for="doc_date">Date du document</label>
            <input type="text" id="doc_date" name="doc_date" maxlength="100"
                   value="<?= e((string) $v('doc_date')) ?>" placeholder="Vers 1952">
        </div>

        <?php if (!$isEdit): ?>
            <?php // Fichier assemblé par l'envoi par morceaux (rempli par le script). ?>
            <input type="hidden" name="envoi" value="<?= e((string) old_value($old, 'envoi', '')) ?>">

            <div class="champ">
                <label for="fichier">Fichier (photo, vidéo, son, PDF…)</label>
                <input type="file" id="fichier" name="fichier">
                <p class="aide">Jusqu’à la taille indiquée par l’administration. Un gros fichier peut aussi passer par l’envoi par morceaux ci-dessous.</p>
            </div>

            <details class="champ">
                <summary>Envoyer un gros fichier par morceaux</summary>
                <?php
                $envoiOptions = [
                    'base'      => '/propositions/fichier',
                    'lot'       => false,
                    'reprendre' => null,
                ];
                require dirname(__DIR__) . '/admin/partials/envoi.php';
                ?>
            </details>
        <?php else: ?>
            <p class="aide">Le fichier d’une proposition existante ne se remplace pas : proposez un nouveau document.</p>
        <?php endif; ?>

    <?php else: ?>
        <div class="ligne-champs">
            <div class="champ">
                <label for="title">Titre <span class="obligatoire">*</span></label>
                <input type="text" id="title" name="title" maxlength="200" required
                       value="<?= e((string) $v('title')) ?>">
            </div>
            <div class="champ">
                <label for="origin">Origine</label>
                <input type="text" id="origin" name="origin" maxlength="120"
                       value="<?= e((string) $v('origin')) ?>" placeholder="Recette de Mamie">
            </div>
        </div>
        <div class="champ champ--petit">
            <label for="servings">Quantités</label>
            <input type="text" id="servings" name="servings" maxlength="60"
                   value="<?= e((string) $v('servings')) ?>" placeholder="6 personnes">
        </div>
        <div class="champ">
            <label for="ingredients">Ingrédients <span class="obligatoire">*</span></label>
            <textarea id="ingredients" name="ingredients" rows="8" required><?= e((string) $v('ingredients')) ?></textarea>
            <p class="aide">Un ingrédient par ligne.</p>
        </div>
        <div class="champ">
            <label for="preparation">Préparation</label>
            <textarea id="preparation" name="preparation" rows="12"><?= e((string) $v('preparation')) ?></textarea>
        </div>
        <div class="champ">
            <label for="notes">Astuce et souvenir</label>
            <textarea id="notes" name="notes" rows="4"><?= e((string) $v('notes')) ?></textarea>
        </div>
    <?php endif; ?>

    <fieldset class="champ">
        <legend>Qui devrait pouvoir la voir, selon vous ?</legend>
        <p class="aide">
            Une suggestion : c’est l’administrateur qui décide au moment de valider.
            En attendant, votre proposition n’est visible que de vous et des administrateurs.
        </p>
        <?php foreach (visibility_levels() as $valeur => $libelle): ?>
            <label class="radio">
                <input type="radio" name="suggested_visibility" value="<?= e($valeur) ?>"
                       <?= checked($suggestion === $valeur) ?>>
                <?= e($libelle) ?>
            </label>
        <?php endforeach; ?>
    </fieldset>

    <p class="actions">
        <?php // id : cible du lien « Aller au bouton d’enregistrement » (envoi par morceaux). ?>
        <button type="submit" name="action" value="proposer" class="bouton" id="enregistrer">
            <?= $isEdit ? 'Enregistrer et proposer' : 'Proposer' ?>
        </button>
        <button type="submit" name="action" value="brouillon" class="bouton bouton--secondaire">
            Garder en brouillon
        </button>
        <a class="bouton bouton--secondaire" href="/propositions">Annuler</a>
    </p>
</form>
