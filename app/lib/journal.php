<?php
/**
 * Journal des connexions et alertes de sécurité.
 *
 * Chaque tentative de connexion est enregistrée dans « login_log » :
 * réussie, échouée (avec la raison) ou bloquée. Les administrateurs la
 * consultent dans Administration → Journal des connexions ; deux alertes
 * discrètes s'affichent sur le tableau de bord :
 *   - trop d'échecs en une heure ;
 *   - connexion d'un administrateur depuis une adresse jamais vue.
 *
 * Écrire dans le journal ne doit JAMAIS empêcher quelqu'un de se connecter.
 */

declare(strict_types=1);

/** Seuil d'alerte : nombre d'échecs de connexion en une heure. */
const JOURNAL_SEUIL_ECHECS = 10;

/** Durées de conservation proposées (en jours). */
function journal_conservations(): array
{
    return [30 => '30 jours', 90 => '90 jours (par défaut)', 180 => '6 mois', 365 => '1 an'];
}

function journal_conservation_jours(): int
{
    $valeur = (int) site_setting('journal_conservation_jours');
    return array_key_exists($valeur, journal_conservations()) ? $valeur : 90;
}

/**
 * Enregistre une tentative de connexion.
 *
 * @param bool $estAdmin le compte est-il un administrateur ? (alerte « nouvelle adresse »)
 */
function journal_enregistrer(
    string $identifiant,
    string $ip,
    bool $succes,
    string $detail,
    ?int $compteId = null,
    bool $estAdmin = false
): void {
    try {
        // Un robot qui insiste sur un compte déjà bloqué ne doit pas remplir le
        // journal : on n'inscrit qu'une ligne « bloqué » par identifiant et par
        // adresse toutes les cinq minutes. La limitation des essais, elle,
        // continue de s'appliquer à chaque tentative.
        if (!$succes && str_starts_with($detail, 'bloqué')) {
            $dejaNote = (int) db_value(
                'SELECT COUNT(*) FROM login_log
                 WHERE success = 0 AND identifier = ? AND ip = ? AND detail LIKE ?
                   AND created_at > ?',
                [mb_substr($identifiant, 0, 60, 'UTF-8'), $ip, 'bloqué%', time() - 300],
                0
            );
            if ($dejaNote > 0) {
                return;
            }
        }

        $nouvelleIp = 0;

        if ($succes && $estAdmin && $compteId !== null) {
            // Nouvelle adresse = jamais vue avec succès pour ce compte, alors que
            // le compte s'était déjà connecté (la toute première connexion
            // ne déclenche donc jamais d'alerte).
            $dejaConnecte = (int) db_value(
                'SELECT COUNT(*) FROM login_log WHERE account_id = ? AND success = 1',
                [$compteId],
                0
            );
            $ipConnue = (int) db_value(
                'SELECT COUNT(*) FROM login_log WHERE account_id = ? AND success = 1 AND ip = ?',
                [$compteId, $ip],
                0
            );
            $nouvelleIp = ($dejaConnecte > 0 && $ipConnue === 0) ? 1 : 0;
        }

        db_exec(
            'INSERT INTO login_log (account_id, identifier, ip, success, detail, new_ip, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $compteId,
                mb_substr($identifiant, 0, 60, 'UTF-8'),
                $ip,
                $succes ? 1 : 0,
                mb_substr($detail, 0, 80, 'UTF-8'),
                $nouvelleIp,
                time(),
            ]
        );
    } catch (Throwable) {
        // Le journal ne doit jamais faire échouer une connexion.
    }
}

/**
 * Alertes à afficher sur le tableau de bord (vide = rien à signaler).
 *
 * @return array{echecs:?array, nouvelles_ip:array}
 */
function journal_alertes(): array
{
    $vues   = (int) site_setting('journal_alertes_vues');
    $depuis = max(time() - 3600, $vues);

    $echecs = null;
    $ligne  = db_one(
        'SELECT COUNT(*) AS nb, COUNT(DISTINCT ip) AS ips FROM login_log
         WHERE success = 0 AND created_at > ?',
        [$depuis]
    );
    if ($ligne !== null && (int) $ligne['nb'] > JOURNAL_SEUIL_ECHECS) {
        $echecs = ['nb' => (int) $ligne['nb'], 'ips' => (int) $ligne['ips']];
    }

    $nouvelles = db_all(
        'SELECT l.ip, l.created_at, a.username
         FROM login_log l LEFT JOIN accounts a ON a.id = l.account_id
         WHERE l.new_ip = 1 AND l.created_at > ?
         ORDER BY l.created_at DESC LIMIT 10',
        [max(time() - 7 * 86400, $vues)]
    );

    return ['echecs' => $echecs, 'nouvelles_ip' => $nouvelles];
}

/** « J'ai pris connaissance » : masque les alertes actuelles. */
function journal_acquitter(): void
{
    save_site_setting('journal_alertes_vues', (string) time());
}

/** Supprime les lignes plus anciennes que la durée de conservation. */
function journal_purger(): int
{
    return db_exec(
        'DELETE FROM login_log WHERE created_at < ?',
        [time() - journal_conservation_jours() * 86400]
    );
}
