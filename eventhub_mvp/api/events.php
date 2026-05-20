<?php
/**
 * ╔══════════════════════════════════════════════════════════════╗
 * ║  EventHub Pro — api/events.php                              ║
 * ║  Endpoint AJAX — Liste et recherche des événements          ║
 * ║  ENSA Marrakech — Examen PHP Avancé                         ║
 * ╚══════════════════════════════════════════════════════════════╝
 *
 * STATUT : ✅ Complété — Partie 1.3
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/db.php';

// ── Lecture des paramètres (GET ou POST JSON) ─────────────────────────────
$params = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body   = file_get_contents('php://input');
    $params = json_decode($body, true) ?? [];
} else {
    $params = $_GET;
}

$keyword  = isset($params['keyword'])   ? trim($params['keyword'])    : '';
$category = isset($params['category'])  ? trim($params['category'])   : '';
$dateFrom = isset($params['date_from']) ? trim($params['date_from'])  : '';
$dateTo   = isset($params['date_to'])   ? trim($params['date_to'])    : '';
$hasPlaces= isset($params['has_places'])? (bool)$params['has_places'] : false;
$page     = isset($params['page'])      ? max(1, (int)$params['page']): 1;
$perPage  = 6;

try {
    $pdo    = getDB();
    $result = searchEvents($pdo, $keyword, $category, $dateFrom, $dateTo, $hasPlaces, $page, $perPage);

    echo json_encode([
        'success' => true,
        'data'    => $result['events'],
        'meta'    => [
            'total'    => $result['total'],
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => ceil($result['total'] / $perPage),
        ]
    ]);

} catch (Exception $e) {
    error_log('[EventHub] api/events.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur.']);
}


// ═════════════════════════════════════════════════════════════════════════
// IMPLÉMENTATION searchEvents() — Partie 1.3
// ═════════════════════════════════════════════════════════════════════════

/**
 * Recherche des événements avec filtres combinables.
 *
 * STRATÉGIE CHOISIE : tableau $conditions[] + tableau $bindings[] (named params)
 *
 * JUSTIFICATION :
 *   On construit deux tableaux en parallèle :
 *     - $conditions[] reçoit les fragments SQL ("e.category = :category")
 *     - $bindings[]   reçoit les valeurs associées ([':category' => 'tech'])
 *   À la fin, on assemble : WHERE implode(' AND ', $conditions)
 *   puis on passe $bindings directement à execute().
 *
 *   Avantages vs alternatives :
 *   → vs sprintf/concaténation : zéro risque d'injection, PDO compile la
 *     structure avant de recevoir les valeurs.
 *   → vs bindParam() en boucle : plus lisible, pas besoin de références,
 *     execute(array) lie tout en une seule opération.
 *   → vs ORM : pas de dépendance externe, code lisible par n'importe quel
 *     correcteur, contrôle total sur le SQL généré.
 *
 *   La pagination utilise deux requêtes séparées : une COUNT(*) sur les
 *   mêmes conditions (pour le total réel), une SELECT avec LIMIT/OFFSET
 *   (pour la page courante). On réutilise $conditions et $bindings dans
 *   les deux pour garder la logique DRY.
 */
function searchEvents(
    PDO    $pdo,
    string $keyword   = '',
    string $category  = '',
    string $dateFrom  = '',
    string $dateTo    = '',
    bool   $hasPlaces = false,
    int    $page      = 1,
    int    $perPage   = 6
): array {

    // ── Requête de base (fournie — conservée intacte) ─────────────────
    $baseSelect = "SELECT e.id,
                          e.title,
                          e.description,
                          e.event_date,
                          e.location,
                          e.capacity,
                          e.category,
                          e.organizer_email,
                          COUNT(r.id)                                  AS registered_count,
                          (e.capacity - COUNT(r.id))                   AS available_places,
                          ROUND(COUNT(r.id) / e.capacity * 100)        AS fill_percentage
                   FROM   events e
                   LEFT JOIN registrations r ON r.event_id = e.id";

    $conditions = [];
    $bindings   = [];

    // ════════════════════════════════════════════════════════════════════
    // ✅ IMPLÉMENTATION 1.3 — Conditions dynamiques
    // ════════════════════════════════════════════════════════════════════

    // FILTRE 1 — Mot-clé : recherche dans title ET description
    // LIKE avec % de chaque côté = recherche partielle (contient)
    // On utilise un seul binding :keyword réutilisé deux fois via CONCAT
    // car PDO named params ne peut pas binder le même nom deux fois dans
    // certains drivers → on duplique sous :keyword2 pour être portable
    if ($keyword !== '') {
        $conditions[]        = '(e.title LIKE :keyword OR e.description LIKE :keyword2)';
        $bindings[':keyword']  = '%' . $keyword . '%';
        $bindings[':keyword2'] = '%' . $keyword . '%';
    }

    // FILTRE 2 — Catégorie exacte (slug : 'tech', 'design'…)
    // Correspondance exacte, pas de LIKE → index utilisé par MySQL
    if ($category !== '') {
        $conditions[]         = 'e.category = :category';
        $bindings[':category'] = $category;
    }

    // FILTRE 3 — Date minimum (borne inférieure)
    // On filtre sur event_date >= début de la journée demandée
    if ($dateFrom !== '') {
        $conditions[]          = 'e.event_date >= :date_from';
        $bindings[':date_from'] = $dateFrom . ' 00:00:00';
    }

    // FILTRE 4 — Date maximum (borne supérieure)
    // On filtre jusqu'à la fin de la journée (23:59:59) pour inclure
    // les événements du jour $dateTo quelle que soit leur heure
    if ($dateTo !== '') {
        $conditions[]        = 'e.event_date <= :date_to';
        $bindings[':date_to'] = $dateTo . ' 23:59:59';
    }

    // FILTRE 5 — Places disponibles (has_places = true)
    // On exclut les événements où capacity - COUNT(r.id) <= 0
    // Ce filtre s'applique sur un agrégat → HAVING, pas WHERE
    // On le stocke séparément pour l'ajouter après le GROUP BY
    $havingClause = '';
    if ($hasPlaces) {
        // (capacity - registered_count) > 0 → au moins 1 place libre
        $havingClause = 'HAVING (e.capacity - COUNT(r.id)) > 0';
    }

    // ── Assemblage de la requête (structure fournie — conservée) ─────
    $sql = $baseSelect;

    if (!empty($conditions)) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' GROUP BY e.id';

    // HAVING après GROUP BY (uniquement si filtre places actif)
    if ($havingClause !== '') {
        $sql .= ' ' . $havingClause;
    }

    $sql .= ' ORDER BY e.event_date ASC';

    // ── Requête COUNT pour le total réel (pagination correcte) ───────
    // On enveloppe la requête principale dans un sous-SELECT COUNT(*)
    // pour obtenir le nombre total de lignes AVANT la limite de page.
    // C'est plus fiable que rowCount() qui ne fonctionne pas toujours
    // avec SELECT sur tous les drivers PDO.
    $countSql  = "SELECT COUNT(*) AS total FROM ($sql) AS sub";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($bindings);
    $total = (int) $countStmt->fetchColumn();

    // ── Pagination : LIMIT + OFFSET ───────────────────────────────────
    // LIMIT  = nombre de résultats par page (6)
    // OFFSET = combien de lignes sauter = (page - 1) × perPage
    // On utilise des entiers castés directement dans la chaîne SQL
    // car PDO ne peut pas binder LIMIT/OFFSET comme paramètres nommés
    // dans tous les modes (EMULATE_PREPARES=false pose problème avec
    // certains drivers). Cast explicite → aucun risque d'injection.
    $offset = ($page - 1) * $perPage;
    $sql   .= ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;

    // ── Exécution de la requête paginée ───────────────────────────────
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindings);
    $events = $stmt->fetchAll();

    return ['events' => $events, 'total' => $total];
}