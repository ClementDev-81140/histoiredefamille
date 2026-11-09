<?php
/**
 * Journal des journalistes (administrateurs seulement, 1.37.0).
 *
 * @var array $lignes
 */
$libelles = [
    'journaliste_publication'  => 'Publication directe',
    'journaliste_modification' => 'Modification directe',
];
?>
<h1>Journal des journalistes</h1>
<p class="aide">
    Les journalistes réglés « sans validation » publient directement : chacune
    de ces actions est notée ici, avec la date et le compte. Vous pouvez
    <strong>dépublier</strong> un contenu en un clic : il repasse en « privé »,
    sans être supprimé.
</p>

<?php if ($lignes === []): ?>
    <p class="panneau">Aucune action directe de journaliste pour le moment.</p>
<?php else: ?>
    <div class="tableau-defilant">
    <table class="tableau">
        <thead>
            <tr>
                <th scope="col">Date</th>
                <th scope="col">Compte</th>
                <th scope="col">Action</th>
                <th scope="col">Contenu</th>
                <th scope="col">Dépublier</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lignes as $ligne): ?>
                <?php $entree = $ligne['entree']; ?>
                <tr>
                    <td><?= e(date('d/m/Y H:i', (int) $entree['created_at'])) ?></td>
                    <td><?= e((string) ($entree['username'] ?? '—')) ?></td>
                    <td><?= e($libelles[(string) $entree['action']] ?? (string) $entree['action']) ?></td>
                    <td>
                        <?= e((string) $ligne['titre']) ?>
                        <?php if ($ligne['genre'] !== ''): ?>
                            <span class="aide">(<?= e((string) $ligne['genre']) ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($ligne['depubliable']): ?>
                            <form method="post"
                                  action="/admin/journalistes/depublier/<?= e((string) $ligne['genre']) ?>/<?= (int) $ligne['id'] ?>"
                                  data-confirmation="Dépublier ce contenu ? Il repassera en privé, sans être supprimé.">
                                <?= csrf_field() ?>
                                <button type="submit" class="bouton bouton--secondaire">Dépublier</button>
                            </form>
                        <?php else: ?>
                            <span class="aide">déjà privé ou retiré</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
