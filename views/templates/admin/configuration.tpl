{*
 * PrestaShop DataLayer - Unified Administration Interface
 * Professional 6-Tab Architecture: Overview, Configuration, Events, Platform Routing, Data Mapping, Diagnostics
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
          <div style="border: 1px solid #cbd5e1; border-radius: 8px; padding: 16px; background: #fafbfc; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 14px;">
              <img src="{$module_path|escape:'html':'UTF-8'}views/img/meta.png" alt="Meta" style="width: 32px; height: 32px; object-fit: contain;">
              <div>
                <strong style="font-size: 15.5px; color: #0f172a;">Meta Pixel & CAPI</strong>
                <div style="font-size: 13.5px; color: #475569; margin-top: 2px;">{$meta_events_count|escape:'html':'UTF-8'} / {$events_total|escape:'html':'UTF-8'} {l s='events routed' mod='ps_gtm_datalayer'}</div>
              </div>
            </div>
            <span class="label {if $config_values.PS_GTM_ADV_META_ENABLED && $config_values.PS_GTM_ADV_MASTER_ENABLED}label-success{else}label-default{/if}" style="font-size: 13px; font-weight: 600; padding: 5px 12px;">
              {if $config_values.PS_GTM_ADV_META_ENABLED && $config_values.PS_GTM_ADV_MASTER_ENABLED}{l s='Active' mod='ps_gtm_datalayer'}{else}{l s='Off' mod='ps_gtm_datalayer'}{/if}
            </span>
          </div>

          <!-- Google Ads -->
          <div style="border: 1px solid #cbd5e1; border-radius: 8px; padding: 16px; background: #fafbfc; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 14px;">
              <img src="{$module_path|escape:'html':'UTF-8'}views/img/google_ads.png" alt="Google Ads" style="width: 32px; height: 32px; object-fit: contain;">
              <div>
                <strong style="font-size: 15.5px; color: #0f172a;">Google Ads Remarketing</strong>
                <div style="font-size: 13.5px; color: #475569; margin-top: 2px;">{$gads_events_count|escape:'html':'UTF-8'} / {$events_total|escape:'html':'UTF-8'} {l s='events routed' mod='ps_gtm_datalayer'}</div>
              </div>
            </div>
            <span class="label {if $config_values.PS_GTM_ADV_GADS_ENABLED && $config_values.PS_GTM_ADV_MASTER_ENABLED}label-success{else}label-default{/if}" style="font-size: 13px; font-weight: 600; padding: 5px 12px;">
              {if $config_values.PS_GTM_ADV_GADS_ENABLED && $config_values.PS_GTM_ADV_MASTER_ENABLED}{l s='Active' mod='ps_gtm_datalayer'}{else}{l s='Off' mod='ps_gtm_datalayer'}{/if}
            </span>
          </div>

          <!-- TikTok -->
          <div style="border: 1px solid #cbd5e1; border-radius: 8px; padding: 16px; background: #fafbfc; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 14px;">
              <img src="{$module_path|escape:'html':'UTF-8'}views/img/tiktok.png" alt="TikTok" style="width: 32px; height: 32px; object-fit: contain;">
              <div>
                <strong style="font-size: 15.5px; color: #0f172a;">TikTok Events API</strong>
                <div style="font-size: 13.5px; color: #475569; margin-top: 2px;">{$tiktok_events_count|escape:'html':'UTF-8'} / {$events_total|escape:'html':'UTF-8'} {l s='events routed' mod='ps_gtm_datalayer'}</div>
              </div>
            </div>
            <span class="label {if $config_values.PS_GTM_ADV_TIKTOK_ENABLED && $config_values.PS_GTM_ADV_MASTER_ENABLED}label-success{else}label-default{/if}" style="font-size: 13px; font-weight: 600; padding: 5px 12px;">
              {if $config_values.PS_GTM_ADV_TIKTOK_ENABLED && $config_values.PS_GTM_ADV_MASTER_ENABLED}{l s='Active' mod='ps_gtm_datalayer'}{else}{l s='Off' mod='ps_gtm_datalayer'}{/if}
            </span>
          </div>

          <!-- Pinterest -->
          <div style="border: 1px solid #cbd5e1; border-radius: 8px; padding: 16px; background: #fafbfc; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 14px;">
              <img src="{$module_path|escape:'html':'UTF-8'}views/img/pinterest.png" alt="Pinterest" style="width: 32px; height: 32px; object-fit: contain;">
              <div>
                <strong style="font-size: 15.5px; color: #0f172a;">Pinterest Tag & CAPI</strong>
                <div style="font-size: 13.5px; color: #475569; margin-top: 2px;">{$pinterest_events_count|escape:'html':'UTF-8'} / {$events_total|escape:'html':'UTF-8'} {l s='events routed' mod='ps_gtm_datalayer'}</div>
              </div>
            </div>
            <span class="label {if $config_values.PS_GTM_ADV_PINTEREST_ENABLED && $config_values.PS_GTM_ADV_MASTER_ENABLED}label-success{else}label-default{/if}" style="font-size: 13px; font-weight: 600; padding: 5px 12px;">
              {if $config_values.PS_GTM_ADV_PINTEREST_ENABLED && $config_values.PS_GTM_ADV_MASTER_ENABLED}{l s='Active' mod='ps_gtm_datalayer'}{else}{l s='Off' mod='ps_gtm_datalayer'}{/if}
            </span>
          </div>

          <!-- Microsoft -->
          <div style="border: 1px solid #cbd5e1; border-radius: 8px; padding: 16px; background: #fafbfc; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 14px;">
              <img src="{$module_path|escape:'html':'UTF-8'}views/img/microsoft_ads.png" alt="Microsoft Ads" style="width: 32px; height: 32px; object-fit: contain;">
              <div>
                <strong style="font-size: 15.5px; color: #0f172a;">Microsoft Advertising (UET)</strong>
                <div style="font-size: 13.5px; color: #475569; margin-top: 2px;">{$microsoft_events_count|escape:'html':'UTF-8'} / {$events_total|escape:'html':'UTF-8'} {l s='events routed' mod='ps_gtm_datalayer'}</div>
              </div>
            </div>
            <span class="label {if $config_values.PS_GTM_ADV_MICROSOFT_ENABLED && $config_values.PS_GTM_ADV_MASTER_ENABLED}label-success{else}label-default{/if}" style="font-size: 13px; font-weight: 600; padding: 5px 12px;">
              {if $config_values.PS_GTM_ADV_MICROSOFT_ENABLED && $config_values.PS_GTM_ADV_MASTER_ENABLED}{l s='Active' mod='ps_gtm_datalayer'}{else}{l s='Off' mod='ps_gtm_datalayer'}{/if}
            </span>
          </div>

          <!-- sGTM Webhooks -->
          <div style="border: 1px solid #cbd5e1; border-radius: 8px; padding: 16px; background: #fafbfc; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 14px;">
              <div style="width: 32px; height: 32px; border-radius: 6px; background: #ede9fe; color: #7c3aed; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                <i class="icon-exchange"></i>
              </div>
              <div>
                <strong style="font-size: 15.5px; color: #0f172a;">Server-to-Server Webhooks</strong>
                <div style="font-size: 13.5px; color: #475569; margin-top: 2px;">{l s='Refunds, Offline Orders, Order Status Updates' mod='ps_gtm_datalayer'}</div>
              </div>
            </div>
            <span class="label {if !empty($config_values.PS_SGTM_TRANSPORT_URL)}label-success{else}label-default{/if}" style="font-size: 13px; font-weight: 600; padding: 5px 12px;">
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
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px;">
          <button type="button" class="btn btn-default" onclick="psGtmSwitchTab('tab-config')" style="padding: 14px; text-align: left; border: 1.5px solid #cbd5e1;">
            <i class="icon-cogs" style="font-size: 20px; color: #2563eb;"></i><br>
            <strong style="font-size: 15px; color: #0f172a; margin-top: 6px; display: inline-block;">{l s='GTM & Server Config' mod='ps_gtm_datalayer'}</strong>
            <div style="font-size: 13.5px; color: #475569; margin-top: 3px;">{l s='Container ID & endpoints' mod='ps_gtm_datalayer'}</div>
          </button>
          <button type="button" class="btn btn-default" onclick="psGtmSwitchTab('tab-events')" style="padding: 14px; text-align: left; border: 1.5px solid #cbd5e1;">
            <i class="icon-tags" style="font-size: 20px; color: #16a34a;"></i><br>
            <strong style="font-size: 15px; color: #0f172a; margin-top: 6px; display: inline-block;">{l s='Manage Events' mod='ps_gtm_datalayer'}</strong>
            <div style="font-size: 13.5px; color: #475569; margin-top: 3px;">{l s='Search & inspect events' mod='ps_gtm_datalayer'}</div>
          </button>
          <button type="button" class="btn btn-default" onclick="psGtmSwitchTab('tab-routing')" style="padding: 14px; text-align: left; border: 1.5px solid #cbd5e1;">
            <i class="icon-share-alt" style="font-size: 20px; color: #9333ea;"></i><br>
            <strong style="font-size: 15px; color: #0f172a; margin-top: 6px; display: inline-block;">{l s='Platform Matrix' mod='ps_gtm_datalayer'}</strong>
            <div style="font-size: 13.5px; color: #475569; margin-top: 3px;">{l s='Route events to ad channels' mod='ps_gtm_datalayer'}</div>
          </button>
          <button type="button" class="btn btn-default" onclick="psGtmSwitchTab('tab-mapping')" style="padding: 14px; text-align: left; border: 1.5px solid #cbd5e1;">
            <i class="icon-sitemap" style="font-size: 20px; color: #0284c7;"></i><br>
            <strong style="font-size: 15px; color: #0f172a; margin-top: 6px; display: inline-block;">{l s='Data Mapping' mod='ps_gtm_datalayer'}</strong>
            <div style="font-size: 13.5px; color: #475569; margin-top: 3px;">{l s='Inspect schemas & payloads' mod='ps_gtm_datalayer'}</div>
          </button>
          <button type="button" class="btn btn-default" onclick="psGtmSwitchTab('tab-diagnostics')" style="padding: 14px; text-align: left; border: 1.5px solid #cbd5e1;">
            <i class="icon-stethoscope" style="font-size: 20px; color: #dc2626;"></i><br>
            <strong style="font-size: 15px; color: #0f172a; margin-top: 6px; display: inline-block;">{l s='Health Diagnostics' mod='ps_gtm_datalayer'}</strong>
            <div style="font-size: 13.5px; color: #475569; margin-top: 3px;">{l s='Run connectivity & audit tests' mod='ps_gtm_datalayer'}</div>
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
        <div style="display: flex; flex-direction: column; gap: 16px;">
          <!-- Refund Webhook -->
          <div style="display: flex; align-items: center; justify-content: space-between; max-width: 760px;">
            <div>
              <strong style="font-size: 15.5px; color: #0f172a;">{l s='Server-to-Server Refund Webhooks (sGTM)' mod='ps_gtm_datalayer'}</strong>
              <div style="font-size: 14px; color: #475569; margin-top: 3px; line-height: 1.5;">
                {l s='Dispatches asynchronous refund events to sGTM upon Credit Slip generation in the Back Office.' mod='ps_gtm_datalayer'}
              </div>
            </div>
            <label class="ps-gtm-switch">
              <input type="checkbox" name="PS_GTM_WEBHOOK_REFUND_ENABLED" value="1" {if $config_values.PS_GTM_WEBHOOK_REFUND_ENABLED}checked{/if}>
              <span class="ps-gtm-slider"></span>
            </label>
          </div>

          <!-- Offline Orders Webhook -->
          <div style="display: flex; align-items: center; justify-content: space-between; max-width: 760px;">
            <div>
              <strong style="font-size: 15.5px; color: #0f172a;">{l s='Server-to-Server Offline Order Webhooks (sGTM)' mod='ps_gtm_datalayer'}</strong>
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
          <div style="display: flex; align-items: center; justify-content: space-between; max-width: 760px;">
            <div>
              <strong style="font-size: 15.5px; color: #0f172a;">{l s='Server-to-Server Order Status Change Webhooks (sGTM)' mod='ps_gtm_datalayer'}</strong>
              <div style="font-size: 14px; color: #475569; margin-top: 3px; line-height: 1.5;">
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
          <textarea name="PS_GTM_RAW_HEAD_CODE" id="PS_GTM_RAW_HEAD_CODE" rows="4" class="ps-gtm-form-control" style="font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 13.5px; line-height: 1.5; padding: 12px 14px;">{$config_values.PS_GTM_RAW_HEAD_CODE|escape:'html':'UTF-8'}</textarea>
          <div class="ps-gtm-help-block">
            {l s='Custom script tag injected before closing </head>. If GTM Container ID is set above, standard container script is generated automatically.' mod='ps_gtm_datalayer'}
          </div>
        </div>

        <div class="ps-gtm-form-group">
          <label class="ps-gtm-form-label" for="PS_GTM_RAW_BODY_CODE">
            {l s='Raw GTM <body> Code' mod='ps_gtm_datalayer'}
          </label>
          <textarea name="PS_GTM_RAW_BODY_CODE" id="PS_GTM_RAW_BODY_CODE" rows="3" class="ps-gtm-form-control" style="font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 13.5px; line-height: 1.5; padding: 12px 14px;">{$config_values.PS_GTM_RAW_BODY_CODE|escape:'html':'UTF-8'}</textarea>
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
      <input type="hidden" name="PS_GTM_EVENTS_SUBMITTED" value="1">

      <!-- Events Tab Header -->
      <div class="ps-gtm-tab-header-row" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; flex-wrap: wrap; gap: 14px;">
        <div>
          <h3 style="margin: 0; font-size: 24px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 10px;">
            <i class="icon-list text-primary"></i> {l s='Events' mod='ps_gtm_datalayer'}
          </h3>
          <div style="font-size: 14.5px; color: #64748b; margin-top: 4px;">
            {l s='Manage tracking events and destinations' mod='ps_gtm_datalayer'}
          </div>
        </div>
        <div>
          <span class="ps-gtm-counter-pill" style="font-size: 14.5px; font-weight: 700; background: #e0f2fe; color: #0369a1; padding: 6px 16px; border-radius: 20px; border: 1.5px solid #bae6fd; display: inline-block;">
            {$events_total|escape:'html':'UTF-8'} {l s='events' mod='ps_gtm_datalayer'}
          </span>
        </div>
      </div>

      <!-- Event Search & Filters Bar -->
      <div class="ps-gtm-filters-bar" style="margin-bottom: 22px;">
        <div class="ps-gtm-search-box" style="flex: 1; min-width: 240px;">
          <i class="icon-search"></i>
          <input type="text" id="ps-gtm-event-search" class="ps-gtm-search-input" placeholder="{l s='Search events...' mod='ps_gtm_datalayer'}">
        </div>

        <div class="ps-gtm-filter-pills">
          <button type="button" class="ps-gtm-filter-pill active" data-category="all">{l s='All events' mod='ps_gtm_datalayer'} {$events_total|escape:'html':'UTF-8'}</button>
          <button type="button" class="ps-gtm-filter-pill" data-category="discovery">{l s='Discovery' mod='ps_gtm_datalayer'}</button>
          <button type="button" class="ps-gtm-filter-pill" data-category="interaction">{l s='Interaction' mod='ps_gtm_datalayer'}</button>
          <button type="button" class="ps-gtm-filter-pill" data-category="cart">{l s='Cart' mod='ps_gtm_datalayer'}</button>
          <button type="button" class="ps-gtm-filter-pill" data-category="checkout">{l s='Checkout' mod='ps_gtm_datalayer'}</button>
          <button type="button" class="ps-gtm-filter-pill" data-category="purchase">{l s='Orders & Returns' mod='ps_gtm_datalayer'}</button>
        </div>

        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
          <select id="ps-gtm-status-filter" class="form-control" style="width: 145px; font-size: 14px; height: 38px; padding: 6px 12px; color: #0f172a; border: 1.5px solid #cbd5e1; border-radius: 6px; display: inline-block;">
            <option value="all">{l s='All Statuses' mod='ps_gtm_datalayer'}</option>
            <option value="enabled">{l s='Enabled Only' mod='ps_gtm_datalayer'}</option>
            <option value="disabled">{l s='Disabled Only' mod='ps_gtm_datalayer'}</option>
          </select>

          <div class="ps-gtm-bulk-actions">
            <button type="button" class="btn btn-default btn-sm ps-gtm-bulk-btn" onclick="psGtmToggleAllEvents(true)" title="{l s='Enable all tracking events' mod='ps_gtm_datalayer'}">
              <i class="icon-check text-success"></i> {l s='Enable All' mod='ps_gtm_datalayer'}
            </button>
            <button type="button" class="btn btn-default btn-sm ps-gtm-bulk-btn" onclick="psGtmToggleAllEvents(false)" title="{l s='Disable all tracking events' mod='ps_gtm_datalayer'}">
              <i class="icon-times text-danger"></i> {l s='Disable All' mod='ps_gtm_datalayer'}
            </button>
          </div>
        </div>
      </div>

      <!-- Events Catalog Table -->
      <table class="ps-gtm-table" id="ps-gtm-events-table">
        <thead>
          <tr>
            <th style="width: 80px; text-align: center;">{l s='STATUS' mod='ps_gtm_datalayer'}</th>
            <th style="width: 260px;">{l s='EVENT' mod='ps_gtm_datalayer'}</th>
            <th style="width: 150px;">{l s='CATEGORY' mod='ps_gtm_datalayer'}</th>
            <th>{l s='DESCRIPTION' mod='ps_gtm_datalayer'}</th>
            <th style="width: 220px;">{l s='DESTINATIONS' mod='ps_gtm_datalayer'}</th>
            <th style="width: 115px; text-align: right;">{l s='ACTIONS' mod='ps_gtm_datalayer'}</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$canonical_events item=e}
          <tr class="ps-gtm-event-row" data-name="{$e.name|escape:'html':'UTF-8'}" data-label="{$e.label|escape:'html':'UTF-8'}" data-desc="{$e.desc|escape:'html':'UTF-8'}" data-category="{$e.category|escape:'html':'UTF-8'}">
            <td style="text-align: center;">
              <label class="ps-gtm-switch ps-gtm-switch-sm" title="{l s='Toggle event active state' mod='ps_gtm_datalayer'}">
                <input type="checkbox" name="event_active_{$e.name|escape:'html':'UTF-8'}" class="ps-gtm-evt-main-cb" value="1" data-event="{$e.name|escape:'html':'UTF-8'}" {if !empty($e.is_active)}checked{/if}>
                <span class="ps-gtm-slider"></span>
              </label>
            </td>
            <td>
              <div style="display: flex; align-items: flex-start; gap: 10px;">
                <div class="ps-gtm-table-event-icon">
                  <i class="{$e.icon|escape:'html':'UTF-8'}"></i>
                </div>
                <div>
                  <strong class="ps-gtm-event-name" style="color: #0f172a; font-size: 16px; font-weight: 700; display: block; line-height: 1.3;">{$e.label|escape:'html':'UTF-8'}</strong>
                  <code class="ps-gtm-code-badge" style="font-size: 13.5px; font-weight: 600; color: #2563eb; background: #eff6ff; padding: 2px 7px; border-radius: 4px; border: 1px solid #bfdbfe; display: inline-block; margin-top: 4px;">{if !empty($config_values.PS_GTM_EVENT_PREFIX)}{$config_values.PS_GTM_EVENT_PREFIX|escape:'html':'UTF-8'}_{/if}{$e.name|escape:'html':'UTF-8'}</code>
                </div>
              </div>
            </td>
            <td>
              <span class="ps-gtm-cat-badge {$e.category|escape:'html':'UTF-8'}">
                {$e.category_label|escape:'html':'UTF-8'}
              </span>
            </td>
            <td>
              <div style="font-size: 14.5px; color: #334155; line-height: 1.55;">{$e.desc|escape:'html':'UTF-8'}</div>
            </td>
            <td>
              <div class="ps-gtm-dest-cell" data-event="{$e.name|escape:'html':'UTF-8'}">
                <span class="ps-gtm-dest-summary-pill" title="{$e.active_dest_names_str|escape:'html':'UTF-8'}">
                  <i class="icon-share-alt"></i> <span class="dest-count-num">{$e.active_dest_count|escape:'html':'UTF-8'}</span> {l s='destinations' mod='ps_gtm_datalayer'}
                </span>
                {if $e.has_sgtm_webhook}
                  <span class="ps-gtm-plat-tag sgtm" style="margin-left: 4px;" title="{l s='Dispatched via Server GTM Webhook' mod='ps_gtm_datalayer'}">
                    <i class="icon-cloud"></i> sGTM
                  </span>
                {/if}
              </div>
            </td>
            <td style="text-align: right;">
              <button type="button" class="btn btn-default btn-sm ps-gtm-inspect-btn" data-event="{$e.name|escape:'html':'UTF-8'}" title="{l s='Inspect event schema, trigger, and platform destinations' mod='ps_gtm_datalayer'}" style="font-size: 14px; font-weight: 600; padding: 7px 14px;">
                <i class="icon-sliders"></i> {l s='Inspect' mod='ps_gtm_datalayer'}
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
                  <span style="font-size: 14px; font-weight: 700; color: #0f172a;">Microsoft</span>
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
            {if $e.name !== 'page_view' && $e.name !== 'refund' && $e.name !== 'order_status_update'}
            <tr>
              <td class="event-cell">
                <strong style="color: #0f172a; font-size: 15px;">{$e.label|escape:'html':'UTF-8'}</strong><br>
                <code style="font-size: 13px;">{$e.name|escape:'html':'UTF-8'}</code>
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
        <div class="ps-gtm-filter-pills" style="margin-bottom: 22px;">
          {foreach from=$canonical_events item=e key=idx}
          <button type="button" class="ps-gtm-filter-pill ps-gtm-mapping-tab {if $idx === 0}active{/if}" data-event="{$e.name|escape:'html':'UTF-8'}" onclick="psGtmSelectDataMappingEvent('{$e.name|escape:'html':'UTF-8'}')">
            {$e.label|escape:'html':'UTF-8'}
          </button>
          {/foreach}
        </div>

        <!-- Mapping Panes for Each Event -->
        {foreach from=$canonical_events item=e key=idx}
        <div class="ps-gtm-mapping-pane" data-event="{$e.name|escape:'html':'UTF-8'}" style="{if $idx > 0}display: none;{/if}">
          <div style="margin-bottom: 18px; background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 16px 20px;">
            <div style="font-size: 17px; font-weight: 700; color: #0f172a;">{$e.label|escape:'html':'UTF-8'} (<code>{$e.name|escape:'html':'UTF-8'}</code>)</div>
            <div style="font-size: 14.5px; color: #334155; margin-top: 4px; line-height: 1.5;">{$e.desc|escape:'html':'UTF-8'}</div>
            <div style="font-size: 13.5px; color: #475569; margin-top: 6px;">
              <strong style="color: #0f172a;">Storefront / Backend Hook:</strong> <code>{$e.trigger_hook|escape:'html':'UTF-8'}</code>
            </div>
          </div>

          <!-- Parameter Mappings Table -->
          <table class="ps-gtm-table" style="margin-bottom: 22px;">
            <thead>
              <tr>
                <th style="width: 190px;">{l s='Parameter' mod='ps_gtm_datalayer'}</th>
                <th style="width: 95px;">{l s='Type' mod='ps_gtm_datalayer'}</th>
                <th>{l s='GA4 Source Field' mod='ps_gtm_datalayer'}</th>
                <th>{l s='Advertising Platform Destinations' mod='ps_gtm_datalayer'}</th>
              </tr>
            </thead>
            <tbody>
              {foreach from=$e.schema_fields item=f}
              <tr>
                <td><strong style="font-size: 14px; color: #0f172a;"><code>{$f.name|escape:'html':'UTF-8'}</code></strong></td>
                <td><span class="badge" style="background: #e2e8f0; color: #1e293b; font-size: 12.5px; font-weight: 600; padding: 4px 8px;">{$f.type|escape:'html':'UTF-8'}</span></td>
                <td><code style="font-size: 13.5px;">{$f.source|escape:'html':'UTF-8'}</code></td>
                <td>
                  <div style="font-size: 13.5px; font-weight: 600; color: #0f172a;">{$f.destinations|escape:'html':'UTF-8'}</div>
                  <div style="font-size: 13px; color: #475569; margin-top: 3px; line-height: 1.4;">{$f.desc|escape:'html':'UTF-8'}</div>
                </td>
              </tr>
              {/foreach}
            </tbody>
          </table>

          <!-- Live Sample Payload Preview -->
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
            <strong style="font-size: 14.5px; font-weight: 700; color: #0f172a;">
              <i class="icon-code"></i> {l s='Verified Backend DataLayer JSON Payload' mod='ps_gtm_datalayer'}:
            </strong>
            <button type="button" class="btn btn-default btn-sm" onclick="psGtmCopyCode('ps-gtm-code-{$e.name|escape:'html':'UTF-8'}')" style="font-size: 13.5px; font-weight: 600; padding: 6px 14px;">
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

        <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
          <button type="button" id="ps-gtm-ping-btn" class="btn btn-primary btn-lg" style="font-size: 15px; font-weight: 600; padding: 11px 22px;">
            <i class="icon-bolt"></i> {l s='Ping sGTM Transport URL' mod='ps_gtm_datalayer'}
          </button>
          <span style="font-size: 14px; color: #475569;">
            {l s='Target Endpoint:' mod='ps_gtm_datalayer'} <code style="font-size: 13.5px;">{$diagnostics.target_endpoint|escape:'html':'UTF-8'}</code>
          </span>
        </div>

        <div id="ps-gtm-ping-result" style="display: none; margin-top: 16px;"></div>
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
                <strong style="font-size: 15.5px; color: #0f172a;">{l s='Google Tag Manager Web Container ID' mod='ps_gtm_datalayer'}</strong>
                <div style="font-size: 13.5px; color: #475569; margin-top: 3px; line-height: 1.5;">{$diagnostics.gtm_id_msg|escape:'html':'UTF-8'}</div>
              </div>
            </div>
            <span class="label {if $diagnostics.gtm_id_valid}label-success{else}label-warning{/if}" style="font-size: 13px; font-weight: 600; padding: 5px 12px;">
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
                <strong style="font-size: 15.5px; color: #0f172a;">{l s='Catalog Identifier Strategy Resolution' mod='ps_gtm_datalayer'}</strong>
                <div style="font-size: 13.5px; color: #475569; margin-top: 3px; line-height: 1.5;">
                  {l s='Current Strategy:' mod='ps_gtm_datalayer'} <code style="font-size: 13px;">{$config_values.PS_GTM_CATALOG_ID_STRATEGY|escape:'html':'UTF-8'}</code> &bull;
                  {l s='Sample output for Product #68 Variant #299:' mod='ps_gtm_datalayer'} <code style="font-size: 13px;">{$diagnostics.sample_catalog_id|escape:'html':'UTF-8'}</code>
                </div>
              </div>
            </div>
            <span class="label label-success" style="font-size: 13px; font-weight: 600; padding: 5px 12px;">{l s='Active' mod='ps_gtm_datalayer'}</span>
          </div>

          <!-- Deduplication Table Check -->
          <div class="ps-gtm-diag-item">
            <div class="ps-gtm-diag-left">
              <div class="ps-gtm-diag-status-icon success">
                <i class="icon-database"></i>
              </div>
              <div>
                <strong style="font-size: 15.5px; color: #0f172a;">{l s='Database Purchase Deduplication Table' mod='ps_gtm_datalayer'}</strong>
                <div style="font-size: 13.5px; color: #475569; margin-top: 3px; line-height: 1.5;">
                  <code style="font-size: 13px;">gtm_purchase_log</code> {l s='table verified with' mod='ps_gtm_datalayer'} {$diagnostics.purchase_log_count|escape:'html':'UTF-8'} {l s='recorded order transactions.' mod='ps_gtm_datalayer'}
                </div>
              </div>
            </div>
            <span class="label label-success" style="font-size: 13px; font-weight: 600; padding: 5px 12px;">{l s='Synchronized' mod='ps_gtm_datalayer'}</span>
          </div>

          <!-- Server Environment Check -->
          <div class="ps-gtm-diag-item">
            <div class="ps-gtm-diag-left">
              <div class="ps-gtm-diag-status-icon success">
                <i class="icon-desktop"></i>
              </div>
              <div>
                <strong style="font-size: 15.5px; color: #0f172a;">{l s='Server Runtime Environment' mod='ps_gtm_datalayer'}</strong>
                <div style="font-size: 13.5px; color: #475569; margin-top: 3px; line-height: 1.5;">
                  PHP {$diagnostics.php_version|escape:'html':'UTF-8'} &bull;
                  cURL: {$diagnostics.curl_status|escape:'html':'UTF-8'} &bull;
                  OpenSSL: {$diagnostics.openssl_status|escape:'html':'UTF-8'} &bull;
                  Memory Limit: {$diagnostics.memory_limit|escape:'html':'UTF-8'}
                </div>
              </div>
            </div>
            <span class="label label-success" style="font-size: 13px; font-weight: 600; padding: 5px 12px;">{l s='Compatible' mod='ps_gtm_datalayer'}</span>
          </div>

          <!-- PrestaShop Hook Registrations Check -->
          <div class="ps-gtm-diag-item">
            <div class="ps-gtm-diag-left">
              <div class="ps-gtm-diag-status-icon success">
                <i class="icon-link"></i>
              </div>
              <div>
                <strong style="font-size: 15.5px; color: #0f172a;">{l s='PrestaShop Lifecycle Hooks' mod='ps_gtm_datalayer'}</strong>
                <div style="font-size: 13.5px; color: #475569; margin-top: 3px; line-height: 1.5;">
                  {l s='All required storefront, checkout, and webhook lifecycle hooks are registered in the core PrestaShop database.' mod='ps_gtm_datalayer'}
                </div>
              </div>
            </div>
            <span class="label label-success" style="font-size: 13px; font-weight: 600; padding: 5px 12px;">{$diagnostics.registered_hooks_count|escape:'html':'UTF-8'} {l s='Hooks Active' mod='ps_gtm_datalayer'}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Unsaved Changes Floating Banner -->
    <div id="ps-gtm-unsaved-alert" class="ps-gtm-unsaved-alert" style="display: none;">
      <div style="display: flex; align-items: center; gap: 12px;">
        <i class="icon-warning-sign" style="font-size: 20px; color: #f59e0b;"></i>
        <div style="font-size: 14.5px; color: #0f172a;">
          <strong>{l s='Unsaved Changes' mod='ps_gtm_datalayer'}:</strong> {l s='You have modified settings across your configuration. Click "Save All Settings" to apply.' mod='ps_gtm_datalayer'}
        </div>
      </div>
      <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('ps-gtm-save-btn').click();" style="font-size: 13.5px; font-weight: 700; padding: 6px 16px;">
        <i class="icon-save"></i> {l s='Save Now' mod='ps_gtm_datalayer'}
      </button>
    </div>

    <!-- Sticky Save Action Bar -->
    <div class="ps-gtm-save-bar">
      <div style="font-size: 14.5px; color: #475569;">
        <i class="icon-info-circle text-primary"></i> {l s='All settings across all tabs are saved simultaneously with zero data loss.' mod='ps_gtm_datalayer'}
      </div>
      <button type="submit" name="submit{$module_name|escape:'html':'UTF-8'}" id="ps-gtm-save-btn" class="ps-gtm-btn-save">
        <i class="icon-save"></i> <span>{l s='Save All Settings' mod='ps_gtm_datalayer'}</span>
      </button>
    </div>

  </form>

  <!-- Slide-Out Inspect Drawer -->
  <div id="ps-gtm-inspect-drawer-backdrop" class="ps-gtm-drawer-backdrop" role="dialog" aria-modal="true">
    <div class="ps-gtm-drawer">
      <!-- Drawer Header -->
      <div class="ps-gtm-drawer-header">
        <div style="display: flex; align-items: center; gap: 14px;">
          <div class="ps-gtm-drawer-icon-box">
            <i id="ps-gtm-drawer-icon" class="icon-eye"></i>
          </div>
          <div>
            <h3 id="ps-gtm-drawer-title" style="margin: 0; font-size: 21px; font-weight: 700; color: #0f172a; line-height: 1.25;">View Item</h3>
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
