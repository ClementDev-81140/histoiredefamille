<?php
/**
 * Mes propositions — espace du journaliste.
 *
 * @var list<array{type:string,row:array}> $propositions
 * @var list<string> $echecs
 */
$types = proposition_types();
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/famille">Espace famille</a>
    <span aria-hidden="true">/</span>
    <span>Mes propositions</span>
</nav>

<h1>Mes propositions</h1>

<div class="message message--info">
    <p>Vos propositions ne sont pas visibles tout de suite : un administrateur les relit, puis choisit qui peut les voir. Vous pouvez les corriger tant qu’elles n’ont pas été validées.</p>
</div>

<?php if ($echecs !== []): ?>
    <div class="message message--erreur">
        <ul><?php foreach ($echecs as $echec): ?><li><?= e($echec) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<section class="panneau">
    <h2>Proposer</h2>
    <p class="actions">
        <a class="bouton" href="/propositions/nouvelle/article">Proposer un article</a>
        <a class="bouton bouton--secondaire" href="/propositions/nouvelle/document">Proposer un document ou une photo</a>
        <a class="bouton bouton--secondaire" href="/propositions/nouvelle/recette">Proposer une recette</a>
    </p>
</section>

<section class="panneau">
    <h2>Mes propositions (<?= count($propositions) ?>)</h2>

    <?php if ($propositions === []): ?>
        <p class="vide">Vous n’avez encore rien proposé.</p>
    <?php else: ?>
        <div class="tableau-defilant">
            <table class="tableau tableau--liste">
                <thead>
                    <tr>
                        <th scope="col">Type</th>
                        <th scope="col">Titre</th>
                        <th scope="col">État</th>
                        <th scope="col">Dernière modification</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($propositions as $element): ?>
                    <?php
                    $type  = $element['type'];
                    $ligne = $element['row'];
                    $etat  = (string) ($ligne['moderation_state'] ?? 'attente');
                    $lien  = '/propositions/' . $type . '/' . (int) $ligne['id'];
                    ?>
                    <tr>
                        <td><?= e($types[$type]['label'] ?? $type) ?></td>
                        <td>
                            <strong><?= e(proposition_titre($ligne, $type)) ?></strong>
                            <?php if ($etat === 'refuse' && (string) ($ligne['moderation_note'] ?? '') !== ''): ?>
                                <span class="discret">Motif du refus : <?= e((string) $ligne['moderation_note']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="<?= e(moderation_class($etat)) ?>"><?= e(moderation_label($etat)) ?></span>
                        </td>
                        <td><?= e(format_date(proposition_date($ligne))) ?></td>
                        <td>
                            <a href="<?= e($lien) ?>">Modifier</a>
                            <?php if ($etat === 'publie'): ?>
                                <?php if ($type === 'article'): ?>
                                    <a href="/articles/<?= e((string) $ligne['slug']) ?>">Voir</a>
                                <?php elseif ($type === 'recette'): ?>
                                    <a href="/recettes/<?= e((string) $ligne['slug']) ?>">Voir</a>
                                <?php else: ?>
                                    <a href="/documents/<?= (int) $ligne['id'] ?>">Voir</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
