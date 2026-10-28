{**
 * Order Marketing Attribution & Ad Click IDs Display - Data Table Format
 *}
<div class="card mt-3 ps-gtm-attribution-card" style="box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #dbe2e8; border-radius: 6px; margin-bottom: 25px; clear: both;">
  <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 14px 18px;">
    <div style="display: flex; align-items: center; gap: 10px;">
      <i class="material-icons" style="color: #2563eb; font-size: 22px; vertical-align: middle;">track_changes</i>
      <span style="font-size: 15px; font-weight: 700; color: #1e293b;">
        {l s='Marketing Attribution & Ad Click IDs' mod='ps_gtm_datalayer'}
      </span>
    </div>
    <div>
      {if !empty($attribution.gclid) || !empty($attribution.fbclid) || !empty($attribution.ttclid) || !empty($attribution.msclkid) || !empty($attribution.utm_campaign) || !empty($attribution.utm_source)}
        <span class="badge badge-success" style="background-color: #10b981; color: white; padding: 5px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
          {l s='✓ Attributed Campaign Order' mod='ps_gtm_datalayer'}
        </span>
      {else}
        <span class="badge badge-secondary" style="background-color: #64748b; color: white; padding: 5px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">
          {l s='Direct / Organic Visit' mod='ps_gtm_datalayer'}
        </span>
      {/if}
    </div>
  </div>

  <div class="card-body p-0" style="padding: 0; background: #ffffff;">
    <div class="table-responsive" style="overflow-x: auto; margin: 0;">
      <table class="table table-striped table-hover mb-0 ps-gtm-table" style="width: 100%; font-size: 13px; border-collapse: collapse; margin-bottom: 0;">
        <thead style="background-color: #f1f5f9; color: #475569; font-weight: 600; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">
          <tr>
            <th style="padding: 10px 16px; border: none; width: 140px;">{l s='Platform' mod='ps_gtm_datalayer'}</th>
            <th style="padding: 10px 16px; border: none; width: 130px;">{l s='Parameter' mod='ps_gtm_datalayer'}</th>
            <th style="padding: 10px 16px; border: none; width: 150px;">{l s='Type' mod='ps_gtm_datalayer'}</th>
            <th style="padding: 10px 16px; border: none;">{l s='Captured Value' mod='ps_gtm_datalayer'}</th>
            <th style="padding: 10px 16px; border: none; width: 110px; text-align: center;">{l s='sGTM Sync' mod='ps_gtm_datalayer'}</th>
          </tr>
        </thead>
        <tbody>
          {if empty($attribution) || (empty($attribution.gclid) && empty($attribution.fbclid) && empty($attribution.ttclid) && empty($attribution.msclkid) && empty($attribution.fbp) && empty($attribution.ga_client_id) && empty($attribution.utm_source))}
            <tr>
              <td colspan="5" style="padding: 24px 18px; text-align: center; color: #64748b; font-style: italic; background: #ffffff;">
                <i class="material-icons" style="font-size: 20px; vertical-align: middle; color: #94a3b8;">info</i>
                {l s='No advertising click identifiers or paid campaign parameters were captured for this order session (Direct / Organic Traffic).' mod='ps_gtm_datalayer'}
              </td>
            </tr>
          {else}
            {if !empty($attribution.gclid)}
              <tr>
                <td style="padding: 10px 16px; font-weight: 600; color: #1e293b;"><span class="badge" style="background:#ea4335;color:white;font-size:10px;">Google Ads</span></td>
                <td style="padding: 10px 16px; font-family: monospace; font-weight: 600; color: #2563eb;">gclid</td>
                <td style="padding: 10px 16px; color: #64748b; font-size: 12px;">Priority 1 Click ID</td>
                <td style="padding: 10px 16px; font-family: monospace; font-size: 12px; word-break: break-all; color: #0f172a;">{$attribution.gclid|escape:'html':'UTF-8'}</td>
                <td style="padding: 10px 16px; text-align: center;"><span class="badge badge-success" style="background:#10b981;color:white;font-size:10px;">Ready</span></td>
              </tr>
            {/if}
            {if !empty($attribution.gbraid)}
              <tr>
                <td style="padding: 10px 16px; font-weight: 600; color: #1e293b;"><span class="badge" style="background:#ea4335;color:white;font-size:10px;">Google Ads</span></td>
                <td style="padding: 10px 16px; font-family: monospace; font-weight: 600; color: #2563eb;">gbraid</td>
                <td style="padding: 10px 16px; color: #64748b; font-size: 12px;">App-to-Web Click ID</td>
                <td style="padding: 10px 16px; font-family: monospace; font-size: 12px; word-break: break-all; color: #0f172a;">{$attribution.gbraid|escape:'html':'UTF-8'}</td>
                <td style="padding: 10px 16px; text-align: center;"><span class="badge badge-success" style="background:#10b981;color:white;font-size:10px;">Ready</span></td>
              </tr>
            {/if}
            {if !empty($attribution.wbraid)}
              <tr>
                <td style="padding: 10px 16px; font-weight: 600; color: #1e293b;"><span class="badge" style="background:#ea4335;color:white;font-size:10px;">Google Ads</span></td>
                <td style="padding: 10px 16px; font-family: monospace; font-weight: 600; color: #2563eb;">wbraid</td>
                <td style="padding: 10px 16px; color: #64748b; font-size: 12px;">Web-to-Web Click ID</td>
                <td style="padding: 10px 16px; font-family: monospace; font-size: 12px; word-break: break-all; color: #0f172a;">{$attribution.wbraid|escape:'html':'UTF-8'}</td>
                <td style="padding: 10px 16px; text-align: center;"><span class="badge badge-success" style="background:#10b981;color:white;font-size:10px;">Ready</span></td>
              </tr>
            {/if}
            {if !empty($attribution.fbclid)}
              <tr>
                <td style="padding: 10px 16px; font-weight: 600; color: #1e293b;"><span class="badge" style="background:#1877f2;color:white;font-size:10px;">Meta Ads</span></td>
                <td style="padding: 10px 16px; font-family: monospace; font-weight: 600; color: #2563eb;">fbclid</td>
                <td style="padding: 10px 16px; color: #64748b; font-size: 12px;">Priority 1 Click ID</td>
                <td style="padding: 10px 16px; font-family: monospace; font-size: 12px; word-break: break-all; color: #0f172a;">{$attribution.fbclid|escape:'html':'UTF-8'}</td>
                <td style="padding: 10px 16px; text-align: center;"><span class="badge badge-success" style="background:#10b981;color:white;font-size:10px;">Ready</span></td>
              </tr>
            {/if}
            {if !empty($attribution.ttclid)}
              <tr>
                <td style="padding: 10px 16px; font-weight: 600; color: #1e293b;"><span class="badge" style="background:#000000;color:white;font-size:10px;">TikTok Ads</span></td>
                <td style="padding: 10px 16px; font-family: monospace; font-weight: 600; color: #2563eb;">ttclid</td>
                <td style="padding: 10px 16px; color: #64748b; font-size: 12px;">Priority 1 Click ID</td>
                <td style="padding: 10px 16px; font-family: monospace; font-size: 12px; word-break: break-all; color: #0f172a;">{$attribution.ttclid|escape:'html':'UTF-8'}</td>
                <td style="padding: 10px 16px; text-align: center;"><span class="badge badge-success" style="background:#10b981;color:white;font-size:10px;">Ready</span></td>
              </tr>
            {/if}
            {if !empty($attribution.msclkid)}
              <tr>
                <td style="padding: 10px 16px; font-weight: 600; color: #1e293b;"><span class="badge" style="background:#00897b;color:white;font-size:10px;">Microsoft Ads</span></td>
                <td style="padding: 10px 16px; font-family: monospace; font-weight: 600; color: #2563eb;">msclkid</td>
                <td style="padding: 10px 16px; color: #64748b; font-size: 12px;">Priority 1 Click ID</td>
                <td style="padding: 10px 16px; font-family: monospace; font-size: 12px; word-break: break-all; color: #0f172a;">{$attribution.msclkid|escape:'html':'UTF-8'}</td>
                <td style="padding: 10px 16px; text-align: center;"><span class="badge badge-success" style="background:#10b981;color:white;font-size:10px;">Ready</span></td>
              </tr>
            {/if}
            {if !empty($attribution.sccid)}
              <tr>
                <td style="padding: 10px 16px; font-weight: 600; color: #1e293b;"><span class="badge" style="background:#fffc00;color:black;font-size:10px;">Snapchat Ads</span></td>
                <td style="padding: 10px 16px; font-family: monospace; font-weight: 600; color: #2563eb;">ScCid</td>
                <td style="padding: 10px 16px; color: #64748b; font-size: 12px;">Priority 1 Click ID</td>
                <td style="padding: 10px 16px; font-family: monospace; font-size: 12px; word-break: break-all; color: #0f172a;">{$attribution.sccid|escape:'html':'UTF-8'}</td>
                <td style="padding: 10px 16px; text-align: center;"><span class="badge badge-success" style="background:#10b981;color:white;font-size:10px;">Ready</span></td>
              </tr>
            {/if}
            {if !empty($attribution.oppref)}
              <tr>
                <td style="padding: 10px 16px; font-weight: 600; color: #1e293b;"><span class="badge" style="background:#10a37f;color:white;font-size:10px;">OpenAI / ChatGPT</span></td>
                <td style="padding: 10px 16px; font-family: monospace; font-weight: 600; color: #2563eb;">oppref</td>
                <td style="padding: 10px 16px; color: #64748b; font-size: 12px;">Search Ad Referral</td>
                <td style="padding: 10px 16px; font-family: monospace; font-size: 12px; word-break: break-all; color: #0f172a;">{$attribution.oppref|escape:'html':'UTF-8'}</td>
                <td style="padding: 10px 16px; text-align: center;"><span class="badge badge-success" style="background:#10b981;color:white;font-size:10px;">Ready</span></td>
              </tr>
            {/if}
            {if !empty($attribution.fbp)}
              <tr>
                <td style="padding: 10px 16px; font-weight: 600; color: #1e293b;"><span class="badge" style="background:#1877f2;color:white;font-size:10px;">Meta Pixel</span></td>
                <td style="padding: 10px 16px; font-family: monospace; font-weight: 600; color: #2563eb;">_fbp</td>
                <td style="padding: 10px 16px; color: #64748b; font-size: 12px;">Browser Identifier Cookie</td>
                <td style="padding: 10px 16px; font-family: monospace; font-size: 12px; word-break: break-all; color: #0f172a;">{$attribution.fbp|escape:'html':'UTF-8'}</td>
                <td style="padding: 10px 16px; text-align: center;"><span class="badge badge-success" style="background:#10b981;color:white;font-size:10px;">Ready</span></td>
              </tr>
            {/if}
            {if !empty($attribution.fbc)}
              <tr>
                <td style="padding: 10px 16px; font-weight: 600; color: #1e293b;"><span class="badge" style="background:#1877f2;color:white;font-size:10px;">Meta Pixel</span></td>
                <td style="padding: 10px 16px; font-family: monospace; font-weight: 600; color: #2563eb;">_fbc</td>
                <td style="padding: 10px 16px; color: #64748b; font-size: 12px;">Click Attribution Cookie</td>
                <td style="padding: 10px 16px; font-family: monospace; font-size: 12px; word-break: break-all; color: #0f172a;">{$attribution.fbc|escape:'html':'UTF-8'}</td>
                <td style="padding: 10px 16px; text-align: center;"><span class="badge badge-success" style="background:#10b981;color:white;font-size:10px;">Ready</span></td>
              </tr>
            {/if}
            {if !empty($attribution.ga_client_id)}
              <tr>
                <td style="padding: 10px 16px; font-weight: 600; color: #1e293b;"><span class="badge" style="background:#f59e0b;color:white;font-size:10px;">Google Analytics 4</span></td>
                <td style="padding: 10px 16px; font-family: monospace; font-weight: 600; color: #2563eb;">client_id</td>
                <td style="padding: 10px 16px; color: #64748b; font-size: 12px;">GA4 Client ID</td>
                <td style="padding: 10px 16px; font-family: monospace; font-size: 12px; word-break: break-all; color: #0f172a;">{$attribution.ga_client_id|escape:'html':'UTF-8'}</td>
                <td style="padding: 10px 16px; text-align: center;"><span class="badge badge-success" style="background:#10b981;color:white;font-size:10px;">Ready</span></td>
              </tr>
            {/if}
            {if !empty($attribution.ga_session_id)}
              <tr>
                <td style="padding: 10px 16px; font-weight: 600; color: #1e293b;"><span class="badge" style="background:#f59e0b;color:white;font-size:10px;">Google Analytics 4</span></td>
                <td style="padding: 10px 16px; font-family: monospace; font-weight: 600; color: #2563eb;">session_id</td>
                <td style="padding: 10px 16px; color: #64748b; font-size: 12px;">GA4 Session Container</td>
                <td style="padding: 10px 16px; font-family: monospace; font-size: 12px; word-break: break-all; color: #0f172a;">{$attribution.ga_session_id|escape:'html':'UTF-8'}</td>
                <td style="padding: 10px 16px; text-align: center;"><span class="badge badge-success" style="background:#10b981;color:white;font-size:10px;">Ready</span></td>
              </tr>
            {/if}
            {if !empty($attribution.utm_source) || !empty($attribution.utm_campaign) || !empty($attribution.utm_medium)}
              <tr>
                <td style="padding: 10px 16px; font-weight: 600; color: #1e293b;"><span class="badge" style="background:#6366f1;color:white;font-size:10px;">Campaign (UTM)</span></td>
                <td style="padding: 10px 16px; font-family: monospace; font-weight: 600; color: #2563eb;">utm_campaign</td>
                <td style="padding: 10px 16px; color: #64748b; font-size: 12px;">Campaign Metadata</td>
                <td style="padding: 10px 16px; font-family: monospace; font-size: 12px; word-break: break-all; color: #0f172a;">
                  {if !empty($attribution.utm_source)}source={$attribution.utm_source|escape:'html':'UTF-8'}&nbsp;|&nbsp;{/if}
                  {if !empty($attribution.utm_medium)}medium={$attribution.utm_medium|escape:'html':'UTF-8'}&nbsp;|&nbsp;{/if}
                  {if !empty($attribution.utm_campaign)}campaign={$attribution.utm_campaign|escape:'html':'UTF-8'}{/if}
                </td>
                <td style="padding: 10px 16px; text-align: center;"><span class="badge badge-success" style="background:#10b981;color:white;font-size:10px;">Ready</span></td>
              </tr>
            {/if}
            {if !empty($attribution.customer_ip)}
              <tr>
                <td style="padding: 10px 16px; font-weight: 600; color: #1e293b;"><span class="badge" style="background:#64748b;color:white;font-size:10px;">Network</span></td>
                <td style="padding: 10px 16px; font-family: monospace; font-weight: 600; color: #2563eb;">customer_ip</td>
                <td style="padding: 10px 16px; color: #64748b; font-size: 12px;">Original Visitor IP</td>
                <td style="padding: 10px 16px; font-family: monospace; font-size: 12px; word-break: break-all; color: #0f172a;">{$attribution.customer_ip|escape:'html':'UTF-8'}</td>
                <td style="padding: 10px 16px; text-align: center;"><span class="badge badge-success" style="background:#10b981;color:white;font-size:10px;">Ready</span></td>
              </tr>
            {/if}
          {/if}
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer" style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 10px 18px; font-size: 11px; color: #64748b; display: flex; justify-content: space-between; align-items: center;">
    <span><i class="material-icons" style="font-size: 13px; vertical-align: middle;">sync</i> {l s='Automatically rehydrated into sGTM server webhooks during order status changes & refunds.' mod='ps_gtm_datalayer'}</span>
    {if !empty($attribution.date_add)}
      <span>{l s='Captured on:' mod='ps_gtm_datalayer'} {$attribution.date_add|escape:'html':'UTF-8'}</span>
    {/if}
  </div>
</div>
