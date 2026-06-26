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

function normalizeTicketField(?string $value, bool $uppercase = false): string
{
    $value = trim((string) $value);
    if ($value === "") {
        return "";
    }

    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return $uppercase ? mb_strtoupper($value, 'UTF-8') : $value;
}

function normalizeTicketDate(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === "") {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat("Y-m-d", $value);
    return $date && $date->format("Y-m-d") === $value ? $value : null;
}

$branch = $company["fixed_branch"] ?? normalizeTicketField($_POST["branch"] ?? "", true);
$dealer = normalizeAllowedFilter($_POST["dealer"] ?? "", $dealerOptions);
$module = normalizeAllowedFilter($_POST["module"] ?? "", $ticketModuleOptions);
$ticketNumber = normalizeTicketField($_POST["ticket_number"] ?? "", true);
$ticketDescription = normalizeTicketField($_POST["ticket_description"] ?? "");
$dateCreated = normalizeTicketDate($_POST["date_created"] ?? "");
$createdBy = normalizeTicketField($_POST["created_by"] ?? "", true);
$ticketStatus = $ticketStatusOptions[0];
$resolvedAt = isLockedTicketStatus($ticketStatus)
    ? (new DateTimeImmutable("now", new DateTimeZone("Asia/Manila")))->format("Y-m-d H:i:s")
    : null;

$sql = "INSERT INTO {$ticketTableNameSql} (
    branch,
    dealer,
    module,
    ticket_number,
    ticket_description,
    date_created,
    created_by,
    ticket_status,
    resolved_at
) VALUES (
    :branch,
    :dealer,
    :module,
    :ticket_number,
    :ticket_description,
    :date_created,
    :created_by,
    :ticket_status,
    :resolved_at
)";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ":branch" => $branch,
    ":dealer" => $dealer,
    ":module" => $module,
    ":ticket_number" => $ticketNumber,
    ":ticket_description" => $ticketDescription,
    ":date_created" => $dateCreated,
    ":created_by" => $createdBy,
    ":ticket_status" => $ticketStatus,
    ":resolved_at" => $resolvedAt,
]);

$ticketId = (int) $pdo->lastInsertId();

$redirectQuery = http_build_query([
    "company" => $company["key"],
    "saved" => 1,
    "ticket_number" => $ticketNumber,
]);

header("Location: ticket_monitoring.php?" . $redirectQuery);
exit;
?>
