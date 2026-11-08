<?php
/**
 * Dossiers d'archives (1.36.0) : création, renommage, suppression.
 *
 * Supprimer un dossier ne supprime jamais ses documents : ils deviennent
 * « sans dossier ». Les documents se rangent depuis leur fiche ou dès
 * l'envoi (« Classer en archive »).
 *
 * @var array $dossiers, $personnes, $erreurs, $old
 */
?>
<nav class="fil" aria-label="Fil d’Ariane">
    <a href="/admin">Administration</a>
    <span aria-hidden="true">/</span>
    <span>Dossiers d’archives</span>
</nav>

<h1>Dossiers d’archives</h1>
<p class="aide">
    Un dossier regroupe des documents et photos d’archive (par exemple « Grand-père Michel »).
    Il n’a pas de visibilité propre : chaque document garde la sienne, et un dossier
    n’apparaît pour un compte que s’il contient quelque chose que ce compte peut voir.
</p>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur" role="alert">
        <ul><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" action="/admin/dossiers/nouveau" class="formulaire panneau">
    <?= csrf_field() ?>
    <h2>Nouveau dossier</h2>
    <div class="champ">
        <label for="name">Nom <span class="obligatoire">*</span></label>
        <input type="text" id="name" name="name" maxlength="100" required
               value="<?= e((string) old_value($old, 'name', '')) ?>" placeholder="ex. Grand-père Michel">
    </div>
    <div class="champ">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3" maxlength="2000"><?= e((string) old_value($old, 'description', '')) ?></textarea>
        <p class="aide">Facultative. Elle s’affiche sur la page du dossier, pour les comptes qui peuvent y voir quelque chose.</p>
    </div>
    <div class="champ">
        <label for="person_id">Personne de l’arbre liée</label>
        <select id="person_id" name="person_id">
            <option value="">Aucune</option>
            <?php foreach ($personnes as $personne): ?>
                <option value="<?= (int) $personne['id'] ?>"<?= selected((int) old_value($old, 'person_id', 0) === (int) $personne['id']) ?>>
                    <?= e(person_full_name($personne)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="aide">Facultative. La fiche de cette personne proposera un lien vers le dossier — seulement aux comptes qui peuvent y voir quelque chose.</p>
    </div>
    <p class="actions">
        <button type="submit" class="bouton">Créer le dossier</button>
    </p>
</form>

<section class="section">
    <h2>Dossiers existants</h2>
    <?php if ($dossiers === []): ?>
        <p class="discret">Aucun dossier pour le moment.</p>
    <?php else: ?>
        <div class="tableau-defilant">
            <table class="tableau">
                <thead>
                    <tr>
                        <th scope="col">Nom et description</th>
                        <th scope="col">Personne liée</th>
                        <th scope="col">Documents</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dossiers as $dossier): ?>
                        <tr>
                            <td>
                                <form method="post" action="/admin/dossiers/<?= (int) $dossier['id'] ?>/modifier" class="formulaire-en-ligne">
                                    <?= csrf_field() ?>
                                    <input type="text" name="name" value="<?= e((string) $dossier['name']) ?>"
                                           maxlength="100" aria-label="Nom du dossier" required>
                                    <input type="text" name="description" value="<?= e((string) ($dossier['description'] ?? '')) ?>"
                                           maxlength="2000" aria-label="Description du dossier" placeholder="Description (facultative)">
                                    <select name="person_id" aria-label="Personne de l’arbre liée">
                                        <option value="">Aucune personne</option>
                                        <?php foreach ($personnes as $personne): ?>
                                            <option value="<?= (int) $personne['id'] ?>"<?= selected((int) ($dossier['person_id'] ?? 0) === (int) $personne['id']) ?>>
                                                <?= e(person_full_name($personne)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="lien-bouton">Enregistrer</button>
                                </form>
                            </td>
                            <td><?php
                                $liee = null;
                                foreach ($personnes as $personne) {
                                    if ((int) $personne['id'] === (int) ($dossier['person_id'] ?? 0)) {
                                        $liee = $personne;
                                    }
                                }
                                echo $liee !== null ? e(person_full_name($liee)) : '—';
                            ?></td>
                            <td>
                                <?= (int) $dossier['nb'] ?>
                                <?php if ((int) $dossier['nb'] > 0): ?>
                                    · <a href="/dossiers/<?= (int) $dossier['id'] ?>">Voir la page</a>
                                <?php endif; ?>
                            </td>
                            <td class="colonne-actions">
                                <form method="post" action="/admin/dossiers/<?= (int) $dossier['id'] ?>/supprimer"
                                      class="formulaire-en-ligne"
                                      data-confirmation="Supprimer ce dossier ? Ses documents seront conservés, sans dossier.">
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
</section>
