<?php
/**
 * Administration — aperçu d'une proposition avant décision.
 *
 * L'administrateur voit exactement ce que le journaliste a déposé, puis
 * choisit : publier (avec la visibilité de son choix, privé par défaut) ou
 * refuser (motif obligatoire, lu par l'auteur).
 *
 * @var string $type
 * @var array  $info, $ligne, $branches, $echecs
 * @var string $auteur
 */
$suggeree = sanitize_visibility((string) ($ligne['suggested_visibility'] ?? ''));
$retour   = '/admin/a-valider';
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="<?= e($retour) ?>">À valider</a>
    <span aria-hidden="true">/</span>
    <span><?= e($info['label']) ?></span>
</nav>

<div class="section__entete">
    <h1><?= e(proposition_titre($ligne, $type)) ?></h1>
    <span class="<?= e(moderation_class((string) $ligne['moderation_state'])) ?>">
        <?= e(moderation_label((string) $ligne['moderation_state'])) ?>
    </span>
</div>

<?php if ($echecs !== []): ?>
    <div class="message message--erreur">
        <ul><?php foreach ($echecs as $echec): ?><li><?= e($echec) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<p class="discret">
    Proposé par <?= e($auteur) ?>
    le <?= e(format_date(proposition_date($ligne))) ?>.
    Visibilité souhaitée par l’auteur :
    <span class="<?= e(visibility_class($suggeree)) ?>"><?= e(visibility_short($suggeree)) ?></span>
    (une suggestion, pas une décision).
</p>

<article class="panneau article-corps">
    <?php if ($type === 'article'): ?>
        <?php if (trim((string) ($ligne['summary'] ?? '')) !== ''): ?>
            <p class="chapeau"><?= e((string) $ligne['summary']) ?></p>
        <?php endif; ?>
        <?= render_text((string) ($ligne['body'] ?? '')) ?>

    <?php elseif ($type === 'document'): ?>
        <?php if (in_array((string) $ligne['kind'], ['photo', 'image'], true) && (string) ($ligne['thumb_path'] ?? '') !== ''): ?>
            <p><img src="/media/miniature/<?= (int) $ligne['id'] ?>" alt="" class="apercu-image"></p>
        <?php endif; ?>
        <?php if (trim((string) ($ligne['description'] ?? '')) !== ''): ?>
            <p><?= nl2br(e((string) $ligne['description'])) ?></p>
        <?php endif; ?>
        <p class="discret">
            <?= e((string) $ligne['original_name']) ?> —
            <?= e((string) $ligne['mime']) ?>,
            <?= e(format_size((int) $ligne['size'])) ?>
        </p>
        <p><a class="bouton bouton--secondaire" href="/f/<?= (int) $ligne['id'] ?>" target="_blank" rel="noopener">Ouvrir le fichier</a></p>

    <?php else: ?>
        <?php if (trim((string) ($ligne['origin'] ?? '')) !== ''): ?>
            <p class="discret">Origine : <?= e((string) $ligne['origin']) ?></p>
        <?php endif; ?>
        <?php if (trim((string) ($ligne['servings'] ?? '')) !== ''): ?>
            <p class="discret">Quantités : <?= e((string) $ligne['servings']) ?></p>
        <?php endif; ?>
        <h2>Ingrédients</h2>
        <?= render_text((string) ($ligne['ingredients'] ?? '')) ?>
        <?php if (trim((string) ($ligne['preparation'] ?? '')) !== ''): ?>
            <h2>Préparation</h2>
            <?= render_text((string) $ligne['preparation']) ?>
        <?php endif; ?>
        <?php if (trim((string) ($ligne['notes'] ?? '')) !== ''): ?>
            <h2>Astuce et souvenir</h2>
            <p><?= nl2br(e((string) $ligne['notes'])) ?></p>
        <?php endif; ?>
    <?php endif; ?>
</article>

<div class="ligne-panneaux">
    <section class="panneau">
        <h2>Valider et publier</h2>
        <form method="post" action="/admin/a-valider/<?= e($type) ?>/<?= (int) $ligne['id'] ?>/valider" class="formulaire">
            <?= csrf_field() ?>

            <fieldset class="champ">
                <legend>Qui pourra le voir ?</legend>
                <?php foreach (visibility_levels() as $valeur => $libelle): ?>
                    <label class="radio">
                        <input type="radio" name="visibility" value="<?= e($valeur) ?>"
                               data-visibilite="<?= e($valeur) ?>"
                               <?= checked($valeur === 'prive') ?>>
                        <?= e($libelle) ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <div class="branches" data-bloc-branches hidden>
                <?php foreach ($branches as $branche): ?>
                    <label class="case">
                        <input type="checkbox" name="branches[]" value="<?= (int) $branche['id'] ?>">
                        <?= e($branche['name']) ?> <span class="discret">(<?= e($branche['code']) ?>)</span>
                    </label>
                <?php endforeach; ?>
            </div>

            <p class="actions">
                <button type="submit" class="bouton">Valider et publier</button>
            </p>
        </form>
    </section>

    <section class="panneau">
        <h2>Refuser</h2>
        <form method="post" action="/admin/a-valider/<?= e($type) ?>/<?= (int) $ligne['id'] ?>/refuser" class="formulaire">
            <?= csrf_field() ?>
            <div class="champ">
                <label for="motif">Motif du refus <span class="obligatoire">*</span></label>
                <textarea id="motif" name="motif" rows="4" maxlength="500" required></textarea>
                <p class="aide">L’auteur lit ce motif dans « Mes propositions » ; il peut ensuite corriger et reproposer.</p>
            </div>
            <p class="actions">
                <button type="submit" class="bouton bouton--danger">Refuser</button>
            </p>
        </form>
    </section>
</div>
