/* Interactions légères — aucune dépendance externe. */

(function () {
    'use strict';

    // Confirmation avant toute action destructive.
    document.addEventListener('click', function (ev) {
        const el = ev.target.closest('[data-confirm]');
        if (el && !window.confirm(el.dataset.confirm)) {
            ev.preventDefault();
            ev.stopPropagation();
        }
    });

    // Fermeture automatique des messages flash.
    document.querySelectorAll('.alert--success').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity .4s';
            alert.style.opacity = '0';
            setTimeout(function () { alert.remove(); }, 400);
        }, 5000);
    });

    // Soumission automatique des filtres (listes déroulantes).
    document.querySelectorAll('[data-auto-submit]').forEach(function (select) {
        select.addEventListener('change', function () { select.form.submit(); });
    });

    // Fermeture du menu latéral au clic extérieur (mobile).
    document.addEventListener('click', function (ev) {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar || !sidebar.classList.contains('is-open')) return;
        if (!sidebar.contains(ev.target) && !ev.target.closest('.topbar__toggle')) {
            sidebar.classList.remove('is-open');
        }
    });

    // Cohérence des dates : la date de fin ne peut précéder la date de début.
    const debut = document.querySelector('input[name="date_debut"]');
    const fin = document.querySelector('input[name="date_fin"]');
    if (debut && fin) {
        const sync = function () { fin.min = debut.value; };
        debut.addEventListener('change', sync);
        sync();
    }
})();
