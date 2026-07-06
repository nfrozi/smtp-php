<?php
require __DIR__ . '/vendor/autoload.php';

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

$env = static fn(string $key) => ($v = getenv($key)) === false ? null : $v;

$mjPublicKey  = $env('MJ_APIKEY_PUBLIC')  ?? '';
$mjPrivateKey = $env('MJ_APIKEY_PRIVATE') ?? '';
$mjFrom       = $env('MJ_FROM_EMAIL')     ?? '';
$mjFromName   = $env('MJ_FROM_NAME')     ?? 'SMTP Test';

$result  = null;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toEmail = trim($_POST['to_email'] ?? '');
    $subject = trim($_POST['subject'] ?? 'Test Email from SMTP PHP');
    $body    = trim($_POST['body'] ?? 'This is a test email sent via SMTP.');

    if ($toEmail === '') {
        $result  = false;
        $message = 'Recipient email is required.';
    } elseif ($mjPublicKey === '' || $mjPrivateKey === '' || $mjFrom === '') {
        $result  = false;
        $message = 'MJ_APIKEY_PUBLIC / MJ_APIKEY_PRIVATE / MJ_FROM_EMAIL not set. Check .env or environment variables.';
    } else {
        try {
            // Mailjet Send API v3.1
            $mj = new \Mailjet\Client($mjPublicKey, $mjPrivateKey, true, ['version' => 'v3.1']);
            $payload = [
                'Messages' => [
                    [
                        'From'      => ['Email' => $mjFrom, 'Name' => $mjFromName],
                        'To'        => [['Email' => $toEmail]],
                        'Subject'   => $subject,
                        'TextPart'  => $body,
                        'HTMLPart'  => nl2br(htmlspecialchars($body)),
                    ],
                ],
            ];
            $response = $mj->post(\Mailjet\Resources::$Email, ['body' => $payload]);

            if ($response->success()) {
                $data       = $response->getData();
                $msgStatus  = $data[0]['Status'] ?? 'Sent';
                $msgId      = $data[0]['To'][0]['MessageID'] ?? null;
                $result     = true;
                $message    = "Email sent to <strong>" . htmlspecialchars($toEmail) . "</strong>";
                if ($msgId) {
                    $message .= " (MessageID: " . htmlspecialchars((string) $msgId) . ", Status: " . htmlspecialchars($msgStatus) . ")";
                }
            } else {
                $result  = false;
                $reasons = $response->getReasonPhrase();
                $details = '';
                $bodyData = $response->getData();
                if (!empty($bodyData['Messages'][0]['Errors'])) {
                    $errs = array_map(
                        static fn($e) => ($e['ErrorMessage'] ?? $e['ErrorCode'] ?? json_encode($e)),
                        $bodyData['Messages'][0]['Errors']
                    );
                    $details = ' — ' . implode('; ', $errs);
                }
                $message = "Send failed: " . $reasons . $details;
            }
        } catch (\Throwable $e) {
            $result  = false;
            $message = "Send failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mailjet Send Test</title>
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
        <h1>📧 Mailjet Send Test</h1>
        <p class="sub">Send email via Mailjet Transactional API using environment credentials.</p>

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
            <code>MJ_APIKEY_PUBLIC</code>, <code>MJ_APIKEY_PRIVATE</code>, <code>MJ_FROM_EMAIL</code> (or <code>.env</code> file).
            <?php if ($mjFrom): ?>
                <br><br>📧 Using: <strong><?= htmlspecialchars($mjFrom) ?></strong>
            <?php else: ?>
                <br><br>⚠️ Not configured yet.
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
