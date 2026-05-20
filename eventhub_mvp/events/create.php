<?php
/**
 * ╔══════════════════════════════════════════════════════════════╗
 * ║  EventHub Pro — events/create.php                           ║
 * ║  Création d'un événement                                    ║
 * ║  ENSA Marrakech — Examen PHP Avancé                         ║
 * ╚══════════════════════════════════════════════════════════════╝
 *
 * STATUT : ✅ Complété — Partie 1.2
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/../config/db.php';

// ── Point d'entrée ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

// Lecture du body JSON
$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Données JSON invalides.']);
    exit;
}

// ✅ CORRECTION TODO 1.2 — Validation des champs obligatoires
// On vérifie que chaque champ requis est présent ET non vide
// sans ça, l'INSERT partirait avec des chaînes vides → données corrompues en BDD
$required = ['title', 'description', 'date', 'location', 'capacity', 'category', 'organizer_email'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error'   => "Le champ « $field » est obligatoire."
        ]);
        exit;
    }
}

// ✅ CORRECTION — Validation supplémentaire des types et formats
$errors = [];

// capacity doit être un entier strictement positif
if (!filter_var($data['capacity'], FILTER_VALIDATE_INT) || (int)$data['capacity'] <= 0) {
    $errors[] = 'La capacité doit être un entier positif.';
}

// date doit être un format datetime valide
$eventDate = DateTime::createFromFormat('Y-m-d\TH:i', $data['date'])
          ?: DateTime::createFromFormat('Y-m-d H:i:s', $data['date'])
          ?: DateTime::createFromFormat('Y-m-d', $data['date']);
if (!$eventDate) {
    $errors[] = 'Le format de date est invalide (attendu : YYYY-MM-DD ou YYYY-MM-DDTHH:MM).';
}

// email organisateur valide
if (!filter_var($data['organizer_email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'L\'adresse email organisateur est invalide.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

try {
    $pdo    = getDB();
    $result = createEvent($pdo, $data);

    echo json_encode([
        'success'  => true,
        'event_id' => $result,
        'message'  => 'Événement créé avec succès.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// ═════════════════════════════════════════════════════════════════════════
// FONCTION PRINCIPALE — CORRIGÉE (Partie 1.2)
// ═════════════════════════════════════════════════════════════════════════

/**
 * Insère un nouvel événement en base de données.
 *
 * @param  PDO   $pdo
 * @param  array $data  Données issues du formulaire (déjà validées)
 * @return int          ID du nouvel événement inséré
 * @throws RuntimeException si l'insertion échoue
 */
function createEvent(PDO $pdo, array $data): int
{
    // ✅ CORRECTION BUG 1 — Requête préparée avec paramètres nommés
    // PROBLÈME : La version originale concaténait $data['title'] etc. directement
    // dans la chaîne SQL → un titre comme "O'Reilly" ou "'; DROP TABLE events;--"
    // s'exécutait comme du SQL arbitraire (injection SQL).
    // SOLUTION : Les marqueurs :xxx sont des placeholders que PDO remplace
    // APRÈS avoir compilé la requête — les valeurs ne sont jamais interprétées
    // comme du SQL, quel que soit leur contenu.
    $sql = "INSERT INTO events
                (title, description, event_date, location, capacity, category, organizer_email, created_at)
            VALUES
                (:title, :description, :event_date, :location, :capacity, :category, :organizer_email, NOW())";

    // ✅ CORRECTION BUG 2 — prepare() + execute() au lieu de query()
    // PROBLÈME : query() exécute une chaîne SQL brute sans protection.
    // SOLUTION : prepare() envoie la structure SQL au serveur MySQL séparément
    // des données ; execute() envoie les valeurs liées — jamais interprétées.
    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':title'           => trim($data['title']),
        ':description'     => trim($data['description']),
        // On normalise la date au format MySQL DATETIME attendu par la BDD
        ':event_date'      => (new DateTime($data['date']))->format('Y-m-d H:i:s'),
        ':location'        => trim($data['location']),
        // cast explicite en int — PDO enverrait une string sinon (type mismatch)
        ':capacity'        => (int) $data['capacity'],
        ':category'        => trim($data['category']),
        ':organizer_email' => strtolower(trim($data['organizer_email'])),
    ]);

    // ✅ CORRECTION BUG 3 — Retourner lastInsertId() au lieu de true
    // PROBLÈME : retourner true ne permet pas de savoir quel ID a été créé,
    // ce qui empêche toute action post-insertion (génération ticket, email…).
    // De plus, si execute() échoue silencieusement, true masque l'erreur.
    // SOLUTION : lastInsertId() retourne l'ID auto-increment réel.
    // Si rowCount() == 0, aucune ligne insérée → on lève une exception explicite.
    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('L\'insertion a échoué : aucune ligne créée.');
    }

    return (int) $pdo->lastInsertId();
}