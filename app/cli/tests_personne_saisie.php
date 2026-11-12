<?php
/** Essais des contrôles préparatoires de la saisie de parenté (1.41.0). */
declare(strict_types=1);

require_once HDF_APP . '/lib/personne_saisie.php';
require_once __DIR__ . '/tests_journalistes.php';

function tests_personne_saisie(): void
{
    echo "Saisie personne — contrôles préparatoires\n";
    $sessionAvant = $_SESSION;
    try {
        db_transaction(static function (): void {
            $brancheA = tests37_branche('Saisie A');
            $brancheB = tests37_branche('Saisie B');
            $admin = tests37_compte('admin');
            $lecteur = tests37_compte('lecteur', $brancheA);
            $direct = tests37_compte('journaliste');
            tests37_regler($direct, false, false, [$brancheA]);
            $validation = tests37_compte('journaliste');
            tests37_regler($validation, true, false, [$brancheA]);

            $visible = tests37_personne('VisibleSaisie', [$brancheA]);
            $hors = tests37_personne('HorsSaisie', [$brancheB]);
            $sansBranche = tests37_personne('SansBrancheSaisie');
            $prive = tests37_personne('PriveSaisie', [$brancheA]);
            db_update('persons', $prive, ['visibility' => 'prive', 'needs_completion' => 1]);
            verifier('saisie personne : les autres fiches restent à compléter = non par défaut',
                (int) db_value('SELECT needs_completion FROM persons WHERE id = ?', [$visible], -1) === 0);
            $listeAdmin = tests_envoi_requete('GET', '/admin/arbre?a_completer=1', $admin);
            verifier('saisie personne : le filtre administrateur trouve les fiches à compléter',
                str_contains((string) $listeAdmin['corps'], 'PriveSaisie')
                && !str_contains((string) $listeAdmin['corps'], 'VisibleSaisie'));
            $ficheAdmin = tests_envoi_requete('GET', '/arbre/' . $prive, $admin);
            verifier('saisie personne : la mention est affichée à l’administrateur sur la fiche',
                str_contains((string) $ficheAdmin['corps'], 'Fiche à compléter.'));
            db_update('persons', $visible, ['needs_completion' => 1]);
            $ficheLecteur = tests_envoi_requete('GET', '/arbre/' . $visible, $lecteur);
            verifier('saisie personne : la mention reste invisible pour le lecteur',
                !str_contains((string) $ficheLecteur['corps'], 'Fiche à compléter.'));

            $_SESSION = ['account_id' => $direct];
            $ids = array_map('intval', array_column(personne_saisie_suggestions(), 'id'));
            verifier('saisie personne : la suggestion visible dans le périmètre est proposée', in_array($visible, $ids, true));
            verifier('saisie personne : une personne visible hors périmètre est exclue', !in_array($hors, $ids, true));
            verifier('saisie personne : une personne sans branche est exclue du périmètre restreint', !in_array($sansBranche, $ids, true));
            verifier('saisie personne : une personne privée est exclue des suggestions', !in_array($prive, $ids, true));
            $_SESSION = ['account_id' => $validation];
            verifier('saisie personne : le journaliste avec validation ne reçoit aucune suggestion', personne_saisie_suggestions() === []);
            $_SESSION = ['account_id' => $lecteur];
            verifier('saisie personne : le lecteur ne reçoit aucune suggestion de saisie', personne_saisie_suggestions() === []);
            $_SESSION = [];
            verifier('saisie personne : le visiteur ne reçoit aucune suggestion de saisie', personne_saisie_suggestions() === []);
            $_SESSION = ['account_id' => $admin];
            $idsAdmin = array_map('intval', array_column(personne_saisie_suggestions(), 'id'));
            verifier('saisie personne : contre-épreuve, l’administrateur voit les quatre personnes',
                count(array_intersect([$visible, $hors, $sansBranche, $prive], $idsAdmin)) === 4);

            tests_famille_liens_parcours($admin, $validation, $direct, $brancheA, $hors, $prive);

            // Visible → privé → hors : la détection doit prendre en compte
            // les chemins privés, sans jamais renvoyer leur contenu.
            tests_arbre_famille($visible, null, [$prive]);
            tests_arbre_famille($prive, null, [$hors]);
            verifier('saisie personne : une personne ne peut pas être son propre parent', personne_saisie_creerait_cycle($visible, $visible));
            verifier('saisie personne : un descendant ne peut pas devenir parent de son ancêtre', personne_saisie_creerait_cycle($hors, $visible));
            verifier('saisie personne : un ancêtre peut être ajouté comme parent de son descendant sans créer de cycle', !personne_saisie_creerait_cycle($visible, $hors));
            verifier('saisie personne : deux personnes sans lien ne créent pas de cycle', !personne_saisie_creerait_cycle($visible, $sansBranche));
            verifier('saisie personne : deux liens proposés ensemble ne peuvent pas former un cycle',
                personne_saisie_liens_creeraient_cycle([[$visible, $sansBranche], [$sansBranche, $visible]]));
            verifier('saisie personne : une fiche nouvelle ne peut pas participer à un cycle proposé',
                personne_saisie_liens_creeraient_cycle([[$visible, -1], [-1, $visible]]));
            verifier('saisie personne : des liens proposés sans cycle sont acceptés',
                !personne_saisie_liens_creeraient_cycle([[$visible, -1], [-1, $sansBranche]]));
            verifier('saisie personne : un chemin privé existant empêche un cycle proposé',
                personne_saisie_liens_creeraient_cycle([[$hors, $visible]]));
            // Une base déjà cyclique doit aussi terminer et renvoyer vrai.
            tests_arbre_famille($hors, null, [$visible]);
            verifier('saisie personne : un cycle préexistant ne fait pas boucler le contrôle', personne_saisie_creerait_cycle($hors, $prive));
            verifier('saisie personne : le cycle préexistant ne crée pas de faux positif pour une personne indépendante', !personne_saisie_creerait_cycle($hors, $sansBranche));
            throw new RuntimeException('fin du test, annulation');
        });
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'fin du test, annulation') {
            throw $e;
        }
    } finally {
        $_SESSION = $sessionAvant;
    }
}

/** Parcours réel sans JavaScript : aperçu, confirmation et refus. */
function tests_famille_liens_parcours(int $admin, int $validation, int $direct, int $branche, int $hors, int $prive): void
{
    $moi = tests37_personne('FamilleMoi', [$branche]);
    $parent = tests37_personne('FamilleParent', [$branche]);
    $enfant = tests37_personne('FamilleEnfant', [$branche]);
    $frere = tests37_personne('FamilleFrere', [$branche]);
    $conjoint = tests37_personne('FamilleConjoint', [$branche]);
    $url = '/arbre/' . $moi . '/famille/';
    $fiche = tests37_requete('GET', '/arbre/' . $moi . '?famille_recherche=familleparent', $admin);
    verifier('famille : recherche et choix visibles sur la fiche', str_contains($fiche['corps'], 'FamilleParent') && str_contains($fiche['corps'], 'Ajouter un parent'), $fiche['erreur']);
    $avant = (int) db_value('SELECT COUNT(*) FROM families');
    $r = tests37_requete('POST', $url . 'apercu', $admin, ['personne' => (string) $parent, 'lien' => 'parent']);
    verifier('famille : aperçu sans écriture', $r['code'] === 200 && str_contains($r['corps'], 'deviendra l’enfant de') && (int) db_value('SELECT COUNT(*) FROM families') === $avant, 'code=' . $r['code'] . ' ' . $r['erreur'] . ' ' . strip_tags($r['corps']));
    preg_match('/name="signature" value="([a-f0-9]+)"/', $r['corps'], $match);
    $signature = $match[1] ?? '';
    $refus = tests37_requete('POST', $url . 'confirmer', $admin, ['personne' => (string) $parent, 'lien' => 'parent', 'signature' => 'invalide']);
    verifier('famille : confirmation falsifiée refusée sans écriture', $refus['code'] === 400 && (int) db_value('SELECT COUNT(*) FROM families') === $avant, $refus['erreur']);
    $ok = tests37_requete('POST', $url . 'confirmer', $admin, ['personne' => (string) $parent, 'lien' => 'parent', 'signature' => $signature]);
    verifier('famille : confirmation crée uniquement le lien parent annoncé', $ok['code'] === 303 && in_array($parent, array_map('intval', array_column(person_parents($moi), 'id')), true), $ok['erreur']);
    foreach ([['enfant', $enfant], ['conjoint', $conjoint], ['fratrie', $frere]] as [$type, $cible]) {
        $r = tests37_requete('POST', $url . 'apercu', $admin, ['personne' => (string) $cible, 'lien' => $type]);
        preg_match('/name="signature" value="([a-f0-9]+)"/', $r['corps'], $match);
        $apercu = $r['code'] === 200 && !empty($match[1]);
        $ok = tests37_requete('POST', $url . 'confirmer', $admin, ['personne' => (string) $cible, 'lien' => $type, 'signature' => $match[1] ?? '']);
        $existe = match ($type) {
            'enfant' => in_array($moi, array_map('intval', array_column(person_parents($cible), 'id')), true),
            'fratrie' => in_array($parent, array_map('intval', array_column(person_parents($cible), 'id')), true)
                && in_array($cible, array_map('intval', array_column(person_siblings($moi), 'id')), true),
            default => (int) db_value('SELECT COUNT(*) FROM families WHERE (husband_id = ? AND wife_id = ?) OR (husband_id = ? AND wife_id = ?)', [$moi, $cible, $cible, $moi]) === 1,
        };
        verifier('famille : aperçu et confirmation ' . $type, $apercu && $ok['code'] === 303 && $existe, $r['erreur'] . ' ' . $ok['erreur']);
    }
    $cycle = tests37_requete('POST', $url . 'apercu', $admin, ['personne' => (string) $enfant, 'lien' => 'parent']);
    verifier('famille : cycle refusé avant écriture', $cycle['code'] === 400 && str_contains($cycle['corps'], 'cycle'), $cycle['erreur']);
    $sansCsrf = tests_envoi_requete('POST', $url . 'apercu', $admin, ['personne' => (string) $parent, 'lien' => 'parent']);
    verifier('famille : un aperçu sans jeton CSRF est refusé', $sansCsrf['code'] === 403, $sansCsrf['erreur']);
    $validationConfirm = tests37_requete('POST', $url . 'confirmer', $validation, ['personne' => (string) $conjoint, 'lien' => 'conjoint', 'signature' => $signature]);
    verifier('famille : journaliste avec validation ne peut pas confirmer', $validationConfirm['code'] === 403, $validationConfirm['erreur']);
    $nouvelleCible = tests37_personne('FamilleCibleChangee', [$branche]);
    $r = tests37_requete('POST', $url . 'apercu', $admin, ['personne' => (string) $nouvelleCible, 'lien' => 'enfant']);
    preg_match('/name="signature" value="([a-f0-9]+)"/', $r['corps'], $match);
    db_update('persons', $nouvelleCible, ['first_name' => 'AutreNom']);
    $refusChange = tests37_requete('POST', $url . 'confirmer', $admin, ['personne' => (string) $nouvelleCible, 'lien' => 'enfant', 'signature' => $match[1] ?? '']);
    verifier('famille : changement après aperçu impose une nouvelle vérification', $refusChange['code'] === 400 && person_parents($nouvelleCible) === [], $refusChange['erreur']);
    $refus = tests37_requete('POST', $url . 'apercu', $validation, ['personne' => (string) $parent, 'lien' => 'parent']);
    verifier('famille : journaliste avec validation reçoit 403', $refus['code'] === 403, $refus['erreur']);
    $refus = tests37_requete('POST', $url . 'apercu', $direct, ['personne' => (string) $hors, 'lien' => 'conjoint']);
    verifier('famille : cible hors périmètre refusée', $refus['code'] === 404, $refus['erreur']);
    $page = tests37_requete('GET', '/arbre/' . $moi . '?famille_recherche=privesaisie', $direct);
    verifier('famille : recherche ne révèle pas les personnes privées', !str_contains($page['corps'], 'PriveSaisie'), $page['erreur']);
}
