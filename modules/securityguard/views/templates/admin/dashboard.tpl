{*
 * SecurityGuard – Dashboard (KPIs + realtime feed)
 * Compatible with PrestaShop 1.7 / 8
 *
 * Smarty variables provided by getContent():
 *   $stats        – array(total_today, total_blocked, total_patterns)
 *   $blocked_ips  – array of blocked-IP rows
 *   $module_dir   – URI path to the module root (with trailing slash)
 *   $ajax_url     – full URL to ajax/realtime.php?token=...
 *   $sg_enabled   – int (0/1)
 *   $sg_debug     – int (0/1)
 *}

{* ── Inject JS globals; use |intval / |escape to avoid Smarty notices ── *}
<script>
  var SG_AJAX_URL = {$ajax_url|default:''|json_encode};
  var SG_DEBUG    = {if $sg_debug|default:0}true{else}false{/if};
</script>

{* ── Module JS (always) ── *}
<script src="{$module_dir|default:''|escape:'htmlall':'UTF-8'}views/js/dashboard.js"></script>

{* ── Debug JS (only when debug mode is on) ── *}
{if $sg_debug|default:0}
<script src="{$module_dir|default:''|escape:'htmlall':'UTF-8'}views/js/debug.js"></script>
{/if}

{* ═══════════════════════════════════════════════════════════
   DASHBOARD CARD
   ═══════════════════════════════════════════════════════════ *}
<div class="panel" style="margin-top:20px;">
  <div class="panel-heading">
    <i class="icon-shield"></i>
    {l s='SecurityGuard – Monitoring Dashboard' mod='securityguard'}
    <span id="sg-server-time" style="font-size:11px;color:#888;margin-left:10px;"></span>
  </div>

  <div class="panel-body">

    {* ── KPI row ── *}
    <div class="row" style="margin-bottom:20px;">

      <div class="col-xs-6 col-sm-3">
        <div class="panel widget-stats" style="text-align:center;padding:15px;">
          <div style="font-size:28px;font-weight:bold;color:#e74c3c;" id="sg-kpi-today">
            {$stats.total_today|default:0|intval}
          </div>
          <div style="color:#888;font-size:12px;">
            {l s='Attacks today' mod='securityguard'}
          </div>
        </div>
      </div>

      <div class="col-xs-6 col-sm-3">
        <div class="panel widget-stats" style="text-align:center;padding:15px;">
          <div style="font-size:28px;font-weight:bold;color:#e67e22;" id="sg-kpi-blocked">
            {$stats.total_blocked|default:0|intval}
          </div>
          <div style="color:#888;font-size:12px;">
            {l s='Blocked IPs' mod='securityguard'}
          </div>
        </div>
      </div>

      <div class="col-xs-6 col-sm-3">
        <div class="panel widget-stats" style="text-align:center;padding:15px;">
          <div style="font-size:28px;font-weight:bold;color:#3498db;" id="sg-kpi-patterns">
            {$stats.total_patterns|default:0|intval}
          </div>
          <div style="color:#888;font-size:12px;">
            {l s='Active patterns' mod='securityguard'}
          </div>
        </div>
      </div>

      <div class="col-xs-6 col-sm-3">
        <div class="panel widget-stats" style="text-align:center;padding:15px;">
          <div style="font-size:28px;font-weight:bold;color:#9b59b6;" id="sg-kpi-last-minute">
            0
          </div>
          <div style="color:#888;font-size:12px;">
            {l s='Attacks last minute' mod='securityguard'}
          </div>
        </div>
      </div>

    </div>{* /row *}

    {* ── Realtime event feed ── *}
    <h4>{l s='Live event feed' mod='securityguard'} <small style="font-weight:normal;font-size:11px;">{l s='(updates every 5 s)' mod='securityguard'}</small></h4>
    <div id="sg-realtime-feed"
         style="background:#1a1a2e;color:#a8d8ea;font-family:monospace;font-size:12px;
                padding:12px;border-radius:4px;height:220px;overflow-y:auto;
                white-space:pre-wrap;word-break:break-all;">
      {l s='Waiting for events…' mod='securityguard'}
    </div>

    {* ── Blocked IPs table ── *}
    {if $blocked_ips|default:[] && $blocked_ips|count > 0}
    <h4 style="margin-top:20px;">{l s='Currently blocked IPs' mod='securityguard'}</h4>
    <table class="table tableDnD">
      <thead>
        <tr>
          <th>{l s='IP' mod='securityguard'}</th>
          <th>{l s='Reason' mod='securityguard'}</th>
          <th>{l s='Attacks' mod='securityguard'}</th>
          <th>{l s='Expires at' mod='securityguard'}</th>
        </tr>
      </thead>
      <tbody>
        {foreach from=$blocked_ips item=row}
        <tr>
          <td>{$row.ip|default:''|escape:'htmlall':'UTF-8'}</td>
          <td>{$row.reason|default:''|escape:'htmlall':'UTF-8'}</td>
          <td>{$row.attack_count|default:0|intval}</td>
          <td>{$row.expires_at|default:'never'|escape:'htmlall':'UTF-8'}</td>
        </tr>
        {/foreach}
      </tbody>
    </table>
    {/if}

    {* ── Debug panel (only when sg_debug = 1) ── *}
    {if $sg_debug|default:0}
    <div id="sg-debug-panel"
         style="margin-top:20px;background:#fff3cd;border:1px solid #ffc107;
                border-radius:4px;padding:15px;">
      <h4 style="margin-top:0;color:#856404;">{l s='Debug panel' mod='securityguard'}</h4>
      <table class="table" style="font-size:12px;">
        <tr>
          <th style="width:180px;">{l s='SG_AJAX_URL' mod='securityguard'}</th>
          <td><code id="sg-debug-ajax-url" style="word-break:break-all;"></code></td>
        </tr>
        <tr>
          <th>{l s='dashboard.js loaded' mod='securityguard'}</th>
          <td><span id="sg-debug-js-loaded" style="color:red;">not yet</span></td>
        </tr>
        <tr>
          <th>{l s='Last AJAX response' mod='securityguard'}</th>
          <td><pre id="sg-debug-last-response" style="max-height:120px;overflow:auto;margin:0;font-size:11px;"></pre></td>
        </tr>
        <tr>
          <th>{l s='Log' mod='securityguard'}</th>
          <td><pre id="sg-debug-log" style="max-height:120px;overflow:auto;margin:0;font-size:11px;color:#555;"></pre></td>
        </tr>
      </table>
    </div>
    {/if}

  </div>{* /panel-body *}
</div>{* /panel *}
