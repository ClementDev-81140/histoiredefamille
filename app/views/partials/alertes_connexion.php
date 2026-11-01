<?php
/**
 * Alertes de connexion (tableau de bord et journal).
 *
 * @var array $alertes  (voir journal_alertes())
 */
$aDesAlertes = ($alertes['echecs'] ?? null) !== null || ($alertes['nouvelles_ip'] ?? []) !== [];
if (!$aDesAlertes) {
    return;
}
$retour = $_SERVER['REQUEST_URI'] === '/admin/connexions' || str_starts_with((string) $_SERVER['REQUEST_URI'], '/admin/connexions?') ? 'journal' : 'tableau';
?>
<div class="message message--erreur alerte-connexion" role="alert">
    <p><strong>À vérifier : activité de connexion inhabituelle</strong></p>
    <ul>
        <?php if ($alertes['echecs'] !== null): ?>
            <li>
                <?= (int) $alertes['echecs']['nb'] ?> échecs de connexion en moins d’une heure
                (<?= (int) $alertes['echecs']['ips'] ?> adresse<?= $alertes['echecs']['ips'] > 1 ? 's' : '' ?> différente<?= $alertes['echecs']['ips'] > 1 ? 's' : '' ?>).
                <a href="/admin/connexions?statut=echecs&amp;periode=24h">Voir le détail</a>
            </li>
        <?php endif; ?>
        <?php foreach ($alertes['nouvelles_ip'] as $ligne): ?>
            <li>
                Le compte administrateur « <?= e((string) ($ligne['username'] ?? '?')) ?> » s’est connecté depuis une
                adresse jamais vue (<code><?= e($ligne['ip']) ?></code>) le
                <?= e(format_date((int) $ligne['created_at'], true)) ?>.
            </li>
        <?php endforeach; ?>
    </ul>
    <form method="post" action="/admin/alertes/acquitter" class="formulaire-en-ligne">
        <?= csrf_field() ?>
        <input type="hidden" name="retour" value="<?= e($retour) ?>">
        <button type="submit" class="bouton bouton--secondaire">J’ai pris connaissance</button>
        <span class="aide">Si ce n’est pas vous, changez votre mot de passe dans « Mon compte ».</span>
    </form>
</div>
