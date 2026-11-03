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
 * @var array|null $alerte
 * @var array  $sauvegarde (dossier, libre_cle, support, rotation)
 */
?>
<?php $aDecocher = array_values(array_filter($checks, static fn ($c) => !$c['ok'])); ?>
<h1>État du site</h1>

<?php if ($alerte !== null): ?>
    <div class="message message--erreur" role="alert">
        <strong>Sauvegarde :</strong> <?= e($alerte['message']) ?>
        <?php if (count($alerte['details']) > 1): ?>
            <ul>
                <?php foreach (array_slice($alerte['details'], 1) as $detail): ?>
                    <li><?= e($detail) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>

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

<section class="panneau" id="sauvegarde">
    <h2>Sauvegarde</h2>

    <?php if ($backup === null): ?>
        <p>
            Aucune sauvegarde n’est encore enregistrée. Sur le Raspberry Pi, lancez une première fois :
        </p>
        <pre class="journal-technique">sudo -u www-data php /var/www/histoiredefamille/app/cli/sauvegarde.php</pre>
    <?php else: ?>
        <dl class="fiche">
            <dt>Dernière sauvegarde</dt>
            <dd><?= e((string) ($backup['date_humain'] ?? format_date((int) ($backup['date'] ?? 0), true))) ?></dd>
            <dt>Résultat</dt>
            <dd><?= ($backup['status'] ?? '') === 'ok' ? 'réussie' : 'en échec' ?></dd>
            <dt>Base copiée</dt>
            <dd>
                <?php if (!empty($backup['database'])): ?>
                    oui, <?= e(format_size((int) ($backup['base_size'] ?? 0))) ?> (intégrité vérifiée)
                <?php else: ?>
                    non
                <?php endif; ?>
            </dd>
            <dt>Fichiers</dt>
            <dd>
                <?= (int) ($backup['files_copied'] ?? 0) ?> copié(s),
                <?= (int) ($backup['files_deleted'] ?? 0) ?> retiré(s),
                <?= (int) ($backup['files_errors'] ?? 0) ?> erreur(s)
            </dd>
            <dt>Durée</dt><dd><?= (int) ($backup['duration'] ?? 0) ?> s</dd>
            <dt>Message</dt><dd><?= e((string) ($backup['message'] ?? '')) ?></dd>
        </dl>
    <?php endif; ?>

    <h3>Clé USB et espace disque</h3>
    <dl class="fiche">
        <dt>Dossier de sauvegarde</dt><dd><code><?= e((string) $sauvegarde['dossier']) ?></code></dd>
        <dt>Clé montée</dt>
        <dd>
            <?php if ($sauvegarde['support']['ok']): ?>
                oui (<?= e($sauvegarde['support']['montage']) ?>)
            <?php else: ?>
                non — <?= e($sauvegarde['support']['raison']) ?>
            <?php endif; ?>
        </dd>
        <dt>Espace libre sur la clé</dt>
        <dd><?= $sauvegarde['libre_cle'] !== null ? e(format_size((int) $sauvegarde['libre_cle'])) : 'inconnu (clé absente ?)' ?></dd>
        <dt>Espace libre sur les données</dt>
        <dd><?= $free !== null ? e(format_size($free)) : 'inconnu' ?></dd>
        <dt>Copies conservées</dt>
        <dd>
            <?= (int) $sauvegarde['rotation']['quotidiennes'] ?> quotidienne(s) (7 au maximum),
            <?= (int) $sauvegarde['rotation']['hebdomadaires'] ?> hebdomadaire(s) (4),
            <?= (int) $sauvegarde['rotation']['mensuelles'] ?> mensuelle(s) (6)
        </dd>
    </dl>

    <p class="note">
        Le fichier <code>config.local.php</code> n’est jamais copié avec les données :
        gardez-en une copie à part (voir le README, section « Sauvegarde et restauration »).
        La clé USB peut être chiffrée : avantages et risques dans
        <code>docs/chiffrement-cle-usb.md</code>.
    </p>

    <h3>Journal de sauvegarde (<?= e($logFile) ?>)</h3>
    <?php if ($logTail === []): ?>
        <p class="discret">Journal illisible ou vide (droits d’accès ?).</p>
    <?php else: ?>
        <pre class="journal-technique"><?= e(implode("\n", $logTail)) ?></pre>
    <?php endif; ?>
</section>
