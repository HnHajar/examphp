<?php
/**
 * api/stats.php — Endpoint statistiques temps réel (Partie 4.1 & 4.2)
 *
 * Retourne en JSON :
 *   - nombre d'inscrits par événement (avec taux de remplissage)
 *   - top 3 des événements les plus remplis
 *   - nouveaux inscrits dans les dernières 24h
 *   - KPI globaux (total inscrits, événements actifs, événements complets)
 *
 * Méthode : GET
 * Auth    : session organisateur requise
 */

require_once __DIR__ . '/../config/db.php';
session_start();

/* ── En-têtes ─────────────────────────────────────────────────── */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

/* ── Contrôle d'accès ─────────────────────────────────────────── */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'organizer') {
    http_response_code(403);
    echo json_encode(['error' => 'Accès refusé']);
    exit;
}

/* ── PDO ──────────────────────────────────────────────────────── */
try {
    $pdo = getDB(); // singleton défini dans config/db.php
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Connexion base de données impossible']);
    exit;
}

/* ── Helpers ──────────────────────────────────────────────────── */

/**
 * Calcule le taux de remplissage en %.
 * Retourne 0 si capacity vaut 0 (évite la division par zéro).
 */
function fillRate(int $registrations, int $capacity): int
{
    if ($capacity === 0) return 0;
    return (int) round(($registrations / $capacity) * 100);
}

try {

    /* ── 1. Inscrits par événement ──────────────────────────────
     * Jointure events ← registrations (LEFT JOIN pour inclure
     * les événements sans inscrit). Filtre sur les événements
     * dont la date est à venir ou aujourd'hui.
     */
    $stmtEvents = $pdo->query(
        "SELECT
            e.id,
            e.title,
            DATE_FORMAT(e.event_date, '%d/%m/%Y') AS event_date,
            e.capacity,
            COALESCE(COUNT(r.id), 0)              AS registrations
         FROM events e
         LEFT JOIN registrations r ON r.event_id = e.id
                                   AND r.status != 'cancelled'
         GROUP BY e.id, e.title, e.event_date, e.capacity
         ORDER BY e.event_date ASC"
    );
    $events = $stmtEvents->fetchAll(PDO::FETCH_ASSOC);

    // Ajoute fill_rate à chaque ligne
    $events = array_map(function (array $ev): array {
        $ev['registrations'] = (int) $ev['registrations'];
        $ev['capacity']      = (int) $ev['capacity'];
        $ev['fill_rate']     = fillRate($ev['registrations'], $ev['capacity']);
        return $ev;
    }, $events);


    /* ── 2. Top 3 des événements les plus remplis ───────────────
     * Tri décroissant par taux de remplissage calculé côté PHP
     * (plus portable que ORDER BY … / … sur MySQL < 8).
     */
    $sorted = $events;
    usort($sorted, fn($a, $b) => $b['fill_rate'] <=> $a['fill_rate']);
    $top3 = array_slice($sorted, 0, 3);


    /* ── 3. Nouveaux inscrits dans les dernières 24 h ───────────*/
    $stmtRecent = $pdo->prepare(
        "SELECT
            r.id,
            r.created_at,
            u.name  AS user_name,
            e.title AS event_title
         FROM registrations r
         JOIN users  u ON u.id = r.user_id
         JOIN events e ON e.id = r.event_id
         WHERE r.created_at >= NOW() - INTERVAL 24 HOUR
           AND r.status != 'cancelled'
         ORDER BY r.created_at DESC
         LIMIT 20"
    );
    $stmtRecent->execute();
    $recentRegistrations = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);


    /* ── 4. KPI globaux ─────────────────────────────────────────*/
    $totalRegistrations = array_sum(array_column($events, 'registrations'));
    $activeEvents       = count(array_filter($events, fn($e) => $e['registrations'] > 0));
    $fullEvents         = count(array_filter($events, fn($e) => $e['fill_rate'] >= 100));
    $newLast24h         = count($recentRegistrations);


    /* ── 5. Réponse JSON ────────────────────────────────────────*/
    echo json_encode([
        // KPI
        'total_registrations' => $totalRegistrations,
        'active_events'       => $activeEvents,
        'full_events'         => $fullEvents,
        'new_last_24h'        => $newLast24h,

        // Listes
        'events'              => $events,
        'top3'                => $top3,
        'recent_registrations'=> $recentRegistrations,

        // Meta
        'generated_at'        => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error'   => 'Erreur de requête SQL',
        'details' => $e->getMessage(), // à supprimer en production
    ]);
}