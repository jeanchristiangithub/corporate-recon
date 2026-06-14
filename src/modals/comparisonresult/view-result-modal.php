<div class="comparison-modal" id="comparisonResultModal" role="dialog" aria-modal="true" aria-label="Comparison Result">
    <div class="comparison-modal__panel">
        <div class="comparison-modal__head">
            <h3>Comparison Result</h3>
            <button type="button" class="comparison-modal__close" data-action="close-result-modal" aria-label="Close">×</button>
        </div>

        <div class="comparison-modal__top">
            <div style="display:flex;flex-direction:column;gap:8px">
                <p class="comparison-modal__summary" data-role="summary">Matched: 0 | Not Matched: 0</p>
            </div>

            <div class="comparison-modal__controls">
                <label class="cmp-control-search">Search: <input data-role="resultSearch" type="search" placeholder="Reference No. or CCREF"></label>
                <label class="cmp-control-filter">Show: <span class="select-wrap"><select class="custom-select" data-role="resultFilter"><option value="all">All</option><option value="notmatched">Not Matched Only</option></select></span></label>
            </div>
        </div>

        <div class="comparison-modal__tables" data-role="globalScroll">
            <section>
                <h4>Partners Data <span data-role="partnersCount" class="comparison-count">(0)</span></h4>
                <div class="comparison-section-metrics">
                    <div data-role="partnersVolume">Volume: 0</div>
                    <div data-role="partnersPrincipal">Principal: 0.00</div>
                </div>
                <table>
                    <thead data-role="partnersHead"></thead>
                    <tbody data-role="partnersBody"></tbody>
                </table>
            </section>

            <section>
                <h4>Web Data <span data-role="webCount" class="comparison-count">(0)</span></h4>
                <div class="comparison-section-metrics">
                    <div data-role="webVolume">Volume: 0</div>
                    <div data-role="webPrincipal">Principal: 0.00</div>
                </div>
                <table>
                    <thead data-role="webHead"></thead>
                    <tbody data-role="webBody"></tbody>
                </table>
            </section>
        </div>

<style>
.comparison-modal__top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin:6px 0;flex-wrap:wrap}
.comparison-modal__summary{margin:0;font-weight:600}
.comparison-modal__controls{display:flex;align-items:center;gap:12px}
.comparison-modal__controls .cmp-control-search input{width:220px;padding:6px;border:1px solid #ddd;border-radius:6px}
.comparison-section-metrics{padding:8px 12px 6px 12px;color:#6b7280;font-size:.95rem;line-height:1.35}
.comparison-modal__tables[data-role="globalScroll"]{max-height:60vh;overflow:auto;display:flex;gap:18px;padding-top:6px;margin-top:6px}
.comparison-modal__tables[data-role="globalScroll"] section{flex:1;min-width:0}
.comparison-modal__tables[data-role="globalScroll"] section h4 {
    position: -webkit-sticky; /* Safari */
    position: sticky;
    top: 0;
    background: #fff;
    z-index: 6;
    margin: 0;
    padding: 8px 12px;
    border-bottom: 1px solid #eee;
}
.comparison-modal__tables[data-role="globalScroll"] thead th {
    position: -webkit-sticky;
    position: sticky;
    top: 44px; /* offset below the section title */
    background: #fff;
    z-index: 5;
}
.comparison-count { font-weight: normal; font-size: 0.9em; color: #555; margin-left:8px; }
.comparison-modal__controls { margin: 0 }
</style>
    </div>
</div>
