/**
 * PrestaShop DataLayer - Back Office Administration Script
 * Provides tabbed navigation, live search & filtering, interactive platform routing,
 * schema inspector, and diagnostics ping testing.
 */
'use strict';

(function () {
  // Wait for DOM
  document.addEventListener('DOMContentLoaded', function () {
    initTabs();
    initEventFilters();
    initModal();
    initDiagnosticsPing();
    initSettingExplainers();
  });

  /**
   * Tab Navigation with Persistent Selection
   */
  function initTabs() {
    var tabs = document.querySelectorAll('.ps-gtm-nav-tab');
    var panes = document.querySelectorAll('.ps-gtm-tab-pane');
    var activeInput = document.getElementById('ps_gtm_active_tab');

    function setActiveTab(targetTabId) {
      if (!targetTabId) return;

      tabs.forEach(function (t) {
        var tabTarget = t.getAttribute('data-tab');
        if (tabTarget === targetTabId) {
          t.classList.add('active');
        } else {
          t.classList.remove('active');
        }
      });

      panes.forEach(function (p) {
        if (p.id === targetTabId) {
          p.classList.add('active');
        } else {
          p.classList.remove('active');
        }
      });

      if (activeInput) {
        activeInput.value = targetTabId;
      }
      try {
        localStorage.setItem('ps_gtm_active_tab', targetTabId);
      } catch (e) {}
    }

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function (e) {
        e.preventDefault();
        var target = tab.getAttribute('data-tab');
        setActiveTab(target);
        if (history.replaceState) {
          history.replaceState(null, null, '#' + target);
        }
      });
    });

    // Determine initial active tab
    var initialTab = 'tab-overview';
    var hash = window.location.hash ? window.location.hash.substring(1) : '';
    var savedTab = null;
    try {
      savedTab = localStorage.getItem('ps_gtm_active_tab');
    } catch (e) {}

    if (hash && document.getElementById(hash)) {
      initialTab = hash;
    } else if (activeInput && activeInput.value && document.getElementById(activeInput.value)) {
      initialTab = activeInput.value;
    } else if (savedTab && document.getElementById(savedTab)) {
      initialTab = savedTab;
    }

    setActiveTab(initialTab);
  }

  /**
   * Events Table Live Search & Filter
   */
  function initEventFilters() {
    var searchInput = document.getElementById('ps-gtm-event-search');
    var filterPills = document.querySelectorAll('.ps-gtm-filter-pill');
    var statusSelect = document.getElementById('ps-gtm-status-filter');
    var rows = document.querySelectorAll('.ps-gtm-event-row');

    var currentCategory = 'all';
    var currentQuery = '';
    var currentStatus = 'all';

    function applyFilters() {
      rows.forEach(function (row) {
        var cat = row.getAttribute('data-category') || '';
        var name = (row.getAttribute('data-name') || '').toLowerCase();
        var label = (row.getAttribute('data-label') || '').toLowerCase();
        var desc = (row.getAttribute('data-desc') || '').toLowerCase();
        var cb = row.querySelector('.ps-gtm-evt-main-cb');
        var isEnabled = cb ? cb.checked : true;

        var matchesCat = (currentCategory === 'all' || cat === currentCategory);
        var matchesQuery = !currentQuery || (name.indexOf(currentQuery) !== -1 || label.indexOf(currentQuery) !== -1 || desc.indexOf(currentQuery) !== -1);
        var matchesStatus = (currentStatus === 'all') || (currentStatus === 'enabled' && isEnabled) || (currentStatus === 'disabled' && !isEnabled);

        if (matchesCat && matchesQuery && matchesStatus) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    }

    if (searchInput) {
      searchInput.addEventListener('input', function () {
        currentQuery = (searchInput.value || '').trim().toLowerCase();
        applyFilters();
      });
    }

    filterPills.forEach(function (pill) {
      pill.addEventListener('click', function () {
        filterPills.forEach(function (p) { p.classList.remove('active'); });
        pill.classList.add('active');
        currentCategory = pill.getAttribute('data-category') || 'all';
        applyFilters();
      });
    });

    if (statusSelect) {
      statusSelect.addEventListener('change', function () {
        currentStatus = statusSelect.value || 'all';
        applyFilters();
      });
    }
  }

  /**
   * Modal Dialog for Event Schema Details
   */
  function initModal() {
    var backdrop = document.getElementById('ps-gtm-schema-modal');
    if (!backdrop) return;

    var titleEl = document.getElementById('ps-gtm-modal-title');
    var descEl = document.getElementById('ps-gtm-modal-desc');
    var payloadEl = document.getElementById('ps-gtm-modal-payload');
    var closeBtns = backdrop.querySelectorAll('.ps-gtm-modal-close, [data-modal-dismiss]');

    function closeModal() {
      backdrop.classList.remove('open');
    }

    closeBtns.forEach(function (btn) {
      btn.addEventListener('click', closeModal);
    });

    backdrop.addEventListener('click', function (e) {
      if (e.target === backdrop) {
        closeModal();
      }
    });

    // Delegate schema inspector buttons
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.ps-gtm-inspect-btn');
      if (!btn) return;

      var eventName = btn.getAttribute('data-event');
      var schemaData = window.psGtmEventSchemas && window.psGtmEventSchemas[eventName];
      if (!schemaData) return;

      if (titleEl) titleEl.textContent = schemaData.label + ' (' + schemaData.name + ')';
      if (descEl) descEl.textContent = schemaData.desc;
      if (payloadEl) payloadEl.textContent = JSON.stringify(schemaData.example, null, 2);

      backdrop.classList.add('open');
    });
  }

  /**
   * Diagnostics Endpoint Ping Test
   */
  function initDiagnosticsPing() {
    var pingBtn = document.getElementById('ps-gtm-ping-btn');
    var resultBox = document.getElementById('ps-gtm-ping-result');
    if (!pingBtn || !resultBox) return;

    pingBtn.addEventListener('click', function () {
      pingBtn.disabled = true;
      pingBtn.innerHTML = '<i class="icon-spinner icon-spin"></i> Testing Connection...';
      resultBox.style.display = 'block';
      resultBox.className = 'alert alert-info';
      resultBox.innerHTML = '<i class="icon-refresh icon-spin"></i> Dispatched diagnostic ping to Server GTM Transport URL...';

      var ajaxUrl = window.psGtmAjaxAdminUrl || '';
      var transportUrl = document.getElementById('PS_SGTM_TRANSPORT_URL') ? document.getElementById('PS_SGTM_TRANSPORT_URL').value : '';

      var formData = new FormData();
      formData.append('ajax', '1');
      formData.append('action', 'testSgtmPing');
      formData.append('PS_SGTM_TRANSPORT_URL', transportUrl);

      fetch(ajaxUrl, {
        method: 'POST',
        body: formData
      })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        pingBtn.disabled = false;
        pingBtn.innerHTML = '<i class="icon-bolt"></i> Ping sGTM Transport URL';

        if (data.success) {
          resultBox.className = 'alert alert-success';
          resultBox.innerHTML = '<strong><i class="icon-check-circle"></i> Connection Verified!</strong><br>' +
            'Endpoint: <code>' + (data.endpoint || '') + '</code><br>' +
            'HTTP Status: <strong>' + data.http_code + '</strong> | Latency: <strong>' + data.latency_ms + ' ms</strong><br>' +
            '<span style="font-size: 11px; color: #2e7d32;">Container responded successfully. Server-to-server conversions ready.</span>';
        } else {
          resultBox.className = 'alert alert-danger';
          resultBox.innerHTML = '<strong><i class="icon-warning-sign"></i> Connection Failed!</strong><br>' +
            'Endpoint: <code>' + (data.endpoint || 'N/A') + '</code><br>' +
            'Message: ' + (data.message || data.error || 'Server unreachable') + '<br>' +
            (data.http_code ? 'HTTP Status: ' + data.http_code : '');
        }
      })
      .catch(function (err) {
        pingBtn.disabled = false;
        pingBtn.innerHTML = '<i class="icon-bolt"></i> Ping sGTM Transport URL';
        resultBox.className = 'alert alert-danger';
        resultBox.innerHTML = '<strong><i class="icon-times-circle"></i> Ping Error:</strong> ' + err.message;
      });
    });
  }

  /**
   * Learn More / Setting Explainers
   */
  function initSettingExplainers() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.ps-gtm-learn-more-btn');
      if (!btn) return;
      var targetId = btn.getAttribute('data-target');
      var panel = document.getElementById(targetId);
      if (!panel) return;

      var isHidden = (panel.style.display === 'none' || !panel.style.display);
      panel.style.display = isHidden ? 'block' : 'none';
      btn.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
      var chevron = btn.querySelector('.ps-gtm-chevron');
      if (chevron) {
        if (isHidden) chevron.classList.add('open');
        else chevron.classList.remove('open');
      }
    });
  }

  // Global window functions for inline onclick handlers
  window.psGtmSwitchTab = function (tabId) {
    var tab = document.querySelector('.ps-gtm-nav-tab[data-tab="' + tabId + '"]');
    if (tab) tab.click();
  };

  window.psGtmToggleAllEvents = function (state) {
    var cbs = document.querySelectorAll('.ps-gtm-evt-main-cb');
    cbs.forEach(function (cb) { cb.checked = state; });
  };

  window.psGtmTogglePlatformColumn = function (platformKey, state) {
    var cbs = document.querySelectorAll('.ps-gtm-evt-' + platformKey);
    cbs.forEach(function (cb) { cb.checked = state; });
    if (typeof window.psGtmUpdateCounter === 'function') {
      window.psGtmUpdateCounter(platformKey);
    }
  };

  window.psGtmSwitchRoutingView = function (mode) {
    var matrixView = document.getElementById('ps-gtm-routing-matrix-view');
    var cardsView = document.getElementById('ps-gtm-routing-cards-view');
    var btnMatrix = document.getElementById('ps-gtm-btn-view-matrix');
    var btnCards = document.getElementById('ps-gtm-btn-view-cards');

    if (mode === 'matrix') {
      if (matrixView) matrixView.style.display = 'block';
      if (cardsView) cardsView.style.display = 'none';
      if (btnMatrix) btnMatrix.classList.add('active');
      if (btnCards) btnCards.classList.remove('active');
    } else {
      if (matrixView) matrixView.style.display = 'none';
      if (cardsView) cardsView.style.display = 'block';
      if (btnMatrix) btnMatrix.classList.remove('active');
      if (btnCards) btnCards.classList.add('active');
    }
  };

  window.psGtmCopyCode = function (elemId) {
    var elem = document.getElementById(elemId);
    if (!elem) return;
    var text = elem.textContent;
    if (navigator.clipboard) {
      navigator.clipboard.writeText(text).then(function () {
        alert('Payload copied to clipboard!');
      });
    } else {
      var ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      alert('Payload copied to clipboard!');
    }
  };

  window.psGtmSelectDataMappingEvent = function (evtName) {
    var tabs = document.querySelectorAll('.ps-gtm-mapping-tab');
    var panes = document.querySelectorAll('.ps-gtm-mapping-pane');
    tabs.forEach(function (t) {
      if (t.getAttribute('data-event') === evtName) t.classList.add('active');
      else t.classList.remove('active');
    });
    panes.forEach(function (p) {
      if (p.getAttribute('data-event') === evtName) p.style.display = 'block';
      else p.style.display = 'none';
    });
  };
})();
