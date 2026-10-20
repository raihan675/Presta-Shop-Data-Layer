{*
 * Server-hydrated initial DataLayer push and client-side configuration
 *}
<script type="text/javascript">
  window.dataLayer = window.dataLayer || [];
  window.psGtmConfig = window.psGtmConfig || {};
  {if isset($event_prefix)}
  window.psGtmConfig.eventPrefix = '{$event_prefix|escape:'javascript':'UTF-8'}';
  {/if}

  {if !empty($initial_server_payload)}
  (function(){
    try {
      var payload = {$initial_server_payload nofilter};
      if (payload && payload.event) {
        // Expose current PDP product context to client-side adapter for consistent attributes
        if (payload.original_event === 'view_item' && payload.ecommerce && payload.ecommerce.items && payload.ecommerce.items[0]) {
          window.psGtmConfig.currentProduct = payload.ecommerce.items[0];
          window.psGtmConfig.currency = payload.ecommerce.currency || 'EUR';
        }
        window.dataLayer.push({ ecommerce: null });
        window.dataLayer.push(payload);
      }
    } catch(e) {
      console.error('[GTM DataLayer] Initialization error:', e);
    }
  })();
  {/if}
</script>
