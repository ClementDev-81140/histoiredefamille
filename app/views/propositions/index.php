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
    <p>Un administrateur relit chaque proposition avant de la valider. Vous pouvez la corriger jusque-là.</p>
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
                            <?php if ((int) ($ligne['replaces_id'] ?? 0) > 0): ?>
                                <span class="discret">Version proposée d’un contenu déjà publié :
                                    le texte en ligne ne changera qu’après validation.</span>
                            <?php endif; ?>
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
                            <?php if ($etat !== 'publie'): ?>
                                <form method="post" action="<?= e($lien) ?>/retirer" class="formulaire-en-ligne"
                                      data-confirmation="Retirer cette proposition ? Elle ne sera pas publiée.">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="lien-bouton lien-bouton--danger">Retirer</button>
                                </form>
                            <?php endif; ?>
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

<?php if (($albumsAttente ?? []) !== []): ?>
<section class="section">
    <div class="section__entete">
        <h2>Mes albums en attente de validation</h2>
    </div>
    <p class="aide">
        Ces albums ne sont visibles que de vous (par leur adresse) et des
        administrateurs. Ils paraîtront lorsque l’un d’eux les aura validés,
        avec la visibilité que vous avez demandée.
    </p>
    <ul>
        <?php foreach ($albumsAttente as $albumAttente): ?>
            <li>
                <a href="/albums/<?= e((string) $albumAttente['slug']) ?>"><?= e((string) $albumAttente['title']) ?></a>
                — visibilité demandée : <?= e(visibility_label((string) ($albumAttente['suggested_visibility'] ?? 'prive'))) ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php if (($corrigables ?? []) !== []): ?>
<section class="section">
    <div class="section__entete">
        <h2>Contenus de mes branches que je peux corriger</h2>
    </div>
    <p class="aide">
        Ces contenus ont été déposés par d’autres comptes, mais ils relèvent des
        branches où vous pouvez écrire. Vous pouvez les corriger ; vous ne pouvez
        pas les supprimer.
    </p>
    <div class="tableau-scroll">
        <table class="tableau">
            <thead>
                <tr><th>Type</th><th>Titre</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($corrigables as $corrigeable): ?>
                <tr>
                    <td><?= e($corrigeable['label']) ?></td>
                    <td><?= e((string) ($corrigeable['ligne']['title'] ?? '')) ?></td>
                    <td><a href="/propositions/<?= e($corrigeable['type']) ?>/<?= (int) $corrigeable['ligne']['id'] ?>">Modifier</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
