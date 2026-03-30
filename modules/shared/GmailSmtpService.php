<?php

/**
 * Minimal Gmail SMTP sender via SSL (port 465).
 * Uses App Password authentication.
 */
class GmailSmtpService
{
    const SMTP_HOST  = 'ssl://smtp.gmail.com';
    const SMTP_PORT  = 465;
    const FROM_EMAIL = 'papierinvest@gmail.com';
    const FROM_NAME  = 'Papir CRM';
    const APP_PASS   = 'dhdjsinqzviptfod';  // App Password без пробелов

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
        $sock   = @fsockopen(self::SMTP_HOST, self::SMTP_PORT, $errno, $errstr, 15);
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

        self::smtpWrite($sock, base64_encode(self::FROM_EMAIL));
        $read = self::smtpRead($sock);
        if (!self::smtpOk($read, '334')) {
            fclose($sock);
            return array('ok' => false, 'error' => 'AUTH user: ' . $read);
        }

        self::smtpWrite($sock, base64_encode(self::APP_PASS));
        $read = self::smtpRead($sock);
        if (!self::smtpOk($read, '235')) {
            fclose($sock);
            return array('ok' => false, 'error' => 'AUTH pass: ' . $read);
        }

        // MAIL FROM
        self::smtpWrite($sock, 'MAIL FROM:<' . self::FROM_EMAIL . '>');
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
        $fromFmt = self::encodeHeader(self::FROM_NAME) . ' <' . self::FROM_EMAIL . '>';
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
