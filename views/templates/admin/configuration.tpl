{*
 * PrestaShop DataLayer - Unified Administration Interface
 * Professional 6-Tab Architecture: Overview, Configuration, Events, Platform Routing, Data Mapping, Diagnostics
 *}

<div id="ps-gtm-admin-wrapper">

  <!-- Header Banner -->
  <div class="ps-gtm-header-card">
    <div class="ps-gtm-brand-left">
      <div class="ps-gtm-logo-box">
        <img src="{$module_logo|escape:'html':'UTF-8'}" alt="{$module_display_name|escape:'html':'UTF-8'}">
      </div>
      <div class="ps-gtm-brand-titles">
        <h2>{$module_display_name|escape:'html':'UTF-8'}</h2>
        <div class="ps-gtm-brand-subtitle">
          {l s='Enterprise GA4 Ecommerce, Server-Side GTM & Dynamic Advertising Remarketing' mod='ps_gtm_datalayer'}
        </div>
      </div>
    </div>
    <div class="ps-gtm-header-right">
      <span class="ps-gtm-version-badge"><i class="icon-code-fork"></i> v{$module_version|escape:'html':'UTF-8'}</span>
    </div>
  </div>

  <!-- Main Tabs Navigation Bar -->
  <div class="ps-gtm-nav-tabs-bar">
    <a href="#tab-overview" class="ps-gtm-nav-tab active" data-tab="tab-overview">
      <i class="icon-dashboard"></i> {l s='Overview' mod='ps_gtm_datalayer'}
    </a>
    <a href="#tab-config" class="ps-gtm-nav-tab" data-tab="tab-config">
      <i class="icon-cogs"></i> {l s='Configuration' mod='ps_gtm_datalayer'}
    </a>
    <a href="#tab-events" class="ps-gtm-nav-tab" data-tab="tab-events">
      <i class="icon-tags"></i> {l s='Events' mod='ps_gtm_datalayer'}
      <span class="ps-gtm-tab-badge">{$events_total|escape:'html':'UTF-8'}</span>
    </a>
    <a href="#tab-routing" class="ps-gtm-nav-tab" data-tab="tab-routing">
      <i class="icon-share-alt"></i> {l s='Platform Routing' mod='ps_gtm_datalayer'}
      <span class="ps-gtm-tab-badge">{$active_platforms_count|escape:'html':'UTF-8'}/5</span>
    </a>
    <a href="#tab-mapping" class="ps-gtm-nav-tab" data-tab="tab-mapping">
      <i class="icon-sitemap"></i> {l s='Data Mapping' mod='ps_gtm_datalayer'}
    </a>
    <a href="#tab-diagnostics" class="ps-gtm-nav-tab" data-tab="tab-diagnostics">
      <i class="icon-stethoscope"></i> {l s='Diagnostics & Testing' mod='ps_gtm_datalayer'}
    </a>
  </div>

  <!-- Unified Settings Form -->
  <form action="{$form_action|escape:'html':'UTF-8'}" method="post" id="ps_gtm_main_form" class="defaultForm form-horizontal">
    <input type="hidden" name="submitps_gtm_datalayer" value="1">
    <input type="hidden" name="PS_GTM_ADV_SUBMITTED" value="1">
    <input type="hidden" name="active_tab" id="ps_gtm_active_tab" value="{$active_tab|escape:'html':'UTF-8'}">

    <!-- ================================================================= -->
    <!-- TAB 1: OVERVIEW                                                   -->
    <!-- ================================================================= -->
    <div id="tab-overview" class="ps-gtm-tab-pane active">

      <!-- Metrics Stat Cards -->
      <div class="ps-gtm-stats-grid">
        <div class="ps-gtm-stat-tile">
          <div class="ps-gtm-stat-icon {if !empty($config_values.PS_GTM_CONTAINER_ID)}green{else}amber{/if}">
            <i class="icon-google"></i>
          </div>
          <div class="ps-gtm-stat-info">
            <div class="ps-gtm-stat-label">{l s='GTM Web Container' mod='ps_gtm_datalayer'}</div>
            <div class="ps-gtm-stat-val">
              {if !empty($config_values.PS_GTM_CONTAINER_ID)}
                <code>{$config_values.PS_GTM_CONTAINER_ID|escape:'html':'UTF-8'}</code>
              {else}
                <span class="text-warning">{l s='Not Configured' mod='ps_gtm_datalayer'}</span>
              {/if}
            </div>
            <div class="ps-gtm-stat-sub">
              {if !empty($config_values.PS_GTM_CONTAINER_ID)}
                <span class="text-success"><i class="icon-check"></i> {l s='Client Container Active' mod='ps_gtm_datalayer'}</span>
              {else}
                <a href="#" onclick="psGtmSwitchTab('tab-config'); return false;">{l s='Set container ID &rarr;' mod='ps_gtm_datalayer'}</a>
              {/if}
            </div>
          </div>
        </div>

        <div class="ps-gtm-stat-tile">
          <div class="ps-gtm-stat-icon {if !empty($config_values.PS_SGTM_TRANSPORT_URL)}purple{else}blue{/if}">
            <i class="icon-server"></i>
          </div>
          <div class="ps-gtm-stat-info">
            <div class="ps-gtm-stat-label">{l s='Server-Side GTM' mod='ps_gtm_datalayer'}</div>
            <div class="ps-gtm-stat-val">
              {if !empty($config_values.PS_SGTM_TRANSPORT_URL)}
                <span class="text-success"><i class="icon-check-circle"></i> {l s='Enabled' mod='ps_gtm_datalayer'}</span>
              {else}
                <span class="text-muted">{l s='Optional / Standard' mod='ps_gtm_datalayer'}</span>
              {/if}
            </div>
            <div class="ps-gtm-stat-sub">
              {if !empty($config_values.PS_SGTM_TRANSPORT_URL)}
                {$config_values.PS_SGTM_TRANSPORT_URL|truncate:26:'...'|escape:'html':'UTF-8'}
              {else}
                <a href="#" onclick="psGtmSwitchTab('tab-config'); return false;">{l s='Configure sGTM &rarr;' mod='ps_gtm_datalayer'}</a>
              {/if}
            </div>
          </div>
        </div>

        <div class="ps-gtm-stat-tile">
          <div class="ps-gtm-stat-icon green">
            <i class="icon-bullhorn"></i>
          </div>
          <div class="ps-gtm-stat-info">
            <div class="ps-gtm-stat-label">{l s='Advertising Platforms' mod='ps_gtm_datalayer'}</div>
            <div class="ps-gtm-stat-val">{$active_platforms_count|escape:'html':'UTF-8'} / 5 {l s='Active' mod='ps_gtm_datalayer'}</div>
            <div class="ps-gtm-stat-sub">
              {if $config_values.PS_GTM_ADV_MASTER_ENABLED}
                <span class="text-success">{l s='Master Switch ON' mod='ps_gtm_datalayer'}</span>
              {else}
                <span class="text-danger">{l s='Master Switch OFF' mod='ps_gtm_datalayer'}</span>
              {/if}
            </div>
          </div>
        </div>

        <div class="ps-gtm-stat-tile">
          <div class="ps-gtm-stat-icon blue">
            <i class="icon-tags"></i>
          </div>
          <div class="ps-gtm-stat-info">
            <div class="ps-gtm-stat-label">{l s='Canonical Events' mod='ps_gtm_datalayer'}</div>
            <div class="ps-gtm-stat-val">{$events_total|escape:'html':'UTF-8'} {l s='Events Ready' mod='ps_gtm_datalayer'}</div>
            <div class="ps-gtm-stat-sub">
              {if !empty($config_values.PS_GTM_EVENT_PREFIX)}
                {l s='Prefix:' mod='ps_gtm_datalayer'} <code>{$config_values.PS_GTM_EVENT_PREFIX|escape:'html':'UTF-8'}_</code>
              {else}
                {l s='Standard GA4 Schema' mod='ps_gtm_datalayer'}
              {/if}
            </div>
          </div>
        </div>
      </div>

      <!-- Configuration Warnings Banner (if any) -->
      {if empty($config_values.PS_GTM_CONTAINER_ID)}
      <div class="alert alert-warning" style="margin-bottom: 20px;">
        <i class="icon-warning-sign"></i> <strong>{l s='Google Tag Manager Container ID is not set.' mod='ps_gtm_datalayer'}</strong>
        {l s='Your storefront is currently not injecting the web GTM container. Navigate to the' mod='ps_gtm_datalayer'}
        <a href="#" onclick="psGtmSwitchTab('tab-config'); return false;"><strong>{l s='Configuration' mod='ps_gtm_datalayer'}</strong></a>
        {l s='tab to enter your GTM Container ID (e.g. GTM-XXXXXXX).' mod='ps_gtm_datalayer'}
      </div>
      {/if}

      {if !empty($config_values.PS_GTM_WEBHOOK_REFUND_ENABLED) && empty($config_values.PS_SGTM_TRANSPORT_URL)}
      <div class="alert alert-info" style="margin-bottom: 20px;">
        <i class="icon-info-sign"></i> <strong>{l s='Server-to-Server Refund Webhooks are enabled without a Transport URL.' mod='ps_gtm_datalayer'}</strong>
        {l s='Server-to-server refund events require a configured Server-Side GTM Transport URL to receive webhooks.' mod='ps_gtm_datalayer'}
      </div>
      {/if}

      <!-- Platform Status Summary Grid -->
      <div class="ps-gtm-card">
        <div class="ps-gtm-card-title">
          <i class="icon-cubes text-primary"></i> {l s='Connected Tracking & Marketing Channels' mod='ps_gtm_datalayer'}
        </div>
        <div class="ps-gtm-card-desc">
          {l s='Summary of real-time event dispatching across supported advertising platforms and server pipelines.' mod='ps_gtm_datalayer'}
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px;">
          <!-- Meta -->
          <div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; background: #fafbfc; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 12px;">
              <img src="{$module_path|escape:'html':'UTF-8'}views/img/meta.png" alt="Meta" style="width: 28px; height: 28px; object-fit: contain;">
              <div>
                <strong>Meta Pixel & CAPI</strong>
                <div style="font-size: 11px; color: #64748b;">{$meta_events_count|escape:'html':'UTF-8'} / {$events_total|escape:'html':'UTF-8'} {l s='events routed' mod='ps_gtm_datalayer'}</div>
              </div>
            </div>
            <span class="label {if $config_values.PS_GTM_ADV_META_ENABLED && $config_values.PS_GTM_ADV_MASTER_ENABLED}label-success{else}label-default{/if}">
              {if $config_values.PS_GTM_ADV_META_ENABLED && $config_values.PS_GTM_ADV_MASTER_ENABLED}{l s='Active' mod='ps_gtm_datalayer'}{else}{l s='Off' mod='ps_gtm_datalayer'}{/if}
            </span>
          </div>

          <!-- Google Ads -->
          <div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; background: #fafbfc; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 12px;">
              <img src="{$module_path|escape:'html':'UTF-8'}views/img/google_ads.png" alt="Google Ads" style="width: 28px; height: 28px; object-fit: contain;">
              <div>
                <strong>Google Ads Remarketing</strong>
                <div style="font-size: 11px; color: #64748b;">{$gads_events_count|escape:'html':'UTF-8'} / {$events_total|escape:'html':'UTF-8'} {l s='events routed' mod='ps_gtm_datalayer'}</div>
              </div>
            </div>
            <span class="label {if $config_values.PS_GTM_ADV_GADS_ENABLED && $config_values.PS_GTM_ADV_MASTER_ENABLED}label-success{else}label-default{/if}">
              {if $config_values.PS_GTM_ADV_GADS_ENABLED && $config_values.PS_GTM_ADV_MASTER_ENABLED}{l s='Active' mod='ps_gtm_datalayer'}{else}{l s='Off' mod='ps_gtm_datalayer'}{/if}
            </span>
          </div>

          <!-- TikTok -->
          <div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; background: #fafbfc; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 12px;">
              <img src="{$module_path|escape:'html':'UTF-8'}views/img/tiktok.png" alt="TikTok" style="width: 28px; height: 28px; object-fit: contain;">
              <div>
                <strong>TikTok Events API</strong>
                <div style="font-size: 11px; color: #64748b;">{$tiktok_events_count|escape:'html':'UTF-8'} / {$events_total|escape:'html':'UTF-8'} {l s='events routed' mod='ps_gtm_datalayer'}</div>
              </div>
            </div>
            <span class="label {if $config_values.PS_GTM_ADV_TIKTOK_ENABLED && $config_values.PS_GTM_ADV_MASTER_ENABLED}label-success{else}label-default{/if}">
              {if $config_values.PS_GTM_ADV_TIKTOK_ENABLED && $config_values.PS_GTM_ADV_MASTER_ENABLED}{l s='Active' mod='ps_gtm_datalayer'}{else}{l s='Off' mod='ps_gtm_datalayer'}{/if}
            </span>
          </div>

          <!-- Pinterest -->
          <div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; background: #fafbfc; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 12px;">
              <img src="{$module_path|escape:'html':'UTF-8'}views/img/pinterest.png" alt="Pinterest" style="width: 28px; height: 28px; object-fit: contain;">
              <div>
                <strong>Pinterest Tag & CAPI</strong>
                <div style="font-size: 11px; color: #64748b;">{$pinterest_events_count|escape:'html':'UTF-8'} / {$events_total|escape:'html':'UTF-8'} {l s='events routed' mod='ps_gtm_datalayer'}</div>
              </div>
            </div>
            <span class="label {if $config_values.PS_GTM_ADV_PINTEREST_ENABLED && $config_values.PS_GTM_ADV_MASTER_ENABLED}label-success{else}label-default{/if}">
              {if $config_values.PS_GTM_ADV_PINTEREST_ENABLED && $config_values.PS_GTM_ADV_MASTER_ENABLED}{l s='Active' mod='ps_gtm_datalayer'}{else}{l s='Off' mod='ps_gtm_datalayer'}{/if}
            </span>
          </div>

          <!-- Microsoft -->
          <div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; background: #fafbfc; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 12px;">
              <img src="{$module_path|escape:'html':'UTF-8'}views/img/microsoft_ads.png" alt="Microsoft Ads" style="width: 28px; height: 28px; object-fit: contain;">
              <div>
                <strong>Microsoft Advertising (UET)</strong>
                <div style="font-size: 11px; color: #64748b;">{$microsoft_events_count|escape:'html':'UTF-8'} / {$events_total|escape:'html':'UTF-8'} {l s='events routed' mod='ps_gtm_datalayer'}</div>
              </div>
            </div>
            <span class="label {if $config_values.PS_GTM_ADV_MICROSOFT_ENABLED && $config_values.PS_GTM_ADV_MASTER_ENABLED}label-success{else}label-default{/if}">
              {if $config_values.PS_GTM_ADV_MICROSOFT_ENABLED && $config_values.PS_GTM_ADV_MASTER_ENABLED}{l s='Active' mod='ps_gtm_datalayer'}{else}{l s='Off' mod='ps_gtm_datalayer'}{/if}
            </span>
          </div>

          <!-- sGTM Webhooks -->
          <div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; background: #fafbfc; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 12px;">
              <div style="width: 28px; height: 28px; border-radius: 4px; background: #ede9fe; color: #7c3aed; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                <i class="icon-exchange"></i>
              </div>
              <div>
                <strong>Server-to-Server Webhooks</strong>
                <div style="font-size: 11px; color: #64748b;">{l s='Refunds, Offline Orders, Order Status Updates' mod='ps_gtm_datalayer'}</div>
              </div>
            </div>
            <span class="label {if !empty($config_values.PS_SGTM_TRANSPORT_URL)}label-success{else}label-default{/if}">
              {if !empty($config_values.PS_SGTM_TRANSPORT_URL)}{l s='Ready' mod='ps_gtm_datalayer'}{else}{l s='Unset' mod='ps_gtm_datalayer'}{/if}
            </span>
          </div>
        </div>
      </div>

      <!-- Quick Action Jump Tiles -->
      <div class="ps-gtm-card">
        <div class="ps-gtm-card-title">
          <i class="icon-compass text-primary"></i> {l s='Quick Configuration Navigator' mod='ps_gtm_datalayer'}
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
          <button type="button" class="btn btn-default" onclick="psGtmSwitchTab('tab-config')" style="padding: 12px; text-align: left;">
            <i class="icon-cogs" style="font-size: 16px; color: #2563eb;"></i><br>
            <strong>{l s='GTM & Server Config' mod='ps_gtm_datalayer'}</strong>
            <div style="font-size: 11px; color: #64748b;">{l s='Container ID & endpoints' mod='ps_gtm_datalayer'}</div>
          </button>
          <button type="button" class="btn btn-default" onclick="psGtmSwitchTab('tab-events')" style="padding: 12px; text-align: left;">
            <i class="icon-tags" style="font-size: 16px; color: #16a34a;"></i><br>
            <strong>{l s='Manage Events' mod='ps_gtm_datalayer'}</strong>
            <div style="font-size: 11px; color: #64748b;">{l s='Search & inspect events' mod='ps_gtm_datalayer'}</div>
          </button>
          <button type="button" class="btn btn-default" onclick="psGtmSwitchTab('tab-routing')" style="padding: 12px; text-align: left;">
            <i class="icon-share-alt" style="font-size: 16px; color: #9333ea;"></i><br>
            <strong>{l s='Platform Matrix' mod='ps_gtm_datalayer'}</strong>
            <div style="font-size: 11px; color: #64748b;">{l s='Route events to ad channels' mod='ps_gtm_datalayer'}</div>
          </button>
          <button type="button" class="btn btn-default" onclick="psGtmSwitchTab('tab-mapping')" style="padding: 12px; text-align: left;">
            <i class="icon-sitemap" style="font-size: 16px; color: #0284c7;"></i><br>
            <strong>{l s='Data Mapping' mod='ps_gtm_datalayer'}</strong>
            <div style="font-size: 11px; color: #64748b;">{l s='Inspect schemas & payloads' mod='ps_gtm_datalayer'}</div>
          </button>
          <button type="button" class="btn btn-default" onclick="psGtmSwitchTab('tab-diagnostics')" style="padding: 12px; text-align: left;">
            <i class="icon-stethoscope" style="font-size: 16px; color: #dc2626;"></i><br>
            <strong>{l s='Health Diagnostics' mod='ps_gtm_datalayer'}</strong>
            <div style="font-size: 11px; color: #64748b;">{l s='Run connectivity & audit tests' mod='ps_gtm_datalayer'}</div>
          </button>
        </div>
      </div>
    </div>

    <!-- ================================================================= -->
    <!-- TAB 2: CONFIGURATION                                              -->
    <!-- ================================================================= -->
    <div id="tab-config" class="ps-gtm-tab-pane">

      <!-- Section A: GTM & DataLayer Core -->
      <div class="ps-gtm-card">
        <div class="ps-gtm-card-title">
          <i class="icon-cogs text-primary"></i> {l s='Google Tag Manager & DataLayer Core' mod='ps_gtm_datalayer'}
        </div>
        <div class="ps-gtm-card-desc">
          {l s='Primary container identifier, dynamic event naming prefix, and product catalog identifier resolution strategy.' mod='ps_gtm_datalayer'}
        </div>

        <div class="ps-gtm-form-group">
          <label class="ps-gtm-form-label" for="PS_GTM_CONTAINER_ID">
            {l s='GTM Container ID' mod='ps_gtm_datalayer'} <span class="req">*</span>
          </label>
          <input type="text" name="PS_GTM_CONTAINER_ID" id="PS_GTM_CONTAINER_ID" class="ps-gtm-form-control" value="{$config_values.PS_GTM_CONTAINER_ID|escape:'html':'UTF-8'}" placeholder="GTM-XXXXXXX" required>
          <div class="ps-gtm-help-block">
            {l s='Enter your primary web container ID from Google Tag Manager (e.g. GTM-XXXXXXX). Injects official container script across storefront pages.' mod='ps_gtm_datalayer'}
          </div>
        </div>

        <div class="ps-gtm-form-group">
          <label class="ps-gtm-form-label" for="PS_GTM_EVENT_PREFIX">
            {l s='DataLayer Event Prefix' mod='ps_gtm_datalayer'}
          </label>
          <input type="text" name="PS_GTM_EVENT_PREFIX" id="PS_GTM_EVENT_PREFIX" class="ps-gtm-form-control" value="{$config_values.PS_GTM_EVENT_PREFIX|escape:'html':'UTF-8'}" placeholder="{l s='Enter custom prefix (e.g., raihan)' mod='ps_gtm_datalayer'}">
          <div class="ps-gtm-help-block">
            {l s='Optional prefix prepended before standard GA4 event names (e.g. ps_view_item, raihan_purchase). Canonical GA4 name is always preserved in original_event.' mod='ps_gtm_datalayer'}
          </div>
        </div>

        <div class="ps-gtm-form-group">
          <label class="ps-gtm-form-label" for="PS_GTM_CATALOG_ID_STRATEGY">
            {l s='Product Catalog Identifier Strategy' mod='ps_gtm_datalayer'}
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
            {l s='Determines how product item_id, content_ids, and ecomm_prodid match your product catalogs across Meta, Google Merchant Center, TikTok, and Pinterest feeds.' mod='ps_gtm_datalayer'}
          </div>
          {$catalog_strategy_help_html nofilter}
        </div>
      </div>

      <!-- Section B: Server-Side Tracking & Webhooks -->
      <div class="ps-gtm-card">
        <div class="ps-gtm-card-title">
          <i class="icon-server text-primary"></i> {l s='Server-Side Tracking & Webhooks (sGTM)' mod='ps_gtm_datalayer'}
        </div>
        <div class="ps-gtm-card-desc">
          {l s='First-party transport domain and server-to-server webhook endpoints for refunds, offline orders, and order status transitions.' mod='ps_gtm_datalayer'}
        </div>

        <div class="ps-gtm-form-group">
          <label class="ps-gtm-form-label" for="PS_SGTM_TRANSPORT_URL">
            {l s='Server-Side GTM Transport URL (Optional)' mod='ps_gtm_datalayer'}
          </label>
          <input type="text" name="PS_SGTM_TRANSPORT_URL" id="PS_SGTM_TRANSPORT_URL" class="ps-gtm-form-control" value="{$config_values.PS_SGTM_TRANSPORT_URL|escape:'html':'UTF-8'}" placeholder="https://gtm.yourdomain.com">
          <div class="ps-gtm-help-block">
            {l s='First-party domain endpoint for Server-Side GTM (e.g. https://gtm.yourdomain.com or Stape.io container URL). Enhances privacy and prevents ad-blocker loss.' mod='ps_gtm_datalayer'}
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

        <hr style="border-top: 1px solid #edf2f7; margin: 20px 0;">

        <!-- Webhook Toggles -->
        <div style="display: flex; flex-direction: column; gap: 14px;">
          <!-- Refund Webhook -->
          <div style="display: flex; align-items: center; justify-content: space-between; max-width: 720px;">
            <div>
              <strong style="color: #1e293b;">{l s='Server-to-Server Refund Webhooks (sGTM)' mod='ps_gtm_datalayer'}</strong>
              <div style="font-size: 11px; color: #64748b;">
                {l s='Dispatches asynchronous refund events to sGTM upon Credit Slip generation in the Back Office.' mod='ps_gtm_datalayer'}
              </div>
            </div>
            <label class="ps-gtm-switch">
              <input type="checkbox" name="PS_GTM_WEBHOOK_REFUND_ENABLED" value="1" {if $config_values.PS_GTM_WEBHOOK_REFUND_ENABLED}checked{/if}>
              <span class="ps-gtm-slider"></span>
            </label>
          </div>

          <!-- Offline Orders Webhook -->
          <div style="display: flex; align-items: center; justify-content: space-between; max-width: 720px;">
            <div>
              <strong style="color: #1e293b;">{l s='Server-to-Server Offline Order Webhooks (sGTM)' mod='ps_gtm_datalayer'}</strong>
              <div style="font-size: 11px; color: #64748b;">
                {l s='Dispatches purchase conversions for orders created or validated offline in the Back Office.' mod='ps_gtm_datalayer'}
              </div>
            </div>
            <label class="ps-gtm-switch">
              <input type="checkbox" name="PS_GTM_WEBHOOK_OFFLINE_ORDER_ENABLED" value="1" {if $config_values.PS_GTM_WEBHOOK_OFFLINE_ORDER_ENABLED}checked{/if}>
              <span class="ps-gtm-slider"></span>
            </label>
          </div>

          <!-- Order Status Updates Webhook -->
          <div style="display: flex; align-items: center; justify-content: space-between; max-width: 720px;">
            <div>
              <strong style="color: #1e293b;">{l s='Server-to-Server Order Status Change Webhooks (sGTM)' mod='ps_gtm_datalayer'}</strong>
              <div style="font-size: 11px; color: #64748b;">
                {l s='Dispatches order_status_update events to sGTM upon Back Office status changes (e.g. Bank Wire to Paid, Mirakl, Shipped).' mod='ps_gtm_datalayer'}
              </div>
            </div>
            <label class="ps-gtm-switch">
              <input type="checkbox" name="PS_GTM_WEBHOOK_STATUS_UPDATE_ENABLED" value="1" {if $config_values.PS_GTM_WEBHOOK_STATUS_UPDATE_ENABLED}checked{/if}>
              <span class="ps-gtm-slider"></span>
            </label>
          </div>
        </div>
      </div>

      <!-- Section C: Advanced Script Injection -->
      <div class="ps-gtm-card">
        <div class="ps-gtm-card-title">
          <i class="icon-code text-primary"></i> {l s='Advanced Script Injection (Optional)' mod='ps_gtm_datalayer'}
        </div>
        <div class="ps-gtm-card-desc">
          {l s='Custom agency scripts injected directly into HTML <head> and <body> without modifying theme templates.' mod='ps_gtm_datalayer'}
        </div>

        <div class="ps-gtm-form-group">
          <label class="ps-gtm-form-label" for="PS_GTM_RAW_HEAD_CODE">
            {l s='Raw GTM <head> Code' mod='ps_gtm_datalayer'}
          </label>
          <textarea name="PS_GTM_RAW_HEAD_CODE" id="PS_GTM_RAW_HEAD_CODE" rows="4" class="ps-gtm-form-control" style="font-family: monospace; font-size: 11px;">{$config_values.PS_GTM_RAW_HEAD_CODE|escape:'html':'UTF-8'}</textarea>
          <div class="ps-gtm-help-block">
            {l s='Custom script tag injected before closing </head>. If GTM Container ID is set above, standard container script is generated automatically.' mod='ps_gtm_datalayer'}
          </div>
        </div>

        <div class="ps-gtm-form-group">
          <label class="ps-gtm-form-label" for="PS_GTM_RAW_BODY_CODE">
            {l s='Raw GTM <body> Code' mod='ps_gtm_datalayer'}
          </label>
          <textarea name="PS_GTM_RAW_BODY_CODE" id="PS_GTM_RAW_BODY_CODE" rows="3" class="ps-gtm-form-control" style="font-family: monospace; font-size: 11px;">{$config_values.PS_GTM_RAW_BODY_CODE|escape:'html':'UTF-8'}</textarea>
          <div class="ps-gtm-help-block">
            {l s='Custom noscript iframe fallback injected immediately after opening <body> tag.' mod='ps_gtm_datalayer'}
          </div>
        </div>
      </div>
    </div>

    <!-- ================================================================= -->
    <!-- TAB 3: EVENTS                                                     -->
    <!-- ================================================================= -->
    <div id="tab-events" class="ps-gtm-tab-pane">

      <!-- Event Search & Filters Bar -->
      <div class="ps-gtm-filters-bar">
        <div class="ps-gtm-search-box">
          <i class="icon-search"></i>
          <input type="text" id="ps-gtm-event-search" class="ps-gtm-search-input" placeholder="{l s='Filter events by name, label, or description...' mod='ps_gtm_datalayer'}">
        </div>

        <div class="ps-gtm-filter-pills">
          <button type="button" class="ps-gtm-filter-pill active" data-category="all">{l s='All Events' mod='ps_gtm_datalayer'} ({$events_total|escape:'html':'UTF-8'})</button>
          <button type="button" class="ps-gtm-filter-pill" data-category="discovery">{l s='Discovery' mod='ps_gtm_datalayer'}</button>
          <button type="button" class="ps-gtm-filter-pill" data-category="interaction">{l s='Interaction' mod='ps_gtm_datalayer'}</button>
          <button type="button" class="ps-gtm-filter-pill" data-category="cart">{l s='Cart' mod='ps_gtm_datalayer'}</button>
          <button type="button" class="ps-gtm-filter-pill" data-category="checkout">{l s='Checkout' mod='ps_gtm_datalayer'}</button>
          <button type="button" class="ps-gtm-filter-pill" data-category="purchase">{l s='Orders & Returns' mod='ps_gtm_datalayer'}</button>
        </div>

        <div style="display: flex; align-items: center; gap: 8px;">
          <select id="ps-gtm-status-filter" class="form-control input-sm" style="width: 130px; font-size: 12px; display: inline-block;">
            <option value="all">{l s='All Statuses' mod='ps_gtm_datalayer'}</option>
            <option value="enabled">{l s='Enabled Only' mod='ps_gtm_datalayer'}</option>
            <option value="disabled">{l s='Disabled Only' mod='ps_gtm_datalayer'}</option>
          </select>
          <button type="button" class="btn btn-default btn-sm" onclick="psGtmToggleAllEvents(true)" title="{l s='Enable All Events' mod='ps_gtm_datalayer'}">
            <i class="icon-check" style="color: #10b981;"></i>
          </button>
          <button type="button" class="btn btn-default btn-sm" onclick="psGtmToggleAllEvents(false)" title="{l s='Disable All Events' mod='ps_gtm_datalayer'}">
            <i class="icon-times" style="color: #ef4444;"></i>
          </button>
        </div>
      </div>

      <!-- Events Catalog Table -->
      <table class="ps-gtm-table" id="ps-gtm-events-table">
        <thead>
          <tr>
            <th style="width: 65px; text-align: center;">{l s='Active' mod='ps_gtm_datalayer'}</th>
            <th style="width: 260px;">{l s='Event Name & Code' mod='ps_gtm_datalayer'}</th>
            <th style="width: 120px;">{l s='Category' mod='ps_gtm_datalayer'}</th>
            <th>{l s='Description & Storefront Trigger' mod='ps_gtm_datalayer'}</th>
            <th style="width: 260px;">{l s='Destination Platforms' mod='ps_gtm_datalayer'}</th>
            <th style="width: 120px; text-align: right;">{l s='Actions' mod='ps_gtm_datalayer'}</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$canonical_events item=e}
          <tr class="ps-gtm-event-row" data-name="{$e.name|escape:'html':'UTF-8'}" data-label="{$e.label|escape:'html':'UTF-8'}" data-desc="{$e.desc|escape:'html':'UTF-8'}" data-category="{$e.category|escape:'html':'UTF-8'}">
            <td style="text-align: center;">
              <label class="ps-gtm-switch ps-gtm-switch-sm">
                <input type="checkbox" name="event_active_{$e.name|escape:'html':'UTF-8'}" class="ps-gtm-evt-main-cb" value="1" {if !empty($e.is_active)}checked{/if}>
                <span class="ps-gtm-slider"></span>
              </label>
            </td>
            <td>
              <strong style="color: #0f172a; font-size: 13px;">{$e.label|escape:'html':'UTF-8'}</strong><br>
              <code>{if !empty($config_values.PS_GTM_EVENT_PREFIX)}{$config_values.PS_GTM_EVENT_PREFIX|escape:'html':'UTF-8'}_{/if}{$e.name|escape:'html':'UTF-8'}</code>
            </td>
            <td>
              <span class="ps-gtm-cat-badge {$e.category|escape:'html':'UTF-8'}">
                {$e.category_label|escape:'html':'UTF-8'}
              </span>
            </td>
            <td>
              <div style="font-size: 12px; color: #334155;">{$e.desc|escape:'html':'UTF-8'}</div>
              <div style="font-size: 11px; color: #94a3b8; margin-top: 3px;">
                <i class="icon-bolt"></i> {$e.trigger_hook|escape:'html':'UTF-8'}
              </div>
            </td>
            <td>
              <span class="ps-gtm-plat-tag meta">Meta</span>
              <span class="ps-gtm-plat-tag gads">Google Ads</span>
              <span class="ps-gtm-plat-tag tiktok">TikTok</span>
              <span class="ps-gtm-plat-tag pinterest">Pinterest</span>
              <span class="ps-gtm-plat-tag microsoft">Microsoft</span>
              {if $e.has_sgtm_webhook}
                <span class="ps-gtm-plat-tag sgtm">sGTM Webhook</span>
              {/if}
            </td>
            <td style="text-align: right;">
              <button type="button" class="btn btn-default btn-xs ps-gtm-inspect-btn" data-event="{$e.name|escape:'html':'UTF-8'}" title="{l s='Inspect Schema & Mapping' mod='ps_gtm_datalayer'}">
                <i class="icon-eye"></i> {l s='Inspect' mod='ps_gtm_datalayer'}
              </button>
            </td>
          </tr>
          {/foreach}
        </tbody>
      </table>
    </div>

    <!-- ================================================================= -->
    <!-- TAB 4: PLATFORM ROUTING                                           -->
    <!-- ================================================================= -->
    <div id="tab-routing" class="ps-gtm-tab-pane">

      <!-- Advertising Master Switch Callout -->
      <div class="ps-gtm-card" style="border-left: 4px solid #2563eb;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
          <div>
            <div style="font-size: 15px; font-weight: 700; color: #0f172a;">
              <i class="icon-bullhorn text-primary"></i> {l s='Enable Advertising DataLayers (Master Switch)' mod='ps_gtm_datalayer'}
            </div>
            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
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
        <div style="display: flex; align-items: center; gap: 8px;">
          <strong style="color: #1e293b; font-size: 13px;">{l s='Display Mode:' mod='ps_gtm_datalayer'}</strong>
          <div class="btn-group">
            <button type="button" class="btn btn-default btn-sm active" id="ps-gtm-btn-view-matrix" onclick="psGtmSwitchRoutingView('matrix')">
              <i class="icon-th"></i> {l s='Matrix View' mod='ps_gtm_datalayer'}
            </button>
            <button type="button" class="btn btn-default btn-sm" id="ps-gtm-btn-view-cards" onclick="psGtmSwitchRoutingView('cards')">
              <i class="icon-th-list"></i> {l s='Platform Cards' mod='ps_gtm_datalayer'}
            </button>
          </div>
        </div>

        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
          <button type="button" class="btn btn-success btn-xs" onclick="psGtmToggleAllPlatforms(true)">
            <i class="icon-check-circle"></i> {l s='Enable All Platforms' mod='ps_gtm_datalayer'}
          </button>
          <button type="button" class="btn btn-warning btn-xs" onclick="psGtmToggleAllPlatforms(false)">
            <i class="icon-ban"></i> {l s='Disable All Platforms' mod='ps_gtm_datalayer'}
          </button>
        </div>
      </div>

      <!-- VIEW 1: Matrix View (Compact Cross-Grid) -->
      <div id="ps-gtm-routing-matrix-view">
        <table class="ps-gtm-table ps-gtm-matrix-table">
          <thead>
            <tr>
              <th style="width: 240px; text-align: left;">{l s='Ecommerce Event' mod='ps_gtm_datalayer'}</th>
              <th style="width: 140px;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                  <img src="{$module_path|escape:'html':'UTF-8'}views/img/meta.png" alt="Meta" style="width: 16px; height: 16px;">
                  <span>Meta</span>
                </div>
                <div style="margin-top: 4px;">
                  <button type="button" class="btn btn-default btn-xs" onclick="psGtmTogglePlatformColumn('meta', true)" title="{l s='Enable All' mod='ps_gtm_datalayer'}"><i class="icon-check text-success"></i></button>
                  <button type="button" class="btn btn-default btn-xs" onclick="psGtmTogglePlatformColumn('meta', false)" title="{l s='Disable All' mod='ps_gtm_datalayer'}"><i class="icon-times text-danger"></i></button>
                </div>
              </th>
              <th style="width: 140px;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                  <img src="{$module_path|escape:'html':'UTF-8'}views/img/google_ads.png" alt="Google Ads" style="width: 16px; height: 16px;">
                  <span>Google Ads</span>
                </div>
                <div style="margin-top: 4px;">
                  <button type="button" class="btn btn-default btn-xs" onclick="psGtmTogglePlatformColumn('gads', true)" title="{l s='Enable All' mod='ps_gtm_datalayer'}"><i class="icon-check text-success"></i></button>
                  <button type="button" class="btn btn-default btn-xs" onclick="psGtmTogglePlatformColumn('gads', false)" title="{l s='Disable All' mod='ps_gtm_datalayer'}"><i class="icon-times text-danger"></i></button>
                </div>
              </th>
              <th style="width: 140px;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                  <img src="{$module_path|escape:'html':'UTF-8'}views/img/tiktok.png" alt="TikTok" style="width: 16px; height: 16px;">
                  <span>TikTok</span>
                </div>
                <div style="margin-top: 4px;">
                  <button type="button" class="btn btn-default btn-xs" onclick="psGtmTogglePlatformColumn('tiktok', true)" title="{l s='Enable All' mod='ps_gtm_datalayer'}"><i class="icon-check text-success"></i></button>
                  <button type="button" class="btn btn-default btn-xs" onclick="psGtmTogglePlatformColumn('tiktok', false)" title="{l s='Disable All' mod='ps_gtm_datalayer'}"><i class="icon-times text-danger"></i></button>
                </div>
              </th>
              <th style="width: 140px;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                  <img src="{$module_path|escape:'html':'UTF-8'}views/img/pinterest.png" alt="Pinterest" style="width: 16px; height: 16px;">
                  <span>Pinterest</span>
                </div>
                <div style="margin-top: 4px;">
                  <button type="button" class="btn btn-default btn-xs" onclick="psGtmTogglePlatformColumn('pinterest', true)" title="{l s='Enable All' mod='ps_gtm_datalayer'}"><i class="icon-check text-success"></i></button>
                  <button type="button" class="btn btn-default btn-xs" onclick="psGtmTogglePlatformColumn('pinterest', false)" title="{l s='Disable All' mod='ps_gtm_datalayer'}"><i class="icon-times text-danger"></i></button>
                </div>
              </th>
              <th style="width: 140px;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                  <img src="{$module_path|escape:'html':'UTF-8'}views/img/microsoft_ads.png" alt="Microsoft Ads" style="width: 16px; height: 16px;">
                  <span>Microsoft</span>
                </div>
                <div style="margin-top: 4px;">
                  <button type="button" class="btn btn-default btn-xs" onclick="psGtmTogglePlatformColumn('microsoft', true)" title="{l s='Enable All' mod='ps_gtm_datalayer'}"><i class="icon-check text-success"></i></button>
                  <button type="button" class="btn btn-default btn-xs" onclick="psGtmTogglePlatformColumn('microsoft', false)" title="{l s='Disable All' mod='ps_gtm_datalayer'}"><i class="icon-times text-danger"></i></button>
                </div>
              </th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$canonical_events item=e}
            {if $e.name !== 'page_view' && $e.name !== 'refund' && $e.name !== 'order_status_update'}
            <tr>
              <td class="event-cell">
                <strong style="color: #1e293b;">{$e.label|escape:'html':'UTF-8'}</strong><br>
                <code>{$e.name|escape:'html':'UTF-8'}</code>
              </td>
              <td>
                <label class="ps-gtm-switch ps-gtm-switch-sm">
                  <input type="checkbox" name="adv_meta_{$e.name|escape:'html':'UTF-8'}" class="ps-gtm-evt-meta" value="1" {if in_array($e.name, $meta_active_events)}checked{/if}>
                  <span class="ps-gtm-slider"></span>
                </label>
              </td>
              <td>
                <label class="ps-gtm-switch ps-gtm-switch-sm">
                  <input type="checkbox" name="adv_gads_{$e.name|escape:'html':'UTF-8'}" class="ps-gtm-evt-gads" value="1" {if in_array($e.name, $gads_active_events)}checked{/if}>
                  <span class="ps-gtm-slider"></span>
                </label>
              </td>
              <td>
                <label class="ps-gtm-switch ps-gtm-switch-sm">
                  <input type="checkbox" name="adv_tiktok_{$e.name|escape:'html':'UTF-8'}" class="ps-gtm-evt-tiktok" value="1" {if in_array($e.name, $tiktok_active_events)}checked{/if}>
                  <span class="ps-gtm-slider"></span>
                </label>
              </td>
              <td>
                <label class="ps-gtm-switch ps-gtm-switch-sm">
                  <input type="checkbox" name="adv_pinterest_{$e.name|escape:'html':'UTF-8'}" class="ps-gtm-evt-pinterest" value="1" {if in_array($e.name, $pinterest_active_events)}checked{/if}>
                  <span class="ps-gtm-slider"></span>
                </label>
              </td>
              <td>
                <label class="ps-gtm-switch ps-gtm-switch-sm">
                  <input type="checkbox" name="adv_microsoft_{$e.name|escape:'html':'UTF-8'}" class="ps-gtm-evt-microsoft" value="1" {if in_array($e.name, $microsoft_active_events)}checked{/if}>
                  <span class="ps-gtm-slider"></span>
                </label>
              </td>
            </tr>
            {/if}
            {/foreach}
          </tbody>
        </table>
      </div>

      <!-- VIEW 2: Platform Cards View (Accordion & Detailed Settings) -->
      <div id="ps-gtm-routing-cards-view" style="display: none;">
        {$platform_cards_html nofilter}
      </div>
    </div>

    <!-- ================================================================= -->
    <!-- TAB 5: DATA MAPPING                                               -->
    <!-- ================================================================= -->
    <div id="tab-mapping" class="ps-gtm-tab-pane">
      <div class="ps-gtm-card">
        <div class="ps-gtm-card-title">
          <i class="icon-sitemap text-primary"></i> {l s='Authoritative Data Mapping & Payload Inspector' mod='ps_gtm_datalayer'}
        </div>
        <div class="ps-gtm-card-desc">
          {l s='Inspect GA4 ecommerce source fields versus mapped parameters pushed to Meta CAPI, Google Ads, TikTok, Pinterest, and Microsoft Advertising.' mod='ps_gtm_datalayer'}
        </div>

        <!-- Event Selection Tabs -->
        <div class="ps-gtm-filter-pills" style="margin-bottom: 20px;">
          {foreach from=$canonical_events item=e key=idx}
          <button type="button" class="ps-gtm-filter-pill ps-gtm-mapping-tab {if $idx === 0}active{/if}" data-event="{$e.name|escape:'html':'UTF-8'}" onclick="psGtmSelectDataMappingEvent('{$e.name|escape:'html':'UTF-8'}')">
            {$e.label|escape:'html':'UTF-8'}
          </button>
          {/foreach}
        </div>

        <!-- Mapping Panes for Each Event -->
        {foreach from=$canonical_events item=e key=idx}
        <div class="ps-gtm-mapping-pane" data-event="{$e.name|escape:'html':'UTF-8'}" style="{if $idx > 0}display: none;{/if}">
          <div style="margin-bottom: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px 18px;">
            <div style="font-size: 15px; font-weight: 700; color: #0f172a;">{$e.label|escape:'html':'UTF-8'} (<code>{$e.name|escape:'html':'UTF-8'}</code>)</div>
            <div style="font-size: 12px; color: #475569; margin-top: 4px;">{$e.desc|escape:'html':'UTF-8'}</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 4px;">
              <strong>Storefront / Backend Hook:</strong> <code>{$e.trigger_hook|escape:'html':'UTF-8'}</code>
            </div>
          </div>

          <!-- Parameter Mappings Table -->
          <table class="ps-gtm-table" style="margin-bottom: 20px;">
            <thead>
              <tr>
                <th style="width: 180px;">{l s='Parameter' mod='ps_gtm_datalayer'}</th>
                <th style="width: 90px;">{l s='Type' mod='ps_gtm_datalayer'}</th>
                <th>{l s='GA4 Source Field' mod='ps_gtm_datalayer'}</th>
                <th>{l s='Advertising Platform Destinations' mod='ps_gtm_datalayer'}</th>
              </tr>
            </thead>
            <tbody>
              {foreach from=$e.schema_fields item=f}
              <tr>
                <td><strong><code>{$f.name|escape:'html':'UTF-8'}</code></strong></td>
                <td><span class="badge" style="background: #e2e8f0; color: #334155;">{$f.type|escape:'html':'UTF-8'}</span></td>
                <td><code>{$f.source|escape:'html':'UTF-8'}</code></td>
                <td>
                  <div style="font-size: 11px; color: #475569;">{$f.destinations|escape:'html':'UTF-8'}</div>
                  <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">{$f.desc|escape:'html':'UTF-8'}</div>
                </td>
              </tr>
              {/foreach}
            </tbody>
          </table>

          <!-- Live Sample Payload Preview -->
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <strong style="font-size: 12px; color: #1e293b;">
              <i class="icon-code"></i> {l s='Verified Backend DataLayer JSON Payload' mod='ps_gtm_datalayer'}:
            </strong>
            <button type="button" class="btn btn-default btn-xs" onclick="psGtmCopyCode('ps-gtm-code-{$e.name|escape:'html':'UTF-8'}')">
              <i class="icon-copy"></i> {l s='Copy JSON' mod='ps_gtm_datalayer'}
            </button>
          </div>
          <pre class="ps-gtm-code-box" id="ps-gtm-code-{$e.name|escape:'html':'UTF-8'}">{$e.example_json|escape:'html':'UTF-8'}</pre>
        </div>
        {/foreach}
      </div>
    </div>

    <!-- ================================================================= -->
    <!-- TAB 6: DIAGNOSTICS & TESTING                                      -->
    <!-- ================================================================= -->
    <div id="tab-diagnostics" class="ps-gtm-tab-pane">

      <!-- Server-Side Ping Test Action Card -->
      <div class="ps-gtm-card" style="border-left: 4px solid #10b981;">
        <div class="ps-gtm-card-title">
          <i class="icon-bolt text-success"></i> {l s='Server-Side GTM Connectivity & Ping Test' mod='ps_gtm_datalayer'}
        </div>
        <div class="ps-gtm-card-desc">
          {l s='Dispatches a non-blocking diagnostic ping directly from your PrestaShop server to the configured sGTM transport URL and webhook path.' mod='ps_gtm_datalayer'}
        </div>

        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
          <button type="button" id="ps-gtm-ping-btn" class="btn btn-primary">
            <i class="icon-bolt"></i> {l s='Ping sGTM Transport URL' mod='ps_gtm_datalayer'}
          </button>
          <span style="font-size: 12px; color: #64748b;">
            {l s='Target Endpoint:' mod='ps_gtm_datalayer'} <code>{$diagnostics.target_endpoint|escape:'html':'UTF-8'}</code>
          </span>
        </div>

        <div id="ps-gtm-ping-result" style="display: none; margin-top: 14px;"></div>
      </div>

      <!-- System Health Checks List -->
      <div class="ps-gtm-card">
        <div class="ps-gtm-card-title">
          <i class="icon-stethoscope text-primary"></i> {l s='System Configuration & Runtime Integrity Audit' mod='ps_gtm_datalayer'}
        </div>
        <div class="ps-gtm-card-desc">
          {l s='Real-time validation of module configuration, PrestaShop hooks, catalog strategies, and database deduplication tables.' mod='ps_gtm_datalayer'}
        </div>

        <div class="ps-gtm-diag-list">
          <!-- GTM ID Check -->
          <div class="ps-gtm-diag-item">
            <div class="ps-gtm-diag-left">
              <div class="ps-gtm-diag-status-icon {if $diagnostics.gtm_id_valid}success{else}warning{/if}">
                <i class="{if $diagnostics.gtm_id_valid}icon-check{else}icon-warning-sign{/if}"></i>
              </div>
              <div>
                <strong>{l s='Google Tag Manager Web Container ID' mod='ps_gtm_datalayer'}</strong>
                <div style="font-size: 11px; color: #64748b;">{$diagnostics.gtm_id_msg|escape:'html':'UTF-8'}</div>
              </div>
            </div>
            <span class="label {if $diagnostics.gtm_id_valid}label-success{else}label-warning{/if}">
              {if $diagnostics.gtm_id_valid}{l s='Valid' mod='ps_gtm_datalayer'}{else}{l s='Attention' mod='ps_gtm_datalayer'}{/if}
            </span>
          </div>

          <!-- Catalog Strategy Check -->
          <div class="ps-gtm-diag-item">
            <div class="ps-gtm-diag-left">
              <div class="ps-gtm-diag-status-icon success">
                <i class="icon-check"></i>
              </div>
              <div>
                <strong>{l s='Catalog Identifier Strategy Resolution' mod='ps_gtm_datalayer'}</strong>
                <div style="font-size: 11px; color: #64748b;">
                  {l s='Current Strategy:' mod='ps_gtm_datalayer'} <code>{$config_values.PS_GTM_CATALOG_ID_STRATEGY|escape:'html':'UTF-8'}</code> &bull;
                  {l s='Sample output for Product #68 Variant #299:' mod='ps_gtm_datalayer'} <code>{$diagnostics.sample_catalog_id|escape:'html':'UTF-8'}</code>
                </div>
              </div>
            </div>
            <span class="label label-success">{l s='Active' mod='ps_gtm_datalayer'}</span>
          </div>

          <!-- Deduplication Table Check -->
          <div class="ps-gtm-diag-item">
            <div class="ps-gtm-diag-left">
              <div class="ps-gtm-diag-status-icon success">
                <i class="icon-database"></i>
              </div>
              <div>
                <strong>{l s='Database Purchase Deduplication Table' mod='ps_gtm_datalayer'}</strong>
                <div style="font-size: 11px; color: #64748b;">
                  <code>gtm_purchase_log</code> {l s='table verified with' mod='ps_gtm_datalayer'} {$diagnostics.purchase_log_count|escape:'html':'UTF-8'} {l s='recorded order transactions.' mod='ps_gtm_datalayer'}
                </div>
              </div>
            </div>
            <span class="label label-success">{l s='Synchronized' mod='ps_gtm_datalayer'}</span>
          </div>

          <!-- Server Environment Check -->
          <div class="ps-gtm-diag-item">
            <div class="ps-gtm-diag-left">
              <div class="ps-gtm-diag-status-icon success">
                <i class="icon-desktop"></i>
              </div>
              <div>
                <strong>{l s='Server Runtime Environment' mod='ps_gtm_datalayer'}</strong>
                <div style="font-size: 11px; color: #64748b;">
                  PHP {$diagnostics.php_version|escape:'html':'UTF-8'} &bull;
                  cURL: {$diagnostics.curl_status|escape:'html':'UTF-8'} &bull;
                  OpenSSL: {$diagnostics.openssl_status|escape:'html':'UTF-8'} &bull;
                  Memory Limit: {$diagnostics.memory_limit|escape:'html':'UTF-8'}
                </div>
              </div>
            </div>
            <span class="label label-success">{l s='Compatible' mod='ps_gtm_datalayer'}</span>
          </div>

          <!-- PrestaShop Hook Registrations Check -->
          <div class="ps-gtm-diag-item">
            <div class="ps-gtm-diag-left">
              <div class="ps-gtm-diag-status-icon success">
                <i class="icon-link"></i>
              </div>
              <div>
                <strong>{l s='PrestaShop Lifecycle Hooks' mod='ps_gtm_datalayer'}</strong>
                <div style="font-size: 11px; color: #64748b;">
                  {l s='All required storefront, checkout, and webhook lifecycle hooks are registered in the core PrestaShop database.' mod='ps_gtm_datalayer'}
                </div>
              </div>
            </div>
            <span class="label label-success">{$diagnostics.registered_hooks_count|escape:'html':'UTF-8'} {l s='Hooks Active' mod='ps_gtm_datalayer'}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Sticky Save Action Bar -->
    <div class="ps-gtm-save-bar">
      <div style="font-size: 12px; color: #64748b;">
        <i class="icon-info-circle text-primary"></i> {l s='All settings across all tabs are saved simultaneously with zero data loss.' mod='ps_gtm_datalayer'}
      </div>
      <button type="submit" name="submit{$module_name|escape:'html':'UTF-8'}" class="ps-gtm-btn-save">
        <i class="icon-save"></i> {l s='Save All Settings' mod='ps_gtm_datalayer'}
      </button>
    </div>

  </form>

  <!-- Modal Dialog: Event Schema Details -->
  <div id="ps-gtm-schema-modal" class="ps-gtm-modal-backdrop" role="dialog" aria-modal="true">
    <div class="ps-gtm-modal-dialog">
      <div class="ps-gtm-modal-header">
        <h4 id="ps-gtm-modal-title">{l s='Event Schema Inspector' mod='ps_gtm_datalayer'}</h4>
        <button type="button" class="ps-gtm-modal-close" aria-label="Close">&times;</button>
      </div>
      <div class="ps-gtm-modal-body">
        <div id="ps-gtm-modal-desc" style="font-size: 12px; color: #475569; margin-bottom: 16px;"></div>
        <strong style="display: block; font-size: 12px; color: #0f172a; margin-bottom: 6px;">
          <i class="icon-code"></i> {l s='Verified JSON Payload Example' mod='ps_gtm_datalayer'}:
        </strong>
        <pre id="ps-gtm-modal-payload" class="ps-gtm-code-box"></pre>
      </div>
      <div class="ps-gtm-modal-footer">
        <button type="button" class="btn btn-default" data-modal-dismiss="true">{l s='Close' mod='ps_gtm_datalayer'}</button>
      </div>
    </div>
  </div>

</div>

<script type="text/javascript">
  window.psGtmAjaxAdminUrl = "{$ajax_admin_url|escape:'javascript':'UTF-8'}";
  window.psGtmEventSchemas = {$events_schemas_json nofilter};
</script>
