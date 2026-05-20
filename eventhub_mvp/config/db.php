<?php
/**
 * ╔══════════════════════════════════════════════════════════════╗
 * ║  EventHub Pro — config/db.php                               ║
 * ║  Connexion à la base de données via PDO                     ║
 * ║  ENSA Marrakech — Examen PHP Avancé                         ║
 * ╚══════════════════════════════════════════════════════════════╝
 *
 * STATUT : ✅ Fourni — À configurer uniquement (credentials)
 *
 * Ce fichier est fonctionnel. Adaptez uniquement les constantes
 * DB_HOST, DB_NAME, DB_USER, DB_PASS à votre environnement local.
 */

// ── Configuration ─────────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_PORT', '3307');
define('DB_NAME', 'eventhub_db');   // Nom de votre base de données
define('DB_USER', 'root');          // Votre utilisateur MySQL
define('DB_PASS', '');              // Votre mot de passe MySQL
define('DB_CHARSET', 'utf8mb4');

/**
 * Retourne une instance PDO configurée et prête à l'emploi.
 *
 * Options activées :
 *  - ERRMODE_EXCEPTION  → les erreurs SQL lèvent des exceptions PHP
 *  - FETCH_ASSOC        → fetch() retourne des tableaux associatifs par défaut
 *  - EMULATE_PREPARES   → désactivé (vraies requêtes préparées côté MySQL)
 *
 * @return PDO
 * @throws PDOException si la connexion échoue
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // En production : logger l'erreur, ne jamais l'afficher
            error_log('[EventHub] DB Connection failed: ' . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'error'   => 'Erreur de connexion à la base de données.'
            ]));
        }
    }

    return $pdo;
}
