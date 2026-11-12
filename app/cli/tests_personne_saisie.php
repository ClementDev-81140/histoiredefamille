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
            db_update('persons', $prive, ['visibility' => 'prive']);

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
