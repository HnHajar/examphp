<?php
/**
 * ╔══════════════════════════════════════════════════════════════╗
 * ║  EventHub Pro — pdf/ticket.php                              ║
 * ║  Génération du ticket PDF d'inscription                     ║
 * ║  ENSA Marrakech — Examen PHP Avancé                         ║
 * ╚══════════════════════════════════════════════════════════════╝
 *
 * BIBLIOTHÈQUE CHOISIE : Dompdf
 *
 * JUSTIFICATION :
 *   Dompdf convertit du HTML/CSS en PDF directement — on peut donc
 *   concevoir le ticket comme une page web (mise en page, couleurs,
 *   images) sans apprendre une API de dessin bas-niveau comme TCPDF.
 *   Pour un ticket visuellement riche avec couleur dynamique par
 *   catégorie, c'est largement plus rapide et maintenable.
 *   TCPDF est plus puissant pour les documents complexes multi-pages
 *   mais sa courbe d'apprentissage est inutile ici.
 *
 * USAGE :
 *   Téléchargement navigateur : pdf/ticket.php?token=abc123
 *   Génération fichier (email) : generateTicketPDF($pdo, $regId, $token, 'F', '/tmp/ticket.pdf')
 *
 * INSTALLATION :
 *   composer require dompdf/dompdf
 *   composer require endroid/qr-code
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;

// ── Couleurs dynamiques par catégorie ─────────────────────────────────────
// Défi créatif : chaque catégorie a sa propre couleur principale
// utilisée pour l'en-tête, la bordure et le bandeau du ticket
const CATEGORY_COLORS = [
    'tech'     => ['primary' => '#2563EB', 'light' => '#DBEAFE', 'label' => 'Tech'],
    'design'   => ['primary' => '#7C3AED', 'light' => '#EDE9FE', 'label' => 'Design'],
    'business' => ['primary' => '#EA580C', 'light' => '#FEF3C7', 'label' => 'Business'],
    'science'  => ['primary' => '#16A34A', 'light' => '#DCFCE7', 'label' => 'Science'],
    'default'  => ['primary' => '#0F172A', 'light' => '#F1F5F9', 'label' => 'Événement'],
];

// ══════════════════════════════════════════════════════════════════════════
// POINT D'ENTRÉE — Téléchargement via navigateur (?token=xxx)
// ══════════════════════════════════════════════════════════════════════════
if (php_sapi_name() !== 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {

    $token = isset($_GET['token']) ? trim($_GET['token']) : '';

    if (empty($token)) {
        http_response_code(400);
        die('Token manquant.');
    }

    $pdo = getDB();

    // Récupérer l'inscription + l'événement via le token
    $stmt = $pdo->prepare(
        'SELECT r.*, e.title, e.event_date, e.location, e.category, e.capacity,
                COUNT(reg.id) AS registered_count
         FROM registrations r
         JOIN events e ON e.id = r.event_id
         LEFT JOIN registrations reg ON reg.event_id = e.id
         WHERE r.token = :token
         GROUP BY r.id'
    );
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        die('Ticket introuvable.');
    }

    // Mode 'I' = afficher dans le navigateur (inline)
    // Mode 'D' = forcer le téléchargement
    generateTicketPDF(
        $pdo,
        (int)$row['id'],
        $token,
        'D',                          // D = Download
        null,
        $row                          // données déjà chargées → pas de double requête
    );
}

// ══════════════════════════════════════════════════════════════════════════
// FONCTION PRINCIPALE — generateTicketPDF()
// ══════════════════════════════════════════════════════════════════════════

/**
 * Génère le ticket PDF pour une inscription.
 *
 * @param PDO         $pdo
 * @param int         $registrationId   ID de la ligne registrations
 * @param string      $token            Token unique de l'inscription
 * @param string      $mode             'D' download | 'I' inline | 'F' fichier | 'S' string
 * @param string|null $filePath         Chemin de sauvegarde (requis si mode='F')
 * @param array|null  $preloaded        Données déjà fetchées (évite une requête)
 * @return string|null                  Contenu PDF si mode='S', null sinon
 */
function generateTicketPDF(
    PDO     $pdo,
    int     $registrationId,
    string  $token,
    string  $mode     = 'D',
    ?string $filePath = null,
    ?array  $preloaded = null
): ?string {

    // ── 1. Charger les données ────────────────────────────────────────────
    if ($preloaded) {
        $data = $preloaded;
    } else {
        $stmt = $pdo->prepare(
            'SELECT r.*, e.title, e.event_date, e.location, e.category, e.capacity,
                    COUNT(reg.id) AS registered_count
             FROM registrations r
             JOIN events e ON e.id = r.event_id
             LEFT JOIN registrations reg ON reg.event_id = e.id
             WHERE r.id = :id AND r.token = :token
             GROUP BY r.id'
        );
        $stmt->execute([':id' => $registrationId, ':token' => $token]);
        $data = $stmt->fetch();

        if (!$data) {
            throw new RuntimeException("Inscription introuvable (id=$registrationId).");
        }
    }

    // ── 2. Couleur dynamique selon catégorie ─────────────────────────────
    $cat    = strtolower($data['category'] ?? 'default');
    $colors = CATEGORY_COLORS[$cat] ?? CATEGORY_COLORS['default'];
    $primaryColor = $colors['primary'];
    $lightColor   = $colors['light'];
    $catLabel     = $colors['label'];

    // ── 3. Formatage de la date en français ──────────────────────────────
    $dateObj = new DateTime($data['event_date']);
    $mois = ['','janvier','février','mars','avril','mai','juin',
              'juillet','août','septembre','octobre','novembre','décembre'];
    $dateFormatee = $dateObj->format('d') . ' '
                  . $mois[(int)$dateObj->format('n')] . ' '
                  . $dateObj->format('Y') . ' à '
                  . $dateObj->format('H\hi');

    // ── 4. Génération du QR Code ──────────────────────────────────────────
    // Données encodées : eventId|registrationId|token
    // Ce format permet au scanner de vérifier la validité du billet
    $qrData = $data['event_id'] . '|' . $registrationId . '|' . $token;

    $qrCode = new QrCode(
        data                : $qrData,
        encoding            : new Encoding('UTF-8'),
        errorCorrectionLevel: ErrorCorrectionLevel::High,
        size                : 200,
        margin              : 10,
        foregroundColor     : new Color(15, 23, 42),   // #0F172A quasi-noir
        backgroundColor     : new Color(255, 255, 255)
    );

    $writer    = new PngWriter();
    $qrResult  = $writer->write($qrCode);
    // Encoder en base64 pour l'intégrer directement dans le HTML (pas de fichier temp)
    $qrBase64  = 'data:image/png;base64,' . base64_encode($qrResult->getString());

    // ── 5. Logo en base64 ─────────────────────────────────────────────────
    $logoPath = __DIR__ . '/../assets/img/logo.png';
    $logoTag  = '';
    if (file_exists($logoPath)) {
        $logoB64 = base64_encode(file_get_contents($logoPath));
        $logoTag = '<img src="data:image/png;base64,' . $logoB64
                 . '" style="height:48px;" alt="EventHub Pro"/>';
    }

    // ── 6. Taux de remplissage ────────────────────────────────────────────
    $fillPct = round(($data['registered_count'] / $data['capacity']) * 100);

    // ── 7. Numéro de billet lisible ───────────────────────────────────────
    // Format : EVT-{eventId}-{registrationId} — affiché sur le ticket
    $ticketNumber = 'EVT-' . str_pad($data['event_id'], 4, '0', STR_PAD_LEFT)
                           . '-' . str_pad($registrationId, 6, '0', STR_PAD_LEFT);

    // ── 8. Construction du HTML du ticket ─────────────────────────────────
    $html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }

  body {
    font-family: DejaVu Sans, Arial, sans-serif;
    background: #F8FAFC;
    color: #0F172A;
    font-size: 13px;
  }

  /* ── Ticket container ── */
  .ticket {
    width: 680px;
    margin: 20px auto;
    border-radius: 16px;
    overflow: hidden;
    border: 2px solid {$primaryColor};
    box-shadow: 0 8px 32px rgba(0,0,0,.12);
  }

  /* ── En-tête coloré par catégorie ── */
  .header {
    background: {$primaryColor};
    padding: 28px 32px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .header-left h1 {
    color: #FFFFFF;
    font-size: 22px;
    font-weight: bold;
    letter-spacing: -.5px;
  }
  .header-left p {
    color: rgba(255,255,255,.7);
    font-size: 12px;
    margin-top: 4px;
  }
  .header-right {
    text-align: right;
  }

  /* ── Bandeau catégorie (défi créatif) ── */
  .category-band {
    background: {$lightColor};
    border-bottom: 1px solid {$primaryColor};
    padding: 8px 32px;
    font-size: 11px;
    font-weight: bold;
    color: {$primaryColor};
    text-transform: uppercase;
    letter-spacing: 1.5px;
  }

  /* ── Corps du ticket ── */
  .body {
    background: #FFFFFF;
    padding: 28px 32px;
    display: flex;
    gap: 24px;
  }

  /* ── Infos événement (gauche) ── */
  .info { flex: 1; }

  .event-title {
    font-size: 19px;
    font-weight: bold;
    color: {$primaryColor};
    margin-bottom: 16px;
    line-height: 1.3;
    border-left: 4px solid {$primaryColor};
    padding-left: 12px;
  }

  .row {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    margin-bottom: 10px;
    font-size: 13px;
    color: #334155;
  }
  .row .icon { min-width: 20px; font-size: 15px; }
  .row .label { color: #94A3B8; font-size: 11px; display: block; }
  .row .value { font-weight: bold; }

  /* ── Séparateur pointillé ── */
  .separator {
    width: 1px;
    background: repeating-linear-gradient(
      to bottom,
      #CBD5E1 0px, #CBD5E1 6px,
      transparent 6px, transparent 12px
    );
    margin: 0 8px;
  }

  /* ── QR Code (droite) ── */
  .qr-side {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 160px;
  }
  .qr-side img { width: 140px; height: 140px; border-radius: 8px; }
  .qr-side .ticket-num {
    margin-top: 8px;
    font-size: 10px;
    color: #64748B;
    font-weight: bold;
    letter-spacing: .5px;
    text-align: center;
  }

  /* ── Participant ── */
  .participant-band {
    background: {$lightColor};
    padding: 14px 32px;
    border-top: 1px dashed #CBD5E1;
    border-bottom: 1px dashed #CBD5E1;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .participant-band .name {
    font-size: 16px;
    font-weight: bold;
    color: {$primaryColor};
  }
  .participant-band .email {
    font-size: 12px;
    color: #64748B;
  }
  .participant-band .badge {
    background: {$primaryColor};
    color: #FFFFFF;
    font-size: 11px;
    font-weight: bold;
    padding: 4px 14px;
    border-radius: 20px;
  }

  /* ── Barre de remplissage ── */
  .fill-bar-wrap {
    padding: 16px 32px;
    background: #F8FAFC;
  }
  .fill-bar-label {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: #64748B;
    margin-bottom: 6px;
  }
  .fill-bar-bg {
    height: 8px;
    background: #E2E8F0;
    border-radius: 4px;
    overflow: hidden;
  }
  .fill-bar-fg {
    height: 8px;
    width: {$fillPct}%;
    background: {$primaryColor};
    border-radius: 4px;
  }

  /* ── Pied de page ── */
  .footer {
    background: #0F172A;
    padding: 14px 32px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .footer p { color: rgba(255,255,255,.5); font-size: 11px; }
  .footer .org { color: rgba(255,255,255,.8); font-weight: bold; }

  /* ── Filigrane VALIDÉ (défi créatif) ── */
  .watermark {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-35deg);
    font-size: 72px;
    font-weight: bold;
    color: rgba(37,99,235,.06);
    white-space: nowrap;
    pointer-events: none;
    z-index: 0;
  }
</style>
</head>
<body>

<div class="ticket">

  <!-- En-tête -->
  <div class="header">
    <div class="header-left">
      {$logoTag}
      <h1 style="margin-top:8px;">EventHub Pro</h1>
      <p>ENSA Marrakech — Billet d'entrée officiel</p>
    </div>
    <div class="header-right">
      <div style="color:rgba(255,255,255,.6);font-size:11px;">N° de billet</div>
      <div style="color:#FFFFFF;font-weight:bold;font-size:14px;letter-spacing:1px;">{$ticketNumber}</div>
    </div>
  </div>

  <!-- Bandeau catégorie -->
  <div class="category-band">🏷 Catégorie : {$catLabel}</div>

  <!-- Corps -->
  <div class="body" style="position:relative;">

    <!-- Filigrane VALIDÉ -->
    <div class="watermark">VALIDÉ</div>

    <!-- Infos événement -->
    <div class="info">
      <div class="event-title">{$data['title']}</div>

      <div class="row">
        <span class="icon">📅</span>
        <div>
          <span class="label">Date</span>
          <span class="value">{$dateFormatee}</span>
        </div>
      </div>

      <div class="row">
        <span class="icon">📍</span>
        <div>
          <span class="label">Lieu</span>
          <span class="value">{$data['location']}</span>
        </div>
      </div>

      <div class="row">
        <span class="icon">🎫</span>
        <div>
          <span class="label">Places</span>
          <span class="value">{$data['registered_count']} / {$data['capacity']}</span>
        </div>
      </div>
    </div>

    <!-- Séparateur -->
    <div class="separator"></div>

    <!-- QR Code -->
    <div class="qr-side">
      <img src="{$qrBase64}" alt="QR Code billet"/>
      <div class="ticket-num">{$ticketNumber}</div>
      <div style="font-size:10px;color:#94A3B8;margin-top:4px;text-align:center;">
        Scanner à l'entrée
      </div>
    </div>

  </div>

  <!-- Participant -->
  <div class="participant-band">
    <div>
      <div class="name">👤 {$data['name']}</div>
      <div class="email">{$data['email']}</div>
    </div>
    <div class="badge">✓ Confirmé</div>
  </div>

  <!-- Barre de remplissage -->
  <div class="fill-bar-wrap">
    <div class="fill-bar-label">
      <span>Taux de remplissage</span>
      <span>{$fillPct}%</span>
    </div>
    <div class="fill-bar-bg">
      <div class="fill-bar-fg"></div>
    </div>
  </div>

  <!-- Pied de page -->
  <div class="footer">
    <p>Généré le {$dateObj->format('d/m/Y')} — Conservez ce ticket</p>
    <p class="org">EventHub Pro © {$dateObj->format('Y')}</p>
  </div>

</div>

</body>
</html>
HTML;

    // ── 9. Configuration Dompdf ────────────────────────────────────────────
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);    // false = sécurité (pas d'URL externes)
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isFontSubsettingEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // ── 10. Sortie selon le mode ──────────────────────────────────────────
    $filename = 'ticket_' . $ticketNumber . '.pdf';

    switch ($mode) {
        case 'D':
            // D = Download — envoie le PDF au navigateur avec téléchargement forcé
            $dompdf->stream($filename, ['Attachment' => true]);
            break;

        case 'I':
            // I = Inline — affiche dans le navigateur sans téléchargement
            $dompdf->stream($filename, ['Attachment' => false]);
            break;

        case 'F':
            // F = File — sauvegarde dans un fichier (pour pièce jointe email)
            if (!$filePath) {
                throw new InvalidArgumentException("filePath requis pour mode 'F'.");
            }
            file_put_contents($filePath, $dompdf->output());
            break;

        case 'S':
            // S = String — retourne le contenu PDF (pour tests unitaires)
            return $dompdf->output();
    }

    return null;
}