(function () {
    var root = document.documentElement;
    var themeToggle = document.getElementById("theme-toggle");
    var recordForm = document.getElementById("record-form");
    var ticketRecordForm = document.getElementById("ticket-record-form");
    var modal = document.getElementById("saved-modal");
    var okButton = document.getElementById("saved-modal-ok");
    var scrollRestoreKey = "systemMonitoringSummaryScroll";

    var getScrollY = function () {
        return window.scrollY || document.documentElement.scrollTop || document.body.scrollTop || 0;
    };

    var saveSummaryScrollPosition = function () {
        try {
            window.sessionStorage.setItem(scrollRestoreKey, JSON.stringify({
                path: window.location.pathname,
                y: getScrollY(),
                savedAt: Date.now()
            }));
        } catch (error) {
        }
    };

    var restoreSummaryScrollPosition = function () {
        var savedScroll = null;

        try {
            savedScroll = JSON.parse(window.sessionStorage.getItem(scrollRestoreKey) || "null");
            window.sessionStorage.removeItem(scrollRestoreKey);
        } catch (error) {
            return;
        }

        if (
            !savedScroll
            || savedScroll.path !== window.location.pathname
            || typeof savedScroll.y !== "number"
            || Date.now() - savedScroll.savedAt > 60000
        ) {
            return;
        }

        var restore = function () {
            window.scrollTo(0, savedScroll.y);
        };

        window.setTimeout(restore, 0);
        window.setTimeout(restore, 100);
    };

    var isSummaryActionForm = function (form) {
        return form && (
            form.classList.contains("monitoring-action-form")
            || form.classList.contains("ticket-status-form")
        );
    };

    document.addEventListener("change", function (event) {
        var field = event.target;

        if (!field || !field.form || !isSummaryActionForm(field.form) || field.value === "") {
            return;
        }

        saveSummaryScrollPosition();
    }, true);

    document.addEventListener("submit", function (event) {
        if (isSummaryActionForm(event.target)) {
            saveSummaryScrollPosition();
        }
    }, true);

    restoreSummaryScrollPosition();

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
        var offenseInput = document.getElementById("offense");
        var pendingStatusInput = recordForm.querySelector('input[name="status[]"][value="Pending"]');
        var classificationInputs = recordForm.querySelectorAll('input[name="classification"]');
        var userErrorMessage = "USER is required when CLASSIFICATION is USER ERROR.";

        applyUppercaseBehavior(recordUppercaseFields);

        if (offenseInput && pendingStatusInput && offenseInput.dataset.incidentReportOffense) {
            var updateIncidentReportStatus = function () {
                if (
                    offenseInput.value.trim().toUpperCase() === offenseInput.dataset.incidentReportOffense.trim().toUpperCase()
                ) {
                    pendingStatusInput.checked = true;
                }
            };

            offenseInput.addEventListener("input", updateIncidentReportStatus);
            offenseInput.addEventListener("change", updateIncidentReportStatus);
            recordForm.addEventListener("submit", updateIncidentReportStatus);
            updateIncidentReportStatus();
        }

        if (userNameInput && classificationInputs.length > 0) {
            var updateUserErrorValidation = function () {
                var hasUserError = false;

                for (var inputIndex = 0; inputIndex < classificationInputs.length; inputIndex += 1) {
                    if (
                        classificationInputs[inputIndex].value === "User Error"
                        && classificationInputs[inputIndex].checked
                    ) {
                        hasUserError = true;
                        break;
                    }
                }

                if (hasUserError && !userNameInput.value.trim()) {
                    userNameInput.setCustomValidity(userErrorMessage);
                    return;
                }

                userNameInput.setCustomValidity("");
            };

            for (var classificationIndex = 0; classificationIndex < classificationInputs.length; classificationIndex += 1) {
                classificationInputs[classificationIndex].addEventListener("change", updateUserErrorValidation);
            }

            userNameInput.addEventListener("input", updateUserErrorValidation);
            recordForm.addEventListener("submit", updateUserErrorValidation);
            updateUserErrorValidation();
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
        var themeIconMarkup = {
            dark: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3a6 6 0 0 0 9 7.5A9 9 0 1 1 12 3Z"></path></svg><span class="sr-only">Switch to dark mode</span>',
            light: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2"></path><path d="M12 20v2"></path><path d="m4.93 4.93 1.41 1.41"></path><path d="m17.66 17.66 1.41 1.41"></path><path d="M2 12h2"></path><path d="M20 12h2"></path><path d="m6.34 17.66-1.41 1.41"></path><path d="m19.07 4.93-1.41 1.41"></path></svg><span class="sr-only">Switch to light mode</span>'
        };

        var updateThemeToggle = function () {
            var isDark = root.classList.contains("dark-theme");
            themeToggle.innerHTML = isDark ? themeIconMarkup.light : themeIconMarkup.dark;
            themeToggle.setAttribute("aria-pressed", isDark ? "true" : "false");
            themeToggle.setAttribute("aria-label", isDark ? "Switch to light mode" : "Switch to dark mode");
            themeToggle.setAttribute("title", isDark ? "Switch to light mode" : "Switch to dark mode");
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
