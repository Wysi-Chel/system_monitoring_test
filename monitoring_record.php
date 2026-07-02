<?php
require __DIR__ . "/includes/auth.php";
requireMonitoringAuthentication();
require "config.php";
require __DIR__ . "/includes/monitoring_options.php";
require __DIR__ . "/includes/monitoring_helpers.php";
require __DIR__ . "/includes/monitoring_repository.php";

$company = resolveCompanyConfig($_GET["company"] ?? null, $companyConfigs);
$fixedBranch = $company["fixed_branch"] ?? null;
$showBranchSelector = $fixedBranch === null;
ensureMonitoringTable($pdo, $company);
$tableNameSql = quoteMysqlIdentifier($company["table_name"]);
$userNameSuggestions = fetchMonitoringUserNameSuggestions($pdo, $tableNameSql);

$filterOptions = [
    "branch" => $branchOptions,
    "dealer" => $dealerOptions,
    "department" => $departmentOptions,
    "module" => $moduleOptions,
    "status" => $summaryStatusOptions,
    "action" => getMonitoringActionOptions(),
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
$isEditMode = $record !== null && ($_GET["edit"] ?? "") === "1";
$validationErrorMessage = resolveMonitoringValidationErrorMessage($_GET["error"] ?? null);
$today = (new DateTimeImmutable("now", new DateTimeZone("Asia/Manila")))->format("Y-m-d");
$nextMonitoringIdentificationNumber = $identificationNumber;

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
    $userTransactionRecords = enrichMonitoringRecordsWithDataCorrectionActions($pdo, $tableNameSql, $userTransactionRecords);
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
$recordEditUrl = $record !== null
    ? buildUrl("monitoring_record.php", $recordPageQueryParams, ["edit" => 1])
    : "";
$recordViewUrl = $record !== null
    ? buildUrl("monitoring_record.php", $recordPageQueryParams, ["edit" => null])
    : "";
$isResolvedIncidentReport = $record !== null && hasResolvedMonitoringIncidentReportStatus($record);
$savedTitle = "Record Updated";
$savedMessage = $identificationNumber !== ""
    ? "Record " . $identificationNumber . " successfully updated."
    : "Record successfully updated.";

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
        </div>

        <form action="monitoring_record.php" method="GET" class="summary-filter-form">
            <input type="hidden" name="company" value="<?= e($company["key"]) ?>">
            <input type="hidden" name="month" value="<?= e($filters["month"] ?? "") ?>">
            <input type="hidden" name="day" value="<?= e($filters["day"] ?? "") ?>">
            <input type="hidden" name="branch" value="<?= e($filters["branch"] ?? "") ?>">
            <input type="hidden" name="dealer" value="<?= e($filters["dealer"] ?? "") ?>">
            <input type="hidden" name="user" value="<?= e($filters["user_name"] ?? "") ?>">
            <input type="hidden" name="status" value="<?= e($filters["status"] ?? "") ?>">
            <input type="hidden" name="action" value="<?= e($filters["disciplinary_action"] ?? "") ?>">
            <?php if (!empty($filters["data_correction_only"])): ?>
            <input type="hidden" name="data_correction" value="1">
            <?php endif; ?>
            <?php if (!empty($filters["escalation_only"])): ?>
            <input type="hidden" name="escalation" value="1">
            <?php endif; ?>
            <input type="hidden" name="page" value="<?= e($filters["page"] ?? 1) ?>">

            <div class="summary-filter-grid">
                <div class="field">
                    <label for="record-identification-number">ID number</label>
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
                <button type="submit" class="primary icon-button" aria-label="Search record" title="Search record">
                    <?= iconSvg("search") ?>
                    <span class="sr-only">Search record</span>
                </button>
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
            <div class="summary-actions">
                <?php if ($isEditMode): ?>
                <a href="<?= e($recordViewUrl) ?>" class="button-link secondary icon-button" aria-label="Cancel edit" title="Cancel edit">
                    <?= iconSvg("arrow-left") ?>
                    <span class="sr-only">Cancel edit</span>
                </a>
                <?php else: ?>
                <a href="<?= e($recordEditUrl) ?>" class="button-link secondary icon-button" aria-label="Edit record" title="Edit record">
                    <?= iconSvg("edit") ?>
                    <span class="sr-only">Edit record</span>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$isEditMode && $isResolvedIncidentReport): ?>
        <div class="form-alert form-alert-success record-status-notice" role="status">
            <?= e(uppercaseText("Incident report resolved")) ?>
        </div>
        <?php endif; ?>

        <?php if ($isEditMode): ?>
            <?php
            $editingRecord = $record;
            require __DIR__ . "/includes/partials/encoding_form.php";
            ?>
        <?php else: ?>
        <div class="record-layout">
            <section class="form-section compact-section">
                <div class="field-grid compact record-top-grid">
                    <?php renderMonitoringReadonlyField("Date", formatMonitoringDetailDisplayValue(["key" => "date_recorded", "format" => "date"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Transaction date", formatMonitoringDetailDisplayValue(["key" => "transaction_date", "format" => "date"], $record)); ?>
                    <?php renderMonitoringReadonlyField("ID number", formatMonitoringDetailDisplayValue(["key" => "identification_number", "format" => "text"], $record)); ?>
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
                    <?php renderMonitoringReadonlyField("Client name", formatMonitoringDetailDisplayValue(["key" => "client_name", "format" => "text"], $record), "field-span-2"); ?>
                    <?php renderMonitoringReadonlyField("Transaction reference", formatMonitoringDetailDisplayValue(["key" => "invoice_reference", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Payment reference", formatMonitoringDetailDisplayValue(["key" => "payment_reference", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Amount", formatMonitoringDetailDisplayValue(["key" => "amount", "format" => "amount"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Ticket", formatMonitoringDetailDisplayValue(["key" => "ticket", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Reason", formatMonitoringDetailDisplayValue(["key" => "reason", "format" => "text"], $record), "field-span-2", true); ?>
                    <?php renderMonitoringReadonlyField("System admin", formatMonitoringDetailDisplayValue(["key" => "system_admin", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Offense", formatMonitoringDetailDisplayValue(["key" => "offense", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Approved by", formatMonitoringDetailDisplayValue(["key" => "approved_by", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Processed by", formatMonitoringDetailDisplayValue(["key" => "processed_by", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Remarks", formatMonitoringDetailDisplayValue(["key" => "remarks", "format" => "text"], $record), "field-span-2", true); ?>
                </div>
            </section>

            <section class="form-section">
                <div class="field-grid">
                    <?php renderMonitoringReadonlyField("Classification", formatMonitoringDetailDisplayValue(["key" => "classification", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Processed type", formatMonitoringDetailDisplayValue(["key" => "processed_type", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Status", formatMonitoringDetailDisplayValue(["key" => "status", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Alert", formatMonitoringDetailDisplayValue(["key" => "data_correction_alert", "format" => "text"], $record)); ?>
                    <?php renderMonitoringReadonlyField("Disciplinary action", formatMonitoringDetailDisplayValue(["key" => "disciplinary_action", "format" => "text"], $record), "field-span-2"); ?>
                    <?php renderMonitoringReadonlyField("Encoded at", formatMonitoringDetailDisplayValue(["key" => "created_at", "format" => "timestamp"], $record), "field-span-2"); ?>
                </div>
            </section>
        </div>
        <?php endif; ?>
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
            <a href="<?= e($userTransactionSummaryUrl) ?>" class="button-link secondary icon-button" aria-label="Open user summary" title="Open user summary">
                <?= iconSvg("search") ?>
                <span class="sr-only">Open user summary</span>
            </a>
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
                        <th>User Error Count</th>
                        <th>Alert</th>
                        <th>Memo Status</th>
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
                        $historyUserErrorCount = (int) ($historyRow["data_correction_offense_count"] ?? 0);
                        $historyAlertValue = trim((string) ($historyRow["data_correction_alert"] ?? ""));
                        $historyMemoStatus = formatMonitoringMemoActionStatusDisplayValue($historyRow);
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
                        <td><?= e($historyUserErrorCount > 0 ? (string) $historyUserErrorCount : "N/A") ?></td>
                        <td><?= e($historyAlertValue !== "" ? uppercaseText($historyAlertValue) : "N/A") ?></td>
                        <td><?= e($historyMemoStatus !== "" ? uppercaseText($historyMemoStatus) : "N/A") ?></td>
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
            <a href="<?= e($incidentReportImagePath) ?>" class="button-link secondary icon-button" target="_blank" rel="noopener" aria-label="Open full image" title="Open full image">
                <?= iconSvg("external-link") ?>
                <span class="sr-only">Open full image</span>
            </a>
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

<?php if (isset($_GET["updated"])): ?>
    <?php require __DIR__ . "/includes/partials/saved_modal.php"; ?>
<?php endif; ?>

<script src="assets/js/index.js" defer></script>
</body>
</html>
