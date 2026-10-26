<?php
/**
 * État du site (diagnostic).
 *
 * @var array  $checks, $ini, $logTail
 * @var array|null $backup
 * @var string $logFile
 * @var int    $dbSize, $walSize, $streams
 * @var int|null $free
 * @var string $php
 */
?>
<?php $aDecocher = array_values(array_filter($checks, static fn ($c) => !$c['ok'])); ?>
<h1>État du site</h1>

<?php if ($aDecocher === []): ?>
    <div class="message message--succes">Tous les contrôles essentiels sont au vert.</div>
<?php else: ?>
    <div class="message message--erreur">
        <?= count($aDecocher) ?> point(s) à corriger :
        <?= e(implode(', ', array_map(static fn ($c) => $c['label'], $aDecocher))) ?>.
    </div>
<?php endif; ?>

<section class="panneau">
    <h2>Contrôles</h2>
    <table class="tableau">
        <thead>
            <tr><th scope="col">Élément</th><th scope="col">Valeur</th><th scope="col">État</th></tr>
        </thead>
        <tbody>
            <?php foreach ($checks as $check): ?>
                <tr>
                    <td><?= e($check['label']) ?></td>
                    <td><code><?= e($check['value']) ?></code></td>
                    <td><?= $check['ok'] ? 'OK' : 'À VÉRIFIER' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<div class="deux-colonnes">
    <section class="panneau">
        <h2>Serveur</h2>
        <dl class="fiche">
            <dt>PHP</dt><dd><?= e($php) ?></dd>
            <dt>upload_max_filesize</dt><dd><?= e($ini['upload_max_filesize']) ?></dd>
            <dt>post_max_size</dt><dd><?= e($ini['post_max_size']) ?></dd>
            <dt>memory_limit</dt><dd><?= e($ini['memory_limit']) ?></dd>
            <dt>max_execution_time</dt><dd><?= e($ini['max_execution_time']) ?></dd>
            <dt>output_buffering</dt><dd><?= e($ini['output_buffering']) ?></dd>
            <dt>Taille maximale d’envoi (site)</dt><dd><?= e(format_size(upload_max_bytes())) ?></dd>
        </dl>
    </section>

    <section class="panneau">
        <h2>Stockage et base</h2>
        <dl class="fiche">
            <dt>Base de données</dt><dd><?= e(format_size($dbSize)) ?></dd>
            <dt>Journal WAL</dt><dd><?= e(format_size($walSize)) ?></dd>
            <dt>Espace libre</dt><dd><?= $free !== null ? e(format_size($free)) : 'inconnu' ?></dd>
            <dt>Lectures vidéo en cours</dt>
            <dd><?= (int) $streams ?> / <?= (int) config('video.max_concurrent_streams', 2) ?></dd>
            <dt>Limite d’envoi navigateur</dt><dd><?= (int) config('uploads.max_mb', 50) ?> Mo</dd>
            <dt>Photos max (mégapixels)</dt>
            <dd><?= (int) ((int) config('uploads.max_image_pixels', 24000000) / 1000000) ?> Mpx</dd>
        </dl>
    </section>
</div>

<section class="panneau">
    <h2>Dernière sauvegarde</h2>
    <?php if ($backup === null): ?>
        <p>Aucune information de sauvegarde. Vérifiez que <code>deploy/backup.sh</code> est installé et exécuté par cron.</p>
    <?php else: ?>
        <dl class="fiche">
            <dt>Date</dt><dd><?= e((string) ($backup['date_humain'] ?? format_date((int) ($backup['date'] ?? 0), true))) ?></dd>
            <dt>Résultat</dt><dd><?= e((string) ($backup['status'] ?? 'inconnu')) ?></dd>
            <dt>Base copiée</dt><dd><?= !empty($backup['database']) ? 'oui' : 'non' ?></dd>
            <dt>Fichiers synchronisés</dt><dd><?= !empty($backup['files']) ? 'oui' : 'non' ?></dd>
            <dt>Message</dt><dd><?= e((string) ($backup['message'] ?? '')) ?></dd>
        </dl>
        <p class="note">
            Test de restauration : voir la section « Sauvegarde » du README.
        </p>
    <?php endif; ?>

    <h3>Journal de sauvegarde (<?= e($logFile) ?>)</h3>
    <?php if ($logTail === []): ?>
        <p class="discret">Journal illisible ou vide (droits d’accès ?).</p>
    <?php else: ?>
        <pre class="journal-technique"><?= e(implode("\n", $logTail)) ?></pre>
    <?php endif; ?>
</section>
