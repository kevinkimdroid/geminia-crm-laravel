<?php
/**
 * Standalone feedback app for geminialife.co.ke/feedback
 * Handles "Were you happy with our service?" form.
 * Deploy this folder to https://geminialife.co.ke/feedback/
 *
 * Requires: FEEDBACK_CRM_API_URL and FEEDBACK_PUBLIC_URL in config.php
 */

session_start();

$config = [
    'crm_api_url' => getenv('FEEDBACK_CRM_API_URL') ?: 'http://10.1.1.65',
    'app_name' => 'Geminia Life Insurance',
];

// Load config from config.php if present
if (file_exists(__DIR__ . '/config.php')) {
    $config = array_merge($config, require __DIR__ . '/config.php');
}

$crmBase = rtrim($config['crm_api_url'], '/');

function callCrm(string $url, string $method = 'GET', array $postData = []): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false) {
        return null;
    }
    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}

$ticket = (int) ($_GET['ticket'] ?? $_POST['ticket'] ?? 0);
$expires = $_GET['expires'] ?? $_POST['expires'] ?? '';
$signature = $_GET['signature'] ?? $_POST['signature'] ?? '';

// Thank you page
if (isset($_GET['thankyou']) || isset($_SESSION['feedback_submitted'])) {
    $_SESSION['feedback_submitted'] = true;
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Thank You — <?= htmlspecialchars($config['app_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>body{font-family:system-ui,sans-serif;background:#f8fafc;min-height:100vh;display:flex;align-items:center;justify-content:center;margin:0}.card{max-width:480px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08)}</style>
</head>
<body>
<div class="container">
    <div class="card p-4 text-center mx-auto">
        <div class="mb-3"><i class="bi bi-check-circle-fill text-success" style="font-size:4rem"></i></div>
        <h3 class="mb-2">Thank you for your feedback</h3>
        <p class="text-muted mb-0">We appreciate you taking the time to help us improve.</p>
    </div>
</div>
</body>
</html>
    <?php
    exit;
}

// POST: submit feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ticket && $expires && $signature) {
    $rating = $_POST['rating'] ?? '';
    $comment = trim($_POST['comment'] ?? '');

    $result = callCrm($crmBase . '/api/feedback/submit', 'POST', [
        'ticket' => $ticket,
        'expires' => $expires,
        'signature' => $signature,
        'rating' => $rating,
        'comment' => $comment,
    ]);

    if ($result && !empty($result['success'])) {
        $_SESSION['feedback_submitted'] = true;
        header('Location: ?thankyou=1');
        exit;
    }

    $error = $result['error'] ?? 'Unable to submit feedback. Please try again.';
}

// GET: validate and show form
elseif ($ticket && $expires && $signature) {
    $result = callCrm($crmBase . '/api/feedback/validate?' . http_build_query([
        'ticket' => $ticket,
        'expires' => $expires,
        'signature' => $signature,
    ]));

    if (!$result) {
        $error = 'Unable to verify this link. Please try again later.';
    } elseif (!empty($result['already_submitted'])) {
        header('Location: ?thankyou=1');
        exit;
    } elseif (empty($result['valid'])) {
        $error = $result['error'] ?? 'This feedback link has expired or is invalid.';
    } else {
        $ticketNo = $result['ticket_no'] ?? 'TT' . $ticket;
        $title = $result['title'] ?? 'Support request';
        $formUrl = ''; // POST to current URL (params in query string)
    }
} else {
    $error = 'Please use the link from the email we sent you after closing your support ticket. That link contains a secure token needed for this form.';
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rate Your Experience — <?= htmlspecialchars($config['app_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body{font-family:system-ui,sans-serif;background:#f8fafc;min-height:100vh;margin:0;padding:2rem 0}
        .feedback-card{max-width:480px;margin:0 auto;padding:2rem;background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08)}
        .feedback-logo{width:48px;height:48px;background:#1A559E;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:1.5rem;margin-bottom:1rem}
    </style>
</head>
<body>
<div class="container">
    <div class="text-center mb-3">
        <div class="feedback-logo"><i class="bi bi-heart-pulse"></i></div>
        <h5 class="text-muted mb-0"><?= htmlspecialchars($config['app_name']) ?></h5>
    </div>

    <?php if (isset($error)): ?>
    <div class="feedback-card mx-auto text-center">
        <div class="text-danger mb-2"><i class="bi bi-exclamation-circle" style="font-size:2rem"></i></div>
        <h5>Unable to load feedback form</h5>
        <p class="text-muted"><?= htmlspecialchars($error) ?></p>
    </div>
    <?php elseif (isset($formUrl)): ?>
    <div class="feedback-card mx-auto">
        <h4 class="mb-2">How was your experience?</h4>
        <p class="text-muted small mb-4">Your support ticket <strong><?= htmlspecialchars($ticketNo) ?></strong> — <?= htmlspecialchars(mb_substr($title, 0, 50)) ?> — has been closed. We'd love to hear from you.</p>

        <form method="POST" action="<?= htmlspecialchars($formUrl) ?>">
            <div class="mb-4">
                <label class="form-label fw-semibold">Were you happy with our service?</label>
                <div class="d-flex flex-column gap-2">
                    <div class="form-check form-check-lg p-3 border rounded">
                        <input class="form-check-input" type="radio" name="rating" id="r1" value="happy" required>
                        <label class="form-check-label fw-medium" for="r1"><i class="bi bi-emoji-smile text-success me-2"></i> Yes, I was happy</label>
                    </div>
                    <div class="form-check form-check-lg p-3 border rounded">
                        <input class="form-check-input" type="radio" name="rating" id="r2" value="not_happy">
                        <label class="form-check-label fw-medium" for="r2"><i class="bi bi-emoji-frown text-warning me-2"></i> No, I was not satisfied</label>
                    </div>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Additional comments <span class="text-muted">(optional)</span></label>
                <textarea name="comment" class="form-control" rows="3" placeholder="Any feedback you'd like to share..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send me-1"></i> Submit Feedback</button>
        </form>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
