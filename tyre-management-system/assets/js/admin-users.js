(function () {
    'use strict';

    var page = document.getElementById('saUsersPage');
    if (!page) return;

    var previewUrl = page.getAttribute('data-preview-url') || '';
    var drawer = document.getElementById('userProfileDrawer');
    var drawerBody = document.getElementById('drawerBody');
    var drawerName = document.getElementById('drawerUserName');
    var drawerEmail = document.getElementById('drawerUserEmail');
    var drawerEdit = document.getElementById('drawerEditBtn');

    function closeDrawer() {
        if (!drawer) return;
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function openDrawer() {
        if (!drawer) return;
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function renderDrawer(data) {
        if (!drawerBody) return;
        var perms = (data.permissions || []).map(function (p) {
            return '<li>' + esc(p) + '</li>';
        }).join('') || '<li>No permissions listed</li>';

        var activity = (data.recent_activity || []).map(function (a) {
            return '<li><strong>' + esc(a.action) + '</strong><small>' + esc(a.module) + ' · ' + esc(a.when) + '</small></li>';
        }).join('') || '<li class="text-muted">No recent activity</li>';

        drawerBody.innerHTML =
            '<div class="sa-drawer__section">' +
            '<h3>Personal Details</h3>' +
            '<dl class="sa-drawer__dl">' +
            '<div><dt>Full Name</dt><dd>' + esc(data.full_name) + '</dd></div>' +
            '<div><dt>Email</dt><dd>' + esc(data.email) + '</dd></div>' +
            '<div><dt>User ID</dt><dd>#' + esc(data.id) + '</dd></div>' +
            '<div><dt>Employee Code</dt><dd>' + esc(data.employee_code) + '</dd></div>' +
            '<div><dt>Designation</dt><dd>' + esc(data.designation) + '</dd></div>' +
            '<div><dt>Status</dt><dd><span class="sa-status-badge ' + esc(data.status_cls) + '">' + esc(data.status_label) + '</span></dd></div>' +
            '</dl></div>' +
            '<div class="sa-drawer__section"><h3>Department & Role</h3><dl class="sa-drawer__dl">' +
            '<div><dt>Department</dt><dd>' + esc(data.department) + '</dd></div>' +
            '<div><dt>Role</dt><dd>' + esc(data.role) + '</dd></div>' +
            '</dl></div>' +
            '<div class="sa-drawer__section"><h3>Permissions</h3><ul class="sa-drawer__perms">' + perms + '</ul></div>' +
            '<div class="sa-drawer__section"><h3>Last Login</h3><p class="mb-0 small">' + esc(data.last_login) + ' · Created ' + esc(data.created_at) + '</p></div>' +
            '<div class="sa-drawer__section"><h3>Recent Activity</h3><ul class="sa-drawer__activity">' + activity + '</ul></div>';

        if (drawerName) drawerName.textContent = data.full_name || 'User Profile';
        if (drawerEmail) drawerEmail.textContent = data.email || '';
        if (drawerEdit) {
            drawerEdit.href = data.edit_url || '#';
            drawerEdit.classList.remove('d-none');
        }
    }

    page.addEventListener('click', function (ev) {
        var viewBtn = ev.target.closest('.js-user-view');
        if (viewBtn) {
            var uid = viewBtn.getAttribute('data-user-id');
            if (!uid || !previewUrl) return;
            openDrawer();
            if (drawerBody) drawerBody.innerHTML = '<div class="sa-drawer__loading text-muted py-4">Loading profile…</div>';
            fetch(previewUrl + (previewUrl.indexOf('?') >= 0 ? '&' : '?') + 'id=' + encodeURIComponent(uid), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.error) throw new Error(data.error);
                    renderDrawer(data);
                })
                .catch(function () {
                    if (drawerBody) drawerBody.innerHTML = '<div class="text-danger py-4">Could not load user profile.</div>';
                });
            return;
        }

        var resetBtn = ev.target.closest('.js-reset-pw');
        if (resetBtn) {
            var id = resetBtn.getAttribute('data-user-id');
            var input = document.getElementById('resetPwUserId');
            var modalEl = document.getElementById('resetPwModal');
            if (!id || !input || !modalEl || !window.bootstrap) return;
            input.value = id;
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    });

    document.querySelectorAll('.js-drawer-close').forEach(function (el) {
        el.addEventListener('click', closeDrawer);
    });

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') closeDrawer();
    });

    document.querySelectorAll('.admin-confirm-form').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            var btn = ev.submitter || form.querySelector('[data-confirm]');
            var msg = btn && btn.getAttribute('data-confirm');
            if (msg && !window.confirm(msg)) ev.preventDefault();
        });
    });
})();
