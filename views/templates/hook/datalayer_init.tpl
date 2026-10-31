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
  {if isset($cart_id) && $cart_id > 0}
  window.psGtmConfig.cartId = {$cart_id|intval};
  {/if}
  {if isset($product_combinations)}
  try {
    window.psGtmConfig.productCombinations = {$product_combinations nofilter};
  } catch(e) {
    window.psGtmConfig.productCombinations = {};
  }
  {/if}
  {if isset($cart_products)}
  try {
    window.psGtmConfig.cartProducts = {$cart_products nofilter};
  } catch(e) {
    window.psGtmConfig.cartProducts = {};
  }
  {/if}
  {if isset($cart_products_data)}
  try {
    window.psGtmConfig.cartProductsData = {$cart_products_data nofilter};
  } catch(e) {
    window.psGtmConfig.cartProductsData = {};
  }
  {/if}
  {if isset($wishlist_capabilities)}
  try {
    window.psGtmConfig.wishlist = {$wishlist_capabilities nofilter};
  } catch(e) {
    window.psGtmConfig.wishlist = {};
  }
  {/if}
  {if isset($catalog_id_strategy)}
  window.psGtmConfig.catalogIdStrategy = '{$catalog_id_strategy|escape:'javascript':'UTF-8'}';
  {/if}
  {if isset($advertising_settings)}
  try {
    window.psGtmConfig.advertisingSettings = {$advertising_settings nofilter};
  } catch(e) {
    window.psGtmConfig.advertisingSettings = null;
  }
  {/if}
  {if isset($carriers_map)}
  try {
    window.psGtmConfig.carriers = {$carriers_map nofilter};
  } catch(e) {
    window.psGtmConfig.carriers = {};
  }
  {/if}
  {if isset($payment_methods_map)}
  try {
    window.psGtmConfig.paymentMethods = {$payment_methods_map nofilter};
  } catch(e) {
    window.psGtmConfig.paymentMethods = {};
  }
  {/if}
  {if isset($payment_options_map)}
  try {
    window.psGtmConfig.paymentOptionsMap = {$payment_options_map nofilter};
  } catch(e) {
    window.psGtmConfig.paymentOptionsMap = {};
  }
  {/if}
  {if isset($selected_carrier)}
  window.psGtmConfig.selectedCarrier = '{$selected_carrier|escape:'javascript':'UTF-8'}';
  {/if}
  {if isset($cart_coupon)}
  window.psGtmConfig.coupon = '{$cart_coupon|escape:'javascript':'UTF-8'}';
  {/if}
  {if !empty($meta_user_data)}
  try {
    window.psGtmConfig.metaUserData = {$meta_user_data nofilter};
  } catch(e) {
    window.psGtmConfig.metaUserData = null;
  }
  {else}
  window.psGtmConfig.metaUserData = null;
  {/if}
  {if !empty($pinterest_user_data)}
  try {
    window.psGtmConfig.pinterestUserData = {$pinterest_user_data nofilter};
  } catch(e) {
    window.psGtmConfig.pinterestUserData = null;
  }
  {else}
  window.psGtmConfig.pinterestUserData = null;
  {/if}
  {if !empty($tiktok_user_data)}
  try {
    window.psGtmConfig.tiktokUserData = {$tiktok_user_data nofilter};
  } catch(e) {
    window.psGtmConfig.tiktokUserData = null;
  }
  {else}
  window.psGtmConfig.tiktokUserData = null;
  {/if}
  {if !empty($microsoft_user_data)}
  try {
    window.psGtmConfig.microsoftUserData = {$microsoft_user_data nofilter};
  } catch(e) {
    window.psGtmConfig.microsoftUserData = null;
  }
  {else}
  window.psGtmConfig.microsoftUserData = null;
  {/if}
  {if !empty($openai_user_data)}
  try {
    window.psGtmConfig.openaiUserData = {$openai_user_data nofilter};
  } catch(e) {
    window.psGtmConfig.openaiUserData = null;
  }
  {else}
  window.psGtmConfig.openaiUserData = null;
  {/if}
  {if isset($page_context)}
  try {
    window.psGtmConfig.pageContext = {$page_context nofilter};
  } catch(e) {
    window.psGtmConfig.pageContext = null;
  }
  {/if}

  {if !empty($initial_server_payload)}
  (function(){
    try {
      var payload = {$initial_server_payload nofilter};
      window.psGtmConfig.initialServerPayload = payload;
      var payloads = Array.isArray(payload) ? payload : [payload];
      for (var pIdx = 0; pIdx < payloads.length; pIdx++) {
        var p = payloads[pIdx];
        if (p && p.event) {
          if (p.original_event === 'view_item' && p.ecommerce && p.ecommerce.items && p.ecommerce.items[0]) {
            window.psGtmConfig.currentProduct = p.ecommerce.items[0];
            window.psGtmConfig.currency = p.ecommerce.currency || 'EUR';
          } else if (p.original_event === 'view_item_list' && p.ecommerce) {
            window.psGtmConfig.currentList = {
              item_list_id: p.ecommerce.item_list_id || '',
              item_list_name: p.ecommerce.item_list_name || '',
              items: p.ecommerce.items || []
            };
            window.psGtmConfig.currency = p.ecommerce.currency || 'EUR';
          } else if (p.original_event === 'view_search_results' && p.ecommerce) {
            window.psGtmConfig.searchResults = {
              search_term: p.search_term || (p.ecommerce && p.ecommerce.search_term) || '',
              items: p.ecommerce.items || []
            };
            window.psGtmConfig.currentList = {
              item_list_id: '',
              item_list_name: '',
              items: p.ecommerce.items || []
            };
            window.psGtmConfig.currency = p.ecommerce.currency || 'EUR';
          }
          window.dataLayer.push({
            ecommerce: null,
            meta: null,
            tiktok: null,
            google_ads: null,
            pinterest: null,
            microsoft_ads: null,
            openai_ads: null,
            snapchat: null
          });
          window.dataLayer.push(p);
        }
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
          window.dataLayer.push({
            ecommerce: null,
            meta: null,
            tiktok: null,
            google_ads: null,
            pinterest: null,
            microsoft_ads: null,
            openai_ads: null,
            snapchat: null
          });
          window.dataLayer.push(pending[i]);
        }
      }
    } catch(e) {
      console.error('[GTM DataLayer] Pending events error:', e);
    }
  })();
  {/if}
</script>
