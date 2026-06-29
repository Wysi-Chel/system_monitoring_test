<?php
function normalizeSearchFilter($value): string
{
    $value = trim((string) $value);
    if ($value === "") {
        return "";
    }

    return preg_replace('/\s+/u', ' ', $value) ?? $value;
}

function normalizeIdentificationNumberFilter($value): string
{
    $value = trim((string) $value);
    if ($value === "") {
        return "";
    }

    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return function_exists("mb_strtoupper")
        ? mb_strtoupper($value, "UTF-8")
        : strtoupper($value);
}

function normalizeDateFilter($value): string
{
    $value = trim((string) $value);
    if ($value === "") {
        return "";
    }

    $date = DateTimeImmutable::createFromFormat("Y-m-d", $value);
    return $date && $date->format("Y-m-d") === $value ? $value : "";
}

function normalizeMonthFilter($value): string
{
    $value = trim((string) $value);
    if ($value === "") {
        return "";
    }

    $month = DateTimeImmutable::createFromFormat("Y-m", $value);
    return $month && $month->format("Y-m") === $value ? $value : "";
}


function normalizeAllowedFilter($value, array $allowedOptions): string
{
    $value = trim((string) $value);
    return in_array($value, $allowedOptions, true) ? $value : "";
}

function normalizePositiveInt($value, int $default): int
{
    if (is_numeric($value) && (int) $value > 0) {
        return (int) $value;
    }

    return $default;
}

function normalizeBooleanFilter($value): bool
{
    return in_array((string) $value, ["1", "true", "yes", "on"], true);
}

function escapeLikeTerm(string $value): string
{
    return str_replace(["\\", "%", "_"], ["\\\\", "\\%", "\\_"], $value);
}

function buildMonitoringFilters(array $input, array $company, array $filterOptions): array
{
    $dateFrom = normalizeDateFilter($input["date_from"] ?? "");
    $dateTo = normalizeDateFilter($input["date_to"] ?? "");
    $allowedPerPage = $filterOptions["per_page"] ?? [];
    $defaultPerPage = $allowedPerPage[0] ?? 25;

    if ($dateFrom !== "" && $dateTo !== "" && $dateFrom > $dateTo) {
        [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
    }

    $filters = [
        "search" => "",
        "identification_number" => normalizeIdentificationNumberFilter($input["id_number"] ?? ""),
        "user_name" => normalizeSearchFilter($input["user"] ?? $input["user_name"] ?? ""),
        "month" => normalizeMonthFilter($input["month"] ?? ""),
        "day" => normalizeDateFilter($input["day"] ?? ""),
        "date_from" => "",
        "date_to" => "",
        "branch" => "",
        "dealer" => normalizeAllowedFilter($input["dealer"] ?? "", $filterOptions["dealer"] ?? []),
        "department" => "",
        "module" => "",
        "status" => normalizeAllowedFilter($input["status"] ?? "", $filterOptions["status"] ?? []),
        "disciplinary_action" => normalizeAllowedFilter($input["action"] ?? $input["disciplinary_action"] ?? "", $filterOptions["action"] ?? []),
        "data_correction_only" => normalizeBooleanFilter($input["data_correction"] ?? ""),
        "escalation_only" => normalizeBooleanFilter($input["escalation"] ?? ""),
        "page" => normalizePositiveInt($input["page"] ?? 1, 1),
        "per_page" => normalizePositiveInt($input["per_page"] ?? $defaultPerPage, $defaultPerPage),
    ];

    if (($company["fixed_branch"] ?? null) === null) {
        $filters["branch"] = normalizeAllowedFilter($input["branch"] ?? "", $filterOptions["branch"] ?? []);
    }

    if (!in_array($filters["per_page"], $allowedPerPage, true)) {
        $filters["per_page"] = $defaultPerPage;
    }

    return $filters;
}

function buildTicketMonitoringFilters(array $input, array $company, array $filterOptions): array
{
    $filters = [
        "search" => normalizeSearchFilter($input["q"] ?? ""),
        "branch" => "",
        "dealer" => normalizeAllowedFilter($input["dealer"] ?? "", $filterOptions["dealer"] ?? []),
        "ticket_status" => normalizeAllowedFilter($input["status"] ?? "", $filterOptions["status"] ?? []),
        "page" => normalizePositiveInt($input["page"] ?? 1, 1),
        "per_page" => normalizePositiveInt($input["per_page"] ?? 25, 25),
    ];

    if (($company["fixed_branch"] ?? null) === null) {
        $filters["branch"] = normalizeAllowedFilter($input["branch"] ?? "", $filterOptions["branch"] ?? []);
    }

    if (!in_array($filters["per_page"], $filterOptions["per_page"] ?? [25], true)) {
        $filters["per_page"] = 25;
    }

    return $filters;
}

function buildMonitoringWhereClause(array $filters, array &$bindings): string
{
    $conditions = [];
    $bindings = [];

    if ($filters["search"] !== "") {
        $searchValue = "%" . escapeLikeTerm($filters["search"]) . "%";
        $searchColumns = [
            "branch",
            "dealer",
            "department",
            "module",
            "identification_number",
            "user_name",
            "invoice_reference",
            "payment_reference",
            "client_name",
            "CAST(amount AS CHAR)",
            "reason",
            "approved_by",
            "processed_type",
        "processed_by",
        "remarks",
        "disciplinary_action",
        "classification",
        "system_admin",
        "ticket",
            "status",
            "offense",
            "DATE_FORMAT(date_recorded, '%Y-%m-%d')",
            "DATE_FORMAT(transaction_date, '%Y-%m-%d')",
            "DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s')",
        ];

        $searchParts = [];
        foreach ($searchColumns as $index => $columnExpression) {
            $paramKey = "search_" . $index;
            $searchParts[] = $columnExpression . " LIKE :" . $paramKey . " ESCAPE '\\\\'";
            $bindings[$paramKey] = $searchValue;
        }

        $conditions[] = "(" . implode(" OR ", $searchParts) . ")";
    }

    if (($filters["identification_number"] ?? "") !== "") {
        $conditions[] = "identification_number LIKE :identification_number ESCAPE '\\\\'";
        $bindings["identification_number"] = "%" . escapeLikeTerm($filters["identification_number"]) . "%";
    }

    if (($filters["user_name"] ?? "") !== "") {
        $conditions[] = "UPPER(TRIM(COALESCE(user_name, ''))) LIKE :user_name ESCAPE '\\\\'";
        $bindings["user_name"] = "%" . escapeLikeTerm(uppercaseText($filters["user_name"])) . "%";
    }

    if (($filters["month"] ?? "") !== "") {
        $monthStart = DateTimeImmutable::createFromFormat("Y-m-d", $filters["month"] . "-01");
        if ($monthStart instanceof DateTimeImmutable) {
            $monthEnd = $monthStart->modify("first day of next month");
            $conditions[] = "date_recorded >= :month_start AND date_recorded < :month_end";
            $bindings["month_start"] = $monthStart->format("Y-m-d");
            $bindings["month_end"] = $monthEnd->format("Y-m-d");
        }
    }

    if (($filters["day"] ?? "") !== "") {
        $conditions[] = "date_recorded = :day";
        $bindings["day"] = $filters["day"];
    }

    if ($filters["date_from"] !== "") {
        $conditions[] = "date_recorded >= :date_from";
        $bindings["date_from"] = $filters["date_from"];
    }

    if ($filters["date_to"] !== "") {
        $conditions[] = "date_recorded <= :date_to";
        $bindings["date_to"] = $filters["date_to"];
    }

    if ($filters["branch"] !== "") {
        $conditions[] = "branch = :branch";
        $bindings["branch"] = $filters["branch"];
    }

    if (($filters["dealer"] ?? "") !== "") {
        $conditions[] = "dealer = :dealer";
        $bindings["dealer"] = $filters["dealer"];
    }

    if ($filters["department"] !== "") {
        $conditions[] = "department = :department";
        $bindings["department"] = $filters["department"];
    }

    if ($filters["module"] !== "") {
        $conditions[] = "module = :module";
        $bindings["module"] = $filters["module"];
    }

    if ($filters["status"] !== "") {
        $conditions[] = "CONCAT(',', REPLACE(COALESCE(status, ''), ', ', ','), ',') LIKE :status ESCAPE '\\\\'";
        $bindings["status"] = "%," . escapeLikeTerm($filters["status"]) . ",%";
    }

    if (!empty($filters["data_correction_only"])) {
        $conditions[] = "UPPER(TRIM(COALESCE(classification, ''))) = :data_correction";
        $bindings["data_correction"] = uppercaseText("User Error");
    }

    return $conditions === [] ? "" : " WHERE " . implode(" AND ", $conditions);
}

function buildTicketMonitoringWhereClause(array $filters, array &$bindings): string
{
    $conditions = [
        "COALESCE(TRIM(ticket_number), '') <> ''",
    ];
    $bindings = [];

    if (($filters["search"] ?? "") !== "") {
        $searchValue = "%" . escapeLikeTerm($filters["search"]) . "%";
        $searchColumns = [
            "ticket_number",
            "dealer",
            "module",
            "ticket_description",
            "created_by",
            "ticket_status",
        ];

        $searchParts = [];
        foreach ($searchColumns as $index => $columnExpression) {
            $paramKey = "ticket_search_" . $index;
            $searchParts[] = $columnExpression . " LIKE :" . $paramKey . " ESCAPE '\\\\'";
            $bindings[$paramKey] = $searchValue;
        }

        $conditions[] = "(" . implode(" OR ", $searchParts) . ")";
    }

    if (($filters["branch"] ?? "") !== "") {
        $conditions[] = "branch = :branch";
        $bindings["branch"] = $filters["branch"];
    }

    if (($filters["dealer"] ?? "") !== "") {
        $conditions[] = "dealer = :dealer";
        $bindings["dealer"] = $filters["dealer"];
    }

    if (($filters["ticket_status"] ?? "") !== "") {
        $conditions[] = "ticket_status = :ticket_status";
        $bindings["ticket_status"] = $filters["ticket_status"];
    }

    return " WHERE " . implode(" AND ", $conditions);
}

function countMonitoringRecords(PDO $pdo, string $tableNameSql, array $filters): int
{
    $bindings = [];
    $whereClause = buildMonitoringWhereClause($filters, $bindings);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$tableNameSql}{$whereClause}");

    foreach ($bindings as $key => $value) {
        $stmt->bindValue(":" . $key, $value, PDO::PARAM_STR);
    }

    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

function fetchMonitoringRecords(
    PDO $pdo,
    string $tableNameSql,
    array $filters,
    ?int $limit = null,
    int $offset = 0,
    string $orderDirection = "DESC"
): array {
    $bindings = [];
    $whereClause = buildMonitoringWhereClause($filters, $bindings);
    $direction = strtoupper($orderDirection) === "ASC" ? "ASC" : "DESC";

    $sql = "SELECT * FROM {$tableNameSql}{$whereClause} ORDER BY id {$direction}";
    if ($limit !== null) {
        $sql .= " LIMIT :limit OFFSET :offset";
    }

    $stmt = $pdo->prepare($sql);

    foreach ($bindings as $key => $value) {
        $stmt->bindValue(":" . $key, $value, PDO::PARAM_STR);
    }

    if ($limit !== null) {
        $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindValue(":offset", max(0, $offset), PDO::PARAM_INT);
    }

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function filterMonitoringRecordsByDisciplinaryAction(array $records, array $filters): array
{
    $selectedAction = trim((string) ($filters["disciplinary_action"] ?? ""));
    if ($selectedAction === "") {
        return $records;
    }

    $selectedActionKey = uppercaseText($selectedAction);
    $legacyVerbalMemoKey = uppercaseText("Vocal Memo");

    return array_values(array_filter(
        $records,
        static function (array $row) use ($selectedActionKey, $legacyVerbalMemoKey): bool {
            $actionValues = [
                trim((string) ($row["disciplinary_action"] ?? "")),
                trim((string) ($row["action_taken"] ?? "")),
                trim((string) ($row["offense"] ?? "")),
            ];

            foreach ($actionValues as $actionValue) {
                if ($actionValue === "") {
                    continue;
                }

                $actionKey = uppercaseText($actionValue);
                if (
                    $actionKey === $selectedActionKey
                    || ($selectedActionKey === uppercaseText("Verbal Memo") && $actionKey === $legacyVerbalMemoKey)
                ) {
                    return true;
                }
            }

            return false;
        }
    ));
}

function fetchDataCorrectionOffenseStatesByRecordId(PDO $pdo, string $tableNameSql, array $userNames): array
{
    $normalizedUserNames = [];

    foreach ($userNames as $userName) {
        $normalizedUserName = strtoupper(trim((string) $userName));
        if ($normalizedUserName === "" || in_array($normalizedUserName, $normalizedUserNames, true)) {
            continue;
        }

        $normalizedUserNames[] = $normalizedUserName;
    }

    if ($normalizedUserNames === []) {
        return [];
    }

    $placeholders = [];
    $bindings = [
        ":classification" => uppercaseText("User Error"),
    ];

    foreach ($normalizedUserNames as $index => $userName) {
        $placeholder = ":user_name_" . $index;
        $placeholders[] = $placeholder;
        $bindings[$placeholder] = $userName;
    }

    $sql = "SELECT id,
                   UPPER(TRIM(user_name)) AS user_key,
                   disciplinary_action,
                   action_taken,
                   offense
            FROM {$tableNameSql}
            WHERE COALESCE(TRIM(user_name), '') <> ''
              AND UPPER(TRIM(COALESCE(classification, ''))) = :classification
              AND UPPER(TRIM(user_name)) IN (" . implode(", ", $placeholders) . ")
            ORDER BY UPPER(TRIM(user_name)) ASC, id ASC";

    $stmt = $pdo->prepare($sql);
    foreach ($bindings as $placeholder => $value) {
        $stmt->bindValue($placeholder, $value, PDO::PARAM_STR);
    }

    $stmt->execute();
    $offenseStatesByRecordId = [];
    $offenseCountsByUser = [];
    $issuedActionsByUser = [];
    $actionRanks = [
        "Verbal Memo" => 1,
        "Written Memo" => 2,
        "Final Memo" => 3,
    ];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $userKey = trim((string) ($row["user_key"] ?? ""));
        $recordId = (int) ($row["id"] ?? 0);
        if ($userKey === "" || $recordId <= 0) {
            continue;
        }

        $offenseCountsByUser[$userKey] = ($offenseCountsByUser[$userKey] ?? 0) + 1;
        $currentIssuedAction = getIssuedMonitoringMemoAction($row);
        $cycleIssuedAction = $issuedActionsByUser[$userKey] ?? "";

        $offenseStatesByRecordId[$recordId] = [
            "count" => $offenseCountsByUser[$userKey],
            "memo_cycle_issued_action" => $cycleIssuedAction,
        ];

        if (
            $currentIssuedAction !== ""
            && ($actionRanks[$currentIssuedAction] ?? 0) > ($actionRanks[$cycleIssuedAction] ?? 0)
        ) {
            $issuedActionsByUser[$userKey] = $currentIssuedAction;
        }

        if ($currentIssuedAction === "Final Memo") {
            $offenseCountsByUser[$userKey] = 0;
            $issuedActionsByUser[$userKey] = "";
        }
    }

    return $offenseStatesByRecordId;
}

function fetchDataCorrectionOffenseNumbersByRecordId(PDO $pdo, string $tableNameSql, array $userNames): array
{
    $offenseStatesByRecordId = fetchDataCorrectionOffenseStatesByRecordId($pdo, $tableNameSql, $userNames);
    $offenseNumbersByRecordId = [];

    foreach ($offenseStatesByRecordId as $recordId => $state) {
        $offenseNumbersByRecordId[$recordId] = (int) ($state["count"] ?? 0);
    }

    return $offenseNumbersByRecordId;
}

function fetchMonitoringUserNameSuggestions(PDO $pdo, string $tableNameSql, int $limit = 100): array
{
    $stmt = $pdo->prepare(
        "SELECT DISTINCT TRIM(user_name) AS user_name
         FROM {$tableNameSql}
         WHERE COALESCE(TRIM(user_name), '') <> ''
         ORDER BY TRIM(user_name) ASC
         LIMIT :limit"
    );
    $stmt->bindValue(":limit", max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();

    $userNames = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $userName = trim((string) ($row["user_name"] ?? ""));
        if ($userName === "") {
            continue;
        }

        $userNames[uppercaseText($userName)] = $userName;
    }

    return array_values($userNames);
}

function enrichMonitoringRecordsWithDataCorrectionActions(PDO $pdo, string $tableNameSql, array $records): array
{
    if ($records === []) {
        return $records;
    }

    $userNames = array_map(
        static fn(array $row): string => (string) ($row["user_name"] ?? ""),
        $records
    );
    $offenseStatesByRecordId = fetchDataCorrectionOffenseStatesByRecordId($pdo, $tableNameSql, $userNames);

    foreach ($records as &$row) {
        $row["data_correction_offense_count"] = 0;
        $row["data_correction_alert"] = "";
        $row["memo_cycle_issued_action"] = "";
        $row["disciplinary_action"] = trim((string) ($row["disciplinary_action"] ?? ""));

        if ($row["disciplinary_action"] === "") {
            $row["disciplinary_action"] = trim((string) ($row["action_taken"] ?? ""));
        }

        $row["issued_disciplinary_action"] = getIssuedMonitoringMemoAction($row);
        if ($row["disciplinary_action"] === "" && $row["issued_disciplinary_action"] !== "") {
            $row["disciplinary_action"] = $row["issued_disciplinary_action"];
        }

        if (uppercaseText(trim((string) ($row["classification"] ?? ""))) !== uppercaseText("User Error")) {
            continue;
        }

        $recordId = (int) ($row["id"] ?? 0);
        if ($recordId <= 0) {
            continue;
        }

        $offenseState = $offenseStatesByRecordId[$recordId] ?? [];
        $offenseCount = (int) ($offenseState["count"] ?? 0);
        if ($offenseCount <= 0) {
            continue;
        }

        $resolvedAction = resolveDataCorrectionDisciplinaryAction($offenseCount);
        $row["data_correction_offense_count"] = $offenseCount;
        $row["data_correction_alert"] = (string) ($resolvedAction["data_correction_alert"] ?? $offenseCount);
        $row["memo_cycle_issued_action"] = (string) ($offenseState["memo_cycle_issued_action"] ?? "");

        if ($row["disciplinary_action"] === "") {
            $suggestedAction = resolveSuggestedMonitoringMemoAction($row, $offenseCount);
            if ($suggestedAction !== "") {
                $row["disciplinary_action"] = $suggestedAction;
            }
        }
    }
    unset($row);

    return $records;
}

function fetchMonitoringRecordById(PDO $pdo, string $tableNameSql, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM {$tableNameSql} WHERE id = :id LIMIT 1");
    $stmt->bindValue(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    return $record === false ? null : $record;
}

function fetchMonitoringRecordByIdentificationNumber(PDO $pdo, string $tableNameSql, string $identificationNumber): ?array
{
    $stmt = $pdo->prepare(
        "SELECT *
         FROM {$tableNameSql}
         WHERE identification_number = :identification_number
         LIMIT 1"
    );
    $stmt->bindValue(":identification_number", normalizeIdentificationNumberFilter($identificationNumber), PDO::PARAM_STR);
    $stmt->execute();

    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    return $record === false ? null : $record;
}

function fetchMonitoringRecordsByUserName(
    PDO $pdo,
    string $tableNameSql,
    string $userName,
    string $orderDirection = "DESC"
): array {
    $normalizedUserName = trim($userName);
    if ($normalizedUserName === "") {
        return [];
    }

    $direction = strtoupper($orderDirection) === "ASC" ? "ASC" : "DESC";
    $stmt = $pdo->prepare(
        "SELECT *
         FROM {$tableNameSql}
         WHERE UPPER(TRIM(COALESCE(user_name, ''))) = :user_name
         ORDER BY transaction_date {$direction}, id {$direction}"
    );
    $stmt->bindValue(":user_name", uppercaseText($normalizedUserName), PDO::PARAM_STR);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function updateMonitoringRecordActionTaken(PDO $pdo, string $tableNameSql, int $id, string $actionTaken): void
{
    $stmt = $pdo->prepare(
        "UPDATE {$tableNameSql}
         SET disciplinary_action = :disciplinary_action,
             action_taken = :disciplinary_action,
             offense = :disciplinary_action
         WHERE id = :id"
    );
    $stmt->bindValue(":disciplinary_action", $actionTaken, PDO::PARAM_STR);
    $stmt->bindValue(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
}

function updateMonitoringRecordStatus(PDO $pdo, string $tableNameSql, int $id, string $status): void
{
    $stmt = $pdo->prepare(
        "UPDATE {$tableNameSql}
         SET status = :status
         WHERE id = :id"
    );
    $stmt->bindValue(":status", $status, PDO::PARAM_STR);
    $stmt->bindValue(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
}

function countTicketMonitoringRecords(PDO $pdo, string $tableNameSql, array $filters): int
{
    $bindings = [];
    $whereClause = buildTicketMonitoringWhereClause($filters, $bindings);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$tableNameSql}{$whereClause}");

    foreach ($bindings as $key => $value) {
        $stmt->bindValue(":" . $key, $value, PDO::PARAM_STR);
    }

    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

function fetchTicketMonitoringRecords(
    PDO $pdo,
    string $tableNameSql,
    array $filters,
    ?int $limit = null,
    int $offset = 0,
    string $orderDirection = "DESC"
): array {
    $bindings = [];
    $whereClause = buildTicketMonitoringWhereClause($filters, $bindings);
    $direction = strtoupper($orderDirection) === "ASC" ? "ASC" : "DESC";

    $sql = "SELECT * FROM {$tableNameSql}{$whereClause} ORDER BY id {$direction}";
    if ($limit !== null) {
        $sql .= " LIMIT :limit OFFSET :offset";
    }

    $stmt = $pdo->prepare($sql);

    foreach ($bindings as $key => $value) {
        $stmt->bindValue(":" . $key, $value, PDO::PARAM_STR);
    }

    if ($limit !== null) {
        $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindValue(":offset", max(0, $offset), PDO::PARAM_INT);
    }

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchTicketMonitoringRecordById(PDO $pdo, string $tableNameSql, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM {$tableNameSql} WHERE id = :id LIMIT 1");
    $stmt->bindValue(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    return $record === false ? null : $record;
}

function updateTicketMonitoringRecordStatus(PDO $pdo, string $tableNameSql, int $id, string $ticketStatus, ?string $resolvedAt): void
{
    $stmt = $pdo->prepare(
        "UPDATE {$tableNameSql}
         SET ticket_status = :ticket_status,
             resolved_at = :resolved_at
         WHERE id = :id"
    );

    $stmt->bindValue(":ticket_status", $ticketStatus, PDO::PARAM_STR);
    if ($resolvedAt === null) {
        $stmt->bindValue(":resolved_at", null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(":resolved_at", $resolvedAt, PDO::PARAM_STR);
    }
    $stmt->bindValue(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
}
