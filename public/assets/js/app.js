// FILE: /public/assets/js/app.js
// AI WhatsApp Bot Builder - vanilla JavaScript helpers (no dependencies).
//
// Conventions used by the views:
//   <form data-confirm="Sure?">                 confirm before submit
//   <div data-show-when="field=a|b">            shown only when form field "field" has value a or b
//   <div class="chart" data-chart='{...}'>      SVG line/bar chart
//   <button data-copy="text">                   copy to clipboard
//   <form id="simulator" data-url="...">        AJAX bot simulator, replies go to #simulator-chat
//   <button data-rewrite-url="..." data-target="message">  AI-improve the textarea

(function () {
    'use strict';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    // Escapes for both element content and quoted attribute values.
    function escapeHtml(text) {
        return String(text == null ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // ---------- Confirm dialogs ----------
    function setupConfirm() {
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (form.hasAttribute && form.hasAttribute('data-confirm')) {
                if (!window.confirm(form.getAttribute('data-confirm') || 'Are you sure?')) {
                    e.preventDefault();
                }
            }
        });
    }

    // ---------- Sidebar toggle (mobile) ----------
    function setupSidebar() {
        var button = document.querySelector('[data-toggle="sidebar"]');
        var sidebar = document.getElementById('sidebar');
        if (button && sidebar) {
            button.addEventListener('click', function () {
                sidebar.classList.toggle('open');
            });
        }
    }

    // ---------- Success alerts fade out ----------
    function setupAlerts() {
        var alerts = document.querySelectorAll('.alert-success');
        Array.prototype.forEach.call(alerts, function (alert) {
            setTimeout(function () {
                alert.style.transition = 'opacity .5s';
                alert.style.opacity = '0';
                setTimeout(function () { if (alert.parentNode) { alert.parentNode.removeChild(alert); } }, 500);
            }, 6000);
        });
    }

    // ---------- Copy to clipboard ----------
    function setupCopy() {
        document.addEventListener('click', function (e) {
            var button = e.target.closest ? e.target.closest('[data-copy]') : null;
            if (!button) { return; }
            var text = button.getAttribute('data-copy');
            var done = function () {
                var original = button.textContent;
                button.textContent = 'Copied!';
                setTimeout(function () { button.textContent = original; }, 1500);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(done);
            } else {
                var area = document.createElement('textarea');
                area.value = text;
                document.body.appendChild(area);
                area.select();
                document.execCommand('copy');
                document.body.removeChild(area);
                done();
            }
        });
    }

    // ---------- Conditional form sections ----------
    function setupShowWhen() {
        var sections = document.querySelectorAll('[data-show-when]');
        if (!sections.length) { return; }

        function fieldValue(form, name) {
            var fields = form.querySelectorAll('[name="' + name + '"]');
            for (var i = 0; i < fields.length; i++) {
                var f = fields[i];
                if ((f.type === 'radio' || f.type === 'checkbox')) {
                    if (f.checked) { return f.value; }
                } else {
                    return f.value;
                }
            }
            return '';
        }

        function refresh() {
            Array.prototype.forEach.call(sections, function (section) {
                var rule = section.getAttribute('data-show-when').split('=');
                var form = section.closest('form') || document;
                var allowed = (rule[1] || '').split('|');
                var visible = allowed.indexOf(fieldValue(form, rule[0])) !== -1;
                section.style.display = visible ? '' : 'none';
            });
        }

        document.addEventListener('change', refresh);
        refresh();
    }

    // ---------- SVG charts ----------
    // data-chart = {"type":"line"|"bar","labels":[...],"series":[{"name","color","values":[...]}]}
    function renderChart(el) {
        var data;
        try { data = JSON.parse(el.getAttribute('data-chart')); } catch (err) { return; }
        var labels = data.labels || [];
        var series = data.series || [];
        if (!labels.length || !series.length) {
            el.innerHTML = '<p class="muted">No data for this period.</p>';
            return;
        }

        var width = 700, height = 240, padL = 36, padB = 28, padT = 10, padR = 10;
        var max = 1;
        series.forEach(function (s) { s.values.forEach(function (v) { if (v > max) { max = v; } }); });
        var plotW = width - padL - padR, plotH = height - padT - padB;
        var stepX = labels.length > 1 ? plotW / (labels.length - 1) : plotW;
        var svg = '<svg viewBox="0 0 ' + width + ' ' + height + '" preserveAspectRatio="none" role="img">';

        for (var g = 0; g <= 4; g++) {
            var gy = padT + plotH - (plotH * g / 4);
            svg += '<line x1="' + padL + '" x2="' + (width - padR) + '" y1="' + gy + '" y2="' + gy + '" stroke="#e5e7eb"/>';
            svg += '<text x="' + (padL - 6) + '" y="' + (gy + 4) + '" font-size="10" text-anchor="end" fill="#6b7280">' + Math.round(max * g / 4) + '</text>';
        }

        var every = Math.ceil(labels.length / 10);
        labels.forEach(function (label, i) {
            if (i % every === 0) {
                var lx = data.type === 'bar' ? padL + (plotW / labels.length) * (i + 0.5) : padL + stepX * i;
                svg += '<text x="' + lx + '" y="' + (height - 8) + '" font-size="10" text-anchor="middle" fill="#6b7280">' + escapeHtml(label) + '</text>';
            }
        });

        series.forEach(function (s, si) {
            if (data.type === 'bar') {
                var groupW = plotW / labels.length;
                var barW = Math.max(2, (groupW * 0.8) / series.length);
                s.values.forEach(function (v, i) {
                    var h = plotH * v / max;
                    var x = padL + groupW * i + groupW * 0.1 + barW * si;
                    svg += '<rect x="' + x + '" y="' + (padT + plotH - h) + '" width="' + barW + '" height="' + h + '" fill="' + s.color + '"><title>' + escapeHtml(s.name + ': ' + v) + '</title></rect>';
                });
            } else {
                var points = s.values.map(function (v, i) {
                    return (padL + stepX * i) + ',' + (padT + plotH - plotH * v / max);
                }).join(' ');
                svg += '<polyline fill="none" stroke="' + s.color + '" stroke-width="2.5" points="' + points + '"/>';
                s.values.forEach(function (v, i) {
                    svg += '<circle cx="' + (padL + stepX * i) + '" cy="' + (padT + plotH - plotH * v / max) + '" r="3" fill="' + s.color + '"><title>' + escapeHtml(labels[i] + ' - ' + s.name + ': ' + v) + '</title></circle>';
                });
            }
        });
        svg += '</svg>';

        var legend = '<div class="chart-legend">' + series.map(function (s) {
            return '<span><span class="legend-swatch" style="background:' + s.color + '"></span>' + escapeHtml(s.name) + '</span>';
        }).join('') + '</div>';
        el.innerHTML = svg + legend;
    }

    function setupCharts() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-chart]'), renderChart);
    }

    // ---------- AJAX helper ----------
    function postForm(url, params) {
        var body = new URLSearchParams();
        body.append('csrf_token', csrfToken());
        Object.keys(params).forEach(function (k) { body.append(k, params[k]); });
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (res) {
            return res.json().then(function (json) { return { ok: res.ok, json: json }; });
        });
    }

    // ---------- Bot simulator ----------
    function setupSimulator() {
        var form = document.getElementById('simulator');
        var chat = document.getElementById('simulator-chat');
        if (!form || !chat) { return; }
        var input = form.querySelector('[name="message"]');

        function bubble(direction, text, extra) {
            var div = document.createElement('div');
            div.className = 'bubble bubble-' + direction;
            div.innerHTML = escapeHtml(text) + (extra || '');
            chat.appendChild(div);
            chat.scrollTop = chat.scrollHeight;
            return div;
        }

        function send(text, reset) {
            if (!text) { return; }
            bubble('inbound', text);
            input.value = '';
            postForm(form.getAttribute('data-url'), { message: text, reset: reset ? '1' : '' }).then(function (res) {
                if (!res.ok) {
                    bubble('outbound', 'Error: ' + (res.json.error || 'request failed'));
                    return;
                }
                if (!res.json.replies.length) {
                    bubble('outbound', res.json.handed_off ? '(Conversation is assigned to an agent - the bot stays silent)' : '(No reply)');
                }
                res.json.replies.forEach(function (r) {
                    var extra = '';
                    if (r.media_url) {
                        extra += '<div class="bubble-meta">Attachment: ' + escapeHtml(r.media_url) + '</div>';
                    }
                    if (r.quick_replies && r.quick_replies.length) {
                        extra += '<div class="quick-replies">' + r.quick_replies.map(function (q) {
                            return '<button type="button" class="quick-reply" data-reply="' + escapeHtml(q) + '">' + escapeHtml(q) + '</button>';
                        }).join('') + '</div>';
                    }
                    extra += '<div class="bubble-meta">' + escapeHtml(r.triggered_by) + '</div>';
                    bubble('outbound', r.content, extra);
                });
            }).catch(function () {
                bubble('outbound', 'Network error');
            });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var reset = form.querySelector('[name="reset"]');
            send(input.value.trim(), reset && reset.checked);
            if (reset) { reset.checked = false; }
        });
        chat.addEventListener('click', function (e) {
            if (e.target.classList.contains('quick-reply')) {
                send(e.target.getAttribute('data-reply'), false);
            }
        });
    }

    // ---------- AI rewrite of an agent draft ----------
    function setupRewrite() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-rewrite-url]'), function (button) {
            button.addEventListener('click', function () {
                var target = document.getElementById(button.getAttribute('data-target'));
                if (!target || !target.value.trim()) { return; }
                button.disabled = true;
                postForm(button.getAttribute('data-rewrite-url'), { message: target.value }).then(function (res) {
                    if (res.ok && res.json.text) { target.value = res.json.text; }
                }).finally(function () { button.disabled = false; });
            });
        });
    }

    // ---------- Insert {{variable}} at cursor ----------
    function setupInsertVariable() {
        document.addEventListener('click', function (e) {
            var button = e.target.closest ? e.target.closest('[data-insert]') : null;
            if (!button) { return; }
            var target = document.getElementById(button.getAttribute('data-target'));
            if (!target) { return; }
            var text = button.getAttribute('data-insert');
            var start = target.selectionStart || target.value.length;
            target.value = target.value.slice(0, start) + text + target.value.slice(target.selectionEnd || start);
            target.focus();
        });
    }

    function init() {
        setupConfirm();
        setupSidebar();
        setupAlerts();
        setupCopy();
        setupShowWhen();
        setupCharts();
        setupSimulator();
        setupRewrite();
        setupInsertVariable();
        var chat = document.querySelector('.chat[data-autoscroll]');
        if (chat) { chat.scrollTop = chat.scrollHeight; }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
