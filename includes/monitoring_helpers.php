<?php
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function uppercaseText(string $value): string
{
    return function_exists("mb_strtoupper")
        ? mb_strtoupper($value, "UTF-8")
        : strtoupper($value);
}

function containsMultiValueText(?string $value, string $target): bool
{
    $normalizedValue = trim((string) $value);
    $normalizedTarget = trim($target);
    if ($normalizedValue === "" || $normalizedTarget === "") {
        return false;
    }

    $targetKey = uppercaseText($normalizedTarget);
    $values = array_map(
        static fn(string $item): string => uppercaseText(trim($item)),
        explode(",", $normalizedValue)
    );

    return in_array($targetKey, $values, true);
}

function splitMultiValueText(?string $value): array
{
    $normalizedValue = trim((string) $value);
    if ($normalizedValue === "") {
        return [];
    }

    $items = array_map(
        static fn(string $item): string => trim($item),
        explode(",", $normalizedValue)
    );
    $items = array_values(array_filter(
        $items,
        static fn(string $item): bool => $item !== ""
    ));

    return array_values(array_unique($items));
}

function resolveMonitoringValidationErrorMessage(?string $errorCode): ?string
{
    return match (trim((string) $errorCode)) {
        "data_correction_user_required" => "USER IS REQUIRED WHEN PROCESSED TYPE INCLUDES DATA CORRECTION.",
        "incident_image_invalid_type" => "ONLY JPG, PNG, WEBP, OR GIF INCIDENT REPORT IMAGES ARE ALLOWED.",
        "incident_image_too_large" => "INCIDENT REPORT IMAGE MUST BE 5 MB OR SMALLER.",
        "incident_image_upload_failed" => "INCIDENT REPORT IMAGE COULD NOT BE UPLOADED.",
        "incident_image_storage_failed" => "INCIDENT REPORT IMAGE COULD NOT BE SAVED.",
        "record_save_failed" => "THE RECORD COULD NOT BE SAVED RIGHT NOW.",
        default => null,
    };
}

function renderOptionButtons(string $name, array $options, bool $allowMultiple = false): void
{
    $groupRole = $allowMultiple ? "group" : "radiogroup";
    $inputType = $allowMultiple ? "checkbox" : "radio";
    $inputName = $allowMultiple ? $name . "[]" : $name;

    echo '<div class="option-group" role="' . $groupRole . '" aria-label="' . e($name) . '">';

    foreach ($options as $option) {
        $id = $name . "_" . preg_replace('/[^a-z0-9]+/i', "_", strtolower($option));
        $safeId = e($id);
        $safeName = e($inputName);
        $safeOption = e($option);
        $displayOption = e(uppercaseText((string) $option));

        echo '<label class="option-button" for="' . $safeId . '">';
        echo '<input type="' . $inputType . '" id="' . $safeId . '" name="' . $safeName . '" value="' . $safeOption . '">';
        echo '<span>' . $displayOption . '</span>';
        echo '</label>';
    }

    echo "</div>";
}

function buildUrl(string $script, array $currentParams = [], array $changes = []): string
{
    $params = $currentParams;

    foreach ($changes as $key => $value) {
        if ($value === null || $value === "") {
            unset($params[$key]);
            continue;
        }

        $params[$key] = $value;
    }

    $query = http_build_query($params);
    return $script . ($query !== "" ? "?" . $query : "");
}

function buildMonitoringListQueryParams(string $companyKey, array $filters, bool $includePaging = true, int $defaultPerPage = 25): array
{
    $params = [
        "company" => $companyKey,
    ];

    $fieldMap = [
        "search" => "q",
        "identification_number" => "id_number",
        "user_name" => "user",
        "month" => "month",
        "date_from" => "date_from",
        "date_to" => "date_to",
        "branch" => "branch",
        "dealer" => "dealer",
        "department" => "department",
        "module" => "module",
        "status" => "status",
        "ticket_status" => "status",
    ];

    foreach ($fieldMap as $filterKey => $queryKey) {
        $value = $filters[$filterKey] ?? "";
        if ($value !== "") {
            $params[$queryKey] = $value;
        }
    }

    if (($filters["per_page"] ?? $defaultPerPage) !== $defaultPerPage) {
        $params["per_page"] = $filters["per_page"];
    }

    if (!empty($filters["data_correction_only"])) {
        $params["data_correction"] = 1;
    }

    if (!empty($filters["escalation_only"])) {
        $params["escalation"] = 1;
    }

    if ($includePaging && ($filters["page"] ?? 1) > 1) {
        $params["page"] = $filters["page"];
    }

    return $params;
}

function buildPaginationState(int $page, int $perPage, int $totalItems): array
{
    $safePerPage = max(1, $perPage);
    $totalPages = max(1, (int) ceil($totalItems / $safePerPage));
    $currentPage = min(max(1, $page), $totalPages);
    $offset = ($currentPage - 1) * $safePerPage;

    return [
        "page" => $currentPage,
        "per_page" => $safePerPage,
        "total_items" => $totalItems,
        "total_pages" => $totalPages,
        "offset" => $offset,
        "limit" => $safePerPage,
        "start_item" => $totalItems === 0 ? 0 : ($offset + 1),
        "end_item" => $totalItems === 0 ? 0 : min($offset + $safePerPage, $totalItems),
        "has_previous" => $currentPage > 1,
        "has_next" => $currentPage < $totalPages,
    ];
}

function buildPaginationPages(int $currentPage, int $totalPages, int $radius = 2): array
{
    $startPage = max(1, $currentPage - $radius);
    $endPage = min($totalPages, $currentPage + $radius);

    return range($startPage, $endPage);
}

function formatDisplayDate(?string $value): string
{
    if (!$value) {
        return "";
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? $value : date("n/j/Y", $timestamp);
}

function formatDisplayTimestamp(?string $value): string
{
    if (!$value) {
        return "";
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? $value : date("n/j/Y g:i A", $timestamp);
}

function formatDisplayMonth(?string $value): string
{
    if (!$value) {
        return "";
    }

    $month = DateTimeImmutable::createFromFormat("Y-m", (string) $value);
    return $month && $month->format("Y-m") === $value ? $month->format("F Y") : (string) $value;
}

function getLockedTicketStatuses(): array
{
    return ["Resolved", "Closed"];
}

function isLockedTicketStatus(?string $status): bool
{
    return in_array(trim((string) $status), getLockedTicketStatuses(), true);
}

function calculateTicketAgeDays(?string $dateCreatedValue, ?string $endDateValue): int
{
    $dateCreatedValue = trim((string) $dateCreatedValue);
    $endDateValue = trim((string) $endDateValue);

    if ($dateCreatedValue === "" || $endDateValue === "") {
        return 0;
    }

    try {
        $timezone = new DateTimeZone("Asia/Manila");
        $dateCreated = new DateTimeImmutable($dateCreatedValue, $timezone);
        $endDate = new DateTimeImmutable($endDateValue, $timezone);
    } catch (Throwable $error) {
        return 0;
    }

    if ($endDate < $dateCreated) {
        return 0;
    }

    return (int) $dateCreated->diff($endDate)->format("%a");
}

function formatTicketAgeValue(array $row): string
{
    $dateCreatedValue = trim((string) ($row["date_created"] ?? ""));
    if ($dateCreatedValue === "") {
        return "";
    }

    try {
        $timezone = new DateTimeZone("Asia/Manila");
        $dateCreated = new DateTimeImmutable($dateCreatedValue, $timezone);
        $resolvedAtValue = trim((string) ($row["resolved_at"] ?? ""));
        $endDate = $resolvedAtValue !== ""
            ? new DateTimeImmutable($resolvedAtValue, $timezone)
            : new DateTimeImmutable("now", $timezone);
    } catch (Throwable $error) {
        return "";
    }

    if ($endDate < $dateCreated) {
        return "0 day(s)";
    }

    $days = calculateTicketAgeDays($dateCreatedValue, $endDate->format("Y-m-d H:i:s"));
    return $days . " day(s)";
}

function formatSummaryValue(array $column, array $row): string
{
    $value = $row[$column["key"]] ?? "";
    $uppercaseSummaryKeys = ["identification_number", "user_name", "client_name", "reason", "processed_type", "classification", "status", "offense", "disciplinary_action", "action_taken"];

    switch ($column["format"] ?? "text") {
        case "date":
            return formatDisplayDate($value === "" ? null : (string) $value);

        case "timestamp":
            return formatDisplayTimestamp($value === "" ? null : (string) $value);

        case "amount":
            if ($value === null || $value === "") {
                return "";
            }

            return is_numeric((string) $value) ? number_format((float) $value, 2) : (string) $value;

        case "ticket_age":
            return formatTicketAgeValue($row);

        default:
            $textValue = (string) $value;

            if (in_array((string) ($column["key"] ?? ""), $uppercaseSummaryKeys, true)) {
                return uppercaseText($textValue);
            }

            return $textValue;
    }
}

function resolveDataCorrectionDisciplinaryAction(int $count): array
{
    if ($count <= 0) {
        return [
            "data_correction_alert" => "",
            "disciplinary_action" => "",
        ];
    }

    $alertMessage = "Data Correction Errors: {$count}";

    if ($count > 3) {
        return [
            "data_correction_alert" => $alertMessage . " - Exceeded 3-error limit",
            "disciplinary_action" => "Written Memo",
        ];
    }

    if ($count === 3) {
        return [
            "data_correction_alert" => $alertMessage . " - Reached 3-error limit",
            "disciplinary_action" => "Vocal Memo",
        ];
    }

    return [
        "data_correction_alert" => $alertMessage,
        "disciplinary_action" => "",
    ];
}

function getMonitoringActionOptions(): array
{
    return ["Vocal Memo", "Written Memo"];
}

function getMonitoringDoneStatus(): string
{
    return "Done";
}

function canMarkMonitoringRecordDone(?string $status): bool
{
    return uppercaseText(trim((string) $status)) === uppercaseText("Pending");
}

function getSummaryHeaders(array $summaryColumns): array
{
    return array_map(
        static fn(array $column): string => $column["label"],
        $summaryColumns
    );
}

function buildActiveFilterBadges(array $filters, ?string $fixedBranch = null): array
{
    $badges = [];

    if (($filters["month"] ?? "") !== "") {
        $badges[] = "Month: " . formatDisplayMonth($filters["month"]);
    }

    if ($fixedBranch !== null) {
        $badges[] = "Branch: " . $fixedBranch;
    } elseif ($filters["branch"] !== "") {
        $badges[] = "Branch: " . $filters["branch"];
    }

    if (($filters["dealer"] ?? "") !== "") {
        $badges[] = "Dealers: " . $filters["dealer"];
    }

    if (($filters["identification_number"] ?? "") !== "") {
        $badges[] = "ID Number: " . $filters["identification_number"];
    }

    if (($filters["user_name"] ?? "") !== "") {
        $badges[] = "User: " . uppercaseText($filters["user_name"]);
    }

    if ($filters["status"] !== "") {
        $badges[] = "Status: " . $filters["status"];
    }

    if (!empty($filters["data_correction_only"])) {
        $badges[] = "Processed Type: Data Correction";
    }

    if (!empty($filters["escalation_only"])) {
        $badges[] = "Escalation Candidates";
    }

    return $badges;
}

function isEscalationCandidateMonitoringRecord(array $row): bool
{
    return containsMultiValueText((string) ($row["processed_type"] ?? ""), "Data Correction")
        && (int) ($row["data_correction_offense_count"] ?? 0) >= 3;
}

function filterEscalationCandidateMonitoringRecords(array $records): array
{
    return array_values(array_filter(
        $records,
        static fn(array $row): bool => isEscalationCandidateMonitoringRecord($row)
    ));
}

function buildTicketFilterBadges(array $filters, ?string $fixedBranch = null): array
{
    $badges = [];

    if ($filters["search"] !== "") {
        $badges[] = 'Ticket: "' . $filters["search"] . '"';
    }

    if ($fixedBranch !== null) {
        $badges[] = "Branch: " . $fixedBranch;
    } elseif (($filters["branch"] ?? "") !== "") {
        $badges[] = "Branch: " . $filters["branch"];
    }

    if (($filters["dealer"] ?? "") !== "") {
        $badges[] = "Dealers: " . $filters["dealer"];
    }

    if (($filters["ticket_status"] ?? "") !== "") {
        $badges[] = "Status: " . $filters["ticket_status"];
    }

    return $badges;
}

function incrementDashboardBucket(array &$buckets, string $label, int $amount = 1): void
{
    $normalizedLabel = trim($label);
    if ($normalizedLabel === "" || $amount <= 0) {
        return;
    }

    $buckets[$normalizedLabel] = ($buckets[$normalizedLabel] ?? 0) + $amount;
}

function buildDashboardBreakdownItems(array $counts, int $total, int $limit = 0): array
{
    $items = [];

    foreach ($counts as $label => $count) {
        $count = (int) $count;
        if ($count <= 0) {
            continue;
        }

        $percentage = $total > 0 ? ($count / $total) * 100 : 0;
        $percentageLabel = rtrim(rtrim(number_format($percentage, 1), "0"), ".");

        $items[] = [
            "label" => (string) $label,
            "count" => $count,
            "percentage" => $percentage,
            "percentage_label" => $percentageLabel . "%",
            "bar_width" => min(100, max(12, (int) round($percentage))),
        ];
    }

    usort($items, static function (array $left, array $right): int {
        $countComparison = $right["count"] <=> $left["count"];
        if ($countComparison !== 0) {
            return $countComparison;
        }

        return strcasecmp((string) $left["label"], (string) $right["label"]);
    });

    if ($limit > 0) {
        $items = array_slice($items, 0, $limit);
    }

    return $items;
}

function buildMonitoringDashboardData(
    array $records,
    array $statusOptions,
    array $processedTypeOptions,
    array $classificationOptions
): array {
    $totalRecords = count($records);
    $statusCounts = array_fill_keys($statusOptions, 0);
    $processedTypeCounts = array_fill_keys($processedTypeOptions, 0);
    $classificationCounts = [];
    $moduleCounts = [];
    $branchCounts = [];
    $dealerCounts = [];
    $metrics = [
        "total_records" => $totalRecords,
        "data_correction_records" => 0,
        "escalation_records" => 0,
        "linked_tickets" => 0,
    ];

    foreach ($records as $row) {
        $statusValue = (string) ($row["status"] ?? "");
        $processedTypeValue = (string) ($row["processed_type"] ?? "");
        $classificationValue = trim((string) ($row["classification"] ?? ""));
        $moduleValue = trim((string) ($row["module"] ?? ""));
        $branchValue = trim((string) ($row["branch"] ?? ""));
        $dealerValue = trim((string) ($row["dealer"] ?? ""));

        foreach ($statusOptions as $option) {
            if (containsMultiValueText($statusValue, $option)) {
                incrementDashboardBucket($statusCounts, $option);
            }
        }

        foreach ($processedTypeOptions as $option) {
            if (containsMultiValueText($processedTypeValue, $option)) {
                incrementDashboardBucket($processedTypeCounts, $option);
            }
        }

        if (containsMultiValueText($processedTypeValue, "Data Correction")) {
            $metrics["data_correction_records"]++;
        }

        if (((int) ($row["data_correction_offense_count"] ?? 0)) >= 3) {
            $metrics["escalation_records"]++;
        }

        if (trim((string) ($row["ticket"] ?? "")) !== "") {
            $metrics["linked_tickets"]++;
        }

        incrementDashboardBucket(
            $classificationCounts,
            $classificationValue !== "" ? $classificationValue : "Unspecified"
        );
        incrementDashboardBucket(
            $moduleCounts,
            $moduleValue !== "" ? $moduleValue : "Unspecified"
        );
        incrementDashboardBucket(
            $branchCounts,
            $branchValue !== "" ? $branchValue : "Unspecified"
        );
        incrementDashboardBucket(
            $dealerCounts,
            $dealerValue !== "" ? $dealerValue : "Unspecified"
        );
    }

    foreach ($classificationOptions as $option) {
        if (!array_key_exists($option, $classificationCounts)) {
            $classificationCounts[$option] = 0;
        }
    }

    return [
        "metrics" => $metrics,
        "status_breakdown" => buildDashboardBreakdownItems($statusCounts, $totalRecords),
        "processed_type_breakdown" => buildDashboardBreakdownItems($processedTypeCounts, $totalRecords),
        "classification_breakdown" => buildDashboardBreakdownItems($classificationCounts, $totalRecords),
        "module_breakdown" => buildDashboardBreakdownItems($moduleCounts, $totalRecords, 6),
        "branch_breakdown" => buildDashboardBreakdownItems($branchCounts, $totalRecords, 6),
        "dealer_breakdown" => buildDashboardBreakdownItems($dealerCounts, $totalRecords, 6),
    ];
}

function buildTicketDashboardData(array $records, array $ticketStatusOptions): array
{
    $totalRecords = count($records);
    $statusCounts = array_fill_keys($ticketStatusOptions, 0);
    $metrics = [
        "total_tickets" => $totalRecords,
        "active_tickets" => 0,
        "resolved_tickets" => 0,
        "aging_tickets" => 0,
        "oldest_active_days" => 0,
    ];
    $timezone = new DateTimeZone("Asia/Manila");
    $now = new DateTimeImmutable("now", $timezone);

    foreach ($records as $row) {
        $ticketStatus = trim((string) ($row["ticket_status"] ?? ""));
        if ($ticketStatus !== "") {
            incrementDashboardBucket($statusCounts, $ticketStatus);
        }

        $isResolved = isLockedTicketStatus($ticketStatus);
        if ($isResolved) {
            $metrics["resolved_tickets"]++;
        } else {
            $metrics["active_tickets"]++;
        }

        $dateCreated = trim((string) ($row["date_created"] ?? ""));
        if ($dateCreated === "" || $isResolved) {
            continue;
        }

        $ageDays = calculateTicketAgeDays($dateCreated, $now->format("Y-m-d H:i:s"));
        if ($ageDays >= 7) {
            $metrics["aging_tickets"]++;
        }

        if ($ageDays > $metrics["oldest_active_days"]) {
            $metrics["oldest_active_days"] = $ageDays;
        }
    }

    return [
        "metrics" => $metrics,
        "status_breakdown" => buildDashboardBreakdownItems($statusCounts, $totalRecords),
    ];
}
