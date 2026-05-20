(function () {
    var wrap = document.getElementById('appNotifyDropdown');
    if (!wrap) return;

    var backdrop = document.getElementById('appNotifyBackdrop');
    var btn = wrap.querySelector('.app-notify-btn');
    var panel = wrap.querySelector('.app-notify-panel');
    var listEl = wrap.querySelector('.app-notify-list');
    var countEl = wrap.querySelector('.app-notify-count');
    var dotEl = wrap.querySelector('.app-notify-dot');
    var badgeEl = wrap.querySelector('.app-notify-panel__badge');
    var apiUrl = wrap.getAttribute('data-api');
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrf ? csrf.getAttribute('content') : '';

    function escapeHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function fmtTime(iso) {
        if (!iso) return '';
        var d = new Date(String(iso).replace(' ', 'T'));
        if (isNaN(d.getTime())) return String(iso);
        var now = new Date();
        var diff = (now - d) / 1000;
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function positionPanel() {
        if (!btn || !panel) return;
        var r = btn.getBoundingClientRect();
        var panelW = 320;
        var gap = 8;
        var top = r.bottom + gap;
        var right = Math.max(8, window.innerWidth - r.right);
        if (top + 400 > window.innerHeight) {
            top = Math.max(56, r.top - 400 - gap);
        }
        panel.style.top = top + 'px';
        panel.style.right = right + 'px';
        panel.style.left = 'auto';
    }

    function renderItem(it) {
        var readCls = it.read ? ' is-read' : '';
        var color = it.color || 'secondary';
        var icon = it.icon || 'bi-bell';
        return '<li class="app-notify-item' + readCls + '">' +
            '<a href="' + escapeHtml(it.url || '#') + '" class="app-notify-link" data-id="' + escapeHtml(it.id || '') + '">' +
            '<span class="app-notify-item__icon app-notify-item__icon--' + escapeHtml(color) + '"><i class="bi ' + escapeHtml(icon) + '"></i></span>' +
            '<span class="app-notify-item__body">' +
            '<span class="app-notify-item__title"><span class="app-notify-item__dot"></span>' + escapeHtml(it.title || '') + '</span>' +
            '<span class="app-notify-item__msg">' + escapeHtml(it.message || '') + '</span>' +
            '<time class="app-notify-item__time">' + escapeHtml(fmtTime(it.created_at)) + '</time>' +
            '</span></a></li>';
    }

    function renderEmpty() {
        return '<li class="app-notify-empty">' +
            '<i class="bi bi-bell-slash"></i>' +
            '<p>No new notifications</p></li>';
    }

    function bindLinks() {
        if (!listEl) return;
        listEl.querySelectorAll('.app-notify-link').forEach(function (a) {
            a.addEventListener('click', function () {
                var id = a.getAttribute('data-id');
                if (id) markRead([id]);
            });
        });
    }

    function updateBadges(unread) {
        if (countEl) {
            countEl.textContent = unread > 0 ? (unread > 9 ? '9+' : String(unread)) : '';
            countEl.hidden = unread <= 0;
        }
        if (dotEl) dotEl.hidden = unread <= 0;
        if (badgeEl) {
            badgeEl.textContent = unread > 0 ? String(unread) + ' new' : '';
            badgeEl.hidden = unread <= 0;
        }
    }

    function render(payload) {
        var items = payload.items || [];
        var unread = payload.unread || 0;
        updateBadges(unread);
        if (!listEl) return;
        if (!items.length) {
            listEl.innerHTML = renderEmpty();
            return;
        }
        listEl.innerHTML = items.map(renderItem).join('');
        bindLinks();
    }

    function fetchNotifications() {
        return fetch(apiUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.payload) render(data.payload);
                else render(data);
            })
            .catch(function () {});
    }

    function postAction(action, keys) {
        return fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ action: action, keys: keys || [], _token: csrfToken })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.payload) render(data.payload);
            });
    }

    function markRead(keys) {
        postAction('mark_read', keys);
    }

    function setOpen(open) {
        if (!panel) return;
        if (open) {
            positionPanel();
            panel.classList.add('show');
            if (backdrop) backdrop.classList.add('show');
            btn.setAttribute('aria-expanded', 'true');
            fetchNotifications();
        } else {
            panel.classList.remove('show');
            if (backdrop) backdrop.classList.remove('show');
            btn.setAttribute('aria-expanded', 'false');
        }
    }

    wrap.querySelectorAll('[data-notify-action]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var act = el.getAttribute('data-notify-action');
            if (act === 'read_all') postAction('mark_all_read');
            if (act === 'clear_all') postAction('clear_all');
        });
    });

    fetchNotifications();
    setInterval(fetchNotifications, 60000);

    if (btn && panel) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            setOpen(!panel.classList.contains('show'));
        });
        if (backdrop) {
            backdrop.addEventListener('click', function () { setOpen(false); });
        }
        document.addEventListener('click', function (e) {
            if (!wrap.contains(e.target) && !(backdrop && e.target === backdrop)) {
                setOpen(false);
            }
        });
        panel.addEventListener('click', function (e) { e.stopPropagation(); });
        window.addEventListener('resize', function () {
            if (panel.classList.contains('show')) positionPanel();
        });
    }
})();
