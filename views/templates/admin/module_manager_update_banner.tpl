{*
 * Notice banner and 1-Click Update for PrestaShop Module Manager & Back Office
 *}
<div class="alert alert-warning ps-gtm-update-alert" style="margin: 15px 0; border-left: 5px solid #25b9d7; background: #fff8e5; box-shadow: 0 2px 5px rgba(0,0,0,0.06); padding: 15px 20px; border-radius: 4px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
  <div style="flex: 1; min-width: 280px;">
    <div style="display: flex; align-items: center; gap: 10px;">
      <i class="material-icons" style="font-size: 26px; color: #f59f00; vertical-align: middle;">notifications_active</i>
      <div>
        <strong style="font-size: 16px; color: #0f172a;">{$module_display_name|replace:'&amp;':'&' nofilter}: New version {$latest_version|escape:'html':'UTF-8'} is available!</strong>
        <span style="display: block; font-size: 14px; color: #475569; margin-top: 4px;">
          Installed: v{$current_version|escape:'html':'UTF-8'} &rarr; Latest: v{$latest_version|escape:'html':'UTF-8'}
        </span>
      </div>
    </div>
    {if !empty($release_notes)}
    <div style="margin-top: 10px; font-size: 14px; line-height: 1.5; color: #1e293b; background: rgba(255,255,255,0.85); padding: 10px 14px; border-radius: 4px; max-width: 800px; white-space: pre-line;">
      {$release_notes|escape:'html':'UTF-8'}
    </div>
    {/if}
  </div>

  <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
    <form action="{if isset($update_action_url)}{$update_action_url}{else}{$current_request_url}{/if}" method="post" style="margin: 0; display: inline-block;">
      <input type="hidden" name="update_download_url" value="{$download_url|escape:'html':'UTF-8'}">
      <input type="hidden" name="update_checksum" value="{$checksum|escape:'html':'UTF-8'}">
      <input type="hidden" name="update_version" value="{$latest_version|escape:'html':'UTF-8'}">
      <button type="submit" name="submitRunModuleUpdate" class="btn btn-success btn-lg" onclick="return confirm('Update {$module_display_name|replace:'&amp;':'&'|escape:'javascript':'UTF-8'} to version {$latest_version|escape:'javascript':'UTF-8'} now? All settings will be preserved.');" style="font-size: 15px; font-weight: 600; padding: 12px 20px;">
        <i class="material-icons" style="font-size: 18px; vertical-align: middle; margin-right: 4px;">system_update_alt</i> 1-Click Update to v{$latest_version|escape:'html':'UTF-8'}
      </button>
    </form>
    <a href="{$download_url|escape:'html':'UTF-8'}" class="btn btn-outline-secondary btn-lg" style="font-size: 15px; font-weight: 500; padding: 12px 18px; border: 1.5px solid #cbd5e1; background: #fff; color: #1e293b;" target="_blank" download>
      <i class="material-icons" style="font-size: 18px; vertical-align: middle; margin-right: 4px;">file_download</i> Download ZIP
    </a>
  </div>
</div>

<script type="text/javascript">
  (function () {
    function injectModuleCardUpdateButton() {
      // Find the module in the Module Manager list
      var selectors = [
        '[data-tech-name="ps_gtm_datalayer"]',
        '[data-module-name="ps_gtm_datalayer"]',
        'tr[data-name*="ps_gtm_datalayer"]',
        '.module-item[data-name*="E-Commerce DataLayer"]',
        '.module-item-list[data-name*="E-Commerce DataLayer"]',
        '.module-item-grid[data-name*="E-Commerce DataLayer"]',
        '.module-item[data-name*="Enterprise GA4"]',
        '.module-item-list[data-name*="Enterprise GA4"]',
        '.module-item-grid[data-name*="Enterprise GA4"]'
      ];

      var moduleCards = [];
      for (var i = 0; i < selectors.length; i++) {
        var elements = document.querySelectorAll(selectors[i]);
        for (var j = 0; j < elements.length; j++) {
          if (moduleCards.indexOf(elements[j]) === -1) {
            moduleCards.push(elements[j]);
          }
        }
      }

      if (moduleCards.length === 0) {
        var allCards = document.querySelectorAll('.module-item, .module-item-grid, .module-item-list, tr.modules');
        for (var k = 0; k < allCards.length; k++) {
          var card = allCards[k];
          if ((card.getAttribute('data-name') && card.getAttribute('data-name').toLowerCase().indexOf('ps_gtm_datalayer') !== -1) ||
              card.innerHTML.indexOf('ps_gtm_datalayer') !== -1 ||
              card.innerHTML.indexOf('E-Commerce DataLayer') !== -1 ||
              card.innerHTML.indexOf('Enterprise GA4') !== -1) {
            moduleCards.push(card);
          }
        }
      }

      for (var m = 0; m < moduleCards.length; m++) {
        var card = moduleCards[m];
        var actionsContainer = card.querySelector('.btn-group, .module-actions, .actions');
        if (actionsContainer && !actionsContainer.querySelector('.btn-ps-gtm-inline-update')) {
          var targetActionUrl = '{if isset($update_action_url)}{$update_action_url|escape:'javascript':'UTF-8'}{else}{$current_request_url|escape:'javascript':'UTF-8'}{/if}';
          var form = document.createElement('form');
          form.action = targetActionUrl;
          form.method = 'POST';
          form.style.display = 'inline-block';
          form.style.marginRight = '6px';
          form.innerHTML = '<input type="hidden" name="update_download_url" value="{$download_url|escape:'html':'UTF-8'}">' +
            '<input type="hidden" name="update_checksum" value="{$checksum|escape:'html':'UTF-8'}">' +
            '<input type="hidden" name="update_version" value="{$latest_version|escape:'html':'UTF-8'}">' +
            '<button type="submit" name="submitRunModuleUpdate" class="btn btn-success btn-sm btn-ps-gtm-inline-update" style="font-weight:600;" onclick="return confirm(\'Update to version {$latest_version|escape:'javascript':'UTF-8'} now?\');">' +
            '<i class="material-icons" style="font-size:14px;vertical-align:middle;margin-right:2px;">system_update_alt</i> Update to v{$latest_version|escape:'html':'UTF-8'}</button>';

          actionsContainer.prepend(form);
        }
      }
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', injectModuleCardUpdateButton);
    } else {
      injectModuleCardUpdateButton();
    }
    setTimeout(injectModuleCardUpdateButton, 1000);
    setTimeout(injectModuleCardUpdateButton, 3000);
  })();
</script>
