<?php
$savedModalTitle = trim((string) ($savedTitle ?? "Record Saved"));
$savedModalMessage = trim((string) ($savedMessage ?? "Record successfully saved."));
$savedModalButtonLabel = trim((string) ($savedButtonLabel ?? "OK"));
?>
<div class="modal-overlay" id="saved-modal" role="presentation">
    <div class="modal-window" role="dialog" aria-modal="true" aria-labelledby="saved-modal-title" aria-describedby="saved-modal-message">
        <div class="modal-body">
            <div class="modal-icon" aria-hidden="true">&#10003;</div>
            <h3 class="modal-title" id="saved-modal-title"><?= e($savedModalTitle) ?></h3>
            <p class="modal-message" id="saved-modal-message"><?= e($savedModalMessage) ?></p>
            <div class="modal-actions">
                <button type="button" class="primary modal-button" id="saved-modal-ok"><?= e($savedModalButtonLabel) ?></button>
            </div>
        </div>
    </div>
</div>
