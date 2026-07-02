<?php
$editingRecord = isset($editingRecord) && is_array($editingRecord) ? $editingRecord : null;
$isEditingRecord = $editingRecord !== null;
$recordFormSectionClass = $isEditingRecord ? "record-edit-panel" : "card";
$recordFormTitle = $isEditingRecord ? "Edit Encoded Record" : "Encode New Record";
$recordFormSubmitLabel = $isEditingRecord ? "Update Record" : "Save";
$recordFormIdentificationNumber = trim((string) ($editingRecord["identification_number"] ?? $nextMonitoringIdentificationNumber));
$recordFormValue = static function (string $key, string $default = "") use ($editingRecord): string {
    if ($editingRecord === null) {
        return $default;
    }

    return (string) ($editingRecord[$key] ?? "");
};
$recordFormSelectedValues = static function (string $key) use ($editingRecord): array {
    if ($editingRecord === null) {
        return [];
    }

    return splitMultiValueText((string) ($editingRecord[$key] ?? ""));
};
$userNameSuggestions = isset($userNameSuggestions) && is_array($userNameSuggestions) ? $userNameSuggestions : [];
?>
<section class="<?= e($recordFormSectionClass) ?>" id="encode-section">
    <h2><?= e($recordFormTitle) ?></h2>

    <?php if (!empty($validationErrorMessage)): ?>
    <div class="form-alert form-alert-error" role="alert">
        <?= e($validationErrorMessage) ?>
    </div>
    <?php endif; ?>

    <form action="save.php" method="POST" id="record-form" enctype="multipart/form-data">
        <input type="hidden" name="company" value="<?= e($company["key"]) ?>">
        <input type="hidden" name="identification_number" value="<?= e($recordFormIdentificationNumber) ?>">
        <?php if ($isEditingRecord): ?>
        <input type="hidden" name="record_id" value="<?= e($editingRecord["id"] ?? "") ?>">
        <?php endif; ?>

        <section class="form-section compact-section">
            <div class="field-grid compact date-grid">
                <div class="field date-field">
                    <label for="date-recorded">Date</label>
                    <input type="date" id="date-recorded" name="date_recorded" value="<?= e($recordFormValue("date_recorded", $today)) ?>" required>
                </div>

                <div class="field date-field">
                    <label for="transaction-date">Transaction date</label>
                    <input type="date" id="transaction-date" name="transaction_date" value="<?= e($recordFormValue("transaction_date")) ?>" required>
                </div>
            </div>
        </section>

        <section class="form-section">
            <div class="selector-grid<?= $showBranchSelector ? " triple" : "" ?>">
                <div class="selector-field selector-field-medium selector-field-stack">
                    <?php if ($showBranchSelector): ?>
                    <div class="selector-subfield">
                        <label>Branch</label>
                        <?php renderOptionButtons("branch", $branchOptions, false, $recordFormValue("branch")); ?>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="branch" value="<?= e($fixedBranch) ?>">
                    <?php endif; ?>

                    <div class="selector-subfield">
                        <label>Dealers</label>
                        <?php renderOptionButtons("dealer", $dealerOptions, false, $recordFormValue("dealer")); ?>
                    </div>
                </div>

                <div class="selector-field selector-field-medium">
                    <label>Department</label>
                    <?php renderOptionButtons("department", $departmentOptions, false, $recordFormValue("department")); ?>
                </div>

                <div class="selector-field selector-field-medium">
                    <label>Module</label>
                    <?php renderOptionButtons("module", $moduleOptions, false, $recordFormValue("module")); ?>
                </div>
            </div>
        </section>

        <section class="form-section">
            <div class="field-grid">
                <div class="field field-span-2">
                    <label for="user-name">User</label>
                    <input type="text" id="user-name" name="user_name" value="<?= e($recordFormValue("user_name")) ?>"<?= $userNameSuggestions !== [] ? ' list="user-name-suggestions"' : "" ?>>
                    <?php if ($userNameSuggestions !== []): ?>
                    <datalist id="user-name-suggestions">
                        <?php foreach ($userNameSuggestions as $userNameSuggestion): ?>
                        <option value="<?= e(uppercaseText((string) $userNameSuggestion)) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="identification-number-preview">ID number</label>
                    <input type="text" id="identification-number-preview" value="<?= e($recordFormIdentificationNumber) ?>" readonly>
                </div>

                <div class="field field-span-2">
                    <label for="client-name">Client name</label>
                    <input type="text" id="client-name" name="client_name" value="<?= e($recordFormValue("client_name")) ?>">
                </div>

                <div class="field">
                    <label for="invoice-reference">Transaction reference</label>
                    <input type="text" id="invoice-reference" name="invoice_reference" value="<?= e($recordFormValue("invoice_reference")) ?>">
                </div>

                <div class="field">
                    <label for="payment-reference">Payment reference</label>
                    <input type="text" id="payment-reference" name="payment_reference" value="<?= e($recordFormValue("payment_reference")) ?>">
                </div>

                <div class="field">
                    <label for="amount">Amount</label>
                    <input type="number" id="amount" name="amount" step="0.01" value="<?= e($recordFormValue("amount")) ?>">
                </div>

                <div class="field">
                    <label for="ticket">Ticket</label>
                    <?php if (companySupportsTicketMonitoring($company)): ?>
                    <div class="inline-input-row">
                        <input type="text" id="ticket" name="ticket" value="<?= e($recordFormValue("ticket")) ?>">
                        
                    </div>
                    <?php else: ?>
                    <input type="text" id="ticket" name="ticket" value="<?= e($recordFormValue("ticket")) ?>">
                    <?php endif; ?>
                </div>

                <div class="field field-span-2">
                    <label for="reason">Reason</label>
                    <input type="text" id="reason" name="reason" value="<?= e($recordFormValue("reason")) ?>">
                </div>

                <div class="field">
                    <label for="system-admin">System admin</label>
                    <input type="text" id="system-admin" name="system_admin" value="<?= e($recordFormValue("system_admin")) ?>">
                </div>

                <div class="field">
                    <label for="offense">Offense</label>
                    <input
                        type="text"
                        id="offense"
                        name="offense"
                        value="<?= e($recordFormValue("offense")) ?>"
                        list="offense-suggestions"
                        data-incident-report-offense="<?= e(getMonitoringIncidentReportOffense()) ?>"
                    >
                    <datalist id="offense-suggestions">
                        <option value="<?= e(getMonitoringIncidentReportOffense()) ?>"></option>
                    </datalist>
                </div>

                <div class="field">
                    <label>Approved by</label>
                    <?php renderOptionButtons("approved_by", $approvedByOptions, false, $recordFormValue("approved_by")); ?>
                </div>

                <div class="field">
                    <label>Processed by</label>
                    <?php renderOptionButtons("processed_by", $processedByOptions, false, $recordFormValue("processed_by")); ?>
                </div>

                <div class="field field-span-2">
                    <label for="remarks">Remarks</label>
                    <input type="text" id="remarks" name="remarks" value="<?= e($recordFormValue("remarks")) ?>">
                </div>

                <div class="field field-span-2">
                    <label for="incident-report-image">Incident report image</label>
                    <input type="file" id="incident-report-image" name="incident_report_image" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
                    <?php if ($isEditingRecord && trim((string) ($editingRecord["incident_report_image_path"] ?? "")) !== ""): ?>
                    <p class="note form-field-note">Leave blank to keep the current image.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="form-section">
            <div class="selector-grid triple">
                <div class="selector-field selector-field-medium">
                    <label>Classification</label>
                    <?php renderOptionButtons("classification", $classificationOptions, false, $recordFormValue("classification")); ?>
                </div>

                <div class="selector-field selector-field-medium">
                    <label>Processed type</label>
                    <?php renderOptionButtons("processed_type", $processedTypeOptions, true, $recordFormSelectedValues("processed_type")); ?>
                </div>

                <div class="selector-field selector-field-medium">
                    <label>Status</label>
                    <?php renderOptionButtons("status", $statusOptions, true, $recordFormSelectedValues("status")); ?>
                </div>
            </div>
        </section>

        <div class="buttons">
            <button type="submit" class="primary icon-button" aria-label="<?= e($recordFormSubmitLabel) ?>" title="<?= e($recordFormSubmitLabel) ?>">
                <?= iconSvg("save") ?>
                <span class="sr-only"><?= e($recordFormSubmitLabel) ?></span>
            </button>
            <?php if ($isEditingRecord && isset($recordViewUrl)): ?>
            <a href="<?= e($recordViewUrl) ?>" class="button-link secondary icon-button" aria-label="Cancel edit" title="Cancel edit">
                <?= iconSvg("arrow-left") ?>
                <span class="sr-only">Cancel edit</span>
            </a>
            <?php endif; ?>
        </div>
    </form>

</section>
