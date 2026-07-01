<?php
require __DIR__ . "/includes/auth.php";
requireMonitoringAuthentication();
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
    "action" => getMonitoringActionOptions(),
    "per_page" => $monitoringSummaryRowsPerPageOptions,
];
$filters = buildMonitoringFilters($_GET, $company, $filterOptions);
$records = fetchMonitoringRecords($pdo, $tableNameSql, $filters, null, 0, "ASC");
$records = enrichMonitoringRecordsWithDataCorrectionActions($pdo, $tableNameSql, $records);

if (!empty($filters["escalation_only"])) {
    $records = filterEscalationCandidateMonitoringRecords($records);
}
$records = filterMonitoringRecordsByDisciplinaryAction($records, $filters);

$activeFilterBadges = buildActiveFilterBadges($filters, $company["fixed_branch"] ?? null);
$activeFilterText = $activeFilterBadges !== []
    ? uppercaseText(implode(" | ", $activeFilterBadges))
    : "ALL RECORDS";
$backUrl = buildUrl("index.php", buildMonitoringListQueryParams($company["key"], $filters, false, $monitoringSummaryRowsPerPageOptions[0])) . "#summary-section";
$printedAt = (new DateTimeImmutable("now", new DateTimeZone("Asia/Manila")))->format("n/j/Y g:i A");
$hiddenPrintColumnKeys = ["payment_reference", "amount", "system_admin"];
$printSummaryColumns = array_values(array_filter(
    $summaryColumns,
    static fn(array $column): bool => !in_array((string) ($column["key"] ?? ""), $hiddenPrintColumnKeys, true)
));

function formatPrintSummaryValue(array $column, array $row): string
{
    if (($column["format"] ?? "text") === "action_control") {
        $memoStatusValue = formatMonitoringMemoActionStatusDisplayValue($row);
        return $memoStatusValue !== ""
            ? uppercaseText($memoStatusValue)
            : uppercaseText(trim((string) ($row["disciplinary_action"] ?? "")));
    }

    return uppercaseText(formatSummaryValue($column, $row));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($company["system_name"]) ?> Printable Summary</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="<?= e($company["logo_type"]) ?>" href="<?= e($company["logo_path"]) ?>">
    <link rel="shortcut icon" type="<?= e($company["logo_type"]) ?>" href="<?= e($company["logo_path"]) ?>">
    <script src="assets/js/theme-init.js"></script>
    <link rel="stylesheet" href="<?= e(buildVersionedAssetPath("assets/css/index.css")) ?>">
</head>
<body class="company-<?= e($company["key"]) ?> page-system-monitoring page-print-summary">
<main>
    <section class="print-summary-card">
        <div class="worksheet-toolbar no-print">
            <div class="summary-actions no-print">
                <button type="button" class="primary icon-button" data-print-button aria-label="Print" title="Print">
                    <?= iconSvg("printer") ?>
                    <span class="sr-only">Print</span>
                </button>
                <a href="<?= e($backUrl) ?>" class="button-link secondary icon-button" aria-label="Back to summary" title="Back to summary">
                    <?= iconSvg("arrow-left") ?>
                    <span class="sr-only">Back to summary</span>
                </a>
            </div>
        </div>

        <div class="worksheet-heading">
            <h1><?= e(uppercaseText($company["system_name"])) ?> SUMMARY</h1>
            <div class="worksheet-meta">
                <span>FILTER: <?= e($activeFilterText) ?></span>
                <span>RECORDS: <?= e(number_format(count($records))) ?></span>
                <span>PRINTED: <?= e(uppercaseText($printedAt)) ?></span>
            </div>
        </div>

        <?php if ($records === []): ?>
        <div class="worksheet-empty">NO RECORDS MATCHED THE CURRENT FILTERS.</div>
        <?php else: ?>
        <div class="table-wrapper print-summary-table-wrapper">
            <table class="compact-summary-table print-summary-table">
                <thead>
                    <tr>
                        <?php foreach ($printSummaryColumns as $column): ?>
                        <th><?= e(uppercaseText((string) ($column["label"] ?? ""))) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $row): ?>
                    <tr>
                        <?php foreach ($printSummaryColumns as $column): ?>
                        <td><?= e(formatPrintSummaryValue($column, $row)) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</main>

<script>
(function () {
    document.documentElement.classList.remove("dark-theme");
    document.body.classList.add("print-worksheet-ready");

    var printButton = document.querySelector("[data-print-button]");
    var printWorksheet = function () {
        window.print();
    };

    if (printButton) {
        printButton.addEventListener("click", printWorksheet);
    }

    window.addEventListener("load", function () {
        window.setTimeout(printWorksheet, 400);
    });
}());
</script>
</body>
</html>
