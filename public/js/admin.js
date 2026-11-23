(function ($) {
    "use strict";

    var csrfToken = document.querySelector('meta[name="csrf-token"]');

    if (csrfToken) {
        $.ajaxSetup({
            headers: {
                "X-CSRF-TOKEN": csrfToken.getAttribute("content"),
                "X-Requested-With": "XMLHttpRequest",
            },
        });
    }

    function loader() {
        return document.getElementById("globalLoader");
    }

    function showLoader() {
        var element = loader();

        if (element) {
            element.classList.add("is-active");
            element.setAttribute("aria-hidden", "false");
        }
    }

    function hideLoader() {
        var element = loader();

        if (element) {
            element.classList.remove("is-active");
            element.setAttribute("aria-hidden", "true");
        }
    }

    $(document).ajaxStart(showLoader).ajaxStop(hideLoader).ajaxError(hideLoader);

    /*
     |--------------------------------------------------------------------------
     | Flash alerts
     |--------------------------------------------------------------------------
     */
    window.setTimeout(function () {
        document.querySelectorAll(".admin-alert[data-auto-dismiss]").forEach(function (element) {
            if (window.bootstrap && window.bootstrap.Alert) {
                window.bootstrap.Alert.getOrCreateInstance(element).close();
            }
        });
    }, 6000);

    /*
     |--------------------------------------------------------------------------
     | Reusable confirmation modal
     |--------------------------------------------------------------------------
     */
    var confirmElement = document.getElementById("adminConfirmModal");

    if (confirmElement && window.bootstrap) {
        var confirmModal = window.bootstrap.Modal.getOrCreateInstance(confirmElement);
        var confirmMessage = confirmElement.querySelector("[data-confirm-message]");
        var confirmAccept = confirmElement.querySelector("[data-confirm-accept]");
        var targetForm = null;

        document.addEventListener("click", function (event) {
            var trigger = event.target.closest("[data-confirm]");

            if (!trigger) {
                return;
            }

            event.preventDefault();

            targetForm = trigger.dataset.confirmForm
                ? document.querySelector(trigger.dataset.confirmForm)
                : trigger.closest("form");

            if (!targetForm) {
                return;
            }

            confirmMessage.textContent = trigger.dataset.confirm || "Are you sure?";
            confirmAccept.textContent = trigger.dataset.confirmButton || "Confirm";
            confirmAccept.className =
                "btn " + (trigger.dataset.confirmVariant === "primary" ? "btn-primary" : "btn-danger");
            confirmAccept.disabled = false;

            confirmModal.show();
        });

        confirmAccept.addEventListener("click", function () {
            if (!targetForm) {
                return;
            }

            confirmAccept.disabled = true;
            targetForm.submit();
        });

        confirmElement.addEventListener("hidden.bs.modal", function () {
            if (!confirmAccept.disabled) {
                targetForm = null;
            }
        });
    }

    /*
     |--------------------------------------------------------------------------
     | Password visibility toggle
     |--------------------------------------------------------------------------
     */
    $(document).on("click", "[data-toggle-password]", function () {
        var target = document.querySelector($(this).attr("data-target"));

        if (!target) {
            return;
        }

        var isPassword = target.getAttribute("type") === "password";

        target.setAttribute("type", isPassword ? "text" : "password");
        $(this)
            .find("i")
            .toggleClass("bi-eye bi-eye-slash");
        $(this).attr("aria-label", isPassword ? "Hide password" : "Show password");
    });

    /*
     |--------------------------------------------------------------------------
     | System status (AJAX)
     |--------------------------------------------------------------------------
     */
    var statusElement = document.getElementById("systemStatus");

    if (statusElement && statusElement.dataset.statusUrl) {
        var dot = statusElement.querySelector(".status-dot");
        var label = statusElement.querySelector(".status-label");

        $.ajax({
            url: statusElement.dataset.statusUrl,
            dataType: "json",
            global: false,
        })
            .done(function (data) {
                var healthy = data.status === "healthy";

                dot.classList.add(healthy ? "is-healthy" : "is-degraded");
                label.textContent = healthy ? "System healthy" : "Database issue";
                statusElement.title =
                    (data.database && data.database.version ? data.database.version : "Database unknown") +
                    " • " +
                    (data.timezone || "UTC");
            })
            .fail(function () {
                dot.classList.add("is-degraded");
                label.textContent = "Status unavailable";
                statusElement.title = "Unable to reach the system status endpoint.";
            });
    }

    /*
     |--------------------------------------------------------------------------
     | Prevent duplicate submissions for forms that opt in
     |--------------------------------------------------------------------------
     */
    $(document).on("submit", "form[data-submit-once]", function () {
        $(this).find('[type="submit"]').prop("disabled", true);
    });
})(jQuery);
