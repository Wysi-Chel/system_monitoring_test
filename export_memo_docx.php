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
$identificationNumber = normalizeIdentificationNumberFilter($_GET["identification_number"] ?? $_GET["id_number"] ?? "");

if ($identificationNumber === "") {
    http_response_code(400);
    header("Content-Type: text/plain; charset=UTF-8");
    echo "Missing record identification number.";
    exit;
}

$record = fetchMonitoringRecordByIdentificationNumber($pdo, $tableNameSql, $identificationNumber);
if ($record === null) {
    http_response_code(404);
    header("Content-Type: text/plain; charset=UTF-8");
    echo "Record not found.";
    exit;
}

if (!isUserErrorMonitoringRecord($record)) {
    http_response_code(403);
    header("Content-Type: text/plain; charset=UTF-8");
    echo "Memo export is available only for User Error records.";
    exit;
}

$record = enrichMonitoringRecordsWithDataCorrectionActions($pdo, $tableNameSql, [$record])[0] ?? $record;
$filename = buildMemoFilename($company, $record);

try {
    $docxPath = buildDraftMemoDocx($company, $record);
    downloadDocxFile($docxPath, $filename);
} catch (Throwable $e) {
    error_log(sprintf(
        "[system_monitoring] Memo export failed for %s/%s: %s in %s on line %d",
        $company["key"],
        $identificationNumber,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
    http_response_code(500);
    header("Content-Type: text/plain; charset=UTF-8");
    echo "Unable to generate the memo file right now.";
}

function buildMemoFilename(array $company, array $record): string
{
    $userName = buildMemoFilenameUserSegment((string) ($record["user_name"] ?? ""));
    $action = trim((string) ($record["disciplinary_action"] ?? ""));
    if ($action === "") {
        $action = "draft memo";
    }

    $actionSegment = buildMemoFilenameSegment($action);
    $base = trim($userName . "_" . $actionSegment, "_");
    $base = trim($base, "_");

    return ($base !== "" ? $base : "monitoring_memo") . ".docx";
}

function buildMemoFilenameUserSegment(string $userName): string
{
    $normalizedUserName = trim(preg_replace('/\s+/', ' ', $userName) ?? $userName);
    if ($normalizedUserName === "") {
        return "unknown_user";
    }

    $parts = preg_split('/\s+/', $normalizedUserName);
    if ($parts === false || $parts === []) {
        return buildMemoFilenameSegment($normalizedUserName);
    }

    if (count($parts) > 1) {
        $lastName = array_pop($parts);
        array_unshift($parts, $lastName);
    }

    return buildMemoFilenameSegment(implode(" ", $parts));
}

function buildMemoFilenameSegment(string $value): string
{
    $segment = strtolower(trim($value));
    $segment = preg_replace('/[^a-z0-9]+/', "_", $segment) ?? "";
    return trim($segment, "_");
}

function buildDraftMemoDocx(array $company, array $record): string
{
    $basePath = tempnam(sys_get_temp_dir(), "monitoring_memo_");
    if ($basePath === false) {
        throw new RuntimeException("Unable to allocate a temporary memo file.");
    }

    @unlink($basePath);
    $docxPath = $basePath . ".docx";

    $files = [
        "[Content_Types].xml" => buildDocxContentTypesXml(),
        "_rels/.rels" => buildDocxRootRelationshipsXml(),
        "word/document.xml" => buildMemoDocumentXml($company, $record),
    ];

    if (class_exists("ZipArchive")) {
        buildDocxWithZipArchive($docxPath, $files);
    } else {
        buildDocxWithPython($docxPath, $files);
    }

    if (!is_file($docxPath) || filesize($docxPath) === 0) {
        throw new RuntimeException("The memo Word file was not created.");
    }

    return $docxPath;
}

function buildDocxWithZipArchive(string $docxPath, array $files): void
{
    $zip = new ZipArchive();

    if ($zip->open($docxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Unable to create the memo Word file.");
    }

    foreach ($files as $path => $content) {
        $zip->addFromString($path, $content);
    }

    $zip->close();
}

function buildDocxWithPython(string $docxPath, array $files): void
{
    $payloadPath = tempnam(sys_get_temp_dir(), "monitoring_memo_payload_");
    if ($payloadPath === false) {
        throw new RuntimeException("Unable to allocate a temporary memo payload.");
    }

    try {
        $payload = [
            "output" => $docxPath,
            "files" => $files,
        ];
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($payloadPath, $json) === false) {
            throw new RuntimeException("Unable to write the memo payload.");
        }

        runPythonMemoExporter($payloadPath);
    } finally {
        @unlink($payloadPath);
    }
}

function runPythonMemoExporter(string $payloadPath): void
{
    $scriptPath = __DIR__ . DIRECTORY_SEPARATOR . "scripts" . DIRECTORY_SEPARATOR . "export_memo_docx_helper.py";
    if (!is_file($scriptPath)) {
        throw new RuntimeException("The memo export helper is missing.");
    }

    $command = resolveMemoPythonCommand() . " "
        . escapeshellarg($scriptPath) . " "
        . escapeshellarg($payloadPath);

    $descriptorSpec = [
        1 => ["pipe", "w"],
        2 => ["pipe", "w"],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, __DIR__);
    if (!is_resource($process)) {
        throw new RuntimeException("Unable to start the memo export helper.");
    }

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        $message = trim((string) ($stderr !== "" ? $stderr : $stdout));
        throw new RuntimeException($message !== "" ? $message : "The memo export helper failed.");
    }
}

function resolveMemoPythonCommand(): string
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
        $command = normalizeMemoPythonCommand($candidate);
        if (canRunMemoCommand($command . " --version")) {
            return $command;
        }
    }

    throw new RuntimeException("Python is not available for memo export.");
}

function normalizeMemoPythonCommand(string $command): string
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

function canRunMemoCommand(string $command): bool
{
    $descriptorSpec = [
        1 => ["pipe", "w"],
        2 => ["pipe", "w"],
    ];

    $process = @proc_open($command, $descriptorSpec, $pipes, __DIR__);
    if (!is_resource($process)) {
        return false;
    }

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $output = trim((string) ($stdout . "\n" . $stderr));
    return proc_close($process) === 0
        && stripos($output, "Microsoft Store") === false
        && preg_match('/Python\s+\d/i', $output) === 1;
}

function buildDocxContentTypesXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '</Types>';
}

function buildDocxRootRelationshipsXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '</Relationships>';
}

function buildMemoDocumentXml(array $company, array $record): string
{
    $timezone = new DateTimeZone("Asia/Manila");
    $generatedAt = (new DateTimeImmutable("now", $timezone))->format("F j, Y g:i A");
    $memoAction = trim((string) ($record["disciplinary_action"] ?? ""));
    if ($memoAction === "") {
        $memoAction = "Draft Memo";
    }

    $fields = [
        "ID Number" => formatSummaryValue(["key" => "identification_number", "format" => "text"], $record),
        "Date" => formatSummaryValue(["key" => "date_recorded", "format" => "date"], $record),
        "Transaction Date" => formatSummaryValue(["key" => "transaction_date", "format" => "date"], $record),
        "Branch" => formatSummaryValue(["key" => "branch", "format" => "text"], $record),
        "Dealers" => formatSummaryValue(["key" => "dealer", "format" => "text"], $record),
        "Department" => formatSummaryValue(["key" => "department", "format" => "text"], $record),
        "Module" => formatSummaryValue(["key" => "module", "format" => "text"], $record),
        "User" => formatSummaryValue(["key" => "user_name", "format" => "text"], $record),
        "Client Name" => formatSummaryValue(["key" => "client_name", "format" => "text"], $record),
        "Transaction Reference" => formatSummaryValue(["key" => "invoice_reference", "format" => "text"], $record),
        "Reason" => formatSummaryValue(["key" => "reason", "format" => "text"], $record),
        "Approved By" => formatSummaryValue(["key" => "approved_by", "format" => "text"], $record),
        "Processed Type" => formatMonitoringProcessedTypeDisplayValue($record),
        "Processed By" => formatSummaryValue(["key" => "processed_by", "format" => "text"], $record),
        "Classification" => formatSummaryValue(["key" => "classification", "format" => "text"], $record),
        "Alert" => formatSummaryValue(["key" => "data_correction_alert", "format" => "text"], $record),
        "Action" => $memoAction,
        "Status" => formatSummaryValue(["key" => "status", "format" => "text"], $record),
        "Ticket" => formatSummaryValue(["key" => "ticket", "format" => "text"], $record),
        "Remarks" => formatSummaryValue(["key" => "remarks", "format" => "text"], $record),
    ];

    $body = docxParagraph(uppercaseText($company["system_name"]), true, "center", 24);
    $body .= docxParagraph("DRAFT MEMORANDUM", true, "center", 32);
    $body .= docxParagraph("Memo Type: " . uppercaseText($memoAction), true, "left", 22);
    $body .= docxParagraph("Generated: " . $generatedAt, false, "left", 20);
    $body .= docxParagraph("This is a draft memo generated from the encoded monitoring incident record.", false, "left", 20);
    $body .= docxTable($fields);
    $body .= docxParagraph("Prepared By:", true, "left", 20);
    $body .= docxParagraph("______________________________", false, "left", 20);
    $body .= docxParagraph("Reviewed By:", true, "left", 20);
    $body .= docxParagraph("______________________________", false, "left", 20);

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:body>'
        . $body
        . '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1080" w:right="1080" w:bottom="1080" w:left="1080" w:header="720" w:footer="720" w:gutter="0"/></w:sectPr>'
        . '</w:body></w:document>';
}

function docxTable(array $fields): string
{
    $rows = "";
    foreach ($fields as $label => $value) {
        $rows .= '<w:tr>'
            . docxCell((string) $label, true, 3200)
            . docxCell(trim((string) $value) !== "" ? (string) $value : "N/A", false, 7600)
            . '</w:tr>';
    }

    return '<w:tbl>'
        . '<w:tblPr><w:tblW w:w="10800" w:type="dxa"/><w:tblBorders><w:top w:val="single" w:sz="4" w:space="0" w:color="999999"/><w:left w:val="single" w:sz="4" w:space="0" w:color="999999"/><w:bottom w:val="single" w:sz="4" w:space="0" w:color="999999"/><w:right w:val="single" w:sz="4" w:space="0" w:color="999999"/><w:insideH w:val="single" w:sz="4" w:space="0" w:color="999999"/><w:insideV w:val="single" w:sz="4" w:space="0" w:color="999999"/></w:tblBorders></w:tblPr>'
        . '<w:tblGrid><w:gridCol w:w="3200"/><w:gridCol w:w="7600"/></w:tblGrid>'
        . $rows
        . '</w:tbl>';
}

function docxCell(string $text, bool $bold, int $width): string
{
    return '<w:tc><w:tcPr><w:tcW w:w="' . $width . '" w:type="dxa"/><w:tcMar><w:top w:w="90" w:type="dxa"/><w:left w:w="90" w:type="dxa"/><w:bottom w:w="90" w:type="dxa"/><w:right w:w="90" w:type="dxa"/></w:tcMar></w:tcPr>'
        . docxParagraph($text, $bold, "left", 18, false)
        . '</w:tc>';
}

function docxParagraph(string $text, bool $bold = false, string $alignment = "left", int $fontSize = 20, bool $spacingAfter = true): string
{
    $paragraphProps = '<w:pPr><w:jc w:val="' . docxXml($alignment) . '"/>';
    if ($spacingAfter) {
        $paragraphProps .= '<w:spacing w:after="160"/>';
    }
    $paragraphProps .= '</w:pPr>';

    $runProps = '<w:rPr>' . ($bold ? '<w:b/>' : '') . '<w:sz w:val="' . $fontSize . '"/></w:rPr>';
    return '<w:p>' . $paragraphProps . '<w:r>' . $runProps . '<w:t xml:space="preserve">' . docxXml($text) . '</w:t></w:r></w:p>';
}

function docxXml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, "UTF-8");
}

function downloadDocxFile(string $filePath, string $filename): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
    header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
    header("Content-Length: " . filesize($filePath));
    header("Cache-Control: max-age=0");
    header("Pragma: public");
    header("Expires: 0");

    readfile($filePath);
    @unlink($filePath);
    exit;
}
?>
