<?php
/**
 * Aperçu avant création : on vérifie tout avant d'écrire quoi que ce soit.
 *
 * L'identifiant proposé est modifiable ; les lignes en erreur ne peuvent pas
 * être créées ; les doublons doivent être cochés exprès.
 *
 * @var array       $bloc, $erreurs
 * @var array|null  $branche
 * @var int         $nbValides, $nbCochés
 */
$lignes = $bloc['lignes'] ?? [];
?>
<div class="section__entete">
    <div>
        <h1>Aperçu avant création</h1>
        <p class="note">
            Vérifiez les noms, les identifiants et la branche. Vous pouvez corriger un
            identifiant ici ; rien n’est encore enregistré.
        </p>
    </div>
    <a class="lien" href="/admin/comptes/nouvelle">← Corriger la saisie</a>
</div>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur">
        <ul>
            <?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (($bloc['erreurs'] ?? []) !== []): ?>
    <div class="message message--erreur">
        <ul>
            <?php foreach ($bloc['erreurs'] as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<p class="comptes-resume">
    <span>Branche : <strong><?= $branche !== null ? e((string) $branche['name']) : '—' ?></strong></span>
    <span>Droits : <strong><?= !empty($bloc['see_all']) ? 'voit toutes les branches' : 'sa branche seulement' ?></strong></span>
    <span><?= (int) $nbValides ?> ligne(s) valide(s), <?= (int) $nbCochés ?> à créer</span>
</p>

<form method="post" action="/admin/comptes/creer" class="formulaire">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e((string) $bloc['token']) ?>">

    <div class="tableau-defilant">
        <table class="tableau comptes-apercu">
            <thead>
                <tr>
                    <th scope="col"><span class="sr-only">Créer</span></th>
                    <th scope="col">Ligne</th>
                    <th scope="col">Nom</th>
                    <th scope="col">Prénom</th>
                    <th scope="col">Identifiant proposé</th>
                    <th scope="col">Remarque</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lignes as $index => $ligne): ?>
                    <tr<?= $ligne['erreur'] !== '' ? ' class="comptes-apercu__ligne--erreur"' : '' ?>>
                        <td>
                            <?php if ($ligne['erreur'] === ''): ?>
                                <input type="checkbox" name="creer[<?= (int) $index ?>]" value="1"
                                       id="creer_<?= (int) $index ?>"
                                       aria-label="Créer la ligne <?= (int) $ligne['numero'] ?>"
                                       <?= !empty($ligne['creer']) ? 'checked' : '' ?>>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $ligne['numero'] ?></td>
                        <td><?= $ligne['nom'] !== '' ? e((string) $ligne['nom']) : '—' ?></td>
                        <td><?= $ligne['prenom'] !== '' ? e((string) $ligne['prenom']) : '—' ?></td>
                        <td>
                            <?php if ($ligne['erreur'] === ''): ?>
                                <input type="text" name="identifiant[<?= (int) $index ?>]"
                                       id="identifiant_<?= (int) $index ?>"
                                       value="<?= e((string) $ligne['identifiant']) ?>"
                                       maxlength="30" pattern="[a-z][a-z0-9._\-]{2,29}"
                                       aria-label="Identifiant de la ligne <?= (int) $ligne['numero'] ?>">
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($ligne['erreur'] !== ''): ?>
                                <span class="comptes-apercu__probleme"><?= e((string) $ligne['erreur']) ?></span>
                            <?php elseif ($ligne['remarque'] !== ''): ?>
                                <span class="comptes-apercu__remarque"><?= e((string) $ligne['remarque']) ?></span>
                            <?php else: ?>
                                <span class="discret">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="note">
        Les lignes en rouge ne peuvent pas être créées : corrigez-les dans la liste collée.
        Une personne déjà présente dans la branche est décochée : cochez-la si vous voulez
        vraiment créer un second compte avec le même nom.
    </p>

    <p class="actions">
        <button type="submit" class="bouton">Créer les comptes cochés</button>
        <a class="bouton bouton--secondaire" href="/admin/comptes/nouvelle">Annuler</a>
    </p>
</form>
