<?php
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
    "per_page" => $monitoringSummaryRowsPerPageOptions,
];
$filters = buildMonitoringFilters($_GET, $company, $filterOptions);
$records = fetchMonitoringRecords($pdo, $tableNameSql, $filters, null, 0, "ASC");
$records = enrichMonitoringRecordsWithDataCorrectionActions($pdo, $tableNameSql, $records);

if (!empty($filters["escalation_only"])) {
    $records = filterEscalationCandidateMonitoringRecords($records);
}

$headers = getSummaryHeaders($summaryColumns);

$rows = [];
foreach ($records as $row) {
    $rowValues = [];

    foreach ($summaryColumns as $column) {
        $value = $row[$column["key"]] ?? "";

        if (($column["format"] ?? "text") === "action_control") {
            $rowValues[] = $row["disciplinary_action"] ?? "";
            continue;
        }

        if (($column["format"] ?? "text") === "date") {
            $rowValues[] = formatDateExcel($value);
            continue;
        }

        if (($column["format"] ?? "text") === "amount") {
            $rowValues[] = normalizeAmount($value);
            continue;
        }

        $rowValues[] = $value;
    }

    $rows[] = $rowValues;
}

$filename = $company["export_slug"] . "_system_monitoring_summary_" . date("Ymd_His") . ".xlsx";

try {
    $tempFile = buildXlsxFile($company["system_name"] . " Summary", $headers, $rows);
    downloadXlsxFile($tempFile, $filename);
} catch (Throwable $e) {
    error_log(sprintf(
        "[system_monitoring] Excel export failed for %s: %s in %s on line %d",
        $company["key"],
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
    http_response_code(500);
    header("Content-Type: text/plain; charset=UTF-8");
    echo "Unable to generate the Excel file right now.";
}

function formatDateExcel($value): string
{
    if (!$value) {
        return "";
    }

    $timestamp = strtotime((string) $value);
    if ($timestamp === false) {
        return (string) $value;
    }

    return date("n/j/Y", $timestamp);
}

function normalizeAmount($value)
{
    if ($value === null || $value === "") {
        return "";
    }

    return is_numeric((string) $value) ? (float) $value : (string) $value;
}

function buildXlsxFile(string $sheetName, array $headers, array $rows): string
{
    $payloadPath = tempnam(sys_get_temp_dir(), "monitoring_payload_");
    if ($payloadPath === false) {
        throw new RuntimeException("Unable to allocate a temporary payload file.");
    }

    $basePath = tempnam(sys_get_temp_dir(), "monitoring_export_");
    if ($basePath === false) {
        @unlink($payloadPath);
        throw new RuntimeException("Unable to allocate a temporary workbook file.");
    }

    @unlink($basePath);
    $xlsxPath = $basePath . ".xlsx";

    try {
        $payload = [
            "sheet_name" => $sheetName,
            "headers" => $headers,
            "rows" => $rows,
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($payloadPath, $json) === false) {
            throw new RuntimeException("Unable to write the export payload.");
        }

        runPythonExporter($payloadPath, $xlsxPath);

        if (!is_file($xlsxPath) || filesize($xlsxPath) === 0) {
            throw new RuntimeException("The workbook was not created.");
        }

        return $xlsxPath;
    } catch (Throwable $e) {
        @unlink($xlsxPath);
        throw $e;
    } finally {
        @unlink($payloadPath);
    }
}

function runPythonExporter(string $payloadPath, string $xlsxPath): void
{
    $scriptPath = __DIR__ . DIRECTORY_SEPARATOR . "scripts" . DIRECTORY_SEPARATOR . "export_excel_helper.py";
    if (!is_file($scriptPath)) {
        throw new RuntimeException("The Excel export helper is missing.");
    }

    $command = resolvePythonCommand() . " "
        . escapeshellarg($scriptPath) . " "
        . escapeshellarg($payloadPath) . " "
        . escapeshellarg($xlsxPath);

    $descriptorSpec = [
        1 => ["pipe", "w"],
        2 => ["pipe", "w"],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, __DIR__);
    if (!is_resource($process)) {
        throw new RuntimeException("Unable to start the Excel export helper.");
    }

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        $message = trim((string) ($stderr !== "" ? $stderr : $stdout));
        throw new RuntimeException($message !== "" ? $message : "The Excel export helper failed.");
    }
}

function resolvePythonCommand(): string
{
    global $pythonCommand;

    $candidates = [];

    if (isset($pythonCommand) && is_string($pythonCommand) && trim($pythonCommand) !== "") {
        $candidates[] = trim($pythonCommand);
    }

    foreach (["python", "py -3"] as $candidate) {
        $candidates[] = $candidate;
    }

    foreach ($candidates as $candidate) {
        $command = normalizePythonCommand($candidate);
        if (canRunCommand($command . " --version")) {
            return $command;
        }
    }

    throw new RuntimeException("Python with openpyxl is not available for Excel export.");
}

function normalizePythonCommand(string $command): string
{
    $command = trim($command);
    if ($command === "") {
        return $command;
    }

    if (preg_match('/\.exe$/i', $command) || str_contains($command, "\\") || str_contains($command, "/")) {
        return escapeshellarg($command);
    }

    return $command;
}

function canRunCommand(string $command): bool
{
    $descriptorSpec = [
        1 => ["pipe", "w"],
        2 => ["pipe", "w"],
    ];

    $process = @proc_open($command, $descriptorSpec, $pipes, __DIR__);
    if (!is_resource($process)) {
        return false;
    }

    fclose($pipes[1]);
    fclose($pipes[2]);

    return proc_close($process) === 0;
}

function downloadXlsxFile(string $filePath, string $filename): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
    header("Content-Length: " . filesize($filePath));
    header("Cache-Control: max-age=0");
    header("Pragma: public");
    header("Expires: 0");

    readfile($filePath);
    @unlink($filePath);
    exit;
}
