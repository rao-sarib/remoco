<?php
/**
 * Offset pagination shared by the task and employee list views.
 *
 * Paging keeps the response size flat as the number of tasks grows, rather than
 * rendering every row into the DOM in one go.
 *
 * The list views are loaded into a dashboard shell over AJAX, so the page links
 * re-fetch the panel rather than navigating the whole document. When a list is
 * opened directly - with no shell around it - the same links fall back to normal
 * navigation.
 */

/**
 * Work out the current page from the query string and clamp it to the real range.
 *
 * @return array{page:int,pages:int,per_page:int,total:int,offset:int,param:string}
 */
function paginate(int $total, int $perPage = 25, string $param = 'p'): array
{
    $perPage = max(1, $perPage);
    $pages   = max(1, (int) ceil($total / $perPage));

    $page = isset($_GET[$param]) ? (int) $_GET[$param] : 1;
    if ($page < 1) {
        $page = 1;
    }
    if ($page > $pages) {
        $page = $pages;
    }

    return [
        'page'     => $page,
        'pages'    => $pages,
        'per_page' => $perPage,
        'total'    => $total,
        'offset'   => ($page - 1) * $perPage,
        'param'    => $param,
    ];
}

/**
 * Render the page controls. $endpoint is the panel script, e.g. "pm_tasks.php".
 * Nothing is emitted when everything already fits on one page.
 */
function pagination_controls(array $p, string $endpoint): string
{
    if ($p['pages'] <= 1) {
        return '';
    }

    $link = function (int $page, string $label, bool $disabled = false, bool $current = false) use ($endpoint, $p) {
        $classes = 'pg-link' . ($disabled ? ' pg-disabled' : '') . ($current ? ' pg-current' : '');
        $url     = htmlspecialchars($endpoint . '?' . $p['param'] . '=' . $page, ENT_QUOTES, 'UTF-8');
        if ($disabled || $current) {
            return '<span class="' . $classes . '">' . $label . '</span>';
        }
        return '<a class="' . $classes . '" href="' . $url . '" data-pg-url="' . $url . '">' . $label . '</a>';
    };

    // A short window around the current page keeps the control compact.
    $from = max(1, $p['page'] - 2);
    $to   = min($p['pages'], $p['page'] + 2);

    $out  = '<nav class="pg" aria-label="Pagination">';
    $out .= '<span class="pg-summary">Showing '
          . (($p['offset'] + 1) . '&ndash;' . min($p['offset'] + $p['per_page'], $p['total']))
          . ' of ' . $p['total'] . '</span>';
    $out .= '<span class="pg-links">';
    $out .= $link(max(1, $p['page'] - 1), '&laquo; Prev', $p['page'] <= 1);

    if ($from > 1) {
        $out .= $link(1, '1');
        if ($from > 2) {
            $out .= '<span class="pg-gap">&hellip;</span>';
        }
    }
    for ($i = $from; $i <= $to; $i++) {
        $out .= $link($i, (string) $i, false, $i === $p['page']);
    }
    if ($to < $p['pages']) {
        if ($to < $p['pages'] - 1) {
            $out .= '<span class="pg-gap">&hellip;</span>';
        }
        $out .= $link($p['pages'], (string) $p['pages']);
    }

    $out .= $link(min($p['pages'], $p['page'] + 1), 'Next &raquo;', $p['page'] >= $p['pages']);
    $out .= '</span></nav>';

    return $out;
}

/**
 * Styles and click handling, emitted at most once per request.
 */
function pagination_assets(): string
{
    static $done = false;
    if ($done) {
        return '';
    }
    $done = true;

    return <<<'HTML'
<style>
    .pg { display: flex; align-items: center; justify-content: space-between; gap: 14px;
          flex-wrap: wrap; padding: 14px 4px 4px; font-size: 0.9rem;
          font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .pg-summary { color: #64748b; }
    .pg-links { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
    .pg-link { display: inline-block; min-width: 34px; text-align: center; padding: 6px 10px;
               border: 1px solid #d1d5db; border-radius: 8px; color: #2563eb;
               text-decoration: none; background: #fff; }
    a.pg-link:hover { background: #eff6ff; border-color: #2563eb; }
    .pg-current { background: #2563eb; border-color: #2563eb; color: #fff; font-weight: 600; }
    .pg-disabled { color: #cbd5e1; background: #f8fafc; }
    .pg-gap { color: #94a3b8; padding: 0 2px; }
</style>
<script>
(function () {
    // Bind once, even if several paged panels are loaded during a session.
    if (window.__remocoPaginationBound) { return; }
    window.__remocoPaginationBound = true;

    document.addEventListener('click', function (event) {
        var link = event.target.closest ? event.target.closest('a.pg-link[data-pg-url]') : null;
        if (!link) { return; }

        var container = document.getElementById('content-container');
        // No shell on the page: let the browser navigate as normal.
        if (!container || typeof window.jQuery === 'undefined') { return; }

        event.preventDefault();
        var url = link.getAttribute('data-pg-url');
        window.jQuery.ajax({
            url: url,
            type: 'GET',
            success: function (data) { window.jQuery(container).html(data); },
            error: function () {
                container.textContent = 'Could not load that page. Please try again.';
            }
        });
    });
})();
</script>
HTML;
}
