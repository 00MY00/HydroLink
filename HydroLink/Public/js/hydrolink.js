/*
 * HydroLink panel: forms sent with fetch (immediate feedback), automatic save of the settings (a pending save is
 * sent when leaving the page, no "leave the page?" dialog), logs loaded on demand, state refreshed every 15-30 s only
 * while the tab is visible.
 */
(function () {
    'use strict';

    var page = document.getElementById('hl-page');
    if (!page || !window.fetch) {
        return;
    }
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var dirty = {};
    // 15 s while a login is going on (the person waits for the next step), 30 s otherwise
    var REFRESH_MS = page.querySelector('[data-status="pending"], [data-status="running"], [data-status="waiting_2fa"], [data-status="waiting_captcha"]') ? 15000 : 30000;
    var toastTimer = null;

    function toast(message, isError) {
        var el = document.getElementById('hl-toast');
        el.textContent = message;
        el.className = 'hl-toast' + (isError ? ' is-error' : '');
        el.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { el.hidden = true; }, isError ? 7000 : 3500);
    }

    function send(url, options) {
        options.headers = Object.assign({ 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, options.headers || {});
        options.credentials = 'same-origin';
        return fetch(url, options).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (data) {
                if (!r.ok || data.ok === false) {
                    var msg = data.message || page.dataset.msgError;
                    if (data.errors) {
                        msg = Object.keys(data.errors).map(function (k) { return data.errors[k][0]; }).join(' ');
                    }
                    throw new Error(msg);
                }
                return data;
            });
        });
    }

    // ---- forms: fetch, busy button, message, reload when the page must show a new step
    page.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.hasAttribute('data-hl-ajax')) {
            return;
        }
        e.preventDefault();
        var button = e.submitter || form.querySelector('[type="submit"]');
        var data = new FormData(form);
        if (button && button.name) {
            data.append(button.name, button.value);
        }
        if (button) {
            button.classList.add('is-busy');
            button.disabled = true;
        }
        send(form.action, { method: 'POST', body: data }).then(function (res) {
            toast(res.message || page.dataset.msgSaved, false);
            form.querySelectorAll('input[type="password"], input[name="code"]').forEach(function (i) { i.value = ''; });
            delete dirty[formKey(form)];
            if (form.hasAttribute('data-hl-reload')) {
                setTimeout(function () { location.reload(); }, 700);
            }
        }).catch(function (err) {
            toast(err.message, true);
            if (button) {
                button.classList.remove('is-busy');
                button.disabled = false;
            }
        });
    });

    function formKey(form) {
        return 'form:' + form.action;
    }

    // ---- typing in a login form: no automatic reload of the page meanwhile (see editing())
    page.addEventListener('input', function (e) {
        var form = e.target.form;
        if (form && form.hasAttribute('data-hl-ajax') && e.target.value !== '') {
            dirty[formKey(form)] = true;
        }
    });
    // ---- automatic save of the settings (data-hl-setting)
    var timers = {};
    function settingValue(input) {
        var key = input.dataset.hlSetting;
        if (input.hasAttribute('data-hl-multi')) {
            return Array.prototype.map.call(page.querySelectorAll('[data-hl-setting="' + key + '"]:checked'), function (i) { return i.value; });
        }
        return input.type === 'checkbox' ? input.checked : input.value;
    }
    function saveSetting(key, value, leaving) {
        dirty['setting:' + key] = true;
        send(page.dataset.settingsUrl, {
            method: 'POST',
            keepalive: !!leaving,   // still sent while the page is being left
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ key: key, value: value })
        }).then(function (res) {
            delete dirty['setting:' + key];
            toast(res.message || page.dataset.msgSaved, false);
            if (key === 'vt_key' || key === 'channels') {
                setTimeout(function () { location.reload(); }, 700);
            }
        }).catch(function (err) {
            toast(err.message, true);
        });
    }
    function onSetting(e) {
        var input = e.target;
        var key = input.dataset && input.dataset.hlSetting;
        if (!key) {
            return;
        }
        var immediate = input.type === 'checkbox' || e.type === 'change';
        if (input.hasAttribute('data-hl-secret') && e.type !== 'change') {
            dirty['setting:' + key] = true;   // a secret is saved on change (leaving the field), not while typing
            return;
        }
        clearTimeout(timers[key]);
        if (immediate) {
            saveSetting(key, settingValue(input));
        } else {
            dirty['setting:' + key] = true;
            timers[key] = setTimeout(function () { saveSetting(key, settingValue(input)); }, 800);
        }
    }
    page.addEventListener('change', onSetting);
    // leaving the page: settings typed less than 800 ms ago are saved right away (settings save themselves)
    window.addEventListener('pagehide', function () {
        Object.keys(timers).forEach(function (key) {
            if (dirty['setting:' + key]) {
                clearTimeout(timers[key]);
                var input = page.querySelector('[data-hl-setting="' + key + '"]');
                if (input) {
                    saveSetting(key, settingValue(input), true);
                }
            }
        });
    });
    page.addEventListener('input', function (e) {
        if (e.target.dataset && e.target.dataset.hlSetting && e.target.type !== 'checkbox') {
            onSetting(e);
        }
    });
    page.addEventListener('click', function (e) {
        var clear = e.target.closest('[data-hl-clear]');
        if (clear) {
            saveSetting(clear.dataset.hlClear, '');
        }
    });

    // ---- logs on demand
    page.addEventListener('toggle', function (e) {
        var d = e.target;
        if (!d.open || !d.dataset || !d.dataset.hlLogs || d.dataset.loaded) {
            return;
        }
        d.dataset.loaded = '1';
        var pre = d.querySelector('pre');
        send(d.dataset.hlLogs, { method: 'GET' }).then(function (logs) {
            var lines = (logs.auth || []).concat(['', '--- hydroxide ---'], logs.serve || []);
            pre.textContent = lines.join('\n').trim() || '-';
        }).catch(function (err) {
            pre.textContent = err.message;
        });
    }, true);

    // ---- state refresh (visible tab only)
    function applyPill(el, s) {
        if (!el || !s) {
            return;
        }
        el.className = 'hl-pill hl-pill-' + s.level;
        el.querySelector('.hl-pill-label').textContent = s.label;
    }
    function editing() {
        var a = document.activeElement;
        return Object.keys(dirty).length > 0 || (a && page.contains(a) && (a.tagName === 'INPUT'));
    }
    function refresh() {
        if (document.hidden) {
            return;
        }
        send(page.dataset.stateUrl, { method: 'GET' }).then(function (st) {
            Object.keys(st.statuses || {}).forEach(function (k) {
                applyPill(page.querySelector('[data-pill="' + k + '"]'), st.statuses[k]);
            });
            var changed = false;
            page.querySelectorAll('[data-account]').forEach(function (card) {
                var a = (st.accounts || {})[card.dataset.account];
                if (!a) {
                    changed = true;
                    return;
                }
                applyPill(card.querySelector('[data-pill]'), a.pill);
                if (a.status !== card.dataset.status || String(a.paused ? 1 : 0) !== card.dataset.paused) {
                    changed = true;
                }
            });
            if (changed && !editing()) {
                location.reload();
            }
        }).catch(function () { /* next try at the next interval */ });
    }
    var timer = setInterval(refresh, REFRESH_MS);
    document.addEventListener('visibilitychange', function () {
        clearInterval(timer);
        if (!document.hidden) {
            refresh();
            timer = setInterval(refresh, REFRESH_MS);
        }
    });
    // an account waiting for the cron: look more often for its next step (still only while visible)
    if (page.querySelector('[data-status="pending"], [data-status="running"]')) {
        setTimeout(refresh, 5000);
    }
})();
