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

$recordUserName = trim((string) ($record["user_name"] ?? ""));
$userTransactionRecords = [];
$userTransactionSummaryUrl = "";
if ($record !== null && $recordUserName !== "") {
    $userTransactionRecords = fetchMonitoringRecordsByUserName($pdo, $tableNameSql, $recordUserName);
    $userTransactionSummaryUrl = buildUrl("index.php", [
        "company" => $company["key"],
        "user" => $recordUserName,
    ]) . "#summary-section";
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
            <input type="hidden" name="user" value="<?= e($filters["user_name"] ?? "") ?>">
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
                <h2>User Transaction History</h2>
                <?php if ($recordUserName !== ""): ?>
                <p class="note">
                    Showing <strong><?= e((string) count($userTransactionRecords)) ?></strong> transaction<?= count($userTransactionRecords) === 1 ? "" : "s" ?>
                    recorded for user <strong><?= e(uppercaseText($recordUserName)) ?></strong>.
                </p>
                <?php else: ?>
                <p class="note">This record has no user name, so related transactions cannot be matched yet.</p>
                <?php endif; ?>
            </div>
            <?php if ($userTransactionSummaryUrl !== ""): ?>
            <a href="<?= e($userTransactionSummaryUrl) ?>" class="button-link secondary">Open User Summary</a>
            <?php endif; ?>
        </div>

        <?php if ($recordUserName === ""): ?>
        <p class="note">Add a user name to this record if you want it to appear in transaction history lookups.</p>
        <?php elseif ($userTransactionRecords === []): ?>
        <div class="summary-card-empty">No transactions were found for this user.</div>
        <?php else: ?>
        <div class="table-wrapper compact-summary-table-wrapper">
            <table class="compact-summary-table record-history-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Transaction Date</th>
                        <th>ID Number</th>
                        <th>Client Name</th>
                        <th>Transaction Reference</th>
                        <th>Payment Reference</th>
                        <th>Amount</th>
                        <th>Processed Type</th>
                        <th>Status</th>
                        <th>View</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($userTransactionRecords as $historyRow): ?>
                        <?php
                        $historyRecordId = (int) ($historyRow["id"] ?? 0);
                        $historyIdentificationNumber = trim((string) ($historyRow["identification_number"] ?? ""));
                        $historyRecordUrl = $historyIdentificationNumber !== ""
                            ? buildUrl("monitoring_record.php", $listQueryParams, [
                                "identification_number" => $historyIdentificationNumber,
                                "id_number" => null,
                            ])
                            : "";
                        $isCurrentRecord = $historyRecordId === (int) ($record["id"] ?? 0);
                        ?>
                    <tr<?= $isCurrentRecord ? ' class="record-history-current-row"' : "" ?>>
                        <td><?= e(formatMonitoringDetailDisplayValue(["key" => "date_recorded", "format" => "date"], $historyRow)) ?></td>
                        <td><?= e(formatMonitoringDetailDisplayValue(["key" => "transaction_date", "format" => "date"], $historyRow)) ?></td>
                        <td>
                            <?php if ($historyRecordUrl !== ""): ?>
                            <a href="<?= e($historyRecordUrl) ?>" class="record-link"><?= e($historyIdentificationNumber) ?></a>
                            <?php else: ?>
                            <?= e($historyIdentificationNumber !== "" ? $historyIdentificationNumber : "N/A") ?>
                            <?php endif; ?>
                        </td>
                        <td><?= e(formatMonitoringDetailDisplayValue(["key" => "client_name", "format" => "text"], $historyRow)) ?></td>
                        <td><?= e(formatMonitoringDetailDisplayValue(["key" => "invoice_reference", "format" => "text"], $historyRow)) ?></td>
                        <td><?= e(formatMonitoringDetailDisplayValue(["key" => "payment_reference", "format" => "text"], $historyRow)) ?></td>
                        <td><?= e(formatMonitoringDetailDisplayValue(["key" => "amount", "format" => "amount"], $historyRow)) ?></td>
                        <td><?= e(formatMonitoringDetailDisplayValue(["key" => "processed_type", "format" => "text"], $historyRow)) ?></td>
                        <td><?= e(formatMonitoringDetailDisplayValue(["key" => "status", "format" => "text"], $historyRow)) ?></td>
                        <td class="record-history-view-cell">
                            <?php if ($isCurrentRecord): ?>
                            <span class="record-history-current-label">Watching</span>
                            <?php elseif ($historyRecordUrl !== ""): ?>
                            <a href="<?= e($historyRecordUrl) ?>" class="record-link">Open</a>
                            <?php else: ?>
                            <span class="note">Unavailable</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
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
