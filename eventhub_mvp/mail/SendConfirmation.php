<?php
/**
 * ╔══════════════════════════════════════════════════════════════╗
 * ║  EventHub Pro — mail/SendConfirmation.php                   ║
 * ║  Email de confirmation d'inscription                        ║
 * ║  ENSA Marrakech — Examen PHP Avancé                         ║
 * ╚══════════════════════════════════════════════════════════════╝
 *
 * STATUT : ✅ Complété — Partie 2.1
 */
require_once __DIR__ . '/../pdf/ticket.php';
require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../config/db.php';

class SendConfirmation
{
    /**
     * Envoie l'email de confirmation d'inscription.
     *
     * @param  PDO    $pdo
     * @param  array  $event   Données de l'événement (depuis la BD)
     * @param  string $name    Nom du participant
     * @param  string $email   Email du participant
     * @param  string $token   Token unique de désinscription
     * @return bool            true si envoi réussi, false sinon
     */
    public static function send(PDO $pdo, array $event, string $name, string $email, string $token, int $registrationId): bool
    {
        // ════════════════════════════════════════════════════════════════
        // ✅ IMPLÉMENTATION 2.1.A — Chargement et personnalisation du template
        // ════════════════════════════════════════════════════════════════

        $templatePath = __DIR__ . '/templates/confirmation.html';

        // Vérification que le template existe avant de le charger
        if (!file_exists($templatePath)) {
            logMailError($pdo, 'confirmation', $email, 'Template confirmation.html introuvable.');
            return false;
        }

        $html = file_get_contents($templatePath);

        // -- Formatage de la date en français --------------------------
        // On utilise IntlDateFormatter si l'extension intl est disponible,
        // sinon on tombe sur un format de secours lisible
        $dateObj = new DateTime($event['event_date']);

        if (class_exists('IntlDateFormatter')) {
            $formatter = new IntlDateFormatter(
                'fr_FR',
                IntlDateFormatter::FULL,    // Lundi 20 septembre 2025
                IntlDateFormatter::SHORT,   // 09:00
                'Africa/Casablanca',
                IntlDateFormatter::GREGORIAN
            );
            $dateFormatee = $formatter->format($dateObj);
        } else {
            // Fallback sans extension intl
            $jours   = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
            $mois    = ['','janvier','février','mars','avril','mai','juin',
                        'juillet','août','septembre','octobre','novembre','décembre'];
            $dateFormatee = $jours[(int)$dateObj->format('w')]
                . ' ' . $dateObj->format('d')
                . ' ' . $mois[(int)$dateObj->format('n')]
                . ' ' . $dateObj->format('Y')
                . ' à ' . $dateObj->format('H\hi');
        }

        // -- Construction des liens ------------------------------------
        // BASE_URL défini dans config/mailer.php ou db.php — fallback dynamique
        $baseUrl        = defined('BASE_URL') ? BASE_URL : 'http://' . $_SERVER['HTTP_HOST'];
        $unsubscribeLink = $baseUrl . '/events/unsubscribe.php?token=' . urlencode($token);
        $ticketLink      = $baseUrl . '/pdf/ticket.php?token='         . urlencode($token);

        // -- Remplacement des placeholders -----------------------------
        // htmlspecialchars() sur toutes les données issues de la BD
        // pour éviter toute injection HTML dans l'email
        $placeholders = [
            '{{PARTICIPANT_NAME}}'  => htmlspecialchars($name,              ENT_QUOTES, 'UTF-8'),
            '{{EVENT_TITLE}}'       => htmlspecialchars($event['title'],     ENT_QUOTES, 'UTF-8'),
            '{{EVENT_DATE}}'        => htmlspecialchars($dateFormatee,       ENT_QUOTES, 'UTF-8'),
            '{{EVENT_LOCATION}}'    => htmlspecialchars($event['location'],  ENT_QUOTES, 'UTF-8'),
            '{{UNSUBSCRIBE_LINK}}'  => $unsubscribeLink,  // URL — pas d'htmlspecialchars ici
            '{{TICKET_LINK}}'       => $ticketLink,        // idem
            '{{YEAR}}'              => date('Y'),
        ];

        // str_replace accepte des tableaux clé/valeur directement
        $html = str_replace(
            array_keys($placeholders),
            array_values($placeholders),
            $html
        );

        // ════════════════════════════════════════════════════════════════
        // ✅ IMPLÉMENTATION 2.1.B — Envoi avec PHPMailer
        // ════════════════════════════════════════════════════════════════

        try {
            $mail = createMailer(); // fonction définie dans config/mailer.php

            // -- Expéditeur -------------------------------------------
            // On fixe l'adresse expéditeur ici au cas où createMailer()
            // ne le ferait pas (selon la config du prof)
            $mail->setFrom('hnidhajar22@gmail.com', 'EventHub Pro');
            $mail->addReplyTo('hnidhajar22@gmail.com', 'EventHub Pro');

            // -- Destinataire -----------------------------------------
            $mail->addAddress($email, $name);

            // -- Sujet ------------------------------------------------
            $mail->Subject = '✅ Inscription confirmée — ' . $event['title'];

            // -- Corps HTML + texte brut (fallback client email basique)
            $mail->isHTML(true);
            $mail->Body    = $html;
            $mail->AltBody = self::buildAltBody($name, $event, $dateFormatee, $unsubscribeLink);

            // -- Encodage UTF-8 pour les caractères français ----------
            $mail->CharSet  = 'UTF-8';
            $mail->Encoding = 'base64';

            // TODO 3.1 — Attacher le ticket PDF généré
            // $pdfPath = generateTicketPDF($pdo, $registrationId, $token, 'F', $tempPath);
            // $mail->addAttachment($pdfPath, 'ticket_' . $event['id'] . '.pdf');
            $pdfPath = tempnam(sys_get_temp_dir(), 'eventhub_ticket_');

            if ($pdfPath === false) {
                throw new RuntimeException('Impossible de créer le fichier PDF temporaire.');
            }

            generateTicketPDF($pdo, $registrationId, $token, 'F', $pdfPath);

            $mail->addAttachment(
                 $pdfPath,
                'ticket_event_' . $event['id'] . '_inscription_' . $registrationId . '.pdf'
            );


            $mail->send();

            // -- Log succès en base -----------------------------------
            self::logMailSuccess($pdo, 'confirmation', $email, $event['id']);

            return true;

        } catch (\Throwable $e) {
            logMailError($pdo, 'confirmation', $email, $e->getMessage());
            return false;
        } finally {
             if (isset($pdfPath) && $pdfPath && file_exists($pdfPath)) {
                unlink($pdfPath);
            }
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // MÉTHODES PRIVÉES UTILITAIRES
    // ════════════════════════════════════════════════════════════════════

    /**
     * Génère le corps texte brut (AltBody) pour les clients sans HTML.
     */
    private static function buildAltBody(
        string $name,
        array  $event,
        string $dateFormatee,
        string $unsubscribeLink
    ): string {
        return implode("\n\n", [
            "Bonjour $name,",
            "Votre inscription est confirmée !",
            "Événement : " . $event['title'],
            "Date       : $dateFormatee",
            "Lieu       : " . $event['location'],
            "Se désinscrire : $unsubscribeLink",
            "© " . date('Y') . " EventHub Pro — ENSA Marrakech",
        ]);
    }

    /**
     * Enregistre un envoi réussi dans mail_logs.
     */
    private static function logMailSuccess(PDO $pdo, string $type, string $recipient, int $eventId): void
    {
        $stmt = $pdo->prepare(
            "INSERT INTO mail_logs (type, recipient, event_id, error_message)
             VALUES (:type, :recipient, :event_id, NULL)"
        );
        $stmt->execute([
            ':type'      => $type,
            ':recipient' => $recipient,
            ':event_id'  => $eventId,
        ]);
    }
}