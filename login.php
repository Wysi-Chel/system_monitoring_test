<?php
require __DIR__ . "/includes/auth.php";

$nextUrl = getSafeAuthRedirectTarget($_GET["next"] ?? "index.php");
$loginError = "";

if (isMonitoringAuthenticated()) {
    header("Location: " . $nextUrl);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nextUrl = getSafeAuthRedirectTarget($_POST["next"] ?? "index.php");
    $password = (string) ($_POST["password"] ?? "");

    if (authenticateMonitoringPassword($password)) {
        header("Location: " . $nextUrl);
        exit;
    }

    $loginError = "Invalid password.";
}

function loginEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Monitoring Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/index.css">
</head>
<body class="page-login">
    <main class="login-shell">
        <section class="login-card" aria-labelledby="login-title">
            <div class="login-kicker">Restricted Access</div>
            <h1 id="login-title">System Monitoring</h1>

            <?php if ($loginError !== ""): ?>
            <div class="form-alert form-alert-error" role="alert"><?= loginEscape($loginError) ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="login-form">
                <input type="hidden" name="next" value="<?= loginEscape($nextUrl) ?>">

                <label for="login-password">Password</label>
                <input type="password" id="login-password" name="password" autocomplete="current-password" required autofocus>

                <button type="submit" class="primary">Login</button>
            </form>
        </section>
    </main>
</body>
</html>
