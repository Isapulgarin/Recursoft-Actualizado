/**
 * RecurSoft - Interacciones visuales de interfaz
 * (menús, contraseña, offcanvas, formularios visuales)
 */
(function () {
    "use strict";

    document.addEventListener("DOMContentLoaded", function () {
        initPasswordToggle();
        initVisualForms();
        initMobileNavClose();
        initCollapsedSidebar();
    });

    function initPasswordToggle() {
        var button = document.getElementById("togglePassword");
        var input = document.getElementById("password");
        if (!button || !input) {
            return;
        }

        button.addEventListener("click", function () {
            var hidden = input.getAttribute("type") === "password";
            input.setAttribute("type", hidden ? "text" : "password");
            var icon = button.querySelector("i");
            if (icon) {
                icon.className = hidden ? "bi bi-eye-slash" : "bi bi-eye";
            }
            button.setAttribute(
                "aria-label",
                hidden ? "Ocultar contraseña" : "Mostrar contraseña"
            );
        });
    }

   function initVisualForms() {
    document.querySelectorAll(".form-visual").forEach(function (form) {
        form.addEventListener("submit", function () {
            // Permite que el formulario se envíe normalmente al controlador.
        });
    });
}

    function initMobileNavClose() {
        var sidebar = document.getElementById("sidebarRecursoft");
        if (!sidebar || typeof bootstrap === "undefined") {
            return;
        }

        sidebar.querySelectorAll(".nav-link").forEach(function (link) {
            link.addEventListener("click", function () {
                if (window.innerWidth < 992) {
                    var instance = bootstrap.Offcanvas.getInstance(sidebar);
                    if (instance) {
                        instance.hide();
                    }
                }
            });
        });
    }

    function initCollapsedSidebar() {
        var toggle = document.getElementById("btnCollapseSidebar");
        if (!toggle) {
            return;
        }

        var stored = window.localStorage.getItem("rs-sidebar-collapsed");
        if (stored === "1" && window.innerWidth >= 992) {
            document.body.classList.add("sidebar-collapsed");
        }

        toggle.addEventListener("click", function () {
            if (window.innerWidth < 992) {
                return;
            }
            document.body.classList.toggle("sidebar-collapsed");
            window.localStorage.setItem(
                "rs-sidebar-collapsed",
                document.body.classList.contains("sidebar-collapsed") ? "1" : "0"
            );
        });
    }
})();
