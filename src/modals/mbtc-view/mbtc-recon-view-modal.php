<?php
// METROBANK HEAD OFFICE Recon View Modal
// Displays per-day partner vs web rows (reference, principal, commission, date)
?>
<link rel="stylesheet" href="/autorecon/src/modals/mbtc-view/mbtc-recon-view-modal.css">

<div class="mbtc-recon-modal" id="mbtcReconViewModal" style="display:none;" role="dialog" aria-modal="true" aria-label="METROBANK HEAD OFFICE Recon Details">
    <div class="mbtc-recon-modal__panel">
        <div class="mbtc-recon-modal__head">
            <h3>METROBANK HEAD OFFICE Recon Details</h3>
            <button type="button" class="mbtc-recon-modal__close" data-action="close-mbtc-recon" aria-label="Close">CLOSE</button>
        </div>

        <div class="mbtc-recon-modal__top">
            <div style="display:flex;flex-direction:column;gap:8px">
                <p class="mbtc-recon-modal__summary" data-role="summary">Matched: 0 | Not Matched: 0</p>
            </div>

            <div class="mbtc-recon-modal__controls">
                <label class="cmp-control-search"><input data-role="resultSearch" type="search" placeholder="Search"></label>
                <label class="cmp-control-filter">Show: <span class="select-wrap"><select class="custom-select" data-role="resultFilter"><option value="all">All</option><option value="mismatch">Mismatch Only</option><option value="duplicates">Duplicates Only</option></select></span></label>
            </div>

                
        </div>

        <div class="mbtc-recon-modal__tables" data-role="globalScroll">
            <section>
                <h4>Partners Data <span data-role="partnersCount" class="comparison-count">(0)</span></h4>
                <div class="mbtc-section-metrics">
                    <div data-role="partnersVolume">Volume: 0</div>
                    <div data-role="partnersPrincipal">Principal: 0.00 pesos</div>
                </div>
                <table>
                    <thead data-role="partnersHead">
                        <tr>
                            <th>Reference</th>
                            <th>Date</th>
                            <th>PHP</th>
                            <th>in PHP</th>
                        </tr>
                    </thead>
                    <tbody data-role="partnersBody"></tbody>
                </table>
            </section>

            <section>
                <h4>Web Data <span data-role="webCount" class="comparison-count">(0)</span></h4>
                <div class="mbtc-section-metrics">
                    <div data-role="webVolume">Volume: 0</div>
                    <div data-role="webPrincipal">Principal: 0.00 pesos</div>
                </div>
                <table>
                    <thead data-role="webHead">
                        <tr>
                            <th>CCREF</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>CTP</th>
                        </tr>
                    </thead>
                    <tbody data-role="webBody"></tbody>
                </table>
            </section>
        </div>

        <div class="mbtc-recon-modal__loading" style="display:none;" aria-hidden="true">
            <div class="mbtc-recon-modal__loader">Loading…</div>
        </div>

    </div>
</div>

<!-- Styles are kept in mbtc-recon-view-modal.css to keep markup clean -->
 
