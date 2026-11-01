<?php
/**
 * Un message du tchat.
 *
 * @var array $message
 * @var bool  $estMoi  message écrit par la personne qui regarde
 * @var bool  $admin   la personne qui regarde est administratrice
 */
$heure = date('H:i', (int) $message['created_at']);
?>
<article class="tchat__message<?= $estMoi ? ' tchat__message--moi' : '' ?>">
    <div class="tchat__entete">
        <span class="tchat__auteur"><?= e($message['author_label']) ?></span>
        <time class="tchat__heure" datetime="<?= e(date('c', (int) $message['created_at'])) ?>"><?= e($heure) ?></time>
        <?php if ($admin): ?>
            <form method="post" action="/tchat/<?= (int) $message['id'] ?>/supprimer" class="tchat__supprimer"
                  data-confirmation="Supprimer définitivement ce message ? L’opération est notée dans le journal d’activité.">
                <?= csrf_field() ?>
                <button type="submit" class="lien-bouton">Supprimer</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="tchat__texte"><?= tchat_texte((string) $message['body']) ?></div>
</article>
