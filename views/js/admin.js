/**
 * PrestaShop DataLayer - Back Office Administration Script
 * Provides tabbed navigation, live search & filtering, interactive platform routing,
 * schema inspector slide-out drawer, unsaved changes tracking, and diagnostics ping testing.
 */
'use strict';

(function () {
  var isFormDirty = false;
  var currentInspectEvent = null;

  // Wait for DOM ready
  document.addEventListener('DOMContentLoaded', function () {
    initTabs();
    initEventFilters();
    initInspectDrawer();
    initUnsavedChangesTracker();
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

    // Dynamic row active switch syncing with drawer and status filter
    document.addEventListener('change', function (e) {
      if (e.target && e.target.classList.contains('ps-gtm-evt-main-cb')) {
        markFormDirty();
        var evtName = e.target.getAttribute('data-event');
        if (currentInspectEvent === evtName) {
          updateDrawerStatusPill(e.target.checked);
        }
        applyFilters();
      }
    });
  }

  /**
   * Slide-Out Inspect Drawer
   */
  function initInspectDrawer() {
    var backdrop = document.getElementById('ps-gtm-inspect-drawer-backdrop');
    if (!backdrop) return;

    var closeBtns = backdrop.querySelectorAll('.ps-gtm-drawer-close, [data-drawer-dismiss]');

    function closeDrawer() {
      backdrop.classList.remove('open');
      currentInspectEvent = null;
    }

    closeBtns.forEach(function (btn) {
      btn.addEventListener('click', closeDrawer);
    });

    backdrop.addEventListener('click', function (e) {
      if (e.target === backdrop) {
        closeDrawer();
      }
    });

    // Delegate Inspect button clicks
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.ps-gtm-inspect-btn');
      if (!btn) return;

      var eventName = btn.getAttribute('data-event');
      openInspectDrawer(eventName);
    });
  }

  function updateDrawerStatusPill(isEnabled) {
    var pill = document.getElementById('ps-gtm-drawer-status-pill');
    if (!pill) return;
    if (isEnabled) {
      pill.className = 'ps-gtm-status-pill enabled';
      pill.textContent = 'Enabled';
    } else {
      pill.className = 'ps-gtm-status-pill disabled';
      pill.textContent = 'Disabled';
    }
  }

  function openInspectDrawer(eventName) {
    var backdrop = document.getElementById('ps-gtm-inspect-drawer-backdrop');
    if (!backdrop) return;

    var schemaData = window.psGtmEventSchemas && window.psGtmEventSchemas[eventName];
    if (!schemaData) return;

    currentInspectEvent = eventName;

    // Header elements
    var titleEl = document.getElementById('ps-gtm-drawer-title');
    var iconEl = document.getElementById('ps-gtm-drawer-icon');
    var codeEl = document.getElementById('ps-gtm-drawer-event-code');
    var catEl = document.getElementById('ps-gtm-drawer-category');
    var trigEl = document.getElementById('ps-gtm-drawer-trigger');
    var srcEl = document.getElementById('ps-gtm-drawer-source');
    var depEl = document.getElementById('ps-gtm-drawer-dependencies');
    var payloadEl = document.getElementById('ps-gtm-drawer-payload');

    if (titleEl) titleEl.textContent = schemaData.label;
    if (iconEl) iconEl.className = schemaData.icon || 'icon-bolt';

    // Status pill check from table
    var rowCb = document.querySelector('input.ps-gtm-evt-main-cb[data-event="' + eventName + '"]');
    var isEnabled = rowCb ? rowCb.checked : schemaData.is_active;
    updateDrawerStatusPill(isEnabled);

    // Prefixed internal code
    var prefix = window.psGtmEventPrefix ? (window.psGtmEventPrefix + '_') : '';
    if (codeEl) codeEl.textContent = prefix + schemaData.name;

    // Details Card
    if (catEl) {
      catEl.textContent = schemaData.category_label || schemaData.category;
      catEl.className = 'ps-gtm-cat-badge ' + (schemaData.category || 'discovery');
    }
    if (trigEl) trigEl.textContent = schemaData.desc;
    if (srcEl) srcEl.textContent = schemaData.trigger_hook;
    if (depEl) depEl.textContent = schemaData.dependencies || 'PrestaShop core runtime';

    // Destination Routing Section
    renderDrawerDestinations(schemaData);

    // Data Parameters Table
    renderDrawerParameters(schemaData);

    // Platform-Specific Formatted Code Section
    renderDrawerPlatformCode(schemaData);

    // Trigger & Bus Specifications
    renderDrawerTriggerDetails(schemaData);

    // JSON Payload
    if (payloadEl) {
      payloadEl.textContent = JSON.stringify(schemaData.example, null, 2);
    }

    backdrop.classList.add('open');
  }

  function renderDrawerDestinations(schemaData) {
    var destContainer = document.getElementById('ps-gtm-drawer-dest-list');
    if (!destContainer) return;
    destContainer.innerHTML = '';

    var destinations = schemaData.destinations || {};
    var pKeys = ['meta', 'gads', 'tiktok', 'pinterest', 'microsoft', 'sgtm'];

    pKeys.forEach(function (pk) {
      var pData = destinations[pk];
      if (!pData) return;

      var card = document.createElement('div');
      card.className = 'ps-gtm-drawer-dest-card';

      var left = document.createElement('div');
      left.className = 'ps-gtm-drawer-dest-left';

      var info = document.createElement('div');
      info.innerHTML = '<strong style="color: #0f172a; font-size: 14.5px;">' + pData.name + '</strong>';

      left.appendChild(info);

      var right = document.createElement('div');
      right.style.display = 'flex';
      right.style.alignItems = 'center';
      right.style.gap = '12px';

      var badge = document.createElement('span');
      var toggleWrap = document.createElement('div');

      if (pk === 'sgtm') {
        if (pData.globally_enabled) {
          badge.className = 'ps-gtm-dest-status-badge active';
          badge.textContent = 'Configured';
        } else {
          badge.className = 'ps-gtm-dest-status-badge inactive';
          badge.textContent = 'Not Configured';
        }
      } else if (!pData.supported) {
        badge.className = 'ps-gtm-dest-status-badge unsupported';
        badge.textContent = 'Not Supported';
      } else {
        // Find underlying form input
        var matrixInput = document.querySelector('input[name="adv_' + pk + '_' + schemaData.name + '"]');
        var masterSw = document.querySelector('input[name="PS_GTM_ADV_MASTER_ENABLED"]');
        var platSw = document.querySelector('input[name="PS_GTM_ADV_' + pk.toUpperCase() + '_ENABLED"]');

        var isGloballyActive = masterSw ? masterSw.checked : true;
        if (platSw && !platSw.checked) isGloballyActive = false;

        var isRouted = matrixInput ? matrixInput.checked : pData.routed;

        if (!isGloballyActive) {
          badge.className = 'ps-gtm-dest-status-badge disabled-global';
          badge.textContent = 'Disabled Globally';
        } else if (isRouted) {
          badge.className = 'ps-gtm-dest-status-badge active';
          badge.textContent = 'Active';
        } else {
          badge.className = 'ps-gtm-dest-status-badge inactive';
          badge.textContent = 'Inactive';
        }

        // Quick toggle inside drawer
        var switchLabel = document.createElement('label');
        switchLabel.className = 'ps-gtm-switch ps-gtm-switch-sm';
        switchLabel.title = 'Route ' + schemaData.label + ' to ' + pData.short_name;

        var switchInput = document.createElement('input');
        switchInput.type = 'checkbox';
        switchInput.className = 'ps-gtm-drawer-dest-toggle';
        switchInput.checked = isRouted;
        switchInput.setAttribute('data-platform', pk);
        switchInput.setAttribute('data-event', schemaData.name);

        var slider = document.createElement('span');
        slider.className = 'ps-gtm-slider';

        switchLabel.appendChild(switchInput);
        switchLabel.appendChild(slider);
        toggleWrap.appendChild(switchLabel);

        switchInput.addEventListener('change', function () {
          markFormDirty();
          if (matrixInput) {
            matrixInput.checked = switchInput.checked;
          }

          if (!isGloballyActive) {
            badge.className = 'ps-gtm-dest-status-badge disabled-global';
            badge.textContent = 'Disabled Globally';
          } else if (switchInput.checked) {
            badge.className = 'ps-gtm-dest-status-badge active';
            badge.textContent = 'Active';
          } else {
            badge.className = 'ps-gtm-dest-status-badge inactive';
            badge.textContent = 'Inactive';
          }

          updateTableDestinationCount(schemaData.name);
        });
      }

      right.appendChild(badge);
      if (toggleWrap.hasChildNodes()) {
        right.appendChild(toggleWrap);
      }

      card.appendChild(left);
      card.appendChild(right);
      destContainer.appendChild(card);
    });
  }

  function updateTableDestinationCount(eventName) {
    var destCell = document.querySelector('.ps-gtm-dest-cell[data-event="' + eventName + '"]');
    if (!destCell) return;

    var schemaData = window.psGtmEventSchemas && window.psGtmEventSchemas[eventName];
    if (!schemaData) return;

    var count = 0;
    var activeNames = [];
    var pKeys = ['meta', 'gads', 'tiktok', 'pinterest', 'microsoft'];

    var masterSw = document.querySelector('input[name="PS_GTM_ADV_MASTER_ENABLED"]');
    var isMaster = masterSw ? masterSw.checked : true;

    pKeys.forEach(function (pk) {
      var platSw = document.querySelector('input[name="PS_GTM_ADV_' + pk.toUpperCase() + '_ENABLED"]');
      var isPlat = platSw ? platSw.checked : true;
      var matrixInput = document.querySelector('input[name="adv_' + pk + '_' + eventName + '"]');
      var isRouted = matrixInput ? matrixInput.checked : false;

      var pData = schemaData.destinations && schemaData.destinations[pk];
      if (pData && pData.supported && isMaster && isPlat && isRouted) {
        count++;
        activeNames.push(pData.short_name);
      }
    });

    if (schemaData.has_sgtm_webhook) {
      var sgtmData = schemaData.destinations && schemaData.destinations['sgtm'];
      if (sgtmData && sgtmData.globally_enabled) {
        count++;
        activeNames.push('sGTM');
      }
    }

    var countNum = destCell.querySelector('.dest-count-num');
    if (countNum) countNum.textContent = count;

    var summaryPill = destCell.querySelector('.ps-gtm-dest-summary-pill');
    if (summaryPill) summaryPill.title = activeNames.join(', ');
  }

  function renderDrawerParameters(schemaData) {
    var tbody = document.getElementById('ps-gtm-drawer-params-body');
    if (!tbody) return;
    tbody.innerHTML = '';

    var fields = schemaData.schema_fields || [];
    if (!fields.length) {
      tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: #94a3b8; padding: 18px;">No extra parameters specified.</td></tr>';
      return;
    }

    fields.forEach(function (f) {
      var tr = document.createElement('tr');
      tr.innerHTML = '<td><strong style="color: #0f172a; font-family: SFMono-Regular, Menlo, monospace;">' + f.name + '</strong></td>' +
        '<td><span class="label label-default" style="font-size: 11px; font-weight: 600; text-transform: uppercase;">' + f.type + '</span></td>' +
        '<td style="color: #475569;">' + f.source + '</td>' +
        '<td style="color: #334155;">' + (f.desc || f.destinations || '') + '</td>';
      tbody.appendChild(tr);
    });
  }

  function renderDrawerPlatformCode(schemaData) {
    var contentBox = document.getElementById('ps-gtm-drawer-platform-code-content');
    if (!contentBox) return;
    contentBox.innerHTML = '';

    var mappings = schemaData.platform_mappings || {};
    var platLabels = {
      meta: 'Meta Pixel & CAPI Mapping',
      gads: 'Google Ads Dynamic Remarketing',
      tiktok: 'TikTok Pixel & Events API',
      pinterest: 'Pinterest Tag & Conversions API',
      microsoft: 'Microsoft Advertising (UET)'
    };

    var count = 0;
    for (var pk in mappings) {
      if (!mappings.hasOwnProperty(pk)) continue;
      count++;
      var card = document.createElement('div');
      card.className = 'ps-gtm-platform-snippet-card';
      card.innerHTML = '<strong style="display: block; font-size: 13.5px; color: #0f172a; margin-bottom: 6px;">' + (platLabels[pk] || pk.toUpperCase()) + '</strong>' +
        '<code style="display: block; background: #0f172a; color: #38bdf8; padding: 8px 12px; border-radius: 6px; font-size: 12.5px; overflow-x: auto; font-family: SFMono-Regular, Menlo, monospace;">' +
        mappings[pk] + '</code>';
      contentBox.appendChild(card);
    }

    if (count === 0) {
      contentBox.innerHTML = '<div style="color: #64748b; font-size: 13.5px;">Standard Google Tag Manager dataLayer event schema.</div>';
    }
  }

  function renderDrawerTriggerDetails(schemaData) {
    var contentBox = document.getElementById('ps-gtm-drawer-trigger-details-content');
    if (!contentBox) return;

    var html = '<div style="margin-bottom: 10px;"><strong>Lifecycle Hook:</strong> <code>' + schemaData.trigger_hook + '</code></div>' +
      '<div style="margin-bottom: 10px;"><strong>Authoritative Architecture:</strong> 100% backend-driven PrestaShop data extraction with zero DOM scraping.</div>' +
      '<div><strong>Dependencies:</strong> ' + (schemaData.dependencies || 'PrestaShop core models') + '</div>';

    contentBox.innerHTML = html;
  }

  /**
   * Unsaved Changes Tracker & Save Feedback
   */
  function initUnsavedChangesTracker() {
    var form = document.querySelector('#ps-gtm-admin-wrapper form');
    if (!form) return;

    form.addEventListener('change', function () {
      markFormDirty();
    });

    form.addEventListener('input', function (e) {
      if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
        markFormDirty();
      }
    });

    form.addEventListener('submit', function () {
      var saveBtn = document.getElementById('ps-gtm-save-btn');
      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="icon-spinner icon-spin"></i> <span>Saving All Settings...</span>';
      }
      isFormDirty = false;
    });

    window.addEventListener('beforeunload', function (e) {
      if (isFormDirty) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        return e.returnValue;
      }
    });
  }

  function markFormDirty() {
    isFormDirty = true;
    var alertBar = document.getElementById('ps-gtm-unsaved-alert');
    if (alertBar) {
      alertBar.style.display = 'flex';
    }
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
            '<span style="font-size: 13px; color: #2e7d32;">Container responded successfully. Server-to-server conversions ready.</span>';
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
    markFormDirty();
    var statusSelect = document.getElementById('ps-gtm-status-filter');
    if (statusSelect && statusSelect.value !== 'all') {
      var event = new Event('change');
      statusSelect.dispatchEvent(event);
    }
  };

  window.psGtmTogglePlatformColumn = function (platformKey, state) {
    var cbs = document.querySelectorAll('.ps-gtm-evt-' + platformKey);
    cbs.forEach(function (cb) { cb.checked = state; });
    markFormDirty();
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

  window.psGtmCopyDrawerCode = function () {
    var elem = document.getElementById('ps-gtm-drawer-event-code');
    if (!elem) return;
    copyTextToClipboard(elem.textContent, 'Internal event name copied!');
  };

  window.psGtmCopyDrawerJson = function () {
    var elem = document.getElementById('ps-gtm-drawer-payload');
    if (!elem) return;
    copyTextToClipboard(elem.textContent, 'DataLayer JSON payload copied!');
  };

  window.psGtmToggleAccordion = function (elemId) {
    var body = document.getElementById(elemId);
    if (!body) return;
    var btn = body.previousElementSibling;
    var isHidden = (body.style.display === 'none' || !body.style.display);
    body.style.display = isHidden ? 'block' : 'none';
    if (btn) {
      if (isHidden) btn.classList.add('open');
      else btn.classList.remove('open');
    }
  };

  window.psGtmCopyCode = function (elemId) {
    var elem = document.getElementById(elemId);
    if (!elem) return;
    copyTextToClipboard(elem.textContent, 'Payload copied to clipboard!');
  };

  function copyTextToClipboard(text, successMsg) {
    if (navigator.clipboard) {
      navigator.clipboard.writeText(text).then(function () {
        alert(successMsg || 'Copied to clipboard!');
      });
    } else {
      var ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      alert(successMsg || 'Copied to clipboard!');
    }
  }

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
