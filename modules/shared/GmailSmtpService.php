<?php

require_once __DIR__ . '/../integrations/IntegrationSettingsService.php';

/**
 * Minimal Gmail SMTP sender via SSL (port 465).
 * Uses App Password authentication.
 */
class GmailSmtpService
{
    private static $cfg = null;

    private static function cfg($key)
    {
        if (self::$cfg === null) {
            $all = IntegrationSettingsService::getAll('gmail_smtp');
            self::$cfg = array(
                'smtp_host'  => isset($all['smtp_host'])  ? $all['smtp_host']['value']  : 'ssl://smtp.gmail.com',
                'smtp_port'  => isset($all['smtp_port'])  ? (int)$all['smtp_port']['value'] : 465,
                'from_email' => isset($all['from_email']) ? $all['from_email']['value'] : '',
                'from_name'  => isset($all['from_name'])  ? $all['from_name']['value']  : 'Papir CRM',
                'app_pass'   => isset($all['app_pass'])   ? $all['app_pass']['value']   : '',
            );
        }
        return self::$cfg[$key];
    }

    /**
     * Send email with optional file attachment.
     * @param string      $toEmail
     * @param string      $toName
     * @param string      $subject
     * @param string      $bodyText        Plain text body
     * @param string|null $attachmentPath  Absolute local path to file (optional)
     * @param string|null $attachmentName  Original filename shown to recipient (optional)
     * @return array ['ok'=>bool, 'error'=>string]
     */
    public static function send($toEmail, $toName, $subject, $bodyText,
                                $attachmentPath = null, $attachmentName = null)
    {
        $toEmail = trim($toEmail);
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return array('ok' => false, 'error' => 'Невалідний email');
        }

        $errno  = 0;
        $errstr = '';
        $sock   = @fsockopen(self::cfg('smtp_host'), self::cfg('smtp_port'), $errno, $errstr, 15);
        if (!$sock) {
            return array('ok' => false, 'error' => 'SMTP connect failed: ' . $errstr);
        }

        stream_set_timeout($sock, 15);

        $read = self::smtpRead($sock);
        if (!self::smtpOk($read, '220')) {
            fclose($sock);
            return array('ok' => false, 'error' => 'SMTP greeting: ' . $read);
        }

        // EHLO
        self::smtpWrite($sock, 'EHLO papir.crm');
        $read = self::smtpRead($sock);
        if (!self::smtpOk($read, '250')) {
            fclose($sock);
            return array('ok' => false, 'error' => 'EHLO: ' . $read);
        }

        // AUTH LOGIN
        self::smtpWrite($sock, 'AUTH LOGIN');
        $read = self::smtpRead($sock);
        if (!self::smtpOk($read, '334')) {
            fclose($sock);
            return array('ok' => false, 'error' => 'AUTH LOGIN: ' . $read);
        }

        self::smtpWrite($sock, base64_encode(self::cfg('from_email')));
        $read = self::smtpRead($sock);
        if (!self::smtpOk($read, '334')) {
            fclose($sock);
            return array('ok' => false, 'error' => 'AUTH user: ' . $read);
        }

        self::smtpWrite($sock, base64_encode(self::cfg('app_pass')));
        $read = self::smtpRead($sock);
        if (!self::smtpOk($read, '235')) {
            fclose($sock);
            return array('ok' => false, 'error' => 'AUTH pass: ' . $read);
        }

        // MAIL FROM
        self::smtpWrite($sock, 'MAIL FROM:<' . self::cfg('from_email') . '>');
        $read = self::smtpRead($sock);
        if (!self::smtpOk($read, '250')) {
            fclose($sock);
            return array('ok' => false, 'error' => 'MAIL FROM: ' . $read);
        }

        // RCPT TO
        self::smtpWrite($sock, 'RCPT TO:<' . $toEmail . '>');
        $read = self::smtpRead($sock);
        if (!self::smtpOk($read, '250')) {
            fclose($sock);
            return array('ok' => false, 'error' => 'RCPT TO: ' . $read);
        }

        // DATA
        self::smtpWrite($sock, 'DATA');
        $read = self::smtpRead($sock);
        if (!self::smtpOk($read, '354')) {
            fclose($sock);
            return array('ok' => false, 'error' => 'DATA: ' . $read);
        }

        // Build message
        $date    = date('r');
        $fromFmt = self::encodeHeader(self::cfg('from_name')) . ' <' . self::cfg('from_email') . '>';
        $toFmt   = ($toName ? self::encodeHeader($toName) . ' ' : '') . '<' . $toEmail . '>';
        $subjFmt = self::encodeHeader($subject);

        $hasAttachment = $attachmentPath && file_exists($attachmentPath);

        if ($hasAttachment) {
            $boundary = '----=_Part_' . md5(uniqid('', true));
            $attName  = $attachmentName ? $attachmentName : basename($attachmentPath);
            $attMime  = mime_content_type($attachmentPath);
            if (!$attMime) $attMime = 'application/octet-stream';
            $attData  = base64_encode(file_get_contents($attachmentPath));

            $msg  = "Date: {$date}\r\n";
            $msg .= "From: {$fromFmt}\r\n";
            $msg .= "To: {$toFmt}\r\n";
            $msg .= "Subject: {$subjFmt}\r\n";
            $msg .= "MIME-Version: 1.0\r\n";
            $msg .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
            $msg .= "\r\n";
            // Text part
            $msg .= "--{$boundary}\r\n";
            $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $msg .= "Content-Transfer-Encoding: base64\r\n";
            $msg .= "\r\n";
            $msg .= chunk_split(base64_encode($bodyText), 76, "\r\n");
            // Attachment part
            $attNameFmt = self::encodeHeader($attName);
            $msg .= "--{$boundary}\r\n";
            $msg .= "Content-Type: {$attMime}; name=\"{$attNameFmt}\"\r\n";
            $msg .= "Content-Transfer-Encoding: base64\r\n";
            $msg .= "Content-Disposition: attachment; filename=\"{$attNameFmt}\"\r\n";
            $msg .= "\r\n";
            $msg .= chunk_split($attData, 76, "\r\n");
            $msg .= "--{$boundary}--\r\n";
            $msg .= "\r\n.";
        } else {
            $msg  = "Date: {$date}\r\n";
            $msg .= "From: {$fromFmt}\r\n";
            $msg .= "To: {$toFmt}\r\n";
            $msg .= "Subject: {$subjFmt}\r\n";
            $msg .= "MIME-Version: 1.0\r\n";
            $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $msg .= "Content-Transfer-Encoding: base64\r\n";
            $msg .= "\r\n";
            $msg .= chunk_split(base64_encode($bodyText), 76, "\r\n");
            $msg .= "\r\n.";
        }

        self::smtpWrite($sock, $msg);
        $read = self::smtpRead($sock);
        if (!self::smtpOk($read, '250')) {
            fclose($sock);
            return array('ok' => false, 'error' => 'Message send: ' . $read);
        }

        self::smtpWrite($sock, 'QUIT');
        fclose($sock);

        return array('ok' => true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function smtpWrite($sock, $msg)
    {
        fwrite($sock, $msg . "\r\n");
    }

    private static function smtpRead($sock)
    {
        $response = '';
        while (!feof($sock)) {
            $line = fgets($sock, 512);
            if ($line === false) break;
            $response .= $line;
            // Multi-line: "NNN-..." continues; "NNN ..." is last line
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return rtrim($response);
    }

    private static function smtpOk($response, $expectedCode)
    {
        return strncmp(trim($response), $expectedCode, 3) === 0;
    }

    /**
     * RFC 2047 UTF-8 encoded word for non-ASCII headers.
     */
    private static function encodeHeader($str)
    {
        if (preg_match('/[^\x20-\x7E]/', $str)) {
            return '=?UTF-8?B?' . base64_encode($str) . '?=';
        }
        return $str;
    }
}
