<?php $paginationPages = buildPaginationPages($pagination["page"], $pagination["total_pages"]); ?>
<?php $summaryAnchor = "#summary-section"; ?>
<?php $monitoringActionOptions = getMonitoringActionOptions(); ?>
<?php $monitoringDoneStatus = getMonitoringDoneStatus(); ?>
<section class="card" id="summary-section">
    <div class="summary-header">
        <div>
            <h2><?= e($company["system_name"]) ?> Summary</h2>
        </div>
        <a href="<?= e($exportUrl) ?>" class="button-link secondary">Export Filtered Excel</a>
    </div>

    <form action="index.php#summary-section" method="GET" class="summary-filter-form">
        <input type="hidden" name="company" value="<?= e($company["key"]) ?>">
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

    <div class="table-wrapper">
        <table class="monitoring-summary-table compact-summary-table">
            <thead>
                <tr>
                    <?php foreach ($summaryColumns as $column): ?>
                    <th><?= e($column["label"]) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($records === []): ?>
                    <tr>
                        <td colspan="<?= e(count($summaryColumns)) ?>" class="empty-table">No records matched the current filters.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($records as $row): ?>
                        <?php
                            $formattedDisciplinaryAction = trim((string) formatSummaryValue(
                                ["key" => "disciplinary_action", "format" => "text"],
                                $row
                            ));
                        ?>
                        <tr class="<?= ((int) ($row["data_correction_offense_count"] ?? 0)) >= 3 ? "summary-row-alert" : "" ?>">
                            <?php foreach ($summaryColumns as $column): ?>
                            <?php if (($column["format"] ?? "text") === "action_control"): ?>
                            <?php
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
                            <td class="summary-discipline-cell summary-action-cell">
                                <div class="summary-action-content">
                                <?php if ($rowActionOptions !== []): ?>
                                <form action="update_monitoring_action.php" method="POST" class="monitoring-action-form">
                                    <input type="hidden" name="company" value="<?= e($company["key"]) ?>">
                                    <input type="hidden" name="record_id" value="<?= e($row["id"] ?? "") ?>">
                                    <input type="hidden" name="filter_month" value="<?= e($filters["month"] ?? "") ?>">
                                    <input type="hidden" name="filter_branch" value="<?= e($filters["branch"] ?? "") ?>">
                                    <input type="hidden" name="filter_dealer" value="<?= e($filters["dealer"] ?? "") ?>">
                                    <input type="hidden" name="filter_identification_number" value="<?= e($filters["identification_number"] ?? "") ?>">
                                    <input type="hidden" name="filter_status" value="<?= e($filters["status"] ?? "") ?>">
                                    <input type="hidden" name="filter_escalation" value="<?= !empty($filters["escalation_only"]) ? "1" : "" ?>">
                                    <input type="hidden" name="filter_page" value="<?= e($pagination["page"]) ?>">
                                    <select
                                        name="disciplinary_action"
                                        class="summary-action-select"
                                        aria-label="Choose disciplinary action"
                                        title="Choose Action"
                                        onchange="if (this.value !== '') { this.form.submit(); }"
                                    >
                                        <option value="" selected>&#9998;</option>
                                        <?php foreach ($rowActionOptions as $option): ?>
                                        <option value="<?= e($option) ?>"><?= e($option) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                                <?php else: ?>
                                <span class="summary-action-placeholder" aria-hidden="true"></span>
                                <?php endif; ?>
                                </div>
                            </td>
                            <?php elseif (($column["format"] ?? "text") === "record_link"): ?>
                            <?php
                                $identificationNumber = trim((string) ($row["identification_number"] ?? ""));
                                $recordUrl = $identificationNumber !== ""
                                    ? buildUrl("monitoring_record.php", $listQueryParams, ["identification_number" => $identificationNumber])
                                    : "";
                            ?>
                            <td>
                                <?php if ($recordUrl !== ""): ?>
                                <a href="<?= e($recordUrl) ?>" class="record-link"><?= e(formatSummaryValue($column, $row)) ?></a>
                                <?php else: ?>
                                <?= e(formatSummaryValue($column, $row)) ?>
                                <?php endif; ?>
                            </td>
                            <?php else: ?>
                            <?php
                                $cellValue = formatSummaryValue($column, $row);
                                $cellClass = in_array($column["key"], ["data_correction_alert", "disciplinary_action"], true)
                                    ? "summary-discipline-cell"
                                    : "";

                                if ($column["key"] === "offense" && $formattedDisciplinaryAction !== "") {
                                    $cellValue = $formattedDisciplinaryAction;
                                    $cellClass = trim($cellClass . " summary-discipline-cell");
                                }
                            ?>
                            <td class="<?= e($cellClass) ?>">
                                <?= e($cellValue) ?>
                            </td>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

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
