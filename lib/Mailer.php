<?php
namespace Kontact;

class Mailer {
    private static function _log_mailer(string $file, string $line): void {
        try { if (function_exists('kontact_log')) { kontact_log($file, $line); } } catch (\Throwable $e) {}
    }

    public static function send(string $to, string $subject, string $html, string $text, array $opts): bool {
        $enable = (bool)($opts['enable_smtp'] ?? false);
        self::_log_mailer('delivery.log', 'mailer_entry enable_smtp='.(int)$enable);
        return $enable ? self::sendSmtp($to,$subject,$html,$text,$opts) : self::sendMailFunction($to,$subject,$html,$text,$opts);
    }

    private static function buildMimeMessage(string $to, string $subject, string $html, string $text, array $opts): array {
        $fromEmail = (string)($opts['from_email'] ?? '');
        $fromName  = (string)($opts['from_name'] ?? '');
        $replyTo   = (string)(($opts['reply_email'] ?? '') ?: $fromEmail);
        $uid = bin2hex(random_bytes(8));
        $boundary = '=_kontact_' . $uid;
        $date = gmdate('D, d M Y H:i:s') . ' +0000';
        $mid  = sprintf('<%s.%s@%s>', $uid, (string)time(), self::guessDomain($fromEmail));

        $subject = preg_replace('/[\r\n]+/', ' ', (string)$subject);

        if ($text === '' || $text === null) {
            $t = strip_tags((string)$html);
            $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $t = preg_replace('/[ \t]+/', ' ', $t);
            $t = preg_replace('/\r\n|\n|\r/', "\n", $t);
            $text = trim($t);
        }

        $qp = function(string $s): string {
            $s = preg_replace('/\r\n|\n|\r/', "\r\n", $s);
            if (function_exists('quoted_printable_encode')) return quoted_printable_encode($s);
            $out = '';
            foreach (explode("\r\n", $s) as $line) {
                while (strlen($line) > 76) { $out .= substr($line,0,76) . "=\r\n"; $line = substr($line,76); }
                $out .= $line . "\r\n";
            }
            return $out;
        };

        $headers = [];
        $fromHdr = $fromName ? sprintf('%s <%s>', $fromName, $fromEmail) : $fromEmail;
        $headers[] = 'Date: ' . $date;
        $headers[] = 'From: ' . $fromHdr;
        $headers[] = 'Reply-To: ' . $replyTo;
        $headers[] = 'To: ' . $to;
        $headers[] = 'Message-ID: ' . $mid;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'X-Mailer: Kontact';
        $headers[] = 'Subject: ' . self::encodeHeader($subject);
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $body  = 'This is a multi-part message in MIME format.' . "\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
        $body .= 'Content-Transfer-Encoding: quoted-printable' . "\r\n\r\n";
        $body .= $qp((string)$text) . "\r\n";

        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
        $body .= 'Content-Transfer-Encoding: quoted-printable' . "\r\n\r\n";
        $body .= $qp((string)$html) . "\r\n";

        $body .= '--' . $boundary . '--' . "\r\n";

        $headers_str = implode("\r\n", $headers);
        $headers_str = preg_replace('/\r\n|\n|\r/', "\r\n", $headers_str);
        $body        = preg_replace('/\r\n|\n|\r/', "\r\n", $body);
        $headers_str = self::foldHeaders($headers_str);

        // DKIM hook
        $hdrAssoc = [];
        foreach (explode("\r\n", $headers_str) as $hline) {
            if ($hline === '') continue;
            $pp = strpos($hline, ':');
            if ($pp === false) continue;
            $hn = substr($hline, 0, $pp);
            $hv = ltrim(substr($hline, $pp+1));
            $hdrAssoc[$hn] = $hv;
        }
        $dkimHeader = self::dkimSign($hdrAssoc, $body, $opts);
        if ($dkimHeader !== '') {
            $headers_str = $dkimHeader . "\r\n" . $headers_str;
        }
        return ['headers' => $headers_str, 'body' => $body];
    }

    private static function foldHeaders(string $headers): string {
        $out = [];
        foreach (explode("\r\n", $headers) as $line) {
            if (strlen($line) <= 78) { $out[] = $line; continue; }
            $pos = strpos($line, ':');
            if ($pos !== false) {
                $name = substr($line, 0, $pos);
                $val  = ltrim(substr($line, $pos+1));
                $folded = $name . ': ';
                $col = strlen($name) + 2;
                foreach (preg_split('/\s+/', $val) as $tok) {
                    if ($col + strlen($tok) + 1 > 78) { $folded .= "\r\n\t"; $col = 1; }
                    elseif ($folded !== '' && !in_array(substr($folded,-1), [' ', "\t"], true)) { $folded .= ' '; $col++; }
                    $folded .= $tok; $col += strlen($tok);
                }
                $out[] = $folded;
            } else {
                $out[] = $line;
            }
        }
        return implode("\r\n", $out);
    }

    private static function encodeHeader(string $val): string {
        if (preg_match('/[^\x20-\x7E]/', $val)) {
            $b64 = base64_encode($val);
            return '=?UTF-8?B?'.$b64.'?=';
        }
        return $val;
    }

    private static function guessDomain(string $email): string {
        $p = explode('@', $email);
        return (isset($p[1]) && $p[1] !== '') ? $p[1] : 'localhost';
    }

    private static function idnEnvelope(string $email): string {
        if (!function_exists('idn_to_ascii')) return $email;
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) return $email;
        [$local, $domain] = $parts;
        $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        return $ascii ? ($local . '@' . $ascii) : $email;
    }

    private static function sendSmtp(string $to, string $subject, string $html, string $text, array $opts): bool {
        $host = (string)($opts['host'] ?? '');
        $port = (int)($opts['port'] ?? 587);
        $user = (string)($opts['user'] ?? '');
        $pass = (string)($opts['pass'] ?? '');
        $secure = (string)($opts['secure'] ?? 'tls');
        $fromEmail = (string)($opts['from_email'] ?? '');
        $fromName  = (string)($opts['from_name'] ?? '');

        try {
            if ($host === '') { self::_log_mailer('smtp.log','empty_host'); return false; }

            $scheme = 'tcp';
            if ($secure === 'ssl') {
                if ($port === 587) $port = 465;
                $scheme = 'ssl';
            }

            $context = stream_context_create([
                'ssl' => [
                    'verify_peer'       => true,
                    'verify_peer_name'  => true,
                    'allow_self_signed' => false,
                    'SNI_enabled'       => true,
                    'peer_name'         => $host,
                ],
            ]);

            $fp = @stream_socket_client(
                $scheme . '://' . $host . ':' . (int)$port,
                $errno, $errstr, 10.0,
                STREAM_CLIENT_CONNECT, $context
            );
            if (!$fp) { self::_log_mailer('smtp.log', 'connect_fail host=' . $host . ' port=' . $port . ' err=' . $errstr); return false; }

            $say = function(string $cmd) use ($fp): string {
                if ($cmd !== '') fwrite($fp, $cmd . "\r\n");
                $resp = '';
                while (!feof($fp)) {
                    $line = fgets($fp, 515);
                    if ($line === false) break;
                    $resp .= $line;
                    if (strlen($line) < 4 || $line[3] !== '-') break;
                }
                return $resp;
            };

            $greeting = fgets($fp, 515) ?: '';
            if ((int)substr($greeting,0,3) !== 220) { self::_log_mailer('smtp.log','bad_greeting resp='.trim($greeting)); fclose($fp); return false; }
            else { self::_log_mailer('smtp.log','connected host=' . $host . ' port=' . $port . ' scheme=' . $scheme); }

            $cmd = function(string $line) use ($say): string {
                $resp = $say($line);
                if ((int)substr($resp,0,3) >= 400) throw new \RuntimeException('SMTP failed: '.$resp);
                return $resp;
            };

            $cmd('EHLO localhost');
            if ($secure === 'tls') {
                $cmd('STARTTLS');
                if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('STARTTLS failed');
                }
                $cmd('EHLO localhost');
            }

            if ($user !== '' && $pass !== '') {
                self::_log_mailer('smtp.log','auth_begin user_present=1');
                $cmd('AUTH LOGIN');
                $cmd(base64_encode($user));
                $cmd(base64_encode($pass));
            }

            $envFrom = self::idnEnvelope($fromEmail);
            $envTo   = self::idnEnvelope($to);

            $cmd('MAIL FROM:<'.$envFrom.'>');
            $cmd('RCPT TO:<'.$envTo.'>');

            $built = self::buildMimeMessage($to,$subject,$html,$text,$opts);
            $full = $built['headers'] . "\r\n\r\n" . $built['body'];

            $cmd('DATA');
            $data = preg_replace('/\r\n|\n|\r/', "\r\n", $full);
            $data = preg_replace('/^\./m', '..', $data);
            fwrite($fp, $data . "\r\n.\r\n");
            $resp = $say('');
            if ((int)substr($resp,0,3) >= 400) { throw new \RuntimeException('SMTP DATA failed: '.$resp); }

            $cmd('QUIT');
            fclose($fp);
            return true;
        } catch (\Throwable $e) {
            self::_log_mailer('smtp.log','exception '.get_class($e).': '.$e->getMessage());
            return false;
        }
    }

    private static function sendMailFunction(string $to, string $subject, string $html, string $text, array $opts): bool {
        $built = self::buildMimeMessage($to,$subject,$html,$text,$opts);
        $headers_str = $built['headers'];
        $body = $built['body'];

        $headers_filtered = [];
        foreach (explode("\r\n", $headers_str) as $h) {
            if ($h === '') continue;
            if (stripos($h, 'To:') === 0 || stripos($h, 'Subject:') === 0) continue;
            $headers_filtered[] = $h;
        }
        $extra = '';
        if (!empty($opts['from_email'])) {
            $extra = '-f ' . escapeshellarg((string)$opts['from_email']);
        }
        $ok = @mail($to, $subject, $body, implode("\r\n", $headers_filtered), $extra);
        self::_log_mailer('mail_function.log','send='.(int)$ok.' to='.$to);
        return (bool)$ok;
    }

    /** DKIM relaxed/relaxed canonicalization and signing */
    private static function dkimCanonicalizeBodyRelaxed(string $body): string {
        // Normalize CRLF
        $b = preg_replace('/\r\n|\n|\r/', "\r\n", $body);
        // Reduce WSP within lines and trim trailing WSP
        $b = preg_replace_callback('/[^\r\n]+/', function ($m) {
            $line = $m[0];
            $line = preg_replace('/[ \t]+/', ' ', $line);
            $line = rtrim($line, " \t");
            return $line;
        }, $b);
        // Remove trailing empty lines -> ensure single CRLF at end
        $b = preg_replace('/(\r\n)*\z/', "\r\n", $b);
        return $b;
    }

    private static function dkimCanonicalizeHeaderRelaxed(string $name, string $value): string {
        // Lowercase field name, unfold continuation lines, compress WSP, trim
        $n = strtolower($name);
        $v = preg_replace('/\r\n[ \t]+/', ' ', $value); // unfold
        $v = preg_replace('/[ \t]+/', ' ', $v);
        $v = trim($v, " \t");
        return $n . ':' . $v . "\r\n";
    }

    private static function dkimSign(array $hdrAssoc, string $body, array $opts): string {
        $domain   = (string)($opts['dkim_domain'] ?? '');
        $selector = (string)($opts['dkim_selector'] ?? '');
        $pkey     = (string)($opts['dkim_private_key'] ?? '');
        $identity = (string)($opts['dkim_identity'] ?? '');
        if ($domain === '' || $selector === '' || $pkey === '') return '';

        // Accept either a file path or a PEM string
        $pem = '';
        if (strpos($pkey, '-----BEGIN') !== false) {
            $pem = $pkey;
        } else if (is_file($pkey)) {
            $pem = @file_get_contents($pkey) ?: '';
        }
        if ($pem === '') return '';

        $headersToSign = ['From','To','Subject','Date','Message-ID','MIME-Version','Content-Type','Reply-To','X-Mailer'];
        $signedNames = [];
        $canonHeaders = '';

        foreach ($headersToSign as $hn) {
            if (!isset($hdrAssoc[$hn])) continue;
            $val = $hdrAssoc[$hn];
            $canonHeaders .= self::dkimCanonicalizeHeaderRelaxed($hn, $val);
            $signedNames[] = strtolower($hn);
        }

        // Body hash
        $canonBody = self::dkimCanonicalizeBodyRelaxed($body);
        $bh = base64_encode(hash('sha256', $canonBody, true));

        // Build DKIM-Signature header without b=
        $dkim = [
            'v=1',
            'a=rsa-sha256',
            'c=relaxed/relaxed',
            'd='.$domain,
            's='.$selector,
            'h=' . implode(':', $signedNames),
            'bh='.$bh,
        ];
        if ($identity !== '') $dkim[] = 'i=' . $identity;
        $dkimHeader = 'DKIM-Signature: ' . implode('; ', $dkim) . '; b=';

        // Canonicalize DKIM header (without the signature value) and append to headers for signing
        $canonDkimHeader = self::dkimCanonicalizeHeaderRelaxed('DKIM-Signature', substr($dkimHeader, strlen('DKIM-Signature: ')));

        $signingData = $canonHeaders . $canonDkimHeader;

        // Sign
        if (!function_exists('openssl_sign')) return '';
        $key = @openssl_pkey_get_private($pem);
        if (!$key) return '';
        $sig = '';
        $ok = @openssl_sign($signingData, $sig, $key, OPENSSL_ALGO_SHA256);
        @openssl_pkey_free($key);
        if (!$ok) return '';

        $b = rtrim(chunk_split(base64_encode($sig), 73, "\r\n\t"));

        // Fold DKIM header per 78-char lines
        $fullHeader = $dkimHeader . $b;
        $folded = self::foldHeaders($fullHeader);
        return $folded;
    }

}
