var wasMobile = window.innerWidth <= 900;

// reruns the page setup bits after ajax loads new content in
function handleResponsiveLayout() {
  if (typeof initialiseMobileSidebar === "function") {
    initialiseMobileSidebar();
  }

  if (typeof initialiseThemeToggle === "function") {
    initialiseThemeToggle();
  }

  if (typeof initialiseCategoryModal === "function") {
    initialiseCategoryModal();
  }
}

$(document).ready(function () {
  handleResponsiveLayout();

  $(document).on("click", ".ajax-link", function (e) {
    e.preventDefault();

    var pageUrl = $(this).attr("href");
    var pageName = $(this).data("page-name") || "";

    loadPage(pageUrl, pageName);
  });

  // page numbers only swap the table they belong to, so the page doesn't jump back to the top
  $(document).on("click", ".js-page-link", function (e) {
    var panel = $(this).closest("[data-paginated]");
    var panelId = panel.attr("id");

    if (!panelId) {
      return; // no panel to swap - let the link load the page normally
    }

    e.preventDefault();

    var pageUrl = $(this).attr("href");
    var clickedLabel = $(this).attr("aria-label");

    $.ajax({
      method: "GET",
      url: pageUrl + (pageUrl.indexOf("?") === -1 ? "?" : "&") + "ajax=1",
      success: function (response) {
        var newPanel = $("<div>").append($.parseHTML(response)).find("#" + panelId);

        if (!newPanel.length) {
          window.location.href = pageUrl;
          return;
        }

        panel.replaceWith(newPanel);
        history.pushState(null, null, pageUrl);

        // keep keyboard users on the button they pressed (or the new current page)
        var focusTarget = newPanel.find('.js-page-link[aria-label="' + clickedLabel + '"]');
        if (!focusTarget.length) {
          focusTarget = newPanel.find(".pagination-link.is-current").attr("tabindex", "-1");
        }
        if (focusTarget.length) {
          focusTarget[0].focus({ preventScroll: true });
        }
      },
      error: function () {
        window.location.href = pageUrl;
      },
    });
  });

  // reports: the date boxes only matter for a custom range
  $(document).on("change", "#report-period", function () {
    var isCustom = $(this).val() === "custom";
    $(".report-date-field").prop("hidden", !isCustom);
    if (!isCustom) {
      $(".report-date-field input").val("");
    }
  });

  // add transaction: keep the symbol next to the amount in sync with the currency
  $(document).on("change", "#currency", function () {
    var symbol = $(this).find("option:selected").data("symbol") || "";
    $(".amount-currency-symbol").text(symbol);
  });

  $(document).on("submit", ".ajax-form", function (e) {
    e.preventDefault();

    var pageUrl = $(this).attr("action") + "?" + $(this).serialize();
    loadPage(pageUrl, "");
  });

  function loadPage(url, pageName) {
    // adds ajax=1 so php only sends back the main page content
    var ajaxUrl = url;

    if (ajaxUrl.indexOf("?") === -1) {
      ajaxUrl += "?ajax=1";
    } else {
      ajaxUrl += "&ajax=1";
    }

    $.ajax({
      method: "GET",
      url: ajaxUrl,
      success: function (response) {
        // some pages return the main tag as the root node, others have it nested
        var newMain = $(response).filter("#main-content");

        if (!newMain.length) {
          newMain = $(response).find("#main-content");
        }

        if (!newMain.length) {
          alert("Could not load page content.");
          return;
        }

        $("#main-content").replaceWith(newMain);

        $(".sidebar-nav a").removeClass("active").removeAttr("aria-current");

        $(".sidebar-nav a").each(function () {
          var href = $(this).attr("href");

          if (href === url.split("?")[0]) {
            $(this).addClass("active");
            $(this).attr("aria-current", "page");
          }
        });

        if (pageName) {
          $("#sidebar-current-page").text(pageName);
        }

        handleResponsiveLayout();

        if (typeof loadDashboardChart === "function") {
          loadDashboardChart();
        }

        if (typeof loadReportsCharts === "function") {
          loadReportsCharts();
        }

        history.pushState(null, null, url);
      },
      error: function () {
        alert("Page could not be loaded.");
      },
    });
  }

  window.addEventListener("popstate", function () {
    loadPage(window.location.pathname + window.location.search, "");
  });

  var resizeTimer;

  window.addEventListener("resize", function () {
    clearTimeout(resizeTimer);

    resizeTimer = setTimeout(function () {
      var isMobile = window.innerWidth <= 900;

      if (isMobile !== wasMobile) {
        wasMobile = isMobile;
        location.reload();
        return;
      }

      handleResponsiveLayout();
    }, 200);
  });
});

function initialiseCategoryModal() {
  var openBtn = document.getElementById("openCategoryModal");
  var closeBtn = document.getElementById("closeCategoryModal");
  var modal = document.getElementById("categoryModal");
  var overlay = document.getElementById("categoryModalOverlay");

  if (!openBtn || !closeBtn || !modal || !overlay) {
    return;
  }

  function openModal() {
    // stops the page scrolling behind the modal
    modal.classList.add("active");
    overlay.classList.add("active");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
  }

  function closeModal() {
    modal.classList.remove("active");
    overlay.classList.remove("active");
    modal.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
  }

  openBtn.onclick = openModal;
  closeBtn.onclick = closeModal;
  overlay.onclick = closeModal;

  document.onkeydown = function (e) {
    if (e.key === "Escape") {
      closeModal();
    }
  };
}
