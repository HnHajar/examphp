<?php
/**
 * pdf/report.php — Rapport organisateur PDF
 * EventHub Pro — Partie 3.2
 *
 * Génère un rapport PDF complet pour l'organisateur d'un événement.
 * Modes :
 *   ?event_id=X              → téléchargement direct (inline navigateur)
 *   ?event_id=X&download=1   → force le téléchargement
 *   ?event_id=X&attach=1     → retourne le chemin du fichier (utilisé par AlertMailer)
 *
 * Sécurité : vérifie que l'utilisateur connecté est bien l'organisateur
 *            (ou qu'un token secret est passé pour l'usage interne)
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// ─── 0. Constantes ───────────────────────────────────────────────────────────

define('INTERNAL_SECRET', getenv('REPORT_SECRET') ?: 'eventhub_internal_secret_2024');

// Couleurs par catégorie (cohérent avec ticket.php)
const CATEGORY_COLORS = [
    'conference'  => ['primary' => '#1a73e8', 'light' => '#e8f0fe', 'badge' => '#ffffff'],
    'hackathon'   => ['primary' => '#e53935', 'light' => '#fce4ec', 'badge' => '#ffffff'],
    'meetup'      => ['primary' => '#43a047', 'light' => '#e8f5e9', 'badge' => '#ffffff'],
    'workshop'    => ['primary' => '#fb8c00', 'light' => '#fff3e0', 'badge' => '#ffffff'],
    'webinaire'   => ['primary' => '#8e24aa', 'light' => '#f3e5f5', 'badge' => '#ffffff'],
    'default'     => ['primary' => '#546e7a', 'light' => '#eceff1', 'badge' => '#ffffff'],
];

// ─── 1. Validation des paramètres ────────────────────────────────────────────

$eventId  = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
$download = filter_input(INPUT_GET, 'download', FILTER_VALIDATE_BOOLEAN);
$attach   = filter_input(INPUT_GET, 'attach',   FILTER_VALIDATE_BOOLEAN);
$token    = filter_input(INPUT_GET, 'token',    FILTER_SANITIZE_SPECIAL_CHARS);

if (!$eventId || $eventId <= 0) {
    http_response_code(400);
    exit(json_encode(['error' => 'Paramètre event_id manquant ou invalide.']));
}

// ─── 2. Authentification ─────────────────────────────────────────────────────
// Soit token interne (AlertMailer), soit session organisateur

$isInternal = ($token === INTERNAL_SECRET);

if (!$isInternal) {
    session_start();
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        exit(json_encode(['error' => 'Non authentifié.']));
    }
}

// ─── 3. Récupération des données ─────────────────────────────────────────────

$pdo = getDbConnection();

// Événement
$stmtEvent = $pdo->prepare("
    SELECT e.*, u.name AS organizer_name, u.email AS organizer_email
    FROM   events e
    JOIN   users  u ON u.id = e.organizer_id
    WHERE  e.id = :id
");
$stmtEvent->execute([':id' => $eventId]);
$event = $stmtEvent->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    http_response_code(404);
    exit(json_encode(['error' => 'Événement introuvable.']));
}

// Vérification organisateur (sauf usage interne)
if (!$isInternal && (int)$event['organizer_id'] !== (int)$_SESSION['user_id']) {
    http_response_code(403);
    exit(json_encode(['error' => 'Accès refusé : vous n\'êtes pas l\'organisateur de cet événement.']));
}

// Inscriptions
$stmtRegs = $pdo->prepare("
    SELECT r.id,
           r.name,
           r.email,
           r.registered_at,
           r.token,
           r.status
    FROM   registrations r
    WHERE  r.event_id = :id
    ORDER  BY r.registered_at ASC
");
$stmtRegs->execute([':id' => $eventId]);
$registrations = $stmtRegs->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$totalCapacity    = (int)($event['capacity'] ?? 0);
$totalRegistered  = count($registrations);
$confirmed        = array_filter($registrations, fn($r) => ($r['status'] ?? 'pending') === 'confirmed');
$pending          = array_filter($registrations, fn($r) => ($r['status'] ?? 'pending') === 'pending');
$cancelled        = array_filter($registrations, fn($r) => ($r['status'] ?? 'pending') === 'cancelled');
$fillRate         = $totalCapacity > 0 ? round(($totalRegistered / $totalCapacity) * 100, 1) : 0;

// Inscriptions par jour (pour le mini-graphique textuel)
$byDay = [];
foreach ($registrations as $r) {
    $day = substr($r['registered_at'], 0, 10);
    $byDay[$day] = ($byDay[$day] ?? 0) + 1;
}
ksort($byDay);

// ─── 4. Couleurs ─────────────────────────────────────────────────────────────

$cat    = strtolower($event['category'] ?? 'default');
$colors = CATEGORY_COLORS[$cat] ?? CATEGORY_COLORS['default'];
$primary = $colors['primary'];
$light   = $colors['light'];

// ─── 5. Construction du HTML ─────────────────────────────────────────────────

$eventDate     = !empty($event['date'])
    ? (new DateTime($event['date']))->format('d/m/Y à H:i')
    : 'Date non définie';
$generatedAt   = (new DateTime())->format('d/m/Y à H:i:s');
$eventTitle    = htmlspecialchars($event['title'] ?? 'Événement sans titre', ENT_QUOTES);
$eventLocation = htmlspecialchars($event['location'] ?? 'Lieu non défini', ENT_QUOTES);
$eventDesc     = htmlspecialchars($event['description'] ?? '', ENT_QUOTES);
$organizerName = htmlspecialchars($event['organizer_name'] ?? 'Inconnu', ENT_QUOTES);
$organizerEmail= htmlspecialchars($event['organizer_email'] ?? '', ENT_QUOTES);
$catLabel      = htmlspecialchars(ucfirst($cat), ENT_QUOTES);

// Barre de remplissage
$barWidth = min($fillRate, 100);
$barColor = $fillRate >= 100 ? '#e53935' : ($fillRate >= 80 ? '#fb8c00' : '#43a047');

// Tableau des inscrits
$rowsHtml = '';
$i = 1;
foreach ($registrations as $r) {
    $status     = $r['status'] ?? 'pending';
    $statusMap  = ['confirmed' => '✔ Confirmé', 'pending' => '⏳ En attente', 'cancelled' => '✘ Annulé'];
    $statusColors = ['confirmed' => '#43a047', 'pending' => '#fb8c00', 'cancelled' => '#e53935'];
    $statusLabel = $statusMap[$status]  ?? $status;
    $statusColor = $statusColors[$status] ?? '#546e7a';
    $regDate     = !empty($r['registered_at'])
        ? (new DateTime($r['registered_at']))->format('d/m/Y H:i')
        : '—';
    $name  = htmlspecialchars($r['name']  ?? '—', ENT_QUOTES);
    $email = htmlspecialchars($r['email'] ?? '—', ENT_QUOTES);
    $token = htmlspecialchars(substr($r['token'] ?? '—', 0, 8) . '…', ENT_QUOTES);
    $bg    = ($i % 2 === 0) ? '#f9f9f9' : '#ffffff';

    $rowsHtml .= "
    <tr style=\"background:{$bg};\">
        <td style=\"text-align:center;color:#888;\">{$i}</td>
        <td>{$name}</td>
        <td style=\"font-size:9px;color:#555;\">{$email}</td>
        <td style=\"text-align:center;font-size:9px;\">{$regDate}</td>
        <td style=\"text-align:center;\">
            <span style=\"color:{$statusColor};font-weight:bold;font-size:9px;\">{$statusLabel}</span>
        </td>
        <td style=\"text-align:center;font-size:8px;color:#aaa;font-family:monospace;\">{$token}</td>
    </tr>";
    $i++;
}

if ($rowsHtml === '') {
    $rowsHtml = '<tr><td colspan="6" style="text-align:center;color:#aaa;padding:16px;">
        Aucune inscription enregistrée.</td></tr>';
}

// Mini-graphique inscriptions/jour (barres ASCII-style en HTML)
$chartHtml = '';
if (!empty($byDay)) {
    $maxVal = max($byDay);
    foreach ($byDay as $day => $count) {
        $pct      = $maxVal > 0 ? round(($count / $maxVal) * 100) : 0;
        $barH     = max(4, (int)($pct * 0.4)); // max ~40px
        $chartHtml .= "
        <div style=\"display:inline-block;text-align:center;margin:0 3px;vertical-align:bottom;\">
            <div style=\"font-size:8px;color:#555;margin-bottom:2px;\">{$count}</div>
            <div style=\"width:20px;height:{$barH}px;background:{$primary};border-radius:2px 2px 0 0;\"></div>
            <div style=\"font-size:7px;color:#888;margin-top:2px;transform:rotate(-45deg);
                         transform-origin:top left;white-space:nowrap;width:30px;\">"
                . htmlspecialchars($day, ENT_QUOTES) . "</div>
        </div>";
    }
}

// ─── 6. Template HTML complet ─────────────────────────────────────────────────

$html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 10px;
    color: #333;
    background: #fff;
  }

  /* ── En-tête ── */
  .header {
    background: {$primary};
    color: #fff;
    padding: 24px 30px 18px;
    position: relative;
  }
  .header h1 { font-size: 20px; font-weight: bold; margin-bottom: 4px; }
  .header .subtitle { font-size: 10px; opacity: 0.85; }
  .header .badge {
    position: absolute; top: 24px; right: 30px;
    background: rgba(255,255,255,0.25);
    border: 1px solid rgba(255,255,255,0.5);
    border-radius: 20px;
    padding: 4px 14px;
    font-size: 10px;
  }
  .header .meta { margin-top: 10px; font-size: 9px; opacity: 0.75; }

  /* ── Infos événement ── */
  .info-box {
    background: {$light};
    border-left: 4px solid {$primary};
    padding: 14px 20px;
    margin: 16px 20px;
    border-radius: 0 6px 6px 0;
  }
  .info-box table { width: 100%; }
  .info-box td { padding: 3px 8px 3px 0; vertical-align: top; }
  .info-box td:first-child { font-weight: bold; color: {$primary}; width: 120px; }

  /* ── Section titre ── */
  .section-title {
    font-size: 11px;
    font-weight: bold;
    color: {$primary};
    border-bottom: 2px solid {$primary};
    padding-bottom: 4px;
    margin: 20px 20px 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  /* ── Cartes stats ── */
  .stats-grid {
    margin: 0 20px;
    width: calc(100% - 40px);
  }
  .stats-grid table { width: 100%; border-collapse: separate; border-spacing: 8px; }
  .stat-card {
    background: #f5f5f5;
    border-radius: 8px;
    padding: 12px 10px;
    text-align: center;
    border-top: 3px solid {$primary};
  }
  .stat-card .val { font-size: 22px; font-weight: bold; color: {$primary}; }
  .stat-card .lbl { font-size: 8px; color: #888; margin-top: 2px; text-transform: uppercase; }

  /* ── Barre de remplissage ── */
  .fill-bar-wrap {
    margin: 4px 20px 0;
    background: #e0e0e0;
    border-radius: 6px;
    height: 10px;
    overflow: hidden;
  }
  .fill-bar {
    height: 10px;
    width: {$barWidth}%;
    background: {$barColor};
    border-radius: 6px;
  }
  .fill-label {
    margin: 4px 20px 0;
    font-size: 9px;
    color: #555;
    text-align: right;
  }

  /* ── Tableau inscrits ── */
  .reg-table {
    margin: 0 20px;
    width: calc(100% - 40px);
    border-collapse: collapse;
  }
  .reg-table th {
    background: {$primary};
    color: #fff;
    padding: 6px 8px;
    text-align: left;
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
  }
  .reg-table td { padding: 5px 8px; border-bottom: 1px solid #eee; font-size: 9px; }

  /* ── Graphique ── */
  .chart-wrap {
    margin: 0 20px;
    padding: 10px;
    background: #fafafa;
    border-radius: 6px;
    border: 1px solid #eee;
    text-align: left;
    min-height: 70px;
  }

  /* ── Pied de page ── */
  .footer {
    margin-top: 24px;
    border-top: 1px solid #e0e0e0;
    padding: 10px 20px;
    font-size: 8px;
    color: #aaa;
    display: flex;
    justify-content: space-between;
  }
  .footer span { display: inline-block; }

  /* ── Page break ── */
  .page-break { page-break-after: always; }
</style>
</head>
<body>

<!-- ═══════════════════ EN-TÊTE ═══════════════════ -->
<div class="header">
  <span class="badge">{$catLabel}</span>
  <h1>📋 Rapport Organisateur</h1>
  <div class="subtitle">EventHub Pro — Rapport de gestion d'événement</div>
  <div class="meta">
    Généré le {$generatedAt} &nbsp;·&nbsp;
    Organisateur : {$organizerName} ({$organizerEmail})
  </div>
</div>

<!-- ═══════════════════ INFOS ÉVÉNEMENT ═══════════════════ -->
<div class="section-title">📍 Informations sur l'événement</div>
<div class="info-box">
  <table>
    <tr>
      <td>Titre</td>
      <td><strong>{$eventTitle}</strong></td>
      <td>Catégorie</td>
      <td>{$catLabel}</td>
    </tr>
    <tr>
      <td>Date</td>
      <td>{$eventDate}</td>
      <td>Lieu</td>
      <td>{$eventLocation}</td>
    </tr>
    <tr>
      <td>Capacité max</td>
      <td>{$totalCapacity} places</td>
      <td>ID Événement</td>
      <td>#{ $eventId}</td>
    </tr>
    <tr>
      <td colspan="4" style="padding-top:6px;">
        <em style="color:#666;">{$eventDesc}</em>
      </td>
    </tr>
  </table>
</div>

<!-- ═══════════════════ STATISTIQUES ═══════════════════ -->
<div class="section-title">📊 Statistiques d'inscription</div>
<div class="stats-grid">
  <table>
    <tr>
      <td width="25%">
        <div class="stat-card">
          <div class="val">{$totalRegistered}</div>
          <div class="lbl">Total inscrits</div>
        </div>
      </td>
      <td width="25%">
        <div class="stat-card" style="border-top-color:#43a047;">
          <div class="val" style="color:#43a047;">{$totalConfirmed}</div>
          <div class="lbl">Confirmés</div>
        </div>
      </td>
      <td width="25%">
        <div class="stat-card" style="border-top-color:#fb8c00;">
          <div class="val" style="color:#fb8c00;">{$totalPending}</div>
          <div class="lbl">En attente</div>
        </div>
      </td>
      <td width="25%">
        <div class="stat-card" style="border-top-color:#e53935;">
          <div class="val" style="color:#e53935;">{$totalCancelled}</div>
          <div class="lbl">Annulés</div>
        </div>
      </td>
    </tr>
  </table>
</div>

<!-- Taux de remplissage -->
<div style="margin:12px 20px 0;font-size:9px;color:#555;font-weight:bold;">
  Taux de remplissage : {$fillRate}%
  {$alertHtml}
</div>
<div class="fill-bar-wrap"><div class="fill-bar"></div></div>
<div class="fill-label">{$totalRegistered} / {$totalCapacity} places</div>

<!-- ═══════════════════ GRAPHIQUE INSCRIPTIONS/JOUR ═══════════════════ -->
{$chartSection}

<!-- ═══════════════════ LISTE DES INSCRITS ═══════════════════ -->
<div class="section-title" style="margin-top:20px;">👥 Liste des participants ({$totalRegistered})</div>

<table class="reg-table">
  <thead>
    <tr>
      <th style="width:30px;">#</th>
      <th>Nom</th>
      <th>Email</th>
      <th style="width:100px;text-align:center;">Inscription</th>
      <th style="width:80px;text-align:center;">Statut</th>
      <th style="width:70px;text-align:center;">Token</th>
    </tr>
  </thead>
  <tbody>
    {$rowsHtml}
  </tbody>
</table>

<!-- ═══════════════════ PIED DE PAGE ═══════════════════ -->
<div class="footer">
  <span>EventHub Pro — Rapport confidentiel réservé à l'organisateur</span>
  <span>Événement #{$eventId} · {$generatedAt}</span>
</div>

</body>
</html>
HTML;

// ─── 7. Remplacement des placeholders calculés ────────────────────────────────

$totalConfirmed  = count($confirmed);
$totalPending    = count($pending);
$totalCancelled  = count($cancelled);

// Alerte seuil 80%
$alertHtml = '';
if ($fillRate >= 100) {
    $alertHtml = '<span style="color:#e53935;font-weight:bold;"> ⚠ COMPLET</span>';
} elseif ($fillRate >= 80) {
    $alertHtml = '<span style="color:#fb8c00;font-weight:bold;"> ⚠ Seuil 80% atteint</span>';
}

// Graphique uniquement s'il y a des données
$chartSection = '';
if ($chartHtml !== '') {
    $chartSection = <<<CHART
<div class="section-title">📈 Évolution des inscriptions</div>
<div class="chart-wrap">
  <div style="vertical-align:bottom;display:inline-block;padding-bottom:10px;">
    {$chartHtml}
  </div>
</div>
CHART;
}

// Injection dans le HTML (on remplace les placeholders restants)
$html = str_replace(
    ['{$totalConfirmed}', '{$totalPending}', '{$totalCancelled}', '{$alertHtml}', '{$chartSection}'],
    [$totalConfirmed,     $totalPending,     $totalCancelled,     $alertHtml,     $chartSection],
    $html
);

// ─── 8. Génération Dompdf ─────────────────────────────────────────────────────

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);   // sécurité : pas de ressources externes

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// ─── 9. Sortie ────────────────────────────────────────────────────────────────

$filename = sprintf(
    'rapport_evenement_%d_%s.pdf',
    $eventId,
    (new DateTime())->format('Ymd_His')
);

if ($attach) {
    // Mode interne : sauvegarder sur disque et retourner le chemin
    $tmpDir  = sys_get_temp_dir();
    $tmpPath = $tmpDir . DIRECTORY_SEPARATOR . $filename;
    file_put_contents($tmpPath, $dompdf->output());
    echo $tmpPath;
    exit;
}

// Mode navigateur
$disposition = $download ? 'attachment' : 'inline';

header('Content-Type: application/pdf');
header("Content-Disposition: {$disposition}; filename=\"{$filename}\"");
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $dompdf->output();
exit;
