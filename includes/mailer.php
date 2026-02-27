<?php
/**
 * Standalone SMTP Mailer — no Composer, no installs needed
 * Drop this file in your cms/ folder and it just works.
 * Uses raw socket SMTP — works on XAMPP Mac/Windows/Linux.
 * 
 * GMAIL SETUP:
 * 1. Enable 2-Factor Authentication on Gmail
 * 2. Go to: https://myaccount.google.com/apppasswords
 * 3. Create App Password (select Mail and Windows Computer)
 * 4. Copy the 16-character password and use as SMTP Password in Settings
 * 5. Email: your-gmail@gmail.com
 */

class Mailer {

    private string $host;
    private int    $port;
    private string $user;
    private string $pass;
    private string $fromEmail;
    private string $fromName;
    private string $lastError = '';

    public function __construct(PDO $pdo) {
        $this->host      = get_setting($pdo, 'smtp_host', 'smtp.gmail.com');
        $this->port      = (int) get_setting($pdo, 'smtp_port', '587');
        $this->user      = get_setting($pdo, 'smtp_user', '');
        $this->pass      = get_setting($pdo, 'smtp_pass', '');
        $this->fromEmail = get_setting($pdo, 'smtp_from', '');
        $this->fromName  = get_setting($pdo, 'smtp_name', 'Complaint Management System');
    }

    public function isConfigured(): bool {
        return !empty($this->host) && !empty($this->user) && !empty($this->pass);
    }

    public function getLastError(): string {
        return $this->lastError;
    }

    public function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
        if (!$this->isConfigured()) {
            $this->lastError = 'SMTP not configured. Go to Admin → Settings.';
            return false;
        }

        try {
            // Open socket to SMTP server with TLS encryption
            $socket = @fsockopen('tls://' . $this->host, $this->port, $errno, $errstr, 30);
            if (!$socket) {
                $this->lastError = "Cannot connect to SMTP server ({$this->host}:{$this->port}): $errstr ($errno)";
                return false;
            }

            stream_set_timeout($socket, 30);
            stream_set_blocking($socket, true);

            // Read initial response from server
            $read = $this->readResponse($socket);
            if (substr($read, 0, 3) !== '220') {
                $this->lastError = "SMTP greeting failed: $read";
                fclose($socket);
                return false;
            }

            // EHLO command (Extended HELO)
            if (!$this->sendCommand($socket, "EHLO " . gethostname(), ['250'])) {
                fclose($socket);
                return false;
            }

            // STARTTLS if available (implicit TLS connection already active)
            // Check if we need AUTH before starting

            // AUTH LOGIN
            if (!$this->sendCommand($socket, "AUTH LOGIN", ['334'])) {
                fclose($socket);
                return false;
            }

            // Encode credentials
            $userEncoded = base64_encode($this->user);
            $passEncoded = base64_encode($this->pass);

            // Send username
            if (!$this->sendCommand($socket, $userEncoded, ['334'])) {
                $this->lastError = 'SMTP: Username not accepted. Check your email address.';
                fclose($socket);
                return false;
            }

            // Send password
            if (!$this->sendCommand($socket, $passEncoded, ['235'])) {
                $this->lastError = 'SMTP: Authentication failed. Use App Password for Gmail or your SMTP password.';
                fclose($socket);
                return false;
            }

            // MAIL FROM
            if (!$this->sendCommand($socket, "MAIL FROM:<{$this->fromEmail}>", ['250'])) {
                fclose($socket);
                return false;
            }

            // RCPT TO (validate recipient)
            if (!$this->sendCommand($socket, "RCPT TO:<{$toEmail}>", ['250'])) {
                $this->lastError = "Recipient '$toEmail' rejected by server.";
                fclose($socket);
                return false;
            }

            // DATA command
            if (!$this->sendCommand($socket, "DATA", ['354'])) {
                fclose($socket);
                return false;
            }

            // Build MIME message
            $boundary = md5(uniqid('cms_', true));
            $date     = date('r');
            $msgId    = '<' . uniqid('cms_', true) . '@' . gethostname() . '>';

            // Email headers
            $headers  = "Date: $date\r\n";
            $headers .= "Message-ID: $msgId\r\n";
            $headers .= "From: =?UTF-8?B?" . base64_encode($this->fromName) . "?= <{$this->fromEmail}>\r\n";
            $headers .= "To: =?UTF-8?B?" . base64_encode($toName) . "?= <{$toEmail}>\r\n";
            $headers .= "Reply-To: <{$this->fromEmail}>\r\n";
            $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
            $headers .= "X-Mailer: CMS-Mailer/2.0\r\n";
            $headers .= "X-Priority: 3\r\n";

            // Plain text fallback (extract from HTML)
            $plain = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody));
            $plain = html_entity_decode($plain, ENT_QUOTES, 'UTF-8');
            $plain = preg_replace('/\s+/', ' ', $plain);

            // Build multipart body
            $body  = "--$boundary\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($plain)) . "\r\n";

            $body .= "--$boundary\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";

            $body .= "--$boundary--\r\n";

            // Send message
            fputs($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
            $resp = $this->readResponse($socket);

            if (substr($resp, 0, 3) !== '250') {
                $this->lastError = "Message rejected: $resp";
                fclose($socket);
                return false;
            }

            // Close connection
            $this->sendCommand($socket, "QUIT", ['221']);
            fclose($socket);
            return true;

        } catch (Throwable $e) {
            $this->lastError = 'Exception: ' . $e->getMessage();
            return false;
        }
    }

    /**
     * Send SMTP command and verify response code
     */
    private function sendCommand($socket, string $cmd, array $expectCodes): bool {
        fputs($socket, $cmd . "\r\n");
        $resp = $this->readResponse($socket);

        $code = substr($resp, 0, 3);
        if (!in_array($code, $expectCodes)) {
            $this->lastError = "SMTP command failed. Command: '$cmd' | Response: $resp";
            return false;
        }
        return true;
    }

    /**
     * Read full response from SMTP server (handles multi-line responses)
     */
    private function readResponse($socket): string {
        $response = '';
        $timeout = time() + 30;

        while (!feof($socket) && time() < $timeout) {
            $line = fgets($socket, 1024);
            if ($line === false) break;

            $response .= $line;

            // Response complete if 4th char is space (e.g., "250 " not "250-")
            if (!empty($line) && strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        return $response ?: '500 No response';
    }

    // ── Pre-built email templates ──────────────────────────

    public function sendOTP(PDO $pdo, string $toEmail, string $toName, string $otp): bool {
        $siteName = get_setting($pdo, 'site_name', 'Complaint Management System');
        $subject  = "Your OTP – $siteName";
        $html = $this->template($siteName, "Email Verification", "
            <p>Hi <strong>" . htmlspecialchars($toName) . "</strong>,</p>
            <p>Use the OTP below to verify your email address.</p>
            <div style='text-align:center;margin:32px 0'>
                <div style='display:inline-block;background:#1a1a35;border:2px dashed #e94560;
                    border-radius:12px;padding:20px 40px;font-size:36px;font-weight:800;
                    letter-spacing:12px;color:#e94560;font-family:monospace'>$otp</div>
            </div>
            <p style='color:#999;font-size:14px'>This OTP expires in <strong>10 minutes</strong>. Do not share it with anyone.</p>
        ");
        return $this->send($toEmail, $toName, $subject, $html);
    }

    public function sendTicketConfirm(PDO $pdo, string $toEmail, string $toName,
                                      string $ticketNo, string $bankName, string $priority): bool {
        $siteName = get_setting($pdo, 'site_name', 'Complaint Management System');
        $colors   = ['high' => '#ef4444', 'medium' => '#f59e0b', 'low' => '#22c55e'];
        $color    = $colors[$priority] ?? '#e94560';
        $subject  = "Ticket $ticketNo Received – $siteName";
        $html = $this->template($siteName, "Complaint Received", "
            <p>Hi <strong>" . htmlspecialchars($toName) . "</strong>,</p>
            <p>Your complaint has been received and assigned a ticket number.</p>
            <div style='background:#1a1a35;border-left:4px solid $color;border-radius:8px;padding:20px;margin:24px 0'>
                <div style='font-size:12px;color:#999;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px'>Ticket Number</div>
                <div style='font-size:24px;font-weight:800;color:$color;font-family:monospace'>$ticketNo</div>
                <div style='margin-top:12px;font-size:14px;color:#ccc'>Bank: <strong>" . htmlspecialchars($bankName) . "</strong></div>
                <div style='font-size:14px;color:#ccc;margin-top:4px'>Priority: <strong style='color:$color'>" . strtoupper($priority) . "</strong></div>
            </div>
            <p style='color:#999;font-size:14px'>You can track your ticket status by logging into the system.</p>
        ");
        return $this->send($toEmail, $toName, $subject, $html);
    }

    public function sendStatusUpdate(PDO $pdo, string $toEmail, string $toName,
                                     string $ticketNo, string $newStatus): bool {
        $siteName = get_setting($pdo, 'site_name', 'Complaint Management System');
        $colors   = ['resolved' => '#22c55e', 'in_progress' => '#38bdf8',
                     'overdue'  => '#ef4444', 'pending'     => '#94a3b8'];
        $color    = $colors[$newStatus] ?? '#e94560';
        $label    = strtoupper(str_replace('_', ' ', $newStatus));
        $subject  = "Ticket $ticketNo Status Update – $siteName";
        $html = $this->template($siteName, "Ticket Status Updated", "
            <p>Hi <strong>" . htmlspecialchars($toName) . "</strong>,</p>
            <p>The status of your ticket has been updated.</p>
            <div style='background:#1a1a35;border-left:4px solid $color;border-radius:8px;padding:20px;margin:24px 0'>
                <div style='font-size:12px;color:#999;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px'>Ticket</div>
                <div style='font-size:20px;font-weight:800;color:#e8e8f0;font-family:monospace'>$ticketNo</div>
                <div style='margin-top:12px;font-size:16px;color:$color;font-weight:700'>● $label</div>
            </div>
            <p style='color:#999;font-size:14px'>Log in to view full details and activity history.</p>
        ");
        return $this->send($toEmail, $toName, $subject, $html);
    }

    private function template(string $siteName, string $heading, string $content): string {
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#0d0d18;font-family:Arial,sans-serif">
        <table width="100%" cellpadding="0" cellspacing="0">
        <tr><td align="center" style="padding:40px 20px">
        <table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%">
            <tr><td style="background:#e94560;border-radius:12px 12px 0 0;padding:28px 32px">
                <div style="font-size:20px;font-weight:800;color:#fff">⚡ ' . htmlspecialchars($siteName) . '</div>
                <div style="font-size:26px;font-weight:800;color:#fff;margin-top:8px">' . htmlspecialchars($heading) . '</div>
            </td></tr>
            <tr><td style="background:#13132a;border-radius:0 0 12px 12px;padding:32px;color:#e8e8f0;font-size:15px;line-height:1.7">
                ' . $content . '
                <hr style="border:none;border-top:1px solid rgba(255,255,255,.08);margin:28px 0">
                <p style="color:#555;font-size:12px;margin:0">This is an automated message from ' . htmlspecialchars($siteName) . '. Please do not reply.</p>
            </td></tr>
        </table>
        </td></tr></table></body></html>';
    }
}
