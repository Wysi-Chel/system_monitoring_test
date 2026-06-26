<?php
require __DIR__ . "/includes/auth.php";
requireMonitoringAuthentication();
require "config.php";
require __DIR__ . "/includes/monitoring_options.php";
require __DIR__ . "/includes/monitoring_helpers.php";

if (!isApplicationTestEnvironment()) {
    http_response_code(403);
    echo "This tool is only available on the test server.";
    exit;
}

if (!isLocalWebRequest()) {
    http_response_code(403);
    echo "This tool is only available from localhost.";
    exit;
}

$company = resolveCompanyConfig($_REQUEST["company"] ?? null, $companyConfigs);
$headerKicker = $company["company_name"];
$headerTitle = "Promote Test Changes";
$headerDescription = "Review the current test-to-live diff, then promote approved changes to the live server.";
$showCompanySwitch = false;
$mitsubishiUrl = buildUrl("index.php", ["company" => "mitsubishi"]);
$hyundaiUrl = buildUrl("index.php", ["company" => "hyundai"]);
$backUrl = buildUrl("index.php", ["company" => $company["key"]]);

function getPromotionPowerShellCommand(): string
{
    $candidates = [
        "C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe",
        "powershell",
    ];

    foreach ($candidates as $candidate) {
        if ($candidate === "powershell" || is_file($candidate)) {
            return $candidate;
        }
    }

    return "powershell";
}

function runPromotionWorkflow(bool $apply): array
{
    $powerShellCommand = getPromotionPowerShellCommand();
    $scriptPath = __DIR__ . DIRECTORY_SEPARATOR . "scripts" . DIRECTORY_SEPARATOR . "promote_to_live.ps1";

    $command = escapeshellarg($powerShellCommand)
        . " -NoProfile -NonInteractive -ExecutionPolicy Bypass -File "
        . escapeshellarg($scriptPath);

    if ($apply) {
        $command .= " -Apply";
    }

    $descriptorSpec = [
        1 => ["pipe", "w"],
        2 => ["pipe", "w"],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, __DIR__);
    if (!is_resource($process)) {
        return [
            "exit_code" => 1,
            "output" => "Unable to start the promote-to-live script.",
        ];
    }

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    $output = trim($stdout . ($stderr !== "" ? PHP_EOL . $stderr : ""));

    if ($output === "") {
        $output = $exitCode === 0
            ? "The promote-to-live script finished without any output."
            : "The promote-to-live script failed without any output.";
    }

    return [
        "exit_code" => $exitCode,
        "output" => $output,
    ];
}

$didApplyPromotion = ($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST";
$promotionResult = runPromotionWorkflow($didApplyPromotion);
$promotionSucceeded = (int) ($promotionResult["exit_code"] ?? 1) === 0;
$primaryActionLabel = $didApplyPromotion ? "Promotion Result" : "Promotion Preview";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Promote Test Changes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="<?= e($company["logo_type"]) ?>" href="<?= e($company["logo_path"]) ?>">
    <link rel="shortcut icon" type="<?= e($company["logo_type"]) ?>" href="<?= e($company["logo_path"]) ?>">
    <script src="assets/js/theme-init.js"></script>
    <link rel="stylesheet" href="<?= e(buildVersionedAssetPath("assets/css/index.css")) ?>">
</head>
<body class="company-<?= e($company["key"]) ?> page-system-monitoring page-promotion-tools">
<?php require __DIR__ . "/includes/partials/page_header.php"; ?>

<main>
    <section class="card">
        <div class="summary-header">
            <div>
                <h2><?= e($primaryActionLabel) ?></h2>
                <p class="note">This page runs only on the test server and promotes code into <code>localhost/system_monitoring</code>.</p>
            </div>
            <a href="<?= e($backUrl) ?>" class="button-link secondary icon-button" aria-label="Back to summary" title="Back to summary">
                <?= iconSvg("arrow-left") ?>
                <span class="sr-only">Back to summary</span>
            </a>
        </div>

        <div class="promotion-meta">
            <div><strong>Current Server:</strong> <?= e(getApplicationEnvironmentDisplayLabel()) ?></div>
            <div><strong>Source:</strong> <code>C:\xampp\htdocs\system_monitoring_test</code></div>
            <div><strong>Target:</strong> <code>C:\xampp\htdocs\system_monitoring</code></div>
            <div><strong>Database:</strong> <?= e($dbname) ?></div>
        </div>

        <pre class="promotion-log<?= $promotionSucceeded ? " success" : " error" ?>"><?= e($promotionResult["output"] ?? "") ?></pre>

        <div class="buttons">
            <a href="<?= e(buildUrl("promote_to_live.php", ["company" => $company["key"]])) ?>" class="button-link secondary icon-button" aria-label="Refresh preview" title="Refresh preview">
                <?= iconSvg("refresh") ?>
                <span class="sr-only">Refresh preview</span>
            </a>
            <form action="promote_to_live.php" method="POST" class="inline-button-form" onsubmit="return confirm('Promote the current test changes to the live server now?');">
                <input type="hidden" name="company" value="<?= e($company["key"]) ?>">
                <button type="submit" class="primary icon-button" aria-label="Promote to live" title="Promote to live">
                    <?= iconSvg("upload") ?>
                    <span class="sr-only">Promote to live</span>
                </button>
            </form>
        </div>
    </section>
</main>

<script src="assets/js/index.js" defer></script>
</body>
</html>
