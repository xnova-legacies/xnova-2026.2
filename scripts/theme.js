/**
 * Bascule de theme clair / sombre (Bootstrap 5.3 data-bs-theme).
 * La preference est memorisee dans localStorage ; l'application initiale
 * (anti-flash) est faite par un script inline dans l'entete de page.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'xnova-theme';
    var DEFAULT_THEME = 'dark';

    function stored() {
        try {
            var value = localStorage.getItem(STORAGE_KEY);
            return (value === 'light' || value === 'dark') ? value : null;
        } catch (e) {
            return null;
        }
    }

    function current() {
        var attr = document.documentElement.getAttribute('data-bs-theme');
        return (attr === 'light' || attr === 'dark') ? attr : DEFAULT_THEME;
    }

    function persist(theme) {
        try {
            localStorage.setItem(STORAGE_KEY, theme);
        } catch (e) {
            /* stockage indisponible : on degrade proprement */
        }
    }

    function paint(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);

        document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
            var next = (theme === 'dark') ? 'light' : 'dark';
            button.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
            button.setAttribute('title', button.dataset['themeTitle' + (next === 'dark' ? 'Dark' : 'Light')] || '');
            button.setAttribute('aria-label', button.getAttribute('title'));

            var icon = button.querySelector('[data-theme-icon]');
            if (icon) {
                icon.className = (theme === 'dark') ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
            }
        });
    }

    function toggle() {
        var theme = (current() === 'dark') ? 'light' : 'dark';
        paint(theme);
        persist(theme);
    }

    // Aligne le DOM sur la preference stockee (au cas ou le script inline n'a pas pu s'executer).
    var saved = stored();
    if (saved !== null) {
        paint(saved);
    }

    document.addEventListener('DOMContentLoaded', function () {
        paint(current());

        document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
            button.addEventListener('click', toggle);
        });
    });
})();
