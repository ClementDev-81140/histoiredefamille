<?php
/**
 * Messages flash (succès, erreur, information).
 *
 * @var array $flashes
 */
foreach ($flashes as $flash):
    $type = in_array($flash['type'], ['succes', 'erreur', 'info'], true) ? $flash['type'] : 'info';
    ?>
    <div class="message message--<?= e($type) ?>" role="<?= $type === 'erreur' ? 'alert' : 'status' ?>">
        <?= e($flash['message']) ?>
    </div>
<?php endforeach; ?>
