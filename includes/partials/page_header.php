<?php
$headerKicker = $headerKicker ?? $company["company_name"];
$headerTitle = $headerTitle ?? $company["system_name"];
$headerDescription = $headerDescription ?? "";
$showCompanySwitch = $showCompanySwitch ?? true;
$appEnvironmentLabel = getApplicationEnvironmentDisplayLabel();
$currentScript = basename((string) ($_SERVER["SCRIPT_NAME"] ?? "index.php"));
$todayDisplay = (new DateTimeImmutable("now", new DateTimeZone("Asia/Manila")))->format("M d, Y");

$monitoringHomeUrl = buildUrl("index.php", ["company" => $company["key"]]);
$encodeRecordUrl = $currentScript === "index.php"
    ? "#encode-section"
    : $monitoringHomeUrl . "#encode-section";
$summaryUrl = $currentScript === "index.php"
    ? "#summary-section"
    : $monitoringHomeUrl . "#summary-section";
$recordLookupUrl = buildUrl("monitoring_record.php", ["company" => $company["key"]]);
$ticketNavUrl = $ticketMonitoringUrl ?? buildUrl("ticket_monitoring.php", ["company" => $company["key"]]);
$promotionUrl = buildUrl("promote_to_live.php", ["company" => $company["key"]]);

$navItems = [
    [
        "label" => "Dashboard",
        "href" => $monitoringHomeUrl,
        "script" => "index.php",
    ],
    [
        "label" => "Ticket Monitoring",
        "href" => $ticketNavUrl,
        "script" => "ticket_monitoring.php",
        "visible" => companySupportsTicketMonitoring($company),
    ],
    [
        "label" => "Record Details",
        "href" => $recordLookupUrl,
        "script" => "monitoring_record.php",
    ],
    [
        "label" => "Promote To Live",
        "href" => $promotionUrl,
        "script" => "promote_to_live.php",
        "visible" => canAccessPromoteToLiveUi(),
    ],
];
?>
<aside class="app-sidebar">
    <div class="brand-block">
        <div class="brand-mark"><?= e(strtoupper(substr($company["company_name"], 0, 2))) ?></div>
        <div>
            <div class="brand-kicker">System Monitoring</div>
            <h2 class="brand-title"><?= e($company["system_name"]) ?></h2>
        </div>
    </div>

    <?php if ($showCompanySwitch): ?>
    <section class="sidebar-panel">
        <div class="sidebar-panel-label">Company Workspace</div>
        <div class="company-switch" aria-label="Switch company">
            <a href="<?= e($mitsubishiUrl) ?>" class="switch-link<?= $company["key"] === "mitsubishi" ? " active" : "" ?>">Mitsubishi</a>
            <a href="<?= e($hyundaiUrl) ?>" class="switch-link<?= $company["key"] === "hyundai" ? " active" : "" ?>">Hyundai</a>
        </div>
    </section>
    <?php endif; ?>

    <section class="sidebar-panel">
        <div class="sidebar-panel-label">Workspace</div>
        <nav class="sidebar-nav" aria-label="Primary navigation">
            <?php foreach ($navItems as $item): ?>
                <?php if (array_key_exists("visible", $item) && !$item["visible"]): ?>
                    <?php continue; ?>
                <?php endif; ?>
                <a href="<?= e($item["href"]) ?>" class="sidebar-link<?= $currentScript === $item["script"] ? " active" : "" ?>">
                    <span><?= e($item["label"]) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </section>

    <section class="sidebar-action">
        <div class="sidebar-panel-label">Quick Action</div>
        <a href="<?= e($encodeRecordUrl) ?>" class="button-link primary">Encode New Record</a>
        <a href="<?= e($summaryUrl) ?>" class="button-link secondary">Open Summary</a>
        <?php if (companySupportsTicketMonitoring($company)): ?>
        <a href="<?= e($ticketNavUrl) ?>" class="button-link secondary">Open Tickets</a>
        <?php endif; ?>
    </section>

    <div class="sidebar-footer">
        <div class="sidebar-note">
            <span>Server</span>
            <strong><?= e($appEnvironmentLabel) ?></strong>
        </div>
        <div class="sidebar-note">
            <span>Today</span>
            <strong><?= e($todayDisplay) ?></strong>
        </div>
    </div>
</aside>

<header class="app-topbar">
    <div class="topbar-copy">
        <p class="eyebrow"><?= e($headerKicker) ?></p>
        <h1 class="page-title"><?= e($headerTitle) ?></h1>
        <?php if ($headerDescription !== ""): ?>
        <p class="page-description"><?= e($headerDescription) ?></p>
        <?php endif; ?>
        <div class="topbar-badges">
            <span class="info-pill"><?= e($company["company_name"]) ?> Workspace</span>
            <?php if (isApplicationTestEnvironment()): ?>
            <span class="info-pill info-pill-muted"><?= e($appEnvironmentLabel) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="topbar-meta">
        <?php if (canAccessPromoteToLiveUi()): ?>
        <a href="<?= e($promotionUrl) ?>" class="button-link secondary topbar-inline-action">Promote To Live</a>
        <?php endif; ?>
        <button type="button" class="theme-toggle" id="theme-toggle" aria-pressed="false">Dark Mode</button>
    </div>
</header>
