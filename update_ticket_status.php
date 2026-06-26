<?php
require __DIR__ . "/includes/auth.php";
requireMonitoringAuthentication();
require "config.php";
require __DIR__ . "/includes/monitoring_options.php";
require __DIR__ . "/includes/monitoring_helpers.php";
require __DIR__ . "/includes/monitoring_repository.php";

$company = resolveCompanyConfig($_POST["company"] ?? $_GET["company"] ?? null, $companyConfigs);
if (!companySupportsTicketMonitoring($company)) {
    header("Location: index.php?company=" . urlencode($company["key"]));
    exit;
}
ensureTicketMonitoringTable($pdo, $company);

$ticketTableNameSql = quoteMysqlIdentifier($company["ticket_table_name"]);
$ticketId = is_numeric($_POST["ticket_id"] ?? null) ? (int) $_POST["ticket_id"] : 0;
$newTicketStatus = trim((string) ($_POST["new_ticket_status"] ?? ""));

$redirectParams = [
    "company" => $company["key"],
];

$filterSearch = trim((string) ($_POST["filter_search"] ?? ""));
$filterBranch = trim((string) ($_POST["filter_branch"] ?? ""));
$filterDealer = trim((string) ($_POST["filter_dealer"] ?? ""));
$filterStatus = trim((string) ($_POST["filter_status"] ?? ""));
$filterPerPage = trim((string) ($_POST["filter_per_page"] ?? ""));
$filterPage = trim((string) ($_POST["filter_page"] ?? ""));

if ($filterSearch !== "") {
    $redirectParams["q"] = $filterSearch;
}

if ($filterBranch !== "") {
    $redirectParams["branch"] = $filterBranch;
}

if ($filterDealer !== "") {
    $redirectParams["dealer"] = $filterDealer;
}

if ($filterStatus !== "") {
    $redirectParams["status"] = $filterStatus;
}

if ($filterPerPage !== "" && $filterPerPage !== "25") {
    $redirectParams["per_page"] = $filterPerPage;
}

if ($filterPage !== "" && $filterPage !== "1") {
    $redirectParams["page"] = $filterPage;
}

$allowedStatuses = $ticketStatusOptions;
if ($ticketId > 0 && in_array($newTicketStatus, $allowedStatuses, true)) {
    $ticketRecord = fetchTicketMonitoringRecordById($pdo, $ticketTableNameSql, $ticketId);

    if ($ticketRecord !== null && !isLockedTicketStatus($ticketRecord["ticket_status"] ?? "")) {
        $resolvedAt = isLockedTicketStatus($newTicketStatus)
            ? (new DateTimeImmutable("now", new DateTimeZone("Asia/Manila")))->format("Y-m-d H:i:s")
            : null;

        updateTicketMonitoringRecordStatus($pdo, $ticketTableNameSql, $ticketId, $newTicketStatus, $resolvedAt);

        $redirectParams["saved"] = 1;
        $redirectParams["updated"] = 1;
    }
}

header("Location: ticket_monitoring.php?" . http_build_query($redirectParams) . "#ticket-summary");
exit;
?>
