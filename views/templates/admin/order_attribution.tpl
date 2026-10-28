{**
 * Order Marketing Attribution & Ad Click IDs Display
 *}
<div class="card mt-2 ps-gtm-attribution-card" style="border: 1px solid #dbe2e6; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 20px;">
  <div class="card-header" style="background-color: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 12px 18px; font-weight: 600; display: flex; align-items: center; justify-content: space-between;">
    <div style="display: flex; align-items: center; gap: 8px;">
      <i class="material-icons" style="color: #2563eb; font-size: 20px;">track_changes</i>
      <span style="font-size: 15px; color: #1e293b;">{l s='Marketing Attribution & Ad Click IDs' mod='ps_gtm_datalayer'}</span>
    </div>
    {if !empty($attribution)}
      <span class="badge badge-success" style="background-color: #10b981; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px;">
        {l s='Attributed Order' mod='ps_gtm_datalayer'}
      </span>
    {else}
      <span class="badge badge-secondary" style="background-color: #94a3b8; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px;">
        {l s='Direct / Organic' mod='ps_gtm_datalayer'}
      </span>
    {/if}
  </div>
  <div class="card-body" style="padding: 16px 18px; background-color: #ffffff;">
    {if empty($attribution)}
      <p style="color: #64748b; margin: 0; font-size: 13px; font-style: italic;">
        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">info</i>
        {l s='No advertising click identifiers or paid campaign parameters were captured for this order session.' mod='ps_gtm_datalayer'}
      </p>
    {else}
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px;">
        
        {* Priority 1: Ad Click IDs *}
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px;">
          <div style="font-weight: 600; font-size: 12px; color: #475569; text-transform: uppercase; margin-bottom: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;">
            {l s='Priority 1: Ad Click Identifiers' mod='ps_gtm_datalayer'}
          </div>
          <table style="width: 100%; font-size: 12px; border-collapse: collapse;">
            {if !empty($attribution.gclid)}
              <tr>
                <td style="padding: 4px 0; color: #64748b; font-weight: 500; width: 80px;">Google (gclid):</td>
                <td style="padding: 4px 0; font-family: monospace; word-break: break-all; color: #0f172a;">{$attribution.gclid|escape:'html':'UTF-8'}</td>
              </tr>
            {/if}
            {if !empty($attribution.gbraid)}
              <tr>
                <td style="padding: 4px 0; color: #64748b; font-weight: 500;">Google (gbraid):</td>
                <td style="padding: 4px 0; font-family: monospace; word-break: break-all; color: #0f172a;">{$attribution.gbraid|escape:'html':'UTF-8'}</td>
              </tr>
            {/if}
            {if !empty($attribution.wbraid)}
              <tr>
                <td style="padding: 4px 0; color: #64748b; font-weight: 500;">Google (wbraid):</td>
                <td style="padding: 4px 0; font-family: monospace; word-break: break-all; color: #0f172a;">{$attribution.wbraid|escape:'html':'UTF-8'}</td>
              </tr>
            {/if}
            {if !empty($attribution.fbclid)}
              <tr>
                <td style="padding: 4px 0; color: #64748b; font-weight: 500;">Meta (fbclid):</td>
                <td style="padding: 4px 0; font-family: monospace; word-break: break-all; color: #0f172a;">{$attribution.fbclid|escape:'html':'UTF-8'}</td>
              </tr>
            {/if}
            {if !empty($attribution.ttclid)}
              <tr>
                <td style="padding: 4px 0; color: #64748b; font-weight: 500;">TikTok (ttclid):</td>
                <td style="padding: 4px 0; font-family: monospace; word-break: break-all; color: #0f172a;">{$attribution.ttclid|escape:'html':'UTF-8'}</td>
              </tr>
            {/if}
            {if !empty($attribution.msclkid)}
              <tr>
                <td style="padding: 4px 0; color: #64748b; font-weight: 500;">Bing (msclkid):</td>
                <td style="padding: 4px 0; font-family: monospace; word-break: break-all; color: #0f172a;">{$attribution.msclkid|escape:'html':'UTF-8'}</td>
              </tr>
            {/if}
            {if !empty($attribution.sccid)}
              <tr>
                <td style="padding: 4px 0; color: #64748b; font-weight: 500;">Snap (ScCid):</td>
                <td style="padding: 4px 0; font-family: monospace; word-break: break-all; color: #0f172a;">{$attribution.sccid|escape:'html':'UTF-8'}</td>
              </tr>
            {/if}
            {if !empty($attribution.oppref)}
              <tr>
                <td style="padding: 4px 0; color: #64748b; font-weight: 500;">OpenAI (oppref):</td>
                <td style="padding: 4px 0; font-family: monospace; word-break: break-all; color: #0f172a;">{$attribution.oppref|escape:'html':'UTF-8'}</td>
              </tr>
            {/if}
            {if !empty($attribution.epik)}
              <tr>
                <td style="padding: 4px 0; color: #64748b; font-weight: 500;">Pinterest (epik):</td>
                <td style="padding: 4px 0; font-family: monospace; word-break: break-all; color: #0f172a;">{$attribution.epik|escape:'html':'UTF-8'}</td>
              </tr>
            {/if}
          </table>
        </div>

        {* Priority 2: Browser & Analytics Cookies *}
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px;">
          <div style="font-weight: 600; font-size: 12px; color: #475569; text-transform: uppercase; margin-bottom: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;">
            {l s='Priority 2 & 3: Browser & Analytics IDs' mod='ps_gtm_datalayer'}
          </div>
          <table style="width: 100%; font-size: 12px; border-collapse: collapse;">
            {if !empty($attribution.fbp)}
              <tr>
                <td style="padding: 4px 0; color: #64748b; font-weight: 500; width: 85px;">Meta _fbp:</td>
                <td style="padding: 4px 0; font-family: monospace; word-break: break-all; color: #0f172a;">{$attribution.fbp|escape:'html':'UTF-8'}</td>
              </tr>
            {/if}
            {if !empty($attribution.fbc)}
              <tr>
                <td style="padding: 4px 0; color: #64748b; font-weight: 500;">Meta _fbc:</td>
                <td style="padding: 4px 0; font-family: monospace; word-break: break-all; color: #0f172a;">{$attribution.fbc|escape:'html':'UTF-8'}</td>
              </tr>
            {/if}
            {if !empty($attribution.ga_client_id)}
              <tr>
                <td style="padding: 4px 0; color: #64748b; font-weight: 500;">GA4 client_id:</td>
                <td style="padding: 4px 0; font-family: monospace; word-break: break-all; color: #0f172a;">{$attribution.ga_client_id|escape:'html':'UTF-8'}</td>
              </tr>
            {/if}
            {if !empty($attribution.ga_session_id)}
              <tr>
                <td style="padding: 4px 0; color: #64748b; font-weight: 500;">GA4 session_id:</td>
                <td style="padding: 4px 0; font-family: monospace; word-break: break-all; color: #0f172a;">{$attribution.ga_session_id|escape:'html':'UTF-8'}</td>
              </tr>
            {/if}
            {if !empty($attribution.ttp)}
              <tr>
                <td style="padding: 4px 0; color: #64748b; font-weight: 500;">TikTok _ttp:</td>
                <td style="padding: 4px 0; font-family: monospace; word-break: break-all; color: #0f172a;">{$attribution.ttp|escape:'html':'UTF-8'}</td>
              </tr>
            {/if}
            {if !empty($attribution.scid)}
              <tr>
                <td style="padding: 4px 0; color: #64748b; font-weight: 500;">Snap _scid:</td>
                <td style="padding: 4px 0; font-family: monospace; word-break: break-all; color: #0f172a;">{$attribution.scid|escape:'html':'UTF-8'}</td>
              </tr>
            {/if}
            {if !empty($attribution.uetvid)}
              <tr>
                <td style="padding: 4px 0; color: #64748b; font-weight: 500;">Bing _uetvid:</td>
                <td style="padding: 4px 0; font-family: monospace; word-break: break-all; color: #0f172a;">{$attribution.uetvid|escape:'html':'UTF-8'}</td>
              </tr>
            {/if}
          </table>
        </div>

        {* Campaign & UTM Metadata *}
        {if !empty($attribution.utm_source) || !empty($attribution.utm_campaign) || !empty($attribution.utm_medium)}
          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; grid-column: 1 / -1;">
            <div style="font-weight: 600; font-size: 12px; color: #475569; text-transform: uppercase; margin-bottom: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;">
              {l s='Campaign Parameters (UTM)' mod='ps_gtm_datalayer'}
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 16px; font-size: 12px;">
              {if !empty($attribution.utm_source)}
                <div><span style="color: #64748b; font-weight: 500;">Source:</span> <span style="font-weight: 600; color: #0f172a;">{$attribution.utm_source|escape:'html':'UTF-8'}</span></div>
              {/if}
              {if !empty($attribution.utm_medium)}
                <div><span style="color: #64748b; font-weight: 500;">Medium:</span> <span style="font-weight: 600; color: #0f172a;">{$attribution.utm_medium|escape:'html':'UTF-8'}</span></div>
              {/if}
              {if !empty($attribution.utm_campaign)}
                <div><span style="color: #64748b; font-weight: 500;">Campaign:</span> <span style="font-weight: 600; color: #0f172a;">{$attribution.utm_campaign|escape:'html':'UTF-8'}</span></div>
              {/if}
              {if !empty($attribution.utm_content)}
                <div><span style="color: #64748b; font-weight: 500;">Content:</span> <span style="color: #0f172a;">{$attribution.utm_content|escape:'html':'UTF-8'}</span></div>
              {/if}
              {if !empty($attribution.utm_term)}
                <div><span style="color: #64748b; font-weight: 500;">Term:</span> <span style="color: #0f172a;">{$attribution.utm_term|escape:'html':'UTF-8'}</span></div>
              {/if}
            </div>
          </div>
        {/if}

      </div>
      <div style="margin-top: 10px; font-size: 11px; color: #94a3b8; display: flex; align-items: center; justify-content: space-between;">
        <span><i class="material-icons" style="font-size: 13px; vertical-align: middle;">sync</i> {l s='Automatically rehydrated into sGTM server webhooks during order status changes & refunds.' mod='ps_gtm_datalayer'}</span>
        {if !empty($attribution.date_add)}
          <span>{l s='Captured on:' mod='ps_gtm_datalayer'} {$attribution.date_add|escape:'html':'UTF-8'}</span>
        {/if}
      </div>
    {/if}
  </div>
</div>
