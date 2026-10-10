/* =====================================================================
   VIP Imports — JS global
   ===================================================================== */
(function ($) {
  "use strict";

  var THEME_KEY = "vip_theme";

  /* ---------- Tema (claro/noturno) ---------- */
  function applyTheme(theme) {
    document.documentElement.setAttribute("data-bs-theme", theme);
    try { localStorage.setItem(THEME_KEY, theme); } catch (e) {}
    $("[data-theme-icon]").each(function () {
      $(this).toggleClass("fa-moon", theme === "light");
      $(this).toggleClass("fa-sun", theme === "dark");
    });
    window.dispatchEvent(new Event("vipThemeChange"));
  }

  function initTheme() {
    var saved = null;
    try { saved = localStorage.getItem(THEME_KEY); } catch (e) {}
    if (!saved) {
      saved = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
    }
    applyTheme(saved);
  }

  $(document).on("click", "[data-theme-toggle]", function (e) {
    e.preventDefault();
    var current = document.documentElement.getAttribute("data-bs-theme");
    applyTheme(current === "light" ? "dark" : "light");
  });

  /* ---------- Sidebar retrátil (desktop) / off-canvas (mobile) ---------- */
  function isMobile() {
    return window.innerWidth <= 767.98;
  }

  $(document).on("click", "[data-sidebar-toggle]", function (e) {
    e.preventDefault();
    var $body = $(".app-body");
    if (isMobile()) {
      $body.toggleClass("sidebar-open");
    } else {
      $body.toggleClass("sidebar-collapsed");
    }
  });

  $(document).on("click", ".sidebar-backdrop", function () {
    $(".app-body").removeClass("sidebar-open");
  });

  $(document).on("click", "[data-sidebar-close]", function (e) {
    e.preventDefault();
    $(".app-body").removeClass("sidebar-open");
  });

  /* ---------- Mostrar/ocultar senha ---------- */
  $(document).on("click", "[data-pass-eye]", function () {
    var $input = $($(this).data("pass-eye"));
    if ($input.length === 0) { $input = $(this).closest(".input-group").find("input"); }
    var show = $input.attr("type") === "password";
    $input.attr("type", show ? "text" : "password");
    $(this).find("i").toggleClass("fa-eye", show).toggleClass("fa-eye-slash", !show);
  });

  /* ---------- Links simulados no protótipo ---------- */
  $(document).on("click", "[data-alert]", function (e) {
    e.preventDefault();
    var msg = $(this).data("alert");
    alert(msg || "Funcionalidade em construção — protótipo visual.");
  });

  /* ---------- foco nos inputs + active bottom nav (demonstrativo) ---------- */
  function setBarActive(label) {
    $(".bottom-actions .bar-btn").removeClass("primary danger");
    $(".bottom-actions [data-bar='" + label + "']").addClass("primary");
  }

  window.vipApp = {
    applyTheme: applyTheme,
    setBarActive: setBarActive
  };

  $(function () {
    initTheme();
  });

})(jQuery);