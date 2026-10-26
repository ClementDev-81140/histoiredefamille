<?php
/**
 * Liste des couples.
 *
 * @var array $families
 */
$nom = static function (string $first, ?string $last): string {
    $label = trim((string) $first . ' ' . mb_strtoupper((string) $last, 'UTF-8'));
    return $label !== '' ? $label : '—';
};
?>
<div class="section__entete">
    <h1>Couples &amp; enfants</h1>
    <a class="bouton" href="/admin/arbre/couple/nouveau">Nouveau couple</a>
</div>

<p class="note">
    Un couple relie deux personnes et leurs enfants. Pour ajouter des parents à une
    personne, créez le couple de ses parents et cochez-la comme enfant. Il est aussi
    possible de créer un couple avec un seul conjoint (parent isolé).
</p>

<?php if ($families === []): ?>
    <div class="panneau panneau--centre">
        <p>Aucun couple enregistré.</p>
        <p><a class="bouton" href="/admin/arbre/couple/nouveau">Créer un couple</a></p>
    </div>
<?php else: ?>
    <div class="tableau-defilant">
        <table class="tableau">
            <thead>
                <tr>
                    <th scope="col">Conjoint 1</th>
                    <th scope="col">Conjoint 2</th>
                    <th scope="col">Mariage</th>
                    <th scope="col">Enfants</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($families as $family): ?>
                    <tr>
                        <td>
                            <?php if ($family['husband_id']): ?>
                                <a href="/admin/arbre/personne/<?= (int) $family['husband_id'] ?>">
                                    <?= e($nom((string) $family['h_first'], $family['h_last'])) ?>
                                </a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <?php if ($family['wife_id']): ?>
                                <a href="/admin/arbre/personne/<?= (int) $family['wife_id'] ?>">
                                    <?= e($nom((string) $family['w_first'], $family['w_last'])) ?>
                                </a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <?= e((string) ($family['marriage_date'] ?? '')) ?>
                            <?php if (trim((string) ($family['marriage_place'] ?? '')) !== ''): ?>
                                <span class="discret"><?= e($family['marriage_place']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $family['nb_enfants'] ?></td>
                        <td class="colonne-actions">
                            <a href="/admin/arbre/couple/<?= (int) $family['id'] ?>">Modifier</a>
                            <form method="post" action="/admin/arbre/couple/<?= (int) $family['id'] ?>/supprimer"
                                  class="formulaire-en-ligne"
                                  data-confirmation="Supprimer ce couple ? Les personnes ne seront pas supprimées.">
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
