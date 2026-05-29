<?php
require "config.php";
require __DIR__ . "/includes/monitoring_options.php";
require __DIR__ . "/includes/monitoring_helpers.php";
require __DIR__ . "/includes/monitoring_repository.php";

$company = resolveCompanyConfig($_GET["company"] ?? null, $companyConfigs);
ensureMonitoringTable($pdo, $company);
$tableNameSql = quoteMysqlIdentifier($company["table_name"]);

$filterOptions = [
    "branch" => $branchOptions,
    "dealer" => $dealerOptions,
    "department" => $departmentOptions,
    "module" => $moduleOptions,
    "status" => $summaryStatusOptions,
    "per_page" => $monitoringSummaryRowsPerPageOptions,
];

$filters = buildMonitoringFilters($_GET, $company, $filterOptions);
$hasIdNumberFilter = isset($_GET["id_number"]) && trim((string) $_GET["id_number"]) !== "";
$identificationNumberInput = $_GET["identification_number"] ?? $_GET["id_number"] ?? "";
$identificationNumber = normalizeIdentificationNumberFilter($identificationNumberInput);

$record = null;
if ($identificationNumber !== "") {
    $record = fetchMonitoringRecordByIdentificationNumber($pdo, $tableNameSql, $identificationNumber);
    if ($record !== null) {
        $record = enrichMonitoringRecordsWithDataCorrectionActions($pdo, $tableNameSql, [$record])[0] ?? null;
    }
}

$listQueryParams = buildMonitoringListQueryParams($company["key"], $filters, true, $monitoringSummaryRowsPerPageOptions[0]);
$backUrl = buildUrl("index.php", $listQueryParams) . "#summary-section";
$recordPageQueryParams = $listQueryParams;
if ($hasIdNumberFilter && $identificationNumber !== "") {
    $recordPageQueryParams["id_number"] = $identificationNumber;
} elseif ($identificationNumber !== "") {
    $recordPageQueryParams["identification_number"] = $identificationNumber;
}

$mitsubishiUrl = buildUrl("monitoring_record.php", $recordPageQueryParams, [
    "company" => "mitsubishi",
    "page" => 1,
]);
$hyundaiUrl = buildUrl("monitoring_record.php", $recordPageQueryParams, [
    "company" => "hyundai",
    "page" => 1,
]);

$headerKicker = $company["company_name"];
$headerTitle = "Monitoring Record Details";
$showCompanySwitch = true;

$recordLookupMessage = null;
if ($identificationNumber === "") {
    $recordLookupMessage = "ENTER AN ID NUMBER TO VIEW THE FULL RECORD.";
} elseif ($record === null) {
    $recordLookupMessage = "NO RECORD WAS FOUND FOR ID NUMBER " . $identificationNumber . ".";
}

$incidentReportImagePath = trim((string) ($record["incident_report_image_path"] ?? ""));
$incidentReportImageAbsolutePath = $incidentReportImagePath !== ""
    ? getMonitoringStoredFileAbsolutePath($incidentReportImagePath)
    : "";
$incidentReportImageAvailable = $incidentReportImagePath !== "" && is_file($incidentReportImageAbsolutePath);

function formatMonitoringDetailDisplayValue(array $field, array $row): string
{
    $formattedValue = formatSummaryValue($field, $row);
    return trim($formattedValue) !== "" ? $formattedValue : "N/A";
}

function renderMonitoringReadonlyField(string $label, string $value, string $fieldClass = "", bool $isMultiline = false): void
{
    $classes = trim("field " . $fieldClass);
    $valueClasses = "record-readonly" . ($isMultiline ? " multiline" : "");
    ?>
    <div class="<?= e($classes) ?>">
        <label><?= e($label) ?></label>
        <div class="<?= e($valueClasses) ?>"><?= nl2br(e($value)) ?></div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($company["system_name"]) ?> Record Details</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="<?= e($company["logo_type"]) ?>" href="<?= e($company["logo_path"]) ?>">
    <link rel="shortcut icon" type="<?= e($company["logo_type"]) ?>" href="<?= e($company["logo_path"]) ?>">
    <script src="assets/js/theme-init.js"></script>
    <link rel="stylesheet" href="<?= e(buildVersionedAssetPath("assets/css/index.css")) ?>">
</head>
<body class="company-<?= e($company["key"]) ?> page-system-monitoring page-record-details">
<?php require __DIR__ . "/includes/partials/page_header.php"; ?>

<main>
    <section class="card">
        <div class="summary-header">
            <div>
                <h2>Find Record</h2>
                <p class="note">Search using the ID number generated when the incident was encoded.</p>
            </div>
            <a href="<?= e($backUrl) ?>" class="button-link secondary">Back to Summary</a>
        </div>

        <form action="monitoring_record.php" method="GET" class="summary-filter-form">
            <input type="hidden" name="company" value="<?= e($company["key"]) ?>">
            <input type="hidden" name="month" value="<?= e($filters["month"] ?? "") ?>">
            <input type="hidden" name="branch" value="<?= e($filters["branch"] ?? "") ?>">
            <input type="hidden" name="dealer" value="<?= e($filters["dealer"] ?? "") ?>">
            <input type="hidden" name="status" value="<?= e($filters["status"] ?? "") ?>">
            <?php if (!empty($filters["data_correction_only"])): ?>
            <input type="hidden" name="data_correction" value="1">
            <?php endif; ?>
            <?php if (!empty($filters["escalation_only"])): ?>
            <input type="hidden" name="escalation" value="1">
            <?php endif; ?>
            <input type="hidden" name="page" value="<?= e($filters["page"] ?? 1) ?>">

            <div class="summary-filter-grid">
                <div class="field">
                    <label for="record-identification-number">ID Number</label>
                    <input
                        type="text"
                        id="record-identification-number"
                        name="id_number"
                        value="<?= e($identificationNumber) ?>"
                        placeholder="Enter ID number"
                        required
                    >
                </div>
            </div>

            <div class="summary-actions">
                <button type="submit" class="primary">Search Record</button>
            </div>
        </form>
    </section>

    <?php if ($recordLookupMessage !== null): ?>
    <section class="card">
        <div class="form-alert form-alert-error" role="alert">
            <?= e($recordLookupMessage) ?>
        </div>
    </section>
    <?php else: ?>
    <section class="card">
        <div class="summary-header">
            <div>
                <h2>Record Information</h2>
                <p class="note">Full incident details for ID number <strong><?= e($identificationNumber) ?></strong>.</p>
            </div>
            <a href="<?= e($backUrl) ?>" class="button-link secondary">Return to Summary</a>
        </div>

        <div class="record-layout">
            <section class="form-section compact-section">
                <div class="field-grid compact record-top-grid">
                    <?php renderMonitoringReadonlyField("Date", formatMonitoringDetailDisplayValue(["key" => "date_recorded", "format" => "date"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Transaction Date", formatMonitoringDetailDisplayValue(["key" => "transaction_date", "format" => "date"], $record)); ?>
                    <?php renderMonitoringReadonlyField("ID Number", formatMonitoringDetailDisplayValue(["key" => "identification_number", "format" => "text"], $record)); ?>
                </div>
            </section>

            <section class="form-section">
                <div class="field-grid">
                    <?php renderMonitoringReadonlyField("Branch", formatMonitoringDetailDisplayValue(["key" => "branch", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Dealers", formatMonitoringDetailDisplayValue(["key" => "dealer", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Department", formatMonitoringDetailDisplayValue(["key" => "department", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Module", formatMonitoringDetailDisplayValue(["key" => "module", "format" => "text"], $record)); ?>
                </div>
            </section>

            <section class="form-section">
                <div class="field-grid">
                    <?php renderMonitoringReadonlyField("User", formatMonitoringDetailDisplayValue(["key" => "user_name", "format" => "text"], $record), "field-span-2"); ?>
                    <?php renderMonitoringReadonlyField("Client Name", formatMonitoringDetailDisplayValue(["key" => "client_name", "format" => "text"], $record), "field-span-2"); ?>
                    <?php renderMonitoringReadonlyField("Transaction Reference", formatMonitoringDetailDisplayValue(["key" => "invoice_reference", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Payment Reference", formatMonitoringDetailDisplayValue(["key" => "payment_reference", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Amount", formatMonitoringDetailDisplayValue(["key" => "amount", "format" => "amount"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Ticket", formatMonitoringDetailDisplayValue(["key" => "ticket", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Reason", formatMonitoringDetailDisplayValue(["key" => "reason", "format" => "text"], $record), "field-span-2", true); ?>
                    <?php renderMonitoringReadonlyField("System Admin", formatMonitoringDetailDisplayValue(["key" => "system_admin", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Offense", formatMonitoringDetailDisplayValue(["key" => "offense", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Approved By", formatMonitoringDetailDisplayValue(["key" => "approved_by", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Processed By", formatMonitoringDetailDisplayValue(["key" => "processed_by", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Remarks", formatMonitoringDetailDisplayValue(["key" => "remarks", "format" => "text"], $record), "field-span-2", true); ?>
                </div>
            </section>

            <section class="form-section">
                <div class="field-grid">
                    <?php renderMonitoringReadonlyField("Classification", formatMonitoringDetailDisplayValue(["key" => "classification", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Processed Type", formatMonitoringDetailDisplayValue(["key" => "processed_type", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Status", formatMonitoringDetailDisplayValue(["key" => "status", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Alert", formatMonitoringDetailDisplayValue(["key" => "data_correction_alert", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Disciplinary Action", formatMonitoringDetailDisplayValue(["key" => "disciplinary_action", "format" => "text"], $record), "field-span-2"); ?>
                    <?php renderMonitoringReadonlyField("Encoded At", formatMonitoringDetailDisplayValue(["key" => "created_at", "format" => "timestamp"], $record), "field-span-2"); ?>
                </div>
            </section>
        </div>
    </section>

    <section class="card">
        <div class="summary-header">
            <div>
                <h2>Incident Report Image</h2>
            </div>
            <?php if ($incidentReportImageAvailable): ?>
            <a href="<?= e($incidentReportImagePath) ?>" class="button-link secondary" target="_blank" rel="noopener">Open Full Image</a>
            <?php endif; ?>
        </div>

        <?php if ($incidentReportImageAvailable): ?>
        <div class="record-image-panel">
            <img
                src="<?= e($incidentReportImagePath) ?>"
                alt="Incident report image for <?= e($identificationNumber) ?>"
                class="record-image"
            >
        </div>
        <?php elseif ($incidentReportImagePath !== ""): ?>
        <p class="note">An incident report image was saved for this record, but the file is currently unavailable.</p>
        <?php else: ?>
        <p class="note">No incident report image was uploaded for this record.</p>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</main>

<script src="assets/js/index.js" defer></script>
</body>
</html>
