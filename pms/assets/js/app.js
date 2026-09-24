/* Police Management System - shared client behaviour. No business rules here:
   every check below is repeated on the server. */
(function () {
    'use strict';

    // Confirmation dialogs for destructive forms: <form data-confirm="Message">
    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (form.matches('form[data-confirm]')) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                ev.preventDefault();
                return;
            }
        }
        // Prevent double submissions.
        var btn = form.querySelector('button[type="submit"], input[type="submit"]');
        if (btn && !form.hasAttribute('data-no-lock')) {
            setTimeout(function () { btn.disabled = true; }, 0);
            setTimeout(function () { btn.disabled = false; }, 8000);
        }
    });

    // Bootstrap client-side validation styling.
    document.querySelectorAll('form.needs-validation').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            if (!form.checkValidity()) {
                ev.preventDefault();
                ev.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // District -> tehsil dependent selects.
    // <select data-district-select> and <select data-tehsil-select data-tehsils='{...}' data-selected="id">
    document.querySelectorAll('select[data-tehsil-select]').forEach(function (tehsilSel) {
        var map = {};
        try { map = JSON.parse(tehsilSel.getAttribute('data-tehsils') || '{}'); } catch (e) { map = {}; }
        var districtSel = document.querySelector('select[data-district-select]');
        if (!districtSel) { return; }
        function fill() {
            var selected = tehsilSel.getAttribute('data-selected') || '';
            var list = map[districtSel.value] || [];
            tehsilSel.innerHTML = '<option value="">Select tehsil</option>';
            list.forEach(function (t) {
                var o = document.createElement('option');
                o.value = t.id;
                o.textContent = t.name;
                if (String(t.id) === String(selected)) { o.selected = true; }
                tehsilSel.appendChild(o);
            });
            tehsilSel.disabled = list.length === 0;
        }
        districtSel.addEventListener('change', function () { tehsilSel.setAttribute('data-selected', ''); fill(); });
        fill();
    });

    // Auto-dismiss success alerts.
    document.querySelectorAll('.alert-success[role="alert"]').forEach(function (el) {
        setTimeout(function () {
            var a = bootstrap.Alert.getOrCreateInstance(el);
            a.close();
        }, 6000);
    });
})();
