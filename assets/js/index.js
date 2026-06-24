(function () {
    var root = document.documentElement;
    var themeToggle = document.getElementById("theme-toggle");
    var recordForm = document.getElementById("record-form");
    var ticketRecordForm = document.getElementById("ticket-record-form");
    var modal = document.getElementById("saved-modal");
    var okButton = document.getElementById("saved-modal-ok");

    var applyUppercaseBehavior = function (fields) {
        for (var index = 0; index < fields.length; index += 1) {
            fields[index].addEventListener("input", function () {
                this.value = this.value.toUpperCase();
            });

            if (fields[index].value) {
                fields[index].value = fields[index].value.toUpperCase();
            }
        }
    };

    if (recordForm) {
        var recordUppercaseFields = recordForm.querySelectorAll('input[type="text"], textarea');
        var ticketInput = document.getElementById("ticket");
        var ticketMonitoringLink = document.getElementById("ticket-monitoring-link");
        var userNameInput = document.getElementById("user-name");
        var processedTypeInputs = recordForm.querySelectorAll('input[name="processed_type[]"]');
        var dataCorrectionMessage = "USER is required when PROCESSED TYPE includes DATA CORRECTION.";

        applyUppercaseBehavior(recordUppercaseFields);

        if (userNameInput && processedTypeInputs.length > 0) {
            var updateDataCorrectionValidation = function () {
                var hasDataCorrection = false;

                for (var inputIndex = 0; inputIndex < processedTypeInputs.length; inputIndex += 1) {
                    if (
                        processedTypeInputs[inputIndex].value === "Data Correction"
                        && processedTypeInputs[inputIndex].checked
                    ) {
                        hasDataCorrection = true;
                        break;
                    }
                }

                if (hasDataCorrection && !userNameInput.value.trim()) {
                    userNameInput.setCustomValidity(dataCorrectionMessage);
                    return;
                }

                userNameInput.setCustomValidity("");
            };

            for (var processedTypeIndex = 0; processedTypeIndex < processedTypeInputs.length; processedTypeIndex += 1) {
                processedTypeInputs[processedTypeIndex].addEventListener("change", updateDataCorrectionValidation);
            }

            userNameInput.addEventListener("input", updateDataCorrectionValidation);
            recordForm.addEventListener("submit", updateDataCorrectionValidation);
            updateDataCorrectionValidation();
        }

        if (ticketInput && ticketMonitoringLink && ticketMonitoringLink.dataset.baseHref) {
            ticketMonitoringLink.addEventListener("click", function () {
                var targetUrl = new URL(ticketMonitoringLink.dataset.baseHref, window.location.href);
                var ticketValue = ticketInput.value.trim();

                if (ticketValue !== "") {
                    targetUrl.searchParams.set("ticket_number", ticketValue);
                    targetUrl.searchParams.set("q", ticketValue);
                } else {
                    targetUrl.searchParams.delete("ticket_number");
                    targetUrl.searchParams.delete("q");
                }

                ticketMonitoringLink.href = targetUrl.toString();
            });
        }
    }

    if (ticketRecordForm) {
        var ticketUppercaseFields = ticketRecordForm.querySelectorAll('#ticket-number, #ticket-created-by');
        var ticketDateCreated = document.getElementById("ticket-date-created");
        var ticketAgePreview = document.getElementById("ticket-age-preview");

        applyUppercaseBehavior(ticketUppercaseFields);

        if (ticketDateCreated && ticketAgePreview) {
            var updateTicketAgePreview = function () {
                if (!ticketDateCreated.value) {
                    ticketAgePreview.value = "";
                    return;
                }

                var createdDate = new Date(ticketDateCreated.value + "T00:00:00");
                var today = new Date();
                today.setHours(0, 0, 0, 0);

                if (isNaN(createdDate.getTime()) || createdDate > today) {
                    ticketAgePreview.value = "0 day(s)";
                    return;
                }

                var diffMs = today.getTime() - createdDate.getTime();
                var days = Math.floor(diffMs / 86400000);
                ticketAgePreview.value = days + " day(s)";
            };

            ticketDateCreated.addEventListener("input", updateTicketAgePreview);
            ticketDateCreated.addEventListener("change", updateTicketAgePreview);
            ticketRecordForm.addEventListener("reset", function () {
                window.setTimeout(updateTicketAgePreview, 0);
            });
            updateTicketAgePreview();
        }
    }

    if (themeToggle) {
        var updateThemeToggle = function () {
            var isDark = root.classList.contains("dark-theme");
            themeToggle.textContent = isDark ? "Light Mode" : "Dark Mode";
            themeToggle.setAttribute("aria-pressed", isDark ? "true" : "false");
        };

        themeToggle.addEventListener("click", function () {
            root.classList.toggle("dark-theme");

            try {
                window.localStorage.setItem("systemMonitoringTheme", root.classList.contains("dark-theme") ? "dark" : "light");
            } catch (error) {
            }

            updateThemeToggle();
        });

        updateThemeToggle();
    }

    if (!modal || !okButton) {
        return;
    }

    document.body.classList.add("modal-open");

    var closeModal = function () {
        modal.style.display = "none";
        document.body.classList.remove("modal-open");

        if (window.history && typeof window.history.replaceState === "function" && typeof URL === "function") {
            var url = new URL(window.location.href);
            var successParams = ["saved", "updated", "identification_number", "ticket_number"];
            for (var index = 0; index < successParams.length; index += 1) {
                url.searchParams.delete(successParams[index]);
            }
            var query = url.searchParams.toString();
            var nextUrl = url.pathname + (query ? "?" + query : "") + url.hash;
            window.history.replaceState({}, document.title, nextUrl);
        }
    };

    okButton.addEventListener("click", function (event) {
        event.preventDefault();
        closeModal();
    });

    modal.addEventListener("click", function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && modal.style.display !== "none") {
            closeModal();
        }
    });

    okButton.focus();
}());
