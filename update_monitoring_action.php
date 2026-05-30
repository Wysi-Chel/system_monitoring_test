<?php
require "config.php";
require __DIR__ . "/includes/monitoring_options.php";
require __DIR__ . "/includes/monitoring_helpers.php";
require __DIR__ . "/includes/monitoring_repository.php";

$company = resolveCompanyConfig($_POST["company"] ?? $_GET["company"] ?? null, $companyConfigs);
ensureMonitoringTable($pdo, $company);
$tableNameSql = quoteMysqlIdentifier($company["table_name"]);

$recordId = is_numeric($_POST["record_id"] ?? null) ? (int) $_POST["record_id"] : 0;
$disciplinaryAction = trim((string) ($_POST["disciplinary_action"] ?? $_POST["action_taken"] ?? ""));
$allowedActions = getMonitoringActionOptions();
$doneStatus = getMonitoringDoneStatus();

$redirectParams = [
    "company" => $company["key"],
];

$filterMonth = trim((string) ($_POST["filter_month"] ?? ""));
$filterBranch = trim((string) ($_POST["filter_branch"] ?? ""));
$filterDealer = trim((string) ($_POST["filter_dealer"] ?? ""));
$filterIdentificationNumber = trim((string) ($_POST["filter_identification_number"] ?? ""));
$filterUserName = trim((string) ($_POST["filter_user_name"] ?? ""));
$filterStatus = trim((string) ($_POST["filter_status"] ?? ""));
$filterDataCorrection = trim((string) ($_POST["filter_data_correction"] ?? ""));
$filterEscalation = trim((string) ($_POST["filter_escalation"] ?? ""));
$filterPage = trim((string) ($_POST["filter_page"] ?? ""));

if ($filterMonth !== "") {
    $redirectParams["month"] = $filterMonth;
}

if ($filterBranch !== "") {
    $redirectParams["branch"] = $filterBranch;
}

if ($filterDealer !== "") {
    $redirectParams["dealer"] = $filterDealer;
}

if ($filterIdentificationNumber !== "") {
    $redirectParams["id_number"] = $filterIdentificationNumber;
}

if ($filterUserName !== "") {
    $redirectParams["user"] = $filterUserName;
}

if ($filterStatus !== "") {
    $redirectParams["status"] = $filterStatus;
}

if ($filterDataCorrection === "1") {
    $redirectParams["data_correction"] = 1;
}

if ($filterEscalation === "1") {
    $redirectParams["escalation"] = 1;
}

if ($filterPage !== "" && $filterPage !== "1") {
    $redirectParams["page"] = $filterPage;
}

if ($recordId > 0 && $disciplinaryAction !== "") {
    $record = fetchMonitoringRecordById($pdo, $tableNameSql, $recordId);

    if ($record !== null) {
        if ($disciplinaryAction === $doneStatus && canMarkMonitoringRecordDone($record["status"] ?? "")) {
            updateMonitoringRecordStatus($pdo, $tableNameSql, $recordId, $doneStatus);
        } elseif (in_array($disciplinaryAction, $allowedActions, true)) {
            $enrichedRecord = enrichMonitoringRecordsWithDataCorrectionActions($pdo, $tableNameSql, [$record])[0] ?? null;

            if (
                $enrichedRecord !== null
                && containsMultiValueText((string) ($enrichedRecord["processed_type"] ?? ""), "Data Correction")
                && (int) ($enrichedRecord["data_correction_offense_count"] ?? 0) >= 3
            ) {
                updateMonitoringRecordActionTaken($pdo, $tableNameSql, $recordId, $disciplinaryAction);
            }
        }
    }
}

header("Location: index.php?" . http_build_query($redirectParams) . "#summary-section");
exit;
?>
