<?php
require dirname(__DIR__) . "/config.php";

foreach ($companyConfigs as $company) {
    ensureMonitoringTable($pdo, $company);

    if (companySupportsTicketMonitoring($company)) {
        ensureTicketMonitoringTable($pdo, $company);
    }
}

echo "Environment: " . getApplicationEnvironmentName() . PHP_EOL;
echo "Database: " . $dbname . PHP_EOL;
echo "Schema sync complete." . PHP_EOL;
