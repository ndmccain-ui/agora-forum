<?php
declare(strict_types=1);
defined('AGORA') or exit;

/**
 * Sending email.
 *
 * - "log":  write emails to data/mail.log instead of sending (local development)
 * - "mail": PHP's built-in mail(), which most shared hosts support
 * - "smtp": your email provider's server (most reliable — fewer emails land in spam)
 */
function mail_driver(): string
{
    $driver = setting('mail_driver');
    if (in_array($driver, ['log', 'mail', 'smtp'], true)) {
        return $driver;
    }
    return PHP_SAPI === 'cli-server' ? 'log' : 'mail';
}

function mail_from(): string
{
    $from = setting('mail_from', '');
    if ($from) {
        return $from;
    }
    $host = parse_url(site_url(), PHP_URL_HOST) ?: 'localhost';
    $host = preg_replace('/^www\./', '', $host);
    return 'noreply@' . $host;
}

/** Send a plain-text email. Returns true if it was handed off successfully; problems are logged. */
function send_mail(string $to, string $subject, string $body): bool
{
    try {
        deliver_mail($to, $subject, $body);
        return true;
    } catch (Throwable $e) {
        error_log("[Agora] Could not send email to $to ($subject): " . $e->getMessage());
        return false;
    }
}

/** Like send_mail(), but throws with the reason if sending fails (used by "Send test email"). */
function deliver_mail(string $to, string $subject, string $body): void
{
    $clean = fn (string $s) => str_replace(["\r", "\n"], '', $s);
    $to = $clean($to);
    $subject = str_replace(["\r", "\n"], ' ', $subject);
    $siteName = str_replace('"', '', $clean((string) setting('site_name', 'Agora Forum')));
    $from = $clean(mail_from());

    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $body .= "\n\n— " . $siteName . "\n" . site_url() . "\n";

    $driver = mail_driver();
    if ($driver === 'log') {
        $entry = str_repeat('=', 60) . "\n"
            . 'Date: ' . gmdate('Y-m-d H:i:s') . " UTC\n"
            . "To: $to\nFrom: \"$siteName\" <$from>\nSubject: $subject\n\n"
            . $body . "\n";
        if (file_put_contents(AGORA_DATA . '/mail.log', $entry, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Could not write data/mail.log.');
        }
        return;
    }

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedName = '=?UTF-8?B?' . base64_encode($siteName) . '?=';
    $headers = [
        'From' => $encodedName . ' <' . $from . '>',
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => 'quoted-printable',
        'X-Mailer' => 'Agora Forum',
    ];
    $encodedBody = quoted_printable_encode(str_replace("\n", "\r\n", $body));

    if ($driver === 'smtp') {
        smtp_send($from, $to, $headers + [
            'To' => $to,
            'Subject' => $encodedSubject,
            'Date' => date(DATE_RFC2822),
            'Message-ID' => '<' . bin2hex(random_bytes(12)) . '@' . (explode('@', $from)[1] ?? 'agora.local') . '>',
        ], $encodedBody);
        return;
    }

    if (!@mail($to, $encodedSubject, $encodedBody, $headers)) {
        throw new RuntimeException('PHP mail() refused the message. Your host may not allow it — try SMTP settings instead.');
    }
}

/* ---------- A small SMTP client ---------- */

function smtp_send(string $from, string $to, array $headers, string $body): void
{
    $host = (string) setting('smtp_host', '');
    $port = (int) setting('smtp_port', '587');
    $encryption = (string) setting('smtp_encryption', 'tls');
    if ($host === '') {
        throw new RuntimeException('No SMTP server is set.');
    }

    $context = stream_context_create(['ssl' => ['peer_name' => $host, 'verify_peer' => true, 'verify_peer_name' => true]]);
    $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $socket = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        throw new RuntimeException("Couldn't connect to $host on port $port ($errstr).");
    }
    stream_set_timeout($socket, 20);

    try {
        smtp_expect($socket, [220]);
        $hello = 'EHLO ' . (parse_url(site_url(), PHP_URL_HOST) ?: 'localhost');
        smtp_command($socket, $hello, [250]);

        if ($encryption === 'tls') {
            smtp_command($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Could not start a secure (TLS) connection.');
            }
            smtp_command($socket, $hello, [250]);
        }

        $username = (string) setting('smtp_username', '');
        if ($username !== '') {
            smtp_command($socket, 'AUTH LOGIN', [334]);
            smtp_command($socket, base64_encode($username), [334]);
            smtp_command($socket, base64_encode((string) setting('smtp_password', '')), [235], 'The username or password was rejected.');
        }

        smtp_command($socket, 'MAIL FROM:<' . $from . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);

        $message = '';
        foreach ($headers as $name => $value) {
            $message .= "$name: $value\r\n";
        }
        // Lines starting with "." must be doubled (SMTP rule).
        $message .= "\r\n" . preg_replace('/^\./m', '..', $body);
        fwrite($socket, $message . "\r\n.\r\n");
        smtp_expect($socket, [250]);
        fwrite($socket, "QUIT\r\n");
    } finally {
        fclose($socket);
    }
}

function smtp_command($socket, string $command, array $okCodes, ?string $friendlyError = null): string
{
    fwrite($socket, $command . "\r\n");
    return smtp_expect($socket, $okCodes, $friendlyError);
}

function smtp_expect($socket, array $okCodes, ?string $friendlyError = null): string
{
    $response = '';
    while (($line = fgets($socket, 1024)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') {
            break;
        }
    }
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $okCodes, true)) {
        $detail = trim($response) !== '' ? trim($response) : 'no response';
        throw new RuntimeException(($friendlyError ? $friendlyError . ' ' : '') . 'The mail server said: ' . $detail);
    }
    return $response;
}
