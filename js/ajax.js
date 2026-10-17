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
