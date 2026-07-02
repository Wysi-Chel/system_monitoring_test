<?php
require __DIR__ . "/includes/auth.php";
requireMonitoringAuthentication();
require "config.php";
require __DIR__ . "/includes/monitoring_options.php";
require __DIR__ . "/includes/monitoring_helpers.php";

$company = resolveCompanyConfig($_POST["company"] ?? $_GET["company"] ?? null, $companyConfigs);
ensureMonitoringTable($pdo, $company);
$tableNameSql = quoteMysqlIdentifier($company["table_name"]);

function shouldPreserveToken(string $token): bool
{
    $core = preg_replace('/^[^\p{L}\p{N}_]+|[^\p{L}\p{N}_#\/\-\(\)]+$/u', '', $token) ?? '';
    if ($core === '') {
        return false;
    }

    if (preg_match('/\d/u', $core)) {
        return true;
    }

    if (preg_match('/[#\/\-\(\)]/u', $core)) {
        return true;
    }

    $lettersOnly = preg_replace('/[^\p{L}]/u', '', $core) ?? '';
    if ($lettersOnly === '') {
        return false;
    }

    $length = mb_strlen($lettersOnly, 'UTF-8');
    return $length >= 2
        && $length <= 6
        && mb_strtoupper($lettersOnly, 'UTF-8') === $lettersOnly;
}

function capitalizeFirstLetter(string $text): string
{
    return preg_replace_callback(
        '/\p{L}/u',
        static fn(array $matches): string => mb_strtoupper($matches[0], 'UTF-8'),
        $text,
        1
    ) ?? $text;
}

function sentenceCaseInput(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    $parts = preg_split('/([.!?]+\s*)/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return $value;
    }

    $normalizedParts = [];

    foreach ($parts as $index => $part) {
        if ($index % 2 === 1) {
            $normalizedParts[] = $part;
            continue;
        }

        $tokens = preg_split('/(\s+)/u', $part, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($tokens === false) {
            $normalizedParts[] = capitalizeFirstLetter(mb_strtolower($part, 'UTF-8'));
            continue;
        }

        $normalizedTokens = [];

        foreach ($tokens as $token) {
            if ($token === '' || preg_match('/^\s+$/u', $token)) {
                $normalizedTokens[] = $token;
            } elseif (shouldPreserveToken($token)) {
                $normalizedTokens[] = $token;
            } else {
                $normalizedTokens[] = mb_strtolower($token, 'UTF-8');
            }
        }

        $normalizedParts[] = capitalizeFirstLetter(implode('', $normalizedTokens));
    }

    return implode('', $normalizedParts);
}

function uppercaseInput(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return mb_strtoupper($value, 'UTF-8');
}

function normalizeAllowedOptionValue(?string $value, array $allowedOptions): string
{
    $value = trim((string) $value);
    return in_array($value, $allowedOptions, true) ? $value : '';
}

function normalizeMonitoringIdentificationNumber(?string $value): string
{
    $value = trim((string) $value);
    return preg_match('/^\d{6}$/', $value) ? $value : '';
}

function redirectToMonitoringFormWithError(array $company, string $errorCode): never
{
    $postedRecordId = is_numeric($_POST["record_id"] ?? null) ? (int) $_POST["record_id"] : 0;
    $postedIdentificationNumber = normalizeMonitoringIdentificationNumber($_POST["identification_number"] ?? "");

    if ($postedRecordId > 0 && $postedIdentificationNumber !== "") {
        redirectToMonitoringRecordWithError($company, $postedIdentificationNumber, $errorCode);
    }

    $redirectQuery = http_build_query([
        "company" => $company["key"],
        "error" => $errorCode,
    ]);

    header("Location: index.php?" . $redirectQuery . "#record-form");
    exit;
}

function redirectToMonitoringRecordWithError(array $company, string $identificationNumber, string $errorCode): never
{
    $redirectQuery = http_build_query([
        "company" => $company["key"],
        "identification_number" => $identificationNumber,
        "edit" => 1,
        "error" => $errorCode,
    ]);

    header("Location: monitoring_record.php?" . $redirectQuery);
    exit;
}

function detectUploadedIncidentImageMimeType(string $temporaryPath): string
{
    if (function_exists("finfo_open")) {
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($fileInfo !== false) {
            $mimeType = finfo_file($fileInfo, $temporaryPath);
            finfo_close($fileInfo);
            if (is_string($mimeType) && $mimeType !== "") {
                return $mimeType;
            }
        }
    }

    if (function_exists("mime_content_type")) {
        $mimeType = mime_content_type($temporaryPath);
        if (is_string($mimeType) && $mimeType !== "") {
            return $mimeType;
        }
    }

    return "";
}

function normalizeIncidentReportUpload(?array $file, array $company): ?array
{
    if (!is_array($file) || !isset($file["error"])) {
        return null;
    }

    $uploadError = (int) $file["error"];
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        redirectToMonitoringFormWithError($company, "incident_image_upload_failed");
    }

    $temporaryPath = (string) ($file["tmp_name"] ?? "");
    $fileSize = (int) ($file["size"] ?? 0);

    if ($temporaryPath === "" || !is_uploaded_file($temporaryPath)) {
        redirectToMonitoringFormWithError($company, "incident_image_upload_failed");
    }

    if ($fileSize <= 0 || $fileSize > 5 * 1024 * 1024) {
        redirectToMonitoringFormWithError($company, "incident_image_too_large");
    }

    $mimeType = detectUploadedIncidentImageMimeType($temporaryPath);
    $allowedMimeTypes = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp",
        "image/gif" => "gif",
    ];

    if (!isset($allowedMimeTypes[$mimeType])) {
        redirectToMonitoringFormWithError($company, "incident_image_invalid_type");
    }

    return [
        "temporary_path" => $temporaryPath,
        "extension" => $allowedMimeTypes[$mimeType],
    ];
}

function normalizeMultiSelectInput($value): string
{
    if (!is_array($value)) {
        return sentenceCaseInput($value === null ? '' : (string) $value);
    }

    $normalizedValues = [];

    foreach ($value as $item) {
        if (!is_scalar($item)) {
            continue;
        }

        $normalizedItem = sentenceCaseInput((string) $item);
        if ($normalizedItem === '' || in_array($normalizedItem, $normalizedValues, true)) {
            continue;
        }

        $normalizedValues[] = $normalizedItem;
    }

    return implode(', ', $normalizedValues);
}

function containsNormalizedMultiSelectValue(string $value, string $target): bool
{
    $normalizedValue = trim($value);
    $normalizedTarget = trim($target);
    if ($normalizedValue === '' || $normalizedTarget === '') {
        return false;
    }

    $targetKey = mb_strtoupper($normalizedTarget, 'UTF-8');
    $values = array_map(
        static fn(string $item): string => mb_strtoupper(trim($item), 'UTF-8'),
        explode(',', $normalizedValue)
    );

    return in_array($targetKey, $values, true);
}

$amount = $_POST["amount"] ?? null;
if ($amount === "") {
    $amount = null;
}

$optionFields = [
    "dealer",
    "department",
    "classification",
];

$uppercaseFields = [
    "user_name",
    "invoice_reference",
    "payment_reference",
    "client_name",
    "reason",
    "approved_by",
    "processed_by",
    "remarks",
    "system_admin",
    "ticket",
    "offense",
];

$normalizedText = [];
foreach ($optionFields as $field) {
    $normalizedText[$field] = sentenceCaseInput($_POST[$field] ?? "");
}

$normalizedText["module"] = normalizeAllowedOptionValue($_POST["module"] ?? "", $moduleOptions);
$normalizedText["branch"] = sentenceCaseInput($company["fixed_branch"] ?? ($_POST["branch"] ?? ""));

foreach ($uppercaseFields as $field) {
    $normalizedText[$field] = uppercaseInput($_POST[$field] ?? "");
}

$normalizedText["processed_type"] = normalizeMultiSelectInput($_POST["processed_type"] ?? []);
$normalizedText["status"] = normalizeMultiSelectInput($_POST["status"] ?? []);
if (
    uppercaseText($normalizedText["offense"]) === uppercaseText(getMonitoringIncidentReportOffense())
    && !containsMultiValueText($normalizedText["status"], "Pending")
) {
    $statusValues = splitMultiValueText($normalizedText["status"]);
    $statusValues[] = "Pending";
    $normalizedText["status"] = implode(", ", $statusValues);
}
$incidentReportUpload = normalizeIncidentReportUpload($_FILES["incident_report_image"] ?? null, $company);
$editRecordId = is_numeric($_POST["record_id"] ?? null) ? (int) $_POST["record_id"] : 0;
$isEditingRecord = $editRecordId > 0;
$existingRecord = null;
$prefilledIdentificationNumber = normalizeMonitoringIdentificationNumber($_POST["identification_number"] ?? "");

if ($isEditingRecord) {
    $existingRecordStmt = $pdo->prepare("SELECT * FROM {$tableNameSql} WHERE id = :id LIMIT 1");
    $existingRecordStmt->bindValue(":id", $editRecordId, PDO::PARAM_INT);
    $existingRecordStmt->execute();
    $existingRecord = $existingRecordStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingRecord === false) {
        redirectToMonitoringFormWithError($company, "record_update_failed");
    }

    $prefilledIdentificationNumber = normalizeMonitoringIdentificationNumber(
        (string) ($existingRecord["identification_number"] ?? $prefilledIdentificationNumber)
    );
}

$identificationNumber = $prefilledIdentificationNumber !== ""
    ? $prefilledIdentificationNumber
    : getNextMonitoringIdentificationNumber($pdo, $company);

if (
    mb_strtoupper($normalizedText["classification"], 'UTF-8') === mb_strtoupper("User Error", 'UTF-8')
    && $normalizedText["user_name"] === ""
) {
    if ($isEditingRecord) {
        redirectToMonitoringRecordWithError($company, $identificationNumber, "user_error_user_required");
    }

    redirectToMonitoringFormWithError($company, "user_error_user_required");
}
$storedIncidentReportRelativePath = null;
$storedIncidentReportAbsolutePath = null;

try {
    $pdo->beginTransaction();

    if ($isEditingRecord && $incidentReportUpload !== null) {
        $incidentReportDirectory = ensureMonitoringIncidentReportDirectory($company);
        $relativeDirectory = getMonitoringIncidentReportRelativeDirectory($company);
        $filename = str_replace("/", "-", $identificationNumber) . "-" . date("Ymd_His") . "." . $incidentReportUpload["extension"];
        $storedIncidentReportAbsolutePath = $incidentReportDirectory . DIRECTORY_SEPARATOR . $filename;
        $storedIncidentReportRelativePath = $relativeDirectory . "/" . $filename;

        if (!move_uploaded_file($incidentReportUpload["temporary_path"], $storedIncidentReportAbsolutePath)) {
            throw new RuntimeException("incident_image_storage_failed");
        }
    }

    if ($isEditingRecord) {
        $incidentReportImagePath = $storedIncidentReportRelativePath
            ?? (string) ($existingRecord["incident_report_image_path"] ?? "");

        $stmt = $pdo->prepare(
            "UPDATE {$tableNameSql}
             SET date_recorded = :date_recorded,
                 transaction_date = :transaction_date,
                 branch = :branch,
                 dealer = :dealer,
                 department = :department,
                 module = :module,
                 user_name = :user_name,
                 invoice_reference = :invoice_reference,
                 payment_reference = :payment_reference,
                 client_name = :client_name,
                 amount = :amount,
                 reason = :reason,
                 approved_by = :approved_by,
                 processed_type = :processed_type,
                 processed_by = :processed_by,
                 remarks = :remarks,
                 incident_report_image_path = :incident_report_image_path,
                 classification = :classification,
                 system_admin = :system_admin,
                 ticket = :ticket,
                 status = :status,
                 offense = :offense
             WHERE id = :id"
        );
        $stmt->execute([
            ":date_recorded" => $_POST["date_recorded"] ?? null,
            ":transaction_date" => $_POST["transaction_date"] ?? null,
            ":branch" => $normalizedText["branch"],
            ":dealer" => $normalizedText["dealer"],
            ":department" => $normalizedText["department"],
            ":module" => $normalizedText["module"],
            ":user_name" => $normalizedText["user_name"],
            ":invoice_reference" => $normalizedText["invoice_reference"],
            ":payment_reference" => $normalizedText["payment_reference"],
            ":client_name" => $normalizedText["client_name"],
            ":amount" => $amount,
            ":reason" => $normalizedText["reason"],
            ":approved_by" => $normalizedText["approved_by"],
            ":processed_type" => $normalizedText["processed_type"],
            ":processed_by" => $normalizedText["processed_by"],
            ":remarks" => $normalizedText["remarks"],
            ":incident_report_image_path" => $incidentReportImagePath !== "" ? $incidentReportImagePath : null,
            ":classification" => $normalizedText["classification"],
            ":system_admin" => $normalizedText["system_admin"],
            ":ticket" => $normalizedText["ticket"],
            ":status" => $normalizedText["status"],
            ":offense" => $normalizedText["offense"],
            ":id" => $editRecordId,
        ]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO {$tableNameSql} (
                identification_number,
                date_recorded,
                transaction_date,
                branch,
                dealer,
                department,
                module,
                user_name,
                invoice_reference,
                payment_reference,
                client_name,
                amount,
                reason,
                approved_by,
                processed_type,
                processed_by,
                remarks,
                incident_report_image_path,
                classification,
                system_admin,
                ticket,
                status,
                offense
            ) VALUES (
                :identification_number,
                :date_recorded,
                :transaction_date,
                :branch,
                :dealer,
                :department,
                :module,
                :user_name,
                :invoice_reference,
                :payment_reference,
                :client_name,
                :amount,
                :reason,
                :approved_by,
                :processed_type,
                :processed_by,
                :remarks,
                :incident_report_image_path,
                :classification,
                :system_admin,
                :ticket,
                :status,
                :offense
            )"
        );
        $stmt->execute([
            ":identification_number" => $identificationNumber,
            ":date_recorded" => $_POST["date_recorded"] ?? null,
            ":transaction_date" => $_POST["transaction_date"] ?? null,
            ":branch" => $normalizedText["branch"],
            ":dealer" => $normalizedText["dealer"],
            ":department" => $normalizedText["department"],
            ":module" => $normalizedText["module"],
            ":user_name" => $normalizedText["user_name"],
            ":invoice_reference" => $normalizedText["invoice_reference"],
            ":payment_reference" => $normalizedText["payment_reference"],
            ":client_name" => $normalizedText["client_name"],
            ":amount" => $amount,
            ":reason" => $normalizedText["reason"],
            ":approved_by" => $normalizedText["approved_by"],
            ":processed_type" => $normalizedText["processed_type"],
            ":processed_by" => $normalizedText["processed_by"],
            ":remarks" => $normalizedText["remarks"],
            ":incident_report_image_path" => null,
            ":classification" => $normalizedText["classification"],
            ":system_admin" => $normalizedText["system_admin"],
            ":ticket" => $normalizedText["ticket"],
            ":status" => $normalizedText["status"],
            ":offense" => $normalizedText["offense"]
        ]);

        $recordId = (int) $pdo->lastInsertId();
        $identificationNumber = buildMonitoringIdentificationNumber($company, $recordId);

        if ($incidentReportUpload !== null) {
            $incidentReportDirectory = ensureMonitoringIncidentReportDirectory($company);
            $relativeDirectory = getMonitoringIncidentReportRelativeDirectory($company);
            $filename = str_replace("/", "-", $identificationNumber) . "-" . date("Ymd_His") . "." . $incidentReportUpload["extension"];
            $storedIncidentReportAbsolutePath = $incidentReportDirectory . DIRECTORY_SEPARATOR . $filename;
            $storedIncidentReportRelativePath = $relativeDirectory . "/" . $filename;

            if (!move_uploaded_file($incidentReportUpload["temporary_path"], $storedIncidentReportAbsolutePath)) {
                throw new RuntimeException("incident_image_storage_failed");
            }
        }

        $updateStmt = $pdo->prepare(
            "UPDATE {$tableNameSql}
             SET identification_number = :identification_number,
                 incident_report_image_path = :incident_report_image_path
             WHERE id = :id"
        );
        $updateStmt->bindValue(":identification_number", $identificationNumber, PDO::PARAM_STR);
        if ($storedIncidentReportRelativePath === null) {
            $updateStmt->bindValue(":incident_report_image_path", null, PDO::PARAM_NULL);
        } else {
            $updateStmt->bindValue(":incident_report_image_path", $storedIncidentReportRelativePath, PDO::PARAM_STR);
        }
        $updateStmt->bindValue(":id", $recordId, PDO::PARAM_INT);
        $updateStmt->execute();
    }

    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($storedIncidentReportAbsolutePath !== null && is_file($storedIncidentReportAbsolutePath)) {
        @unlink($storedIncidentReportAbsolutePath);
    }

    $errorCode = match ($error->getMessage()) {
        "incident_image_storage_failed" => "incident_image_storage_failed",
        default => $isEditingRecord ? "record_update_failed" : "record_save_failed",
    };

    if ($isEditingRecord) {
        redirectToMonitoringRecordWithError($company, $identificationNumber, $errorCode);
    }

    redirectToMonitoringFormWithError($company, $errorCode);
}

if ($isEditingRecord) {
    $redirectQuery = http_build_query([
        "company" => $company["key"],
        "identification_number" => $identificationNumber,
        "updated" => 1,
    ]);

    header("Location: monitoring_record.php?" . $redirectQuery);
    exit;
}

$redirectQuery = http_build_query([
    "company" => $company["key"],
    "saved" => 1,
    "identification_number" => $identificationNumber,
]);

header("Location: index.php?" . $redirectQuery);
exit;
?>
