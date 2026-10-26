{*
 * PrestaShop DataLayer - Unified Administration Interface
 * Left-Sidebar Panel Navigation Architecture:
 * - GTM & DataLayer Settings
 * - Advertising Platforms & Event Routing Matrix
 * - Server-Side Tracking & Webhooks (sGTM)
 * - Custom Script Injection
 *}

<div id="ps-gtm-admin-wrapper">

  <!-- Header Banner -->
  <div class="ps-gtm-header-card">
    <div class="ps-gtm-brand-left">
      <div class="ps-gtm-logo-box">
        <img src="{$module_logo|escape:'html':'UTF-8'}" alt="{$module_display_name|replace:'&amp;':'&'|escape:'html':'UTF-8'}">
      </div>
      <div class="ps-gtm-brand-titles">
        <h2>{$module_display_name|replace:'&amp;':'&' nofilter}</h2>
        <div class="ps-gtm-brand-subtitle">
          {l s='Enterprise GA4 Ecommerce, Server-Side GTM & Dynamic Advertising Remarketing' mod='ps_gtm_datalayer'}
        </div>
      </div>
    </div>
    <div class="ps-gtm-header-right">
      <span class="ps-gtm-version-badge"><i class="icon-code-fork"></i> v{$module_version|escape:'html':'UTF-8'}</span>
    </div>
  </div>

  <!-- Unified Settings Form -->
  <form action="{$form_action|escape:'html':'UTF-8'}" method="post" id="ps_gtm_main_form" class="defaultForm form-horizontal">
    <input type="hidden" name="submitps_gtm_datalayer" value="1">
    <input type="hidden" name="PS_GTM_ADV_SUBMITTED" value="1">
    <input type="hidden" name="active_tab" id="ps_gtm_active_tab" value="{$active_tab|escape:'html':'UTF-8'}">

    <!-- 2-Column Sidebar Layout -->
    <div class="ps-gtm-panel-layout">

      <!-- LEFT SIDEBAR NAVIGATION PANEL -->
      <aside class="ps-gtm-sidebar-panel">
        <div class="ps-gtm-sidebar-header">
          <i class="icon-sliders text-primary"></i> {l s='Settings Navigation' mod='ps_gtm_datalayer'}
        </div>

        <nav class="ps-gtm-sidebar-nav">
          <a href="#tab-config" class="ps-gtm-nav-tab active" data-tab="tab-config">
            <div class="ps-gtm-nav-icon"><i class="icon-cogs"></i></div>
            <div class="ps-gtm-nav-text">
              <strong class="ps-gtm-nav-title">{l s='GTM & DataLayer' mod='ps_gtm_datalayer'}</strong>
              <span class="ps-gtm-nav-sub">{l s='Container ID & Catalog Feeds' mod='ps_gtm_datalayer'}</span>
            </div>
          </a>

          <a href="#tab-routing" class="ps-gtm-nav-tab" data-tab="tab-routing">
            <div class="ps-gtm-nav-icon"><i class="icon-share-alt"></i></div>
            <div class="ps-gtm-nav-text">
              <strong class="ps-gtm-nav-title">{l s='Platform Routing' mod='ps_gtm_datalayer'}</strong>
              <span class="ps-gtm-nav-sub">{l s='Meta, Google Ads, TikTok, etc.' mod='ps_gtm_datalayer'}</span>
            </div>
            <span class="ps-gtm-tab-badge">{$active_platforms_count|escape:'html':'UTF-8'}/5</span>
          </a>

          <a href="#tab-sgtm" class="ps-gtm-nav-tab" data-tab="tab-sgtm">
            <div class="ps-gtm-nav-icon"><i class="icon-server"></i></div>
            <div class="ps-gtm-nav-text">
              <strong class="ps-gtm-nav-title">{l s='Server-Side & Webhooks' mod='ps_gtm_datalayer'}</strong>
              <span class="ps-gtm-nav-sub">{l s='sGTM Transport & S2S Webhooks' mod='ps_gtm_datalayer'}</span>
            </div>
          </a>

          <a href="#tab-scripts" class="ps-gtm-nav-tab" data-tab="tab-scripts">
            <div class="ps-gtm-nav-icon"><i class="icon-code"></i></div>
            <div class="ps-gtm-nav-text">
              <strong class="ps-gtm-nav-title">{l s='Custom Scripts' mod='ps_gtm_datalayer'}</strong>
              <span class="ps-gtm-nav-sub">{l s='Direct <head> & <body> Code' mod='ps_gtm_datalayer'}</span>
            </div>
          </a>
        </nav>

        <!-- Quick Status Card at bottom of sidebar -->
        <div class="ps-gtm-sidebar-footer-card">
          <div class="ps-gtm-sidebar-status-item">
            <span class="status-dot {if !empty($config_values.PS_GTM_CONTAINER_ID)}online{else}warning{/if}"></span>
            <span><strong>GTM:</strong> {if !empty($config_values.PS_GTM_CONTAINER_ID)}<code>{$config_values.PS_GTM_CONTAINER_ID|escape:'html':'UTF-8'}</code>{else}<em>{l s='Unconfigured' mod='ps_gtm_datalayer'}</em>{/if}</span>
          </div>
          <div class="ps-gtm-sidebar-status-item">
            <span class="status-dot {if $config_values.PS_GTM_ADV_MASTER_ENABLED}online{else}offline{/if}"></span>
            <span><strong>{l s='Advertising:' mod='ps_gtm_datalayer'}</strong> {$active_platforms_count|escape:'html':'UTF-8'}/5 {l s='Active' mod='ps_gtm_datalayer'}</span>
          </div>
          <div class="ps-gtm-sidebar-status-item">
            <span class="status-dot {if !empty($config_values.PS_SGTM_TRANSPORT_URL)}online{else}neutral{/if}"></span>
            <span><strong>sGTM:</strong> {if !empty($config_values.PS_SGTM_TRANSPORT_URL)}{l s='Connected' mod='ps_gtm_datalayer'}{else}<em>{l s='Optional' mod='ps_gtm_datalayer'}</em>{/if}</span>
          </div>
        </div>
      </aside>

      <!-- RIGHT MAIN SETTINGS CONTENT -->
      <main class="ps-gtm-main-content">

        <!-- ============================================================= -->
        <!-- TAB 1: GTM & DATALAYER CORE                                   -->
        <!-- ============================================================= -->
        <div id="tab-config" class="ps-gtm-tab-pane active">

          <div class="ps-gtm-panel-header">
            <h3 class="ps-gtm-panel-title">
              <i class="icon-cogs text-primary"></i> {l s='Google Tag Manager & DataLayer Settings' mod='ps_gtm_datalayer'}
            </h3>
            <div class="ps-gtm-panel-desc">
              {l s='Configure your primary GTM web container ID, dynamic DataLayer event naming prefix, and product catalog identifier resolution strategy.' mod='ps_gtm_datalayer'}
            </div>
          </div>

          <!-- Card 1: GTM Web Container & Event Prefix -->
          <div class="ps-gtm-card">
            <div class="ps-gtm-card-title">
              <i class="icon-google text-primary"></i> {l s='Web Container & Event Naming' mod='ps_gtm_datalayer'}
            </div>
            <div class="ps-gtm-card-desc">
              {l s='Official Google Tag Manager container injection and custom event prefix options.' mod='ps_gtm_datalayer'}
            </div>

            <div class="ps-gtm-form-group">
              <label class="ps-gtm-form-label" for="PS_GTM_CONTAINER_ID">
                {l s='GTM Container ID' mod='ps_gtm_datalayer'} <span class="req">*</span>
              </label>
              <input type="text" name="PS_GTM_CONTAINER_ID" id="PS_GTM_CONTAINER_ID" class="ps-gtm-form-control" value="{$config_values.PS_GTM_CONTAINER_ID|escape:'html':'UTF-8'}" placeholder="GTM-XXXXXXX" required>
              <div class="ps-gtm-help-block">
                {l s='Enter your primary web container ID from Google Tag Manager (e.g. GTM-XXXXXXX). Automatically injects the container script into your storefront.' mod='ps_gtm_datalayer'}
              </div>
            </div>

            <div class="ps-gtm-form-group">
              <label class="ps-gtm-form-label" for="PS_GTM_EVENT_PREFIX">
                {l s='DataLayer Event Prefix (Optional)' mod='ps_gtm_datalayer'}
              </label>
              <input type="text" name="PS_GTM_EVENT_PREFIX" id="PS_GTM_EVENT_PREFIX" class="ps-gtm-form-control" value="{$config_values.PS_GTM_EVENT_PREFIX|escape:'html':'UTF-8'}" placeholder="{l s='Enter custom prefix (e.g., raihan)' mod='ps_gtm_datalayer'}">
              <div class="ps-gtm-help-block">
                {l s='Optional custom prefix prepended before standard GA4 event names (e.g. ps_view_item, raihan_purchase). The canonical GA4 event name is always preserved in original_event.' mod='ps_gtm_datalayer'}
              </div>
            </div>
          </div>

          <!-- Card 2: Product Catalog Identifier Strategy -->
          <div class="ps-gtm-card">
            <div class="ps-gtm-card-title">
              <i class="icon-barcode text-primary"></i> {l s='Product Catalog Identifier Strategy' mod='ps_gtm_datalayer'}
            </div>
            <div class="ps-gtm-card-desc">
              {l s='Determines how product item_id, content_ids, and ecomm_prodid match your product catalog feeds across Meta, Google Merchant Center, TikTok, and Pinterest.' mod='ps_gtm_datalayer'}
            </div>

            <div class="ps-gtm-form-group">
              <label class="ps-gtm-form-label" for="PS_GTM_CATALOG_ID_STRATEGY">
                {l s='Catalog Identifier Strategy' mod='ps_gtm_datalayer'}
              </label>
              <select name="PS_GTM_CATALOG_ID_STRATEGY" id="PS_GTM_CATALOG_ID_STRATEGY" class="ps-gtm-form-control" style="max-width: 640px;">
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
              <div class="ps-gtm-help-block" style="margin-bottom: 12px;">
                {l s='Select the strategy that matches your product feed generator. Click any of the interactive strategy cards below to select it.' mod='ps_gtm_datalayer'}
              </div>

              <!-- Interactive 3-Card Visual Selector -->
              {$catalog_strategy_help_html nofilter}
            </div>
          </div>

          <!-- Panel Save Button -->
          <div class="ps-gtm-panel-footer">
            <button type="submit" name="submit{$module_name|escape:'html':'UTF-8'}" class="btn btn-primary btn-lg ps-gtm-save-btn">
              <i class="icon-save"></i> {l s='Save Settings' mod='ps_gtm_datalayer'}
            </button>
          </div>

        </div>

        <!-- ============================================================= -->
        <!-- TAB 2: PLATFORM ROUTING                                       -->
        <!-- ============================================================= -->
        <div id="tab-routing" class="ps-gtm-tab-pane">

          <div class="ps-gtm-panel-header">
            <h3 class="ps-gtm-panel-title">
              <i class="icon-share-alt text-primary"></i> {l s='Advertising Platform DataLayers & Event Routing' mod='ps_gtm_datalayer'}
            </h3>
            <div class="ps-gtm-panel-desc">
              {l s='Control advertising platform namespaces (Meta, Google Ads, TikTok, Pinterest, Microsoft Ads) and route verified ecommerce events to each channel.' mod='ps_gtm_datalayer'}
            </div>
          </div>

          <!-- Advertising Master Switch Callout -->
          <div class="ps-gtm-card" style="border-left: 4px solid #2563eb;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
              <div>
                <div style="font-size: 17px; font-weight: 700; color: #0f172a;">
                  <i class="icon-bullhorn text-primary"></i> {l s='Enable Advertising DataLayers (Master Switch)' mod='ps_gtm_datalayer'}
                </div>
                <div style="font-size: 14px; color: #475569; margin-top: 3px; line-height: 1.5;">
                  {l s='Master toggle. When disabled, all advertising platform namespaces (meta, google_ads, tiktok, pinterest, microsoft_ads) are suppressed from window.dataLayer across all events.' mod='ps_gtm_datalayer'}
                </div>
              </div>
              <label class="ps-gtm-switch">
                <input type="checkbox" name="PS_GTM_ADV_MASTER_ENABLED" value="1" {if $config_values.PS_GTM_ADV_MASTER_ENABLED}checked{/if}>
                <span class="ps-gtm-slider"></span>
              </label>
            </div>
          </div>

          <!-- View Mode & Bulk Controls Toolbar -->
          <div class="ps-gtm-filters-bar">
            <div style="display: flex; align-items: center; gap: 10px;">
              <strong style="color: #0f172a; font-size: 14.5px;">{l s='Display Mode:' mod='ps_gtm_datalayer'}</strong>
              <div class="btn-group">
                <button type="button" class="btn btn-default btn-sm active" id="ps-gtm-btn-view-matrix" onclick="psGtmSwitchRoutingView('matrix')" style="font-size: 14px; font-weight: 600; padding: 7px 14px;">
                  <i class="icon-th"></i> {l s='Matrix View' mod='ps_gtm_datalayer'}
                </button>
                <button type="button" class="btn btn-default btn-sm" id="ps-gtm-btn-view-cards" onclick="psGtmSwitchRoutingView('cards')" style="font-size: 14px; font-weight: 600; padding: 7px 14px;">
                  <i class="icon-th-list"></i> {l s='Platform Cards' mod='ps_gtm_datalayer'}
                </button>
              </div>
            </div>

            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
              <button type="button" class="btn btn-success btn-sm" onclick="psGtmToggleAllPlatforms(true)" style="font-size: 13.5px; font-weight: 600; padding: 7px 14px;">
                <i class="icon-check-circle"></i> {l s='Enable All Platforms' mod='ps_gtm_datalayer'}
              </button>
              <button type="button" class="btn btn-warning btn-sm" onclick="psGtmToggleAllPlatforms(false)" style="font-size: 13.5px; font-weight: 600; padding: 7px 14px;">
                <i class="icon-ban"></i> {l s='Disable All Platforms' mod='ps_gtm_datalayer'}
              </button>
            </div>
          </div>

          <!-- VIEW 1: Matrix View (Compact Cross-Grid) -->
          <div id="ps-gtm-routing-matrix-view">
            <table class="ps-gtm-table ps-gtm-matrix-table">
              <thead>
                <tr>
                  <th style="width: 250px; text-align: left; font-size: 14px; font-weight: 700; color: #0f172a;">{l s='Ecommerce Event' mod='ps_gtm_datalayer'}</th>
                  <th style="width: 140px;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                      <img src="{$module_path|escape:'html':'UTF-8'}views/img/meta.png" alt="Meta" style="width: 18px; height: 18px;">
                      <span style="font-size: 14px; font-weight: 700; color: #0f172a;">Meta</span>
                    </div>
                    <div style="margin-top: 6px;">
                      <button type="button" class="btn btn-default btn-xs" onclick="psGtmTogglePlatformColumn('meta', true)" title="{l s='Enable All' mod='ps_gtm_datalayer'}" style="font-size: 13px; padding: 4px 8px;"><i class="icon-check text-success"></i></button>
                      <button type="button" class="btn btn-default btn-xs" onclick="psGtmTogglePlatformColumn('meta', false)" title="{l s='Disable All' mod='ps_gtm_datalayer'}" style="font-size: 13px; padding: 4px 8px;"><i class="icon-times text-danger"></i></button>
                    </div>
                  </th>
                  <th style="width: 140px;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                      <img src="{$module_path|escape:'html':'UTF-8'}views/img/google_ads.png" alt="Google Ads" style="width: 18px; height: 18px;">
                      <span style="font-size: 14px; font-weight: 700; color: #0f172a;">Google Ads</span>
                    </div>
                    <div style="margin-top: 6px;">
                      <button type="button" class="btn btn-default btn-xs" onclick="psGtmTogglePlatformColumn('gads', true)" title="{l s='Enable All' mod='ps_gtm_datalayer'}" style="font-size: 13px; padding: 4px 8px;"><i class="icon-check text-success"></i></button>
                      <button type="button" class="btn btn-default btn-xs" onclick="psGtmTogglePlatformColumn('gads', false)" title="{l s='Disable All' mod='ps_gtm_datalayer'}" style="font-size: 13px; padding: 4px 8px;"><i class="icon-times text-danger"></i></button>
                    </div>
                  </th>
                  <th style="width: 140px;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                      <img src="{$module_path|escape:'html':'UTF-8'}views/img/tiktok.png" alt="TikTok" style="width: 18px; height: 18px;">
                      <span style="font-size: 14px; font-weight: 700; color: #0f172a;">TikTok</span>
                    </div>
                    <div style="margin-top: 6px;">
                      <button type="button" class="btn btn-default btn-xs" onclick="psGtmTogglePlatformColumn('tiktok', true)" title="{l s='Enable All' mod='ps_gtm_datalayer'}" style="font-size: 13px; padding: 4px 8px;"><i class="icon-check text-success"></i></button>
                      <button type="button" class="btn btn-default btn-xs" onclick="psGtmTogglePlatformColumn('tiktok', false)" title="{l s='Disable All' mod='ps_gtm_datalayer'}" style="font-size: 13px; padding: 4px 8px;"><i class="icon-times text-danger"></i></button>
                    </div>
                  </th>
                  <th style="width: 140px;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                      <img src="{$module_path|escape:'html':'UTF-8'}views/img/pinterest.png" alt="Pinterest" style="width: 18px; height: 18px;">
                      <span style="font-size: 14px; font-weight: 700; color: #0f172a;">Pinterest</span>
                    </div>
                    <div style="margin-top: 6px;">
                      <button type="button" class="btn btn-default btn-xs" onclick="psGtmTogglePlatformColumn('pinterest', true)" title="{l s='Enable All' mod='ps_gtm_datalayer'}" style="font-size: 13px; padding: 4px 8px;"><i class="icon-check text-success"></i></button>
                      <button type="button" class="btn btn-default btn-xs" onclick="psGtmTogglePlatformColumn('pinterest', false)" title="{l s='Disable All' mod='ps_gtm_datalayer'}" style="font-size: 13px; padding: 4px 8px;"><i class="icon-times text-danger"></i></button>
                    </div>
                  </th>
                  <th style="width: 140px;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                      <img src="{$module_path|escape:'html':'UTF-8'}views/img/microsoft_ads.png" alt="Microsoft Ads" style="width: 18px; height: 18px;">
                      <span style="font-size: 14px; font-weight: 700; color: #0f172a;">Microsoft Ads</span>
                    </div>
                    <div style="margin-top: 6px;">
                      <button type="button" class="btn btn-default btn-xs" onclick="psGtmTogglePlatformColumn('microsoft', true)" title="{l s='Enable All' mod='ps_gtm_datalayer'}" style="font-size: 13px; padding: 4px 8px;"><i class="icon-check text-success"></i></button>
                      <button type="button" class="btn btn-default btn-xs" onclick="psGtmTogglePlatformColumn('microsoft', false)" title="{l s='Disable All' mod='ps_gtm_datalayer'}" style="font-size: 13px; padding: 4px 8px;"><i class="icon-times text-danger"></i></button>
                    </div>
                  </th>
                </tr>
              </thead>
              <tbody>
                {foreach from=$canonical_events item=e}
                <tr>
                  <td style="text-align: left;">
                    <strong style="color: #0f172a; font-size: 15px;">{$e.label|escape:'html':'UTF-8'}</strong>
                    <div style="font-size: 12.5px; color: #64748b; font-family: SFMono-Regular, Menlo, monospace;">{$e.name|escape:'html':'UTF-8'}</div>
                  </td>
                  <td>
                    <label class="ps-gtm-switch ps-gtm-switch-sm">
                      <input type="checkbox" name="adv_meta_{$e.name|escape:'html':'UTF-8'}" value="1" {if in_array($e.name, $meta_active_events)}checked{/if}>
                      <span class="ps-gtm-slider"></span>
                    </label>
                  </td>
                  <td>
                    <label class="ps-gtm-switch ps-gtm-switch-sm">
                      <input type="checkbox" name="adv_gads_{$e.name|escape:'html':'UTF-8'}" value="1" {if in_array($e.name, $gads_active_events)}checked{/if}>
                      <span class="ps-gtm-slider"></span>
                    </label>
                  </td>
                  <td>
                    <label class="ps-gtm-switch ps-gtm-switch-sm">
                      <input type="checkbox" name="adv_tiktok_{$e.name|escape:'html':'UTF-8'}" value="1" {if in_array($e.name, $tiktok_active_events)}checked{/if}>
                      <span class="ps-gtm-slider"></span>
                    </label>
                  </td>
                  <td>
                    <label class="ps-gtm-switch ps-gtm-switch-sm">
                      <input type="checkbox" name="adv_pinterest_{$e.name|escape:'html':'UTF-8'}" value="1" {if in_array($e.name, $pinterest_active_events)}checked{/if}>
                      <span class="ps-gtm-slider"></span>
                    </label>
                  </td>
                  <td>
                    <label class="ps-gtm-switch ps-gtm-switch-sm">
                      <input type="checkbox" name="adv_microsoft_{$e.name|escape:'html':'UTF-8'}" value="1" {if in_array($e.name, $microsoft_active_events)}checked{/if}>
                      <span class="ps-gtm-slider"></span>
                    </label>
                  </td>
                </tr>
                {/foreach}
              </tbody>
            </table>
          </div>

          <!-- VIEW 2: Platform Cards View (Detailed Config per Platform) -->
          <div id="ps-gtm-routing-cards-view" style="display: none;">
            {$platform_cards_html nofilter}
          </div>

          <!-- Panel Save Button -->
          <div class="ps-gtm-panel-footer">
            <button type="submit" name="submit{$module_name|escape:'html':'UTF-8'}" class="btn btn-primary btn-lg ps-gtm-save-btn">
              <i class="icon-save"></i> {l s='Save Settings' mod='ps_gtm_datalayer'}
            </button>
          </div>

        </div>

        <!-- ============================================================= -->
        <!-- TAB 3: SERVER-SIDE TRACKING & WEBHOOKS (sGTM)                 -->
        <!-- ============================================================= -->
        <div id="tab-sgtm" class="ps-gtm-tab-pane">

          <div class="ps-gtm-panel-header">
            <h3 class="ps-gtm-panel-title">
              <i class="icon-server text-primary"></i> {l s='Server-Side Tracking & Webhooks (sGTM)' mod='ps_gtm_datalayer'}
            </h3>
            <div class="ps-gtm-panel-desc">
              {l s='Configure your first-party Server-Side Google Tag Manager transport domain and server-to-server webhook endpoints for refunds, offline orders, and order status transitions.' mod='ps_gtm_datalayer'}
            </div>
          </div>

          <!-- Card 1: Endpoints & Diagnostic Ping -->
          <div class="ps-gtm-card">
            <div class="ps-gtm-card-title">
              <i class="icon-cloud text-primary"></i> {l s='Server-Side GTM Endpoints' mod='ps_gtm_datalayer'}
            </div>
            <div class="ps-gtm-card-desc">
              {l s='First-party transport domain for Server-Side GTM and custom webhook routing paths.' mod='ps_gtm_datalayer'}
            </div>

            <div class="ps-gtm-form-group">
              <label class="ps-gtm-form-label" for="PS_SGTM_TRANSPORT_URL">
                {l s='Server-Side GTM Transport URL (Optional)' mod='ps_gtm_datalayer'}
              </label>
              <input type="text" name="PS_SGTM_TRANSPORT_URL" id="PS_SGTM_TRANSPORT_URL" class="ps-gtm-form-control" value="{$config_values.PS_SGTM_TRANSPORT_URL|escape:'html':'UTF-8'}" placeholder="https://gtm.yourdomain.com">
              <div class="ps-gtm-help-block">
                {l s='First-party domain endpoint for Server-Side GTM (e.g. https://gtm.yourdomain.com or your Stape container URL). Enhances privacy and prevents ad-blocker loss.' mod='ps_gtm_datalayer'}
              </div>
            </div>

            <div class="ps-gtm-form-group">
              <label class="ps-gtm-form-label" for="PS_SGTM_WEBHOOK_ENDPOINT_PATH">
                {l s='Server GTM Webhook Path (Optional)' mod='ps_gtm_datalayer'}
              </label>
              <input type="text" name="PS_SGTM_WEBHOOK_ENDPOINT_PATH" id="PS_SGTM_WEBHOOK_ENDPOINT_PATH" class="ps-gtm-form-control" value="{$config_values.PS_SGTM_WEBHOOK_ENDPOINT_PATH|escape:'html':'UTF-8'}" placeholder="/ps-webhook">
              <div class="ps-gtm-help-block">
                {l s='HTTP path claimed by your sGTM Client. Default is /ps-webhook for the official PrestaShop Webhook Client. If using Stape Data Client, enter /data.' mod='ps_gtm_datalayer'}
              </div>
            </div>

            <!-- Test Connection Ping Button -->
            <div style="margin-top: 18px; padding-top: 16px; border-top: 1px solid #edf2f7; display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
              <button type="button" id="ps-gtm-ping-btn" class="btn btn-default" style="font-size: 14.5px; font-weight: 600; padding: 9px 18px;">
                <i class="icon-bolt text-warning"></i> {l s='Test Server Connection (Ping)' mod='ps_gtm_datalayer'}
              </button>
              <span style="font-size: 13.5px; color: #64748b;">
                {l s='Dispatches a non-blocking diagnostic test request from your server to your sGTM container.' mod='ps_gtm_datalayer'}
              </span>
            </div>
            <div id="ps-gtm-ping-result" style="display: none; margin-top: 14px;"></div>
          </div>

          <!-- Card 2: Server-to-Server Event Webhooks -->
          <div class="ps-gtm-card">
            <div class="ps-gtm-card-title">
              <i class="icon-exchange text-primary"></i> {l s='Server-to-Server Event Webhooks' mod='ps_gtm_datalayer'}
            </div>
            <div class="ps-gtm-card-desc">
              {l s='Asynchronously dispatch verified backend orders, refunds, and status updates directly to your sGTM container.' mod='ps_gtm_datalayer'}
            </div>

            <div style="display: flex; flex-direction: column; gap: 18px;">
              <!-- Refund Webhook -->
              <div style="display: flex; align-items: center; justify-content: space-between; max-width: 780px;">
                <div>
                  <strong style="font-size: 15.5px; color: #0f172a;">{l s='Server-to-Server Refund Webhooks' mod='ps_gtm_datalayer'}</strong>
                  <div style="font-size: 14px; color: #475569; margin-top: 3px; line-height: 1.5;">
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
                  <strong style="font-size: 15.5px; color: #0f172a;">{l s='Server-to-Server Offline Order Webhooks' mod='ps_gtm_datalayer'}</strong>
                  <div style="font-size: 14px; color: #475569; margin-top: 3px; line-height: 1.5;">
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
                  <strong style="font-size: 15.5px; color: #0f172a;">{l s='Server-to-Server Order Status Change Webhooks' mod='ps_gtm_datalayer'}</strong>
                  <div style="font-size: 14px; color: #475569; margin-top: 3px; line-height: 1.5;">
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

          <!-- Panel Save Button -->
          <div class="ps-gtm-panel-footer">
            <button type="submit" name="submit{$module_name|escape:'html':'UTF-8'}" class="btn btn-primary btn-lg ps-gtm-save-btn">
              <i class="icon-save"></i> {l s='Save Settings' mod='ps_gtm_datalayer'}
            </button>
          </div>

        </div>

        <!-- ============================================================= -->
        <!-- TAB 4: ADVANCED SCRIPT INJECTION                              -->
        <!-- ============================================================= -->
        <div id="tab-scripts" class="ps-gtm-tab-pane">

          <div class="ps-gtm-panel-header">
            <h3 class="ps-gtm-panel-title">
              <i class="icon-code text-primary"></i> {l s='Advanced Script Injection (Optional)' mod='ps_gtm_datalayer'}
            </h3>
            <div class="ps-gtm-panel-desc">
              {l s='Inject custom agency scripts directly into HTML <head> and <body> without modifying theme template files.' mod='ps_gtm_datalayer'}
            </div>
          </div>

          <div class="ps-gtm-card">
            <div class="ps-gtm-card-title">
              <i class="icon-terminal text-primary"></i> {l s='Custom HTML Script Snippets' mod='ps_gtm_datalayer'}
            </div>
            <div class="ps-gtm-card-desc">
              {l s='Optional raw snippets. Standard GTM container scripts are generated automatically from your GTM Container ID.' mod='ps_gtm_datalayer'}
            </div>

            <div class="ps-gtm-form-group">
              <label class="ps-gtm-form-label" for="PS_GTM_RAW_HEAD_CODE">
                {l s='Raw <head> Code' mod='ps_gtm_datalayer'}
              </label>
              <textarea name="PS_GTM_RAW_HEAD_CODE" id="PS_GTM_RAW_HEAD_CODE" rows="4" class="ps-gtm-form-control" style="font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 13.5px; line-height: 1.5; padding: 12px 14px;">{$config_values.PS_GTM_RAW_HEAD_CODE|escape:'html':'UTF-8'}</textarea>
              <div class="ps-gtm-help-block">
                {l s='Custom script tag injected before closing </head>. Useful for custom verification meta tags or third-party marketing tags.' mod='ps_gtm_datalayer'}
              </div>
            </div>

            <div class="ps-gtm-form-group">
              <label class="ps-gtm-form-label" for="PS_GTM_RAW_BODY_CODE">
                {l s='Raw <body> Code' mod='ps_gtm_datalayer'}
              </label>
              <textarea name="PS_GTM_RAW_BODY_CODE" id="PS_GTM_RAW_BODY_CODE" rows="3" class="ps-gtm-form-control" style="font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 13.5px; line-height: 1.5; padding: 12px 14px;">{$config_values.PS_GTM_RAW_BODY_CODE|escape:'html':'UTF-8'}</textarea>
              <div class="ps-gtm-help-block">
                {l s='Custom noscript iframe fallback injected immediately after the opening <body> tag.' mod='ps_gtm_datalayer'}
              </div>
            </div>
          </div>

          <!-- Panel Save Button -->
          <div class="ps-gtm-panel-footer">
            <button type="submit" name="submit{$module_name|escape:'html':'UTF-8'}" class="btn btn-primary btn-lg ps-gtm-save-btn">
              <i class="icon-save"></i> {l s='Save Settings' mod='ps_gtm_datalayer'}
            </button>
          </div>

        </div>

      </main>
    </div>

    <!-- Unsaved Changes Floating Banner -->
    <div id="ps-gtm-unsaved-alert" class="ps-gtm-unsaved-alert" style="display: none;">
      <div style="display: flex; align-items: center; gap: 12px;">
        <i class="icon-warning-sign" style="font-size: 20px; color: #f59e0b;"></i>
        <div style="font-size: 14.5px; color: #0f172a;">
          <strong>{l s='Unsaved Changes' mod='ps_gtm_datalayer'}:</strong> {l s='You have modified settings. Click "Save Settings" to apply your changes.' mod='ps_gtm_datalayer'}
        </div>
      </div>
      <button type="submit" name="submit{$module_name|escape:'html':'UTF-8'}" class="btn btn-primary btn-sm" style="font-size: 13.5px; font-weight: 700; padding: 6px 16px;">
        <i class="icon-save"></i> {l s='Save Now' mod='ps_gtm_datalayer'}
      </button>
    </div>

    <!-- Sticky Save Action Bar -->
    <div class="ps-gtm-save-bar">
      <div style="font-size: 14.5px; color: #475569;">
        <i class="icon-info-circle text-primary"></i> {l s='All settings across all panels are saved simultaneously with zero data loss.' mod='ps_gtm_datalayer'}
      </div>
      <button type="submit" name="submit{$module_name|escape:'html':'UTF-8'}" id="ps-gtm-save-btn" class="ps-gtm-btn-save">
        <i class="icon-save"></i> <span>{l s='Save All Settings' mod='ps_gtm_datalayer'}</span>
      </button>
    </div>

  </form>

</div>

<script type="text/javascript">
  window.psGtmAjaxAdminUrl = "{$ajax_admin_url|escape:'javascript':'UTF-8'}";
  window.psGtmEventPrefix = "{$config_values.PS_GTM_EVENT_PREFIX|escape:'javascript':'UTF-8'}";
</script>
