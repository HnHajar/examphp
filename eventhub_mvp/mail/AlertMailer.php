<?php
/**
 * ╔══════════════════════════════════════════════════════════════╗
 * ║  EventHub Pro — mail/AlertMailer.php                        ║
 * ║  Email d'alerte organisateur (seuil 80%)                    ║
 * ║  ENSA Marrakech — Examen PHP Avancé                         ║
 * ╚══════════════════════════════════════════════════════════════╝
 *
 * STATUT : ✅ Complété — Partie 2.2
 */

require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../config/db.php';

class AlertMailer
{
    /**
     * Envoie l'email d'alerte de capacité à l'organisateur.
     *
     * @param  PDO   $pdo
     * @param  array $event   Données complètes de l'événement
     * @return bool
     */
    public static function sendCapacityAlert(PDO $pdo, array $event): bool
    {
        // ════════════════════════════════════════════════════════════════
        // ✅ IMPLÉMENTATION 2.2.A — Vérifier si l'alerte a déjà été envoyée
        // ════════════════════════════════════════════════════════════════
        //
        // APPROCHE CHOISIE : Colonne alert_sent dans la table events
        //
        // JUSTIFICATION : C'est la solution la plus robuste en concurrence.
        // Si deux participants s'inscrivent en même temps et que le seuil
        // est atteint simultanément, les deux requêtes lisent alert_sent=0.
        // On utilise UPDATE avec WHERE alert_sent=0 : MySQL garantit qu'une
        // seule des deux UPDATE réussit (verrou ligne InnoDB).
        // Celui qui obtient rowCount()=0 sait que l'autre a déjà envoyé
        // → pas de double envoi, même sous charge.
        // Les approches fichier-lock ou mail_logs ne garantissent pas
        // l'atomicité en cas de requêtes simultanées.

        // Tentative d'acquisition atomique du droit d'envoyer l'alerte :
        // UPDATE ne modifie la ligne QUE si alert_sent vaut encore 0
        $lockStmt = $pdo->prepare(
            'UPDATE events SET alert_sent = 1
             WHERE id = :id AND alert_sent = 0'
        );
        $lockStmt->execute([':id' => $event['id']]);

        // rowCount() = 0 → une autre requête a déjà posé alert_sent=1
        // → on sort sans envoyer pour éviter le doublon
        if ($lockStmt->rowCount() === 0) {
            return false;
        }

        // ════════════════════════════════════════════════════════════════
        // ✅ IMPLÉMENTATION 2.2.B — Générer le rapport PDF temporaire
        // ════════════════════════════════════════════════════════════════

        // sys_get_temp_dir() retourne le dossier temp du système (ex: C:\Windows\Temp)
        // On suffixe avec l'ID de l'événement pour éviter les collisions
        $tempPdf = sys_get_temp_dir() . '/report_event_' . $event['id'] . '.pdf';

        // generateReportPDF() est définie dans pdf/report.php (Partie 3.2)
        // Mode 'F' = sauvegarder dans un fichier (pas de sortie navigateur)
        if (file_exists(__DIR__ . '/../pdf/report.php')) {
            require_once __DIR__ . '/../pdf/report.php';
            generateReportPDF($pdo, $event['id'], 'F', $tempPdf);
        }

        // ════════════════════════════════════════════════════════════════
        // ✅ IMPLÉMENTATION 2.2.C — Charger le template et envoyer l'email
        // ════════════════════════════════════════════════════════════════

        $templatePath = __DIR__ . '/templates/alert.html';

        // Calcul du taux de remplissage pour les placeholders du template
        $registered = (int)$event['registered_count'] + 1; // +1 = le nouvel inscrit
        $fillPct    = round(($registered / $event['capacity']) * 100);

        // Chargement et personnalisation du template HTML
        if (file_exists($templatePath)) {
            $html = file_get_contents($templatePath);
            $html = str_replace([
                '{{ORGANIZER_NAME}}',
                '{{EVENT_TITLE}}',
                '{{FILL_PCT}}',
                '{{REGISTERED}}',
                '{{CAPACITY}}',
                '{{DASHBOARD_LINK}}',
            ], [
                htmlspecialchars($event['organizer_email'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($event['title'],           ENT_QUOTES, 'UTF-8'),
                $fillPct,
                $registered,
                (int)$event['capacity'],
                (defined('BASE_URL') ? BASE_URL : 'http://localhost/xamp') . '/index.php',
            ], $html);
        } else {
            // Fallback texte brut si le template est absent
            $html = "<p>⚠️ L'événement <strong>{$event['title']}</strong> "
                  . "a atteint $fillPct% de sa capacité "
                  . "($registered / {$event['capacity']} inscrits).</p>";
        }

        try {
            $mail = createMailer();

            // Destinataire : l'organisateur de l'événement
            // (en plus du destinataire par défaut dans createMailer())
            $mail->addAddress($event['organizer_email']);

            $mail->Subject = '⚠️ Alerte capacité — ' . $event['title']
                           . ' (' . $fillPct . '% complet)';
            $mail->Body    = $html;
            $mail->AltBody = strip_tags($html);

            // Attacher le PDF rapport si généré avec succès
            if (file_exists($tempPdf)) {
                $mail->addAttachment($tempPdf, 'rapport_event_' . $event['id'] . '.pdf');
            }

            $mail->send();

            // Logger l'envoi réussi dans mail_logs pour la traçabilité
            $logStmt = $pdo->prepare(
                'INSERT INTO mail_logs (type, recipient, event_id, created_at)
                 VALUES (:type, :recipient, :event_id, NOW())'
            );
            $logStmt->execute([
                ':type'      => 'capacity_alert',
                ':recipient' => $event['organizer_email'],
                ':event_id'  => $event['id'],
            ]);

            // Nettoyer le fichier temporaire après envoi
            if (file_exists($tempPdf)) {
                @unlink($tempPdf);
            }

            return true;

        } catch (\PHPMailer\PHPMailer\Exception $e) {
            // En cas d'échec PHPMailer : on remet alert_sent à 0
            // pour qu'une prochaine inscription puisse retenter l'envoi
            $pdo->prepare('UPDATE events SET alert_sent = 0 WHERE id = :id')
                ->execute([':id' => $event['id']]);

            logMailError($pdo, 'capacity_alert', $event['organizer_email'], $e->getMessage());

            if (file_exists($tempPdf)) {
                @unlink($tempPdf);
            }

            return false;
        }
    }
}