<?php
// SKYBRIDGE PAYMENT INC. Reconciliation View Modal
// Displays per-day partner vs KPX web rows.
?>
<link rel="stylesheet" href="/autorecon/src/modals/skybridgepaymentinc-view/skybridgepaymentinc-recon-view-modal.css">

<div class="moneygram-recon-modal skybridgepaymentinc-recon-modal" id="skybridgepaymentincReconViewModal" style="display:none;" role="dialog" aria-modal="true" aria-label="SKYBRIDGE PAYMENT INC. Reconciliation Details">
    <div class="moneygram-recon-modal__panel skybridgepaymentinc-recon-modal__panel">
        <div class="moneygram-recon-modal__head">
            <h3>SKYBRIDGE PAYMENT INC. Reconciliation Details</h3>
            <button type="button" class="moneygram-recon-modal__close" data-action="close-skybridgepaymentinc-recon" aria-label="Close">CLOSE</button>
        </div>

        <div class="moneygram-recon-modal__top">
            <div class="moneygram-recon-modal__summary-wrap">
                <p class="moneygram-recon-modal__summary" data-role="summary">Matched: 0 | Not Matched: 0 | Duplicates: 0</p>
            </div>

            <div class="moneygram-recon-modal__controls">
                <label class="cmp-control-search"><input data-role="resultSearch" type="search" placeholder="Search"></label>
                <label class="cmp-control-filter">Show: <span class="select-wrap"><select class="custom-select" data-role="resultFilter"><option value="all">All</option><option value="matched">Match Only</option><option value="mismatch">Mismatch Only</option><option value="duplicates">Duplicates Only</option></select></span></label>
                <button id="skybridgepaymentincLockAllMatchedBtn" class="moneygram-lock-all-btn" type="button">LOCK MATCHED TRANSACTIONS</button>
            </div>
        </div>

        <div class="moneygram-recon-modal__tables" data-role="globalScroll">
            <section>
                <div class="moneygram-section-header">
                    <h4>Partners Data</h4>
                    <div class="moneygram-section-metrics">
                        <div class="moneygram-volume" data-role="partnersVolume">Volume: 0</div>
                        <div class="moneygram-principal" data-role="partnersPrincipalPhp">Principal: PHP: 0.00 USD: 0.00</div>
                        <div class="moneygram-principal" data-role="partnersPrincipalUsd" style="display:none;"></div>
                    </div>
                </div>
                <div class="moneygram-table-shell moneygram-table-shell--partners">
                    <table class="moneygram-table moneygram-table--partners moneygram-table--head skybridgepaymentinc-table--partners">
                        <colgroup>
                            <col class="moneygram-col-date">
                            <col class="moneygram-col-ref">
                            <col class="moneygram-col-amount">
                            <col class="moneygram-col-currency">
                        </colgroup>
                        <thead data-role="partnersHead">
                            <tr>
                                <th>Date</th>
                                <th>Control No</th>
                                <th>Amount</th>
                                <th>PHP</th>
                            </tr>
                        </thead>
                    </table>
                    <div class="moneygram-scroll-lock-header" aria-hidden="true">&#128274;</div>
                    <div class="moneygram-table-body-scroll" data-role="partnersScroll">
                        <table class="moneygram-table moneygram-table--partners moneygram-table--body skybridgepaymentinc-table--partners">
                            <colgroup>
                                <col class="moneygram-col-date">
                                <col class="moneygram-col-ref">
                                <col class="moneygram-col-amount">
                                <col class="moneygram-col-currency">
                            </colgroup>
                            <tbody data-role="partnersBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section>
                <div class="moneygram-section-header">
                    <h4>KPX Web Data</h4>
                    <div class="moneygram-section-metrics">
                        <div class="moneygram-volume" data-role="webVolume">Volume: 0</div>
                        <div class="moneygram-principal" data-role="webPrincipalPhp">Principal: PHP: 0.00 USD: 0.00</div>
                        <div class="moneygram-principal" data-role="webPrincipalUsd" style="display:none;"></div>
                    </div>
                </div>
                <div class="moneygram-table-shell moneygram-table-shell--web">
                    <table class="moneygram-table moneygram-table--web moneygram-table--head">
                        <colgroup>
                            <col class="moneygram-col-date">
                            <col class="moneygram-col-kptn">
                            <col class="moneygram-col-ref">
                            <col class="moneygram-col-amount">
                            <col class="moneygram-col-currency">
                        </colgroup>
                        <thead data-role="webHead">
                            <tr>
                                <th>Date</th>
                                <th>KPTN</th>
                                <th>CCREF NO</th>
                                <th>Amount</th>
                                <th>CURRENCY</th>
                            </tr>
                        </thead>
                    </table>
                    <div class="moneygram-table-body-scroll" data-role="webScroll">
                        <table class="moneygram-table moneygram-table--web moneygram-table--body">
                            <colgroup>
                                <col class="moneygram-col-date">
                                <col class="moneygram-col-kptn">
                                <col class="moneygram-col-ref">
                                <col class="moneygram-col-amount">
                                <col class="moneygram-col-currency">
                            </colgroup>
                            <tbody data-role="webBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <div class="moneygram-recon-modal__loading" style="display:none;" aria-hidden="true">
            <div class="moneygram-recon-modal__loader">Loading...</div>
        </div>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('skybridgepaymentincReconViewModal');
    if (!modal || modal.dataset.scrollSyncBound === 'true') return;

    const partnersScroll = modal.querySelector('[data-role="partnersScroll"]');
    const webScroll = modal.querySelector('[data-role="webScroll"]');
    if (!partnersScroll || !webScroll) return;

    let syncingSource = null;
    const syncScroll = function (source, target) {
        if (syncingSource && syncingSource !== source) return;
        syncingSource = source;
        target.scrollTop = source.scrollTop;
        window.requestAnimationFrame(function () {
            if (syncingSource === source) syncingSource = null;
        });
    };

    partnersScroll.addEventListener('scroll', function () {
        syncScroll(partnersScroll, webScroll);
    }, { passive: true });

    webScroll.addEventListener('scroll', function () {
        syncScroll(webScroll, partnersScroll);
    }, { passive: true });

    partnersScroll.addEventListener('wheel', function (event) {
        if (!event.deltaY) return;
        event.preventDefault();
        webScroll.scrollTop += event.deltaY;
    }, { passive: false });

    modal.dataset.scrollSyncBound = 'true';
})();
</script>
