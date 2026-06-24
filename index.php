<?php
require "config.php";
require __DIR__ . "/includes/monitoring_options.php";
require __DIR__ . "/includes/monitoring_helpers.php";
require __DIR__ . "/includes/monitoring_repository.php";

$today = (new DateTimeImmutable("now", new DateTimeZone("Asia/Manila")))->format("Y-m-d");
$company = resolveCompanyConfig($_GET["company"] ?? null, $companyConfigs);
$fixedBranch = $company["fixed_branch"] ?? null;
$showBranchSelector = $fixedBranch === null;
ensureMonitoringTable($pdo, $company);
if (companySupportsTicketMonitoring($company)) {
    ensureTicketMonitoringTable($pdo, $company);
}
$nextMonitoringIdentificationNumber = getNextMonitoringIdentificationNumber($pdo, $company);

$filterOptions = [
    "branch" => $branchOptions,
    "dealer" => $dealerOptions,
    "department" => $departmentOptions,
    "module" => $moduleOptions,
    "status" => $summaryStatusOptions,
    "per_page" => $monitoringSummaryRowsPerPageOptions,
];

$tableNameSql = quoteMysqlIdentifier($company["table_name"]);
$filters = buildMonitoringFilters($_GET, $company, $filterOptions);

if (!empty($filters["escalation_only"])) {
    $dashboardRecords = fetchMonitoringRecords($pdo, $tableNameSql, $filters);
    $dashboardRecords = enrichMonitoringRecordsWithDataCorrectionActions($pdo, $tableNameSql, $dashboardRecords);
    $dashboardRecords = filterEscalationCandidateMonitoringRecords($dashboardRecords);
    $totalRecords = count($dashboardRecords);
    $pagination = buildPaginationState($filters["page"], $filters["per_page"], $totalRecords);
    $filters["page"] = $pagination["page"];
    $records = array_slice($dashboardRecords, $pagination["offset"], $pagination["limit"]);
} else {
    $totalRecords = countMonitoringRecords($pdo, $tableNameSql, $filters);
    $pagination = buildPaginationState($filters["page"], $filters["per_page"], $totalRecords);
    $filters["page"] = $pagination["page"];
    $records = fetchMonitoringRecords($pdo, $tableNameSql, $filters, $pagination["limit"], $pagination["offset"]);
    $records = enrichMonitoringRecordsWithDataCorrectionActions($pdo, $tableNameSql, $records);
    $dashboardRecords = fetchMonitoringRecords($pdo, $tableNameSql, $filters);
    $dashboardRecords = enrichMonitoringRecordsWithDataCorrectionActions($pdo, $tableNameSql, $dashboardRecords);
}

$dashboardData = buildMonitoringDashboardData(
    $dashboardRecords,
    $summaryStatusOptions,
    $processedTypeOptions,
    $classificationOptions
);

$ticketDashboardData = null;
if (companySupportsTicketMonitoring($company)) {
    $ticketTableNameSql = quoteMysqlIdentifier($company["ticket_table_name"]);
    $ticketDashboardFilters = [
        "search" => "",
        "branch" => $fixedBranch !== null ? $fixedBranch : ($filters["branch"] ?? ""),
        "dealer" => $filters["dealer"] ?? "",
        "ticket_status" => "",
        "page" => 1,
        "per_page" => $rowsPerPageOptions[0] ?? 25,
    ];
    $ticketDashboardRecords = fetchTicketMonitoringRecords($pdo, $ticketTableNameSql, $ticketDashboardFilters);
    $ticketDashboardData = buildTicketDashboardData($ticketDashboardRecords, $ticketStatusOptions);
}

$listQueryParams = buildMonitoringListQueryParams($company["key"], $filters, true, $monitoringSummaryRowsPerPageOptions[0]);
$mitsubishiUrl = buildUrl("index.php", $listQueryParams, [
    "company" => "mitsubishi",
    "saved" => null,
    "page" => 1,
]);
$hyundaiUrl = buildUrl("index.php", $listQueryParams, [
    "company" => "hyundai",
    "saved" => null,
    "page" => 1,
]);
$ticketMonitoringUrl = buildUrl("ticket_monitoring.php", [
    "company" => $company["key"],
    "branch" => $fixedBranch !== null ? $fixedBranch : ($filters["branch"] ?? ""),
    "dealer" => $filters["dealer"] ?? "",
]);
$clearFiltersUrl = buildUrl("index.php", ["company" => $company["key"]]);
$exportUrl = buildUrl("export_excel.php", buildMonitoringListQueryParams($company["key"], $filters, false, $monitoringSummaryRowsPerPageOptions[0]));
$activeFilterBadges = buildActiveFilterBadges($filters);
$savedIdentificationNumber = trim((string) ($_GET["identification_number"] ?? ""));
$savedTitle = "Record Saved";
$savedMessage = $savedIdentificationNumber !== ""
    ? "Record " . $savedIdentificationNumber . " successfully saved to the " . $company["table_name"] . " table."
    : "";
$validationErrorMessage = resolveMonitoringValidationErrorMessage($_GET["error"] ?? null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($company["system_name"]) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="<?= e($company["logo_type"]) ?>" href="<?= e($company["logo_path"]) ?>">
    <link rel="shortcut icon" type="<?= e($company["logo_type"]) ?>" href="<?= e($company["logo_path"]) ?>">
    <script src="assets/js/theme-init.js"></script>
    <link rel="stylesheet" href="<?= e(buildVersionedAssetPath("assets/css/index.css")) ?>">
</head>
<body class="company-<?= e($company["key"]) ?> page-system-monitoring">
<?php require __DIR__ . "/includes/partials/page_header.php"; ?>

<main>
    <?php require __DIR__ . "/includes/partials/dashboard.php"; ?>
    <?php require __DIR__ . "/includes/partials/encoding_form.php"; ?>
    <?php require __DIR__ . "/includes/partials/summary_table.php"; ?>
</main>

<?php if (isset($_GET["saved"])): ?>
    <?php require __DIR__ . "/includes/partials/saved_modal.php"; ?>
<?php endif; ?>

<script src="assets/js/index.js" defer></script>
</body>
</html>
    
