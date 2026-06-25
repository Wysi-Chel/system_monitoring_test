<?php
$dashboardMetrics = $dashboardData["metrics"] ?? [];
$dashboardDataCorrectionUrl = buildUrl("index.php", $listQueryParams, [
    "data_correction" => 1,
    "escalation" => null,
    "page" => 1,
]) . "#summary-section";
$dashboardEscalationUrl = buildUrl("index.php", $listQueryParams, [
    "data_correction" => null,
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

        <a href="<?= e($dashboardDataCorrectionUrl) ?>" class="dashboard-metric-card dashboard-metric-link">
            <div class="dashboard-metric-label">Data Correction</div>
            <div class="dashboard-metric-value"><?= e(number_format((int) ($dashboardMetrics["data_correction_records"] ?? 0))) ?></div>
        </a>

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

    </div>
</section>
