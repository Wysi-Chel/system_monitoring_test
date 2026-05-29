<?php
require "config.php";
require __DIR__ . "/includes/monitoring_options.php";
require __DIR__ . "/includes/monitoring_helpers.php";
require __DIR__ . "/includes/monitoring_repository.php";

$today = (new DateTimeImmutable("now", new DateTimeZone("Asia/Manila")))->format("Y-m-d");
$company = resolveCompanyConfig($_GET["company"] ?? null, $companyConfigs);
if (!companySupportsTicketMonitoring($company)) {
    header("Location: index.php?company=" . urlencode($company["key"]));
    exit;
}
$fixedBranch = $company["fixed_branch"] ?? null;
$showBranchSelector = $fixedBranch === null;
ensureTicketMonitoringTable($pdo, $company);

$filterOptions = [
    "branch" => $branchOptions,
    "dealer" => $dealerOptions,
    "status" => $ticketStatusOptions,
    "per_page" => $rowsPerPageOptions,
];

$ticketTableNameSql = quoteMysqlIdentifier($company["ticket_table_name"]);
$filters = buildTicketMonitoringFilters($_GET, $company, $filterOptions);
$totalRecords = countTicketMonitoringRecords($pdo, $ticketTableNameSql, $filters);
$pagination = buildPaginationState($filters["page"], $filters["per_page"], $totalRecords);
$filters["page"] = $pagination["page"];
$records = fetchTicketMonitoringRecords($pdo, $ticketTableNameSql, $filters, $pagination["limit"], $pagination["offset"]);
$ticketQueryParams = buildMonitoringListQueryParams($company["key"], $filters);
$mitsubishiUrl = buildUrl("ticket_monitoring.php", $ticketQueryParams, [
    "company" => "mitsubishi",
    "page" => 1,
]);
$hyundaiUrl = buildUrl("ticket_monitoring.php", $ticketQueryParams, [
    "company" => "hyundai",
    "page" => 1,
]);
$mainPageUrl = buildUrl("index.php", ["company" => $company["key"]]);
$exportUrl = buildUrl("export_ticket_excel.php", buildMonitoringListQueryParams($company["key"], $filters, false));
$clearFiltersUrl = buildUrl("ticket_monitoring.php", ["company" => $company["key"]]);
$activeFilterBadges = buildTicketFilterBadges($filters, $fixedBranch);
$ticketSummaryAnchor = "#ticket-summary";
$headerKicker = $company["company_name"];
$headerTitle = "Ticket Monitoring";
$showCompanySwitch = true;
$paginationPages = buildPaginationPages($pagination["page"], $pagination["total_pages"]);
$savedMessage = isset($_GET["updated"])
    ? "Ticket status successfully updated."
    : "Ticket monitoring record successfully saved to the " . $company["ticket_table_name"] . " table.";
$ticketFormDefaults = [
    "dealer" => "",
    "module" => "",
    "ticket_number" => trim((string) ($_GET["ticket_number"] ?? $_GET["q"] ?? "")),
    "ticket_description" => "",
    "date_created" => $today,
    "created_by" => "",
    "ticket_status" => $ticketStatusOptions[0],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($company["company_name"]) ?> Ticket Monitoring</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="<?= e($company["logo_type"]) ?>" href="<?= e($company["logo_path"]) ?>">
    <link rel="shortcut icon" type="<?= e($company["logo_type"]) ?>" href="<?= e($company["logo_path"]) ?>">
    <script src="assets/js/theme-init.js"></script>
    <link rel="stylesheet" href="<?= e(buildVersionedAssetPath("assets/css/index.css")) ?>">
</head>
<body class="company-<?= e($company["key"]) ?> page-ticket-monitoring">
<?php require __DIR__ . "/includes/partials/page_header.php"; ?>

<main>
    <section class="card">
        <div class="summary-header">
            <div>
                <h2>Encode Ticket Record</h2>
                <!-- <p class="note summary-note">Enter the basic ticket details below. New ticket records are saved with an initial status of <strong>Open</strong>.</p> -->
            </div>
            <a href="<?= e($mainPageUrl) ?>" class="button-link secondary">Back to System Monitoring</a>
        </div>

        <form action="save_ticket_monitoring.php" method="POST" id="ticket-record-form" class="ticket-record-form">
            <input type="hidden" name="company" value="<?= e($company["key"]) ?>">

            <section class="form-section">
                <div class="form-section-title">
                    <h3>Ticket Details</h3>
                </div>

                <div class="field-grid ticket-form-grid">
                    <?php if ($showBranchSelector): ?>
                    <div class="field">
                        <label for="ticket-form-branch">Branch</label>
                        <select id="ticket-form-branch" name="branch" required>
                            <option value="">Select branch</option>
                            <?php foreach ($branchOptions as $option): ?>
                            <option value="<?= e($option) ?>"><?= e($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                    <div class="field">
                        <label for="ticket-form-branch-display">Branch</label>
                        <input type="text" id="ticket-form-branch-display" value="<?= e($fixedBranch) ?>" readonly>
                        <input type="hidden" name="branch" value="<?= e($fixedBranch) ?>">
                    </div>
                    <?php endif; ?>

                    <div class="field">
                        <label for="ticket-form-dealer">Dealers</label>
                        <select id="ticket-form-dealer" name="dealer" required>
                            <option value="">Select dealer</option>
                            <?php foreach ($dealerOptions as $option): ?>
                            <option value="<?= e($option) ?>"<?= $ticketFormDefaults["dealer"] === $option ? " selected" : "" ?>><?= e($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="ticket-number">Ticket Number</label>
                        <input type="text" id="ticket-number" name="ticket_number" value="<?= e($ticketFormDefaults["ticket_number"]) ?>" required>
                    </div>

                    <div class="field">
                        <label for="ticket-module">Module</label>
                        <select id="ticket-module" name="module" required>
                            <option value="">Select module</option>
                            <?php foreach ($ticketModuleOptions as $option): ?>
                            <option value="<?= e($option) ?>"<?= $ticketFormDefaults["module"] === $option ? " selected" : "" ?>><?= e($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="ticket-date-created">Date Created</label>
                        <input type="date" id="ticket-date-created" name="date_created" value="<?= e($ticketFormDefaults["date_created"]) ?>" required>
                    </div>

                    <div class="field">
                        <label for="ticket-created-by">Created by</label>
                        <input type="text" id="ticket-created-by" name="created_by" value="<?= e($ticketFormDefaults["created_by"]) ?>" required>
                    </div>

                    <div class="field field-span-2 ticket-description-field">
                        <label for="ticket-description">Description of the Ticket</label>
                        <textarea id="ticket-description" name="ticket_description" required><?= e($ticketFormDefaults["ticket_description"]) ?></textarea>
                    </div>
                </div>
            </section>

            <div class="buttons ticket-form-actions">
                <button type="submit" class="primary">Save Ticket Record</button>
                <button type="reset" class="secondary">Clear Form</button>
            </div>
        </form>
    </section>

    <section class="card" id="ticket-summary">
        <div class="summary-header">
            <div>
                <h2>Ticket Monitoring Summary</h2>
            </div>
            <a href="<?= e($exportUrl) ?>" class="button-link secondary">Export Filtered Excel</a>
        </div>

        <form action="ticket_monitoring.php#ticket-summary" method="GET" class="summary-filter-form">
            <input type="hidden" name="company" value="<?= e($company["key"]) ?>">

            <div class="summary-filter-grid">
                <div class="field">
                    <label for="ticket-search">Ticket Search</label>
                    <input type="search" id="ticket-search" name="q" value="<?= e($filters["search"]) ?>" placeholder="Enter ticket number, module, or description">
                </div>

                <?php if ($showBranchSelector): ?>
                <div class="field">
                    <label for="ticket-branch">Branch</label>
                    <select id="ticket-branch" name="branch">
                        <option value="">All branches</option>
                        <?php foreach ($branchOptions as $option): ?>
                        <option value="<?= e($option) ?>"<?= $filters["branch"] === $option ? " selected" : "" ?>><?= e($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="field">
                    <label for="ticket-dealer">Dealers</label>
                    <select id="ticket-dealer" name="dealer">
                        <option value="">All dealers</option>
                        <?php foreach ($dealerOptions as $option): ?>
                        <option value="<?= e($option) ?>"<?= ($filters["dealer"] ?? "") === $option ? " selected" : "" ?>><?= e($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="ticket-status">Status</label>
                    <select id="ticket-status" name="status">
                        <option value="">All statuses</option>
                        <?php foreach ($ticketStatusOptions as $option): ?>
                        <option value="<?= e($option) ?>"<?= $filters["ticket_status"] === $option ? " selected" : "" ?>><?= e($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

              
            </div>

            <div class="summary-toolbar">
                <div class="summary-actions">
                    <button type="submit" class="primary">Apply Filters</button>
                    <a href="<?= e($clearFiltersUrl . $ticketSummaryAnchor) ?>" class="button-link secondary">Clear Filters</a>
                </div>

                <div class="results-meta">
                    <strong><?= e($pagination["start_item"]) ?>-<?= e($pagination["end_item"]) ?></strong> of <strong><?= e($totalRecords) ?></strong> ticket records
                </div>
                
            </div>
            <br>
        </form>

        <?php if ($activeFilterBadges !== []): ?>
        <div class="active-filters" aria-label="Active filters">
            <?php foreach ($activeFilterBadges as $badge): ?>
            <span class="filter-badge"><?= e($badge) ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="table-wrapper compact-summary-table-wrapper">
            <table class="compact-summary-table">
                <thead>
                    <tr>
                        <?php foreach ($ticketMonitoringColumns as $column): ?>
                        <th class="<?= $column["key"] === "ticket_status" ? "status-column-header" : "" ?>"><?= e($column["label"]) ?></th>
                        <?php endforeach; ?>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($records === []): ?>
                        <tr>
                            <td colspan="<?= e(count($ticketMonitoringColumns) + 1) ?>" class="empty-table">No ticket records matched the current filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $row): ?>
                            <tr class="<?= isLockedTicketStatus($row["ticket_status"] ?? "") ? "ticket-row-resolved" : "" ?>">
                                <?php foreach ($ticketMonitoringColumns as $column): ?>
                                <?php if ($column["key"] === "ticket_status"): ?>
                                <?php
                                    $statusValue = formatSummaryValue($column, $row);
                                    $statusClass = preg_replace('/[^a-z0-9]+/i', '-', strtolower($statusValue)) ?: 'unknown';
                                ?>
                                <td class="status-column-cell">
                                    <span class="status-pill status-pill-<?= e($statusClass) ?>"><?= e($statusValue) ?></span>
                                </td>
                                <?php else: ?>
                                <td><?= e(formatSummaryValue($column, $row)) ?></td>
                                <?php endif; ?>
                                <?php endforeach; ?>
                                <td class="table-action-cell">
                                    <?php if (isLockedTicketStatus($row["ticket_status"] ?? "")): ?>
                                        
                                    <?php else: ?>
                                        <form action="update_ticket_status.php" method="POST" class="ticket-status-form">
                                            <input type="hidden" name="company" value="<?= e($company["key"]) ?>">
                                            <input type="hidden" name="ticket_id" value="<?= e($row["id"] ?? "") ?>">
                                            <input type="hidden" name="filter_search" value="<?= e($filters["search"]) ?>">
                                            <input type="hidden" name="filter_branch" value="<?= e($filters["branch"]) ?>">
                                            <input type="hidden" name="filter_dealer" value="<?= e($filters["dealer"] ?? "") ?>">
                                            <input type="hidden" name="filter_status" value="<?= e($filters["ticket_status"]) ?>">
                                            <input type="hidden" name="filter_per_page" value="<?= e($filters["per_page"]) ?>">
                                            <input type="hidden" name="filter_page" value="<?= e($pagination["page"]) ?>">
                                            <select
                                                name="new_ticket_status"
                                                class="ticket-status-select"
                                                aria-label="Edit ticket status"
                                                title="Edit Status"
                                                onchange="if (this.value !== '') { this.form.submit(); }"
                                            >
                                                <option value="" selected>&#9998;</option>
                                                <?php foreach ($ticketStatusOptions as $option): ?>
                                                    <?php if (($row["ticket_status"] ?? "") === $option) { continue; } ?>
                                                <option value="<?= e($option) ?>"><?= e($option) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pagination["total_pages"] > 1): ?>
        <nav class="pagination" aria-label="Ticket monitoring pages">
            <?php if ($pagination["has_previous"]): ?>
            <a href="<?= e(buildUrl("ticket_monitoring.php", $ticketQueryParams, ["page" => $pagination["page"] - 1]) . $ticketSummaryAnchor) ?>" class="button-link secondary">Previous</a>
            <?php else: ?>
            <span class="button-link secondary disabled" aria-disabled="true">Previous</span>
            <?php endif; ?>

            <div class="page-numbers">
                <?php foreach ($paginationPages as $pageNumber): ?>
                <a
                    href="<?= e(buildUrl("ticket_monitoring.php", $ticketQueryParams, ["page" => $pageNumber]) . $ticketSummaryAnchor) ?>"
                    class="page-number<?= $pageNumber === $pagination["page"] ? " active" : "" ?>"
                    <?= $pageNumber === $pagination["page"] ? 'aria-current="page"' : "" ?>
                ><?= e($pageNumber) ?></a>
                <?php endforeach; ?>
            </div>

            <?php if ($pagination["has_next"]): ?>
            <a href="<?= e(buildUrl("ticket_monitoring.php", $ticketQueryParams, ["page" => $pagination["page"] + 1]) . $ticketSummaryAnchor) ?>" class="button-link secondary">Next</a>
            <?php else: ?>
            <span class="button-link secondary disabled" aria-disabled="true">Next</span>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </section>
</main>

<?php if (isset($_GET["saved"])): ?>
    <?php require __DIR__ . "/includes/partials/saved_modal.php"; ?>
<?php endif; ?>

<script src="assets/js/index.js" defer></script>
</body>
</html>
