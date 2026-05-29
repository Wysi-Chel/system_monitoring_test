<?php
$dashboardMetrics = $dashboardData["metrics"] ?? [];
$recentDashboardRecords = $dashboardData["recent_records"] ?? [];
$dashboardEscalationUrl = buildUrl("index.php", $listQueryParams, [
    "escalation" => 1,
    "page" => 1,
]) . "#summary-section";

$renderDashboardBreakdown = static function (array $items, string $emptyMessage): void {
    if ($items === []) {
        echo '<p class="dashboard-empty-state">' . e($emptyMessage) . '</p>';
        return;
    }

    echo '<div class="dashboard-breakdown-list">';
    foreach ($items as $item) {
        echo '<div class="dashboard-breakdown-row">';
        echo '<div class="dashboard-breakdown-head">';
        echo '<span class="dashboard-breakdown-label">' . e($item["label"]) . '</span>';
        echo '<span class="dashboard-breakdown-value">' . e(number_format((int) $item["count"])) . '</span>';
        echo '</div>';
        echo '<div class="dashboard-breakdown-track" aria-hidden="true">';
        echo '<span class="dashboard-breakdown-fill" style="width: ' . e((string) $item["bar_width"]) . '%;"></span>';
        echo '</div>';
        echo '<div class="dashboard-breakdown-foot">' . e($item["percentage_label"]) . ' of current scope</div>';
        echo '</div>';
    }
    echo '</div>';
};
?>
<section class="card dashboard-shell" id="dashboard-section">
    <div class="dashboard-hero">
        <div class="dashboard-hero-copy">
            <div class="dashboard-kicker">Operational Dashboard</div>
            <h2><?= e($company["company_name"]) ?> Monitoring Overview</h2>
        </div>
    </div>

    <div class="dashboard-scope">
        <div class="dashboard-scope-label">Current Scope</div>
        <?php if ($activeFilterBadges !== []): ?>
        <div class="active-filters dashboard-filter-strip" aria-label="Dashboard scope filters">
            <?php foreach ($activeFilterBadges as $badge): ?>
            <span class="filter-badge"><?= e($badge) ?></span>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <?php endif; ?>
    </div>

    <div class="dashboard-metrics-grid">
        <article class="dashboard-metric-card">
            <div class="dashboard-metric-label">Total Records</div>
            <div class="dashboard-metric-value"><?= e(number_format((int) ($dashboardMetrics["total_records"] ?? 0))) ?></div>
        </article>

        <article class="dashboard-metric-card">
            <div class="dashboard-metric-label">Data Correction</div>
            <div class="dashboard-metric-value"><?= e(number_format((int) ($dashboardMetrics["data_correction_records"] ?? 0))) ?></div>
        </article>

        <a href="<?= e($dashboardEscalationUrl) ?>" class="dashboard-metric-card dashboard-metric-link">
            <div class="dashboard-metric-label">Escalation Candidates</div>
            <div class="dashboard-metric-value"><?= e(number_format((int) ($dashboardMetrics["escalation_records"] ?? 0))) ?></div>
        </a>

        <article class="dashboard-metric-card">
            <div class="dashboard-metric-label">Linked Tickets</div>
            <div class="dashboard-metric-value"><?= e(number_format((int) ($dashboardMetrics["linked_tickets"] ?? 0))) ?></div>
        </article>
    </div>

    <div class="dashboard-panel-grid">
        <article class="dashboard-panel dashboard-panel-wide">
            <div class="dashboard-panel-header">
                <div>
                    <h3>Status Overview</h3>
                </div>
            </div>
            <?php $renderDashboardBreakdown($dashboardData["status_breakdown"] ?? [], "No status tags are available for this scope."); ?>
        </article>

        <article class="dashboard-panel dashboard-panel-wide">
            <div class="dashboard-panel-header">
                <div>
                    <h3>Processed Type Mix</h3>
                </div>
            </div>
            <?php $renderDashboardBreakdown($dashboardData["processed_type_breakdown"] ?? [], "No processed types are available for this scope."); ?>
        </article>

        <article class="dashboard-panel">
            <div class="dashboard-panel-header">
                <div>
                    <h3>Module Activity</h3>
                </div>
            </div>
            <?php $renderDashboardBreakdown($dashboardData["module_breakdown"] ?? [], "No module activity is available yet."); ?>
        </article>

        <article class="dashboard-panel">
            <div class="dashboard-panel-header">
                <div>
                    <h3>Classification Mix</h3>
                </div>
            </div>
            <?php $renderDashboardBreakdown($dashboardData["classification_breakdown"] ?? [], "No classifications are available for this scope."); ?>
        </article>

        <article class="dashboard-panel">
            <div class="dashboard-panel-header">
                <div>
                    <h3>Branch Activity</h3>
                </div>
            </div>
            <?php $renderDashboardBreakdown($dashboardData["branch_breakdown"] ?? [], "No branch activity is available for this scope."); ?>
        </article>

        <article class="dashboard-panel">
            <div class="dashboard-panel-header">
                <div>
                    <h3>Dealer Activity</h3>
                </div>
            </div>
            <?php $renderDashboardBreakdown($dashboardData["dealer_breakdown"] ?? [], "No dealer activity is available for this scope."); ?>
        </article>

        <?php if ($ticketDashboardData !== null): ?>
        <article class="dashboard-panel dashboard-panel-wide dashboard-ticket-panel">
            <div class="dashboard-panel-header">
                <div>
                    <h3>Ticket Snapshot</h3>
                </div>
                <a href="<?= e($ticketMonitoringUrl) ?>" class="button-link secondary">Open Ticket Monitoring</a>
            </div>

            <div class="dashboard-ticket-metrics">
                <div class="dashboard-ticket-metric">
                    <span class="dashboard-ticket-label">Total Tickets</span>
                    <strong><?= e(number_format((int) ($ticketDashboardData["metrics"]["total_tickets"] ?? 0))) ?></strong>
                </div>
                <div class="dashboard-ticket-metric">
                    <span class="dashboard-ticket-label">Active</span>
                    <strong><?= e(number_format((int) ($ticketDashboardData["metrics"]["active_tickets"] ?? 0))) ?></strong>
                </div>
                <div class="dashboard-ticket-metric">
                    <span class="dashboard-ticket-label">Resolved</span>
                    <strong><?= e(number_format((int) ($ticketDashboardData["metrics"]["resolved_tickets"] ?? 0))) ?></strong>
                </div>
                <div class="dashboard-ticket-metric">
                    <span class="dashboard-ticket-label">7+ Days Old</span>
                    <strong><?= e(number_format((int) ($ticketDashboardData["metrics"]["aging_tickets"] ?? 0))) ?></strong>
                </div>
            </div>

            <p class="dashboard-ticket-note">
                Oldest active ticket:
                <strong><?= e(number_format((int) ($ticketDashboardData["metrics"]["oldest_active_days"] ?? 0))) ?> day(s)</strong>
            </p>

            <?php $renderDashboardBreakdown($ticketDashboardData["status_breakdown"] ?? [], "No ticket records are available for this scope."); ?>
        </article>
        <?php endif; ?>

        <article class="dashboard-panel dashboard-panel-wide">
            <div class="dashboard-panel-header">
                <div>
                    <h3>Recent Monitoring Activity</h3>
                </div>
            </div>

            <?php if ($recentDashboardRecords === []): ?>
            <p class="dashboard-empty-state">No monitoring records are available yet.</p>
            <?php else: ?>
            <div class="dashboard-activity-list">
                <?php foreach ($recentDashboardRecords as $row): ?>
                    <?php
                    $identificationNumber = trim((string) ($row["identification_number"] ?? ""));
                    $recordUrl = $identificationNumber !== ""
                        ? buildUrl("monitoring_record.php", $listQueryParams, ["identification_number" => $identificationNumber])
                        : "";
                    $activityTitle = trim((string) ($row["user_name"] ?? ""));
                    if ($activityTitle === "") {
                        $activityTitle = trim((string) ($row["client_name"] ?? ""));
                    }
                    if ($activityTitle === "") {
                        $activityTitle = "Unassigned";
                    }
                    $statusTags = splitMultiValueText($row["status"] ?? "");
                    if ($statusTags === []) {
                        $statusTags = ["No Status"];
                    }
                    $ticketValue = trim((string) ($row["ticket"] ?? ""));
                    $metaParts = array_filter([
                        formatDisplayDate((string) ($row["date_recorded"] ?? "")),
                        trim((string) ($row["module"] ?? "")),
                        trim((string) ($row["dealer"] ?? "")),
                        trim((string) ($row["branch"] ?? "")),
                    ]);
                    ?>
                <div class="dashboard-activity-item">
                    <div class="dashboard-activity-main">
                        <?php if ($recordUrl !== ""): ?>
                        <a href="<?= e($recordUrl) ?>" class="dashboard-activity-id"><?= e($identificationNumber) ?></a>
                        <?php else: ?>
                        <span class="dashboard-activity-id"><?= e($identificationNumber !== "" ? $identificationNumber : "No ID") ?></span>
                        <?php endif; ?>

                        <div class="dashboard-activity-title"><?= e(uppercaseText($activityTitle)) ?></div>
                        <div class="dashboard-activity-meta"><?= e(implode(" • ", $metaParts)) ?></div>
                    </div>

                    <div class="dashboard-activity-tags">
                        <?php foreach ($statusTags as $statusTag): ?>
                        <span class="dashboard-chip"><?= e(uppercaseText($statusTag)) ?></span>
                        <?php endforeach; ?>

                        <?php if (((int) ($row["data_correction_offense_count"] ?? 0)) >= 3): ?>
                        <span class="dashboard-chip alert">3+ DATA CORRECTIONS</span>
                        <?php endif; ?>

                        <?php if ($ticketValue !== ""): ?>
                        <span class="dashboard-chip ticket">TICKET <?= e(uppercaseText($ticketValue)) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </article>
    </div>
</section>
