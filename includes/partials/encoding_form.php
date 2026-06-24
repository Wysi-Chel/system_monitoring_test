<section class="card" id="encode-section">
    <h2>Encode New Record</h2>

    <?php if (!empty($validationErrorMessage)): ?>
    <div class="form-alert form-alert-error" role="alert">
        <?= e($validationErrorMessage) ?>
    </div>
    <?php endif; ?>

    <form action="save.php" method="POST" id="record-form" enctype="multipart/form-data">
        <input type="hidden" name="company" value="<?= e($company["key"]) ?>">
        <input type="hidden" name="identification_number" value="<?= e($nextMonitoringIdentificationNumber) ?>">

        <section class="form-section compact-section">
            <div class="field-grid compact date-grid">
                <div class="field date-field">
                    <label for="date-recorded">Date</label>
                    <input type="date" id="date-recorded" name="date_recorded" value="<?= e($today) ?>" required>
                </div>

                <div class="field date-field">
                    <label for="transaction-date">Transaction Date</label>
                    <input type="date" id="transaction-date" name="transaction_date" required>
                </div>
            </div>
        </section>

        <section class="form-section">
            <div class="selector-grid<?= $showBranchSelector ? " triple" : "" ?>">
                <div class="selector-field selector-field-medium selector-field-stack">
                    <?php if ($showBranchSelector): ?>
                    <div class="selector-subfield">
                        <label>Branch</label>
                        <?php renderOptionButtons("branch", $branchOptions); ?>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="branch" value="<?= e($fixedBranch) ?>">
                    <?php endif; ?>

                    <div class="selector-subfield">
                        <label>Dealers</label>
                        <?php renderOptionButtons("dealer", $dealerOptions); ?>
                    </div>
                </div>

                <div class="selector-field selector-field-medium">
                    <label>Department</label>
                    <?php renderOptionButtons("department", $departmentOptions); ?>
                </div>

                <div class="selector-field selector-field-medium">
                    <label>Module</label>
                    <?php renderOptionButtons("module", $moduleOptions); ?>
                </div>
            </div>
        </section>

        <section class="form-section">
            <div class="field-grid">
                <div class="field field-span-2">
                    <label for="user-name">User</label>
                    <input type="text" id="user-name" name="user_name">
                </div>

                <div class="field">
                    <label for="identification-number-preview">Identification Number</label>
                    <input type="text" id="identification-number-preview" value="<?= e($nextMonitoringIdentificationNumber) ?>" readonly>
                </div>

                <div class="field field-span-2">
                    <label for="client-name">Client Name</label>
                    <input type="text" id="client-name" name="client_name">
                </div>

                <div class="field">
                    <label for="invoice-reference">Transaction Reference</label>
                    <input type="text" id="invoice-reference" name="invoice_reference">
                </div>

                <div class="field">
                    <label for="payment-reference">Payment Reference</label>
                    <input type="text" id="payment-reference" name="payment_reference">
                </div>

                <div class="field">
                    <label for="amount">Amount</label>
                    <input type="number" id="amount" name="amount" step="0.01">
                </div>

                <div class="field">
                    <label for="ticket">Ticket</label>
                    <?php if (companySupportsTicketMonitoring($company)): ?>
                    <div class="inline-input-row">
                        <input type="text" id="ticket" name="ticket">
                        
                    </div>
                    <?php else: ?>
                    <input type="text" id="ticket" name="ticket">
                    <?php endif; ?>
                </div>

                <div class="field field-span-2">
                    <label for="reason">Reason</label>
                    <input type="text" id="reason" name="reason">
                </div>

                <div class="field">
                    <label for="system-admin">System Admin</label>
                    <input type="text" id="system-admin" name="system_admin">
                </div>

                <div class="field">
                    <label for="offense">Offense</label>
                    <input type="text" id="offense" name="offense">
                </div>

                <div class="field">
                    <label for="approved-by">Approved By</label>
                    <input type="text" id="approved-by" name="approved_by">
                </div>

                <div class="field">
                    <label for="processed-by">Processed By</label>
                    <input type="text" id="processed-by" name="processed_by" placeholder="">
                </div>

                <div class="field field-span-2">
                    <label for="remarks">Remarks</label>
                    <input type="text" id="remarks" name="remarks">
                </div>

                <div class="field field-span-2">
                    <label for="incident-report-image">Incident Report Image</label>
                    <input type="file" id="incident-report-image" name="incident_report_image" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
                </div>
            </div>
        </section>

        <section class="form-section">
            <div class="selector-grid triple">
                <div class="selector-field selector-field-medium">
                    <label>Classification</label>
                    <?php renderOptionButtons("classification", $classificationOptions); ?>
                </div>

                <div class="selector-field selector-field-medium">
                    <label>Processed Type</label>
                    <?php renderOptionButtons("processed_type", $processedTypeOptions, true); ?>
                </div>

                <div class="selector-field selector-field-medium">
                    <label>Status</label>
                    <?php renderOptionButtons("status", $statusOptions, true); ?>
                </div>
            </div>
        </section>

        <div class="buttons">
            <button type="submit" class="primary">Save</button>
            <button type="reset" class="secondary">Clear Form</button>
        </div>
    </form>

</section>
