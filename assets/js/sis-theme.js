(function () {
  var storageKey = 'sis-theme';
  var root = document.documentElement;

  function safeGetTheme() {
    try { return localStorage.getItem(storageKey); } catch (e) { return null; }
  }

  function safeSetTheme(theme) {
    try { localStorage.setItem(storageKey, theme); } catch (e) {}
  }

  function preferredTheme() {
    var saved = safeGetTheme();
    if (saved === 'light' || saved === 'dark') return saved;
    return 'light';
  }

  function currentTheme() {
    return root.dataset.theme === 'dark' ? 'dark' : 'light';
  }
  function syncChartTheme(theme) {
    if (!window.Chart) return;
    var textColor = theme === 'dark' ? '#e5edf8' : '#253044';
    var gridColor = theme === 'dark' ? 'rgba(148, 163, 184, 0.22)' : 'rgba(15, 23, 42, 0.08)';
    try {
      Chart.defaults.color = textColor;
      Chart.defaults.borderColor = gridColor;
      Object.keys(Chart.instances || {}).forEach(function (key) {
        var chart = Chart.instances[key];
        if (!chart || !chart.options) return;
        var scales = chart.options.scales || {};
        Object.keys(scales).forEach(function (scaleKey) {
          scales[scaleKey].ticks = scales[scaleKey].ticks || {};
          scales[scaleKey].grid = scales[scaleKey].grid || {};
          scales[scaleKey].ticks.color = textColor;
          scales[scaleKey].grid.color = gridColor;
        });
        chart.options.plugins = chart.options.plugins || {};
        chart.options.plugins.legend = chart.options.plugins.legend || {};
        chart.options.plugins.legend.labels = chart.options.plugins.legend.labels || {};
        chart.options.plugins.legend.labels.color = textColor;
        chart.update('none');
      });
    } catch (e) {}
  }

  function syncThemeButton(theme) {
    var buttons = document.querySelectorAll('.sis-theme-toggle, [data-sis-theme-toggle]');
    if (!buttons.length) return;
    buttons.forEach(function (btn) {
      btn.innerHTML = theme === 'dark' ? '<i class="bi bi-sun"></i>' : '<i class="bi bi-moon-stars"></i>';
      btn.setAttribute('aria-label', 'Toggle theme');
      btn.setAttribute('title', 'Theme');
      btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
    });
  }

  function setTheme(theme) {
    theme = theme === 'dark' ? 'dark' : 'light';
    root.dataset.theme = theme;
    root.classList.toggle('sis-dark-root', theme === 'dark');
    root.classList.toggle('sis-light-root', theme !== 'dark');
    root.setAttribute('data-bs-theme', theme);
    if (document.body) {
      document.body.setAttribute('data-theme', theme);
      document.body.classList.toggle('sis-dark', theme === 'dark');
      document.body.classList.toggle('sis-light', theme !== 'dark');
    }
    safeSetTheme(theme);
    syncThemeButton(theme);
    syncChartTheme(theme);
    window.dispatchEvent(new CustomEvent('sis-theme-change', { detail: { theme: theme } }));
  }

  function ensureThemeButton() {
    if (document.querySelector('[data-sis-theme-toggle]')) { syncThemeButton(currentTheme()); return; }
    if (document.querySelector('.sis-theme-toggle')) { syncThemeButton(currentTheme()); return; }
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'sis-theme-toggle';
    btn.setAttribute('data-sis-theme-toggle', '1');
    btn.setAttribute('type', 'button');
    btn.dataset.sisThemeBound = '1';
    btn.addEventListener('click', function () {
      setTheme(currentTheme() === 'dark' ? 'light' : 'dark');
    });
    document.body.appendChild(btn);
  }

  function enhanceTables() {
    document.querySelectorAll('table').forEach(function (table) {
      table.classList.add('align-middle');
      if (!table.classList.contains('table')) table.classList.add('table');
      if (!table.classList.contains('table-hover') && !table.dataset.noHover) table.classList.add('table-hover');
      if (!table.closest('.table-responsive') && !table.closest('.dataTables_scroll') && !table.closest('.sis-tt-table-wrap')) {
        var wrapper = document.createElement('div');
        wrapper.className = 'table-responsive sis-auto-table-wrap';
        table.parentNode.insertBefore(wrapper, table);
        wrapper.appendChild(table);
      }
    });
  }

  function applyDynamicColors() {
    document.querySelectorAll('[data-color].sis-dynamic-color, [data-color].sis-dynamic-color-text').forEach(function (el) {
      var color = el.getAttribute('data-color') || '';
      if (!/^#?[0-9a-fA-F]{3,8}$/.test(color) && !/^rgb|^hsl/.test(color)) return;
      if (color.charAt(0) !== '#' && /^[0-9a-fA-F]{3,8}$/.test(color)) color = '#' + color;
      if (el.classList.contains('sis-dynamic-color-text')) {
        el.style.setProperty('color', color, 'important');
      } else {
        el.style.setProperty('color', color, 'important');
        el.style.setProperty('border-color', color, 'important');
        el.style.setProperty('background-color', color.length === 7 ? color + '20' : 'rgba(227, 6, 19, 0.1)', 'important');
      }
    });
  }

  function applyProgressValues() {
    document.querySelectorAll('[data-progress]').forEach(function (el) {
      var value = parseFloat(el.getAttribute('data-progress') || '0');
      if (!Number.isFinite(value)) value = 0;
      value = Math.max(0, Math.min(100, value));
      if (el.classList.contains('conversion-circle')) {
        el.style.setProperty('--conversion-rate', value + '%');
      } else {
        el.style.width = value + '%';
      }
    });
  }



  function initSidebarSearch() {
    var input = document.querySelector('[data-sis-sidebar-search]');
    if (!input || input.dataset.bound === '1') return;
    input.dataset.bound = '1';
    input.addEventListener('input', function () {
      var q = (input.value || '').toLowerCase().trim();
      document.querySelectorAll('.sis-side-section').forEach(function (section) {
        var anyVisible = false;
        section.querySelectorAll('.sis-side-link').forEach(function (link) {
          var match = !q || link.textContent.toLowerCase().indexOf(q) !== -1;
          link.classList.toggle('sis-menu-hidden', !match);
          if (match) anyVisible = true;
        });
        section.classList.toggle('sis-section-hidden', !anyVisible);
      });
    });
  }

  function normalizePage() {
    if (!document.body) return;
    document.body.classList.add('sis-ui-ready');
    document.querySelectorAll('i[class*="bi-"]').forEach(function (icon) {
      if (!icon.classList.contains('me-1') && !icon.classList.contains('me-2') && icon.parentElement && icon.parentElement.textContent.trim().length > 1) {
        icon.classList.add('me-1');
      }
      icon.setAttribute('aria-hidden', icon.getAttribute('aria-hidden') || 'true');
    });
    document.querySelectorAll('.card:not(.sis-normalized), .stat-card:not(.sis-normalized), .dashboard-card:not(.sis-normalized), .kpi-card:not(.sis-normalized), .sis-kpi-card:not(.sis-normalized), .metric-card:not(.sis-normalized), .chart-card:not(.sis-normalized), .summary-card:not(.sis-normalized), .section-card:not(.sis-normalized), .content-card:not(.sis-normalized), .support-card:not(.sis-normalized), .stock-card:not(.sis-normalized), .payment-card:not(.sis-normalized), .sis-module-hero:not(.sis-normalized)').forEach(function (card) {
      card.classList.add('sis-normalized');
    });
    document.querySelectorAll('.btn, button, .action-btn, .tab-btn, .filter-btn, .page-link').forEach(function (btn) {
      if (!btn.classList.contains('sis-click-ready')) btn.classList.add('sis-click-ready');
    });
    document.querySelectorAll('.sis-side-link, .nav-link, .dropdown-item').forEach(function (link) {
      if (!link.classList.contains('sis-hover-ready')) link.classList.add('sis-hover-ready');
    });
    applyDynamicColors();
    applyProgressValues();
  }

  // Apply the saved theme immediately to prevent pages from being stuck in the old forced-light style.
  setTheme(preferredTheme());

  function initThemeDom() {
    document.querySelectorAll('[data-sis-theme-toggle]').forEach(function (btn) {
      if (btn.dataset.sisThemeBound === '1') return;
      btn.dataset.sisThemeBound = '1';
      btn.addEventListener('click', function () { setTheme(currentTheme() === 'dark' ? 'light' : 'dark'); });
    });
    ensureThemeButton();
    setTheme(preferredTheme());
    normalizePage();
    enhanceTables();
    initSidebarSearch();

    if (!document.documentElement.dataset.sisThemeDocBound) {
      document.documentElement.dataset.sisThemeDocBound = '1';
      document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-sis-theme-toggle], .sis-theme-toggle');
        if (!btn) return;
        if (btn.dataset.sisThemeBound === '1') return;
        event.preventDefault();
        setTheme(currentTheme() === 'dark' ? 'light' : 'dark');
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initThemeDom);
  } else {
    initThemeDom();
  }

  window.SISTheme = { set: setTheme, current: currentTheme };
})();

(function () {
  if (window.SISHandleTimer && window.SISHandleTimer.__uiStandardVersion === '1') return;

  function pad(value) {
    return String(value).padStart(2, '0');
  }

  function localDateTime(date) {
    return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) + ' ' + pad(date.getHours()) + ':' + pad(date.getMinutes()) + ':' + pad(date.getSeconds());
  }

  function localTime(ms) {
    var date = new Date(ms);
    return pad(date.getHours()) + ':' + pad(date.getMinutes()) + ':' + pad(date.getSeconds());
  }

  function format(seconds) {
    seconds = Math.max(0, parseInt(seconds || 0, 10));
    var hours = Math.floor(seconds / 3600);
    var minutes = Math.floor((seconds % 3600) / 60);
    var secs = seconds % 60;
    return pad(hours) + ':' + pad(minutes) + ':' + pad(secs);
  }

  function widgetsFor(form) {
    if (form) return Array.prototype.slice.call(form.querySelectorAll('[data-handle-timer]'));
    return Array.prototype.slice.call(document.querySelectorAll('[data-handle-timer]'));
  }

  function find(widget, selector) {
    return widget ? widget.querySelector(selector) : null;
  }

  function write(widget, selector, value) {
    var el = find(widget, selector);
    if (el) el.textContent = value;
  }

  function setValue(widget, selector, value) {
    var el = find(widget, selector);
    if (el) el.value = value;
  }

  function getValue(widget, selector) {
    var el = find(widget, selector);
    return el ? el.value : '';
  }

  function setStart(widget, startedMs) {
    var ts = Math.floor(startedMs / 1000);
    widget.dataset.startedMs = String(startedMs);
    setValue(widget, '.handle-started-ts', String(ts));
    setValue(widget, '.handling-started-ms', String(Math.floor(startedMs)));
    setValue(widget, '.handle-started-at', localDateTime(new Date(startedMs)));
    write(widget, '.handle-timer-start-label', localTime(startedMs));
  }

  function getStart(widget) {
    var startedMs = parseInt(widget.dataset.startedMs || '0', 10);
    if (!startedMs) {
      var ts = parseInt(getValue(widget, '.handle-started-ts') || '0', 10);
      if (ts > 0) startedMs = ts * 1000;
    }
    if (!startedMs) {
      startedMs = Date.now();
      setStart(widget, startedMs);
    }
    return startedMs;
  }

  function updateWidget(widget) {
    if (!widget || widget.dataset.running !== '1') return;
    var startedMs = getStart(widget);
    var nowMs = Date.now();
    var seconds = Math.max(0, Math.floor((nowMs - startedMs) / 1000));
    var display = find(widget, '.handle-timer-display');
    if (display) display.textContent = format(seconds);
    write(widget, '.handle-timer-duration-label', format(seconds));
    write(widget, '.handle-timer-completed-label', localTime(nowMs));
    setValue(widget, '.handling-completed-ms', String(Math.floor(nowMs)));
    setValue(widget, '.handle-completed-at', localDateTime(new Date(nowMs)));
    setValue(widget, '.handling-seconds-client', String(seconds));
  }

  function startWidget(widget, reset) {
    if (!widget) return;
    if (widget.dataset.running === '1' && !reset) return;
    var startedMs = reset ? Date.now() : getStart(widget);
    widget.dataset.running = '1';
    setStart(widget, startedMs);
    setValue(widget, '.handling-completed-ms', '');
    setValue(widget, '.handling-seconds-client', '0');
    write(widget, '.handle-timer-duration-label', '00:00:00');
    updateWidget(widget);
  }

  function stopWidget(widget) {
    if (!widget) return;
    var startedMs = getStart(widget);
    var nowMs = Date.now();
    var seconds = Math.max(1, Math.floor((nowMs - startedMs) / 1000));
    setValue(widget, '.handling-completed-ms', String(Math.floor(nowMs)));
    setValue(widget, '.handle-completed-at', localDateTime(new Date(nowMs)));
    setValue(widget, '.handling-seconds-client', String(seconds));
    write(widget, '.handle-timer-completed-label', localTime(nowMs));
    write(widget, '.handle-timer-duration-label', format(seconds));
    var display = find(widget, '.handle-timer-display');
    if (display) display.textContent = format(seconds);
    widget.dataset.running = '0';
  }

  function bindForm(form) {
    if (!form || form.dataset.sisHandleTimerBound === '1') return;
    form.dataset.sisHandleTimerBound = '1';
    form.addEventListener('submit', function () { stopForm(form); }, true);
    form.addEventListener('input', function () { startForm(form); }, true);
    form.addEventListener('focusin', function () { startForm(form); }, true);
    form.addEventListener('pointerdown', function () { startForm(form); }, true);
  }

  function startForm(form) { widgetsFor(form).forEach(function (widget) { startWidget(widget, false); }); }
  function resetForm(form) { widgetsFor(form).forEach(function (widget) { startWidget(widget, true); }); }
  function updateForm(form) { widgetsFor(form).forEach(updateWidget); }
  function stopForm(form) { widgetsFor(form).forEach(stopWidget); return true; }

  window.SISHandleTimer = {
    __uiStandardVersion: '1',
    bindForm: bindForm,
    startForForm: startForm,
    resetForForm: resetForm,
    updateForForm: updateForm,
    stopForForm: stopForm,
    stopFormBeforeSubmit: stopForm,
    submitForm: function (form) {
      if (!form) return false;
      stopForm(form);
      if (form.requestSubmit) form.requestSubmit(); else form.submit();
      return false;
    }
  };

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form').forEach(function (form) {
      if (form.querySelector('[data-handle-timer]')) bindForm(form);
    });
    document.querySelectorAll('[data-handle-timer]').forEach(function (widget) {
      if (widget.getAttribute('data-start-on-load') === '1') startWidget(widget, false);
    });
    setInterval(function () {
      document.querySelectorAll('[data-handle-timer]').forEach(updateWidget);
    }, 1000);
  });
})();

(function () {
  function initTodayTransactions() {
    document.querySelectorAll('[data-sis-today-transactions]').forEach(function (card) {
      if (card.dataset.ttBound === '1') return;
      card.dataset.ttBound = '1';
      var search = card.querySelector('[data-tt-search]');
      var statusFilter = card.querySelector('[data-tt-status-filter]');
      var typeFilter = card.querySelector('[data-tt-type-filter]');
      var rows = Array.prototype.slice.call(card.querySelectorAll('[data-tt-row]'));
      var visibleCount = card.querySelector('[data-tt-visible-count]');
      var noResults = card.querySelector('[data-tt-no-results]');

      function apply() {
        var q = (search && search.value ? search.value : '').toLowerCase().trim();
        var st = statusFilter ? statusFilter.value : 'all';
        var ty = typeFilter ? typeFilter.value : 'all';
        var count = 0;
        rows.forEach(function (row) {
          var matchesText = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
          var matchesStatus = st === 'all' || (row.getAttribute('data-status') || '') === st;
          var matchesType = ty === 'all' || (row.getAttribute('data-type') || '') === ty;
          var show = matchesText && matchesStatus && matchesType;
          row.style.display = show ? '' : 'none';
          if (show) count += 1;
        });
        if (visibleCount) visibleCount.textContent = count;
        if (noResults) noResults.hidden = count !== 0 || rows.length === 0;
      }
      [search, statusFilter, typeFilter].forEach(function (el) {
        if (el) el.addEventListener(el.tagName === 'INPUT' ? 'input' : 'change', apply);
      });
      apply();
    });
  }

  document.addEventListener('DOMContentLoaded', initTodayTransactions);
  window.SISInitTodayTransactions = initTodayTransactions;
})();
