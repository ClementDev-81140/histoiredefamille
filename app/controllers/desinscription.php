<?php
/**
 * Désinscription en un clic, sans connexion.
 *
 * Le lien reçu par courriel contient un jeton long, propre à la personne et au
 * type d'envoi. Ce jeton remplace la connexion : il ne permet QUE de se
 * désinscrire (rien d'autre n'est accessible avec).
 *
 * Deux façons de s'en servir :
 *   - la page du site : on affiche d'abord un bouton de confirmation (POST),
 *     pour qu'un logiciel qui visiterait le lien ne désinscrive personne ;
 *   - la commande « One-Click » des logiciels de messagerie (norme
 *     List-Unsubscribe) : un POST direct, authentifié par le jeton.
 */

declare(strict_types=1);

/** Libellés des deux types d'envoi. */
function desinscription_libelle(string $type): string
{
    return $type === 'gazette' ? 'la gazette du mois' : 'les nouveaux articles';
}

/** Page de confirmation (aucune modification sur un simple GET). */
function desinscription(array $params = []): void
{
    $type  = (string) ($_GET['type'] ?? '');
    $jeton = (string) ($_GET['jeton'] ?? '');
    $but   = array_key_exists($type, courriel_buts()) ? $type : '';

    $compte = $but !== '' ? courriel_jeton_compte($jeton, $but) : null;
    $ligne  = $compte !== null ? db_one('SELECT * FROM accounts WHERE id = ?', [$compte]) : null;

    if ($ligne === null) {
        http_response_code(404);
        render('desinscription', [
            'titre'    => 'Lien de désinscription',
            'valide'   => false,
            'type'     => $but,
            'jeton'    => '',
            'compte'   => null,
            'fait'     => false,
            'message'  => 'Ce lien n’est pas valide : il a peut-être été recopié en partie. Ouvrez le lien '
                . 'tel quel depuis le courriel, ou connectez-vous et décochez la case dans « Mon compte ».',
        ]);
        return;
    }

    render('desinscription', [
        'titre'   => 'Me désinscrire',
        'valide'  => true,
        'type'    => $but,
        'jeton'   => $jeton,
        'compte'  => $ligne,
        'fait'    => false,
        'message' => '',
    ]);
}

/** Enregistre la désinscription (POST uniquement). */
function desinscription_confirmer(array $params = []): void
{
    // « One-Click » : les logiciels de messagerie envoient ce corps sans jeton
    // anti-CSRF. Le jeton signé fait office d'authentification.
    $unClic = (string) ($_POST['List-Unsubscribe'] ?? '') === 'One-Click';

    if (!$unClic) {
        csrf_check();
    }

    $type  = (string) ($_POST['type'] ?? '');
    $jeton = (string) ($_POST['jeton'] ?? '');
    $but   = array_key_exists($type, courriel_buts()) ? $type : '';

    $compte = $but !== '' ? courriel_jeton_compte($jeton, $but) : null;
    $ligne  = $compte !== null ? db_one('SELECT * FROM accounts WHERE id = ?', [$compte]) : null;

    if ($ligne === null) {
        if ($unClic) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Lien invalide.\n";
            return;
        }
        http_response_code(404);
        render('desinscription', [
            'titre'   => 'Lien de désinscription',
            'valide'  => false,
            'type'    => $but,
            'jeton'   => '',
            'compte'  => null,
            'fait'    => false,
            'message' => 'Ce lien n’est pas valide. Connectez-vous et décochez la case dans « Mon compte ».',
        ]);
        return;
    }

    // Désinscription : l'accord retombe à zéro, et la date d'accord sur les
    // articles aussi (un futur ré-abonnement repartira du bon moment).
    $valeurs = $but === 'gazette'
        ? ['notify_gazette' => 0]
        : ['notify_articles' => 0, 'notify_articles_depuis' => 0];
    $valeurs['updated_at'] = time();
    db_update('accounts', (int) $ligne['id'], $valeurs);

    log_activity(
        (int) $ligne['id'],
        'courriel_desinscription',
        'Désinscription de ' . desinscription_libelle($but) . ' (lien reçu par courriel)'
    );

    if ($unClic) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Désinscription enregistrée : " . desinscription_libelle($but) . ".\n";
        return;
    }

    render('desinscription', [
        'titre'   => 'Me désinscrire',
        'valide'  => true,
        'type'    => $but,
        'jeton'   => '',
        'compte'  => $ligne,
        'fait'    => true,
        'message' => '',
    ]);
}
