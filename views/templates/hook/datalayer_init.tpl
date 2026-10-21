{*
 * Server-hydrated initial DataLayer push and backend configuration.
 * 100% authoritative backend data sourcing — ZERO DOM dependency.
 *}
<script type="text/javascript">
  window.dataLayer = window.dataLayer || [];
  window.psGtmConfig = window.psGtmConfig || {};
  {if isset($event_prefix)}
  window.psGtmConfig.eventPrefix = '{$event_prefix|escape:'javascript':'UTF-8'}';
  {/if}
  {if isset($currency_precision)}
  window.psGtmConfig.currencyPrecision = {$currency_precision|intval};
  {/if}
  {if isset($ajax_url)}
  window.psGtmConfig.ajaxUrl = '{$ajax_url|escape:'javascript':'UTF-8'}';
  {/if}
  {if isset($product_combinations)}
  try {
    window.psGtmConfig.productCombinations = {$product_combinations nofilter};
  } catch(e) {
    window.psGtmConfig.productCombinations = {};
  }
  {/if}

  {if !empty($initial_server_payload)}
  (function(){
    try {
      var payload = {$initial_server_payload nofilter};
      if (payload && payload.event) {
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

  {if !empty($pending_events)}
  (function(){
    try {
      var pending = {$pending_events nofilter};
      if (Array.isArray(pending)) {
        for (var i = 0; i < pending.length; i++) {
          window.dataLayer.push({ ecommerce: null });
          window.dataLayer.push(pending[i]);
        }
      }
    } catch(e) {
      console.error('[GTM DataLayer] Pending events error:', e);
    }
  })();
  {/if}
</script>
