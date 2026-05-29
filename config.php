<?php
function readApplicationEnvironmentMarker(string $appRoot): string
{
    $markerPath = $appRoot . DIRECTORY_SEPARATOR . ".app-env";
    if (!is_file($markerPath)) {
        return "";
    }

    $markerValue = trim((string) file_get_contents($markerPath));
    return in_array($markerValue, ["live", "test"], true) ? $markerValue : "";
}

function detectApplicationEnvironment(string $appRoot): string
{
    $markerEnvironment = readApplicationEnvironmentMarker($appRoot);
    if ($markerEnvironment !== "") {
        return $markerEnvironment;
    }

    $folderName = strtolower(basename($appRoot));
    return str_ends_with($folderName, "_test") ? "test" : "live";
}

function buildApplicationDatabaseName(string $baseDatabaseName, string $environmentName): string
{
    if ($environmentName !== "test") {
        return $baseDatabaseName;
    }

    return str_ends_with($baseDatabaseName, "_db")
        ? substr($baseDatabaseName, 0, -3) . "_test_db"
        : $baseDatabaseName . "_test";
}

function getApplicationEnvironmentLabel(string $environmentName): string
{
    return $environmentName === "test" ? "Test Server" : "Live Server";
}

function getApplicationEnvironmentName(): string
{
    global $appEnvironmentName;
    return (string) $appEnvironmentName;
}

function getApplicationEnvironmentDisplayLabel(): string
{
    global $appEnvironmentLabel;
    return (string) $appEnvironmentLabel;
}

function isApplicationTestEnvironment(): bool
{
    return getApplicationEnvironmentName() === "test";
}

function isLocalWebRequest(): bool
{
    $remoteAddress = trim((string) ($_SERVER["REMOTE_ADDR"] ?? ""));
    return in_array($remoteAddress, ["127.0.0.1", "::1", "::ffff:127.0.0.1"], true);
}

function canAccessPromoteToLiveUi(): bool
{
    return isApplicationTestEnvironment() && isLocalWebRequest();
}

// Database settings
// For XAMPP default setup, username is usually "root" and password is usually blank.
$appEnvironmentName = detectApplicationEnvironment(__DIR__);
$appEnvironmentLabel = getApplicationEnvironmentLabel($appEnvironmentName);
$host = "localhost";
$baseDbname = "system_monitoring_db";
$dbname = buildApplicationDatabaseName($baseDbname, $appEnvironmentName);
$username = "chel";
$password = "Wysiwyg1721!";

// Excel export helper Python executable.
// Using the absolute path avoids PATH differences between the terminal and Apache/PHP.
$pythonCommand = "C:\\Users\\IT TechSupport 3\\AppData\\Local\\Python\\pythoncore-3.14-64\\python.exe";

$companyConfigs = [
    "mitsubishi" => [
        "key" => "mitsubishi",
        "company_name" => "Mitsubishi",
        "system_name" => "MICEI System Monitoring",
        "has_ticket_monitoring" => true,
        "table_name" => "micei_system_monitoring",
        "ticket_table_name" => "micei_ticket_monitoring",
        "legacy_table_names" => ["MICEI system monitoring", "micei system monitoring"],
        "legacy_ticket_table_names" => ["MICEI ticket monitoring", "micei ticket monitoring"],
        "legacy_resolved_ticket_table_names" => ["MICEI resolved ticket monitoring", "micei resolved ticket monitoring"],
        "logo_path" => "assets/images/mitsubishi-logo.png",
        "logo_type" => "image/png",
        "logo_alt" => "Mitsubishi Motors Drive your Ambition",
        "export_slug" => "micei",
    ],
    "hyundai" => [
        "key" => "hyundai",
        "company_name" => "Hyundai",
        "system_name" => "NTR System Monitoring",
        "has_ticket_monitoring" => true,
        "table_name" => "ntr_system_monitoring",
        "ticket_table_name" => "ntr_ticket_monitoring",
        "legacy_table_names" => ["NTR system monitoring", "ntr system monitoring"],
        "legacy_ticket_table_names" => ["NTR ticket monitoring", "ntr ticket monitoring"],
        "legacy_resolved_ticket_table_names" => ["NTR resolved ticket monitoring", "ntr resolved ticket monitoring"],
        "logo_path" => "assets/images/hyundai_logo.png",
        "logo_type" => "image/png",
        "logo_alt" => "Hyundai Company",
        "export_slug" => "ntr",
    ],
];

function resolveCompanyConfig(?string $companyKey, array $configs): array
{
    $normalizedKey = strtolower(trim((string) $companyKey));
    return $configs[$normalizedKey] ?? $configs["mitsubishi"];
}

function companySupportsTicketMonitoring(array $company): bool
{
    return (bool) ($company["has_ticket_monitoring"] ?? false);
}

function buildMonitoringIdentificationNumber(array $company, int $recordId): string
{
    return str_pad((string) max(0, $recordId), 6, "0", STR_PAD_LEFT);
}

function getNextMonitoringRecordId(PDO $pdo, array $company): int
{
    $tableName = trim((string) ($company["table_name"] ?? ""));
    if ($tableName === "") {
        return 1;
    }

    $autoIncrementStmt = $pdo->prepare(
        "SELECT AUTO_INCREMENT
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name"
    );
    $autoIncrementStmt->execute([
        ":table_name" => $tableName,
    ]);

    $nextRecordId = (int) $autoIncrementStmt->fetchColumn();
    if ($nextRecordId > 0) {
        return $nextRecordId;
    }

    $tableNameSql = quoteMysqlIdentifier($tableName);
    $maxRecordId = (int) $pdo->query("SELECT MAX(id) FROM {$tableNameSql}")->fetchColumn();
    return max(1, $maxRecordId + 1);
}

function getNextMonitoringIdentificationNumber(PDO $pdo, array $company): string
{
    return buildMonitoringIdentificationNumber($company, getNextMonitoringRecordId($pdo, $company));
}

function getMonitoringIncidentReportRelativeDirectory(array $company): string
{
    $companyKey = strtolower(trim((string) ($company["key"] ?? "default")));
    $companyKey = preg_replace('/[^a-z0-9_-]+/', '_', $companyKey) ?? "default";
    $companyKey = $companyKey !== "" ? $companyKey : "default";

    return "uploads/incident_reports/" . $companyKey;
}

function ensureMonitoringIncidentReportDirectory(array $company): string
{
    $relativeDirectory = str_replace(["/", "\\"], DIRECTORY_SEPARATOR, getMonitoringIncidentReportRelativeDirectory($company));
    $absoluteDirectory = __DIR__ . DIRECTORY_SEPARATOR . $relativeDirectory;

    if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0777, true) && !is_dir($absoluteDirectory)) {
        throw new RuntimeException("incident_image_storage_failed");
    }

    return $absoluteDirectory;
}

function getMonitoringStoredFileAbsolutePath(string $relativePath): string
{
    $normalizedRelativePath = ltrim(str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);
    return __DIR__ . DIRECTORY_SEPARATOR . $normalizedRelativePath;
}

function buildVersionedAssetPath(string $relativePath): string
{
    $normalizedRelativePath = ltrim(str_replace("\\", "/", $relativePath), "/");
    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $normalizedRelativePath);
    $version = is_file($absolutePath) ? (string) filemtime($absolutePath) : "1";

    return $normalizedRelativePath . "?v=" . rawurlencode($version);
}

function quoteMysqlIdentifier(string $identifier): string
{
    return "`" . str_replace("`", "``", $identifier) . "`";
}

function mysqlTableExists(PDO $pdo, string $tableName): bool
{
    $stmt = $pdo->prepare("SHOW TABLES LIKE :table_name");
    $stmt->execute([":table_name" => $tableName]);

    return $stmt->fetchColumn() !== false;
}

function normalizeLegacyTableNames($legacyTableNames): array
{
    if (is_string($legacyTableNames)) {
        $legacyTableNames = [$legacyTableNames];
    }

    if (!is_array($legacyTableNames)) {
        return [];
    }

    $normalized = [];
    foreach ($legacyTableNames as $legacyTableName) {
        if (!is_string($legacyTableName)) {
            continue;
        }

        $legacyTableName = trim($legacyTableName);
        if ($legacyTableName === "" || in_array($legacyTableName, $normalized, true)) {
            continue;
        }

        $normalized[] = $legacyTableName;
    }

    return $normalized;
}

function countMysqlTableRows(PDO $pdo, string $tableName): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM " . quoteMysqlIdentifier($tableName))->fetchColumn();
}

function fetchMysqlTableColumnNames(PDO $pdo, string $tableName): array
{
    $stmt = $pdo->query("SHOW COLUMNS FROM " . quoteMysqlIdentifier($tableName));
    $columns = [];

    while ($column = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!isset($column["Field"])) {
            continue;
        }

        $columns[] = (string) $column["Field"];
    }

    return $columns;
}

function renameLegacyTableIfNeeded(PDO $pdo, $legacyTableNames, string $targetTableName): void
{
    foreach (normalizeLegacyTableNames($legacyTableNames) as $legacyTableName) {
        if ($legacyTableName === $targetTableName) {
            continue;
        }

        if (mysqlTableExists($pdo, $targetTableName) || !mysqlTableExists($pdo, $legacyTableName)) {
            continue;
        }

        $pdo->exec(
            "RENAME TABLE " . quoteMysqlIdentifier($legacyTableName)
            . " TO " . quoteMysqlIdentifier($targetTableName)
        );
        return;
    }
}

function syncLegacyTableIntoTargetIfNeeded(PDO $pdo, $legacyTableNames, string $targetTableName): void
{
    if (!mysqlTableExists($pdo, $targetTableName)) {
        return;
    }

    $targetRowCount = countMysqlTableRows($pdo, $targetTableName);

    foreach (normalizeLegacyTableNames($legacyTableNames) as $legacyTableName) {
        if ($legacyTableName === $targetTableName || !mysqlTableExists($pdo, $legacyTableName)) {
            continue;
        }

        $legacyRowCount = countMysqlTableRows($pdo, $legacyTableName);
        if ($legacyRowCount === 0) {
            $pdo->exec("DROP TABLE " . quoteMysqlIdentifier($legacyTableName));
            continue;
        }

        if ($targetRowCount === 0) {
            $targetColumns = fetchMysqlTableColumnNames($pdo, $targetTableName);
            $legacyColumns = fetchMysqlTableColumnNames($pdo, $legacyTableName);
            $sharedColumns = array_values(array_intersect($targetColumns, $legacyColumns));

            if ($sharedColumns === []) {
                continue;
            }

            $columnListSql = implode(
                ", ",
                array_map(
                    static fn(string $columnName): string => quoteMysqlIdentifier($columnName),
                    $sharedColumns
                )
            );

            $pdo->exec(
                "INSERT INTO " . quoteMysqlIdentifier($targetTableName)
                . " (" . $columnListSql . ") SELECT " . $columnListSql
                . " FROM " . quoteMysqlIdentifier($legacyTableName)
            );
            $pdo->exec("DROP TABLE " . quoteMysqlIdentifier($legacyTableName));
            $targetRowCount = $legacyRowCount;
        }
    }
}

function ensureMonitoringTable(PDO $pdo, array $company): void
{
    if (!isset($company["table_name"]) || !is_string($company["table_name"]) || trim($company["table_name"]) === "") {
        throw new RuntimeException("System monitoring table is not configured for this company.");
    }

    renameLegacyTableIfNeeded($pdo, $company["legacy_table_names"] ?? [], $company["table_name"]);
    $tableNameSql = quoteMysqlIdentifier($company["table_name"]);
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS {$tableNameSql} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            identification_number VARCHAR(100),
            date_recorded DATE NOT NULL,
            transaction_date DATE NOT NULL,
            branch VARCHAR(100),
            dealer VARCHAR(100),
            department VARCHAR(100),
            module VARCHAR(100),
            user_name VARCHAR(150),
            invoice_reference VARCHAR(150),
            payment_reference VARCHAR(150),
            client_name VARCHAR(200),
            amount DECIMAL(15,2) NULL,
            reason TEXT,
            approved_by VARCHAR(150),
            processed_type VARCHAR(100),
            processed_by VARCHAR(150),
            remarks TEXT,
            incident_report_image_path VARCHAR(255),
            classification VARCHAR(100),
            system_admin VARCHAR(150),
            ticket VARCHAR(150),
            status VARCHAR(100),
            offense VARCHAR(150),
            disciplinary_action VARCHAR(100),
            action_taken VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    );
    ensureMysqlTableColumn($pdo, $tableNameSql, "identification_number", "identification_number VARCHAR(100) AFTER id");
    ensureMysqlTableColumn($pdo, $tableNameSql, "dealer", "dealer VARCHAR(100) AFTER branch");
    ensureMysqlTableColumn($pdo, $tableNameSql, "incident_report_image_path", "incident_report_image_path VARCHAR(255) NULL AFTER remarks");
    ensureMysqlTableColumn($pdo, $tableNameSql, "disciplinary_action", "disciplinary_action VARCHAR(100) AFTER offense");
    ensureMysqlTableColumn($pdo, $tableNameSql, "action_taken", "action_taken VARCHAR(100) AFTER offense");
    syncLegacyTableIntoTargetIfNeeded($pdo, $company["legacy_table_names"] ?? [], $company["table_name"]);
    backfillMonitoringDealerValues($pdo, $tableNameSql);
    backfillMonitoringModuleValues($pdo, $tableNameSql);
    backfillMonitoringIdentificationNumbers($pdo, $company, $tableNameSql);
    backfillMonitoringDisciplinaryActions($pdo, $tableNameSql);
    backfillMonitoringOffenseMemoValues($pdo, $tableNameSql);
}

function backfillMonitoringDealerValues(PDO $pdo, string $tableNameSql): void
{
    $pdo->exec(
        "UPDATE {$tableNameSql}
         SET dealer = UPPER(TRIM(branch)),
             branch = 'GSC'
         WHERE COALESCE(TRIM(dealer), '') = ''
           AND UPPER(TRIM(branch)) IN ('MGSC', 'NGSC', 'MKC')"
    );
}

function backfillMonitoringModuleValues(PDO $pdo, string $tableNameSql): void
{
    $pdo->exec(
        "UPDATE {$tableNameSql}
         SET module = 'All Modules'
         WHERE module IS NULL
            OR TRIM(module) = ''
            OR UPPER(TRIM(module)) IN ('ALL MODULE', 'ALL MODULES')"
    );
}

function backfillMonitoringIdentificationNumbers(PDO $pdo, array $company, string $tableNameSql): void
{
    $selectStmt = $pdo->query(
        "SELECT id, identification_number
         FROM {$tableNameSql}
         ORDER BY id ASC"
    );
    $updateStmt = $pdo->prepare(
        "UPDATE {$tableNameSql}
         SET identification_number = :identification_number
         WHERE id = :id"
    );

    foreach ($selectStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $recordId = (int) ($row["id"] ?? 0);
        if ($recordId <= 0) {
            continue;
        }

        $expectedIdentificationNumber = buildMonitoringIdentificationNumber($company, $recordId);
        $currentIdentificationNumber = trim((string) ($row["identification_number"] ?? ""));

        if ($currentIdentificationNumber === $expectedIdentificationNumber) {
            continue;
        }

        $updateStmt->execute([
            ":identification_number" => $expectedIdentificationNumber,
            ":id" => $recordId,
        ]);
    }
}

function backfillMonitoringDisciplinaryActions(PDO $pdo, string $tableNameSql): void
{
    $pdo->exec(
        "UPDATE {$tableNameSql}
         SET disciplinary_action = action_taken
         WHERE COALESCE(TRIM(disciplinary_action), '') = ''
           AND COALESCE(TRIM(action_taken), '') <> ''"
    );
}

function backfillMonitoringOffenseMemoValues(PDO $pdo, string $tableNameSql): void
{
    $pdo->exec(
        "UPDATE {$tableNameSql}
         SET offense = disciplinary_action
         WHERE COALESCE(TRIM(disciplinary_action), '') <> ''
           AND COALESCE(TRIM(offense), '') = ''"
    );
}

function ensureMysqlTableColumn(PDO $pdo, string $tableNameSql, string $columnName, string $definitionSql): void
{
    $columnNameSql = $pdo->quote($columnName);
    $columnExists = (bool) $pdo->query("SHOW COLUMNS FROM {$tableNameSql} LIKE {$columnNameSql}")->fetch(PDO::FETCH_ASSOC);

    if (!$columnExists) {
        $pdo->exec("ALTER TABLE {$tableNameSql} ADD COLUMN {$definitionSql}");
    }
}

function ensureTicketMonitoringTable(PDO $pdo, array $company): void
{
    if (!companySupportsTicketMonitoring($company)) {
        throw new RuntimeException("Ticket monitoring is not enabled for this company.");
    }

    if (!isset($company["ticket_table_name"]) || !is_string($company["ticket_table_name"]) || trim($company["ticket_table_name"]) === "") {
        throw new RuntimeException("Ticket monitoring table is not configured for this company.");
    }

    renameLegacyTableIfNeeded($pdo, $company["legacy_ticket_table_names"] ?? [], $company["ticket_table_name"]);
    $tableNameSql = quoteMysqlIdentifier($company["ticket_table_name"]);
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS {$tableNameSql} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            branch VARCHAR(100),
            dealer VARCHAR(100),
            module VARCHAR(100),
            ticket_number VARCHAR(150) NOT NULL,
            ticket_description TEXT,
            date_created DATE NOT NULL,
            created_by VARCHAR(150),
            ticket_status VARCHAR(100) NOT NULL,
            resolved_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    );

    ensureMysqlTableColumn($pdo, $tableNameSql, "dealer", "dealer VARCHAR(100) AFTER branch");
    ensureMysqlTableColumn($pdo, $tableNameSql, "module", "module VARCHAR(100) AFTER dealer");
    syncLegacyTableIntoTargetIfNeeded($pdo, $company["legacy_ticket_table_names"] ?? [], $company["ticket_table_name"]);
    backfillTicketMonitoringDealerValues($pdo, $tableNameSql);
    backfillTicketMonitoringModuleValues($pdo, $tableNameSql);
}

function backfillTicketMonitoringDealerValues(PDO $pdo, string $tableNameSql): void
{
    $pdo->exec(
        "UPDATE {$tableNameSql}
         SET dealer = UPPER(TRIM(branch)),
             branch = 'GSC'
         WHERE COALESCE(TRIM(dealer), '') = ''
           AND UPPER(TRIM(branch)) IN ('MGSC', 'NGSC', 'MKC')"
    );
}

function backfillTicketMonitoringModuleValues(PDO $pdo, string $tableNameSql): void
{
    $pdo->exec(
        "UPDATE {$tableNameSql}
         SET module = 'All Modules'
         WHERE module IS NULL
            OR TRIM(module) = ''
            OR UPPER(TRIM(module)) IN ('ALL MODULE', 'ALL MODULES')"
    );
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
