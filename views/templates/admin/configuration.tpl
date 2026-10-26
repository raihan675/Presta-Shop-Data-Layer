{*
 * PrestaShop DataLayer - Professional Administration Interface (v1.40.1)
 * Left Sidebar Navigation Panel with Elevated Cards Architecture
 *}

<style type="text/css">
  /* Embedded Defensive Styling - Guarantees Professional Rendering Regardless of Cache */
  #ps-gtm-admin-wrapper {
    margin-top: 10px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    color: #1e293b;
  }
  .ps-gtm-brand-banner {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #1e3a8a 100%);
    border-radius: 8px;
    padding: 22px 28px;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
  }
  .ps-gtm-brand-banner .brand-left {
    display: flex;
    align-items: center;
    gap: 16px;
  }
  .ps-gtm-brand-banner .brand-logo-wrap {
    width: 52px;
    height: 52px;
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 12px;
    padding: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .ps-gtm-brand-banner .brand-logo-wrap img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
  }
  .ps-gtm-brand-banner h2 {
    color: #ffffff !important;
    margin: 0 !important;
    font-size: 23px !important;
    font-weight: 700 !important;
    letter-spacing: -0.3px;
  }
  .ps-gtm-brand-banner .brand-subtitle {
    color: #94a3b8;
    font-size: 14px;
    margin-top: 4px;
  }
  .ps-gtm-brand-banner .version-badge {
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.25);
    color: #38bdf8;
    padding: 6px 14px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 13.5px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }

  /* 2-Column Sidebar Layout */
  .ps-gtm-sidebar-card {
    background: #ffffff;
    border: 1px solid #d3d8db;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    overflow: hidden;
    margin-bottom: 24px;
  }
  .ps-gtm-sidebar-header {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 16px 20px;
    font-size: 13.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #475569;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .ps-gtm-nav-group {
    padding: 8px 0;
  }
  .ps-gtm-nav-item {
    display: flex !important;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px !important;
    color: #334155 !important;
    text-decoration: none !important;
    border-left: 4px solid transparent !important;
    border-right: none !important;
    border-top: none !important;
    border-bottom: 1px solid #f1f5f9 !important;
    background: #ffffff;
    transition: all 0.15s ease-in-out;
    cursor: pointer;
    user-select: none;
  }
  .ps-gtm-nav-item:last-child {
    border-bottom: none !important;
  }
  .ps-gtm-nav-item:hover {
    background: #f8fafc !important;
    color: #0f172a !important;
  }
  .ps-gtm-nav-item.active {
    background: #eff6ff !important;
    color: #1d4ed8 !important;
    border-left-color: #2563eb !important;
    font-weight: 600;
  }
  .ps-gtm-nav-item-content {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .ps-gtm-nav-icon {
    width: 38px;
    height: 38px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    padding: 6px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    background: #ffffff;
    border: 1px solid #e2e8f0;
    transition: all 0.2s ease;
  }
  .ps-gtm-nav-icon img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
  }
  .ps-gtm-nav-icon.blue { background: #eff6ff; border-color: #bfdbfe; }
  .ps-gtm-nav-icon.purple { background: #faf5ff; border-color: #e9d5ff; }
  .ps-gtm-nav-icon.green { background: #f0fdf4; border-color: #bbf7d0; }
  .ps-gtm-nav-icon.indigo { background: #eef2ff; border-color: #c7d2fe; }
  .ps-gtm-nav-icon.slate { background: #f8fafc; border-color: #e2e8f0; }

  .ps-gtm-nav-item.active .ps-gtm-nav-icon {
    background: #ffffff;
    border-color: #2563eb;
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.2);
    transform: scale(1.05);
  }
  .ps-gtm-nav-text-block {
    display: flex;
    flex-direction: column;
  }
  .ps-gtm-nav-main-title {
    font-size: 15px;
    font-weight: 600;
    line-height: 1.3;
    color: inherit;
  }
  .ps-gtm-nav-sub-desc {
    font-size: 12.5px;
    color: #64748b;
    margin-top: 2px;
  }
  .ps-gtm-nav-item.active .ps-gtm-nav-sub-desc {
    color: #3b82f6;
  }

  /* Sidebar Status Footer */
  .ps-gtm-sidebar-status-box {
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    font-size: 13px;
  }
  .status-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: #475569;
  }
  .status-indicator-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 6px;
  }
  .status-indicator-dot.green { background: #10b981; box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2); }
  .status-indicator-dot.amber { background: #f59e0b; }
  .status-indicator-dot.gray { background: #94a3b8; }

  /* Right Settings Cards */
  .ps-gtm-tab-pane {
    display: none;
  }
  .ps-gtm-tab-pane.active {
    display: block;
  }
  .ps-gtm-card {
    background: #ffffff;
    border: 1px solid #d3d8db;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    margin-bottom: 24px;
    overflow: hidden;
  }
  .ps-gtm-card .card-header-bar {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 18px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
  }
  .ps-gtm-card .card-header-title {
    margin: 0;
    font-size: 19px;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .ps-gtm-card .card-body-content {
    padding: 24px;
  }
  .ps-gtm-card-intro {
    font-size: 14.5px;
    color: #475569;
    margin-bottom: 22px;
    line-height: 1.55;
  }
  .ps-gtm-subcard {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #ffffff;
    padding: 20px 22px;
    margin-bottom: 20px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
  }
  .ps-gtm-subcard:last-child {
    margin-bottom: 0;
  }
  .ps-gtm-subcard-title {
    font-size: 16.5px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 4px 0;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .ps-gtm-subcard-desc {
    font-size: 13.5px;
    color: #64748b;
    margin-bottom: 16px;
    line-height: 1.5;
  }
  .ps-gtm-card-footer-bar {
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    padding: 16px 24px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
  }
  .ps-gtm-form-label {
    font-size: 15px;
    font-weight: 600;
    color: #0f172a;
    display: block;
    margin-bottom: 6px;
  }
  .ps-gtm-form-input {
    width: 100%;
    max-width: 640px;
    height: 42px;
    border: 1.5px solid #cbd5e1;
    border-radius: 6px;
    padding: 8px 14px;
    font-size: 15px;
    color: #0f172a;
    background: #ffffff;
    transition: all 0.15s ease;
  }
  .ps-gtm-form-input:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    outline: none;
  }
  .ps-gtm-help-text {
    font-size: 13.5px;
    color: #64748b;
    margin-top: 6px;
    line-height: 1.5;
  }

  /* Catalog Strategy Visual Cards */
  .ps-gtm-strategy-grid {
    display: flex;
    flex-direction: column;
    gap: 14px;
    margin-top: 14px;
  }
  .ps-gtm-strategy-card {
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    padding: 16px 20px;
    background: #ffffff;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: flex-start;
    gap: 16px;
  }
  .ps-gtm-strategy-card:hover {
    border-color: #93c5fd;
    background: #f8fafc;
  }
  .ps-gtm-strategy-card.active {
    border-color: #2563eb;
    background: #eff6ff;
    box-shadow: 0 0 0 1px #2563eb;
  }
  .ps-gtm-strategy-radio {
    margin-top: 3px;
    font-size: 18px;
    color: #cbd5e1;
  }
  .ps-gtm-strategy-card.active .ps-gtm-strategy-radio {
    color: #2563eb;
  }

  /* iOS Style Switch */
  .ps-gtm-switch {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 26px;
    margin: 0;
    cursor: pointer;
  }
  .ps-gtm-switch input {
    opacity: 0;
    width: 0;
    height: 0;
  }
  .ps-gtm-slider {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: #cbd5e1;
    transition: .25s ease;
    border-radius: 26px;
  }
  .ps-gtm-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .25s ease;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0,0,0,0.3);
  }
  .ps-gtm-switch input:checked + .ps-gtm-slider {
    background-color: #10b981;
  }
  .ps-gtm-switch input:checked + .ps-gtm-slider:before {
    transform: translateX(22px);
  }

  .ps-gtm-switch.ps-gtm-switch-sm {
    width: 40px;
    height: 22px;
  }
  .ps-gtm-switch.ps-gtm-switch-sm .ps-gtm-slider:before {
    height: 16px;
    width: 16px;
  }
  .ps-gtm-switch.ps-gtm-switch-sm input:checked + .ps-gtm-slider:before {
    transform: translateX(18px);
  }

  /* Table styling & Platform Headers */
  .ps-gtm-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
  }
  .ps-gtm-table th {
    background: #f8fafc;
    padding: 10px 6px;
    font-size: 13px;
    font-weight: 700;
    color: #475569;
    border-bottom: 2px solid #e2e8f0;
    text-align: center;
    vertical-align: middle;
  }
  .ps-gtm-platform-header-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
  }
  .ps-gtm-platform-header-title {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 3px 7px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
  }
  .ps-gtm-platform-header-title img {
    width: 16px;
    height: 16px;
    object-fit: contain;
    flex-shrink: 0;
  }
  .ps-gtm-platform-header-title span {
    font-size: 12.5px;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.2px;
    white-space: nowrap;
  }
  .ps-gtm-platform-header-controls {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
  }
  .ps-gtm-platform-toggle-pill {
    display: inline-flex;
    align-items: center;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
  }
  .ps-gtm-platform-toggle-btn {
    border: none;
    background: transparent;
    padding: 2px 6px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    line-height: 1.2;
    transition: background 0.15s ease, color 0.15s ease;
  }
  .ps-gtm-platform-toggle-btn.btn-enable {
    color: #16a34a;
    border-right: 1px solid #e2e8f0;
  }
  .ps-gtm-platform-toggle-btn.btn-enable:hover {
    background: #dcfce7;
  }
  .ps-gtm-platform-toggle-btn.btn-disable {
    color: #dc2626;
  }
  .ps-gtm-platform-toggle-btn.btn-disable:hover {
    background: #fee2e2;
  }
  .ps-gtm-table td {
    padding: 10px 8px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13.5px;
    vertical-align: middle;
    text-align: center;
    background: #ffffff;
  }
  .ps-gtm-table tr:hover td {
    background: #f8fafc;
  }

  /* Enterprise Primary Save Button Styling */
  .ps-gtm-btn-save-primary {
    background: #2563eb !important;
    border: 1.5px solid #1d4ed8 !important;
    color: #ffffff !important;
    font-size: 15px !important;
    font-weight: 600 !important;
    padding: 9px 24px !important;
    border-radius: 6px !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 8px !important;
    cursor: pointer !important;
    box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2) !important;
    transition: all 0.15s ease-in-out !important;
    text-transform: none !important;
    letter-spacing: normal !important;
  }
  .ps-gtm-btn-save-primary:hover,
  .ps-gtm-btn-save-primary:focus {
    background: #1d4ed8 !important;
    border-color: #1e40af !important;
    color: #ffffff !important;
    box-shadow: 0 4px 8px rgba(37, 99, 235, 0.3) !important;
    transform: translateY(-1px);
    outline: none !important;
  }
  .ps-gtm-btn-save-primary:active {
    background: #1e40af !important;
    transform: translateY(0);
    box-shadow: 0 1px 2px rgba(37, 99, 235, 0.2) !important;
  }

  /* Unsaved Floating Alert */
  .ps-gtm-unsaved-alert {
    position: fixed;
    bottom: 24px;
    right: 28px;
    background: #ffffff;
    border: 1.5px solid #f59e0b;
    border-radius: 10px;
    padding: 14px 22px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    gap: 16px;
    z-index: 99999;
  }

  /* ==========================================================================
     Tab 3: Modern Advertising Routing Matrix & Grouped Layout
     ========================================================================== */
  .ps-gtm-routing-header-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 18px 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
  }
  .ps-gtm-routing-header-left {
    display: flex;
    align-items: center;
    gap: 14px;
  }
  .ps-gtm-routing-header-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: #eff6ff;
    color: #2563eb;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
  }
  .ps-gtm-routing-title {
    font-size: 17.5px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 3px 0;
    line-height: 1.25;
  }
  .ps-gtm-routing-subtitle {
    font-size: 13.5px;
    color: #64748b;
    margin: 0;
  }
  .ps-gtm-master-switch-pill {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 30px;
    padding: 6px 16px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
  }
  .ps-gtm-master-switch-label {
    font-size: 13.5px;
    font-weight: 700;
    color: #1e293b;
    letter-spacing: -0.2px;
  }
  .ps-gtm-master-status-text {
    font-size: 12.5px;
    font-weight: 700;
    min-width: 28px;
    text-transform: uppercase;
  }
  .ps-gtm-master-status-text.on {
    color: #10b981;
  }
  .ps-gtm-master-status-text.off {
    color: #94a3b8;
  }

  .ps-gtm-routing-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
  }
  .ps-gtm-view-switcher {
    display: inline-flex;
    background: #e2e8f0;
    padding: 3px;
    border-radius: 8px;
    gap: 2px;
  }
  .ps-gtm-view-pill {
    border: none;
    background: transparent;
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    line-height: 1.2;
  }
  .ps-gtm-view-pill:hover {
    color: #0f172a;
  }
  .ps-gtm-view-pill.active {
    background: #ffffff;
    color: #0f172a;
    box-shadow: 0 1px 2px rgba(0,0,0,0.08);
    font-weight: 700;
  }
  .ps-gtm-bulk-actions {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }
  .ps-gtm-btn-bulk-enable {
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #059669;
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s ease;
  }
  .ps-gtm-btn-bulk-enable:hover {
    background: #d1fae5;
    border-color: #6ee7b7;
    color: #047857;
  }
  .ps-gtm-btn-bulk-disable {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #dc2626;
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s ease;
  }
  .ps-gtm-btn-bulk-disable:hover {
    background: #fee2e2;
    border-color: #fca5a5;
    color: #b91c1c;
  }

  /* Routing Scroll Wrapper for Responsive Viewports */
  .ps-gtm-routing-container-scroll {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 6px;
  }
  .ps-gtm-routing-inner-minwidth {
    min-width: 760px;
  }

  /* Group Card Container */
  .ps-gtm-group-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    margin-bottom: 18px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    overflow: hidden;
    transition: border-color 0.15s ease;
  }
  .ps-gtm-group-card:hover {
    border-color: #cbd5e1;
  }

  /* Group Header */
  .ps-gtm-group-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 18px;
    border-bottom: 1px solid #e2e8f0;
    cursor: pointer;
    user-select: none;
    transition: background 0.15s ease;
  }
  .ps-gtm-group-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .ps-gtm-group-chevron {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    color: inherit;
    transition: transform 0.2s ease;
    font-size: 13px;
  }
  .ps-gtm-group-chevron.collapsed {
    transform: rotate(-90deg);
  }
  .ps-gtm-group-title {
    font-size: 15px;
    font-weight: 700;
    letter-spacing: -0.2px;
  }
  .ps-gtm-group-badge {
    font-size: 11.5px;
    font-weight: 700;
    padding: 2px 9px;
    border-radius: 12px;
    letter-spacing: normal;
  }

  .ps-gtm-group-header-right {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0;
    min-width: 440px;
    max-width: 440px;
  }
  .ps-gtm-platform-col-head {
    width: 88px;
    min-width: 88px;
    max-width: 88px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 4px 0;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.15s ease;
  }
  .ps-gtm-platform-col-head:hover {
    background: rgba(0,0,0,0.05);
  }
  .ps-gtm-platform-col-head img {
    width: 20px;
    height: 20px;
    object-fit: contain;
    flex-shrink: 0;
  }
  .ps-gtm-platform-col-head span {
    font-size: 12px;
    font-weight: 700;
    color: #1e293b;
    white-space: nowrap;
    letter-spacing: -0.2px;
  }

  /* Group Body & Rows */
  .ps-gtm-group-body {
    transition: max-height 0.25s ease;
  }
  .ps-gtm-group-body.collapsed {
    display: none;
  }
  .ps-gtm-event-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 18px;
    border-bottom: 1px solid #f1f5f9;
    background: #ffffff;
    transition: background 0.15s ease;
  }
  .ps-gtm-event-row:last-child {
    border-bottom: none;
  }
  .ps-gtm-event-row:hover {
    background: #f8fafc;
  }
  .ps-gtm-event-row-left {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 260px;
  }
  .ps-gtm-row-select-cb {
    width: 16px;
    height: 16px;
    accent-color: #2563eb;
    cursor: pointer;
    margin: 0;
  }
  .ps-gtm-event-icon-badge {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
  }
  .ps-gtm-event-name-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }
  .ps-gtm-event-label {
    font-size: 14.5px;
    font-weight: 600;
    color: #0f172a;
    letter-spacing: -0.2px;
  }
  .ps-gtm-event-code {
    font-size: 12px;
    color: #64748b;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 1px 7px;
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  }
  .ps-gtm-info-circle-btn {
    border: none;
    background: transparent;
    color: #94a3b8;
    font-size: 16px;
    cursor: pointer;
    padding: 2px 4px;
    border-radius: 50%;
    transition: color 0.15s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .ps-gtm-info-circle-btn:hover {
    color: #2563eb;
  }

  .ps-gtm-event-row-right {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0;
    min-width: 440px;
    max-width: 440px;
  }
  .ps-gtm-switch-cell {
    width: 88px;
    min-width: 88px;
    max-width: 88px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  /* Full List Flat Header */
  .ps-gtm-flat-header {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-bottom: 2px solid #cbd5e1;
    border-top-left-radius: 10px;
    border-top-right-radius: 10px;
    padding: 12px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  .ps-gtm-flat-header-left {
    font-size: 14px;
    font-weight: 700;
    color: #334155;
  }
  .ps-gtm-flat-header-right {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0;
    min-width: 440px;
    max-width: 440px;
  }

  /* Modern Footer Bar */
  .ps-gtm-routing-footer-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    padding: 16px 20px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    margin-top: 18px;
  }
  .ps-gtm-routing-footer-left {
    display: flex;
    align-items: center;
    gap: 14px;
  }
  .ps-gtm-footer-counter-badge {
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 5px 12px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .ps-gtm-footer-unsaved-pill {
    font-size: 12.5px;
    font-weight: 600;
    color: #b45309;
    background: #fef3c7;
    border: 1px solid #fde68a;
    border-radius: 12px;
    padding: 4px 10px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .ps-gtm-unsaved-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #f59e0b;
    box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.25);
  }
  .ps-gtm-routing-footer-right {
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .ps-gtm-btn-reset {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #475569;
    font-size: 14px;
    font-weight: 600;
    padding: 8px 18px;
    border-radius: 6px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s ease;
  }
  .ps-gtm-btn-reset:hover {
    background: #f8fafc;
    color: #0f172a;
    border-color: #94a3b8;
  }
</style>

<div id="ps-gtm-admin-wrapper">

  <!-- Header Banner -->
  <div class="ps-gtm-brand-banner">
    <div class="brand-left">
      <div class="brand-logo-wrap">
        <img src="{$module_logo|escape:'html':'UTF-8'}" alt="{$module_display_name|replace:'&amp;':'&'|escape:'html':'UTF-8'}">
      </div>
      <div>
        <h2>{$module_display_name|replace:'&amp;':'&' nofilter}</h2>
        <div class="brand-subtitle">
          {l s='Enterprise GA4 Ecommerce, Server-Side GTM & Dynamic Advertising Remarketing' mod='ps_gtm_datalayer'}
        </div>
      </div>
    </div>
    <div>
      <span class="version-badge"><i class="icon-code-fork"></i> v{$module_version|escape:'html':'UTF-8'}</span>
    </div>
  </div>

  <!-- Unified Settings Form -->
  <form action="{$form_action|escape:'html':'UTF-8'}" method="post" id="ps_gtm_main_form" class="defaultForm form-horizontal">
    {if !empty($admin_token)}<input type="hidden" name="token" value="{$admin_token|escape:'html':'UTF-8'}">{/if}
    <input type="hidden" name="submitps_gtm_datalayer" value="1">
    <input type="hidden" name="PS_GTM_CORE_SUBMITTED" value="1">
    <input type="hidden" name="PS_GTM_ADV_SUBMITTED" value="1">
    <input type="hidden" name="PS_GTM_EVENTS_SUBMITTED" value="1">
    <input type="hidden" name="active_tab" id="ps_gtm_active_tab" value="{$active_tab|escape:'html':'UTF-8'}">

    <!-- 2-Column Responsive Layout -->
    <div class="row">

      <!-- LEFT SIDEBAR PANEL (col-lg-3 col-md-4) -->
      <div class="col-lg-3 col-md-4 col-sm-12">
        <div class="ps-gtm-sidebar-card">
          <div class="ps-gtm-sidebar-header">
            <i class="icon-sliders text-primary"></i> {l s='Configuration Menu' mod='ps_gtm_datalayer'}
          </div>

          <div class="ps-gtm-nav-group">
            <!-- 1. GTM Web Container -->
            <a href="#tab-config" class="ps-gtm-nav-item ps-gtm-nav-tab active" data-tab="tab-config">
              <div class="ps-gtm-nav-item-content">
                <div class="ps-gtm-nav-icon blue">
                  <img src="{$module_path|escape:'html':'UTF-8'}views/img/gtm_tag.png" alt="GTM Web Container">
                </div>
                <div class="ps-gtm-nav-text-block">
                  <span class="ps-gtm-nav-main-title">{l s='GTM Web Container' mod='ps_gtm_datalayer'}</span>
                  <span class="ps-gtm-nav-sub-desc">{l s='Container ID & Prefix' mod='ps_gtm_datalayer'}</span>
                </div>
              </div>
              <i class="icon-chevron-right" style="color: #cbd5e1; font-size: 12px;"></i>
            </a>

            <!-- 2. Product Catalog Strategy -->
            <a href="#tab-catalog" class="ps-gtm-nav-item ps-gtm-nav-tab" data-tab="tab-catalog">
              <div class="ps-gtm-nav-item-content">
                <div class="ps-gtm-nav-icon purple">
                  <img src="{$module_path|escape:'html':'UTF-8'}views/img/catalog_strategy.png" alt="Catalog Strategy">
                </div>
                <div class="ps-gtm-nav-text-block">
                  <span class="ps-gtm-nav-main-title">{l s='Catalog Strategy' mod='ps_gtm_datalayer'}</span>
                  <span class="ps-gtm-nav-sub-desc">{l s='Identifier Resolution Feeds' mod='ps_gtm_datalayer'}</span>
                </div>
              </div>
              <i class="icon-chevron-right" style="color: #cbd5e1; font-size: 12px;"></i>
            </a>

            <!-- 3. Advertising Platforms & Routing -->
            <a href="#tab-routing" class="ps-gtm-nav-item ps-gtm-nav-tab" data-tab="tab-routing">
              <div class="ps-gtm-nav-item-content">
                <div class="ps-gtm-nav-icon green">
                  <img src="{$module_path|escape:'html':'UTF-8'}views/img/advertising_routing.png" alt="Advertising Platforms">
                </div>
                <div class="ps-gtm-nav-text-block">
                  <span class="ps-gtm-nav-main-title">{l s='Advertising Platforms' mod='ps_gtm_datalayer'}</span>
                  <span class="ps-gtm-nav-sub-desc">{l s='Meta, Google, TikTok, Bing' mod='ps_gtm_datalayer'}</span>
                </div>
              </div>
              <span class="badge {if $active_platforms_count > 0}badge-success{else}badge-default{/if}">{$active_platforms_count|escape:'html':'UTF-8'}/5</span>
            </a>

            <!-- 4. Server-Side & Webhooks -->
            <a href="#tab-sgtm" class="ps-gtm-nav-item ps-gtm-nav-tab" data-tab="tab-sgtm">
              <div class="ps-gtm-nav-item-content">
                <div class="ps-gtm-nav-icon indigo">
                  <img src="{$module_path|escape:'html':'UTF-8'}views/img/server_side.png" alt="Server-Side & Webhooks">
                </div>
                <div class="ps-gtm-nav-text-block">
                  <span class="ps-gtm-nav-main-title">{l s='Server-Side & Webhooks' mod='ps_gtm_datalayer'}</span>
                  <span class="ps-gtm-nav-sub-desc">{l s='sGTM Transport & S2S' mod='ps_gtm_datalayer'}</span>
                </div>
              </div>
              <i class="icon-chevron-right" style="color: #cbd5e1; font-size: 12px;"></i>
            </a>

            <!-- 5. Custom Scripts -->
            <a href="#tab-scripts" class="ps-gtm-nav-item ps-gtm-nav-tab" data-tab="tab-scripts">
              <div class="ps-gtm-nav-item-content">
                <div class="ps-gtm-nav-icon slate">
                  <img src="{$module_path|escape:'html':'UTF-8'}views/img/custom_scripts.png" alt="Custom Scripts">
                </div>
                <div class="ps-gtm-nav-text-block">
                  <span class="ps-gtm-nav-main-title">{l s='Custom Scripts' mod='ps_gtm_datalayer'}</span>
                  <span class="ps-gtm-nav-sub-desc">{l s='Direct <head> & <body>' mod='ps_gtm_datalayer'}</span>
                </div>
              </div>
              <i class="icon-chevron-right" style="color: #cbd5e1; font-size: 12px;"></i>
            </a>
          </div>

          <!-- Sidebar Status Breakdown -->
          <div class="ps-gtm-sidebar-status-box">
            <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; margin-bottom: 4px;">
              {l s='Live Status Overview' mod='ps_gtm_datalayer'}
            </div>
            <div class="status-row">
              <span><span class="status-indicator-dot {if !empty($config_values.PS_GTM_CONTAINER_ID)}green{else}amber{/if}"></span> GTM Web:</span>
              <strong>{if !empty($config_values.PS_GTM_CONTAINER_ID)}<code>{$config_values.PS_GTM_CONTAINER_ID|escape:'html':'UTF-8'}</code>{else}<span class="text-warning">{l s='Not Set' mod='ps_gtm_datalayer'}</span>{/if}</strong>
            </div>
            <div class="status-row">
              <span><span class="status-indicator-dot {if $config_values.PS_GTM_ADV_MASTER_ENABLED}green{else}amber{/if}"></span> Advertising:</span>
              <strong>{if $config_values.PS_GTM_ADV_MASTER_ENABLED}<span class="text-success">{l s='Active' mod='ps_gtm_datalayer'}</span>{else}<span class="text-muted">{l s='Disabled' mod='ps_gtm_datalayer'}</span>{/if}</strong>
            </div>
            <div class="status-row">
              <span><span class="status-indicator-dot {if !empty($config_values.PS_SGTM_TRANSPORT_URL)}green{else}gray{/if}"></span> sGTM:</span>
              <strong>{if !empty($config_values.PS_SGTM_TRANSPORT_URL)}<span class="text-success">{l s='Connected' mod='ps_gtm_datalayer'}</span>{else}<span class="text-muted">{l s='Optional' mod='ps_gtm_datalayer'}</span>{/if}</strong>
            </div>
          </div>
        </div>
      </div>

      <!-- RIGHT SETTINGS CONTENT PANELS (col-lg-9 col-md-8) -->
      <div class="col-lg-9 col-md-8 col-sm-12">

        <!-- ============================================================= -->
        <!-- TAB 1: GTM & DATALAYER CORE                                   -->
        <!-- ============================================================= -->
        <div id="tab-config" class="ps-gtm-tab-pane active">
          <div class="ps-gtm-card">
            <div class="card-header-bar">
              <h3 class="card-header-title">
                <i class="icon-google text-primary"></i> {l s='Google Tag Manager Web Container Settings' mod='ps_gtm_datalayer'}
              </h3>
              <span class="label {if !empty($config_values.PS_GTM_CONTAINER_ID)}label-success{else}label-warning{/if}">
                {if !empty($config_values.PS_GTM_CONTAINER_ID)}{l s='Configured' mod='ps_gtm_datalayer'}{else}{l s='Awaiting Setup' mod='ps_gtm_datalayer'}{/if}
              </span>
            </div>

            <div class="card-body-content">
              <div class="ps-gtm-card-intro">
                {l s='Configure your Google Tag Manager web container. The module will automatically inject the official asynchronous tracking scripts and initialize window.dataLayer across all storefront pages.' mod='ps_gtm_datalayer'}
              </div>

              <!-- Sub-card: GTM Container ID -->
              <div class="ps-gtm-subcard">
                <h4 class="ps-gtm-subcard-title">
                  <i class="icon-tag text-primary"></i> {l s='Primary GTM Container ID' mod='ps_gtm_datalayer'}
                </h4>
                <div class="ps-gtm-subcard-desc">
                  {l s='Your unique Google Tag Manager container identifier (e.g., GTM-XXXXXXX). Found in your GTM Admin dashboard.' mod='ps_gtm_datalayer'}
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                  <input type="text" name="PS_GTM_CONTAINER_ID" id="PS_GTM_CONTAINER_ID" class="ps-gtm-form-input" value="{$config_values.PS_GTM_CONTAINER_ID|escape:'html':'UTF-8'}" placeholder="GTM-XXXXXXX">
                  <div class="ps-gtm-help-text">
                    <i class="icon-info-circle"></i> {l s='Leave empty if using server-side only container or third-party container loaders.' mod='ps_gtm_datalayer'}
                  </div>
                </div>
              </div>

              <!-- Sub-card: Event Prefix -->
              <div class="ps-gtm-subcard">
                <h4 class="ps-gtm-subcard-title">
                  <i class="icon-edit text-primary"></i> {l s='DataLayer Event Prefix (Optional)' mod='ps_gtm_datalayer'}
                </h4>
                <div class="ps-gtm-subcard-desc">
                  {l s='Custom identifier prepended before standard GA4 event names (e.g., entering "ps" results in ps_view_item, ps_add_to_cart, ps_purchase).' mod='ps_gtm_datalayer'}
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                  <input type="text" name="PS_GTM_EVENT_PREFIX" id="PS_GTM_EVENT_PREFIX" class="ps-gtm-form-input" value="{$config_values.PS_GTM_EVENT_PREFIX|escape:'html':'UTF-8'}" placeholder="{l s='Enter prefix (e.g., ps)' mod='ps_gtm_datalayer'}">
                  <div class="ps-gtm-help-text">
                    <i class="icon-info-circle"></i> {l s='The original canonical GA4 event name is always preserved inside original_event in the dataLayer.' mod='ps_gtm_datalayer'}
                  </div>
                </div>
              </div>
            </div>

            <div class="ps-gtm-card-footer-bar">
              <button type="submit" name="submit{$module_name|escape:'html':'UTF-8'}" class="btn btn-primary ps-gtm-btn-save-primary">
                <i class="icon-save"></i> <span>{l s='Save Settings' mod='ps_gtm_datalayer'}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- ============================================================= -->
        <!-- TAB 2: PRODUCT CATALOG STRATEGY                               -->
        <!-- ============================================================= -->
        <div id="tab-catalog" class="ps-gtm-tab-pane">
          <div class="ps-gtm-card">
            <div class="card-header-bar">
              <h3 class="card-header-title">
                <i class="icon-barcode text-primary"></i> {l s='Product Catalog Identifier Strategy' mod='ps_gtm_datalayer'}
              </h3>
              <span class="label label-info">
                {l s='Dynamic Remarketing' mod='ps_gtm_datalayer'}
              </span>
            </div>

            <div class="card-body-content">
              <div class="ps-gtm-card-intro">
                {l s='Determines how product item_id, content_ids, and ecomm_prodid are constructed to achieve exact 1:1 matching with your product catalog feeds in Meta Catalog, Google Merchant Center, TikTok, and Pinterest.' mod='ps_gtm_datalayer'}
              </div>

              <!-- Sub-card: Strategy Selection -->
              <div class="ps-gtm-subcard">
                <h4 class="ps-gtm-subcard-title">
                  <i class="icon-check-circle text-primary"></i> {l s='Select Catalog Strategy' mod='ps_gtm_datalayer'}
                </h4>
                <div class="ps-gtm-subcard-desc">
                  {l s='Choose the format corresponding to your product feed export module (e.g., Google Shopping Feed, Facebook Product Feed).' mod='ps_gtm_datalayer'}
                </div>

                <div class="form-group" style="margin-bottom: 18px;">
                  <select name="PS_GTM_CATALOG_ID_STRATEGY" id="PS_GTM_CATALOG_ID_STRATEGY" class="ps-gtm-form-input" style="max-width: 640px; height: 42px;">
                    <option value="composite" {if $config_values.PS_GTM_CATALOG_ID_STRATEGY === 'composite'}selected{/if}>
                      {l s='Option 1: Composite ID {id_product}_{id_product_attribute} (Recommended for 90% of stores)' mod='ps_gtm_datalayer'}
                    </option>
                    <option value="id_product" {if $config_values.PS_GTM_CATALOG_ID_STRATEGY === 'id_product'}selected{/if}>
                      {l s='Option 2: Base Product ID {id_product} (Parent-only catalogs)' mod='ps_gtm_datalayer'}
                    </option>
                    <option value="reference_sku" {if $config_values.PS_GTM_CATALOG_ID_STRATEGY === 'reference_sku'}selected{/if}>
                      {l s='Option 3: Reference / SKU {sku} (ERP & Barcode feeds)' mod='ps_gtm_datalayer'}
                    </option>
                  </select>
                </div>

                <!-- 3 Visual Interactive Cards -->
                {$catalog_strategy_help_html nofilter}
              </div>
            </div>

            <div class="ps-gtm-card-footer-bar">
              <button type="submit" name="submit{$module_name|escape:'html':'UTF-8'}" class="btn btn-primary ps-gtm-btn-save-primary">
                <i class="icon-save"></i> <span>{l s='Save Settings' mod='ps_gtm_datalayer'}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- ============================================================= -->
        <!-- TAB 3: PLATFORM ROUTING & ADVERTISING                         -->
        <!-- ============================================================= -->
        <div id="tab-routing" class="ps-gtm-tab-pane">

          <!-- 1. Header Card with Title and Master Switch Pill -->
          <div class="ps-gtm-routing-header-card">
            <div class="ps-gtm-routing-header-left">
              <div class="ps-gtm-routing-header-icon">
                <i class="icon-random"></i>
              </div>
              <div>
                <h3 class="ps-gtm-routing-title">{l s='Advertising Platform DataLayers & Event Routing' mod='ps_gtm_datalayer'}</h3>
                <p class="ps-gtm-routing-subtitle">{l s='Configure which platform-specific DataLayer events are dispatched to your advertising channels.' mod='ps_gtm_datalayer'}</p>
              </div>
            </div>
            <div class="ps-gtm-routing-header-right">
              <div class="ps-gtm-master-switch-pill">
                <span class="ps-gtm-master-switch-label">{l s='Master Switch' mod='ps_gtm_datalayer'}</span>
                <label class="ps-gtm-switch" style="margin: 0;" title="{l s='Toggle all advertising platform event routing' mod='ps_gtm_datalayer'}">
                  <input type="checkbox" name="PS_GTM_ADV_MASTER_ENABLED" id="adv_master_switch" value="1" {if $config_values.PS_GTM_ADV_MASTER_ENABLED}checked{/if} onchange="psGtmOnAdvMasterChange(this)">
                  <span class="ps-gtm-slider"></span>
                </label>
                <span class="ps-gtm-master-status-text {if $config_values.PS_GTM_ADV_MASTER_ENABLED}on{else}off{/if}" id="adv_master_status_text">{if $config_values.PS_GTM_ADV_MASTER_ENABLED}ON{else}OFF{/if}</span>
              </div>
            </div>
          </div>

          <!-- Hidden Platform Master State Inputs -->
          <input type="hidden" name="PS_GTM_ADV_META_ENABLED" id="adv_platform_meta_enabled" value="{if $config_values.PS_GTM_ADV_META_ENABLED}1{else}0{/if}">
          <input type="hidden" name="PS_GTM_ADV_GADS_ENABLED" id="adv_platform_gads_enabled" value="{if $config_values.PS_GTM_ADV_GADS_ENABLED}1{else}0{/if}">
          <input type="hidden" name="PS_GTM_ADV_TIKTOK_ENABLED" id="adv_platform_tiktok_enabled" value="{if $config_values.PS_GTM_ADV_TIKTOK_ENABLED}1{else}0{/if}">
          <input type="hidden" name="PS_GTM_ADV_PINTEREST_ENABLED" id="adv_platform_pinterest_enabled" value="{if $config_values.PS_GTM_ADV_PINTEREST_ENABLED}1{else}0{/if}">
          <input type="hidden" name="PS_GTM_ADV_MICROSOFT_ENABLED" id="adv_platform_microsoft_enabled" value="{if $config_values.PS_GTM_ADV_MICROSOFT_ENABLED}1{else}0{/if}">

          <!-- 2. Toolbar: Display Mode & Bulk Actions -->
          <div class="ps-gtm-routing-toolbar">
            <div class="ps-gtm-view-switcher">
              <button type="button" class="ps-gtm-view-pill active" id="ps-gtm-view-btn-grouped" onclick="psGtmSetRoutingDisplayMode('grouped')">
                <i class="icon-th-large"></i> {l s='By Event Group' mod='ps_gtm_datalayer'}
              </button>
              <button type="button" class="ps-gtm-view-pill" id="ps-gtm-view-btn-flat" onclick="psGtmSetRoutingDisplayMode('flat')">
                <i class="icon-list"></i> {l s='Full List' mod='ps_gtm_datalayer'}
              </button>
            </div>
            <div class="ps-gtm-bulk-actions">
              <button type="button" class="ps-gtm-btn-bulk-enable" onclick="psGtmToggleAllPlatforms(true)">
                <i class="icon-check-circle"></i> {l s='Enable All' mod='ps_gtm_datalayer'}
              </button>
              <button type="button" class="ps-gtm-btn-bulk-disable" onclick="psGtmToggleAllPlatforms(false)">
                <i class="icon-ban"></i> {l s='Disable All' mod='ps_gtm_datalayer'}
              </button>
            </div>
          </div>

          <!-- 3. Responsive Scroll Wrapper -->
          <div class="ps-gtm-routing-container-scroll">
            <div class="ps-gtm-routing-inner-minwidth">

              <!-- Flat List View Sticky Header (Initially hidden, shown when 'Full List' active) -->
              <div id="ps-gtm-flat-view-header" class="ps-gtm-flat-header" style="display: none;">
                <div class="ps-gtm-flat-header-left">
                  <span>{l s='Ecommerce Event' mod='ps_gtm_datalayer'}</span>
                </div>
                <div class="ps-gtm-flat-header-right">
                  <div class="ps-gtm-platform-col-head" title="{l s='Toggle Meta Column' mod='ps_gtm_datalayer'}" onclick="psGtmTogglePlatformColumn('meta')">
                    <img src="{$module_path|escape:'html':'UTF-8'}views/img/meta.png" alt="Meta">
                    <span>Meta</span>
                  </div>
                  <div class="ps-gtm-platform-col-head" title="{l s='Toggle Google Ads Column' mod='ps_gtm_datalayer'}" onclick="psGtmTogglePlatformColumn('gads')">
                    <img src="{$module_path|escape:'html':'UTF-8'}views/img/google_ads.png" alt="Google Ads">
                    <span>Google Ads</span>
                  </div>
                  <div class="ps-gtm-platform-col-head" title="{l s='Toggle TikTok Column' mod='ps_gtm_datalayer'}" onclick="psGtmTogglePlatformColumn('tiktok')">
                    <img src="{$module_path|escape:'html':'UTF-8'}views/img/tiktok.png" alt="TikTok">
                    <span>TikTok</span>
                  </div>
                  <div class="ps-gtm-platform-col-head" title="{l s='Toggle Pinterest Column' mod='ps_gtm_datalayer'}" onclick="psGtmTogglePlatformColumn('pinterest')">
                    <img src="{$module_path|escape:'html':'UTF-8'}views/img/pinterest.png" alt="Pinterest">
                    <span>Pinterest</span>
                  </div>
                  <div class="ps-gtm-platform-col-head" title="{l s='Toggle Microsoft Column' mod='ps_gtm_datalayer'}" onclick="psGtmTogglePlatformColumn('microsoft')">
                    <img src="{$module_path|escape:'html':'UTF-8'}views/img/microsoft_ads.png" alt="Microsoft">
                    <span>Microsoft</span>
                  </div>
                </div>
              </div>

              <!-- Categorized Event Groups (3 Groups: Product Discovery, Cart & Checkout, Conversion) -->
              {foreach from=$adv_event_groups key=grpKey item=grp}
              <div class="ps-gtm-group-card" id="grp-card-{$grpKey|escape:'html':'UTF-8'}">
                <!-- Group Header Banner -->
                <div class="ps-gtm-group-header" style="background: {$grp.bg|escape:'html':'UTF-8'}; border-color: {$grp.border|escape:'html':'UTF-8'};" onclick="psGtmToggleGroup('{$grpKey|escape:'html':'UTF-8'}')">
                  <div class="ps-gtm-group-header-left">
                    <span class="ps-gtm-group-chevron" id="grp-chevron-{$grpKey|escape:'html':'UTF-8'}"><i class="icon-chevron-down"></i></span>
                    <span class="ps-gtm-group-title" style="color: {$grp.badge_color|escape:'html':'UTF-8'};">{$grp.title|escape:'html':'UTF-8'}</span>
                    <span class="ps-gtm-group-badge" style="background: {$grp.border|escape:'html':'UTF-8'}; color: {$grp.badge_color|escape:'html':'UTF-8'};">
                      {count($grp.events)} {if count($grp.events) == 1}{l s='event' mod='ps_gtm_datalayer'}{else}{l s='events' mod='ps_gtm_datalayer'}{/if}
                    </span>
                  </div>
                  <div class="ps-gtm-group-header-right" onclick="event.stopPropagation();">
                    <div class="ps-gtm-platform-col-head" title="{l s='Toggle Meta for this group' mod='ps_gtm_datalayer'}" onclick="psGtmToggleGroupPlatform('{$grpKey|escape:'html':'UTF-8'}', 'meta')">
                      <img src="{$module_path|escape:'html':'UTF-8'}views/img/meta.png" alt="Meta">
                      <span>Meta</span>
                    </div>
                    <div class="ps-gtm-platform-col-head" title="{l s='Toggle Google Ads for this group' mod='ps_gtm_datalayer'}" onclick="psGtmToggleGroupPlatform('{$grpKey|escape:'html':'UTF-8'}', 'gads')">
                      <img src="{$module_path|escape:'html':'UTF-8'}views/img/google_ads.png" alt="Google Ads">
                      <span>Google Ads</span>
                    </div>
                    <div class="ps-gtm-platform-col-head" title="{l s='Toggle TikTok for this group' mod='ps_gtm_datalayer'}" onclick="psGtmToggleGroupPlatform('{$grpKey|escape:'html':'UTF-8'}', 'tiktok')">
                      <img src="{$module_path|escape:'html':'UTF-8'}views/img/tiktok.png" alt="TikTok">
                      <span>TikTok</span>
                    </div>
                    <div class="ps-gtm-platform-col-head" title="{l s='Toggle Pinterest for this group' mod='ps_gtm_datalayer'}" onclick="psGtmToggleGroupPlatform('{$grpKey|escape:'html':'UTF-8'}', 'pinterest')">
                      <img src="{$module_path|escape:'html':'UTF-8'}views/img/pinterest.png" alt="Pinterest">
                      <span>Pinterest</span>
                    </div>
                    <div class="ps-gtm-platform-col-head" title="{l s='Toggle Microsoft for this group' mod='ps_gtm_datalayer'}" onclick="psGtmToggleGroupPlatform('{$grpKey|escape:'html':'UTF-8'}', 'microsoft')">
                      <img src="{$module_path|escape:'html':'UTF-8'}views/img/microsoft_ads.png" alt="Microsoft">
                      <span>Microsoft</span>
                    </div>
                  </div>
                </div>

                <!-- Group Rows -->
                <div class="ps-gtm-group-body" id="grp-body-{$grpKey|escape:'html':'UTF-8'}">
                  {foreach from=$grp.events item=e}
                  <div class="ps-gtm-event-row" data-event="{$e.name|escape:'html':'UTF-8'}">
                    <div class="ps-gtm-event-row-left">
                      <input type="checkbox" class="ps-gtm-row-select-cb" id="row_cb_{$e.name|escape:'html':'UTF-8'}" title="{l s='Toggle all platforms for this event' mod='ps_gtm_datalayer'}" onchange="psGtmOnRowCheck(this, '{$e.name|escape:'html':'UTF-8'}')">
                      <span class="ps-gtm-event-icon-badge" style="background: {$grp.icon_bg|escape:'html':'UTF-8'}; color: {$grp.icon_color|escape:'html':'UTF-8'};">
                        <i class="{$e.icon|escape:'html':'UTF-8'}"></i>
                      </span>
                      <div class="ps-gtm-event-name-wrap">
                        <span class="ps-gtm-event-label">{$e.label|escape:'html':'UTF-8'}</span>
                        <code class="ps-gtm-event-code">{$e.name|escape:'html':'UTF-8'}</code>
                      </div>
                      <button type="button" class="ps-gtm-info-circle-btn ps-gtm-inspect-btn" data-event="{$e.name|escape:'html':'UTF-8'}" title="{l s='Inspect event parameters & payload schema' mod='ps_gtm_datalayer'}">
                        <i class="icon-info-circle"></i>
                      </button>
                    </div>
                    <div class="ps-gtm-event-row-right">
                      <!-- Meta Switch -->
                      <div class="ps-gtm-switch-cell">
                        <label class="ps-gtm-switch ps-gtm-switch-sm" title="{l s='Toggle Meta for %s' sprintf=[$e.label] mod='ps_gtm_datalayer'}">
                          <input type="checkbox" name="adv_meta_{$e.name|escape:'html':'UTF-8'}" class="ps-gtm-evt-meta ps-gtm-row-evt-{$e.name|escape:'html':'UTF-8'} ps-gtm-grp-{$grpKey|escape:'html':'UTF-8'}-meta" value="1" {if in_array($e.name, $meta_active_events)}checked{/if} onchange="psGtmSyncRowState('{$e.name|escape:'html':'UTF-8'}')">
                          <span class="ps-gtm-slider"></span>
                        </label>
                      </div>
                      <!-- Google Ads Switch -->
                      <div class="ps-gtm-switch-cell">
                        <label class="ps-gtm-switch ps-gtm-switch-sm" title="{l s='Toggle Google Ads for %s' sprintf=[$e.label] mod='ps_gtm_datalayer'}">
                          <input type="checkbox" name="adv_gads_{$e.name|escape:'html':'UTF-8'}" class="ps-gtm-evt-gads ps-gtm-row-evt-{$e.name|escape:'html':'UTF-8'} ps-gtm-grp-{$grpKey|escape:'html':'UTF-8'}-gads" value="1" {if in_array($e.name, $gads_active_events)}checked{/if} onchange="psGtmSyncRowState('{$e.name|escape:'html':'UTF-8'}')">
                          <span class="ps-gtm-slider"></span>
                        </label>
                      </div>
                      <!-- TikTok Switch -->
                      <div class="ps-gtm-switch-cell">
                        <label class="ps-gtm-switch ps-gtm-switch-sm" title="{l s='Toggle TikTok for %s' sprintf=[$e.label] mod='ps_gtm_datalayer'}">
                          <input type="checkbox" name="adv_tiktok_{$e.name|escape:'html':'UTF-8'}" class="ps-gtm-evt-tiktok ps-gtm-row-evt-{$e.name|escape:'html':'UTF-8'} ps-gtm-grp-{$grpKey|escape:'html':'UTF-8'}-tiktok" value="1" {if in_array($e.name, $tiktok_active_events)}checked{/if} onchange="psGtmSyncRowState('{$e.name|escape:'html':'UTF-8'}')">
                          <span class="ps-gtm-slider"></span>
                        </label>
                      </div>
                      <!-- Pinterest Switch -->
                      <div class="ps-gtm-switch-cell">
                        <label class="ps-gtm-switch ps-gtm-switch-sm" title="{l s='Toggle Pinterest for %s' sprintf=[$e.label] mod='ps_gtm_datalayer'}">
                          <input type="checkbox" name="adv_pinterest_{$e.name|escape:'html':'UTF-8'}" class="ps-gtm-evt-pinterest ps-gtm-row-evt-{$e.name|escape:'html':'UTF-8'} ps-gtm-grp-{$grpKey|escape:'html':'UTF-8'}-pinterest" value="1" {if in_array($e.name, $pinterest_active_events)}checked{/if} onchange="psGtmSyncRowState('{$e.name|escape:'html':'UTF-8'}')">
                          <span class="ps-gtm-slider"></span>
                        </label>
                      </div>
                      <!-- Microsoft Switch -->
                      <div class="ps-gtm-switch-cell">
                        <label class="ps-gtm-switch ps-gtm-switch-sm" title="{l s='Toggle Microsoft for %s' sprintf=[$e.label] mod='ps_gtm_datalayer'}">
                          <input type="checkbox" name="adv_microsoft_{$e.name|escape:'html':'UTF-8'}" class="ps-gtm-evt-microsoft ps-gtm-row-evt-{$e.name|escape:'html':'UTF-8'} ps-gtm-grp-{$grpKey|escape:'html':'UTF-8'}-microsoft" value="1" {if in_array($e.name, $microsoft_active_events)}checked{/if} onchange="psGtmSyncRowState('{$e.name|escape:'html':'UTF-8'}')">
                          <span class="ps-gtm-slider"></span>
                        </label>
                      </div>
                    </div>
                  </div>
                  {/foreach}
                </div>
              </div>
              {/foreach}

            </div>
          </div>

          <!-- Google Ads Advanced Cart Data Subcard -->
          <div class="ps-gtm-card" style="margin-top: 20px; border-left: 4px solid #ea4335;">
            <div class="card-header-bar" style="padding: 12px 18px;">
              <h4 class="card-header-title" style="font-size: 15px; margin: 0;">
                <i class="icon-shopping-cart text-danger"></i> {l s='Google Ads Conversions with Cart Data (Basket Reporting)' mod='ps_gtm_datalayer'}
              </h4>
              <span class="label {if $config_values.PS_GTM_ADV_GADS_CART_DATA}label-success{else}label-default{/if}">
                {if $config_values.PS_GTM_ADV_GADS_CART_DATA}{l s='Enabled' mod='ps_gtm_datalayer'}{else}{l s='Disabled' mod='ps_gtm_datalayer'}{/if}
              </span>
            </div>
            <div class="card-body-content" style="padding: 16px 18px;">
              <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 14px;">
                <div>
                  <strong style="font-size: 14.5px; color: #1e293b;">{l s='Enable Cart Data on Purchase Conversions' mod='ps_gtm_datalayer'}</strong>
                  <div style="font-size: 13.5px; color: #64748b; margin-top: 2px;">
                    {l s='Includes itemized products, quantities, and prices on purchase conversions for Google Ads Basket Cart Data reporting.' mod='ps_gtm_datalayer'}
                  </div>
                </div>
                <label class="ps-gtm-switch ps-gtm-switch-sm">
                  <input type="checkbox" name="PS_GTM_ADV_GADS_CART_DATA" value="1" {if $config_values.PS_GTM_ADV_GADS_CART_DATA}checked{/if}>
                  <span class="ps-gtm-slider"></span>
                </label>
              </div>
              <div style="max-width: 420px;">
                <label style="font-size: 14px; font-weight: 600; color: #1e293b; display: block; margin-bottom: 6px;">{l s='Google Merchant Center ID (Optional)' mod='ps_gtm_datalayer'}</label>
                <input type="text" name="PS_GTM_ADV_GADS_MERCHANT_ID" value="{$config_values.PS_GTM_ADV_GADS_MERCHANT_ID|escape:'html':'UTF-8'}" placeholder="e.g. 123456789" class="ps-gtm-form-input">
                <div class="ps-gtm-help-text" style="font-size: 12.5px;">
                  {l s='If provided, automatically populates Merchant Center ID inside cart data.' mod='ps_gtm_datalayer'}
                </div>
              </div>
            </div>
          </div>

          <!-- 4. Footer Bar with Event Count, Unsaved Pill, Reset & Save -->
          <div class="ps-gtm-routing-footer-bar">
            <div class="ps-gtm-routing-footer-left">
              <span class="ps-gtm-footer-counter-badge">
                <i class="icon-tags"></i> {$adv_total_events_count|escape:'html':'UTF-8'} {l s='events' mod='ps_gtm_datalayer'}
              </span>
              <span class="ps-gtm-footer-unsaved-pill" id="ps-gtm-footer-unsaved-pill" style="display: none;">
                <span class="ps-gtm-unsaved-dot"></span> {l s='Unsaved changes' mod='ps_gtm_datalayer'}
              </span>
            </div>
            <div class="ps-gtm-routing-footer-right">
              <button type="button" class="btn btn-default ps-gtm-btn-reset" onclick="psGtmResetForm()">
                <i class="icon-undo"></i> <span>{l s='Reset' mod='ps_gtm_datalayer'}</span>
              </button>
              <button type="submit" name="submit{$module_name|escape:'html':'UTF-8'}" class="btn btn-primary ps-gtm-btn-save-primary">
                <i class="icon-save"></i> <span>{l s='Save Settings' mod='ps_gtm_datalayer'}</span>
              </button>
            </div>
          </div>

        </div>

        <!-- ============================================================= -->
        <!-- TAB 4: SERVER-SIDE TRACKING & WEBHOOKS (sGTM)                 -->
        <!-- ============================================================= -->
        <div id="tab-sgtm" class="ps-gtm-tab-pane">
          <div class="ps-gtm-card">
            <div class="card-header-bar">
              <h3 class="card-header-title">
                <i class="icon-server text-primary"></i> {l s='Server-Side Tracking & Webhooks (sGTM)' mod='ps_gtm_datalayer'}
              </h3>
              <span class="label {if !empty($config_values.PS_SGTM_TRANSPORT_URL)}label-success{else}label-default{/if}">
                {if !empty($config_values.PS_SGTM_TRANSPORT_URL)}{l s='sGTM Configured' mod='ps_gtm_datalayer'}{else}{l s='Optional' mod='ps_gtm_datalayer'}{/if}
              </span>
            </div>

            <div class="card-body-content">
              <div class="ps-gtm-card-intro">
                {l s='Route your ecommerce events through a first-party Server-Side Google Tag Manager container to bypass ad-blockers, improve page performance, and send server-to-server conversions.' mod='ps_gtm_datalayer'}
              </div>

              <!-- Sub-card: Server Transport Endpoints -->
              <div class="ps-gtm-subcard">
                <h4 class="ps-gtm-subcard-title">
                  <i class="icon-cloud text-primary"></i> {l s='First-Party Server GTM Endpoints' mod='ps_gtm_datalayer'}
                </h4>
                <div class="ps-gtm-subcard-desc">
                  {l s='The transport domain of your server container (e.g. gtm.yourdomain.com or Stape.io URL).' mod='ps_gtm_datalayer'}
                </div>

                <div class="form-group">
                  <label class="ps-gtm-form-label" for="PS_SGTM_TRANSPORT_URL">
                    {l s='Server-Side GTM Transport URL (Optional)' mod='ps_gtm_datalayer'}
                  </label>
                  <input type="text" name="PS_SGTM_TRANSPORT_URL" id="PS_SGTM_TRANSPORT_URL" class="ps-gtm-form-input" value="{$config_values.PS_SGTM_TRANSPORT_URL|escape:'html':'UTF-8'}" placeholder="https://gtm.yourdomain.com">
                  <div class="ps-gtm-help-text">
                    <i class="icon-info-circle"></i> {l s='Leave empty if using standard client-side Google Tag Manager.' mod='ps_gtm_datalayer'}
                  </div>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                  <label class="ps-gtm-form-label" for="PS_SGTM_WEBHOOK_ENDPOINT_PATH">
                    {l s='Server GTM Webhook Path' mod='ps_gtm_datalayer'}
                  </label>
                  <input type="text" name="PS_SGTM_WEBHOOK_ENDPOINT_PATH" id="PS_SGTM_WEBHOOK_ENDPOINT_PATH" class="ps-gtm-form-input" value="{$config_values.PS_SGTM_WEBHOOK_ENDPOINT_PATH|escape:'html':'UTF-8'}" placeholder="/ps-webhook">
                  <div class="ps-gtm-help-text">
                    <i class="icon-info-circle"></i> {l s='Default is /ps-webhook for PrestaShop Webhook Client. If using Stape Data Client, set to /data.' mod='ps_gtm_datalayer'}
                  </div>
                </div>

                <!-- Test Connection Button -->
                <div style="padding-top: 14px; border-top: 1px solid #edf2f7; display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                  <button type="button" id="ps-gtm-ping-btn" class="btn btn-default" style="font-size: 14px; font-weight: 600; padding: 8px 16px;">
                    <i class="icon-bolt text-warning"></i> {l s='Test Server Connection (Ping)' mod='ps_gtm_datalayer'}
                  </button>
                  <span style="font-size: 13.5px; color: #64748b;">
                    {l s='Sends a test request directly to your sGTM transport URL to verify network connectivity.' mod='ps_gtm_datalayer'}
                  </span>
                </div>
                <div id="ps-gtm-ping-result" style="display: none; margin-top: 14px;"></div>
              </div>

              <!-- Sub-card: Server-to-Server Event Webhooks -->
              <div class="ps-gtm-subcard">
                <h4 class="ps-gtm-subcard-title">
                  <i class="icon-exchange text-primary"></i> {l s='Server-to-Server Event Webhooks' mod='ps_gtm_datalayer'}
                </h4>
                <div class="ps-gtm-subcard-desc">
                  {l s='Asynchronously dispatch verified backend orders, refunds, and status updates directly from PrestaShop to your sGTM container.' mod='ps_gtm_datalayer'}
                </div>

                <div style="display: flex; flex-direction: column; gap: 18px;">
                  <!-- Refund Webhook -->
                  <div style="display: flex; align-items: center; justify-content: space-between; max-width: 780px;">
                    <div>
                      <strong style="font-size: 15px; color: #0f172a;">{l s='Server-to-Server Refund Webhooks' mod='ps_gtm_datalayer'}</strong>
                      <div style="font-size: 13.5px; color: #64748b; margin-top: 2px; line-height: 1.4;">
                        {l s='Dispatches refund events to sGTM upon Credit Slip generation in the Back Office.' mod='ps_gtm_datalayer'}
                      </div>
                    </div>
                    <label class="ps-gtm-switch">
                      <input type="checkbox" name="PS_GTM_WEBHOOK_REFUND_ENABLED" value="1" {if $config_values.PS_GTM_WEBHOOK_REFUND_ENABLED}checked{/if}>
                      <span class="ps-gtm-slider"></span>
                    </label>
                  </div>

                  <!-- Offline Orders Webhook -->
                  <div style="display: flex; align-items: center; justify-content: space-between; max-width: 780px;">
                    <div>
                      <strong style="font-size: 15px; color: #0f172a;">{l s='Server-to-Server Offline Order Webhooks' mod='ps_gtm_datalayer'}</strong>
                      <div style="font-size: 13.5px; color: #64748b; margin-top: 2px; line-height: 1.4;">
                        {l s='Dispatches purchase conversions for orders created or validated offline in the Back Office.' mod='ps_gtm_datalayer'}
                      </div>
                    </div>
                    <label class="ps-gtm-switch">
                      <input type="checkbox" name="PS_GTM_WEBHOOK_OFFLINE_ORDER_ENABLED" value="1" {if $config_values.PS_GTM_WEBHOOK_OFFLINE_ORDER_ENABLED}checked{/if}>
                      <span class="ps-gtm-slider"></span>
                    </label>
                  </div>

                  <!-- Order Status Updates Webhook -->
                  <div style="display: flex; align-items: center; justify-content: space-between; max-width: 780px;">
                    <div>
                      <strong style="font-size: 15px; color: #0f172a;">{l s='Server-to-Server Order Status Change Webhooks' mod='ps_gtm_datalayer'}</strong>
                      <div style="font-size: 13.5px; color: #64748b; margin-top: 2px; line-height: 1.4;">
                        {l s='Dispatches order_status_update events to sGTM when payment status updates (e.g. Bank Wire to Paid, Mirakl, Shipped).' mod='ps_gtm_datalayer'}
                      </div>
                    </div>
                    <label class="ps-gtm-switch">
                      <input type="checkbox" name="PS_GTM_WEBHOOK_STATUS_UPDATE_ENABLED" value="1" {if $config_values.PS_GTM_WEBHOOK_STATUS_UPDATE_ENABLED}checked{/if}>
                      <span class="ps-gtm-slider"></span>
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <div class="ps-gtm-card-footer-bar">
              <button type="submit" name="submit{$module_name|escape:'html':'UTF-8'}" class="btn btn-primary ps-gtm-btn-save-primary">
                <i class="icon-save"></i> <span>{l s='Save Settings' mod='ps_gtm_datalayer'}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- ============================================================= -->
        <!-- TAB 5: ADVANCED SCRIPT INJECTION                              -->
        <!-- ============================================================= -->
        <div id="tab-scripts" class="ps-gtm-tab-pane">
          <div class="ps-gtm-card">
            <div class="card-header-bar">
              <h3 class="card-header-title">
                <i class="icon-code text-primary"></i> {l s='Advanced Custom Script Injection' mod='ps_gtm_datalayer'}
              </h3>
              <span class="label label-default">
                {l s='Optional Agency Snippets' mod='ps_gtm_datalayer'}
              </span>
            </div>

            <div class="card-body-content">
              <div class="ps-gtm-card-intro">
                {l s='Inject custom HTML or marketing scripts directly into storefront <head> and <body> without modifying theme template files. Standard GTM container scripts are generated automatically from your GTM Container ID.' mod='ps_gtm_datalayer'}
              </div>

              <!-- Sub-card: Raw Head Code -->
              <div class="ps-gtm-subcard">
                <h4 class="ps-gtm-subcard-title">
                  <i class="icon-file-text-o text-primary"></i> {l s='Raw <head> HTML Snippet' mod='ps_gtm_datalayer'}
                </h4>
                <div class="ps-gtm-subcard-desc">
                  {l s='Custom script tag injected before the closing </head> tag. Ideal for site verification tags or custom tracking pixels.' mod='ps_gtm_datalayer'}
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                  <textarea name="PS_GTM_RAW_HEAD_CODE" id="PS_GTM_RAW_HEAD_CODE" rows="4" class="form-control" style="font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 13.5px; line-height: 1.5; padding: 12px 14px; border: 1.5px solid #cbd5e1; border-radius: 6px;">{$config_values.PS_GTM_RAW_HEAD_CODE|escape:'html':'UTF-8'}</textarea>
                </div>
              </div>

              <!-- Sub-card: Raw Body Code -->
              <div class="ps-gtm-subcard">
                <h4 class="ps-gtm-subcard-title">
                  <i class="icon-file-code-o text-primary"></i> {l s='Raw <body> HTML Snippet' mod='ps_gtm_datalayer'}
                </h4>
                <div class="ps-gtm-subcard-desc">
                  {l s='Custom noscript iframe fallback injected immediately after the opening <body> tag.' mod='ps_gtm_datalayer'}
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                  <textarea name="PS_GTM_RAW_BODY_CODE" id="PS_GTM_RAW_BODY_CODE" rows="3" class="form-control" style="font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 13.5px; line-height: 1.5; padding: 12px 14px; border: 1.5px solid #cbd5e1; border-radius: 6px;">{$config_values.PS_GTM_RAW_BODY_CODE|escape:'html':'UTF-8'}</textarea>
                </div>
              </div>
            </div>

            <div class="ps-gtm-card-footer-bar">
              <button type="submit" name="submit{$module_name|escape:'html':'UTF-8'}" class="btn btn-primary ps-gtm-btn-save-primary">
                <i class="icon-save"></i> <span>{l s='Save Settings' mod='ps_gtm_datalayer'}</span>
              </button>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Unsaved Changes Floating Banner -->
    <div id="ps-gtm-unsaved-alert" class="ps-gtm-unsaved-alert" style="display: none;">
      <div style="display: flex; align-items: center; gap: 12px;">
        <i class="icon-warning-sign" style="font-size: 20px; color: #f59e0b;"></i>
        <div style="font-size: 14.5px; color: #0f172a;">
          <strong>{l s='Unsaved Changes' mod='ps_gtm_datalayer'}:</strong> {l s='You have modified settings. Click "Save Settings" to apply your changes.' mod='ps_gtm_datalayer'}
        </div>
      </div>
      <button type="submit" name="submit{$module_name|escape:'html':'UTF-8'}" class="btn btn-primary ps-gtm-btn-save-primary" style="padding: 6px 18px !important; font-size: 13.5px !important;">
        <i class="icon-save"></i> <span>{l s='Save Settings' mod='ps_gtm_datalayer'}</span>
      </button>
    </div>

  </form>

  <!-- ===================================================================== -->
  <!-- Slide-Out Event Schema Inspector Drawer Backdrop                     -->
  <!-- ===================================================================== -->
  <div id="ps-gtm-inspect-drawer-backdrop" class="ps-gtm-drawer-backdrop" role="dialog" aria-modal="true">
    <div class="ps-gtm-drawer">
      <!-- Drawer Header -->
      <div class="ps-gtm-drawer-header">
        <div style="display: flex; align-items: center; gap: 14px;">
          <div class="ps-gtm-drawer-icon-box">
            <i id="ps-gtm-drawer-icon" class="icon-eye"></i>
          </div>
          <div>
            <h3 id="ps-gtm-drawer-title" style="margin: 0; font-size: 20px; font-weight: 700; color: #0f172a; line-height: 1.25;">View Item</h3>
            <div style="font-size: 13.5px; color: #64748b; margin-top: 2px;">{l s='Event details and configuration' mod='ps_gtm_datalayer'}</div>
          </div>
        </div>
        <div style="display: flex; align-items: center; gap: 14px;">
          <span id="ps-gtm-drawer-status-pill" class="ps-gtm-status-pill enabled">{l s='Enabled' mod='ps_gtm_datalayer'}</span>
          <button type="button" class="ps-gtm-drawer-close" aria-label="Close">&times;</button>
        </div>
      </div>

      <!-- Drawer Body -->
      <div class="ps-gtm-drawer-body">

        <!-- Section: Internal Event Name -->
        <div class="ps-gtm-drawer-section">
          <label class="ps-gtm-drawer-section-label">{l s='INTERNAL EVENT NAME' mod='ps_gtm_datalayer'}</label>
          <div class="ps-gtm-drawer-code-row">
            <code id="ps-gtm-drawer-event-code">view_item</code>
            <button type="button" class="btn btn-default btn-xs" onclick="psGtmCopyDrawerCode()" title="{l s='Copy event name' mod='ps_gtm_datalayer'}" style="font-size: 12.5px; font-weight: 600;">
              <i class="icon-copy"></i> {l s='Copy' mod='ps_gtm_datalayer'}
            </button>
          </div>
        </div>

        <!-- Section: Event Details -->
        <div class="ps-gtm-drawer-section">
          <label class="ps-gtm-drawer-section-label">{l s='EVENT DETAILS' mod='ps_gtm_datalayer'}</label>
          <div class="ps-gtm-drawer-details-card">
            <div class="ps-gtm-drawer-detail-item">
              <span class="detail-label">{l s='Category' mod='ps_gtm_datalayer'}</span>
              <span id="ps-gtm-drawer-category" class="ps-gtm-cat-badge interaction">Interaction</span>
            </div>
            <div class="ps-gtm-drawer-detail-item">
              <span class="detail-label">{l s='Trigger' mod='ps_gtm_datalayer'}</span>
              <span id="ps-gtm-drawer-trigger" class="detail-val">Product detail page or variant selection</span>
            </div>
            <div class="ps-gtm-drawer-detail-item">
              <span class="detail-label">{l s='Source' mod='ps_gtm_datalayer'}</span>
              <span id="ps-gtm-drawer-source" class="detail-val">hookDisplayHeader (ProductController) / updatedProduct bus</span>
            </div>
            <div class="ps-gtm-drawer-detail-item">
              <span class="detail-label">{l s='Dependencies' mod='ps_gtm_datalayer'}</span>
              <span id="ps-gtm-drawer-dependencies" class="detail-val">PrestaShop Core Product Model & Combination Engine</span>
            </div>
          </div>
        </div>

        <!-- Section: Destination Routing -->
        <div class="ps-gtm-drawer-section">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <div>
              <label class="ps-gtm-drawer-section-label" style="margin: 0;">{l s='DESTINATION ROUTING' mod='ps_gtm_datalayer'}</label>
              <div style="font-size: 13px; color: #64748b;">{l s='Platforms configured to receive this event payload' mod='ps_gtm_datalayer'}</div>
            </div>
          </div>
          <div id="ps-gtm-drawer-dest-list" class="ps-gtm-drawer-dest-list">
            <!-- Dynamically populated -->
          </div>
        </div>

        <!-- Section: Data Parameters -->
        <div class="ps-gtm-drawer-section">
          <label class="ps-gtm-drawer-section-label">{l s='DATA PARAMETERS' mod='ps_gtm_datalayer'}</label>
          <div style="font-size: 13px; color: #64748b; margin-top: -4px; margin-bottom: 8px;">
            {l s='Authoritative PrestaShop backend parameters transmitted with this event' mod='ps_gtm_datalayer'}
          </div>
          <div class="ps-gtm-drawer-table-wrapper">
            <table class="ps-gtm-drawer-table" id="ps-gtm-drawer-params-table">
              <thead>
                <tr>
                  <th style="width: 26%;">{l s='Parameter' mod='ps_gtm_datalayer'}</th>
                  <th style="width: 16%;">{l s='Type' mod='ps_gtm_datalayer'}</th>
                  <th style="width: 28%;">{l s='Source' mod='ps_gtm_datalayer'}</th>
                  <th style="width: 30%;">{l s='Purpose / Mapping' mod='ps_gtm_datalayer'}</th>
                </tr>
              </thead>
              <tbody id="ps-gtm-drawer-params-body">
                <!-- Dynamically populated -->
              </tbody>
            </table>
          </div>
        </div>

        <!-- Section: Platform-Specific Formatted Code (Accordion) -->
        <div class="ps-gtm-drawer-section">
          <div class="ps-gtm-drawer-accordion">
            <button type="button" class="ps-gtm-accordion-btn" onclick="psGtmToggleAccordion('ps-gtm-drawer-platform-code-box')">
              <span><i class="icon-code"></i> {l s='Platform-Specific Formatted Code & Mappings' mod='ps_gtm_datalayer'}</span>
              <i class="icon-chevron-down ps-gtm-chevron"></i>
            </button>
            <div id="ps-gtm-drawer-platform-code-box" class="ps-gtm-accordion-body" style="display: none;">
              <div id="ps-gtm-drawer-platform-code-content"></div>
            </div>
          </div>
        </div>

        <!-- Section: Trigger & Zero-DOM Specifications (Accordion) -->
        <div class="ps-gtm-drawer-section">
          <div class="ps-gtm-drawer-accordion">
            <button type="button" class="ps-gtm-accordion-btn" onclick="psGtmToggleAccordion('ps-gtm-drawer-trigger-details-box')">
              <span><i class="icon-bolt"></i> {l s='Trigger & Zero-DOM Bus Specifications' mod='ps_gtm_datalayer'}</span>
              <i class="icon-chevron-down ps-gtm-chevron"></i>
            </button>
            <div id="ps-gtm-drawer-trigger-details-box" class="ps-gtm-accordion-body" style="display: none;">
              <div id="ps-gtm-drawer-trigger-details-content" style="font-size: 13.5px; color: #334155; line-height: 1.6;"></div>
            </div>
          </div>
        </div>

        <!-- Section: Verified DataLayer JSON Payload -->
        <div class="ps-gtm-drawer-section">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <label class="ps-gtm-drawer-section-label" style="margin: 0;">{l s='VERIFIED JSON PAYLOAD EXAMPLE' mod='ps_gtm_datalayer'}</label>
            <button type="button" class="btn btn-default btn-xs" onclick="psGtmCopyDrawerJson()" style="font-size: 12.5px; font-weight: 600;">
              <i class="icon-copy"></i> {l s='Copy JSON' mod='ps_gtm_datalayer'}
            </button>
          </div>
          <pre id="ps-gtm-drawer-payload" class="ps-gtm-code-box" style="margin: 0;"></pre>
        </div>

      </div>

      <!-- Drawer Footer -->
      <div class="ps-gtm-drawer-footer">
        <button type="button" class="btn btn-default" data-drawer-dismiss="true" style="font-size: 14.5px; font-weight: 600; padding: 9px 22px;">{l s='Close' mod='ps_gtm_datalayer'}</button>
      </div>
    </div>
  </div>

</div>

<script type="text/javascript">
  window.psGtmAjaxAdminUrl = "{$ajax_admin_url|escape:'javascript':'UTF-8'}";
  window.psGtmEventPrefix = "{$config_values.PS_GTM_EVENT_PREFIX|escape:'javascript':'UTF-8'}";
  window.psGtmEventSchemas = {$events_schemas_json nofilter};
</script>
