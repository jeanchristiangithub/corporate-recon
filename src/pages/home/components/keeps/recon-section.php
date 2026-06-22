<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../config/csrf.php';

$csrfToken = csrfToken();
?>

<section class="recon-section" id="reconSection" data-endpoint="../../controllers/excelcontrol/test-controller.php" data-csrf-token="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <div class="recon-section__header">
        <div>
            <h2>Auto File Reconciliation System</h2>
            <p>Upload Partners Data and Web Data to validate and compare.</p>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
            <button type="button" id="clearSessionsBtn" class="material-btn" style="background:#fff;border:1px solid var(--border-soft);">Clear Sessions</button>
        </div>
    </div>
    <!-- moved component styles into recon-section.css -->

    <div class="recon-section__controls" style="margin:12px 0; display:flex; align-items:center; gap:16px; justify-content:space-between">
        <div style="display:flex; align-items:center; gap:12px">
            <label style="margin-right:12px; display:flex; align-items:center; gap:8px">Mode:
                <span class="select-wrap">
                    <select id="globalModeSelect" class="custom-select">
                        <option value="Test">Test</option>
                        <option value="KPX">KPX (Soon)</option>
                        <option value="KP7">KP7 (Soon)</option>
                    </select>
                </span>
            </label>

            <label style="display:flex; align-items:center; gap:8px">Date:
                <input type="month" id="globalMonth" value="<?= date('Y-m') ?>" style="padding:6px 8px;border-radius:8px;border:1px solid #d0d0d0;background:#fff">
            </label>
        </div>

        <div style="display:flex; align-items:center; gap:8px">
            <button type="button" id="openPartnerFetch" class="material-icon-btn" title="Partner Fetch" aria-label="Partner Fetch" style="background:transparent;border:0;cursor:pointer;padding:6px">
                <!-- user icon -->
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5zM4 20c0-3.314 2.686-6 6-6h4c3.314 0 6 2.686 6 6v1H4v-1z" fill="#c0392b"/></svg>
            </button>
            <button type="button" id="openWebFetch" class="material-icon-btn" title="Web Fetch" aria-label="Web Fetch" style="background:transparent;border:0;cursor:pointer;padding:6px">
                <!-- cloud-download icon -->
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M19.35 10.04A7 7 0 0 0 5 11h1.26A4.5 4.5 0 0 1 11 6.5 4.5 4.5 0 0 1 15.5 11H16a4 4 0 0 0 3.35-6.96A5.5 5.5 0 0 1 21.5 11H19.35zM12 13v6m0 0l-3-3m3 3l3-3" stroke="#c0392b" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        </div>
    </div>

    <div class="recon-section__cards" id="reconCards"></div>

        <?php
        // Emit initial session payloads so the client can restore cards after a refresh.
        $initial = ['batches' => []];
        if (!empty($_SESSION['excel_compare_recent_payloads']) && is_array($_SESSION['excel_compare_recent_payloads'])) {
            foreach ($_SESSION['excel_compare_recent_payloads'] as $sid => $entry) {
                $initial['batches'][] = [
                    'id' => $entry['id'] ?? $sid,
                    'mode' => $entry['mode'] ?? 'Test',
                    'date' => $entry['date'] ?? null,
                    'payload' => $entry['payload'] ?? null,
                ];
            }
        }
        ?>
        <script>
            window.__recon_initial = <?= json_encode($initial, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        </script>

    <?php
    // Include fetch-test modal fragments so they're present in the page HTML.
    include __DIR__ . '/../../../../modals/fetch-test/partnerfetch.php';
    include __DIR__ . '/../../../../modals/fetch-test/webfetch.php';
    ?>
</section>

<template id="reconCardTemplate">
    <article class="recon-card" data-status="pending" data-expanded="true">
        <button type="button" class="recon-card__head" data-action="toggle">
            <span class="recon-card__title">Day 1</span>
            <span class="recon-card__state">
                <span class="recon-card__spinner" hidden aria-hidden="true"></span>
                <span class="recon-card__icon" aria-hidden="true">•</span>
            </span>
        </button>

        <div class="recon-card__body">
            <!-- Mode is now a global control; per-card selector removed -->

            <div class="recon-card__drops">
                <div class="dropzone" data-drop="partners">
                    <input type="file" accept=".xlsx,.xlx,.xls,.xlsm,.xlsb,.ods,.csv" data-input="partners" hidden>
                    <p class="dropzone__label">Partners Data</p>
                    <p class="dropzone__hint">Drop .xlsx/.xlx/.csv here or click to upload</p>
                    <p class="dropzone__file" data-file="partners">No file selected</p>
                </div>

                <div class="dropzone" data-drop="web">
                    <input type="file" accept=".xlsx,.xlx,.xls,.xlsm,.xlsb,.ods,.csv" data-input="web" hidden>
                    <p class="dropzone__label">Web Data</p>
                    <p class="dropzone__hint">Drop .xlsx/.xlx/.csv here or click to upload</p>
                    <p class="dropzone__file" data-file="web">No file selected</p>
                </div>
            </div>
        </div>
    </article>
</template>

<script>
(() => {
    const section = document.getElementById('reconSection');
    if (!section) return;

    const endpoint = section.dataset.endpoint;
    const csrfToken = section.dataset.csrfToken;
    // Add button removed — initial card is created automatically and new cards are added when user drops files.
    const cardsWrap = document.getElementById('reconCards');
    const tpl = document.getElementById('reconCardTemplate');
    const modeSelect = document.getElementById('globalModeSelect');
    const monthInput = document.getElementById('globalMonth');

    // Fetch-test modals are included server-side (see PHP includes above)

    const state = { count: 0, batches: new Map() };

    // Restore any server-saved comparison payloads (from PHP session)
    if (window.__recon_initial && Array.isArray(window.__recon_initial.batches) && window.__recon_initial.batches.length) {
        try {
            window.__recon_initial.batches.forEach((b) => {
                // create a card DOM from template
                const fragment = tpl.content.cloneNode(true);
                const card = fragment.querySelector('.recon-card');
                const head = card.querySelector('.recon-card__head');
                const title = card.querySelector('.recon-card__title');

                // label: try to create a human title from date if present
                if (b.date) {
                    try {
                        const parts = String(b.date).split('-');
                        const yy = parts[0];
                        const mm = Number(parts[1]);
                        const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                        // use the next state count as the day number so restored cards don't show 00
                        const dayNum = String(state.count + 1).padStart(2, '0');
                        title.textContent = `${months[(mm||1)-1] || 'Month'} ${dayNum}, ${yy}`;
                    } catch (e) {
                        title.textContent = 'Restored';
                    }
                } else if (b.payload && b.payload.parsedHeaders && b.payload.parsedHeaders.partners) {
                    // fallback: create a day label based on the restored card order
                    try {
                        const dayNum = String(state.count + 1).padStart(2, '0');
                        if (monthInput && monthInput.value) {
                            const parts = monthInput.value.split('-');
                            const yy = parts[0];
                            const mm = Number(parts[1]);
                            const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                            title.textContent = `${months[Math.max(1, mm) - 1] || 'Month'} ${dayNum}, ${yy}`;
                            } else {
                            title.textContent = `Day ${state.count + 1}`;
                        }
                    } catch (e) {
                        title.textContent = `Day ${state.count}`;
                    }
                } else {
                    title.textContent = `Day ${state.count + 1}`;
                }

                const batchId = b.id || (`restored-${Date.now()}-${Math.random().toString(36).slice(2,8)}`);
                card.dataset.batchId = batchId;

                // add batch to state (finished = true)
                state.count += 1;
                state.batches.set(batchId, {
                    id: batchId,
                    mode: b.mode || 'Test',
                    date: b.date || null,
                    files: { partners: null, web: null },
                    submitting: false,
                    finished: true,
                    result: b.payload || null,
                    _retriedAfterClear: false
                });

                // set visual status (matched/unmatched)
                card.dataset.status = (b.payload && b.payload.allMatched) ? 'true' : 'false';
                card.dataset.expanded = 'true';
                const spinner = card.querySelector('.recon-card__spinner'); if (spinner) spinner.hidden = true;
                const icon = card.querySelector('.recon-card__icon'); if (icon) icon.textContent = (b.payload && b.payload.allMatched) ? '✓' : '✕';

                // attach head click handler (make restored card open the result modal)
                head.addEventListener('click', () => {
                    const batch = state.batches.get(batchId);
                    if (batch && batch.finished) {
                        showResultModal(batch.result, batch.mode);
                        return;
                    }

                    if (card.dataset.expanded === 'true') {
                        collapseCard(card);
                    } else {
                        expandCard(card);
                    }
                });

                // disable dropzones on restored cards (read-only)
                const dzs = card.querySelectorAll('.dropzone');
                dzs.forEach(z => {
                    z.classList.add('dropzone--restored');
                    // prevent interaction
                    z.style.pointerEvents = 'none';
                    z.style.opacity = '0.95';

                    // disable the file input to prevent accidental selection
                    const inputEl = z.querySelector('input[type="file"]');
                    if (inputEl) inputEl.disabled = true;

                    // hide interactive hints and file text to avoid showing upload UI
                    const hintEl = z.querySelector('.dropzone__hint');
                    const fileEl = z.querySelector('.dropzone__file');
                    if (hintEl) hintEl.style.display = 'none';
                    if (fileEl) fileEl.style.display = 'none';

                    // add a read-only message so users know to click the card header
                    if (!z.querySelector('.dropzone--restored__msg')) {
                        const msg = document.createElement('div');
                        msg.className = 'dropzone--restored__msg';
                        msg.textContent = 'Restored — click header to view results';
                        msg.style.padding = '10px 12px';
                        msg.style.color = '#114B33';
                        msg.style.fontWeight = '600';
                        msg.style.fontSize = '13px';
                        z.appendChild(msg);
                    }
                });

                // append card to DOM
                cardsWrap.appendChild(fragment);
            });
        } catch (e) {
            console.error('Failed to restore recon cards from session', e);
        }
    }

    // expose minimal API so the error modal can reset a specific card without page refresh
    window.recon = window.recon || {};
    window.recon.resetCardByBatchId = function(batchId) {
        const batch = state.batches.get(batchId);
        if (!batch) return false;
        const card = document.querySelector(`.recon-card[data-batch-id="${batchId}"]`);
        if (!card) return false;

        // reset batch state
        batch.files.partners = null;
        batch.files.web = null;
        batch.submitting = false;
        batch.finished = false;
        batch.result = null;
        batch._retriedAfterClear = false;

        // reset inputs and labels
        const partnersInput = card.querySelector('[data-input="partners"]');
        const webInput = card.querySelector('[data-input="web"]');
        const partnersText = card.querySelector('[data-file="partners"]');
        const webText = card.querySelector('[data-file="web"]');
        if (partnersInput) partnersInput.value = '';
        if (webInput) webInput.value = '';
        if (partnersText) partnersText.textContent = 'No file selected';
        if (webText) webText.textContent = 'No file selected';

        // reset UI state
        card.dataset.status = 'pending';
        card.dataset.expanded = 'true';
        const spinner = card.querySelector('.recon-card__spinner');
        const icon = card.querySelector('.recon-card__icon');
        if (spinner) spinner.hidden = true;
        if (icon) icon.textContent = '•';
        return true;
    };

    // retry comparison for a given batch id (used by the debug modal 'Compare Again' button)
    window.recon.retryComparison = function(batchId) {
        const batch = state.batches.get(batchId);
        if (!batch) return false;
        const card = document.querySelector(`.recon-card[data-batch-id="${batchId}"]`);
        if (!card) return false;
        if (batch.submitting) return false;

        // allow one retry after a server-side clear
        batch._retriedAfterClear = false;
        // kick off the comparison (runComparison is in scope)
        try {
            runComparison(card, batchId);
            return true;
        } catch (e) {
            console.error('retryComparison failed', e);
            return false;
        }
    };

    function showResultModal(payload, mode) {
        const modal = document.getElementById('comparisonResultModal');
        if (!modal) return;

        modal.classList.add('is-open');
        {
            const m = Number(payload.matchedCount || 0);
            const u = Number(payload.unmatchedCount || 0);
            const mLabel = (m === 1) ? 'transaction' : 'transactions';
            const uLabel = (u === 1) ? 'transaction' : 'transactions';
            const el = modal.querySelector('[data-role="summary"]');
            if(el) el.innerHTML = `<span class="recon-summary__item">Matched: ${m.toLocaleString()} ${mLabel}</span><span class="recon-summary__sep">|</span><span class="recon-summary__item">Not Matched: ${u.toLocaleString()} ${uLabel}</span>`;
        }

        // update extracted counts if provided by server
        const partnersCountEl = modal.querySelector('[data-role="partnersCount"]');
        const webCountEl = modal.querySelector('[data-role="webCount"]');
        if (partnersCountEl) partnersCountEl.textContent = `(${((payload.partners_count ?? (payload.debug && payload.debug.partners_rows_count) ?? 0).toLocaleString())})`;
        if (webCountEl) webCountEl.textContent = `(${((payload.web_count ?? (payload.debug && payload.debug.web_rows_count) ?? 0).toLocaleString())})`;

        const leftHead = modal.querySelector('[data-role="partnersHead"]');
        const leftBody = modal.querySelector('[data-role="partnersBody"]');
        const rightHead = modal.querySelector('[data-role="webHead"]') || null;
        const rightBody = modal.querySelector('[data-role="webBody"]');

        leftBody.innerHTML = '';
        rightBody.innerHTML = '';

        // store payload and mode on modal for interactive filtering/search
        modal._lastPayload = payload;
        modal._lastMode = mode || 'Test';

        // compute section metrics for display: Volume and Principal per side
        (function() {
            const rows = payload.rows || [];
            const parseNum = (v) => {
                if (v === undefined || v === null) return 0;
                const s = String(v).replace(/[,\s]/g, '') || '0';
                const n = Number(s);
                return Number.isFinite(n) ? n : 0;
            };
            let totalWebAmount = 0, totalPartnersPhp = 0;
            rows.forEach(r => {
                totalWebAmount += parseNum(r.web && r.web.amount);
                totalPartnersPhp += parseNum(r.partners && r.partners.php);
            });
            const fmt = (n) => Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const partnersVolumeEl = modal.querySelector('[data-role="partnersVolume"]');
            const webVolumeEl = modal.querySelector('[data-role="webVolume"]');
            const partnersPrincipalEl = modal.querySelector('[data-role="partnersPrincipal"]');
            const partnersPrincipalPhpEl = modal.querySelector('[data-role="partnersPrincipalPhp"]');
            const partnersPrincipalUsdEl = modal.querySelector('[data-role="partnersPrincipalUsd"]');
            const webPrincipalEl = modal.querySelector('[data-role="webPrincipal"]');
            const webPrincipalPhpEl = modal.querySelector('[data-role="webPrincipalPhp"]');
            const webPrincipalUsdEl = modal.querySelector('[data-role="webPrincipalUsd"]');

            if (partnersVolumeEl) partnersVolumeEl.textContent = 'Volume: ' + String(rows.length || 0);
            if (webVolumeEl) webVolumeEl.textContent = 'Volume: ' + String(rows.length || 0);
            if (partnersPrincipalPhpEl) partnersPrincipalPhpEl.textContent = 'Principal PHP: ' + fmt(totalPartnersPhp) + ' pesos';
            if (partnersPrincipalUsdEl) partnersPrincipalUsdEl.textContent = 'Principal USD: ' + fmt(0);
            if (partnersPrincipalEl) partnersPrincipalEl.textContent = 'Principal: ' + fmt(totalPartnersPhp);
            if (webPrincipalUsdEl) webPrincipalUsdEl.textContent = 'Principal USD: ' + fmt(totalWebAmount);
            if (webPrincipalEl) webPrincipalEl.textContent = 'Principal: ' + fmt(totalWebAmount);
        })();

        // Define column mappings per mode (fallback to Test)
        const mappings = {
            Test: {
                partners: { fields: ['referenceNo', 'php', 'inPhp'], labels: ['Reference No.', 'PHP', 'in PHP'] },
                web: { fields: ['ccrefNo', 'amount', 'ctp'], labels: ['CCREF NO', 'AMOUNT', 'CTP'] }
            },
            KPX: null,
            KP7: null
        };

        const modeKey = (mode || 'Test');
        const map = mappings[modeKey] || mappings.Test;

        // populate header for partners (prepend numbering column)
        if (leftHead) {
            leftHead.innerHTML = '';
            const tr = document.createElement('tr');
            const thIndex = document.createElement('th');
            thIndex.textContent = 'No.';
            tr.appendChild(thIndex);
            map.partners.labels.forEach((lbl) => {
                const th = document.createElement('th');
                th.textContent = lbl;
                tr.appendChild(th);
            });
            leftHead.appendChild(tr);
        }

        // populate header for web (prepend numbering column)
        if (rightHead) {
            rightHead.innerHTML = '';
            const tr = document.createElement('tr');
            const thIndex = document.createElement('th');
            thIndex.textContent = 'No.';
            tr.appendChild(thIndex);
            map.web.labels.forEach((lbl) => {
                const th = document.createElement('th');
                th.textContent = lbl;
                tr.appendChild(th);
            });
            rightHead.appendChild(tr);
        }

        // ensure we use a single global scroll container
        const globalScroll = modal.querySelector('[data-role="globalScroll"]');
        if (globalScroll) {
            globalScroll.style.overflow = 'auto';
            globalScroll.style.maxHeight = globalScroll.style.maxHeight || '60vh';
        }

        // remove per-table scrolling so the global container controls it
        [leftBody, rightBody].forEach((tb) => {
            if (tb) {
                tb.style.overflow = 'visible';
                tb.style.maxHeight = 'none';
            }
        });

        // renderRows: populate table bodies according to current search/filter
        function renderRows() {
            const rows = modal._lastPayload.rows || [];
            const qEl = modal.querySelector('[data-role="resultSearch"]');
            const fEl = modal.querySelector('[data-role="resultFilter"]');
            const q = qEl && qEl.value ? String(qEl.value).trim().toLowerCase() : '';
            const filter = fEl && fEl.value ? String(fEl.value) : 'all';

            leftBody.innerHTML = '';
            rightBody.innerHTML = '';

            const filtered = rows.filter((row) => {
                if (filter === 'notmatched' && row.all === true) return false;
                if (!q) return true;
                const ref = String(row.partners.referenceNo || '').toLowerCase();
                const ccref = String(row.web.ccrefNo || '').toLowerCase();
                return ref.includes(q) || ccref.includes(q);
            });

            filtered.forEach((row, index) => {
                const trLeft = document.createElement('tr');
                const trRight = document.createElement('tr');

                // numbering column
                const tdIndexL = document.createElement('td');
                tdIndexL.textContent = String(index + 1);
                trLeft.appendChild(tdIndexL);

                map.partners.fields.forEach((field, colIndex) => {
                    const td = document.createElement('td');
                    td.textContent = row.partners[field] ?? '';
                    td.className = row.match[field] ? 'match-true' : 'match-false';
                    td.title = `${map.partners.labels[colIndex]} (${index + 1})`;
                    trLeft.appendChild(td);
                });

                // numbering column for web
                const tdIndexR = document.createElement('td');
                tdIndexR.textContent = String(index + 1);
                trRight.appendChild(tdIndexR);

                map.web.fields.forEach((field, colIndex) => {
                    const td = document.createElement('td');
                    td.textContent = row.web[field] ?? '';
                    const matchKey = field === 'ccrefNo' ? 'referenceNo' : field === 'amount' ? 'php' : field === 'ctp' ? 'inPhp' : field;
                    td.className = row.match[matchKey] ? 'match-true' : 'match-false';
                    td.title = `${map.web.labels[colIndex]} (${index + 1})`;
                    trRight.appendChild(td);
                });

                leftBody.appendChild(trLeft);
                rightBody.appendChild(trRight);
            });
        }

        // wire search/filter controls (only once)
        const searchEl = modal.querySelector('[data-role="resultSearch"]');
        const filterEl = modal.querySelector('[data-role="resultFilter"]');
        if (searchEl && !modal._listenersSet) {
            searchEl.addEventListener('input', renderRows);
        }
        if (filterEl && !modal._listenersSet) {
            filterEl.addEventListener('change', renderRows);
        }
        modal._listenersSet = true;

        // initial render
        renderRows();

        payload.rows.forEach((row, index) => {
            const trLeft = document.createElement('tr');
            const trRight = document.createElement('tr');
            // (legacy append removed — rows are rendered via renderRows)
        });
    }

    function closeResultModal() {
        const modal = document.getElementById('comparisonResultModal');
        if (!modal) return;
        modal.classList.remove('is-open');
    }

    // simple session-cleared modal with styled OK button and scoped handlers
    function showSessionClearedModal() {
        let m = document.getElementById('sessionClearedModal');
        if (!m) {
            m = document.createElement('div');
            m.id = 'sessionClearedModal';
            m.className = 'simple-modal';
            m.innerHTML = `<div class="simple-modal__panel"><div class="simple-modal__body">Sessions cleared.</div><div class="simple-modal__actions"><button id="sessionClearedOk" type="button" class="material-btn material-btn--primary">OK</button></div></div>`;
            document.body.appendChild(m);

            const okBtn = m.querySelector('#sessionClearedOk');

            function cleanup() {
                try { if (okBtn) okBtn.removeEventListener('click', onOk); } catch (e) {}
                try { m.removeEventListener('click', onOverlayClick); } catch (e) {}
                try { document.removeEventListener('keydown', onKey); } catch (e) {}
                if (m && m.parentNode) m.parentNode.removeChild(m);
            }

            const onOk = () => { cleanup(); };
            const onKey = (e) => { if (e.key === 'Escape') cleanup(); };
            const onOverlayClick = (e) => { if (e.target === m) cleanup(); };

            if (okBtn) okBtn.addEventListener('click', onOk);
            m.addEventListener('click', onOverlayClick);
            document.addEventListener('keydown', onKey);
        }
        // basic styles if not present
        if (!document.getElementById('sessionClearedModalStyles')) {
            const s = document.createElement('style'); s.id = 'sessionClearedModalStyles'; s.textContent = `.simple-modal{position:fixed;left:0;top:0;right:0;bottom:0;background:rgba(0,0,0,0.45);display:flex;align-items:center;justify-content:center;z-index:9999}.simple-modal__panel{background:#fff;padding:18px;border-radius:10px;min-width:260px;box-shadow:0 8px 24px rgba(0,0,0,0.2)}.simple-modal__body{margin-bottom:12px;font-weight:600}.simple-modal__actions{text-align:right}`; document.head.appendChild(s);
        }
    }

    // confirm modal for clearing sessions
    function openClearConfirmModal() {
        let m = document.getElementById('clearSessionsConfirmModal');
        if (!m) {
            m = document.createElement('div');
            m.id = 'clearSessionsConfirmModal';
            m.className = 'simple-modal';
            m.innerHTML = `<div class="simple-modal__panel"><div class="simple-modal__body">Clear all session data? This will remove restored comparison cards.</div><div class="simple-modal__actions"><button id="clearSessionsCancel" type="button" class="material-btn" style="margin-right:8px">Cancel</button><button id="clearSessionsConfirm" type="button" class="material-btn material-btn--primary">Clear</button></div></div>`;
            document.body.appendChild(m);
            // attach handlers scoped to this modal element
            const cancelBtn = m.querySelector('#clearSessionsCancel');
            const confirmBtn = m.querySelector('#clearSessionsConfirm');
            const onCancel = () => { cleanup(); };
            const onConfirm = async () => {
                try {
                    await doClearSessions();
                } finally {
                    cleanup();
                }
            };

            function onKey(e) {
                if (e.key === 'Escape') cleanup();
            }

            function onOverlayClick(e) {
                if (e.target === m) cleanup();
            }

            function cleanup() {
                try { if (cancelBtn) cancelBtn.removeEventListener('click', onCancel); } catch (e) {}
                try { if (confirmBtn) confirmBtn.removeEventListener('click', onConfirm); } catch (e) {}
                try { m.removeEventListener('click', onOverlayClick); } catch (e) {}
                try { document.removeEventListener('keydown', onKey); } catch (e) {}
                if (m && m.parentNode) m.parentNode.removeChild(m);
            }

            if (cancelBtn) cancelBtn.addEventListener('click', onCancel);
            if (confirmBtn) confirmBtn.addEventListener('click', onConfirm);
            m.addEventListener('click', onOverlayClick);
            document.addEventListener('keydown', onKey);
        }
        // ensure styles exist (reuse session cleared styles)
        if (!document.getElementById('sessionClearedModalStyles')) {
            const s = document.createElement('style'); s.id = 'sessionClearedModalStyles'; s.textContent = `.simple-modal{position:fixed;left:0;top:0;right:0;bottom:0;background:rgba(0,0,0,0.45);display:flex;align-items:center;justify-content:center;z-index:9999}.simple-modal__panel{background:#fff;padding:18px;border-radius:10px;min-width:260px;box-shadow:0 8px 24px rgba(0,0,0,0.2)}.simple-modal__body{margin-bottom:12px;font-weight:600}.simple-modal__actions{text-align:right}`; document.head.appendChild(s);
        }
    }

    async function doClearSessions() {
        try {
            const fd = new FormData();
            fd.append('csrf_token', csrfToken);
            const res = await fetch('../../controllers/excelcontrol/clearsection-controller.php', { method: 'POST', body: fd, credentials: 'same-origin' });

            // Debug: capture raw response text and status before parsing JSON
            const raw = await res.text();
            console.log('[clear-session] response status:', res.status);
            console.log('[clear-session] raw response:', raw);

            let j = null;
            try {
                j = raw ? JSON.parse(raw) : null;
            } catch (parseErr) {
                console.error('[clear-session] JSON parse error:', parseErr, 'raw:', raw);
                alert('Clear sessions failed — server returned invalid JSON. See console for details.');
                return;
            }

            if (res.ok && j && j.success) {
                // remove restored/finished cards and only remove their state entries
                const cards = Array.from(cardsWrap.querySelectorAll('.recon-card'));
                const removedIds = [];
                cards.forEach(c => {
                    const id = c.dataset.batchId || '';
                    if (id.startsWith('restored-') || c.dataset.status === 'true' || c.dataset.status === 'false') {
                        removedIds.push(id);
                        c.remove();
                    }
                });
                // delete only removed batches from state (keep active batches intact)
                removedIds.forEach(id => { if (state.batches.has(id)) state.batches.delete(id); });
                if (removedIds.length > 0) {
                    // adjust counter conservatively
                    state.count = Math.max(0, state.count - removedIds.length);
                }
                showSessionClearedModal();
            } else {
                console.error('[clear-session] server responded with error:', j);
                alert((j && j.message) ? j.message : 'Failed to clear sessions');
            }
        } catch (e) {
            console.error('clear sessions failed', e);
            alert('Failed to clear sessions');
        }
    }

    document.addEventListener('click', (event) => {
        if (event.target.matches('[data-action="close-result-modal"]')) {
            closeResultModal();
        }
    });

    function setCardStatus(card, status) {
        card.dataset.status = status;
        const icon = card.querySelector('.recon-card__icon');
        const spinner = card.querySelector('.recon-card__spinner');

        if (status === 'loading') {
            spinner.hidden = false;
            icon.textContent = '•';
        } else {
            spinner.hidden = true;
            icon.textContent = status === 'true' ? '✓' : status === 'false' ? '✕' : '•';
        }
    }

    function collapseCard(card) {
        card.dataset.expanded = 'false';
    }

    function expandCard(card) {
        card.dataset.expanded = 'true';
    }

    function validUploadFile(file) {
        if (!file) return false;
        const name = (file.name || '').toLowerCase();
        return name.endsWith('.xlsx') || name.endsWith('.csv') || name.endsWith('.xlx') || name.endsWith('.xls') || name.endsWith('.xlsm') || name.endsWith('.xlsb') || name.endsWith('.ods');
    }

    async function runComparison(card, batchId) {
        const batch = state.batches.get(batchId);
        if (!batch || batch.submitting || !batch.files.partners || !batch.files.web) return;
        if (batch.mode !== 'Test') return;

        batch.submitting = true;
        setCardStatus(card, 'loading');
        collapseCard(card);

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('mode', batch.mode);
        formData.append('batch_id', String(batchId));
        formData.append('partners_file', batch.files.partners);
        formData.append('web_file', batch.files.web);

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const payload = await response.json();
            if (!response.ok || !payload.success) {
                // show debug modal with server payload
                const debugModal = document.getElementById('errorDebugModal');
                if (debugModal) {
                    // tag modal with originating batch id so its Clear button can reset the right card
                    debugModal.dataset.batchId = batchId;
                    debugModal.classList.add('is-open');
                    const msgEl = debugModal.querySelector('[data-role="errorMessage"]');
                    const pre = debugModal.querySelector('[data-role="debugConsole"]');
                    if (msgEl) msgEl.textContent = payload.message || 'Comparison failed. See debug console.';
                    if (pre) pre.textContent = JSON.stringify(payload, null, 2);
                } else {
                    console.debug('Comparison error payload:', payload);
                    alert(payload.message || 'Comparison failed. See console for details.');
                }

                // If duplicate-blocked, attempt an automatic clear+retry once
                try {
                    const msg = String(payload.message || '');
                    if (msg.includes('Duplicate submission blocked.') && !batch._retriedAfterClear) {
                        batch._retriedAfterClear = true;
                        const clearUrl = '../../controllers/excelcontrol/clear-recent.php';
                        await fetch(clearUrl, { method: 'GET', credentials: 'same-origin' });
                        // small delay to ensure server session updated
                        await new Promise(r => setTimeout(r, 200));
                        // retry the same comparison once (will respect batch._retriedAfterClear)
                        runComparison(card, batchId);
                        return;
                    }
                } catch (e) {
                    console.error('Auto clear+retry failed', e);
                }

                setCardStatus(card, 'pending');
                // reset uploads so user can re-upload corrected files
                resetCardFiles(card, batchId);
                expandCard(card);
                batch.submitting = false;
                return;
            }

            batch.finished = true;
            batch.result = payload;
            setCardStatus(card, payload.allMatched ? 'true' : 'false');
        } catch (error) {
            console.error('Comparison request error', error);
            alert('Comparison failed. Please try again.');
            setCardStatus(card, 'pending');
            // reset so users can try again
            resetCardFiles(card, batchId);
            expandCard(card);
        } finally {
            batch.submitting = false;
        }
    }

    function wireDropzone(card, batchId, type) {
        const zone = card.querySelector(`[data-drop="${type}"]`);
        const input = card.querySelector(`[data-input="${type}"]`);
        const fileText = card.querySelector(`[data-file="${type}"]`);

        function setFile(file) {
                if (!validUploadFile(file)) {
                    alert('Invalid file type. Allowed: .xlsx, .xls, .xlsm, .xlsb, .ods, .csv');
                    return;
                }

            const batch = state.batches.get(batchId);
            const prevCount = batch ? ((batch.files.partners ? 1 : 0) + (batch.files.web ? 1 : 0)) : 0;

            fileText.textContent = file.name;
            if (batch) {
                batch.files[type] = file;
            }

            // Only auto-add a new empty card after the user has uploaded BOTH files
            // and only when the card we just updated is the last card in the list.
            try {
                const lastCard = cardsWrap.querySelector('.recon-card:last-child');
                const isLast = lastCard && lastCard.dataset && lastCard.dataset.batchId === batchId;
                const nowCount = batch ? ((batch.files.partners ? 1 : 0) + (batch.files.web ? 1 : 0)) : 0;
                if (prevCount === 1 && isLast && batch && nowCount === 2) {
                    createCard();
                }
            } catch (e) {
                // ignore
            }

            if (batch && batch.files.partners && batch.files.web) {
                runComparison(card, batchId);
            }
        }

        // expose fetch-test buttons behavior (global handlers will open modals)
        const openPartnerFetchBtn = document.getElementById('openPartnerFetch');
        const openWebFetchBtn = document.getElementById('openWebFetch');
        if (openPartnerFetchBtn && !openPartnerFetchBtn._wired) {
            openPartnerFetchBtn.addEventListener('click', () => {
                const modal = document.getElementById('partnerFetchModal');
                if (modal) modal.classList.add('is-open');
                const tokenInput = modal && modal.querySelector('input[name="csrf_token"]');
                if (tokenInput) tokenInput.value = csrfToken;
            });
            openPartnerFetchBtn._wired = true;
        }
        if (openWebFetchBtn && !openWebFetchBtn._wired) {
            openWebFetchBtn.addEventListener('click', () => {
                const modal = document.getElementById('webFetchModal');
                if (modal) modal.classList.add('is-open');
                const tokenInput = modal && modal.querySelector('input[name="csrf_token"]');
                if (tokenInput) tokenInput.value = csrfToken;
            });
            openWebFetchBtn._wired = true;
        }

        zone.addEventListener('click', () => {
            const batch = state.batches.get(batchId);
            if (batch.finished) return;
            input.click();
        });

        input.addEventListener('change', (event) => {
            const file = event.target.files && event.target.files[0];
            if (file) setFile(file);
        });

        ['dragenter', 'dragover'].forEach((evt) => {
            zone.addEventListener(evt, (event) => {
                event.preventDefault();
                event.stopPropagation();
                zone.classList.add('is-dragover');
            });
        });

        ['dragleave', 'drop'].forEach((evt) => {
            zone.addEventListener(evt, (event) => {
                event.preventDefault();
                event.stopPropagation();
                zone.classList.remove('is-dragover');
            });
        });

        zone.addEventListener('drop', (event) => {
            const batch = state.batches.get(batchId);
            if (batch.finished) return;
            const file = event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0];
            if (file) setFile(file);
        });
    }

    function resetCardFiles(card, batchId) {
        const batch = state.batches.get(batchId);
        if (!batch) return;
        batch.files.partners = null;
        batch.files.web = null;

        const partnersInput = card.querySelector('[data-input="partners"]');
        const webInput = card.querySelector('[data-input="web"]');
        const partnersText = card.querySelector('[data-file="partners"]');
        const webText = card.querySelector('[data-file="web"]');

        if (partnersInput) partnersInput.value = '';
        if (webInput) webInput.value = '';
        if (partnersText) partnersText.textContent = 'No file selected';
        if (webText) webText.textContent = 'No file selected';
    }

    function createCard() {
        state.count += 1;
        const batchId = `${Date.now()}-${state.count}`;

        // compute day label from selected month (YYYY-MM) -> MonthName DD, YYYY
        let dayLabel;
        try {
            const dayNum = String(state.count).padStart(2, '0');
            if (monthInput && monthInput.value) {
                const parts = monthInput.value.split('-');
                const yy = parts[0];
                const mm = Number(parts[1]);
                const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                dayLabel = `${months[Math.max(1, mm) - 1] || 'Month'} ${dayNum}, ${yy}`;
            } else {
                dayLabel = `Day ${state.count}`;
            }
        } catch (e) {
            dayLabel = `Day ${state.count}`;
        }

        const fragment = tpl.content.cloneNode(true);
        const card = fragment.querySelector('.recon-card');
        const head = card.querySelector('.recon-card__head');
        const title = card.querySelector('.recon-card__title');
        title.textContent = dayLabel;

        state.batches.set(batchId, {
            id: batchId,
            mode: (modeSelect && modeSelect.value) ? modeSelect.value : 'Test',
            date: (monthInput && monthInput.value) ? monthInput.value : null,
            files: { partners: null, web: null },
            submitting: false,
            finished: false,
            result: null
        });

        // attach batch id to the DOM card so modal handlers can target it
        card.dataset.batchId = batchId;

        // batch mode is controlled globally by the Mode selector

        head.addEventListener('click', () => {
            const batch = state.batches.get(batchId);
            if (batch.finished) {
                showResultModal(batch.result, batch.mode);
                return;
            }

            if (card.dataset.expanded === 'true') {
                collapseCard(card);
            } else {
                expandCard(card);
            }
        });

        wireDropzone(card, batchId, 'partners');
        wireDropzone(card, batchId, 'web');

        cardsWrap.appendChild(fragment);
    }

    // Always start with a single empty card for users to drop into.
    createCard();

    // Clear sessions button: clears server-side stored recent comparisons and removes restored cards
    const clearBtn = document.getElementById('clearSessionsBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            openClearConfirmModal();
        });
    }

    // When the global Mode changes, update existing batches
    if (modeSelect) {
        modeSelect.addEventListener('change', () => {
            const val = modeSelect.value;
            if (val !== 'Test') {
                alert('Only Test mode is available for now. Other modes are planned.');
            }
            for (const b of state.batches.values()) {
                b.mode = val;
            }
        });
    }
})();
</script>
