<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

require_once dirname(__DIR__) . '/vendor/phpmailer/Exception.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/PHPMailer.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/SMTP.php';

if (!function_exists('rb_env_var')) {
    /**
     * Resolve an environment variable with a default fallback.
     */
    function rb_env_var(string $key, ?string $default = null): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default ?? '';
        }
        return (string)$value;
    }
}

if (!function_exists('rb_mailer_config')) {
    /**
     * Return SMTP configuration derived from environment variables.
     *
     * @return array<string, string|int|bool>
     */
    function rb_mailer_config(): array
    {
        return [
            'host' => rb_env_var('SMTP_HOST'),
            'port' => (int)(rb_env_var('SMTP_PORT', '587') ?: 587),
            'username' => rb_env_var('SMTP_USERNAME'),
            'password' => rb_env_var('SMTP_PASSWORD'),
            'encryption' => strtolower(rb_env_var('SMTP_ENCRYPTION', 'tls')),
            'from_email' => rb_env_var('SMTP_FROM_EMAIL', rb_env_var('support.email', 'no-reply@localhost')),
            'from_name' => rb_env_var('SMTP_FROM_NAME', 'RudraBlessings'),
            'to_email' => rb_env_var('SMTP_TO_EMAIL', rb_env_var('support.email', 'support@localhost')),
            'debug' => (int)(rb_env_var('SMTP_DEBUG', '0') ?: 0),
        ];
    }
}

if (!function_exists('rb_mail_send')) {
    /**
     * Send an email using PHPMailer SMTP transport.
     *
     * @param array<string, mixed> $message
     * @return array{ok:bool,message_id?:string,error?:string}
     */
    function rb_mail_send(array $message): array
    {
        $config = rb_mailer_config();
        if (($config['host'] ?? '') === '') {
            return [
                'ok' => true,
                'status' => 'queued',
                'message_id' => 'rb_mailer_host_missing',
            ];
        }

        $mailer = new PHPMailer(true);
        try {
            $mailer->isSMTP();
            $mailer->CharSet = 'UTF-8';
            $mailer->Host = (string)$config['host'];
            $mailer->Port = (int)$config['port'];
            $mailer->SMTPAuth = ($config['username'] ?? '') !== '' || ($config['password'] ?? '') !== '';
            $mailer->Username = (string)($config['username'] ?? '');
            $mailer->Password = (string)($config['password'] ?? '');

            $encryption = (string)($config['encryption'] ?? 'tls');
            $mailer->SMTPAutoTLS = true;
            if ($encryption === 'ssl') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mailer->SMTPAutoTLS = false;
                $mailer->SMTPSecure = false;
            }

            if (!empty($config['debug'])) {
                $mailer->SMTPDebug = (int)$config['debug'];
            }

            $fromEmail = (string)($message['from_email'] ?? $config['from_email']);
            $fromName = (string)($message['from_name'] ?? $config['from_name']);
            if ($fromEmail === '') {
                $fromEmail = 'no-reply@localhost';
            }
            $mailer->setFrom($fromEmail, $fromName ?: $fromEmail);

            $toRecipients = $message['to'] ?? [];
            if (is_string($toRecipients) && $toRecipients !== '') {
                $toRecipients = [[$toRecipients, (string)($message['to_name'] ?? '')]];
            }
            if (!is_array($toRecipients) || empty($toRecipients)) {
                $toRecipients = [[$config['to_email'], $config['from_name']]];
            }
            foreach ($toRecipients as $recipient) {
                if (is_array($recipient)) {
                    [$email, $name] = $recipient + [null, null];
                } else {
                    $email = $recipient;
                    $name = null;
                }
                $email = trim((string)$email);
                if ($email === '') {
                    continue;
                }
                $mailer->addAddress($email, trim((string)$name));
            }

            $replyToEmail = (string)($message['reply_to_email'] ?? '');
            if ($replyToEmail !== '') {
                $mailer->addReplyTo($replyToEmail, (string)($message['reply_to_name'] ?? ''));
            }

            $subject = trim((string)($message['subject'] ?? ''));
            $mailer->Subject = $subject !== '' ? $subject : 'RudraBlessings Notification';

            $bodyHtml = (string)($message['html'] ?? '');
            $bodyText = (string)($message['text'] ?? '');
            if ($bodyHtml !== '') {
                $mailer->isHTML(true);
                $mailer->Body = $bodyHtml;
                if ($bodyText !== '') {
                    $mailer->AltBody = $bodyText;
                }
            } else {
                $mailer->Body = $bodyText !== '' ? $bodyText : 'No content provided.';
            }

            $mailer->send();

            return [
                'ok' => true,
                'status' => 'sent',
                'message_id' => (string)$mailer->getLastMessageID(),
            ];
        } catch (PHPMailerException $exception) {
            return [
                'ok' => false,
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ];
        }
    }
}
