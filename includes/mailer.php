<?php

/*
 * Sends an email using authenticated SMTP through the configured
 * mailbox (see config/mail.php). Falls back to PHP's built-in mail()
 * if no SMTP host is configured, so email sending still works even
 * before SMTP is set up - just with weaker deliverability.
 *
 * $attachment (optional): ['filename' => ..., 'content' => raw bytes, 'mime' => ...]
 *
 * Returns ['success' => bool, 'error' => string]
 */
function send_app_email(string $to, string $subject, string $html_body, array $cc = [], ?array $attachment = null): array
{
    if (defined('SMTP_HOST') && SMTP_HOST !== '') {
        return send_smtp_email($to, $subject, $html_body, $cc, $attachment);
    }

    return send_basic_mail($to, $subject, $html_body, $cc, $attachment);
}


/* -------------------------------------------------
   SHARED: build the multipart MIME body
   (used by both the SMTP path and the mail() fallback)
------------------------------------------------- */

function build_mime_body(string $html_body, ?array $attachment, string &$boundary): string
{
    $boundary = 'b' . md5(uniqid((string) mt_rand(), true));

    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $html_body . "\r\n\r\n";

    if ($attachment !== null) {

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: " . $attachment['mime'] . "; name=\"" . $attachment['filename'] . "\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"" . $attachment['filename'] . "\"\r\n\r\n";
        $body .= chunk_split(base64_encode($attachment['content'])) . "\r\n";

    }

    $body .= "--{$boundary}--\r\n";

    return $body;
}


/* -------------------------------------------------
   FALLBACK: PHP's built-in mail()
------------------------------------------------- */

function send_basic_mail(string $to, string $subject, string $html_body, array $cc = [], ?array $attachment = null): array
{
    $host = preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $from_email = defined('SMTP_FROM_EMAIL') && SMTP_FROM_EMAIL !== '' ? SMTP_FROM_EMAIL : ('quotes@' . $host);
    $from_name  = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'SecuTech SA';

    $boundary = '';
    $mime_body = build_mime_body($html_body, $attachment, $boundary);

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
    $headers .= "From: " . $from_name . " <" . $from_email . ">\r\n";
    $headers .= "Reply-To: " . $from_email . "\r\n";

    if (!empty($cc)) {
        $headers .= "Cc: " . implode(', ', $cc) . "\r\n";
    }

    $sent = @mail($to, $subject, $mime_body, $headers);

    return $sent
        ? ['success' => true, 'error' => '']
        : ['success' => false, 'error' => 'PHP mail() failed. Check your hosting mail configuration.'];
}


/* -------------------------------------------------
   AUTHENTICATED SMTP (real mailbox)
------------------------------------------------- */

function send_smtp_email(string $to, string $subject, string $html_body, array $cc = [], ?array $attachment = null): array
{
    $host = SMTP_HOST;
    $port = (int) SMTP_PORT;
    $encryption = strtolower(SMTP_ENCRYPTION);
    $username = SMTP_USER;
    $password = SMTP_PASS;
    $from_email = SMTP_FROM_EMAIL !== '' ? SMTP_FROM_EMAIL : $username;
    $from_name  = SMTP_FROM_NAME;

    $transport = ($encryption === 'ssl') ? 'ssl://' . $host : $host;

    $errno = 0;
    $errstr = '';

    $socket = @stream_socket_client($transport . ':' . $port, $errno, $errstr, 15);

    if (!$socket) {
        return ['success' => false, 'error' => "Could not connect to mail server ({$host}:{$port}): {$errstr}"];
    }

    stream_set_timeout($socket, 15);

    $read = function () use ($socket) {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };

    $write = function (string $cmd) use ($socket) {
        fwrite($socket, $cmd . "\r\n");
    };

    $response = $read();

    if (substr($response, 0, 3) !== '220') {
        fclose($socket);
        return ['success' => false, 'error' => 'Unexpected server greeting: ' . trim($response)];
    }

    $ehlo_domain = preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost');

    $write('EHLO ' . $ehlo_domain);
    $read();

    if ($encryption === 'tls') {

        $write('STARTTLS');
        $response = $read();

        if (substr($response, 0, 3) !== '220') {
            fclose($socket);
            return ['success' => false, 'error' => 'STARTTLS was rejected: ' . trim($response)];
        }

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return ['success' => false, 'error' => 'Could not establish a TLS-encrypted connection.'];
        }

        $write('EHLO ' . $ehlo_domain);
        $read();

    }

    if ($username !== '') {

        $write('AUTH LOGIN');
        $read();

        $write(base64_encode($username));
        $read();

        $write(base64_encode($password));
        $response = $read();

        if (substr($response, 0, 3) !== '235') {
            fclose($socket);
            return ['success' => false, 'error' => 'SMTP login failed - check the mailbox username/password: ' . trim($response)];
        }

    }

    $write('MAIL FROM:<' . $from_email . '>');
    $response = $read();

    if (substr($response, 0, 3) !== '250') {
        fclose($socket);
        return ['success' => false, 'error' => 'Sender address rejected: ' . trim($response)];
    }

    $recipients = array_merge([$to], $cc);

    foreach ($recipients as $recipient) {

        $write('RCPT TO:<' . $recipient . '>');
        $response = $read();

        if (substr($response, 0, 3) !== '250' && substr($response, 0, 3) !== '251') {
            fclose($socket);
            return ['success' => false, 'error' => "Recipient rejected ({$recipient}): " . trim($response)];
        }

    }

    $write('DATA');
    $response = $read();

    if (substr($response, 0, 3) !== '354') {
        fclose($socket);
        return ['success' => false, 'error' => 'Server rejected the message data step: ' . trim($response)];
    }

    $header_lines = [
        'From: ' . $from_name . ' <' . $from_email . '>',
        'To: ' . $to,
    ];

    if (!empty($cc)) {
        $header_lines[] = 'Cc: ' . implode(', ', $cc);
    }

    $header_lines[] = 'Subject: ' . $subject;
    $header_lines[] = 'MIME-Version: 1.0';

    $boundary = '';
    $mime_body = build_mime_body($html_body, $attachment, $boundary);

    $header_lines[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
    $header_lines[] = 'Date: ' . date('r');

    /* Per SMTP rules, lines starting with a lone "." must be escaped. */
    $escaped_body = preg_replace('/^\./m', '..', $mime_body);

    $message = implode("\r\n", $header_lines) . "\r\n\r\n" . $escaped_body . "\r\n.\r\n";

    fwrite($socket, $message);
    $response = $read();

    if (substr($response, 0, 3) !== '250') {
        fclose($socket);
        return ['success' => false, 'error' => 'Message was not accepted: ' . trim($response)];
    }

    $write('QUIT');
    fclose($socket);

    return ['success' => true, 'error' => ''];
}
