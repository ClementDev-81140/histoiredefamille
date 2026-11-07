<?php
/**
 * Mes envois (et supervision, en lecture seule).
 *
 * @var array $envois, $documents, $reglages, $erreurs
 * @var bool  $tous
 * @var int   $compte
 */
?>
<div class="section__entete">
    <h1><?= $tous ? 'Tous les envois' : 'Mes envois' ?></h1>
    <span class="actions">
        <a class="bouton" href="/admin/envoyer">Envoyer des fichiers</a>
        <?php if ($tous): ?>
            <a class="bouton bouton--secondaire" href="/admin/envois">Mes envois</a>
        <?php else: ?>
            <a class="bouton bouton--secondaire" href="/admin/envois?tous=1">Tous les envois</a>
        <?php endif; ?>
    </span>
</div>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul>
            <?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<p class="note">
    <?php if ($tous): ?>
        Vue de supervision, en <strong>lecture seule</strong>.
    <?php else: ?>
        Vos envois des 30 derniers jours. « Reprendre » : redéposez le même fichier.
    <?php endif; ?>
</p>

<?php if ($envois === []): ?>
    <div class="panneau panneau--centre">
        <p>Aucun envoi pour le moment.</p>
        <p><a class="bouton" href="/admin/envoyer">Envoyer un premier fichier</a></p>
    </div>
<?php else: ?>
    <div class="tableau-defilant">
        <table class="tableau">
            <thead>
                <tr>
                    <th scope="col">Fichier</th>
                    <th scope="col">Taille</th>
                    <th scope="col">Morceaux</th>
                    <th scope="col">État</th>
                    <th scope="col">Dernière activité</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($envois as $envoi): ?>
                    <?php
                    $etat   = (string) $envoi['state'];
                    $pieces = envoi_morceaux($envoi);
                    $docId  = (int) ($envoi['document_id'] ?? 0);
                    $doc    = $docId > 0 ? ($documents[$docId] ?? null) : null;
                    $estMoi = (int) $envoi['account_id'] === $compte;
                    ?>
                    <tr>
                        <td>
                            <?= e((string) $envoi['original_name']) ?>
                            <?php if ($tous && !$estMoi): ?>
                                <span class="discret discret--bloc">
                                    par <?= e((string) ($envoi['username'] ?? '—')) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ((string) $envoi['message'] !== ''): ?>
                                <span class="discret discret--bloc"><?= e((string) $envoi['message']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e(format_size((int) $envoi['size'])) ?></td>
                        <td class="colonne-position">
                            <?= (int) $pieces['nombre'] ?> / <?= (int) $envoi['chunks_total'] ?>
                        </td>
                        <td>
                            <span class="<?= e(envoi_etat_classe($etat)) ?>">
                                <?= e(envoi_etat_libelle($etat)) ?>
                            </span>
                        </td>
                        <td><?= e(format_date((int) $envoi['updated_at'], true)) ?></td>
                        <td class="colonne-actions">
                            <?php if ($estMoi && envoi_reprisable($envoi)): ?>
                                <a href="/admin/envoyer?reprendre=<?= e((string) $envoi['token']) ?>">Reprendre</a>
                                <span aria-hidden="true"> · </span>
                            <?php endif; ?>

                            <?php if ($doc): ?>
                                <a href="/admin/documents/<?= (int) $doc['id'] ?>/modifier">Fiche</a>
                            <?php elseif ($etat === 'pret' && $estMoi): ?>
                                <a href="/admin/envoyer">Créer le document</a>
                            <?php endif; ?>

                            <?php if ($estMoi && in_array($etat, ['en_cours', 'doublon', 'pret'], true)): ?>
                                <?php if ($doc): ?><span aria-hidden="true"> · </span><?php endif; ?>
                                <form method="post" action="/admin/envoyer/annuler" class="formulaire-en-ligne"
                                      data-confirmation="Annuler cet envoi et supprimer définitivement les morceaux et le fichier déjà reçus ?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="token" value="<?= e((string) $envoi['token']) ?>">
                                    <button type="submit" class="lien-bouton lien-bouton--danger">Annuler</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php if (!$tous): ?>
    <section class="panneau">
        <h2>Rappel</h2>
        <p>
            Un envoi interrompu depuis plus de 48 heures est supprimé automatiquement
            par la tâche d’entretien, et l’espace disque est libéré. Aucun fichier
            terminé n’est concerné.
        </p>
    </section>
<?php endif; ?>
