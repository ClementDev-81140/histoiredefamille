<?php
/**
 * Administration — « À valider ».
 *
 * Une seule file d'attente pour tout ce que les journalistes proposent :
 * articles, documents (photos, vidéos, sons, PDF…) et recettes. Rien n'y
 * entre sans passer par un journaliste ; rien n'en sort sans une décision
 * d'administrateur :
 *
 *   - Valider  : le contenu devient visible selon la visibilité choisie
 *                (privé par défaut, jamais public par accident) ;
 *   - Refuser  : le contenu reste en base, avec un motif obligatoire que son
 *                auteur lit dans « Mes propositions ».
 *
 * Tout est noté au journal d'actions. Un courriel prévient les
 * administrateurs quand une proposition arrive (si l'envoi est configuré).
 */

declare(strict_types=1);

/** File d'attente : tout ce qui attend une décision. */
function admin_a_valider(): void
{
    require_admin();

    render('admin/a_valider', [
        'titre'        => 'À valider',
        'propositions' => propositions_en_attente(),
        'echecs'       => form_errors(),
    ], 'layout_admin');
}

/**
 * Aperçu complet d'une proposition, dans l'administration.
 *
 * @param array $params {type: string, id: int}
 */
function admin_a_valider_apercu(array $params = []): void
{
    require_admin();

    $type  = (string) ($params['type'] ?? '');
    $id    = (int) ($params['id'] ?? 0);
    $info  = proposition_type($type);
    $ligne = $info !== null ? proposition_obtenir($type, $id) : null;

    if ($info === null || $ligne === null) {
        http_response_code(404);
        render('erreur', ['titre' => 'Proposition introuvable', 'message' => 'Cette proposition n’existe pas.']);
        return;
    }

    // Branches proposables pour une visibilité « certaines branches ».
    $branches = db_all('SELECT * FROM branches ORDER BY name COLLATE NOCASE');

    render('admin/a_valider_apercu', [
        'titre'    => 'À valider — ' . proposition_titre($ligne, $type),
        'type'     => $type,
        'info'     => $info,
        'ligne'    => $ligne,
        'auteur'   => proposition_auteur($ligne),
        'branches' => $branches,
        'echecs'   => form_errors(),
    ], 'layout_admin');
}

/**
 * Valide une proposition : publication immédiate, avec la visibilité choisie.
 *
 * @param array $params {type: string, id: int}
 */
function admin_a_valider_valider(array $params = []): void
{
    require_admin();
    csrf_check();

    $type = (string) ($params['type'] ?? '');
    $id   = (int) ($params['id'] ?? 0);
    $lien = '/admin/a-valider/' . $type . '/' . $id;

    // Privé par défaut, et jamais public sans que l'administrateur l'ait coché.
    $visibilite = sanitize_visibility(post('visibility'));

    $resultat = proposition_valider($type, $id, $visibilite, (int) current_account()['id']);
    if (!$resultat['ok']) {
        flash('erreur', (string) $resultat['error']);
        redirect('/admin/a-valider');
    }

    if ($visibilite === 'branches') {
        $branches = array_map('intval', (array) ($_POST['branches'] ?? []));
        set_content_branches(proposition_kind($type), $id, $branches);
    } else {
        set_content_branches(proposition_kind($type), $id, []);
    }

    // Les lecteurs sont prévenus au moment de la publication, pas avant.
    proposition_prevenir_lecteurs($type, $id);

    flash('succes', 'Proposition validée : elle est publiée (' . mb_strtolower(visibility_label($visibilite)) . ').');
    redirect('/admin/a-valider');
}

/**
 * Refuse une proposition (motif obligatoire).
 *
 * @param array $params {type: string, id: int}
 */
function admin_a_valider_refuser(array $params = []): void
{
    require_admin();
    csrf_check();

    $type = (string) ($params['type'] ?? '');
    $id   = (int) ($params['id'] ?? 0);

    $resultat = proposition_refuser($type, $id, post('motif'), (int) current_account()['id']);
    if (!$resultat['ok']) {
        flash('erreur', (string) $resultat['error']);
        redirect('/admin/a-valider/' . $type . '/' . $id);
    }

    flash('succes', 'Proposition refusée : son auteur lit le motif dans « Mes propositions ».');
    redirect('/admin/a-valider');
}

/** Nom de contenu attendu par set_content_branches() pour un type de proposition. */
function proposition_kind(string $type): string
{
    return (string) (proposition_types()[$type]['kind'] ?? $type);
}
