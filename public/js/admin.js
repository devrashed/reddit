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

    function showToast(message, variant) {
        var container = document.getElementById("ajaxToastContainer");

        if (!container || !window.bootstrap || !window.bootstrap.Toast) {
            return;
        }

        var toastElement = document.createElement("div");
        toastElement.className = "toast align-items-center border-0 text-bg-" + (variant || "primary");
        toastElement.setAttribute("role", "alert");
        toastElement.setAttribute("aria-live", "assertive");
        toastElement.setAttribute("aria-atomic", "true");

        var wrapper = document.createElement("div");
        wrapper.className = "d-flex";

        var body = document.createElement("div");
        body.className = "toast-body";
        body.textContent = message;

        var close = document.createElement("button");
        close.type = "button";
        close.className = "btn-close btn-close-white me-2 m-auto";
        close.setAttribute("data-bs-dismiss", "toast");
        close.setAttribute("aria-label", "Close");

        wrapper.append(body, close);
        toastElement.append(wrapper);
        container.append(toastElement);

        var toast = window.bootstrap.Toast.getOrCreateInstance(toastElement, { delay: 4000 });
        toast.show();

        toastElement.addEventListener("hidden.bs.toast", function () {
            toastElement.remove();
        });
    }

    function ajaxErrorMessage(xhr, fallback) {
        if (xhr.status === 419) {
            return "Your session expired. Refresh the page and try again.";
        }

        if (xhr.responseJSON) {
            if (xhr.responseJSON.errors) {
                var errors = xhr.responseJSON.errors;
                var firstKey = Object.keys(errors)[0];

                if (firstKey && errors[firstKey] && errors[firstKey][0]) {
                    return errors[firstKey][0];
                }
            }

            if (xhr.responseJSON.message) {
                return xhr.responseJSON.message;
            }
        }

        return fallback;
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
        var confirmAjaxUrl = null;
        var confirmAjaxMethod = "DELETE";
        var confirmRemoveSelector = null;
        var confirmCountSelector = null;

        document.addEventListener("click", function (event) {
            var trigger = event.target.closest("[data-confirm]");

            if (!trigger) {
                return;
            }

            event.preventDefault();

            targetForm = trigger.dataset.confirmForm
                ? document.querySelector(trigger.dataset.confirmForm)
                : trigger.closest("form");
            confirmAjaxUrl = trigger.dataset.confirmAjax || null;
            confirmAjaxMethod = trigger.dataset.confirmMethod || "DELETE";
            confirmRemoveSelector = trigger.dataset.confirmRemove || null;
            confirmCountSelector = trigger.dataset.confirmCount || null;

            if (!targetForm && !confirmAjaxUrl) {
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
            if (confirmAjaxUrl) {
                var requestUrl = confirmAjaxUrl;
                var requestMethod = confirmAjaxMethod;
                var removeSelector = confirmRemoveSelector;
                var countSelector = confirmCountSelector;

                confirmAccept.disabled = true;

                $.ajax({
                    url: requestUrl,
                    method: requestMethod,
                    dataType: "json",
                    global: false,
                })
                    .done(function (data) {
                        confirmModal.hide();

                        if (removeSelector) {
                            document.querySelectorAll(removeSelector).forEach(function (element) {
                                element.remove();
                            });
                        }

                        if (countSelector) {
                            var countElement = document.querySelector(countSelector);

                            if (countElement) {
                                var current = parseInt(countElement.textContent.replace(/,/g, ""), 10);

                                if (!isNaN(current)) {
                                    countElement.textContent = Math.max(current - 1, 0).toLocaleString();
                                }
                            }
                        }

                        if (data.message) {
                            showToast(data.message, "success");
                        }

                        if (data.redirect) {
                            window.location.href = data.redirect;
                            return;
                        }

                        if (removeSelector && !document.querySelector("[data-ajax-row]")) {
                            window.location.reload();
                        }
                    })
                    .fail(function (xhr) {
                        confirmAccept.disabled = false;
                        showToast(ajaxErrorMessage(xhr, "Unable to complete this action."), "danger");
                    });

                return;
            }

            if (!targetForm) {
                return;
            }

            confirmAccept.disabled = true;
            targetForm.submit();
        });

        confirmElement.addEventListener("hidden.bs.modal", function () {
            targetForm = null;
            confirmAjaxUrl = null;
            confirmAjaxMethod = "DELETE";
            confirmRemoveSelector = null;
            confirmCountSelector = null;
            confirmAccept.disabled = false;
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
     | Subreddit status toggle (AJAX)
     |--------------------------------------------------------------------------
     */
    $(document).on("submit", "form[data-subreddit-status-form]", function (event) {
        event.preventDefault();

        var form = $(this);
        var button = form.find("[data-subreddit-toggle]");

        if (!button.length || button.prop("disabled")) {
            return;
        }

        var statusInput = form.find('input[name="status"]');
        var icon = button.find("[data-subreddit-toggle-icon]");
        var label = button.find("[data-subreddit-toggle-label]");
        var subredditName = button.data("name") || "subreddit";
        var originalIconClass = icon.attr("class");
        var originalLabel = label.text();

        button.prop("disabled", true);
        icon.attr("class", "spinner-border spinner-border-sm");

        $.ajax({
            url: button.data("url"),
            method: "PATCH",
            data: { status: statusInput.val() },
            dataType: "json",
            global: false,
        })
            .done(function (data) {
                var container = button.closest("[data-subreddit-row]");

                container
                    .find("[data-subreddit-status-badge]")
                    .attr("class", "badge rounded-pill " + data.badge_class)
                    .text(data.label);

                statusInput.val(data.next_status);
                icon.attr("class", "bi " + (data.next_status === "active" ? "bi-play-fill" : "bi-pause-fill"));
                label.text(data.next_label);

                button.attr({
                    title: data.next_label + " r/" + subredditName,
                    "aria-label": data.next_label + " r/" + subredditName,
                });

                button.prop("disabled", false);
                showToast(data.message || "Subreddit status updated.", "success");
            })
            .fail(function (xhr) {
                icon.attr("class", originalIconClass);
                label.text(originalLabel);
                button.prop("disabled", false);
                showToast(ajaxErrorMessage(xhr, "Unable to update the subreddit status."), "danger");
            });
    });

    /*
     |--------------------------------------------------------------------------
     | Subreddit URL preview
     |--------------------------------------------------------------------------
     */
    var nameInput = document.querySelector("[data-subreddit-name-input]");
    var urlPreview = document.querySelector("[data-subreddit-url-preview]");

    if (nameInput && urlPreview) {
        var updateUrlPreview = function () {
            var normalized = nameInput.value
                .trim()
                .replace(/^https?:\/\/(?:www\.)?reddit\.com\/r\//i, "")
                .replace(/^\/?r\//i, "")
                .replace(/\.json$/i, "")
                .replace(/^\/+|\/+$/g, "");

            urlPreview.textContent = "https://www.reddit.com/r/" + (normalized || "subreddit") + ".json";
        };

        nameInput.addEventListener("input", updateUrlPreview);
        updateUrlPreview();
    }

    /*
     |--------------------------------------------------------------------------
     | Scheduled collection time fields
     |--------------------------------------------------------------------------
     */
    var frequencySelect = document.getElementById("regular_frequency");
    var collectionTimeFields = document.querySelectorAll("[data-collection-time-field]");

    if (frequencySelect && collectionTimeFields.length > 0) {
        var syncCollectionTimeFields = function () {
            var count = parseInt(frequencySelect.value, 10) || 0;

            collectionTimeFields.forEach(function (field) {
                var visible = parseInt(field.dataset.index, 10) < count;

                field.classList.toggle("d-none", !visible);
                field.querySelectorAll("input").forEach(function (input) {
                    input.disabled = !visible;
                    input.required = visible;
                });
            });
        };

        frequencySelect.addEventListener("change", syncCollectionTimeFields);
        syncCollectionTimeFields();
    }

    /*
     |--------------------------------------------------------------------------
     | Reddit posts dashboard
     |--------------------------------------------------------------------------
     */
    var postsFilterForm = document.getElementById("postsFilterForm");
    var postsList = document.getElementById("postsList");
    var postsLoading = document.getElementById("postsLoading");
    var postsResultCount = document.getElementById("postsResultCount");
    var postsListWrap = postsList ? postsList.closest(".posts-list-wrap") : null;
    var postsSearchTimer = null;

    var setPostsLoading = function (loading) {
        if (postsLoading) {
            postsLoading.classList.toggle("d-none", !loading);
        }

        if (postsListWrap) {
            postsListWrap.classList.toggle("is-loading", loading);
        }
    };

    var loadPosts = function (url, successMessage) {
        if (!postsList) {
            return;
        }

        postsList.setAttribute("aria-busy", "true");
        setPostsLoading(true);

        $.ajax({
            url: url,
            dataType: "json",
            global: false,
        })
            .done(function (data) {
                postsList.innerHTML = data.html;

                if (postsResultCount) {
                    postsResultCount.textContent = Number(data.count || 0).toLocaleString();
                }

                if (window.history && window.history.replaceState) {
                    window.history.replaceState({}, "", url);
                }

                if (successMessage) {
                    showToast(successMessage, "success");
                }
            })
            .fail(function (xhr) {
                showToast(ajaxErrorMessage(xhr, "Unable to load Reddit posts."), "danger");
            })
            .always(function () {
                postsList.removeAttribute("aria-busy");
                setPostsLoading(false);
            });
    };

    if (postsFilterForm && postsList) {
        $(document).on("submit", "#postsFilterForm", function (event) {
            event.preventDefault();
            clearTimeout(postsSearchTimer);

            var form = this;
            var params = new URLSearchParams(new FormData(form));

            Array.from(params.keys()).forEach(function (key) {
                if (params.get(key) === "") {
                    params.delete(key);
                }
            });

            var query = params.toString();
            loadPosts(form.action + (query ? "?" + query : ""));
        });

        $(document).on("input", "[data-posts-search]", function () {
            clearTimeout(postsSearchTimer);
            postsSearchTimer = setTimeout(function () {
                $("#postsFilterForm").trigger("submit");
            }, 450);
        });

        $(document).on("change", "[data-posts-auto-submit]", function () {
            clearTimeout(postsSearchTimer);
            $("#postsFilterForm").trigger("submit");
        });

        $(document).on("click", "[data-posts-list] .pagination a", function (event) {
            var href = $(this).attr("href");

            if (!href) {
                return;
            }

            event.preventDefault();
            loadPosts(href);
        });

        $(document).on("click", "[data-posts-refresh]", function () {
            loadPosts(window.location.href, "Post list refreshed.");
        });
    }

    var reanalyzeModalElement = document.getElementById("reanalyzeConfirmModal");
    var reanalyzeModal = reanalyzeModalElement && window.bootstrap
        ? window.bootstrap.Modal.getOrCreateInstance(reanalyzeModalElement)
        : null;
    var reanalyzeTrigger = null;
    var reanalyzeUrl = null;

    var runAiAnalysis = function (button, url) {
        if (!button || !url || button.prop("disabled")) {
            return;
        }

        var originalHtml = button.html();
        var aiButtons = $("[data-analyze-post], [data-reanalyze-post]");

        aiButtons.prop("disabled", true);
        button.html(
            '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Analyzing...'
        );

        $.ajax({
            url: url,
            method: "POST",
            data: { confirm: 1 },
            dataType: "json",
            global: false,
            timeout: 180000,
        })
            .done(function (data) {
                showToast("AI analysis completed.", "success");

                if (data.analysis_url) {
                    window.setTimeout(function () {
                        window.location.assign(data.analysis_url);
                    }, 400);

                    return;
                }

                aiButtons.prop("disabled", false);
                button.html(originalHtml);
            })
            .fail(function (xhr) {
                showToast(ajaxErrorMessage(xhr, "AI analysis failed."), "danger");

                if (xhr.responseJSON && xhr.responseJSON.analysis_url) {
                    window.setTimeout(function () {
                        window.location.assign(xhr.responseJSON.analysis_url);
                    }, 700);

                    return;
                }

                window.setTimeout(function () {
                    window.location.reload();
                }, 1300);
            });
    };

    $(document).on("click", "[data-analyze-post]", function () {
        runAiAnalysis($(this), String($(this).data("url") || ""));
    });

    $(document).on("click", "[data-reanalyze-post]", function (event) {
        event.preventDefault();

        var button = $(this);
        var url = String(button.data("url") || "");

        if (!url) {
            return;
        }

        if (!reanalyzeModal) {
            if (window.confirm("Call the AI provider again and create a new analysis version?")) {
                runAiAnalysis(button, url);
            }

            return;
        }

        reanalyzeTrigger = button;
        reanalyzeUrl = url;

        var titleElement = reanalyzeModalElement.querySelector("[data-reanalyze-title]");

        if (titleElement) {
            titleElement.textContent = button.data("post-title")
                ? "Post: " + button.data("post-title")
                : "";
        }

        reanalyzeModal.show();
    });

    $(document).on("click", "[data-reanalyze-confirm]", function () {
        if (!reanalyzeTrigger || !reanalyzeUrl) {
            return;
        }

        var trigger = reanalyzeTrigger;
        var url = reanalyzeUrl;

        reanalyzeModal.hide();
        runAiAnalysis(trigger, url);
    });

    if (reanalyzeModalElement) {
        reanalyzeModalElement.addEventListener("hidden.bs.modal", function () {
            if (!reanalyzeTrigger || !reanalyzeTrigger.prop("disabled")) {
                reanalyzeTrigger = null;
                reanalyzeUrl = null;
            }
        });
    }

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
