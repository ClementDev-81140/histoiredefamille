<?php
/**
 * Journal des connexions.
 *
 * @var array  $lignes, $pagination, $resume, $alertes, $conservation
 * @var string $statut, $periode, $q
 * @var int    $total
 */
?>
<div class="section__entete">
    <h1>Journal des connexions</h1>
</div>

<p class="discret">
    Dernières 24 heures :
    <strong><?= (int) $resume['succes'] ?></strong> connexion<?= $resume['succes'] > 1 ? 's' : '' ?> réussie<?= $resume['succes'] > 1 ? 's' : '' ?>,
    <strong><?= (int) $resume['echecs'] ?></strong> échec<?= $resume['echecs'] > 1 ? 's' : '' ?>.
</p>

<?php require __DIR__ . '/../partials/alertes_connexion.php'; ?>

<form method="get" action="/admin/connexions" class="filtres">
    <div class="champ">
        <label for="q">Identifiant ou adresse IP</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>" maxlength="60">
    </div>
    <div class="champ">
        <label for="statut">Type</label>
        <select id="statut" name="statut">
            <option value="">Tout</option>
            <option value="echecs"<?= selected($statut === 'echecs') ?>>Échecs et blocages</option>
            <option value="succes"<?= selected($statut === 'succes') ?>>Connexions réussies</option>
            <option value="nouvelle_ip"<?= selected($statut === 'nouvelle_ip') ?>>Nouvelle adresse (admin)</option>
        </select>
    </div>
    <div class="champ">
        <label for="periode">Période</label>
        <select id="periode" name="periode">
            <option value="">Toute la durée conservée</option>
            <option value="24h"<?= selected($periode === '24h') ?>>24 heures</option>
            <option value="7j"<?= selected($periode === '7j') ?>>7 jours</option>
            <option value="30j"<?= selected($periode === '30j') ?>>30 jours</option>
        </select>
    </div>
    <div class="champ champ--bouton">
        <button type="submit" class="bouton">Filtrer</button>
        <a class="lien" href="/admin/connexions">Réinitialiser</a>
    </div>
</form>

<p class="discret"><?= (int) $total ?> ligne<?= $total > 1 ? 's' : '' ?></p>

<?php if ($lignes === []): ?>
    <div class="panneau panneau--centre"><p>Aucune connexion enregistrée pour ces critères.</p></div>
<?php else: ?>
    <div class="tableau-defilant">
        <table class="tableau">
            <thead>
                <tr>
                    <th scope="col">Date</th>
                    <th scope="col">Résultat</th>
                    <th scope="col">Identifiant saisi</th>
                    <th scope="col">Adresse IP</th>
                    <th scope="col">Détail</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lignes as $ligne): ?>
                <tr>
                    <td><?= e(format_date((int) $ligne['created_at'], true)) ?></td>
                    <td>
                        <?php if ((int) $ligne['success'] === 1): ?>
                            <span class="badge badge-public">réussie</span>
                        <?php else: ?>
                            <span class="badge badge-prive">échec</span>
                        <?php endif; ?>
                        <?php if ((int) $ligne['new_ip'] === 1): ?>
                            <span class="badge badge-branches">nouvelle adresse</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= e($ligne['identifier']) ?>
                        <?php if (!empty($ligne['role']) && $ligne['role'] === 'admin'): ?>
                            <span class="discret">(admin)</span>
                        <?php endif; ?>
                    </td>
                    <td><code><?= e($ligne['ip']) ?></code></td>
                    <td><?= e($ligne['detail']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
<?php endif; ?>

<section class="panneau">
    <h2>Conservation du journal</h2>
    <form method="post" action="/admin/connexions/conservation" class="formulaire-en-ligne">
        <?= csrf_field() ?>
        <label for="conservation">Garder les lignes pendant</label>
        <select id="conservation" name="conservation">
            <?php foreach (journal_conservations() as $jours => $libelle): ?>
                <option value="<?= (int) $jours ?>"<?= selected($conservation === $jours) ?>><?= e($libelle) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="bouton bouton--secondaire">Enregistrer</button>
    </form>
    <p class="aide">
        Les lignes plus anciennes sont effacées chaque dimanche par la tâche d’entretien.
        Attention : l’identifiant est enregistré tel qu’il a été saisi, même quand il n’existe
        pas. Si quelqu’un tape par erreur son mot de passe dans ce champ, il apparaît ici : ce journal
        n’est visible que des administrateurs.
    </p>
</section>
