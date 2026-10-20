{*
 * Body noscript fallback
 *}
{if !empty($gtm_id)}
<noscript>
  <iframe src="https://www.googletagmanager.com/ns.html?id={$gtm_id|escape:'htmlall':'UTF-8'}"
  height="0" width="0" style="display:none;visibility:hidden"></iframe>
</noscript>
{/if}
