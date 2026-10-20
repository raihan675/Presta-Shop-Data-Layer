{*
 * Server-hydrated initial DataLayer push and client-side configuration
 *}
<script type="text/javascript">
  window.dataLayer = window.dataLayer || [];
  {if isset($event_prefix)}
  window.psGtmConfig = window.psGtmConfig || {};
  window.psGtmConfig.eventPrefix = '{$event_prefix|escape:'javascript':'UTF-8'}';
  if (window.EcommerceDataLayer && typeof window.EcommerceDataLayer.setEventPrefix === 'function') {
    window.EcommerceDataLayer.setEventPrefix(window.psGtmConfig.eventPrefix);
  }
  {/if}

  {if !empty($initial_server_payload)}
  (function(){
    try {
      var payload = {$initial_server_payload nofilter};
      if (payload && payload.event) {
        window.dataLayer.push({ ecommerce: null });
        window.dataLayer.push(payload);
      }
    } catch(e) {
      console.error('[GTM DataLayer] Initialization error:', e);
    }
  })();
  {/if}
</script>
