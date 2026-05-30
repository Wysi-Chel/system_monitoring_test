<?php
$paginationPages = buildPaginationPages($pagination["page"], $pagination["total_pages"]);
$summaryAnchor = "#summary-section";
$monitoringActionOptions = getMonitoringActionOptions();
$monitoringDoneStatus = getMonitoringDoneStatus();
$formatCardValue = static function (string $value): string {
    $value = trim($value);
    return $value !== "" ? $value : "N/A";
};
?>
<section class="card" id="summary-section">
    <div class="summary-header">
        <div>
            <h2><?= e($company["system_name"]) ?> Summary</h2>
        </div>
        <a href="<?= e($exportUrl) ?>" class="button-link secondary">Export Filtered Excel</a>
    </div>

    <form action="index.php#summary-section" method="GET" class="summary-filter-form">
        <input type="hidden" name="company" value="<?= e($company["key"]) ?>">
        <?php if (!empty($filters["data_correction_only"])): ?>
        <input type="hidden" name="data_correction" value="1">
        <?php endif; ?>
        <?php if (!empty($filters["escalation_only"])): ?>
        <input type="hidden" name="escalation" value="1">
        <?php endif; ?>

        <div class="summary-filter-grid">
            <div class="field">
                <label for="filter-month">Month</label>
                <input type="month" id="filter-month" name="month" value="<?= e($filters["month"] ?? "") ?>">
            </div>

            <?php if ($showBranchSelector): ?>
            <div class="field">
                <label for="filter-branch">Branch</label>
                <select id="filter-branch" name="branch">
                    <option value="">All branches</option>
                    <?php foreach ($branchOptions as $option): ?>
                    <option value="<?= e($option) ?>"<?= $filters["branch"] === $option ? " selected" : "" ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="field">
                <label for="filter-dealer">Dealers</label>
                <select id="filter-dealer" name="dealer">
                    <option value="">All dealers</option>
                    <?php foreach ($dealerOptions as $option): ?>
                    <option value="<?= e($option) ?>"<?= ($filters["dealer"] ?? "") === $option ? " selected" : "" ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="filter-identification-number">ID Number</label>
                <input
                    type="text"
                    id="filter-identification-number"
                    name="id_number"
                    value="<?= e($filters["identification_number"] ?? "") ?>"
                    placeholder=""
                >
            </div>

            <div class="field">
                <label for="filter-user-name">User</label>
                <input
                    type="text"
                    id="filter-user-name"
                    name="user"
                    value="<?= e($filters["user_name"] ?? "") ?>"
                    placeholder=""
                >
            </div>

            <div class="field">
                <label for="filter-status">Status</label>
                <select id="filter-status" name="status">
                    <option value="">All statuses</option>
                    <?php foreach ($summaryStatusOptions as $option): ?>
                    <option value="<?= e($option) ?>"<?= $filters["status"] === $option ? " selected" : "" ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="summary-toolbar">
            <div class="summary-actions">
                <button type="submit" class="primary">Apply Filters</button>
                <a href="<?= e($clearFiltersUrl . $summaryAnchor) ?>" class="button-link secondary">Clear Filters</a>
            </div>

            <div class="results-meta">
                <strong><?= e($pagination["start_item"]) ?>-<?= e($pagination["end_item"]) ?></strong> of <strong><?= e($totalRecords) ?></strong> records
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

    <?php if ($records === []): ?>
    <div class="summary-card-empty">No records matched the current filters.</div>
    <?php else: ?>
    <div class="summary-card-list">
        <?php foreach ($records as $row): ?>
            <?php
            $formattedDisciplinaryAction = trim((string) formatSummaryValue(
                ["key" => "disciplinary_action", "format" => "text"],
                $row
            ));
            $identificationNumber = trim((string) ($row["identification_number"] ?? ""));
            $recordUrl = $identificationNumber !== ""
                ? buildUrl("monitoring_record.php", $listQueryParams, ["identification_number" => $identificationNumber])
                : "";
            $titleValue = trim((string) formatSummaryValue(["key" => "user_name", "format" => "text"], $row));
            if ($titleValue === "") {
                $titleValue = trim((string) formatSummaryValue(["key" => "client_name", "format" => "text"], $row));
            }
            if ($titleValue === "") {
                $titleValue = $identificationNumber !== "" ? $identificationNumber : "UNASSIGNED";
            }
            $metaParts = array_filter([
                formatDisplayDate((string) ($row["date_recorded"] ?? "")),
                formatDisplayDate((string) ($row["transaction_date"] ?? "")),
                trim((string) formatSummaryValue(["key" => "module", "format" => "text"], $row)),
                trim((string) formatSummaryValue(["key" => "dealer", "format" => "text"], $row)),
                trim((string) formatSummaryValue(["key" => "branch", "format" => "text"], $row)),
            ]);
            $statusTags = splitMultiValueText((string) ($row["status"] ?? ""));
            $processedTypeTags = splitMultiValueText((string) ($row["processed_type"] ?? ""));
            $classificationValue = trim((string) formatSummaryValue(["key" => "classification", "format" => "text"], $row));
            $ticketValue = trim((string) formatSummaryValue(["key" => "ticket", "format" => "text"], $row));
            $offenseValue = $formattedDisciplinaryAction !== ""
                ? $formattedDisciplinaryAction
                : trim((string) formatSummaryValue(["key" => "offense", "format" => "text"], $row));
            $rowActionOptions = [];

            if (canMarkMonitoringRecordDone($row["status"] ?? "")) {
                $rowActionOptions[] = $monitoringDoneStatus;
            }

            if (((int) ($row["data_correction_offense_count"] ?? 0)) >= 3) {
                foreach ($monitoringActionOptions as $option) {
                    if (!in_array($option, $rowActionOptions, true)) {
                        $rowActionOptions[] = $option;
                    }
                }
            }
            ?>
        <article class="summary-card summary-card-monitoring<?= ((int) ($row["data_correction_offense_count"] ?? 0)) >= 3 ? " summary-card-alert" : "" ?>">
            <div class="summary-card-header">
                <div class="summary-card-main">
                    <?php if ($recordUrl !== ""): ?>
                    <a href="<?= e($recordUrl) ?>" class="dashboard-activity-id"><?= e($identificationNumber !== "" ? $identificationNumber : "NO ID") ?></a>
                    <?php else: ?>
                    <span class="dashboard-activity-id"><?= e($identificationNumber !== "" ? $identificationNumber : "NO ID") ?></span>
                    <?php endif; ?>

                    <div class="dashboard-activity-title"><?= e($titleValue) ?></div>
                    <?php if ($metaParts !== []): ?>
                    <div class="dashboard-activity-meta"><?= e(implode(" / ", $metaParts)) ?></div>
                    <?php endif; ?>
                </div>

                <div class="summary-card-action">
                    <?php if ($rowActionOptions !== []): ?>
                    <form action="update_monitoring_action.php" method="POST" class="monitoring-action-form">
                        <input type="hidden" name="company" value="<?= e($company["key"]) ?>">
                        <input type="hidden" name="record_id" value="<?= e($row["id"] ?? "") ?>">
                        <input type="hidden" name="filter_month" value="<?= e($filters["month"] ?? "") ?>">
                        <input type="hidden" name="filter_branch" value="<?= e($filters["branch"] ?? "") ?>">
                        <input type="hidden" name="filter_dealer" value="<?= e($filters["dealer"] ?? "") ?>">
                        <input type="hidden" name="filter_identification_number" value="<?= e($filters["identification_number"] ?? "") ?>">
                        <input type="hidden" name="filter_user_name" value="<?= e($filters["user_name"] ?? "") ?>">
                        <input type="hidden" name="filter_status" value="<?= e($filters["status"] ?? "") ?>">
                        <input type="hidden" name="filter_data_correction" value="<?= !empty($filters["data_correction_only"]) ? "1" : "" ?>">
                        <input type="hidden" name="filter_escalation" value="<?= !empty($filters["escalation_only"]) ? "1" : "" ?>">
                        <input type="hidden" name="filter_page" value="<?= e($pagination["page"]) ?>">
                        <select
                            name="disciplinary_action"
                            class="summary-action-select"
                            aria-label="Choose disciplinary action"
                            title="Choose Action"
                            onchange="if (this.value !== '') { this.form.submit(); }"
                        >
                            <option value="" selected disabled>Action</option>
                            <?php foreach ($rowActionOptions as $option): ?>
                            <option value="<?= e($option) ?>"><?= e($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="summary-card-tags">
                <?php foreach ($statusTags as $statusTag): ?>
                <span class="dashboard-chip"><?= e(uppercaseText($statusTag)) ?></span>
                <?php endforeach; ?>

                <?php foreach ($processedTypeTags as $processedTypeTag): ?>
                <span class="dashboard-chip"><?= e(uppercaseText($processedTypeTag)) ?></span>
                <?php endforeach; ?>

                <?php if ($classificationValue !== ""): ?>
                <span class="dashboard-chip"><?= e(uppercaseText($classificationValue)) ?></span>
                <?php endif; ?>

                <?php if (((int) ($row["data_correction_offense_count"] ?? 0)) > 0): ?>
                <span class="dashboard-chip alert"><?= e((string) ($row["data_correction_offense_count"] ?? 0)) ?> DATA CORRECTION<?= ((int) ($row["data_correction_offense_count"] ?? 0)) > 1 ? "S" : "" ?></span>
                <?php endif; ?>

                <?php if ($ticketValue !== ""): ?>
                <span class="dashboard-chip ticket">TICKET <?= e(uppercaseText($ticketValue)) ?></span>
                <?php endif; ?>
            </div>

            <div class="summary-card-grid">
                <div class="summary-card-field summary-card-field-department">
                    <div class="summary-card-label">Department</div>
                    <div class="summary-card-value"><?= e($formatCardValue((string) formatSummaryValue(["key" => "department", "format" => "text"], $row))) ?></div>
                </div>
                <div class="summary-card-field summary-card-field-client">
                    <div class="summary-card-label">Client Name</div>
                    <div class="summary-card-value"><?= e($formatCardValue((string) formatSummaryValue(["key" => "client_name", "format" => "text"], $row))) ?></div>
                </div>
                <div class="summary-card-field summary-card-field-reference">
                    <div class="summary-card-label">Transaction Reference</div>
                    <div class="summary-card-value"><?= e($formatCardValue((string) formatSummaryValue(["key" => "invoice_reference", "format" => "text"], $row))) ?></div>
                </div>
                <div class="summary-card-field summary-card-field-approved">
                    <div class="summary-card-label">Approved By</div>
                    <div class="summary-card-value"><?= e($formatCardValue((string) formatSummaryValue(["key" => "approved_by", "format" => "text"], $row))) ?></div>
                </div>
                <div class="summary-card-field summary-card-field-processed">
                    <div class="summary-card-label">Processed By</div>
                    <div class="summary-card-value"><?= e($formatCardValue((string) formatSummaryValue(["key" => "processed_by", "format" => "text"], $row))) ?></div>
                </div>
                <div class="summary-card-field summary-card-field-alert">
                    <div class="summary-card-label">Alert / Action</div>
                    <div class="summary-card-value"><?= e($formatCardValue($offenseValue)) ?></div>
                </div>
                <div class="summary-card-field summary-card-field-reason">
                    <div class="summary-card-label">Reason</div>
                    <div class="summary-card-value summary-card-value-multiline"><?= e($formatCardValue((string) formatSummaryValue(["key" => "reason", "format" => "text"], $row))) ?></div>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($pagination["total_pages"] > 1): ?>
    <nav class="pagination" aria-label="Summary pages">
        <?php if ($pagination["has_previous"]): ?>
        <a href="<?= e(buildUrl("index.php", $listQueryParams, ["page" => $pagination["page"] - 1]) . $summaryAnchor) ?>" class="button-link secondary">Previous</a>
        <?php else: ?>
        <span class="button-link secondary disabled" aria-disabled="true">Previous</span>
        <?php endif; ?>

        <div class="page-numbers">
            <?php foreach ($paginationPages as $pageNumber): ?>
            <a
                href="<?= e(buildUrl("index.php", $listQueryParams, ["page" => $pageNumber]) . $summaryAnchor) ?>"
                class="page-number<?= $pageNumber === $pagination["page"] ? " active" : "" ?>"
                <?= $pageNumber === $pagination["page"] ? 'aria-current="page"' : "" ?>
            ><?= e($pageNumber) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ($pagination["has_next"]): ?>
        <a href="<?= e(buildUrl("index.php", $listQueryParams, ["page" => $pagination["page"] + 1]) . $summaryAnchor) ?>" class="button-link secondary">Next</a>
        <?php else: ?>
        <span class="button-link secondary disabled" aria-disabled="true">Next</span>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</section>
