<?php
/**
 * Administration → Comptes : la liste des comptes de la famille.
 *
 * @var array  $comptes, $branches, $parType
 * @var string $q, $type
 * @var int    $branche
 */
$libelles = compte_types();
?>
<div class="section__entete">
    <div>
        <h1>Comptes de la famille</h1>
    </div>
    <a class="bouton" href="/admin/comptes/nouvelle">Ajouter un compte</a>
</div>

<p class="comptes-resume">
    <span><strong><?= (int) ($parType['branche'] ?? 0) ?></strong> lecteur(s)</span>
    <span><strong><?= (int) ($parType['personne'] ?? 0) ?></strong> journaliste(s)</span>
    <span><strong><?= (int) ($parType['admin'] ?? 0) ?></strong> administrateur(s)</span>
</p>

<form method="get" action="/admin/comptes" class="formulaire formulaire--filtre panneau">
    <div class="ligne-champs">
        <div class="champ">
            <label for="q">Rechercher</label>
            <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Nom, prénom ou identifiant">
        </div>
        <div class="champ">
            <label for="branche">Branche</label>
            <select id="branche" name="branche">
                <option value="0">Toutes les branches</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= (int) $b['id'] ?>"<?= selected($branche === (int) $b['id']) ?>>
                        <?= e($b['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="champ">
            <label for="type">Type</label>
            <select id="type" name="type">
                <option value="">Tous les types</option>
                <?php foreach ($libelles as $cle => $libelle): ?>
                    <option value="<?= e($cle) ?>"<?= selected($type === $cle) ?>><?= e($libelle) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <p class="actions">
        <button type="submit" class="bouton bouton--secondaire">Filtrer</button>
        <?php if ($q !== '' || $branche > 0 || $type !== ''): ?>
            <a class="lien" href="/admin/comptes">Tout afficher</a>
        <?php endif; ?>
    </p>
</form>

<?php if ($comptes === []): ?>
    <div class="panneau panneau--centre">
        <p>Aucun compte ne correspond à cette recherche.</p>
        <p><a class="bouton" href="/admin/comptes/nouvelle">Ajouter un compte</a></p>
    </div>
<?php else: ?>
    <div class="tableau-defilant">
        <table class="tableau comptes-tableau">
            <thead>
                <tr>
                    <th scope="col">Nom</th>
                    <th scope="col">Prénom</th>
                    <th scope="col">Identifiant</th>
                    <th scope="col">Branche</th>
                    <th scope="col">Type</th>
                    <th scope="col">Droits</th>
                    <th scope="col">État</th>
                    <th scope="col">Dernière connexion</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($comptes as $compte): ?>
                    <?php $typeCompte = compte_type($compte); ?>
                    <tr>
                        <td>
                            <?= $typeCompte === 'personne' ? e((string) ($compte['last_name'] !== '' ? $compte['last_name'] : '—')) : e(compte_nom_complet($compte)) ?>
                        </td>
                        <td><?= $typeCompte === 'personne' ? e((string) ($compte['first_name'] !== '' ? $compte['first_name'] : '—')) : '—' ?></td>
                        <td><code><?= e((string) $compte['username']) ?></code></td>
                        <td><?= $compte['branche_nom'] !== null ? e((string) $compte['branche_nom']) : '—' ?></td>
                        <td><?= e(compte_type_libelle($compte)) ?></td>
                        <td>
                            <?php if ($typeCompte === 'admin' || ($typeCompte === 'branche' && (int) ($compte['branch_see_all'] ?? 0) === 1) || ($typeCompte === 'personne' && (int) $compte['see_all'] === 1)): ?>
                                <span class="badge badge-famille">voit tout</span>
                            <?php else: ?>
                                <span class="badge badge-branches">sa branche</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int) $compte['is_active'] === 1): ?>
                                <span class="badge badge-public">actif</span>
                            <?php else: ?>
                                <span class="badge badge-prive">désactivé</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $compte['last_login_at'] ? e(format_date((int) $compte['last_login_at'], true)) : '—' ?></td>
                        <td class="colonne-actions">
                            <?php if ($typeCompte === 'personne' || $typeCompte === 'branche'): ?>
                                <a href="/admin/comptes/<?= (int) $compte['id'] ?>">Modifier</a>
                            <?php endif; ?>
                            <?php if ($typeCompte === 'personne'): ?>

                                <form method="post" action="/admin/comptes/<?= (int) $compte['id'] ?>/mot-de-passe"
                                      class="formulaire-en-ligne"
                                      data-confirmation="Générer un nouveau mot de passe ? L’ancien ne fonctionnera plus.">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="lien-bouton">Nouveau mot de passe</button>
                                </form>

                                <form method="post" action="/admin/comptes/<?= (int) $compte['id'] ?>/activer"
                                      class="formulaire-en-ligne">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="lien-bouton">
                                        <?= (int) $compte['is_active'] === 1 ? 'Désactiver' : 'Réactiver' ?>
                                    </button>
                                </form>

                                <form method="post" action="/admin/comptes/<?= (int) $compte['id'] ?>/supprimer"
                                      class="formulaire-en-ligne"
                                      data-confirmation="Supprimer définitivement ce compte ? Ses messages du tchat resteront lisibles.">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="lien-bouton lien-bouton--danger">Supprimer</button>
                                </form>
                            <?php elseif ($typeCompte === 'branche'): ?>
                                <a href="/admin/branches/<?= (int) ($compte['branch_id'] ?? 0) ?>">Modifier</a>
                            <?php else: ?>
                                <a href="/admin/admins">Administrateurs</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<p class="note">
    Les administrateurs se créent dans « Administrateurs » (ou avec <code>php app/cli/create-admin.php</code>).
</p>
