{*
 * Server-hydrated initial DataLayer push
 *}
{if !empty($initial_server_payload)}
<script type="text/javascript">
  window.dataLayer = window.dataLayer || [];
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
</script>
{/if}
