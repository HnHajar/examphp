<?php
/**
 * ╔══════════════════════════════════════════════════════════════╗
 * ║  EventHub Pro — events/register.php                         ║
 * ║  Inscription d'un participant à un événement                ║
 * ║  ENSA Marrakech — Examen PHP Avancé                         ║
 * ╚══════════════════════════════════════════════════════════════╝
 *
 * STATUT : ✅ Complété — Parties 2.1 + 2.2
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../mail/SendConfirmation.php';
require_once __DIR__ . '/../mail/AlertMailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// ── Validation basique (fournie) ──────────────────────────────────────────
$eventId = isset($data['event_id']) ? (int)$data['event_id'] : 0;
$name    = isset($data['name'])     ? trim($data['name'])     : '';
$email   = isset($data['email'])    ? trim($data['email'])    : '';

if (!$eventId || !$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Données manquantes ou invalides.']);
    exit;
}

try {
    $pdo = getDB();

    // ── Récupérer l'événement (fourni) ────────────────────────────────────
    $stmt = $pdo->prepare(
        'SELECT e.*, 
                COUNT(r.id) AS registered_count
         FROM events e
         LEFT JOIN registrations r ON r.event_id = e.id
         WHERE e.id = :id
         GROUP BY e.id'
    );
    $stmt->execute([':id' => $eventId]);
    $event = $stmt->fetch();

    if (!$event) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Événement introuvable.']);
        exit;
    }

    // ── Vérifier capacité (fourni) ────────────────────────────────────────
    if ($event['registered_count'] >= $event['capacity']) {
        echo json_encode(['success' => false, 'error' => 'Événement complet.', 'full' => true]);
        exit;
    }

    // ── Vérifier doublon (fourni) ─────────────────────────────────────────
    $stmt = $pdo->prepare(
        'SELECT id FROM registrations WHERE event_id = :eid AND email = :email'
    );
    $stmt->execute([':eid' => $eventId, ':email' => $email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Vous êtes déjà inscrit(e) à cet événement.']);
        exit;
    }

    // ════════════════════════════════════════════════════════════════════
    // ✅ IMPLÉMENTATION 2.1 — Insérer l'inscription
    // ════════════════════════════════════════════════════════════════════

    // bin2hex(random_bytes(32)) génère 64 caractères hexadécimaux
    // cryptographiquement sûrs — imprévisible pour un attaquant
    // qui tenterait de deviner le lien de désinscription d'un autre inscrit
    $token = bin2hex(random_bytes(32));

    $stmt = $pdo->prepare(
        'INSERT INTO registrations (event_id, name, email, token, registered_at)
         VALUES (:event_id, :name, :email, :token, NOW())'
    );
    $stmt->execute([
        ':event_id' => $eventId,
        ':name'     => $name,
        ':email'    => $email,
        ':token'    => $token,
    ]);

    $registrationId = (int) $pdo->lastInsertId();

    // ════════════════════════════════════════════════════════════════════
    // ✅ IMPLÉMENTATION 2.1 — Envoyer l'email de confirmation
    // ════════════════════════════════════════════════════════════════════

    // L'envoi est non-bloquant : si le mail échoue, l'inscription reste
    // valide en BD. L'erreur est loggée dans mail_logs par SendConfirmation.
    $mailSent = SendConfirmation::send($pdo, $event, $name, $email, $token, $registrationId);
    // ════════════════════════════════════════════════════════════════════
    // ✅ IMPLÉMENTATION 2.2 — Détecter le seuil 80%
    // ════════════════════════════════════════════════════════════════════

    // On incrémente registered_count de +1 car le nouvel inscrit
    // vient d'être ajouté en BD mais la requête GROUP BY du début
    // a été exécutée AVANT l'INSERT — donc on calcule à la main
    $newCount  = (int)$event['registered_count'] + 1;
    $pct       = round(($newCount / $event['capacity']) * 100);
    $isFull    = ($newCount >= $event['capacity']);
    $alertSent = false;

    if ($pct >= 80) {
        // AlertMailer vérifie en interne si l'alerte a déjà été envoyée
        // (via la colonne alert_sent) avant d'envoyer quoi que ce soit
        $alertSent = AlertMailer::sendCapacityAlert($pdo, $event);
    }

    // ════════════════════════════════════════════════════════════════════
    // ✅ Retourner la réponse JSON
    // ════════════════════════════════════════════════════════════════════
    echo json_encode([
        'success'         => true,
        'registration_id' => $registrationId,
        'token'           => $token,       // pour le lien de désinscription côté JS
        'capacity_pct'    => $pct,         // pour mettre à jour la barre de progression
        'is_full'         => $isFull,
        'alert_sent'      => $alertSent,   // seuil 80% atteint et mail envoyé ?
        'mail_sent'       => $mailSent,    // confirmation participant envoyée ?
    ]);

} catch (PDOException $e) {
    error_log('[EventHub] register.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur.']);
}