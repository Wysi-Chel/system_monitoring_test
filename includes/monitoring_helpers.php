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

function iconSvg(string $name): string
{
    $paths = [
        "arrow-left" => '<path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path>',
        "arrow-right" => '<path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path>',
        "check" => '<path d="m20 6-11 11-5-5"></path>',
        "download" => '<path d="M12 3v12"></path><path d="m7 10 5 5 5-5"></path><path d="M5 21h14"></path>',
        "edit" => '<path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"></path>',
        "external-link" => '<path d="M15 3h6v6"></path><path d="M10 14 21 3"></path><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>',
        "file-text" => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"></path><path d="M14 2v6h6"></path><path d="M16 13H8"></path><path d="M16 17H8"></path><path d="M10 9H8"></path>',
        "home" => '<path d="m3 11 9-8 9 8"></path><path d="M5 10v11h14V10"></path><path d="M9 21v-6h6v6"></path>',
        "moon" => '<path d="M12 3a6 6 0 0 0 9 7.5A9 9 0 1 1 12 3Z"></path>',
        "printer" => '<path d="M6 9V3h12v6"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><path d="M6 14h12v7H6z"></path>',
        "refresh" => '<path d="M21 12a9 9 0 0 1-15.5 6.2"></path><path d="M3 12A9 9 0 0 1 18.5 5.8"></path><path d="M3 18v-6h6"></path><path d="M21 6v6h-6"></path>',
        "save" => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"></path><path d="M17 21v-8H7v8"></path><path d="M7 3v5h8"></path>',
        "search" => '<circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path>',
        "send" => '<path d="m22 2-7 20-4-9-9-4Z"></path><path d="M22 2 11 13"></path>',
        "sun" => '<circle cx="12" cy="12" r="4"></circle><path d="M12 2v2"></path><path d="M12 20v2"></path><path d="m4.93 4.93 1.41 1.41"></path><path d="m17.66 17.66 1.41 1.41"></path><path d="M2 12h2"></path><path d="M20 12h2"></path><path d="m6.34 17.66-1.41 1.41"></path><path d="m19.07 4.93-1.41 1.41"></path>',
        "trash" => '<path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="m19 6-1 15H6L5 6"></path>',
        "upload" => '<path d="M12 21V9"></path><path d="m7 14 5-5 5 5"></path><path d="M5 3h14"></path>',
        "x" => '<path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>',
    ];

    $pathMarkup = $paths[$name] ?? $paths["check"];
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $pathMarkup . '</svg>';
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

function isUserErrorMonitoringRecord(array $row): bool
{
    return uppercaseText(trim((string) ($row["classification"] ?? ""))) === uppercaseText("User Error");
}

function normalizeMonitoringMemoAction(string $action): string
{
    return match (uppercaseText(trim($action))) {
        uppercaseText("Verbal Memo"), uppercaseText("Vocal Memo") => "Verbal Memo",
        uppercaseText("Written Memo") => "Written Memo",
        uppercaseText("Final Memo") => "Final Memo",
        default => "",
    };
}

function getMonitoringMemoActionRank(string $action): int
{
    return match (normalizeMonitoringMemoAction($action)) {
        "Verbal Memo" => 1,
        "Written Memo" => 2,
        "Final Memo" => 3,
        default => 0,
    };
}

function getIssuedMonitoringMemoAction(array $row): string
{
    $actionValues = [];

    if (array_key_exists("issued_disciplinary_action", $row)) {
        $actionValues[] = trim((string) ($row["issued_disciplinary_action"] ?? ""));
    } else {
        foreach (["disciplinary_action", "action_taken", "offense"] as $key) {
            $actionValues[] = trim((string) ($row[$key] ?? ""));
        }
    }

    $issuedAction = "";
    $issuedActionRank = 0;

    foreach ($actionValues as $actionValue) {
        $memoAction = normalizeMonitoringMemoAction($actionValue);
        $memoActionRank = getMonitoringMemoActionRank($memoAction);
        if ($memoAction === "" || $memoActionRank <= $issuedActionRank) {
            continue;
        }

        $issuedAction = $memoAction;
        $issuedActionRank = $memoActionRank;
    }

    return $issuedAction;
}

function formatMonitoringMemoActionStatusDisplayValue(array $row): string
{
    $issuedAction = getIssuedMonitoringMemoAction($row);
    if ($issuedAction !== "") {
        return $issuedAction . " - Issued";
    }

    foreach (["disciplinary_action", "action_taken", "offense"] as $key) {
        $memoAction = normalizeMonitoringMemoAction((string) ($row[$key] ?? ""));
        if ($memoAction !== "") {
            return $memoAction . " - To issue";
        }
    }

    return "";
}

function isFinalMemoMonitoringRecord(array $row): bool
{
    return getIssuedMonitoringMemoAction($row) === "Final Memo";
}

function getAvailableMonitoringMemoActionOptions(array $row): array
{
    if (getIssuedMonitoringMemoAction($row) !== "") {
        return [];
    }

    $cycleIssuedAction = trim((string) ($row["memo_cycle_issued_action"] ?? ""));

    return match ($cycleIssuedAction) {
        "Final Memo" => [],
        "Written Memo" => ["Final Memo"],
        "Verbal Memo" => ["Written Memo", "Final Memo"],
        default => getMonitoringActionOptions(),
    };
}

function resolveSuggestedMonitoringMemoAction(array $row, int $count): string
{
    $options = getAvailableMonitoringMemoActionOptions($row);
    if ($options !== []) {
        return $options[0];
    }

    $resolvedAction = resolveDataCorrectionDisciplinaryAction($count);
    return (string) ($resolvedAction["disciplinary_action"] ?? "");
}

function resolveMonitoringValidationErrorMessage(?string $errorCode): ?string
{
    return match (trim((string) $errorCode)) {
        "data_correction_user_required" => "USER IS REQUIRED WHEN CLASSIFICATION IS USER ERROR.",
        "user_error_user_required" => "USER IS REQUIRED WHEN CLASSIFICATION IS USER ERROR.",
        "incident_image_invalid_type" => "ONLY JPG, PNG, WEBP, OR GIF INCIDENT REPORT IMAGES ARE ALLOWED.",
        "incident_image_too_large" => "INCIDENT REPORT IMAGE MUST BE 5 MB OR SMALLER.",
        "incident_image_upload_failed" => "INCIDENT REPORT IMAGE COULD NOT BE UPLOADED.",
        "incident_image_storage_failed" => "INCIDENT REPORT IMAGE COULD NOT BE SAVED.",
        "record_save_failed" => "THE RECORD COULD NOT BE SAVED RIGHT NOW.",
        "record_update_failed" => "THE RECORD COULD NOT BE UPDATED RIGHT NOW.",
        default => null,
    };
}

function renderOptionButtons(string $name, array $options, bool $allowMultiple = false, $selectedValues = []): void
{
    $groupRole = $allowMultiple ? "group" : "radiogroup";
    $inputType = $allowMultiple ? "checkbox" : "radio";
    $inputName = $allowMultiple ? $name . "[]" : $name;
    $selectedValues = is_array($selectedValues) ? $selectedValues : [$selectedValues];
    $selectedKeys = array_map(
        static fn($item): string => uppercaseText(trim((string) $item)),
        $selectedValues
    );

    echo '<div class="option-group" role="' . $groupRole . '" aria-label="' . e($name) . '">';

    foreach ($options as $option) {
        $id = $name . "_" . preg_replace('/[^a-z0-9]+/i', "_", strtolower($option));
        $safeId = e($id);
        $safeName = e($inputName);
        $safeOption = e($option);
        $displayOption = e(uppercaseText((string) $option));
        $isChecked = in_array(uppercaseText(trim((string) $option)), $selectedKeys, true);

        echo '<label class="option-button" for="' . $safeId . '">';
        echo '<input type="' . $inputType . '" id="' . $safeId . '" name="' . $safeName . '" value="' . $safeOption . '"' . ($isChecked ? ' checked' : '') . '>';
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
        "day" => "day",
        "disciplinary_action" => "action",
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
    $columnKey = (string) ($column["key"] ?? "");

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
            if ($columnKey === "disciplinary_action") {
                $memoStatusValue = formatMonitoringMemoActionStatusDisplayValue($row);
                if ($memoStatusValue !== "") {
                    return uppercaseText($memoStatusValue);
                }
            }

            $textValue = $columnKey === "processed_type"
                ? formatMonitoringProcessedTypeDisplayValue($row)
                : (string) $value;

            if (in_array($columnKey, $uppercaseSummaryKeys, true)) {
                return uppercaseText($textValue);
            }

            return $textValue;
    }
}

function formatMonitoringProcessedTypeDisplayValue(array $row): string
{
    $processedTypeValue = trim((string) ($row["processed_type"] ?? ""));
    if ($processedTypeValue === "") {
        return "";
    }

    if (uppercaseText(trim((string) ($row["classification"] ?? ""))) !== uppercaseText("User Error")) {
        return $processedTypeValue;
    }

    $processedTypeValues = splitMultiValueText($processedTypeValue);
    if ($processedTypeValues === []) {
        return "";
    }

    $displayValues = [];
    foreach ($processedTypeValues as $processedType) {
        $displayValue = containsMultiValueText($processedType, "Data Correction")
            ? "User Error"
            : $processedType;

        if (!in_array($displayValue, $displayValues, true)) {
            $displayValues[] = $displayValue;
        }
    }

    return implode(", ", $displayValues);
}

function resolveDataCorrectionDisciplinaryAction(int $count): array
{
    if ($count <= 0) {
        return [
            "data_correction_alert" => "",
            "disciplinary_action" => "",
        ];
    }

    $alertMessage = "User Error Count: {$count}";

    if ($count >= 5) {
        return [
            "data_correction_alert" => $alertMessage . " - Final memo threshold",
            "disciplinary_action" => "Final Memo",
        ];
    }

    if ($count > 3) {
        return [
            "data_correction_alert" => $alertMessage . " - Written memo threshold",
            "disciplinary_action" => "Written Memo",
        ];
    }

    return [
        "data_correction_alert" => $alertMessage . " - Verbal memo threshold",
        "disciplinary_action" => "Verbal Memo",
    ];
}

function getMonitoringActionOptions(): array
{
    return ["Verbal Memo", "Written Memo", "Final Memo"];
}

function getMonitoringIncidentReportOffense(): string
{
    return "Incident Report";
}

function getMonitoringIncidentReportResolvedAction(): string
{
    return "Resolved";
}

function getMonitoringDoneStatus(): string
{
    return "Done";
}

function canMarkMonitoringRecordDone(?string $status): bool
{
    return uppercaseText(trim((string) $status)) === uppercaseText("Pending");
}

function isMonitoringIncidentReportRecord(array $row): bool
{
    return uppercaseText(trim((string) ($row["offense"] ?? ""))) === uppercaseText(getMonitoringIncidentReportOffense());
}

function hasPendingMonitoringIncidentReportStatus(array $row): bool
{
    return isMonitoringIncidentReportRecord($row)
        && containsMultiValueText((string) ($row["status"] ?? ""), "Pending");
}

function hasResolvedMonitoringIncidentReportStatus(array $row): bool
{
    return isMonitoringIncidentReportRecord($row)
        && containsMultiValueText((string) ($row["status"] ?? ""), getMonitoringDoneStatus());
}

function resolveMonitoringIncidentReportStatus(?string $status): string
{
    $statusValues = splitMultiValueText($status);
    if ($statusValues === []) {
        return getMonitoringDoneStatus();
    }

    $resolvedValues = [];
    $doneStatus = getMonitoringDoneStatus();
    $hasDoneStatus = false;

    foreach ($statusValues as $statusValue) {
        if (uppercaseText($statusValue) === uppercaseText($doneStatus)) {
            $hasDoneStatus = true;
            break;
        }
    }

    foreach ($statusValues as $statusValue) {
        if (uppercaseText($statusValue) === uppercaseText("Pending")) {
            if (!$hasDoneStatus) {
                $resolvedValues[] = $doneStatus;
                $hasDoneStatus = true;
            }
            continue;
        }

        if (!containsMultiValueText(implode(", ", $resolvedValues), $statusValue)) {
            $resolvedValues[] = $statusValue;
        }
    }

    return implode(", ", $resolvedValues);
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
    if (($filters["day"] ?? "") !== "") {
        $badges[] = "Day: " . formatDisplayDate($filters["day"]);
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

    if (($filters["disciplinary_action"] ?? "") !== "") {
        $badges[] = "Action: " . $filters["disciplinary_action"];
    }

    if (!empty($filters["data_correction_only"])) {
        $badges[] = "Classification: User Error";
    }

    if (!empty($filters["escalation_only"])) {
        $badges[] = "Action Items";
    }

    return $badges;
}

function isEscalationCandidateMonitoringRecord(array $row): bool
{
    return isUserErrorMonitoringRecord($row)
        && (int) ($row["data_correction_offense_count"] ?? 0) >= 1;
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

        if (uppercaseText($classificationValue) === uppercaseText("User Error")) {
            $metrics["data_correction_records"]++;
        }

        if (((int) ($row["data_correction_offense_count"] ?? 0)) >= 1) {
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
