<?php
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load .env if exists (optional — env vars also work standalone)
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// getenv() instead of $_ENV — Railway tidak populate $_ENV superglobal
$env = static fn(string $key) => ($v = getenv($key)) === false ? null : $v;

$smtpHost   = $env('SMTP_HOST')   ?? 'smtp.gmail.com';
$smtpPort   = $env('SMTP_PORT')   ?? '587';
$smtpUser   = $env('SMTP_USER')   ?? '';
$smtpPass   = $env('SMTP_PASS')   ?? '';
$smtpFrom   = $env('SMTP_FROM')   ?? $smtpUser;
$smtpFromName = $env('SMTP_FROM_NAME') ?? 'SMTP Test';

$result  = null; // null = no send yet, true = success, false = fail
$message = '';

print_r(getenv());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toEmail = trim($_POST['to_email'] ?? '');
    $subject = trim($_POST['subject'] ?? 'Test Email from SMTP PHP');
    $body    = trim($_POST['body'] ?? 'This is a test email sent via SMTP.');

    if ($toEmail === '') {
        $result  = false;
        $message = 'Recipient email is required.';
    } elseif ($smtpUser === '' || $smtpPass === '') {
        $result  = false;
        $message = 'SMTP_USER / SMTP_PASS not set. Check .env or environment variables.';
    } else {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpUser;
            $mail->Password   = $smtpPass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) $smtpPort;

            $mail->setFrom($smtpFrom, $smtpFromName);
            $mail->addAddress($toEmail);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            $result  = true;
            $message = "Email sent successfully to <strong>" . htmlspecialchars($toEmail) . "</strong>.";
        } catch (Exception $e) {
            $result  = false;
            $message = "Send failed: " . $mail->ErrorInfo;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Test</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5; display: flex; justify-content: center;
            padding: 40px 16px;
        }
        .card {
            background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.1);
            padding: 32px; width: 100%; max-width: 520px;
        }
        h1 { font-size: 1.4rem; margin-bottom: 8px; }
        p.sub { color: #666; font-size: .9rem; margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin: 14px 0 4px; font-size: .9rem; }
        input, textarea {
            width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 6px;
            font-size: .95rem;
        }
        textarea { resize: vertical; min-height: 100px; font-family: inherit; }
        button {
            margin-top: 18px; width: 100%; padding: 12px; background: #1a73e8;
            color: #fff; border: none; border-radius: 6px; font-size: 1rem;
            font-weight: 600; cursor: pointer;
        }
        button:hover { background: #1557b0; }

        .alert {
            margin-top: 18px; padding: 12px 16px; border-radius: 6px;
            font-size: .9rem;
        }
        .alert.success { background: #e6f4ea; color: #1e7e34; border: 1px solid #b7dfb9; }
        .alert.error   { background: #fce8e6; color: #c5221f; border: 1px solid #f5c6cb; }

        .env-note {
            margin-top: 20px; padding: 12px 16px; background: #fef7e0;
            border: 1px solid #f9e08b; border-radius: 6px; font-size: .82rem; color: #5f4b00;
        }
        code { background: #eee; padding: 1px 5px; border-radius: 3px; font-size: .85em; }
    </style>
</head>
<body>
    <div class="card">
        <h1>📧 SMTP Send Test</h1>
        <p class="sub">Send email via Gmail SMTP using environment credentials.</p>

        <?php if ($result === true): ?>
            <div class="alert success"><?= $message ?></div>
        <?php elseif ($result === false): ?>
            <div class="alert error"><?= $message ?></div>
        <?php endif; ?>

        <form method="post">
            <label for="to_email">To</label>
            <input type="email" name="to_email" id="to_email" required
                   value="<?= htmlspecialchars($_POST['to_email'] ?? '') ?>"
                   placeholder="recipient@gmail.com">

            <label for="subject">Subject</label>
            <input type="text" name="subject" id="subject"
                   value="<?= htmlspecialchars($_POST['subject'] ?? 'Test Email from SMTP PHP') ?>">

            <label for="body">Body</label>
            <textarea name="body" id="body"><?= htmlspecialchars($_POST['body'] ?? 'This is a test email sent via SMTP.') ?></textarea>

            <button type="submit">Send</button>
        </form>

        <div class="env-note">
            <strong>⚙️ Credentials</strong> are read from environment variables:
            <code>SMTP_USER</code>, <code>SMTP_PASS</code> (or <code>.env</code> file).
            <?php if ($smtpUser): ?>
                <br><br>📧 Using: <strong><?= htmlspecialchars($smtpUser) ?></strong>
            <?php else: ?>
                <br><br>⚠️ Not configured yet.
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
