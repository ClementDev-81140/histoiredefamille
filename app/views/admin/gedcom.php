<?php
/**
 * Import GEDCOM : envoi, aperçu, confirmation.
 *
 * @var array|null $preview
 * @var array      $erreurs
 * @var int        $maxMb
 */
?>
<div class="section__entete">
    <h1>Importer un fichier GEDCOM</h1>
    <a class="bouton bouton--secondaire" href="/admin/arbre">Retour à l’arbre</a>
</div>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if ($preview === null): ?>
    <section class="panneau">
        <h2>1. Choisir le fichier</h2>
        <p class="note">
            Fichier exporté depuis un logiciel de généalogie (GEDCOM 5.5 / 5.5.1,
            extension <code>.ged</code>). Taille maximale : <?= (int) $maxMb ?> Mo.
            Les erreurs de format sont signalées mais n’empêchent pas l’import.
        </p>

        <form method="post" action="/admin/arbre/import" enctype="multipart/form-data" class="formulaire">
            <?= csrf_field() ?>
            <div class="champ">
                <label for="gedcom">Fichier GEDCOM</label>
                <input type="file" id="gedcom" name="gedcom" accept=".ged,.gedcom,.txt" required>
            </div>
            <p class="actions">
                <button type="submit" class="bouton">Analyser le fichier</button>
            </p>
        </form>
    </section>

    <section class="panneau">
        <h2>Bon à savoir</h2>
        <ul class="liste">
            <li>Un aperçu est toujours affiché <strong>avant</strong> l’import définitif.</li>
            <li>Les personnes importées sont visibles par « Toutes les familles » — jamais publiques.</li>
            <li>Réimporter le même fichier ne crée pas de doublons (repérage par identifiant GEDCOM).</li>
            <li>Les dates approximatives et les lieux sont conservés tels quels, en texte libre.</li>
            <li>Les notes, photos et sources du fichier GEDCOM ne sont pas importées (notes texte uniquement).</li>
        </ul>
    </section>
<?php else: ?>
    <section class="panneau">
        <h2>2. Aperçu du fichier</h2>
        <dl class="fiche">
            <dt>Fichier</dt><dd><?= e($preview['filename']) ?></dd>
            <dt>Encodage</dt><dd><?= e($preview['encoding']) ?></dd>
            <dt>Personnes</dt><dd><?= (int) $preview['persons'] ?></dd>
            <dt>Familles (couples)</dt><dd><?= (int) $preview['families'] ?></dd>
        </dl>

        <?php if (($preview['warnings'] ?? []) !== []): ?>
            <div class="message message--info">
                <p><strong>Points à vérifier :</strong></p>
                <ul>
                    <?php foreach ($preview['warnings'] as $warning): ?>
                        <li><?= e($warning) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <p class="message message--succes">Aucune anomalie détectée.</p>
        <?php endif; ?>

        <?php if (($preview['sample'] ?? []) !== []): ?>
            <h3>Premières personnes</h3>
            <ul class="liste">
                <?php foreach ($preview['sample'] as $name): ?>
                    <li><?= e($name) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="post" action="/admin/arbre/import/confirmer" class="formulaire">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($preview['token']) ?>">
            <p class="actions">
                <button type="submit" class="bouton">Importer ces données</button>
                <a class="bouton bouton--secondaire" href="/admin/arbre/import">Annuler et choisir un autre fichier</a>
            </p>
        </form>
    </section>
<?php endif; ?>
