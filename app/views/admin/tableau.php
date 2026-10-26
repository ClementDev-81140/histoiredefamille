<?php
/**
 * Tableau de bord d'administration.
 *
 * @var array $stats, $documents, $activity
 */
$backup = $stats['last_backup'];
$backupDays = $backup && !empty($backup['date']) ? days_since((int) $backup['date']) : null;
?>
<div class="section__entete">
    <h1>Tableau de bord</h1>
    <span class="actions">
        <a class="bouton" href="/admin/documents/nouveau">Ajouter un document</a>
        <a class="bouton bouton--secondaire" href="/admin/pages/nouvelle">Nouvelle page</a>
    </span>
</div>

<?php if ($backup === null): ?>
    <div class="message message--info">
        Aucune sauvegarde enregistrée pour l’instant.
        Mettez en place la tâche quotidienne décrite dans le README
        (<code>deploy/backup.sh</code>) et vérifiez la page
        <a href="/admin/etat">État du site</a>.
    </div>
<?php elseif (($backup['status'] ?? '') !== 'ok'): ?>
    <div class="message message--erreur">
        La dernière sauvegarde a échoué (<?= e((string) ($backup['date_humain'] ?? '')) ?>).
        Consultez le journal : <code><?= e((string) config('maintenance.backup_log')) ?></code>.
    </div>
<?php elseif ($backupDays !== null && $backupDays > 2): ?>
    <div class="message message--erreur">
        La dernière sauvegarde réussie date de <?= (int) $backupDays ?> jours.
        Vérifiez que la clé USB est branchée et que la tâche cron tourne.
    </div>
<?php endif; ?>

<div class="deux-colonnes">
    <section class="panneau panneau--public">
        <div class="section__entete">
            <h2>Partie publique</h2>
            <span class="badge badge-public">visible sans compte</span>
        </div>
        <p class="note">Contenus visibles par n’importe quel visiteur.</p>

        <ul class="chiffres">
            <li><strong><?= (int) $stats['public_documents'] ?></strong> document(s) public(s)</li>
            <li><strong><?= (int) $stats['public_pages'] ?></strong> page(s) publiée(s)</li>
            <li><strong><?= (int) $stats['public_persons'] ?></strong> personne(s) publique(s)</li>
        </ul>

        <p class="actions">
            <a class="bouton bouton--secondaire" href="/">Voir le site public</a>
            <a class="bouton bouton--secondaire" href="/admin/pages">Pages d’informations</a>
            <a class="bouton bouton--secondaire" href="/admin/documents?visibilite=public">Documents publics</a>
        </p>
        <p class="note">
            Rappel : un contenu sans niveau de visibilité choisi reste
            <strong>privé</strong> (visible des seuls administrateurs).
        </p>
    </section>

    <section class="panneau panneau--famille">
        <div class="section__entete">
            <h2>Partie famille</h2>
            <span class="badge badge-famille">comptes de branche</span>
        </div>
        <p class="note">Contenus réservés aux comptes de famille connectés.</p>

        <ul class="chiffres">
            <li><strong><?= (int) $stats['branches_active'] ?></strong> branche(s) active(s) sur <?= (int) $stats['branches'] ?></li>
            <li><strong><?= (int) $stats['family_documents'] ?></strong> document(s) famille/branches</li>
            <li><strong><?= (int) $stats['private_documents'] ?></strong> document(s) privé(s) (admin)</li>
            <li><strong><?= (int) $stats['persons'] ?></strong> personne(s) — <?= (int) $stats['families'] ?> couple(s)</li>
        </ul>

        <p class="actions">
            <a class="bouton bouton--secondaire" href="/admin/branches">Branches &amp; comptes</a>
            <a class="bouton bouton--secondaire" href="/admin/arbre">Arbre généalogique</a>
            <a class="bouton bouton--secondaire" href="/admin/importer">
                Dossier arrivée<?= (int) $stats['incoming'] > 0 ? ' (' . (int) $stats['incoming'] . ')' : '' ?>
            </a>
        </p>
        <p class="note">
            <?= (int) $stats['admins'] ?> compte(s) administrateur actif(s) — chacun est personnel.
        </p>
    </section>
</div>

<section class="panneau">
    <h2>Rappel pour les vidéos</h2>
    <p>
        Les films doivent être <strong>déjà encodés en MP4 (H.264 + AAC)</strong> : le
        Raspberry Pi ne convertit rien. Déposez-les dans le
        <a href="/admin/importer">dossier arrivée</a> (SFTP/Samba) puis importez-les
        depuis l’administration.
    </p>
</section>

<section class="section">
    <div class="section__entete">
        <h2>Derniers documents ajoutés</h2>
        <a href="/admin/documents">Tous les documents →</a>
    </div>

    <?php if ($documents === []): ?>
        <p class="discret">Aucun document pour le moment.</p>
    <?php else: ?>
        <table class="tableau">
            <thead>
                <tr>
                    <th scope="col">Titre</th>
                    <th scope="col">Type</th>
                    <th scope="col">Visibilité</th>
                    <th scope="col">Ajouté le</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $doc): ?>
                    <tr>
                        <td><?= e($doc['title']) ?></td>
                        <td><?= e(document_kind_label((string) $doc['kind'], (string) $doc['ext'])) ?></td>
                        <td><span class="<?= e(visibility_class((string) $doc['visibility'])) ?>">
                            <?= e(visibility_short((string) $doc['visibility'])) ?></span></td>
                        <td><?= e(format_date((int) $doc['created_at'])) ?></td>
                        <td class="colonne-actions">
                            <a href="/documents/<?= (int) $doc['id'] ?>">Voir</a>
                            <a href="/admin/documents/<?= (int) $doc['id'] ?>/modifier">Modifier</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="section">
    <h2>Espace disque et journal</h2>
    <p>
        Stockage : <code><?= e((string) config('storage.root')) ?></code> —
        <?= $stats['storage_free'] !== null ? e(format_size((int) $stats['storage_free'])) . ' libres' : 'espace inconnu' ?>
    </p>

    <?php if ($activity === []): ?>
        <p class="discret">Aucune action enregistrée.</p>
    <?php else: ?>
        <ul class="journal">
            <?php foreach ($activity as $entry): ?>
                <li>
                    <span class="discret"><?= e(format_date((int) $entry['created_at'], true)) ?></span>
                    <strong><?= e((string) ($entry['username'] ?? 'système')) ?></strong>
                    <?= e((string) $entry['action']) ?>
                    <?php if (trim((string) $entry['details']) !== ''): ?>
                        — <?= e((string) $entry['details']) ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
