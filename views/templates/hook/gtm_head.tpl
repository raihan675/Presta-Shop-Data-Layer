{*
 * Header hook template: Google Consent Mode v2 + Web / sGTM Container loader
 *}
{if $consent_mode_enabled}
<script type="text/javascript">
  window.dataLayer = window.dataLayer || [];
  function gtag(){ dataLayer.push(arguments); }
  
  gtag('consent', 'default', {
    'ad_storage': 'denied',
    'analytics_storage': 'denied',
    'ad_user_data': 'denied',
    'ad_personalization': 'denied',
    'wait_for_update': 500
  });
</script>
{/if}

{if $gtm_id}
<script type="text/javascript">
(function(w,d,s,l,i){
  w[l]=w[l]||[];w[l].push({ 'gtm.start': new Date().getTime(), event:'gtm.js' });
  var f=d.getElementsByTagName(s)[0],
      j=d.createElement(s),
      dl=l!='dataLayer'?'&l='+l:'';
  j.async=true;
  {if !empty($sgtm_url)}
  j.src='{$sgtm_url|escape:'javascript':'UTF-8'}/gtm.js?id='+i+dl;
  {else}
  j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;
  {/if}
  f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{$gtm_id|escape:'javascript':'UTF-8'}');
</script>
{/if}
