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
function famille_lien_plan(int $id, int $autreId, string $type, array $compte): array
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
        $phrase = person_full_name($enfant) . ' deviendra l’enfant de ' . person_full_name($parent) . '.';
    }
    foreach ($liens as [$pid, $eid]) {
        if (personne_saisie_creerait_cycle($pid, $eid)) throw new DomainException('Ce lien créerait un cycle de parenté.');
        $parentsExistants = person_parents($eid);
        if (in_array($pid, array_map('intval', array_column($parentsExistants, 'id')), true)) {
            throw new DomainException('Ce lien existe déjà.');
        }
        if (count($parentsExistants) >= 2) throw new DomainException('Cette personne a déjà deux parents enregistrés.');
    }
    if (personne_saisie_liens_creeraient_cycle($liens)) throw new DomainException('Ce lien créerait un cycle de parenté.');
    if ($liens !== []) {
        $enfantId = (int) $liens[0][1];
        $existants = array_map('intval', array_column(person_parents($enfantId), 'id'));
        if (count(array_unique(array_merge($existants, array_column($liens, 0)))) > 2) throw new DomainException('Cette personne aurait plus de deux parents.');
        foreach ($parents as $p) {
            $col = $p['colonne'];
            $occupants = db_all("SELECT DISTINCT f.$col AS id FROM families f JOIN family_children fc ON fc.family_id = f.id WHERE fc.person_id = ? AND f.$col IS NOT NULL", [$enfantId]);
            foreach ($occupants as $oc) if ((int) $oc['id'] !== $p['id']) throw new DomainException('Une place de parent est déjà occupée.');
        }
    }
    return ['id' => $id, 'autre' => $autreId, 'type' => $type, 'famille' => $famille, 'parents' => $parents, 'phrase' => $phrase];
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
        $plan = famille_lien_plan((int) ($params['id'] ?? 0), (int) post('personne'), post('lien'), $compte);
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
            $plan = famille_lien_plan((int) ($params['id'] ?? 0), (int) post('personne'), post('lien'), $compte);
            $signature = hash_hmac('sha256', json_encode($plan, JSON_THROW_ON_ERROR) . ':' . (int) $compte['id'], csrf_token());
            if (!hash_equals($signature, post('signature'))) throw new DomainException('La situation a changé. Revenez à la fiche et vérifiez à nouveau le lien.');
            $id = $plan['id']; $autre = $plan['autre'];
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
                if ($plan['type'] === 'fratrie') {
                    db_exec('INSERT INTO family_children (family_id, person_id) VALUES (?, ?)', [$famille, $id]);
                }
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
