<?php
/**
 * Shared presentation for the report panels.
 *
 * Kept in one place so the five report views (company analytics, alerts, and the
 * three role reports) stay visually consistent and the rules are not copied five
 * times. Emitted once per request - the panels are loaded individually over AJAX,
 * so the guard matters when more than one ends up on the page.
 */
if (!defined('REMOCO_REPORT_STYLES_EMITTED')) {
    define('REMOCO_REPORT_STYLES_EMITTED', true);
    ?>
<style>
    .rpt { padding: 25px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #1e293b; }
    .rpt-head h2 { font-size: 1.6rem; color: #2563eb; display: flex; align-items: center; gap: 12px; margin-bottom: 6px; }
    .rpt-head p { color: #64748b; margin-bottom: 22px; }
    .rpt-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626;
                 padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; }
    .rpt-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(160px, 100%), 1fr));
                 gap: 16px; margin-bottom: 24px; }
    .rpt-card { background: #fff; border-radius: 12px; padding: 18px 20px;
                box-shadow: 0 4px 16px rgba(0,0,0,0.06); display: flex; flex-direction: column; gap: 6px; }
    .rpt-card-label { font-size: 0.85rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.4px; }
    .rpt-card-value { font-size: 1.9rem; font-weight: 700; }
    .rpt-good { color: #10b981; }
    .rpt-bad  { color: #dc2626; }
    .rpt-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(280px, 100%), 1fr)); gap: 18px; }
    .rpt-panel { background: #fff; border-radius: 12px; padding: 20px;
                 box-shadow: 0 4px 16px rgba(0,0,0,0.06); margin-bottom: 18px; }
    .rpt-panel h3 { font-size: 1.05rem; margin-bottom: 16px; color: #1e293b; }
    .rpt-row { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
    .rpt-row-label { flex: 0 0 96px; font-size: 0.9rem; color: #475569; }
    .rpt-track { flex: 1; height: 10px; background: #e2e8f0; border-radius: 999px; overflow: hidden; }
    .rpt-fill { display: block; height: 100%; border-radius: 999px; }
    .rpt-row-value { flex: 0 0 40px; text-align: right; font-weight: 600; }
    .rpt-table { width: 100%; border-collapse: collapse; font-size: 0.92rem; }
    .rpt-table th, .rpt-table td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; text-align: left; }
    .rpt-table th { color: #475569; font-weight: 600; }
    .rpt-num { text-align: right; }
    .rpt-scroll { width: 100%; overflow-x: auto; }
    .rpt-empty { color: #64748b; text-align: center; padding: 30px 0; }
    .rpt-empty i { display: block; font-size: 1.8rem; margin-bottom: 10px; color: #cbd5e1; }
    .rpt-pill { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 0.8rem; font-weight: 600; }
    .rpt-pill-notstarted { background: #e2e8f0; color: #334155; }
    .rpt-pill-inprogress { background: #dbeafe; color: #1e40af; }
    .rpt-pill-completed  { background: #d1fae5; color: #065f46; }
</style>
    <?php
}
