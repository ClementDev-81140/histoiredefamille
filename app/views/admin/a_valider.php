<?php
/**
 * Administration — « À valider » : la file d'attente unique.
 *
 * @var list<array{type:string,row:array}> $propositions
 * @var list<string> $echecs
 */
$types = proposition_types();
?>
<div class="section__entete">
    <h1>À valider</h1>
    <p class="discret"><?= count($propositions) ?> proposition<?= count($propositions) > 1 ? 's' : '' ?> en attente</p>
</div>

<?php if ($echecs !== []): ?>
    <div class="message message--erreur">
        <ul><?php foreach ($echecs as $echec): ?><li><?= e($echec) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if ($propositions === []): ?>
    <div class="panneau panneau--centre">
        <p>Rien à valider pour le moment.</p>
        <p class="discret">Les articles, documents et recettes proposés par les journalistes arrivent ici.</p>
    </div>
<?php else: ?>
    <div class="tableau-defilant">
        <table class="tableau tableau--compact">
            <thead>
                <tr>
                    <th scope="col" class="colonne-type">Type</th>
                    <th scope="col">Titre</th>
                    <th scope="col">Auteur</th>
                    <th scope="col" class="colonne-date">Déposé le</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($propositions as $element): ?>
                    <?php
                    $type  = $element['type'];
                    $ligne = $element['row'];
                    $suggeree = sanitize_visibility((string) ($ligne['suggested_visibility'] ?? ''));
                    ?>
                    <tr>
                        <td class="colonne-type"><?= e($types[$type]['label'] ?? $type) ?></td>
                        <td>
                            <a href="/admin/a-valider/<?= e($type) ?>/<?= (int) $ligne['id'] ?>"><?= e(proposition_titre($ligne, $type)) ?></a>
                            <span class="<?= e(visibility_class($suggeree)) ?>" title="Visibilité souhaitée par l’auteur"><?= e(visibility_short($suggeree)) ?></span>
                        </td>
                        <td><?= e(proposition_auteur($ligne)) ?></td>
                        <td class="colonne-date"><?= e(format_date(proposition_date($ligne))) ?></td>
                        <td class="colonne-actions">
                            <a class="bouton bouton--secondaire" href="/admin/a-valider/<?= e($type) ?>/<?= (int) $ligne['id'] ?>">Examiner</a>
                            <span class="sr-only">la proposition</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
