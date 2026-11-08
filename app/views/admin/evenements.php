<?php
/**
 * Administration → Calendrier : la liste des événements de famille.
 *
 * @var array $evenements, $erreurs, $old
 */
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/admin">Administration</a>
    <span aria-hidden="true">/</span>
    <span>Calendrier</span>
</nav>

<h1>Calendrier</h1>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<p class="actions">
    <a class="bouton" href="/admin/calendrier/nouveau">Nouvel événement</a>
    <a class="bouton bouton--secondaire" href="/calendrier">Voir le calendrier</a>
</p>

<p class="aide">
    Un événement créé ici est <strong>privé</strong> tant que vous ne choisissez pas
    qui peut le voir. Les dates de l’arbre (anniversaires, mariages) restent
    affichées automatiquement dans le calendrier. Un événement proposé par un
    journaliste apparaît ici <strong>en attente de validation</strong> : il n'est
    visible nulle part tant que vous ne l'avez pas validé.
</p>

<?php if ($evenements === []): ?>
    <div class="panneau panneau--centre">
        <p>Aucun événement pour le moment.</p>
    </div>
<?php else: ?>
    <div class="tableau-defilant">
        <table class="tableau">
            <thead>
                <tr>
                    <th scope="col">Date</th>
                    <th scope="col">Titre</th>
                    <th scope="col">Lieu</th>
                    <th scope="col">État</th>
                    <th scope="col">Visibilité</th>
                    <th scope="col">Répétition</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($evenements as $evenement): ?>
                    <?php
                    $bras = content_branch_ids('event', (int) $evenement['id']);
                    $brancheNoms = [];
                    foreach ($bras as $brancheId) {
                        $branche = find_branch((int) $brancheId);
                        if ($branche !== null) {
                            $brancheNoms[] = (string) $branche['name'];
                        }
                    }
                    ?>
                    <tr>
                        <td>
                            <?= e(french_long_date((int) strtotime((string) $evenement['start_date'] . ' 12:00:00'), true)) ?>
                            <?php if (trim((string) ($evenement['start_time'] ?? '')) !== ''): ?>
                                <span class="discret">à <?= e((string) $evenement['start_time']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e((string) $evenement['title']) ?>
                            <?php if (evenement_type_libelle((string) ($evenement['type'] ?? '')) !== ''): ?>
                                <span class="discret"><?= e(evenement_type_libelle((string) ($evenement['type'] ?? ''))) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((string) ($evenement['status'] ?? 'publie') === 'en_attente'): ?>
                                <span class="etiquette">En attente de validation</span>
                            <?php else: ?>
                                <span class="discret">Publié</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e((string) $evenement['place']) ?></td>
                        <td>
                            <span class="<?= e(visibility_class((string) $evenement['visibility'])) ?>">
                                <?= e(visibility_label((string) $evenement['visibility'])) ?>
                            </span>
                            <?php if ($brancheNoms !== []): ?>
                                <span class="discret"><?= e(implode(', ', $brancheNoms)) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $evenement['annual'] === 1 ? 'Chaque année' : 'Une seule fois' ?></td>
                        <td class="colonne-actions">
                            <?php if ((string) ($evenement['status'] ?? 'publie') === 'en_attente'): ?>
                                <form method="post" action="/admin/calendrier/<?= (int) $evenement['id'] ?>/valider"
                                      class="formulaire-en-ligne">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="lien-bouton">Valider</button>
                                </form>
                            <?php endif; ?>
                            <a class="lien-bouton" href="/admin/calendrier/<?= (int) $evenement['id'] ?>/modifier">Modifier</a>
                            <form method="post" action="/admin/calendrier/<?= (int) $evenement['id'] ?>/supprimer"
                                  class="formulaire-en-ligne"
                                  data-confirmation="Supprimer cet événement ? Cette action est définitive.">
                                <?= csrf_field() ?>
                                <button type="submit" class="lien-bouton lien-bouton--danger">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
