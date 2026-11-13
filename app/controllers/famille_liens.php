<?php
/** Ajout explicite d'un seul lien sur la fiche, après aperçu. */
declare(strict_types=1);

/** Comparaison indépendante de la casse et des accents courants. */
function famille_lien_normaliser(string $texte): string
{
    return strtr(mb_strtolower(trim($texte)), [
        'à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ã'=>'a','å'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ô'=>'o','ö'=>'o','ó'=>'o','ò'=>'o','õ'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ú'=>'u',
        'ç'=>'c','ñ'=>'n','ÿ'=>'y','œ'=>'oe','æ'=>'ae',
    ]);
}

class FamilleLienIntrouvable extends DomainException {}

/** Renvoie le plan complet et sa phrase ; ne fait aucune écriture. */
function famille_lien_plan(int $id, int $autreId, string $type, array $compte, ?int $choix = null): array
{
    if (!in_array($type, ['parent', 'conjoint', 'enfant', 'fratrie'], true) || $id <= 0 || $autreId <= 0 || $id === $autreId) {
        throw new DomainException('Choisissez deux personnes différentes et un lien.');
    }
    $moi = find_viewable_person($id);
    $autre = find_viewable_person($autreId);
    $ids = array_map('intval', array_column(personne_saisie_suggestions(), 'id'));
    if (!$moi || !$autre || !journaliste_peut_modifier('person', $moi, $compte)
        || !in_array($autreId, $ids, true) || !journaliste_peut_modifier('person', $autre, $compte)) {
        throw new FamilleLienIntrouvable('Cette fiche n’existe pas ou n’est pas accessible.');
    }
    $moiNom = person_full_name($moi);
    $autreNom = person_full_name($autre);
    $liens = [];
    $famille = 0;
    $parents = [];
    $options = [];
    $modificationFamille = false;
    $enfantsFamille = [];
    if ($type === 'conjoint') {
        if (db_value('SELECT COUNT(*) FROM families WHERE (husband_id = ? AND wife_id = ?) OR (husband_id = ? AND wife_id = ?)', [$id, $autreId, $autreId, $id], 0)) {
            throw new DomainException('Ce lien existe déjà.');
        }
        $phrase = "$moiNom et $autreNom deviendront conjoints.";
    } elseif ($type === 'fratrie') {
        $familles = db_all('SELECT f.* FROM families f JOIN family_children fc ON fc.family_id = f.id WHERE fc.person_id = ? AND (f.husband_id IS NOT NULL OR f.wife_id IS NOT NULL) ORDER BY f.id', [$id]);
        if (count($familles) !== 1) {
            throw new DomainException('Pour ajouter une fratrie, cette fiche doit avoir une seule famille de parents connue.');
        }
        $f = $familles[0];
        $famille = (int) $f['id'];
        if (db_value('SELECT COUNT(*) FROM family_children WHERE family_id = ? AND person_id = ?', [$famille, $autreId], 0)) {
            throw new DomainException('Ce lien existe déjà.');
        }
        foreach (['husband_id', 'wife_id'] as $colonne) {
            $pid = (int) ($f[$colonne] ?? 0);
            if ($pid === 0) continue;
            $p = find_viewable_person($pid);
            if (!$p || !in_array($pid, $ids, true) || !journaliste_peut_modifier('person', $p, $compte)) {
                throw new DomainException('Cette famille ne peut pas être utilisée pour ce lien.');
            }
            $parents[] = ['id' => $pid, 'nom' => person_full_name($p), 'colonne' => $colonne];
            $liens[] = [$pid, $autreId];
        }
        $phrase = $autreNom . ' deviendra l’enfant de ' . implode(' et ', array_column($parents, 'nom')) . ' et partagera cette famille avec ' . $moiNom . '.';
    } else {
        $parent = $type === 'parent' ? $autre : $moi;
        $enfant = $type === 'parent' ? $moi : $autre;
        $pid = (int) $parent['id'];
        $eid = (int) $enfant['id'];
        $colonne = ($parent['sex'] ?? '') === 'F' ? 'wife_id' : 'husband_id';
        $parents[] = ['id' => $pid, 'nom' => person_full_name($parent), 'colonne' => $colonne];
        $liens[] = [$pid, $eid];
        // On ne propose que des familles dont toutes les conséquences sont
        // visibles et modifiables. Un parent ajouté à une famille devient le
        // parent de TOUS ses enfants, pas seulement de la fiche courante.
        $candidats = $type === 'enfant'
            ? db_all('SELECT * FROM families WHERE (husband_id = ? OR wife_id = ?) AND husband_id IS NOT NULL AND wife_id IS NOT NULL ORDER BY id', [$pid, $pid])
            : db_all('SELECT f.* FROM families f JOIN family_children fc ON fc.family_id = f.id WHERE fc.person_id = ? AND ((f.husband_id IS NULL AND f.wife_id IS NOT NULL) OR (f.wife_id IS NULL AND f.husband_id IS NOT NULL)) ORDER BY f.id', [$eid]);
        foreach ($candidats as $f) {
            if ($type === 'parent' && !empty($f[$colonne])) continue;
            $coId = $type === 'enfant'
                ? ((int) $f['husband_id'] === $pid ? (int) $f['wife_id'] : (int) $f['husband_id'])
                : (int) ($f['husband_id'] ?: $f['wife_id']);
            if ($coId <= 0 || $coId === $pid || $coId === $eid) continue;
            $co = find_viewable_person($coId);
            if (!$co || !in_array($coId, $ids, true) || !journaliste_peut_modifier('person', $co, $compte)) continue;
            $enfants = [];
            if ($type === 'parent') {
                foreach (db_all('SELECT p.* FROM persons p JOIN family_children fc ON fc.person_id=p.id WHERE fc.family_id=? ORDER BY p.id', [(int) $f['id']]) as $e) {
                    $eId = (int) $e['id'];
                    if (!find_viewable_person($eId) || !in_array($eId, $ids, true) || !journaliste_peut_modifier('person', $e, $compte)) {
                        $enfants = null; break;
                    }
                    $enfants[] = ['id' => $eId, 'nom' => person_full_name($e)];
                }
                if ($enfants === null) continue;
            }
            $options[] = ['id' => (int) $f['id'], 'conjoint_id' => $coId, 'nom' => person_full_name($co), 'colonne' => (int) $f['husband_id'] === $coId ? 'husband_id' : 'wife_id', 'enfants' => $enfants];
        }
        if ($choix === null) $choix = count($options) === 1 ? $options[0]['id'] : 0;
        if ($choix !== 0) {
            $choisies = array_values(array_filter($options, static fn (array $o): bool => $o['id'] === $choix));
            if (count($choisies) !== 1) throw new DomainException('Cette famille ne peut pas être choisie.');
            $o = $choisies[0];
            $famille = $o['id'];
            $parents[] = ['id' => $o['conjoint_id'], 'nom' => $o['nom'], 'colonne' => $o['colonne']];
            if ($type === 'enfant') {
                $liens[] = [$o['conjoint_id'], $eid];
            } else {
                $modificationFamille = true;
                $enfantsFamille = $o['enfants'];
                $liens = [];
                foreach ($enfantsFamille as $e) $liens[] = [$pid, $e['id']];
            }
            $phrase = person_full_name($enfant) . ' deviendra l’enfant de ' . person_full_name($parent) . ' et ' . $o['nom'] . '.';
            if ($type === 'parent' && count($enfantsFamille) > 1) {
                $phrase .= ' Le parent sera aussi ajouté à : ' . implode(', ', array_column(array_filter($enfantsFamille, static fn (array $e): bool => $e['id'] !== $eid), 'nom')) . '.';
            }
        } else {
            $phrase = person_full_name($enfant) . ' deviendra l’enfant de ' . person_full_name($parent) . ', sans conjoint dans cette nouvelle famille.';
        }
    }
    if (personne_saisie_liens_creeraient_cycle($liens)) throw new DomainException('Ce lien créerait un cycle de parenté.');
    $parEnfant = [];
    foreach ($liens as [$pid, $eid]) $parEnfant[$eid][] = $pid;
    foreach ($parEnfant as $eid => $ajouts) {
        $existants = array_map('intval', array_column(person_parents((int) $eid), 'id'));
        foreach ($ajouts as $pid) {
            if (in_array($pid, $existants, true)) throw new DomainException('Ce lien existe déjà.');
        }
        if (count(array_unique(array_merge($existants, $ajouts))) > 2) throw new DomainException('Cette personne aurait plus de deux parents.');
        foreach ($parents as $p) {
            if (!in_array($p['id'], $ajouts, true)) continue;
            $col = $p['colonne'];
            $occupants = db_all("SELECT DISTINCT f.$col AS id FROM families f JOIN family_children fc ON fc.family_id = f.id WHERE fc.person_id = ? AND f.$col IS NOT NULL", [(int) $eid]);
            foreach ($occupants as $oc) if ((int) $oc['id'] !== $p['id']) throw new DomainException('Une place de parent est déjà occupée.');
        }
    }
    return ['id' => $id, 'autre' => $autreId, 'type' => $type, 'famille' => $famille, 'parents' => $parents, 'phrase' => $phrase, 'options' => $options, 'modifier_famille' => $modificationFamille, 'enfants_famille' => $enfantsFamille, 'liens' => $liens];
}

function famille_lien_erreur(string $message): void
{
    http_response_code(400);
    render('erreur', ['titre' => 'Lien impossible', 'message' => $message]);
}

function famille_lien_apercu(array $params): void
{
    $compte = journaliste_personne_garde();
    csrf_check();
    try {
        $plan = famille_lien_plan((int) ($params['id'] ?? 0), (int) post('personne'), post('lien'), $compte, post('choix_famille') === '' ? null : (int) post('choix_famille'));
        $signature = hash_hmac('sha256', json_encode($plan, JSON_THROW_ON_ERROR) . ':' . (int) $compte['id'], csrf_token());
        render('public/famille_apercu', ['titre' => 'Vérifier le lien', 'plan' => $plan, 'signature' => $signature]);
    } catch (FamilleLienIntrouvable $e) {
        http_response_code(404);
        render('erreur', ['titre' => 'Personne introuvable', 'message' => $e->getMessage()]);
    } catch (DomainException $e) {
        famille_lien_erreur($e->getMessage());
    }
}

function famille_lien_confirmer(array $params): void
{
    $compte = journaliste_personne_garde();
    csrf_check();
    try {
        db_transaction(static function () use ($params, $compte): void {
            $plan = famille_lien_plan((int) ($params['id'] ?? 0), (int) post('personne'), post('lien'), $compte, post('choix_famille') === '' ? null : (int) post('choix_famille'));
            $signature = hash_hmac('sha256', json_encode($plan, JSON_THROW_ON_ERROR) . ':' . (int) $compte['id'], csrf_token());
            if (!hash_equals($signature, post('signature'))) throw new DomainException('La situation a changé. Revenez à la fiche et vérifiez à nouveau le lien.');
            $id = $plan['id']; $autre = $plan['autre'];
            $familleAvant = null;
            $enfantsAvant = [];
            if ($plan['famille'] > 0 && in_array($plan['type'], ['parent', 'enfant'], true)) {
                $familleAvant = db_one('SELECT * FROM families WHERE id = ?', [$plan['famille']]);
                $enfantsAvant = array_map('intval', array_column(db_all('SELECT person_id FROM family_children WHERE family_id = ? ORDER BY person_id', [$plan['famille']]), 'person_id'));
            }
            if ($plan['famille'] > 0 && in_array($plan['type'], ['parent', 'enfant'], true)) {
                $famille = $plan['famille'];
                if ($plan['modifier_famille']) {
                    $p = $plan['parents'][0];
                    db_update('families', $famille, [$p['colonne'] => $p['id'], 'updated_at' => time()]);
                } else {
                    db_exec('INSERT INTO family_children (family_id, person_id) VALUES (?, ?)', [$famille, $autre]);
                }
            } else {
                $donnees = ['husband_id' => null, 'wife_id' => null, 'created_at' => time(), 'updated_at' => time()];
                if ($plan['type'] === 'conjoint') {
                    $moi = db_one('SELECT sex FROM persons WHERE id = ?', [$id]);
                    $donnees['husband_id'] = ($moi['sex'] ?? '') === 'F' ? $autre : $id;
                    $donnees['wife_id'] = ($moi['sex'] ?? '') === 'F' ? $id : $autre;
                } else {
                    foreach ($plan['parents'] as $p) $donnees[$p['colonne']] = $p['id'];
                }
                $famille = db_insert('families', $donnees);
                if ($plan['type'] !== 'conjoint') {
                    $enfant = $plan['type'] === 'parent' ? $id : $autre;
                    db_exec('INSERT INTO family_children (family_id, person_id) VALUES (?, ?)', [$famille, $enfant]);
                    if ($plan['type'] === 'fratrie') db_exec('INSERT INTO family_children (family_id, person_id) VALUES (?, ?)', [$famille, $id]);
                }
            }
            $nom = static fn (int $personneId): string => person_full_name((array) db_one('SELECT * FROM persons WHERE id = ?', [$personneId]));
            $entrees = [];
            foreach ($plan['liens'] as [$parentId, $enfantId]) {
                $entrees[] = ['personnes' => [$nom($parentId), $nom($enfantId)],
                    'phrase' => $nom($enfantId) . ' est devenu l’enfant de ' . $nom($parentId) . '.'];
            }
            if ($plan['type'] === 'conjoint' || $plan['type'] === 'fratrie') {
                $entrees[] = ['personnes' => [$nom($id), $nom($autre)], 'phrase' => $plan['phrase']];
            } elseif ($plan['modifier_famille'] && count($plan['parents']) === 2) {
                $entrees[] = ['personnes' => [$plan['parents'][0]['nom'], $plan['parents'][1]['nom']],
                    'phrase' => $plan['parents'][0]['nom'] . ' et ' . $plan['parents'][1]['nom'] . ' sont devenus conjoints dans cette famille.'];
            }
            foreach ($entrees as $entree) {
                db_insert('contenu_historique', [
                    'kind' => 'lien_familial', 'content_id' => $famille, 'account_id' => (int) $compte['id'],
                    'action' => 'ajout', 'avant' => json_encode($entree + ['famille' => $famille, 'type' => $plan['type'], 'avant_famille' => $familleAvant, 'avant_enfants' => $enfantsAvant], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'created_at' => time(),
                ]);
            }
            log_activity((int) $compte['id'], 'lien_familial_ajoute', 'Lien familial ajouté');
        });
        flash('succes', 'Lien familial ajouté.');
        redirect('/arbre/' . (int) $params['id']);
    } catch (FamilleLienIntrouvable $e) {
        http_response_code(404);
        render('erreur', ['titre' => 'Personne introuvable', 'message' => $e->getMessage()]);
    } catch (DomainException $e) {
        famille_lien_erreur($e->getMessage());
    }
}

/** Historique réservé aux administrateurs ; la fiche couple permet la correction. */
function admin_famille_historique(): void
{
    require_admin();
    $lignes = db_all("SELECT * FROM contenu_historique WHERE kind = 'lien_familial' AND action = 'ajout' ORDER BY id DESC LIMIT 200");
    render('admin/famille_historique', ['titre' => 'Historique des liens familiaux', 'lignes' => $lignes], 'layout_admin');
}
