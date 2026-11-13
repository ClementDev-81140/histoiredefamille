<h1>Historique des liens familiaux</h1>
<p>Pour retirer un lien, ouvrez la famille correspondante et modifiez les personnes concernées.</p>
<?php if ($lignes === []): ?><p>Aucun lien ajouté par ce parcours.</p><?php else: ?>
<ul>
<?php foreach ($lignes as $ligne): ?>
    <?php $details = json_decode((string) $ligne['avant'], true); ?>
    <li>
        <?= e(date('d/m/Y H:i', (int) $ligne['created_at'])) ?> — <?= e((string) ($details['phrase'] ?? 'Lien familial')) ?>
        <a href="/admin/arbre/couple/<?= (int) $ligne['content_id'] ?>">Modifier cette famille</a>
    </li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<p><a class="bouton bouton--secondaire" href="/admin/arbre">Retour à l’arbre</a></p>
