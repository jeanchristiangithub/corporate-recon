<?php
// UI-only Home Section: Statistics & Reconciliation Status (loads partner list from DB)
// Load partner names from `partner_data.partner_name` in filerecondb
require_once __DIR__ . '/../../../config/db.php';

$partners = [];
try {
    $pdo = masterDataConnection();
    $stmt = $pdo->query("SELECT DISTINCT partner_name FROM corpo_partner_masterfile WHERE partner_name IS NOT NULL AND partner_name <> '' ORDER BY partner_name ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (is_array($rows) && count($rows) > 0) {
        $partners = $rows;
    }
} catch (Throwable $e) {
    // If DB access fails, fall back to an empty partners list (UI will still render)
    $partners = [];
}
?>
<!-- Processing overlay for Start Reconcile -->
<div id="reconProcessingOverlay" role="dialog" aria-modal="true" aria-label="Processing reconciliation">
    <div class="recon-processing-box">
        <div class="recon-processing-spinner" aria-hidden="true"></div>
        <div class="recon-processing-text">Processing reconciliation...</div>
        <div class="recon-processing-sub">Please wait while data is being loaded.</div>
    </div>
</div>

<section id="homeSection" class="home-section" aria-label="Home Statistics">
    <div class="home-section__inner">
        <div class="home-section__sticky">
            <div class="filters">
                <div class="filters-left">
                    <label class="filter"><span>Corporate Partner</span>
                        <div class="autocomplete-field">
                            <input id="hsCompany" placeholder="Select corporate partner" autocomplete="off" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:60ch;width:min(100%,72ch);box-sizing:border-box;">
                            <ul class="autocomplete-list" id="hsCompanySuggestions" role="listbox" hidden></ul>
                            <datalist id="hsCompanyList">
                                <?php if (empty($partners)): ?>
                                    <option value=""></option>
                                <?php else: ?>
                                    <?php foreach ($partners as $p): ?>
                                        <option value="<?= htmlspecialchars((string)$p, ENT_QUOTES, 'UTF-8') ?>"></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </datalist>
                        </div>
                    </label>
                    <label class="filter"><span>Start Date</span>
                        <input id="hsStartDate" type="date" value="<?= date('Y-m-01') ?>">
                    </label>
                    <label class="filter"><span>End Date</span>
                        <input id="hsEndDate" type="date" value="<?= date('Y-m-d') ?>">
                    </label>
                    <div style="display:inline-flex;align-items:flex-end;gap:8px">
                        <button id="hsReconcile" class="material-btn material-btn--primary" style="margin-left:6px;">Start Reconcile</button>
                    </div>
                </div>
                <div class="filters-actions">
                        <!--button id="hsViewCoverPH" class="material-btn">View Cover PHP</button>
                        <!--button id="hsViewUsd" class="material-btn" style="display:none;margin-left:8px">View USD</button>
                        <!--<button id="hsExport" class="material-btn material-btn--primary">Export</button>-->
                </div>
            </div>

            
        </div>

        <div class="days">
            <div class="days-grid" id="hsDays">
                <!-- day cards populated by JS -->
            </div>
        </div>
    </div>

    <script>
    (function(){
        const daysContainer = document.getElementById('hsDays');
        // SVG icons (use inline SVG instead of emoji)
        const LOCK_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 17a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm6-7h-1V7a5 5 0 0 0-10 0v3H6a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2zm-8-3a3 3 0 0 1 6 0v3H10V7z"/></svg>';
        const UNLOCK_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M17 8V7a5 5 0 0 0-10 0h2a3 3 0 0 1 6 0v1H7a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2h-2zM12 17a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/></svg>';
        const MONTH_NAMES = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        // ensure day cards show pointer cursor
        (function(){
            const s = document.createElement('style');
            s.textContent = '.day-card{cursor:pointer} .day-card .day-badge{cursor:pointer}';
            document.head.appendChild(s);
        })();

        // expose whether current user is Admin to client-side scripts
        const IS_ADMIN = <?= isset($_SESSION['user']['role']) && strcasecmp((string)($_SESSION['user']['role']), 'Admin') === 0 ? 'true' : 'false' ?>;

        // lightweight modal utilities for confirmation and alerts
        function showConfirmModal(message){
            return new Promise(resolve => {
                let m = document.getElementById('__mbtc_confirm_modal');
                if(m) m.parentNode.removeChild(m);
                m = document.createElement('div'); m.id = '__mbtc_confirm_modal';
                Object.assign(m.style, { position:'fixed', inset:0, display:'flex', alignItems:'center', justifyContent:'center', background:'rgba(0,0,0,0.35)', zIndex:200002 });
                const box = document.createElement('div'); Object.assign(box.style, { background:'#fff', padding:'18px', borderRadius:'8px', minWidth:'320px', maxWidth:'90%' });
                const txt = document.createElement('div'); txt.textContent = message; txt.style.marginBottom = '12px';
                const row = document.createElement('div'); row.style.textAlign = 'right';
                const btnCancel = document.createElement('button'); btnCancel.textContent = 'Cancel'; btnCancel.className = 'material-btn'; btnCancel.style.marginRight = '8px';
                const btnOk = document.createElement('button'); btnOk.textContent = 'Lock'; btnOk.className = 'material-btn material-btn--primary';
                btnCancel.addEventListener('click', ()=>{ try{ document.body.removeChild(m); }catch(e){} resolve(false); });
                btnOk.addEventListener('click', ()=>{ try{ document.body.removeChild(m); }catch(e){} resolve(true); });
                row.appendChild(btnCancel); row.appendChild(btnOk);
                box.appendChild(txt); box.appendChild(row); m.appendChild(box); document.body.appendChild(m);
            });
        }

        function showAlertModal(message){
            return new Promise(resolve => {
                let m = document.getElementById('__mbtc_alert_modal');
                if(m) m.parentNode.removeChild(m);
                m = document.createElement('div'); m.id = '__mbtc_alert_modal';
                Object.assign(m.style, { position:'fixed', inset:0, display:'flex', alignItems:'center', justifyContent:'center', background:'rgba(0,0,0,0.35)', zIndex:200002 });
                const box = document.createElement('div'); Object.assign(box.style, { background:'#fff', padding:'18px', borderRadius:'8px', minWidth:'280px', maxWidth:'90%' });
                const txt = document.createElement('div'); txt.textContent = message; txt.style.marginBottom = '12px';
                const btnOk = document.createElement('button'); btnOk.textContent = 'OK'; btnOk.className = 'material-btn material-btn--primary';
                btnOk.addEventListener('click', ()=>{ try{ document.body.removeChild(m); }catch(e){} resolve(); });
                box.appendChild(txt); box.appendChild(btnOk); m.appendChild(box); document.body.appendChild(m);
            });
        }

        function normalizeIsoDate(value){
            const raw = String(value || '').trim();
            if(!raw) return '';
            const match = raw.match(/^(\d{4}-\d{2}-\d{2})/);
            if(match) return match[1];
            const parsed = new Date(raw);
            if(isNaN(parsed.getTime())) return '';
            return parsed.getFullYear() + '-' + String(parsed.getMonth() + 1).padStart(2, '0') + '-' + String(parsed.getDate()).padStart(2, '0');
        }

        function formatDateMMDDYYYY(dateStr){
            if(!dateStr) return '';
            const raw = String(dateStr || '').trim();
            const m = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if(m) return m[2] + '-' + m[3] + '-' + m[1];
            const d = new Date(raw);
            if(!isNaN(d.getTime())){
                return String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0') + '-' + d.getFullYear();
            }
            return raw;
        }

        function getDayCardReconData(dayCard){
            if(!dayCard) return null;
            const dateKey = normalizeIsoDate(dayCard.getAttribute('data-date') || '');
            const daysArr = Array.isArray(window._lastMbtcDays)
                ? window._lastMbtcDays
                : (typeof _lastMbtcDays !== 'undefined' && Array.isArray(_lastMbtcDays) ? _lastMbtcDays : []);
            const currentPartner = (company && company.value) ? String(company.value).trim().toUpperCase() : '';
            const matches = Array.isArray(daysArr) ? daysArr.filter((dayObj) => normalizeIsoDate(dayObj && dayObj.date || '') === dateKey) : [];
            if(matches.length > 0) return matches[0];

            if(dateKey && currentPartner){
                return { date: dateKey, partner: currentPartner };
            }

            return null;
        }

        function isLockableReconDay(dayObj){
            if(!dayObj) return false;
            const matchedCount = Number(dayObj.matchedCount || dayObj.vol || 0);
            const principal = Number(dayObj.principal || 0);
            const commission = Number(dayObj.commission || 0);
            return matchedCount > 0 || principal !== 0 || commission !== 0;
        }

        function getSelectedPartnerLockKey(){
            return (company && company.value) ? String(company.value).trim().toUpperCase() : '';
        }

        function setDayCardLockState(card, locked){
            if(!card) return;
            const isLocked = !!locked;
            const existingIcon = card.querySelector('.day-lock-icon');

            if(isLocked){
                card.classList.add('locked-day');
                card.setAttribute('data-locked', 'true');
                if(!existingIcon){
                    const icon = document.createElement('div');
                    icon.className = 'day-lock-icon';
                    icon.innerHTML = LOCK_SVG;
                    Object.assign(icon.style, { position: 'absolute', right: '8px', top: '8px', fontSize: '18px', pointerEvents: 'none' });
                    if(!card.style.position) card.style.position = 'relative';
                    card.appendChild(icon);
                }
            } else {
                card.classList.remove('locked-day');
                card.removeAttribute('data-locked');
                if(existingIcon) existingIcon.remove();
            }

            const lb = card.querySelector('.day-lock-btn');
            if(lb){
                lb.setAttribute('data-locked', isLocked ? 'true' : 'false');
                lb.innerHTML = isLocked ? LOCK_SVG : UNLOCK_SVG;
                lb.title = isLocked ? 'Unlock reconciliation' : 'Lock reconciliation';
            }
        }

        async function fetchDayCardLocks(startDate, endDate){
            const partner = getSelectedPartnerLockKey();
            if(!partner || !startDate || !endDate) return;
            const url = location.origin + '/autorecon/src/controllers/recon/get_daycard_locks.php?partner=' + encodeURIComponent(partner) + '&start_date=' + encodeURIComponent(startDate) + '&end_date=' + encodeURIComponent(endDate);
            try{
                const res = await fetch(url, { method: 'GET', credentials: 'same-origin' });
                if(!res || !res.ok) return;
                const json = await res.json();
                const lockMap = new Map();
                const locks = Array.isArray(json && json.locks) ? json.locks : [];
                locks.forEach((row) => {
                    const dateKey = normalizeIsoDate(row.recon_date || row.date || '');
                    if(!dateKey) return;
                    const locked = Number(row.is_locked) === 1 || String(row.is_locked) === '1' || row.is_locked === true;
                    lockMap.set(dateKey, locked);
                });

                daysContainer.querySelectorAll('.day-card[data-date]').forEach((card) => {
                    const dateKey = normalizeIsoDate(card.getAttribute('data-date') || '');
                    if(!dateKey) return;
                    if(lockMap.has(dateKey)) setDayCardLockState(card, lockMap.get(dateKey));
                });
            }catch(e){
                console.warn('Failed to fetch day card locks', e);
            }
        }

        async function persistDayCardLock(action, partner, date){
            const endpoint = action === 'unlock'
                ? '/autorecon/src/controllers/recon/unlock_daycard.php'
                : '/autorecon/src/controllers/recon/lock_daycard.php';
            try{
                const res = await fetch(location.origin + endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ partner: partner || '', date: date || '' })
                });
                if(!res.ok){
                    if(res.status === 403) await showAlertModal('You are not authorized to perform this action.');
                    else await showAlertModal('Server error while saving lock state.');
                    return false;
                }
                return true;
            }catch(e){
                console.warn('Failed to persist day card lock', e);
                await showAlertModal('Failed to contact lock service.');
                return false;
            }
        }

        const company = document.getElementById('hsCompany');
        // Reconcile button behavior: show day cards only when valid partner selected
        const reconcileBtn = document.getElementById('hsReconcile');
        const daysContainerWrap = document.querySelector('.days');
        function hideDays(){ if(daysContainerWrap) { daysContainerWrap.classList.add('hidden'); daysContainerWrap.classList.remove('showing'); } }
        function showDays(){ if(daysContainerWrap) { daysContainerWrap.classList.remove('hidden'); daysContainerWrap.classList.add('showing'); } }
        // hide by default on load
        hideDays();

        const reconOverlay = document.getElementById('reconProcessingOverlay');
        function showReconLoader(){
            if(reconOverlay) reconOverlay.classList.add('active');
            reconcileBtn.disabled = true;
            reconcileBtn.classList.add('disabled');
            reconcileBtn.textContent = 'Processing...';
        }
        function hideReconLoader(origText){
            if(reconOverlay) reconOverlay.classList.remove('active');
            reconcileBtn.disabled = false;
            reconcileBtn.classList.remove('disabled');
            reconcileBtn.textContent = origText || 'Start Reconcile';
        }

        function toUpperKey(value){
            return String(value || '').trim().toUpperCase();
        }

        function extractMoneygramPartnerRef(row){
            // Partner side must be sourced strictly from partner_* fields.
            return row.partner_reference_id || row.partner_transaction_id || row.partner_reference_no || row.partner_ref_no || '';
        }

        function extractMoneygramWebRef(row){
            // Web side must be sourced strictly from web_* fields.
            return row.web_ccref_no || row.web_cc_ref || row.web_ccref || row.web_ref || '';
        }

        function toMoneygramAmount(value){
            const n = Number(value || 0);
            return Number.isFinite(n) ? n : 0;
        }

        async function fetchMoneygramRangeAggregate(partnerName, startDate, endDate){
            const baseUrl = location.origin + '/autorecon/src/controllers/recon/moneygram-recon.php?start_date=' + encodeURIComponent(startDate || '') + '&end_date=' + encodeURIComponent(endDate || '') + '&partnerName=' + encodeURIComponent(partnerName || '');
            const summaryResp = await fetch(baseUrl, { method: 'GET', credentials: 'same-origin' });
            if(!summaryResp || !summaryResp.ok){
                throw new Error('summary_fetch_failed');
            }

            const summaryJson = await summaryResp.json();
            const days = Array.isArray(summaryJson && summaryJson.days) ? summaryJson.days : [];
            const aggregate = {
                partner: String(partnerName || '').trim().toUpperCase(),
                startDate: startDate || '',
                endDate: endDate || '',
                rows: [],
                duplicates: [],
                allMissingWebRefs: [],
                allMissingPartnerRefs: []
            };

            const detailDays = days.filter((dayObj) => {
                if(!dayObj) return false;
                const status = String(dayObj.status || '').toLowerCase();
                if(status === 'green' || status === 'red' || status === 'yellow') return true;
                if(Number(dayObj.vol || 0) > 0) return true;
                if(Array.isArray(dayObj.missing_web_refs) && dayObj.missing_web_refs.length > 0) return true;
                if(Array.isArray(dayObj.missing_partner_refs) && dayObj.missing_partner_refs.length > 0) return true;
                return false;
            });

            for(const dayObj of detailDays){
                const dayNum = Number(dayObj.day || 0);
                const dayDate = String(dayObj.date || '');
                if(!dayNum || !dayDate) continue;

                const detailUrl = baseUrl + '&detail=1&day=' + encodeURIComponent(String(dayNum)) + '&date=' + encodeURIComponent(dayDate);
                try{
                    const detailResp = await fetch(detailUrl, { method: 'GET', credentials: 'same-origin' });
                    if(!detailResp || !detailResp.ok) continue;
                    const detailJson = await detailResp.json();
                    let detailDay = null;
                    if(detailJson && detailJson.day) detailDay = detailJson.day;
                    else if(detailJson && Array.isArray(detailJson.days)) detailDay = detailJson.days.find((d) => String(d.day) === String(dayNum) || String(d.date) === dayDate) || null;

                    if(detailDay && Array.isArray(detailDay.rows) && detailDay.rows.length){
                        detailDay.rows.forEach((row) => {
                            const next = Object.assign({}, row);
                            if(!next.partner_tran_date && dayDate) next.partner_tran_date = dayDate;
                            aggregate.rows.push(next);
                        });
                    }

                    if(detailDay && Array.isArray(detailDay.missing_web_refs)){
                        detailDay.missing_web_refs.forEach((ref) => {
                            aggregate.allMissingWebRefs.push({ ref: ref, date: dayDate });
                        });
                    }
                    if(detailDay && Array.isArray(detailDay.missing_partner_refs)){
                        detailDay.missing_partner_refs.forEach((ref) => {
                            aggregate.allMissingPartnerRefs.push({ ref: ref, date: dayDate });
                        });
                    }
                    if(detailDay && Array.isArray(detailDay.duplicates) && detailDay.duplicates.length){
                        aggregate.duplicates = aggregate.duplicates.concat(detailDay.duplicates);
                    }
                }catch(_detailErr){
                    // Skip a failed day detail fetch and continue with remaining days.
                }
            }

            return aggregate;
        }

        function openMoneygramRangeModal(aggregate, partnerName){
            const modal = document.getElementById('moneygramReconViewModal');
            if(!modal) throw new Error('moneygram_modal_not_found');

            const loadingEl = modal.querySelector('.moneygram-recon-modal__loading');
            if(loadingEl) loadingEl.style.display = 'flex';
            modal.style.display = 'block';
            try{ document.body.style.overflow = 'hidden'; }catch(e){}

            modal.dataset.partnerName = String(partnerName || '');
            modal.dataset.reconDate = String(aggregate && aggregate.startDate || '');
            modal.dataset.startDate = String(aggregate && aggregate.startDate || '');
            modal.dataset.endDate = String(aggregate && aggregate.endDate || '');

            const summaryEl = modal.querySelector('[data-role="summary"]');
            const partnersBody = modal.querySelector('[data-role="partnersBody"]');
            const webBody = modal.querySelector('[data-role="webBody"]');
            const partnersVolumeEl = modal.querySelector('[data-role="partnersVolume"]');
            const webVolumeEl = modal.querySelector('[data-role="webVolume"]');
            const partnersPrincipalPhpEl = modal.querySelector('[data-role="partnersPrincipalPhp"]');
            const partnersPrincipalUsdEl = modal.querySelector('[data-role="partnersPrincipalUsd"]');
            const webPrincipalPhpEl = modal.querySelector('[data-role="webPrincipalPhp"]');
            const webPrincipalUsdEl = modal.querySelector('[data-role="webPrincipalUsd"]');
            const searchEl = modal.querySelector('[data-role="resultSearch"]');
            const filterEl = modal.querySelector('[data-role="resultFilter"]');

            if(!partnersBody || !webBody){
                if(loadingEl) loadingEl.style.display = 'none';
                throw new Error('moneygram_modal_table_not_found');
            }

            partnersBody.innerHTML = '';
            webBody.innerHTML = '';

            const rows = Array.isArray(aggregate && aggregate.rows) ? aggregate.rows : [];
            const allMissingWebRefs = Array.isArray(aggregate && aggregate.allMissingWebRefs) ? aggregate.allMissingWebRefs : [];
            const allMissingPartnerRefs = Array.isArray(aggregate && aggregate.allMissingPartnerRefs) ? aggregate.allMissingPartnerRefs : [];
            const duplicateRefs = new Set((Array.isArray(aggregate && aggregate.duplicates) ? aggregate.duplicates : []).map((d) => toUpperKey(d && d.ref ? d.ref : '')));

            const alignedPairs = [];
            const partnerBucket = new Map();
            const webBucket = new Map();
            const orderedKeys = [];
            const partnerNoKey = [];
            const webNoKey = [];
            const missingWebRefs = Array.isArray(allMissingWebRefs) ? allMissingWebRefs : [];
            const missingPartnerRefs = Array.isArray(allMissingPartnerRefs) ? allMissingPartnerRefs : [];
            // Track unmatched rows already materialized from detail rows,
            // so missing_* fallbacks don't push the same logical row again.
            const seenPartnerOnlyKeys = new Set();
            const seenWebOnlyKeys = new Set();

            const pushOrderedKey = function(key){
                if(!key) return;
                if(orderedKeys.indexOf(key) === -1) orderedKeys.push(key);
            };

            const appendBucket = function(mapObj, key, rowObj){
                if(!mapObj.has(key)) mapObj.set(key, []);
                mapObj.get(key).push(rowObj);
            };

            const createPartnerRow = function(obj){
                const tr = document.createElement('tr');
                if(obj && obj.placeholder){
                    tr.classList.add('row-placeholder');
                    tr.innerHTML = '<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>';
                    tr.dataset.ref = '';
                    tr.dataset.amount = '0';
                    tr.dataset.currency = '';
                    tr.dataset.isoDate = '';
                    return tr;
                }
                const dateText = formatDateMMDDYYYY(obj.pDate || '');
                const isoDate = normalizeIsoDate(obj.pDate || '');
                const amount = toMoneygramAmount(obj.pAmt);
                const currency = String(obj.pCoin || '').trim();
                tr.dataset.ref = String(obj.tx || '');
                tr.dataset.amount = String(amount);
                tr.dataset.currency = currency;
                tr.dataset.isoDate = isoDate;
                tr.innerHTML = '<td>' + dateText + '</td><td class="highlight-ref">' + String(obj.tx || '') + '</td><td>' + Math.abs(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td><td>' + currency + '</td>';
                return tr;
            };

            const createWebRow = function(obj){
                const tr = document.createElement('tr');
                if(obj && obj.placeholder){
                    tr.classList.add('row-placeholder');
                    tr.innerHTML = '<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>';
                    tr.dataset.ref = '';
                    tr.dataset.amount = '0';
                    tr.dataset.currency = '';
                    tr.dataset.isoDate = '';
                    return tr;
                }
                const dateText = formatDateMMDDYYYY(obj.wDateRaw || '');
                const isoDate = normalizeIsoDate(obj.wDateRaw || '');
                const amount = toMoneygramAmount(obj.wAmt);
                const currency = String(obj.wCurrency || '').trim();
                tr.dataset.ref = String(obj.wRef || '');
                tr.dataset.amount = String(amount);
                tr.dataset.currency = currency;
                tr.dataset.isoDate = isoDate;
                tr.innerHTML = '<td>' + dateText + '</td><td class="highlight-ref">' + String(obj.wRef || '') + '</td><td>' + Math.abs(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td><td>' + currency + '</td>';
                return tr;
            };

            const createDateSeparatorRow = function(isoDate){
                const tr = document.createElement('tr');
                tr.className = 'date-sep-row';
                tr.setAttribute('data-role', 'date-separator');
                tr.setAttribute('data-date', isoDate || '');
                const label = formatDateMMDDYYYY(isoDate || '') || 'NO DATE';
                tr.innerHTML = '<td colspan="4" style="font-weight:700;color:#334155;background:#f8fafc;border-top:1px solid #dbe3ef;border-bottom:1px solid #dbe3ef;padding:6px 8px;">===== ' + label + ' =====</td>';
                return tr;
            };

            let pairInsertIndex = 0;
            const addAlignedPair = function(partnerObj, webObj){
                const partnerRow = createPartnerRow(partnerObj || { placeholder: true });
                const webRow = createWebRow(webObj || { placeholder: true });
                const pRef = toUpperKey(partnerObj ? partnerObj.tx : '');
                const wRef = toUpperKey(webObj ? webObj.wRef : '');
                const hasPartner = !!partnerObj;
                const hasWeb = !!webObj;
                const isMatch = hasPartner && hasWeb && pRef && wRef && pRef === wRef;
                const isDuplicate = !!((pRef && duplicateRefs.has(pRef)) || (wRef && duplicateRefs.has(wRef)));

                if(isDuplicate){
                    partnerRow.classList.add('dup-row');
                    webRow.classList.add('row-duplicate');
                } else if(isMatch){
                    partnerRow.classList.add('matched-row');
                    webRow.classList.add('row-match');
                } else {
                    partnerRow.classList.add('mismatch-row');
                    webRow.classList.add('row-mismatch');
                }

                const pairDateIso = normalizeIsoDate(
                    (partnerObj && partnerObj.pDate) ||
                    (webObj && webObj.wDateRaw) ||
                    aggregate.startDate || ''
                );
                alignedPairs.push({
                    partnerRow: partnerRow,
                    webRow: webRow,
                    isMismatch: !isMatch,
                    isDuplicate: isDuplicate,
                    pairDateIso: pairDateIso,
                    insertIndex: pairInsertIndex++
                });
            };

            rows.forEach((row) => {
                const tx = extractMoneygramPartnerRef(row);
                const wRef = extractMoneygramWebRef(row);
                const pObj = tx ? {
                    pDate: row.partner_tran_date || row.partner_date || row.partner_fx_date_trn || row.partner_cover_date || row.partner_date_claimed || row.partner_date_send || aggregate.startDate || '',
                    tx: tx,
                    pAmt: toMoneygramAmount(row.partner_principal || row.partner_base_tran_amt || row.partner_total_tran_amt || row.partner_amount || 0),
                    pCoin: row.partner_transaction_currency || row.partner_base_cncy || row.partner_currency || row.partner_coin || ''
                } : null;
                const wObj = wRef ? {
                    wDateRaw: row.web_date_claimed || row.web_date_send || row.web_tran_date || row.web_date || '',
                    wRef: wRef,
                    wAmt: toMoneygramAmount(row.web_amount || row.web_amt || 0),
                    wCurrency: row.web_currency || row.web_ccy || row.web_currency_code || ''
                } : null;

                if(pObj && !wObj){
                    const pOnlyKey = normalizeIsoDate(pObj.pDate || '') + '|' + toUpperKey(pObj.tx || '');
                    if(pOnlyKey !== '|') seenPartnerOnlyKeys.add(pOnlyKey);
                }
                if(wObj && !pObj){
                    const wOnlyKey = normalizeIsoDate(wObj.wDateRaw || '') + '|' + toUpperKey(wObj.wRef || '');
                    if(wOnlyKey !== '|') seenWebOnlyKeys.add(wOnlyKey);
                }

                if(pObj){
                    const key = toUpperKey(tx);
                    if(key){ appendBucket(partnerBucket, key, pObj); pushOrderedKey(key); }
                    else partnerNoKey.push(pObj);
                }
                if(wObj){
                    const key = toUpperKey(wRef);
                    if(key){ appendBucket(webBucket, key, wObj); pushOrderedKey(key); }
                    else webNoKey.push(wObj);
                }
            });

            orderedKeys.forEach((key) => {
                const pList = partnerBucket.get(key) || [];
                const wList = webBucket.get(key) || [];
                const maxLen = Math.max(pList.length, wList.length);
                for(let i = 0; i < maxLen; i++){
                    addAlignedPair(pList[i] || null, wList[i] || null);
                }
            });

            const orphanMax = Math.max(partnerNoKey.length, webNoKey.length);
            for(let i = 0; i < orphanMax; i++){
                addAlignedPair(partnerNoKey[i] || null, webNoKey[i] || null);
            }

            // For missing refs, try to reuse any available row data from aggregate.rows
            missingWebRefs.forEach((item) => {
                const ref = item && item.ref ? String(item.ref).trim() : '';
                if(!ref) return;
                const itemDateKey = normalizeIsoDate(item && item.date || aggregate.startDate || '');
                const dedupeKey = itemDateKey + '|' + toUpperKey(ref);
                if(seenPartnerOnlyKeys.has(dedupeKey)) return;
                // attempt to find a partner row in the detailed rows that matches this ref
                let source = null;
                for(let i = 0; i < rows.length; i++){
                    const r = rows[i];
                    const rtx = toUpperKey(extractMoneygramPartnerRef(r));
                    if(rtx && rtx === toUpperKey(ref)){ source = r; break; }
                }
                if(source){
                    const pObj = {
                        pDate: source.partner_tran_date || source.partner_date || source.partner_fx_date_trn || source.partner_cover_date || source.partner_date_claimed || source.partner_date_send || aggregate.startDate || item && item.date || '',
                        tx: ref,
                        pAmt: toMoneygramAmount(source.partner_principal || source.partner_base_tran_amt || source.partner_total_tran_amt || source.partner_amount || source.amount || 0),
                        pCoin: source.partner_transaction_currency || source.partner_base_cncy || source.partner_currency || source.partner_coin || ''
                    };
                    seenPartnerOnlyKeys.add(normalizeIsoDate(pObj.pDate || '') + '|' + toUpperKey(pObj.tx || ''));
                    addAlignedPair(pObj, null);
                } else {
                    const pObj = { pDate: item && item.date || aggregate.startDate || '', tx: ref, pAmt: 0, pCoin: '' };
                    seenPartnerOnlyKeys.add(normalizeIsoDate(pObj.pDate || '') + '|' + toUpperKey(pObj.tx || ''));
                    addAlignedPair(pObj, null);
                }
            });

            missingPartnerRefs.forEach((item) => {
                const ref = item && item.ref ? String(item.ref).trim() : '';
                if(!ref) return;
                const itemDateKey = normalizeIsoDate(item && item.date || aggregate.startDate || '');
                const dedupeKey = itemDateKey + '|' + toUpperKey(ref);
                if(seenWebOnlyKeys.has(dedupeKey)) return;
                // attempt to find a web row in the detailed rows that matches this ref
                let source = null;
                for(let i = 0; i < rows.length; i++){
                    const r = rows[i];
                    const rw = toUpperKey(extractMoneygramWebRef(r));
                    if(rw && rw === toUpperKey(ref)){ source = r; break; }
                }
                if(source){
                    const wObj = {
                        wDateRaw: source.web_date_claimed || source.web_date_send || source.web_tran_date || source.web_date || '',
                        wRef: ref,
                        wAmt: toMoneygramAmount(source.web_amount || source.web_amt || source.amount || 0),
                        wCurrency: source.web_currency || source.web_ccy || source.web_currency_code || ''
                    };
                    seenWebOnlyKeys.add(normalizeIsoDate(wObj.wDateRaw || '') + '|' + toUpperKey(wObj.wRef || ''));
                    addAlignedPair(null, wObj);
                } else {
                    const wObj = { wDateRaw: item && item.date || aggregate.startDate || '', wRef: ref, wAmt: 0, wCurrency: '' };
                    seenWebOnlyKeys.add(normalizeIsoDate(wObj.wDateRaw || '') + '|' + toUpperKey(wObj.wRef || ''));
                    addAlignedPair(null, wObj);
                }
            });

            const dateSeparatorPairs = [];
            const renderAlignedPairsByDate = function(){
                partnersBody.innerHTML = '';
                webBody.innerHTML = '';
                dateSeparatorPairs.length = 0;

                const sorted = alignedPairs.slice().sort((a, b) => {
                    const aDate = a.pairDateIso || '9999-12-31';
                    const bDate = b.pairDateIso || '9999-12-31';
                    if(aDate < bDate) return -1;
                    if(aDate > bDate) return 1;

                    // Within each date group, keep matched rows above mismatched/duplicates.
                    const aRank = (a.isMismatch || a.isDuplicate) ? 1 : 0;
                    const bRank = (b.isMismatch || b.isDuplicate) ? 1 : 0;
                    if(aRank !== bRank) return aRank - bRank;

                    return a.insertIndex - b.insertIndex;
                });

                let currentDate = null;
                sorted.forEach((pair) => {
                    const pairDate = pair.pairDateIso || '';
                    if(pairDate !== currentDate){
                        currentDate = pairDate;
                        const pSep = createDateSeparatorRow(currentDate);
                        const wSep = createDateSeparatorRow(currentDate);
                        partnersBody.appendChild(pSep);
                        webBody.appendChild(wSep);
                        dateSeparatorPairs.push({ date: currentDate, partnerRow: pSep, webRow: wSep });
                    }
                    partnersBody.appendChild(pair.partnerRow);
                    webBody.appendChild(pair.webRow);
                });
            };

            const updateDateSeparatorVisibility = function(){
                dateSeparatorPairs.forEach((sep) => {
                    const hasVisibleData = alignedPairs.some((pair) => {
                        if((pair.pairDateIso || '') !== (sep.date || '')) return false;
                        return pair.partnerRow.style.display !== 'none' || pair.webRow.style.display !== 'none';
                    });
                    sep.partnerRow.style.display = hasVisibleData ? '' : 'none';
                    sep.webRow.style.display = hasVisibleData ? '' : 'none';
                });
            };

            renderAlignedPairsByDate();

            const matchedCount = alignedPairs.filter((p) => !p.isMismatch && !p.isDuplicate).length;
            const unmatchedCount = alignedPairs.filter((p) => (p.isMismatch || p.isDuplicate)).length;
            const matchedLabel = matchedCount === 1 ? 'transaction' : 'transactions';
            const unmatchedLabel = unmatchedCount === 1 ? 'transaction' : 'transactions';
            if(summaryEl){
                summaryEl.innerHTML = '<span class="recon-summary__item">Matched: ' + matchedCount.toLocaleString() + ' ' + matchedLabel + '</span><span class="recon-summary__sep">|</span><span class="recon-summary__item">Not Matched: ' + unmatchedCount.toLocaleString() + ' ' + unmatchedLabel + '</span>';
            }

            const ensureEmptyRow = function(tbody, colspan = 4, message = 'No Data Found'){
                if(!tbody) return;
                Array.from(tbody.querySelectorAll('.empty-row')).forEach((el) => el.remove());
                const visibleRows = Array.from(tbody.querySelectorAll('tr')).filter((tr) => {
                    if(tr.style.display === 'none') return false;
                    if(tr.classList.contains('dup-sep')) return false;
                    if(tr.getAttribute('data-role') === 'date-separator') return false;
                    return true;
                });
                if(visibleRows.length === 0){
                    const tr = document.createElement('tr');
                    tr.className = 'empty-row';
                    tr.innerHTML = `<td colspan="${colspan}" style="text-align:center;color:var(--muted);padding:20px 8px;">${message}</td>`;
                    tbody.appendChild(tr);
                }
            };

            const updateMetrics = function(){
                const visiblePairs = alignedPairs.filter((pair) => pair.partnerRow.style.display !== 'none' || pair.webRow.style.display !== 'none');

                let partnerVisibleCount = 0;
                let webVisibleCount = 0;
                let pPhp = 0;
                let pUsd = 0;
                let wPhp = 0;
                let wUsd = 0;

                visiblePairs.forEach((pair) => {
                    if(pair.partnerRow.style.display !== 'none'){
                        const pRef = String(pair.partnerRow.dataset.ref || '').trim();
                        if(pRef) partnerVisibleCount++;
                        const amount = toMoneygramAmount(pair.partnerRow.dataset.amount);
                        const cur = toUpperKey(pair.partnerRow.dataset.currency || '');
                        if(cur.indexOf('PHP') !== -1) pPhp += Math.abs(amount);
                        else if(cur.indexOf('USD') !== -1) pUsd += Math.abs(amount);
                    }
                    if(pair.webRow.style.display !== 'none'){
                        const wRef = String(pair.webRow.dataset.ref || '').trim();
                        if(wRef) webVisibleCount++;
                        const amount = toMoneygramAmount(pair.webRow.dataset.amount);
                        const cur = toUpperKey(pair.webRow.dataset.currency || '');
                        if(cur.indexOf('PHP') !== -1) wPhp += amount;
                        else if(cur.indexOf('USD') !== -1) wUsd += amount;
                    }
                });

                if(partnersVolumeEl) partnersVolumeEl.textContent = 'Volume: ' + partnerVisibleCount.toLocaleString();
                if(webVolumeEl) webVolumeEl.textContent = 'Volume: ' + webVisibleCount.toLocaleString();
                if(partnersPrincipalPhpEl) partnersPrincipalPhpEl.textContent = 'Principal PHP: ' + pPhp.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' pesos';
                if(partnersPrincipalUsdEl) partnersPrincipalUsdEl.textContent = 'Principal USD: ' + pUsd.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                if(webPrincipalPhpEl) webPrincipalPhpEl.textContent = 'Principal PHP: ' + wPhp.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' pesos';
                if(webPrincipalUsdEl) webPrincipalUsdEl.textContent = 'Principal USD: ' + wUsd.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            };

            const applySearchFilter = function(){
                const query = searchEl && searchEl.value ? String(searchEl.value).trim().toLowerCase() : '';
                const filter = filterEl && filterEl.value ? String(filterEl.value) : 'all';

                alignedPairs.forEach((pair) => {
                    const pRef = String(pair.partnerRow.dataset.ref || '').toLowerCase();
                    const wRef = String(pair.webRow.dataset.ref || '').toLowerCase();
                    let show = true;
                    if(query) show = pRef.includes(query) || wRef.includes(query);
                    if(filter === 'mismatch') show = show && (pair.isMismatch && !pair.isDuplicate);
                    else if(filter === 'duplicates') show = show && pair.isDuplicate;
                    else if(filter === 'matched') show = show && !pair.isMismatch && !pair.isDuplicate;
                    pair.partnerRow.style.display = show ? '' : 'none';
                    pair.webRow.style.display = show ? '' : 'none';
                });

                // If a specific search query is present, collapse duplicate/secondary
                // pairs that refer to the same reference. Prefer a finalized valid
                // match (green) when deciding which pair to show. This ensures
                // searches for a particular CCREF/Reference ID return a single
                // definitive row (either matched OR mismatched), not both.
                if(query){
                    const refGroups = new Map();
                    alignedPairs.forEach((pair) => {
                        const pRef = String(pair.partnerRow.dataset.ref || '').toLowerCase();
                        const wRef = String(pair.webRow.dataset.ref || '').toLowerCase();
                        const key = pRef || wRef;
                        if(!key) return;
                        if(!refGroups.has(key)) refGroups.set(key, []);
                        refGroups.get(key).push(pair);
                    });

                    for(const [key, group] of refGroups.entries()){
                        if(!key.includes(query)) continue;
                        // Prefer exact valid match (not mismatch, not duplicate)
                        let chosen = group.find(p => !p.isMismatch && !p.isDuplicate);
                        // If none, prefer a mismatch (non-duplicate) or fallback to first
                        if(!chosen) chosen = group.find(p => p.isMismatch && !p.isDuplicate) || group[0];
                        group.forEach(p => {
                            if(p !== chosen){
                                p.partnerRow.style.display = 'none';
                                p.webRow.style.display = 'none';
                            }
                        });
                    }
                }

                updateDateSeparatorVisibility();
                ensureEmptyRow(partnersBody);
                ensureEmptyRow(webBody);
                updateMetrics();
            };

            if(searchEl && modal._moneygramRangeSearchHandler){
                searchEl.removeEventListener('input', modal._moneygramRangeSearchHandler);
            }
            if(filterEl && modal._moneygramRangeFilterHandler){
                filterEl.removeEventListener('change', modal._moneygramRangeFilterHandler);
            }
            modal._moneygramRangeSearchHandler = applySearchFilter;
            modal._moneygramRangeFilterHandler = applySearchFilter;
            if(searchEl){ searchEl.value = ''; searchEl.addEventListener('input', modal._moneygramRangeSearchHandler); }
            if(filterEl){ filterEl.value = 'all'; filterEl.addEventListener('change', modal._moneygramRangeFilterHandler); }

            ensureEmptyRow(partnersBody);
            ensureEmptyRow(webBody);
            updateMetrics();
            applySearchFilter();

            const closeBtn = modal.querySelector('[data-action="close-moneygram-recon"]');
            if(closeBtn){
                closeBtn.onclick = function(){
                    if(searchEl) searchEl.value = '';
                    if(filterEl) filterEl.value = 'all';
                    modal.style.display = 'none';
                    try{ document.body.style.overflow = ''; }catch(e){}
                };
            }

            if(loadingEl) loadingEl.style.display = 'none';
        }

        if(reconcileBtn && !reconcileBtn._listener){
            reconcileBtn.addEventListener('click', async function(ev){
                ev.preventDefault();
                const origText = reconcileBtn.textContent;
                // validate partner
                const val = (company && company.value) ? String(company.value).trim() : '';
                const valid = (Array.isArray(partners) && partners.some(p => String(p).trim() === val));
                if(!valid){
                    showAlertModal('Please select a valid Corporate Partner before reconciling.');
                    hideDays();
                    return;
                }
                if(!isValidDateRange(true)){
                    hideDays();
                    return;
                }
                showReconLoader();
                try{
                    const range = getSelectedDateRange();
                    const companyName = company && company.value ? String(company.value) : '';
                    const aggregate = await fetchMoneygramRangeAggregate(companyName, range.startDate, range.endDate);
                    hideDays();

                    const totalRows = Array.isArray(aggregate && aggregate.rows) ? aggregate.rows.length : 0;
                    const totalCount = Number(aggregate && aggregate.matchedCount || 0) + Number(aggregate && aggregate.unmatchedCount || 0);
                    if(totalRows === 0 && totalCount === 0){
                        await showAlertModal('No transactions found for selected dates.');
                        return;
                    }

                    openMoneygramRangeModal(aggregate, companyName);
                }catch(err){
                    console.warn('Reconcile failed', err);
                    hideDays();
                    await showAlertModal('Failed to load reconciliation details.');
                } finally {
                    hideReconLoader(origText);
                }
            });
            reconcileBtn._listener = true;
        }

        // Hide day cards immediately when corporate partner is cleared or becomes invalid
        function onCompanyChangeHideDays(){
            const val = (company && company.value) ? String(company.value).trim() : '';
            const valid = (Array.isArray(partners) && partners.some(p => String(p).trim() === val));
            if(!valid) {
                hideDays();
                try{
                    if(principalEl) principalEl.textContent = '0.00 pesos';
                    if(commissionEl) commissionEl.textContent = '0.00 pesos';
                }catch(e){ /* ignore formatting errors */ }
            }
        }
        if(company){ company.addEventListener('input', onCompanyChangeHideDays); company.addEventListener('change', onCompanyChangeHideDays); }
        const startDateInput = document.getElementById('hsStartDate');
        const endDateInput = document.getElementById('hsEndDate');

        function getSelectedDateRange(){
            const startDate = (startDateInput && startDateInput.value) ? String(startDateInput.value) : '';
            const endDate = (endDateInput && endDateInput.value) ? String(endDateInput.value) : '';
            return { startDate, endDate };
        }

        function getMonthYearFromStartDate(){
            const startDate = (startDateInput && startDateInput.value) ? String(startDateInput.value) : '';
            if(!startDate || !/^\d{4}-\d{2}-\d{2}$/.test(startDate)) return { month: '', year: '' };
            return {
                month: String(parseInt(startDate.slice(5, 7), 10)),
                year: startDate.slice(0, 4)
            };
        }

        function isValidDateRange(showErrors){
            const range = getSelectedDateRange();
            if(!range.startDate || !range.endDate){
                if(showErrors) showAlertModal('Please select both Start Date and End Date before running reconciliation.');
                return false;
            }
            if(range.startDate > range.endDate){
                if(showErrors) showAlertModal('Start Date cannot be greater than End Date.');
                return false;
            }
            return true;
        }

        const month = {
            get value(){
                return getMonthYearFromStartDate().month;
            }
        };
        const year = {
            get value(){
                return getMonthYearFromStartDate().year;
            }
        };
        const principalEl = document.getElementById('hsPrincipal');
        const commissionEl = document.getElementById('hsCommission');
        const varianceEl = document.getElementById('hsVariance');

        // Update the View Cover button to show a PESO label when WORLD INTERNATIONAL COMMUNICATIONS is selected and show View USD as well
        const _viewCoverBtn = document.getElementById('hsViewCoverPH');
        const _viewUsdBtn = document.getElementById('hsViewUsd');
        function refreshViewCoverButton(){
            if(!_viewCoverBtn) return;
            try{
                if(company && isWorldInternationalCommunications(company.value)){
                    _viewCoverBtn.textContent = 'View PESO';
                    _viewCoverBtn.setAttribute('data-company','PESO');
                    if(_viewUsdBtn) _viewUsdBtn.style.display = '';
                } else {
                    _viewCoverBtn.textContent = 'View Cover PHP';
                    _viewCoverBtn.removeAttribute('data-company');
                    if(_viewUsdBtn) _viewUsdBtn.style.display = 'none';
                }
            }catch(e){ /* ignore UI update errors */ }
        }
        if(company) company.addEventListener('change', refreshViewCoverButton);
        // initialize on load
        refreshViewCoverButton();

        const partners = <?= json_encode($partners, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
        const MBTC_PARTNER_NAME = 'METROBANK HEAD OFFICE';
        const WIC_PARTNER_NAME = 'WORLDCOM INTERNATIONAL COMMUNICATIONS';
        const RCBC_PARTNER_NAME = 'RCBC';
        
        // Show/Hide View Cover PHP button only when a valid corporate partner is selected
        (function(){
            const coverBtn = document.getElementById('hsViewCoverPH');
            function isValidPartner(name){
                if(!name) return false;
                const n = String(name).trim();
                if(!n) return false;
                try{ return (Array.isArray(partners) && partners.some(p => String(p).trim() === n)); }catch(e){ return false; }
            }
            function updateCoverVisibility(){
                if(!coverBtn) return;
                if(isValidPartner(company.value)){
                    coverBtn.style.display = '';
                } else {
                    coverBtn.style.display = 'none';
                }
            }
            // respond to user typing or clearing the input
            if(company){ company.addEventListener('input', updateCoverVisibility); company.addEventListener('change', updateCoverVisibility); }
            // initial state
            updateCoverVisibility();
        })();

        function attachPartnerAutocomplete(input, suggestions){
            const container = input ? input.closest('.autocomplete-field') : null;
            const list = container ? container.querySelector('.autocomplete-list') : null;
            if(!input || !container || !list) return;

            let activeIndex = -1;

            function normalize(value){
                return String(value || '').trim().toLowerCase();
            }

            function getMatches(value){
                const query = normalize(value);
                const options = Array.from(new Set((suggestions || []).map(item => String(item || '').trim()).filter(Boolean)));
                if(!query) return options.slice(0, 8);

                const startsWith = [];
                const contains = [];
                options.forEach(option => {
                    const normalizedOption = normalize(option);
                    if(normalizedOption.startsWith(query)) startsWith.push(option);
                    else if(normalizedOption.includes(query)) contains.push(option);
                });

                return startsWith.concat(contains).slice(0, 8);
            }

            function closeSuggestions(){
                list.hidden = true;
                list.innerHTML = '';
                activeIndex = -1;
            }

            function applyActiveItem(items){
                items.forEach((item, index) => item.classList.toggle('is-active', index === activeIndex));
            }

            function selectSuggestion(value){
                input.value = value;
                closeSuggestions();
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }

            function renderSuggestions(){
                const matches = getMatches(input.value);
                if(matches.length === 0){
                    closeSuggestions();
                    return;
                }

                list.innerHTML = '';
                matches.forEach((match, index) => {
                    const item = document.createElement('li');
                    item.className = 'autocomplete-item';
                    item.setAttribute('role', 'option');
                    item.textContent = match;
                    item.addEventListener('mousedown', function(event){
                        event.preventDefault();
                        selectSuggestion(match);
                    });
                    item.addEventListener('mouseenter', function(){
                        activeIndex = index;
                        applyActiveItem(Array.from(list.children));
                    });
                    list.appendChild(item);
                });
                activeIndex = -1;
                list.hidden = false;
            }

            input.addEventListener('input', renderSuggestions);
            input.addEventListener('focus', renderSuggestions);
            input.addEventListener('keydown', function(event){
                const items = Array.from(list.querySelectorAll('.autocomplete-item'));
                if(list.hidden || items.length === 0) return;

                if(event.key === 'ArrowDown'){
                    event.preventDefault();
                    activeIndex = (activeIndex + 1) % items.length;
                    applyActiveItem(items);
                } else if(event.key === 'ArrowUp'){
                    event.preventDefault();
                    activeIndex = activeIndex <= 0 ? items.length - 1 : activeIndex - 1;
                    applyActiveItem(items);
                } else if(event.key === 'Enter'){
                    if(activeIndex >= 0 && activeIndex < items.length){
                        event.preventDefault();
                        selectSuggestion(items[activeIndex].textContent || '');
                    }
                } else if(event.key === 'Escape'){
                    closeSuggestions();
                }
            });

            document.addEventListener('click', function(event){
                if(!container.contains(event.target)) closeSuggestions();
            });
        }

        function isMetrobankHeadOffice(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'MBTC' || normalized === MBTC_PARTNER_NAME;
        }

        function isWorldInternationalCommunications(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'WIC' || normalized === WIC_PARTNER_NAME;
        }

        function isRcbc(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'RCBC' || normalized === RCBC_PARTNER_NAME;
        }

        function isMoneygram(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'MONEYGRAM';
        }

        attachPartnerAutocomplete(company, partners);

        // Partner selection is now an input with a datalist (`hsCompany`)

        function seededRandom(seed){
            let x = Math.sin(seed) * 10000; return x - Math.floor(x);
        }

        function renderDays(){
            daysContainer.innerHTML = '';
            const companyName = company.value;
            const range = getSelectedDateRange();
            // If METROBANK HEAD OFFICE is selected, request recon from server. Fallback to demo data if fetch fails.
            if(isMetrobankHeadOffice(companyName)){
                loadMbtcRecon(companyName, range.startDate, range.endDate);
                return;
            }

            // If MONEYGRAM is selected, request recon from server.
            if(isMoneygram(companyName)){
                loadMoneygramRecon(companyName, range.startDate, range.endDate);
                return;
            }

            // If WORLD INTERNATIONAL COMMUNICATIONS is selected, request recon from server. Fallback to demo data if fetch fails.
            if(isWorldInternationalCommunications(companyName)){
                loadWicRecon(companyName, range.startDate, range.endDate);
                return;
            }

            // If RCBC is selected, request recon from server. Fallback to demo data if fetch fails.
            if(isRcbc(companyName)){
                loadRcbcRecon(companyName, range.startDate, range.endDate);
                return;
            }

            // deterministic pseudo-random for demo (based on strings)
            const seedBase = Array.from((companyName + (range.startDate||'') + (range.endDate||''))).reduce((s,ch)=>s+ch.charCodeAt(0),0);

            // generate mock summary values for non-mapped companies
            const principal = Math.floor(seededRandom(seedBase+1)*90000)+10000;
            const commission = Math.floor(seededRandom(seedBase+2)*9000)+1000;
            const variance = principal - commission;
            principalEl.textContent = principal.toLocaleString() + ' pesos';
            if(commissionEl) commissionEl.textContent = commission.toLocaleString() + ' pesos';
            if(varianceEl) varianceEl.textContent = variance.toLocaleString() + ' pesos';

            let daysInRange = 30;
            let rangeStartObj = null;
            if(range.startDate && range.endDate){
                rangeStartObj = new Date(range.startDate + 'T00:00:00');
                const rangeEndObj = new Date(range.endDate + 'T00:00:00');
                if(!isNaN(rangeStartObj.getTime()) && !isNaN(rangeEndObj.getTime()) && rangeStartObj <= rangeEndObj){
                    daysInRange = Math.floor((rangeEndObj - rangeStartObj) / 86400000) + 1;
                }
            }

            for (let i=1; i<=daysInRange; i++){
                const r = seededRandom(seedBase + i);
                // threshold: >0.75 green, 0.4-0.75 white (no data), <0.4 red
                const state = r > 0.75 ? 'green' : (r > 0.4 ? 'white' : 'red');

                // partner is the selected company (no data when white)
                const partnerName = state === 'white' ? '' : companyName;

                // generate mock amounts
                const p = Math.floor(seededRandom(seedBase + i + 10) * 90000) + 1000;
                const commissionVal = Math.floor(p * (0.05 + seededRandom(seedBase + i + 20) * 0.15));
                const v = p - commissionVal;

                const displayDate = rangeStartObj ? new Date(rangeStartObj.getTime() + ((i - 1) * 86400000)) : null;
                const monthLabel = displayDate && !isNaN(displayDate.getTime()) ? MONTH_NAMES[displayDate.getMonth()] : '';
                const dayNumber = displayDate && !isNaN(displayDate.getTime()) ? displayDate.getDate() : i;
                const dateKey = displayDate && !isNaN(displayDate.getTime())
                    ? (displayDate.getFullYear() + '-' + String(displayDate.getMonth()+1).padStart(2,'0') + '-' + String(displayDate.getDate()).padStart(2,'0'))
                    : '';

                const card = document.createElement('div');
                card.className = 'day-card day-' + state;
                card.setAttribute('data-day', i);
                if(dateKey) card.setAttribute('data-date', dateKey);

                const badge = document.createElement('div');
                badge.className = 'day-badge';
                badge.innerHTML = '<div class="badge-month">'+(monthLabel||'')+'</div><div class="badge-day">'+dayNumber+'</div>';

                const meta = document.createElement('div');
                meta.className = 'meta';
                const partnerEl = document.createElement('div');
                partnerEl.className = 'partner';
                partnerEl.textContent = partnerName || 'No Data';
                const amounts = document.createElement('div');
                amounts.className = 'amounts';
                const principalElCard = document.createElement('div');
                principalElCard.className = 'amount principal';
                principalElCard.textContent = 'Principal: ' + p.toLocaleString() + ' pesos';
                const commissionElCard = document.createElement('div');
                commissionElCard.className = 'amount commission';
                commissionElCard.textContent = 'Commission: ' + commissionVal.toLocaleString() + ' pesos';
                amounts.appendChild(principalElCard);
                amounts.appendChild(commissionElCard);

                meta.appendChild(partnerEl);
                meta.appendChild(amounts);

                if (state === 'red'){
                    card.setAttribute('data-tooltip','Missing Reference No. for ML Web Data/Partners Data\nAmount not Match\nWrong Date File\nWrong Partner Name');
                }

                card.appendChild(badge);
                card.appendChild(meta);

                daysContainer.appendChild(card);
            }
        }

        // controller to cancel in-flight MBTC recon fetches
        let _mbtcReconController = null;
        // controller to cancel in-flight WORLD INTERNATIONAL COMMUNICATIONS recon fetches
        let _wicReconController = null;

        // Load MBTC recon from server and render cards
        async function loadMbtcRecon(companyName, startDate, endDate){
            daysContainer.innerHTML = '';
            let daysInRange = 30;
            if(startDate && endDate){
                const startObj = new Date(startDate + 'T00:00:00');
                const endObj = new Date(endDate + 'T00:00:00');
                if(!isNaN(startObj.getTime()) && !isNaN(endObj.getTime()) && startObj <= endObj){
                    daysInRange = Math.floor((endObj - startObj) / 86400000) + 1;
                }
            }
            // show loading skeleton immediately
            const loading = document.createElement('div');
            loading.textContent = 'Loading recon...';
            loading.style.padding = '1rem';
            daysContainer.appendChild(loading);

            // cancel previous fetch if running
            if(_mbtcReconController){ try{ _mbtcReconController.abort(); }catch(e){} }
            _mbtcReconController = new AbortController();
            const signal = _mbtcReconController.signal;

            const url = location.origin + '/autorecon/src/controllers/recon/mbtc-recon.php?start_date='+encodeURIComponent(startDate || '')+'&end_date='+encodeURIComponent(endDate || '')+'&partnerName='+encodeURIComponent(companyName || 'METROBANK HEAD OFFICE');
            let data = null;
            try{
                const res = await fetch(url, { method: 'GET', credentials: 'same-origin', signal });
                if(res && res.ok){
                    const txt = await res.text();
                    try{ data = JSON.parse(txt); }
                    catch(e){ console.warn('mbtc recon returned non-json', txt); }
                } else if(res && res.status === 204){
                    // no content
                    data = { success:true, days: [] };
                }
            }catch(e){
                if(e.name === 'AbortError'){
                    // fetch aborted because user changed selection — exit silently
                    return;
                }
                console.warn('mbtc recon fetch failed', e);
            } finally {
                _mbtcReconController = null;
            }

            // If server didn't return usable data, fallback to seeded demo (white cards)
            if(!data || !Array.isArray(data.days)){
                console.warn('Falling back to demo recon for MBTC');
                const days = [];
                for(let d=1; d<=daysInRange; d++) days.push({ day: d, status: 'white' });
                // store fallback days for modal access
                window._lastMbtcDays = days;
                renderReconDays(daysInRange, days);
                await fetchDayCardLocks(startDate, endDate);
                principalEl.textContent = '0 pesos';
                if(commissionEl) commissionEl.textContent = '0 pesos';
                if(varianceEl) varianceEl.textContent = 'Not yet computed';
                return;
            }

            // store recon days so modal drill-down can access detailed diagnostics
            window._lastMbtcDays = data.days || [];
            renderReconDays((data.days || []).length || daysInRange, data.days || []);
            await fetchDayCardLocks(startDate, endDate);
        }

        // Load MONEYGRAM recon from server and render cards
        async function loadMoneygramRecon(companyName, startDate, endDate){
            daysContainer.innerHTML = '';
            let daysInRange = 30;
            if(startDate && endDate){
                const startObj = new Date(startDate + 'T00:00:00');
                const endObj = new Date(endDate + 'T00:00:00');
                if(!isNaN(startObj.getTime()) && !isNaN(endObj.getTime()) && startObj <= endObj){
                    daysInRange = Math.floor((endObj - startObj) / 86400000) + 1;
                }
            }

            const loading = document.createElement('div');
            loading.textContent = 'Loading recon...';
            loading.style.padding = '1rem';
            daysContainer.appendChild(loading);

            if(_mbtcReconController){ try{ _mbtcReconController.abort(); }catch(e){} }
            _mbtcReconController = new AbortController();
            const signal = _mbtcReconController.signal;

            const url = location.origin + '/autorecon/src/controllers/recon/moneygram-recon.php?start_date='+encodeURIComponent(startDate || '')+'&end_date='+encodeURIComponent(endDate || '')+'&partnerName='+encodeURIComponent(companyName || 'MONEYGRAM');
            let data = null;
            try{
                const res = await fetch(url, { method: 'GET', credentials: 'same-origin', signal });
                if(res && res.ok){
                    const txt = await res.text();
                    try{ data = JSON.parse(txt); }
                    catch(e){ console.warn('moneygram recon returned non-json', txt); }
                } else if(res && res.status === 204){
                    data = { success:true, days: [] };
                }
            }catch(e){
                if(e.name === 'AbortError') return;
                console.warn('moneygram recon fetch failed', e);
            } finally {
                _mbtcReconController = null;
            }

            if(!data || !Array.isArray(data.days)){
                console.warn('Falling back to demo recon for MONEYGRAM');
                const days = [];
                for(let d=1; d<=daysInRange; d++) days.push({ day: d, status: 'white' });
                window._lastMbtcDays = days;
                renderReconDays(daysInRange, days);
                await fetchDayCardLocks(startDate, endDate);
                principalEl.textContent = '0 pesos';
                if(commissionEl) commissionEl.textContent = '0 pesos';
                if(varianceEl) varianceEl.textContent = 'Not yet computed';
                return;
            }

            window._lastMbtcDays = data.days || [];
            renderReconDays((data.days || []).length || daysInRange, data.days || []);
            await fetchDayCardLocks(startDate, endDate);
        }

        // Load WORLD INTERNATIONAL COMMUNICATIONS recon from server and render cards (mirrors MBTC loader but hits wic-recon.php)
        async function loadWicRecon(companyName, startDate, endDate){
            daysContainer.innerHTML = '';
            const my = getMonthYearFromStartDate();
            const m = my.month;
            const y = my.year;
            const daysInMonth = (y && m) ? new Date(parseInt(y,10), parseInt(m,10), 0).getDate() : 30;
            const loading = document.createElement('div');
            loading.textContent = 'Loading recon...';
            loading.style.padding = '1rem';
            daysContainer.appendChild(loading);

            if(_wicReconController){ try{ _wicReconController.abort(); }catch(e){} }
            _wicReconController = new AbortController();
            const signal = _wicReconController.signal;

            const url = location.origin + '/autorecon/src/controllers/recon/wic-recon.php?month='+encodeURIComponent(m)+'&year='+encodeURIComponent(y)+'&partnerName='+encodeURIComponent(companyName || 'WORLDCOM INTERNATIONAL COMMUNICATIONS');
            let data = null;
            try{
                const res = await fetch(url, { method: 'GET', credentials: 'same-origin', signal });
                if(res && res.ok){
                    const txt = await res.text();
                    try{ data = JSON.parse(txt); }
                    catch(e){ console.warn('wic recon returned non-json', txt); }
                } else if(res && res.status === 204){
                    data = { success:true, days: [] };
                }
            }catch(e){
                if(e.name === 'AbortError'){
                    return;
                }
                console.warn('wic recon fetch failed', e);
            } finally {
                _wicReconController = null;
            }

            if(!data || !Array.isArray(data.days)){
                console.warn('Falling back to demo recon for WORLD INTERNATIONAL COMMUNICATIONS');
                const days = [];
                for(let d=1; d<=daysInMonth; d++) days.push({ day: d, status: 'white' });
                window._lastMbtcDays = days;
                renderReconDays(daysInMonth, days);
                await fetchDayCardLocks(startDate, endDate);
                principalEl.textContent = '0 pesos';
                if(commissionEl) commissionEl.textContent = '0 pesos';
                if(varianceEl) varianceEl.textContent = 'Not yet computed';
                return;
            }

            // store recon days so modal drill-down can access detailed diagnostics (reuses MBTC modal plumbing)
            window._lastMbtcDays = data.days || [];
            renderReconDays(daysInMonth, data.days || []);
            await fetchDayCardLocks(startDate, endDate);
        }

        // Load RCBC recon from server and render cards (mirrors WIC loader but hits rcbc-recon.php)
        async function loadRcbcRecon(companyName, startDate, endDate){
            daysContainer.innerHTML = '';
            const my = getMonthYearFromStartDate();
            const m = my.month;
            const y = my.year;
            const daysInMonth = (y && m) ? new Date(parseInt(y,10), parseInt(m,10), 0).getDate() : 30;
            const loading = document.createElement('div');
            loading.textContent = 'Loading recon...';
            loading.style.padding = '1rem';
            daysContainer.appendChild(loading);

            // cancel previous fetches (reuse controllers where appropriate)
            if(_wicReconController){ try{ _wicReconController.abort(); }catch(e){} }
            // reuse controller variable for simplicity
            _wicReconController = new AbortController();
            const signal = _wicReconController.signal;

            const url = location.origin + '/autorecon/src/controllers/recon/rcbc-recon.php?month='+encodeURIComponent(m)+'&year='+encodeURIComponent(y);
            let data = null;
            try{
                const res = await fetch(url, { method: 'GET', credentials: 'same-origin', signal });
                if(res && res.ok){
                    const txt = await res.text();
                    try{ data = JSON.parse(txt); }
                    catch(e){ console.warn('rcbc recon returned non-json', txt); }
                } else if(res && res.status === 204){
                    data = { success:true, days: [] };
                }
            }catch(e){
                if(e.name === 'AbortError') return;
                console.warn('rcbc recon fetch failed', e);
            } finally { _wicReconController = null; }

            if(!data || !Array.isArray(data.days)){
                console.warn('Falling back to demo recon for RCBC');
                const days = [];
                for(let d=1; d<=daysInMonth; d++) days.push({ day: d, status: 'white' });
                window._lastMbtcDays = days;
                renderReconDays(daysInMonth, days);
                await fetchDayCardLocks(startDate, endDate);
                principalEl.textContent = '0 pesos';
                if(commissionEl) commissionEl.textContent = '0 pesos';
                if(varianceEl) varianceEl.textContent = 'Not yet computed';
                return;
            }

            window._lastMbtcDays = data.days || [];
            renderReconDays(daysInMonth, data.days || []);
            await fetchDayCardLocks(startDate, endDate);
        }

        function renderReconDays(daysInMonth, dayDataArray){
            daysContainer.innerHTML = '';

            const range = getSelectedDateRange();
            const startDate = String(range.startDate || '');
            const endDate = String(range.endDate || '');
            const fallbackMonthYear = getMonthYearFromStartDate();

            function toIsoDate(value){
                const raw = String(value || '').trim();
                if(!raw) return '';
                const match = raw.match(/^(\d{4}-\d{2}-\d{2})/);
                if(match) return match[1];
                const parsed = new Date(raw);
                if(isNaN(parsed.getTime())) return '';
                return parsed.getFullYear() + '-' + String(parsed.getMonth() + 1).padStart(2, '0') + '-' + String(parsed.getDate()).padStart(2, '0');
            }

            function inSelectedRange(isoDate){
                if(!isoDate || !startDate || !endDate) return false;
                return isoDate >= startDate && isoDate <= endDate;
            }

            const normalizedDays = (Array.isArray(dayDataArray) ? dayDataArray : []).map((item) => {
                const dayObj = Object.assign({}, item || {});
                let dateKey = toIsoDate(dayObj.date || dayObj.transaction_date || dayObj.tran_date || dayObj.cover_date || dayObj.date_claimed);

                if(!dateKey){
                    const dayNum = parseInt(dayObj.day, 10);
                    const monthNum = parseInt(fallbackMonthYear.month, 10);
                    const yearNum = parseInt(fallbackMonthYear.year, 10);
                    if(Number.isFinite(dayNum) && Number.isFinite(monthNum) && Number.isFinite(yearNum) && dayNum > 0){
                        dateKey = yearNum + '-' + String(monthNum).padStart(2, '0') + '-' + String(dayNum).padStart(2, '0');
                    }
                }

                dayObj.date = dateKey;
                return dayObj;
            });

            const visibleDays = normalizedDays
                .filter((dayObj) => inSelectedRange(String(dayObj.date || '')))
                .sort((a, b) => {
                    const aDate = String(a.date || '');
                    const bDate = String(b.date || '');
                    if(aDate === bDate) return (parseInt(a.day, 10) || 0) - (parseInt(b.day, 10) || 0);
                    return aDate.localeCompare(bDate);
                });

            window._lastMbtcDays = visibleDays;
            _lastMbtcDays = visibleDays;

            if(visibleDays.length === 0){
                const empty = document.createElement('div');
                empty.className = 'day-card day-white';
                empty.style.padding = '16px 18px';
                empty.style.fontWeight = '600';
                empty.style.color = '#4b5563';
                empty.textContent = 'No records found for selected date range.';
                daysContainer.appendChild(empty);
                principalEl.textContent = '0 pesos';
                if(commissionEl) commissionEl.textContent = '0 pesos';
                if(varianceEl) varianceEl.textContent = '0 pesos';
                return;
            }

            let totalPrincipal = 0;
            let totalCommission = 0;
            let totalVariance = 0;

            visibleDays.forEach((dayObj, index) => {
                const state = dayObj.status || 'white';
                const card = document.createElement('div');
                card.className = 'day-card day-' + state;
                card.setAttribute('data-day', String(parseInt(dayObj.day, 10) || (index + 1)));
                if(dayObj.date) card.setAttribute('data-date', String(dayObj.date));
                const selectedPartnerLockKey = getSelectedPartnerLockKey();
                if(selectedPartnerLockKey) card.setAttribute('data-partner', selectedPartnerLockKey);

                const badge = document.createElement('div');
                badge.className = 'day-badge';

                let monthLabel = '';
                let dayLabel = parseInt(dayObj.day, 10) || (index + 1);
                try{
                    if(dayObj.date){
                        const dt = new Date(dayObj.date + 'T00:00:00');
                        if(!isNaN(dt.getTime())){
                            monthLabel = MONTH_NAMES[dt.getMonth()];
                            dayLabel = dt.getDate();
                        }
                    }
                }catch(e){}
                badge.innerHTML = '<div class="badge-month">' + (monthLabel || '') + '</div><div class="badge-day">' + dayLabel + '</div>';

                const meta = document.createElement('div');
                meta.className = 'meta';
                const partnerEl = document.createElement('div');
                partnerEl.className = 'partner';
                const selectedCompanyName = (company && company.value) ? String(company.value).trim() : '';
                const fallbackPartnerLabel = isWorldInternationalCommunications(selectedCompanyName)
                    ? WIC_PARTNER_NAME
                    : (isMetrobankHeadOffice(selectedCompanyName) ? MBTC_PARTNER_NAME : (selectedCompanyName || MBTC_PARTNER_NAME));
                const dayPartnerName = String(dayObj.partner || '').trim();
                const normalizedDayPartnerName = isWorldInternationalCommunications(dayPartnerName)
                    ? WIC_PARTNER_NAME
                    : (isMetrobankHeadOffice(dayPartnerName) ? MBTC_PARTNER_NAME : dayPartnerName);
                partnerEl.textContent = (normalizedDayPartnerName || (state === 'white' ? 'No Data' : fallbackPartnerLabel));

                const amounts = document.createElement('div');
                amounts.className = 'amounts';
                const pVal = Number(dayObj.principal) || 0;
                const cVal = Number(dayObj.commission) || 0;
                const vVal = (dayObj.variance !== undefined && dayObj.variance !== null) ? Number(dayObj.variance) : null;

                const principalElCard = document.createElement('div');
                principalElCard.className = 'amount principal';
                principalElCard.textContent = 'Principal: ' + (pVal ? pVal.toLocaleString() + ' pesos' : '0 pesos');
                const commissionElCard = document.createElement('div');
                commissionElCard.className = 'amount commission';
                commissionElCard.textContent = 'Commission: ' + (cVal ? cVal.toLocaleString() + ' pesos' : '0 pesos');
                amounts.appendChild(principalElCard);
                amounts.appendChild(commissionElCard);

                meta.appendChild(partnerEl);
                meta.appendChild(amounts);

                let tooltip = '';
                try{
                    const missingWeb = Array.isArray(dayObj.missing_web_refs) ? dayObj.missing_web_refs : [];
                    const missingPartner = Array.isArray(dayObj.missing_partner_refs) ? dayObj.missing_partner_refs : [];
                    const mismatches = Array.isArray(dayObj.mismatches) ? dayObj.mismatches : [];
                    const duplicates = Array.isArray(dayObj.duplicates) ? dayObj.duplicates : [];

                    const parts = [];
                    if(duplicates.length > 0) parts.push('Duplicates: ' + duplicates.map(d=>d.ref+'('+d.type+':'+d.count+')').slice(0,8).join(', '));
                    if(missingWeb.length > 0) parts.push('Missing web refs: ' + missingWeb.slice(0,8).join(', ') + (missingWeb.length>8?(' +'+(missingWeb.length-8)+' more'):''));
                    if(missingPartner.length > 0) parts.push('Missing partner refs: ' + missingPartner.slice(0,8).join(', ') + (missingPartner.length>8?(' +'+(missingPartner.length-8)+' more'):''));
                    if(mismatches.length > 0) parts.push('Amount mismatches: ' + mismatches.slice(0,6).map(mm=> mm.ref + ' (P:'+mm.partner_principal+' W:'+mm.web_amount+' | CTP P:'+mm.partner_commission+' W:'+mm.web_ctp +')').join(', '));

                    if(parts.length > 0) tooltip = parts.join('\n');
                    else {
                        if(state === 'red') tooltip = 'Mismatch detected';
                        else if(state === 'white') tooltip = 'No matching web data uploaded for this cover date';
                        else if(state === 'green') tooltip = 'Matched / Reconciled';
                        else if(state === 'yellow') tooltip = 'Duplicate Reference No / CCREF';
                    }
                }catch(e){
                    if(state === 'red') tooltip = 'Mismatch: Missing Reference No / Amount mismatch';
                    else if(state === 'white') tooltip = 'No matching web data uploaded for this cover date';
                    else if(state === 'green') tooltip = 'Matched / Reconciled';
                    else if(state === 'yellow') tooltip = 'Duplicate Reference No / CCREF';
                }
                if(tooltip) card.setAttribute('data-tooltip', tooltip);

                if(state === 'yellow'){
                    card.style.background = '#fff8e1';
                    card.style.border = '1px solid #ffe082';
                    badge.style.background = '#ffcc80';
                    badge.style.color = '#422800';
                }

                card.appendChild(badge);
                card.appendChild(meta);
                daysContainer.appendChild(card);

                totalPrincipal += pVal;
                totalCommission += cVal;
                const fallbackDayVariance = Number(dayObj.total_partner_amount || 0) - Number(dayObj.total_web_amount || 0);
                totalVariance += (vVal !== null && !isNaN(vVal)) ? vVal : (Number.isFinite(fallbackDayVariance) ? fallbackDayVariance : 0);
            });

            principalEl.textContent = totalPrincipal.toLocaleString() + ' pesos';
            if(commissionEl) commissionEl.textContent = totalCommission.toLocaleString() + ' pesos';
            if(varianceEl) varianceEl.textContent = Number(totalVariance || 0).toLocaleString() + ' pesos';

            try{ markUsdDays(); }catch(e){ /* ignore */ }
        }

        // style for USD-flagged day cards (sky-blue)
        (function(){
            const s = document.createElement('style');
            s.textContent = '.day-card.day-usd{ background:#e1f5fe; border:1px solid #81d4fa } .day-card.day-usd .day-badge{ background:#03a9f4; color:#fff }';
            document.head.appendChild(s);
        })();

        // check a single day for USD rows by fetching detail=1 from the WORLD INTERNATIONAL COMMUNICATIONS recon endpoint
        async function checkDayHasUsd(dayNum){
            try{
                const companyName = (company && company.value) ? String(company.value).toUpperCase() : '';
            if(!isWorldInternationalCommunications(companyName)) return false;
                const mVal = (month && month.value) ? month.value : '';
                const yVal = (year && year.value) ? year.value : '';
                const url = location.origin + '/autorecon/src/controllers/recon/wic-recon.php?month='+encodeURIComponent(mVal)+'&year='+encodeURIComponent(yVal)+'&day='+encodeURIComponent(dayNum)+'&detail=1'+'&partnerName='+encodeURIComponent(companyName || 'WORLDCOM INTERNATIONAL COMMUNICATIONS');
                const resp = await fetch(url, { method: 'GET', credentials: 'same-origin' });
                if(!resp || !resp.ok) return false;
                const json = await resp.json();
                let dayObj = null;
                if(json && json.day) dayObj = json.day;
                else if(json && Array.isArray(json.days)) dayObj = json.days.find(dd=>String(dd.day)===String(dayNum));
                if(!dayObj) return false;
                const rows = Array.isArray(dayObj.rows) ? dayObj.rows : [];
                if(!rows.length) return false;
                const candidates = ['web_currency','partner_coin','coin','currency','web_coin','partner_currency','partner_currency_code'];
                for(const r of rows){
                    for(const key of candidates){
                        const val = r[key] || r[key.toUpperCase()] || r[key.toLowerCase()];
                        if(!val) continue;
                        if(String(val).toUpperCase().includes('USD')) return true;
                    }
                    // also try scanning all values for a USD substring as a fallback
                    for(const k in r){
                        try{ if(String(r[k]).toUpperCase().includes('USD')) return true; }catch(e){}
                    }
                }
            }catch(e){ console.warn('checkDayHasUsd error', e); }
            return false;
        }

        // mark all currently-rendered red day-cards that contain USD rows
        async function markUsdDays(){
            try{
                const cards = Array.from(daysContainer.querySelectorAll('.day-card.day-red'));
                await Promise.all(cards.map(async card => {
                    const d = card.getAttribute('data-day');
                    if(!d) return;
                    try{
                        const hasUsd = await checkDayHasUsd(d);
                        if(hasUsd){
                            card.classList.remove('day-red');
                            card.classList.add('day-usd');
                            // show a clear tooltip indicating matched status and USD content
                            card.setAttribute('data-tooltip', 'Matched / Reconciled\nContains USD Transactions');
                        }
                    }catch(e){}
                }));
            }catch(e){ /* ignore */ }
        }

        function attachTooltipHandlers(){
            daysContainer.addEventListener('mouseover', function(e){
                const t = e.target.closest && e.target.closest('.day-card[data-tooltip]');
                if (!t) return;
                // handled purely with CSS; nothing to do here
            });
        }

        // Do NOT automatically run reconciliation when inputs change.
        // User must click the Reconcile button to fetch/update data.
        attachTooltipHandlers();

        // store last fetched recon data for modal drill-down
        let _lastMbtcDays = null;

        // expose function to open modal for a given day
        function openMbtcReconModalForDay(dayNum){
            if(!window._lastMbtcDays && !_lastMbtcDays) return;
            const days = _lastMbtcDays || window._lastMbtcDays || [];
            const dayObj = days.find(dd => parseInt(dd.day,10) === parseInt(dayNum,10)) || { status: 'white', day: dayNum };
            openMbtcReconModal(dayObj);
        }

        // click handler for day cards: show loading overlay, fetch details if needed, then open modal
        daysContainer.addEventListener('click', async function(e){
            const card = e.target.closest && e.target.closest('.day-card');
            // locked days remain viewable, but the lock state is persisted in the database
            
            if(!card) return;
            const d = card.getAttribute('data-day');
            if(!d) return;

            // create a lightweight full-page loading overlay so the page remains responsive
            const overlay = document.createElement('div');
            overlay.className = 'mbtc-global-loading';
            Object.assign(overlay.style, {
                position: 'fixed', top: '0', left: '0', right: '0', bottom: '0',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                background: 'rgba(0,0,0,0.18)', color: '#fff', zIndex: 99999,
                fontSize: '1.1rem'
            });
            overlay.textContent = 'Loading recon details — please wait…';
            document.body.appendChild(overlay);

            try{
                // try to use cached day diagnostics first
                const daysArr = window._lastMbtcDays || _lastMbtcDays || [];
                let dayObj = daysArr.find(dd => String(dd.day) === String(d));

                // if cached data is missing detailed row-level matches, fetch from server
                // Previously we skipped fetching when empty diagnostics arrays existed (e.g. fully matched days),
                // which caused green (matched) cards to open the modal with no `rows`/volume. Fetch when `rows` is absent.
                const needsFetch = !dayObj || !Array.isArray(dayObj.rows);
                if(needsFetch){
                    const mVal = (month && month.value) ? month.value : '';
                    const yVal = (year && year.value) ? year.value : '';
                    const range = getSelectedDateRange();
                    const companyName = (company && company.value) ? String(company.value).toUpperCase() : '';
                    const reconFile = isWorldInternationalCommunications(companyName) ? 'wic-recon.php' : (isMetrobankHeadOffice(companyName) ? 'mbtc-recon.php' : (isMoneygram(companyName) ? 'moneygram-recon.php' : (isRcbc(companyName) ? 'rcbc-recon.php' : 'mbtc-recon.php')));
                    const selectedDate = (dayObj && dayObj.date) ? String(dayObj.date) : (card.getAttribute('data-date') || '');
                    const url = (isMetrobankHeadOffice(companyName) || isMoneygram(companyName))
                        ? (location.origin + '/autorecon/src/controllers/recon/' + reconFile + '?start_date=' + encodeURIComponent(range.startDate || '') + '&end_date=' + encodeURIComponent(range.endDate || '') + '&date=' + encodeURIComponent(selectedDate || '') + '&day=' + encodeURIComponent(d) + '&detail=1' + '&partnerName=' + encodeURIComponent(companyName || ''))
                        : (location.origin + '/autorecon/src/controllers/recon/' + reconFile + '?month=' + encodeURIComponent(mVal) + '&year=' + encodeURIComponent(yVal) + '&day=' + encodeURIComponent(d) + '&detail=1' + '&partnerName=' + encodeURIComponent(companyName || ''));
                    try{
                        const resp = await fetch(url, { method: 'GET', credentials: 'same-origin' });
                        if(resp.ok){
                            const json = await resp.json();
                            // server may return { day: {...} } or { days: [...] }
                            if(json && json.day) dayObj = json.day;
                            else if(json && Array.isArray(json.days)) dayObj = json.days.find(dd=>String(dd.day)===String(d)) || dayObj || { day: d };
                            else if(json && json.days && json.days.length) dayObj = json.days[0];
                        } else {
                            console.warn('Failed to fetch day details', resp.status);
                        }
                    }catch(fetchErr){
                        console.warn('Error fetching day details', fetchErr);
                    }
                }

                const companyName = (company && company.value) ? String(company.value) : '';
                // If MONEYGRAM is selected, open the MONEYGRAM recon modal in the same format as the day-card image.
                if(isMoneygram(companyName)){
                    try{
                        const modal = document.getElementById('moneygramReconViewModal');
                        if(modal){
                            const loadingEl = modal.querySelector('.moneygram-recon-modal__loading'); if(loadingEl) loadingEl.style.display = 'flex';
                            modal.style.display = 'block'; try{ document.body.style.overflow = 'hidden'; }catch(e){}
                            // expose partner/date to modal for row-level operations
                            try{ modal.dataset.reconDate = String(dayObj.date || ''); modal.dataset.partnerName = String(companyName || ''); }catch(_e){}

                            {
                                const m = Number(dayObj.matchedCount || 0);
                                const u = Number(dayObj.unmatchedCount || 0);
                                const mLabel = (m === 1) ? 'transaction' : 'transactions';
                                const uLabel = (u === 1) ? 'transaction' : 'transactions';
                                const el = modal.querySelector('[data-role="summary"]');
                                if(el) el.innerHTML = `<span class="recon-summary__item">Matched: ${m.toLocaleString()} ${mLabel}</span><span class="recon-summary__sep">|</span><span class="recon-summary__item">Not Matched: ${u.toLocaleString()} ${uLabel}</span>`;
                            }

                            const partnersBody = modal.querySelector('[data-role="partnersBody"]');
                            const webBody = modal.querySelector('[data-role="webBody"]');
                            partnersBody.innerHTML = '';
                            webBody.innerHTML = '';

                            const normalizeKey = function(v){ return String(v || '').trim().toUpperCase(); };

                            const alignedPairs = [];
                            const partnerBucket = new Map();
                            const webBucket = new Map();
                            const orderedKeys = [];
                            const partnerNoKey = [];
                            const webNoKey = [];
                            const duplicateRefs = new Set(
                                (Array.isArray(dayObj.duplicates) ? dayObj.duplicates : [])
                                    .map(dd => normalizeKey(dd && dd.ref ? dd.ref : ''))
                                    .filter(Boolean)
                            );

                            const pushOrderedKey = function(k){
                                if(!k) return;
                                if(orderedKeys.indexOf(k) === -1) orderedKeys.push(k);
                            };

                            const appendBucket = function(mapObj, key, rowObj){
                                if(!mapObj.has(key)) mapObj.set(key, []);
                                mapObj.get(key).push(rowObj);
                            };

                            const createPartnerRow = function(obj){
                                const tr = document.createElement('tr');
                                if(obj && obj.placeholder){
                                    tr.classList.add('row-placeholder');
                                    tr.innerHTML = '<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>';
                                    tr.dataset.ref = '';
                                    tr.dataset.amount = '0';
                                    return tr;
                                }
                                const pDateRaw = obj.pDate || '';
                                const pDate = formatDateMMDDYYYY(pDateRaw);
                                const tx = obj.tx || '';
                                const pAmt = Number(obj.pAmt || 0);
                                // store raw amount (may be negative) for accurate totals
                                tr.dataset.amount = String(Number.isFinite(pAmt) ? pAmt : 0);
                                // display absolute value without negative sign, always show two decimals
                                const pAmtDisplay = Number.isFinite(pAmt) ? Math.abs(pAmt).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
                                const pCoin = obj.pCoin || '';
                                tr.dataset.ref = tx;
                                tr.innerHTML = `<td>${pDate}</td><td class="highlight-ref">${tx}</td><td>${pAmtDisplay}</td><td>${pCoin}</td>`;
                                return tr;
                            };

                            const createWebRow = function(obj){
                                const tr = document.createElement('tr');
                                if(obj && obj.placeholder){
                                    tr.classList.add('row-placeholder');
                                    tr.innerHTML = '<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>';
                                    tr.dataset.ref = '';
                                    tr.dataset.amount = '0';
                                    tr.dataset.currency = '';
                                    return tr;
                                }
                                const wRef = obj.wRef || '';
                                const wAmt = Number(obj.wAmt || 0);
                                const wDateRaw = obj.wDateRaw || '';
                                const wDate = formatDateMMDDYYYY(wDateRaw);
                                tr.dataset.amount = String(Number.isFinite(wAmt) ? wAmt : 0);
                                const wAmtDisplay = Number.isFinite(wAmt) ? Math.abs(wAmt).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
                                const wCurrency = obj.wCurrency || '';
                                tr.dataset.ref = wRef;
                                tr.dataset.currency = wCurrency;
                                tr.innerHTML = `<td>${wDate}</td><td class="highlight-ref">${wRef}</td><td>${wAmtDisplay}</td><td>${wCurrency? wCurrency: ''}</td>`;
                                return tr;
                            };

                            const addAlignedPair = function(partnerObj, webObj){
                                const pr = createPartnerRow(partnerObj || { placeholder: true });
                                const wr = createWebRow(webObj || { placeholder: true });

                                const pRefNorm = normalizeKey(partnerObj ? partnerObj.tx : '');
                                const wRefNorm = normalizeKey(webObj ? webObj.wRef : '');
                                const hasPartner = !!partnerObj;
                                const hasWeb = !!webObj;
                                const isMatch = hasPartner && hasWeb && pRefNorm && wRefNorm && pRefNorm === wRefNorm;
                                const isDuplicate = !!((pRefNorm && duplicateRefs.has(pRefNorm)) || (wRefNorm && duplicateRefs.has(wRefNorm)));

                                if(isDuplicate){
                                    pr.classList.add('dup-row');
                                    wr.classList.add('row-duplicate');
                                } else if(isMatch){
                                    pr.classList.add('matched-row');
                                    wr.classList.add('row-match');
                                } else {
                                    pr.classList.add('mismatch-row');
                                    wr.classList.add('row-mismatch');
                                }

                                const pairIdx = String(alignedPairs.length);
                                pr.dataset.pairIndex = pairIdx;
                                wr.dataset.pairIndex = pairIdx;

                                partnersBody.appendChild(pr);
                                webBody.appendChild(wr);
                                alignedPairs.push({
                                    partnerRow: pr,
                                    webRow: wr,
                                    ref: (partnerObj && partnerObj.tx) || (webObj && webObj.wRef) || '',
                                    isMismatch: !isMatch,
                                    isDuplicate: isDuplicate
                                });
                            };

                            if(Array.isArray(dayObj.rows) && dayObj.rows.length){
                                dayObj.rows.forEach(r => {
                                    const pDate = r.partner_tran_date || r.partner_date || r.partner_fx_date_trn || dayObj.date || '';
                                    const tx = r.partner_reference_id || r.partner_transaction_id || r.partner_reference_no || r.partner_ref_no || '';
                                    const pAmt = Number(r.partner_principal || r.partner_base_tran_amt || 0);
                                    const pCoin = r.partner_transaction_currency || r.partner_base_cncy || r.partner_currency || r.partner_coin || '';

                                    const wRef = r.web_ccref_no || r.web_cc_ref || r.web_ccref || r.web_ref || '';
                                    const wAmt = Number(r.web_amount || 0);
                                    const wCurrency = r.web_currency || r.web_ccy || r.web_currency_code || '';
                                    const wDateRaw = r.web_date_claimed || r.web_date_send || r.web_tran_date || r.web_date || '';

                                    const pObj = tx ? { pDate, tx, pAmt, pCoin } : null;
                                    const wObj = wRef ? { wRef, wAmt, wCurrency, wDateRaw } : null;

                                    if(pObj){
                                        const pKey = normalizeKey(tx);
                                        if(pKey){ appendBucket(partnerBucket, pKey, pObj); pushOrderedKey(pKey); }
                                        else partnerNoKey.push(pObj);
                                    }
                                    if(wObj){
                                        const wKey = normalizeKey(wRef);
                                        if(wKey){ appendBucket(webBucket, wKey, wObj); pushOrderedKey(wKey); }
                                        else webNoKey.push(wObj);
                                    }
                                });

                                orderedKeys.forEach(k => {
                                    const pList = partnerBucket.get(k) || [];
                                    const wList = webBucket.get(k) || [];
                                    const maxLen = Math.max(pList.length, wList.length);
                                    for(let i=0;i<maxLen;i++){
                                        addAlignedPair(pList[i] || null, wList[i] || null);
                                    }
                                });

                                const orphanMax = Math.max(partnerNoKey.length, webNoKey.length);
                                for(let i=0;i<orphanMax;i++) addAlignedPair(partnerNoKey[i] || null, webNoKey[i] || null);

                                // if nothing rendered, ensure empty-state rows are present (handled after wiring)
                            } else {
                                const mismatches = Array.isArray(dayObj.mismatches) ? dayObj.mismatches : [];
                                const missingWeb = Array.isArray(dayObj.missing_web_refs) ? dayObj.missing_web_refs : [];
                                const missingPartner = Array.isArray(dayObj.missing_partner_refs) ? dayObj.missing_partner_refs : [];

                                mismatches.forEach(mm => {
                                    addAlignedPair(
                                        {
                                            pDate: dayObj.date || '',
                                            tx: mm.ref || '',
                                            pAmt: Number(mm.partner_principal || 0),
                                            pCoin: mm.partner_transaction_currency || ''
                                        },
                                        {
                                            wRef: mm.ref || '',
                                            wAmt: Number(mm.web_amount || 0),
                                            wCurrency: mm.web_currency || mm.web_currency_code || mm.web_ccy || '',
                                            wDateRaw: mm.web_date_claimed || mm.web_date_send || mm.web_tran_date || mm.web_date || ''
                                        }
                                    );
                                });

                                missingWeb.forEach(ref => {
                                    addAlignedPair(
                                        { pDate: dayObj.date || '', tx: ref || '', pAmt: 0, pCoin: '' },
                                        null
                                    );
                                });

                                missingPartner.forEach(ref => {
                                    addAlignedPair(
                                        null,
                                        { wRef: ref || '', wAmt: 0, wCurrency: '' }
                                    );
                                });
                            }

                            try{
                                const partnersVolumeEl = modal.querySelector('[data-role="partnersVolume"]');
                                const webVolumeEl = modal.querySelector('[data-role="webVolume"]');
                                const partnersPrincipalPhpEl = modal.querySelector('[data-role="partnersPrincipalPhp"]');
                                const partnersPrincipalUsdEl = modal.querySelector('[data-role="partnersPrincipalUsd"]');
                                const webPrincipalPhpEl = modal.querySelector('[data-role="webPrincipalPhp"]');
                                const webPrincipalUsdEl = modal.querySelector('[data-role="webPrincipalUsd"]');
                                const pRows = Array.from(partnersBody.querySelectorAll('tr:not(.dup-sep)'));
                                const wRows = Array.from(webBody.querySelectorAll('tr:not(.dup-sep)'));
                                const pCount = pRows.length || 0;
                                // Count only web rows that have a non-empty CCREF (dataset.ref)
                                const wCount = wRows.filter(tr => String(tr.dataset.ref || '').trim() !== '').length || 0;
                                // compute per-currency totals
                                let pPhp = 0, pUsd = 0;
                                pRows.forEach(tr => {
                                    const raw = tr.dataset.amount !== undefined ? tr.dataset.amount : (((tr.cells[2] && tr.cells[2].textContent) || '').replace(/,/g, ''));
                                    const val = Number(raw);
                                    const cur = (tr.dataset.currency || (tr.cells[3] && tr.cells[3].textContent) || '').toString().trim().toUpperCase();
                                    if(!Number.isFinite(val)) return;
                                    if(cur.indexOf('PHP') !== -1){ pPhp += Math.abs(val); }
                                    else if(cur.indexOf('USD') !== -1){ pUsd += Math.abs(val); }
                                });

                                let wPhp = 0, wUsd = 0;
                                wRows.forEach(tr => {
                                    const raw = tr.dataset.amount !== undefined ? tr.dataset.amount : (((tr.cells[2] && tr.cells[2].textContent) || '').replace(/,/g, ''));
                                    const val = Number(raw);
                                    const cur = (tr.dataset.currency || (tr.cells[3] && tr.cells[3].textContent) || '').toString().trim().toUpperCase();
                                    if(!Number.isFinite(val)) return;
                                    if(cur.indexOf('PHP') !== -1){ wPhp += val; }
                                    else if(cur.indexOf('USD') !== -1){ wUsd += val; }
                                });
                                if(partnersVolumeEl) partnersVolumeEl.textContent = 'Volume: ' + (pCount.toLocaleString());
                                if(webVolumeEl) webVolumeEl.textContent = 'Volume: ' + (wCount.toLocaleString());
                                if(partnersPrincipalPhpEl) partnersPrincipalPhpEl.textContent = 'Principal PHP: ' + pPhp.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' pesos';
                                if(partnersPrincipalUsdEl) partnersPrincipalUsdEl.textContent = 'Principal USD: ' + pUsd.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                if(webPrincipalPhpEl) webPrincipalPhpEl.textContent = 'Principal PHP: ' + wPhp.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' pesos';
                                if(webPrincipalUsdEl) webPrincipalUsdEl.textContent = 'Principal USD: ' + wUsd.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            }catch(e){ console.warn('Error updating MONEYGRAM counts', e); }

                            // helper: ensure a centered 'No Data Found' row appears when a tbody is empty
                            const ensureEmptyRow = function(tbody, colspan, message){
                                if(!tbody) return;
                                Array.from(tbody.querySelectorAll('.empty-row')).forEach(el => el.remove());
                                const rows = Array.from(tbody.querySelectorAll('tr:not(.dup-sep)'));
                                const visible = rows.filter(tr => (tr.style.display !== 'none'));
                                if(visible.length === 0){
                                    const tr = document.createElement('tr');
                                    tr.className = 'empty-row';
                                    tr.innerHTML = `<td colspan="${colspan}" style="text-align:center;color:var(--muted);padding:20px 8px;">${message}</td>`;
                                    tbody.appendChild(tr);
                                }
                            };

                            try{
                                const searchEl = modal.querySelector('[data-role="resultSearch"]');
                                const filterEl = modal.querySelector('[data-role="resultFilter"]');
                                function modalRenderMoneygramRows(){
                                    const q = searchEl && searchEl.value ? String(searchEl.value).trim().toLowerCase() : '';
                                    const filter = filterEl && filterEl.value ? String(filterEl.value) : 'all';

                                    // Show/hide rows according to search + filter
                                    alignedPairs.forEach(pair => {
                                        const partnerRef = String((pair.partnerRow.querySelector('.highlight-ref')?.textContent) || (pair.partnerRow.cells[1]?.textContent) || '').toLowerCase();
                                        const webRef = String((pair.webRow.querySelector('.highlight-ref')?.textContent) || (pair.webRow.cells[1]?.textContent) || '').toLowerCase();

                                        // base visibility from search
                                        let show = true;
                                        if(q){
                                            const partnerMatch = partnerRef.includes(q);
                                            const webMatch = webRef.includes(q);
                                            show = partnerMatch || webMatch;
                                        }

                                        // apply filter: 'all' should include matched, mismatched and duplicates
                                        if(filter === 'matched') show = show && (!pair.isMismatch && !pair.isDuplicate);
                                        else if(filter === 'mismatch') show = show && pair.isMismatch;
                                        else if(filter === 'duplicates') show = show && pair.isDuplicate;
                                        // else 'all' -> no additional restriction

                                        pair.partnerRow.style.display = show ? '' : 'none';
                                        pair.webRow.style.display = show ? '' : 'none';
                                    });

                                    // Recompute visible counts and totals based on currently visible rows
                                    const visiblePairs = alignedPairs.filter(p => (p.partnerRow.style.display !== 'none' || p.webRow.style.display !== 'none'));

                                    function textVal(cell){ return cell ? String((cell.textContent || '')).replace(/\u00A0/g,'').trim() : ''; }
                                    function rowHasData(tr, side){
                                        if(!tr) return false;
                                        // consider dataset.ref (when present) or meaningful cell text in relevant columns
                                        const ref = String(tr.dataset.ref || '').trim();
                                        if(ref) return true;
                                        if(side === 'partner'){
                                            const d = textVal(tr.cells[0]);
                                            const r = textVal(tr.cells[1]);
                                            const a = textVal(tr.cells[2]);
                                            const c = textVal(tr.cells[3]);
                                            return Boolean(d || r || a || c);
                                        } else {
                                            const r = textVal(tr.cells[1]);
                                            const a = textVal(tr.cells[2]);
                                            const c = textVal(tr.cells[3]);
                                            return Boolean(r || a || c);
                                        }
                                    }

                                    const partnerVisible = alignedPairs.filter(p => (p.partnerRow.style.display !== 'none') && rowHasData(p.partnerRow, 'partner')).length;
                                    const webVisible = alignedPairs.filter(p => (p.webRow.style.display !== 'none') && rowHasData(p.webRow, 'web')).length;

                                    const matchedVisible = visiblePairs.filter(p => !p.isMismatch && !p.isDuplicate && (rowHasData(p.partnerRow,'partner') || rowHasData(p.webRow,'web'))).length;
                                    const mismatchVisible = visiblePairs.filter(p => p.isMismatch && !p.isDuplicate && (rowHasData(p.partnerRow,'partner') || rowHasData(p.webRow,'web'))).length;
                                    const duplicateVisible = visiblePairs.filter(p => p.isDuplicate && (rowHasData(p.partnerRow,'partner') || rowHasData(p.webRow,'web'))).length;

                                    // Update header summary and section metrics
                                    try{
                                        const summaryEl = modal.querySelector('[data-role="summary"]');
                                        if(summaryEl){
                                            const m = Number(matchedVisible || 0);
                                            const u = Number(mismatchVisible || 0);
                                            const mLabel = (m === 1) ? 'transaction' : 'transactions';
                                            const uLabel = (u === 1) ? 'transaction' : 'transactions';
                                            summaryEl.innerHTML = `<span class="recon-summary__item">Matched: ${m.toLocaleString()} ${mLabel}</span><span class="recon-summary__sep">|</span><span class="recon-summary__item">Not Matched: ${u.toLocaleString()} ${uLabel}</span>`;
                                        }

                                        const partnersVolumeEl = modal.querySelector('[data-role="partnersVolume"]');
                                        const webVolumeEl = modal.querySelector('[data-role="webVolume"]');
                                        const partnersPrincipalPhpEl = modal.querySelector('[data-role="partnersPrincipalPhp"]');
                                        const partnersPrincipalUsdEl = modal.querySelector('[data-role="partnersPrincipalUsd"]');
                                        const webPrincipalPhpEl = modal.querySelector('[data-role="webPrincipalPhp"]');
                                        const webPrincipalUsdEl = modal.querySelector('[data-role="webPrincipalUsd"]');

                                        if(partnersVolumeEl) partnersVolumeEl.textContent = 'Volume: ' + (partnerVisible.toLocaleString());
                                        if(webVolumeEl) webVolumeEl.textContent = 'Volume: ' + (webVisible.toLocaleString());

                                        // recompute principals from visible rows, split by currency
                                        let pPhp = 0, pUsd = 0;
                                        alignedPairs.forEach(p => {
                                            if(p.partnerRow.style.display === 'none') return;
                                            const raw = p.partnerRow.dataset.amount !== undefined ? p.partnerRow.dataset.amount : (((p.partnerRow.cells[2] && p.partnerRow.cells[2].textContent) || '').replace(/,/g, ''));
                                            const val = Number(raw);
                                            const cur = (p.partnerRow.dataset.currency || (p.partnerRow.cells[3] && p.partnerRow.cells[3].textContent) || '').toString().trim().toUpperCase();
                                            if(!Number.isFinite(val)) return;
                                            if(cur.indexOf('PHP') !== -1){ pPhp += Math.abs(val); }
                                            else if(cur.indexOf('USD') !== -1){ pUsd += Math.abs(val); }
                                        });
                                        let wPhp = 0, wUsd = 0;
                                        alignedPairs.forEach(p => {
                                            if(p.webRow.style.display === 'none') return;
                                            const raw = p.webRow.dataset.amount !== undefined ? p.webRow.dataset.amount : (((p.webRow.cells[2] && p.webRow.cells[2].textContent) || '').replace(/,/g, ''));
                                            const val = Number(raw);
                                            const cur = (p.webRow.dataset.currency || (p.webRow.cells[3] && p.webRow.cells[3].textContent) || '').toString().trim().toUpperCase();
                                            if(!Number.isFinite(val)) return;
                                            if(cur.indexOf('PHP') !== -1){ wPhp += val; }
                                            else if(cur.indexOf('USD') !== -1){ wUsd += val; }
                                        });
                                        if(modal.querySelector('[data-role="partnersPrincipalPhp"]')) modal.querySelector('[data-role="partnersPrincipalPhp"]').textContent = 'Principal PHP: ' + pPhp.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' pesos';
                                        if(modal.querySelector('[data-role="partnersPrincipalUsd"]')) modal.querySelector('[data-role="partnersPrincipalUsd"]').textContent = 'Principal USD: ' + pUsd.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                        if(modal.querySelector('[data-role="webPrincipalPhp"]')) modal.querySelector('[data-role="webPrincipalPhp"]').textContent = 'Principal PHP: ' + wPhp.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' pesos';
                                        if(modal.querySelector('[data-role="webPrincipalUsd"]')) modal.querySelector('[data-role="webPrincipalUsd"]').textContent = 'Principal USD: ' + wUsd.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    }catch(e){ console.warn('Error updating MONEYGRAM counts after filter', e); }

                                    // Manage empty-state placeholders when a filter yields nothing
                                    if(filter === 'duplicates'){
                                        if(partnerVisible === 0) ensureEmptyRow(partnersBody, 4, 'No Data Found');
                                        else Array.from(partnersBody.querySelectorAll('.empty-row')).forEach(el => el.remove());

                                        if(webVisible === 0) ensureEmptyRow(webBody, 4, 'No Data Found');
                                        else Array.from(webBody.querySelectorAll('.empty-row')).forEach(el => el.remove());
                                    } else {
                                        Array.from(partnersBody.querySelectorAll('.empty-row')).forEach(el => el.remove());
                                        Array.from(webBody.querySelectorAll('.empty-row')).forEach(el => el.remove());
                                    }
                                }

                                const resetMoneygramState = function(){
                                    if(searchEl) searchEl.value = '';
                                    modal._moneygramLastSearchValue = '';
                                    if(modal._moneygramDebounceTimer){
                                        try{ clearTimeout(modal._moneygramDebounceTimer); }catch(_e){}
                                        modal._moneygramDebounceTimer = null;
                                    }
                                    alignedPairs.forEach(pair => {
                                        pair.partnerRow.style.display = '';
                                        pair.webRow.style.display = '';
                                    });
                                    Array.from(partnersBody.querySelectorAll('.empty-row')).forEach(el => el.remove());
                                    Array.from(webBody.querySelectorAll('.empty-row')).forEach(el => el.remove());
                                };

                                // Always rebind handlers for fresh row/context state on each open.
                                if(searchEl && modal._moneygramSearchHandler){
                                    searchEl.removeEventListener('input', modal._moneygramSearchHandler);
                                }
                                if(filterEl && modal._moneygramFilterHandler){
                                    filterEl.removeEventListener('change', modal._moneygramFilterHandler);
                                }
                                modal._moneygramSearchHandler = function(){ modalRenderMoneygramRows(); };
                                modal._moneygramFilterHandler = function(){ modalRenderMoneygramRows(); };
                                if(searchEl) searchEl.addEventListener('input', modal._moneygramSearchHandler);
                                if(filterEl) filterEl.addEventListener('change', modal._moneygramFilterHandler);

                                // On reopen: start with blank search and current dropdown filter.
                                resetMoneygramState();
                                modalRenderMoneygramRows();
                                // Ensure empty-state message shows when there are no visible rows
                                ensureEmptyRow(partnersBody, 4, 'No Data Found');
                                ensureEmptyRow(webBody, 4, 'No Data Found');
                            }catch(e){ console.warn('Error wiring MONEYGRAM search/filter', e); }

                            const closeBtn = modal.querySelector('[data-action="close-moneygram-recon"]');
                            if(closeBtn){
                                closeBtn.onclick = function(){
                                    const searchEl = modal.querySelector('[data-role="resultSearch"]');
                                    if(searchEl) searchEl.value = '';
                                    modal._moneygramLastSearchValue = '';
                                    if(modal._moneygramDebounceTimer){
                                        try{ clearTimeout(modal._moneygramDebounceTimer); }catch(_e){}
                                        modal._moneygramDebounceTimer = null;
                                    }
                                    Array.from(partnersBody.querySelectorAll('tr')).forEach(tr => { tr.style.display = ''; });
                                    Array.from(webBody.querySelectorAll('tr')).forEach(tr => { tr.style.display = ''; });
                                    Array.from(partnersBody.querySelectorAll('.empty-row')).forEach(el => el.remove());
                                    Array.from(webBody.querySelectorAll('.empty-row')).forEach(el => el.remove());
                                    modal.style.display='none';
                                    try{ document.body.style.overflow=''; }catch(e){}
                                };
                            }
                            if(loadingEl) loadingEl.style.display = 'none';
                        }
                        try{ document.body.removeChild(overlay); }catch(e){}
                        return;
                    }catch(modErr){ console.error('Error opening MONEYGRAM modal', modErr); }
                }

                // If WORLD INTERNATIONAL COMMUNICATIONS is selected, open the recon modal and populate with date, transaction id, amount, coin
                if(isWorldInternationalCommunications(companyName)){
                    try{
                        const modal = document.getElementById('wicReconViewModal');
                        if(modal){
                            const loadingEl = modal.querySelector('.wic-recon-modal__loading'); if(loadingEl) loadingEl.style.display = 'flex';
                            modal.style.display = 'block'; try{ document.body.style.overflow = 'hidden'; }catch(e){}

                            // populate header
                            {
                                const m = Number(dayObj.matchedCount || 0);
                                const u = Number(dayObj.unmatchedCount || 0);
                                const mLabel = (m === 1) ? 'transaction' : 'transactions';
                                const uLabel = (u === 1) ? 'transaction' : 'transactions';
                                const el = modal.querySelector('[data-role="summary"]');
                                if(el) el.innerHTML = `<span class="recon-summary__item">Matched: ${m.toLocaleString()} ${mLabel}</span><span class="recon-summary__sep">|</span><span class="recon-summary__item">Not Matched: ${u.toLocaleString()} ${uLabel}</span>`;
                            }

                            const partnersBody = modal.querySelector('[data-role="partnersBody"]');
                            const webBody = modal.querySelector('[data-role="webBody"]');
                            partnersBody.innerHTML = '';
                            webBody.innerHTML = '';

                            let rowNo = 1;
                            if(Array.isArray(dayObj.rows) && dayObj.rows.length){
                                dayObj.rows.forEach(r => {
                                    const pDate = r.partner_date || r.partner_cover_date || r.partner_date_claimed || dayObj.date || '';
                                    const tx = r.partner_transaction_id || r.partner_reference_no || r.partner_ref_no || r.partner_ref || r.partner_ref_no || '';
                                    const pAmt = Number(r.partner_principal || 0);
                                    const pCoin = r.partner_coin || '';

                                    const pr = document.createElement('tr');
                                    pr.dataset.ref = tx || '';
                                        pr.innerHTML = `<td>${formatDateMMDDYYYY(pDate)}</td><td class="highlight-ref">${tx}</td><td>${pAmt? pAmt.toLocaleString(): ''}</td><td>${pCoin}</td>`;
                                    partnersBody.appendChild(pr);

                                    const wDateRaw = r.web_date || r.web_date_claimed || dayObj.date || '';
                                    const wDate = formatDateMMDDYYYY(wDateRaw);
                                    const wRef = r.web_ccref_no || r.web_cc_ref || r.web_ccref || r.web_ref || '';
                                    const wAmt = Number(r.web_amount || 0);
                                    const wCtp = Number(r.web_ctp || 0);
                                    const wr = document.createElement('tr');
                                    wr.dataset.ref = wRef || '';
                                        wr.innerHTML = `<td class="highlight-ref">${wRef}</td><td>${wDate}</td><td>${wAmt? wAmt.toLocaleString(): ''}</td><td>${wCtp? wCtp.toLocaleString(): ''}</td>`;
                                        webBody.appendChild(wr);

                                        // determine match status: for WORLD INTERNATIONAL COMMUNICATIONS we consider a row matched when partner transaction id equals web CCREF (presence on both sides)
                                        const bothPresent = (tx && wRef);
                                        if(bothPresent){
                                            pr.classList.add('matched-row');
                                            // mark web row background as matched (no icon)
                                            wr.classList.add('row-match');
                                        } else {
                                            // one side missing -> mark the partner side as mismatch so filters show it when 'Mismatch Only' selected
                                            if(!wRef) pr.classList.add('mismatch-row');
                                            // if partner is missing, mark web row as mismatch (web shows color-only status)
                                            if(!tx) wr.classList.add('row-mismatch');
                                        }

                                    rowNo++;
                                });
                            } else {
                                // fallback to diagnostics arrays
                                const mismatches = Array.isArray(dayObj.mismatches) ? dayObj.mismatches : [];
                                const missingWeb = Array.isArray(dayObj.missing_web_refs) ? dayObj.missing_web_refs : [];
                                mismatches.forEach(mm => {
                                    const pr = document.createElement('tr');
                                    pr.innerHTML = `<td>${formatDateMMDDYYYY(dayObj.date||'')}</td><td class="highlight-ref">${mm.ref||''}</td><td>${Number(mm.partner_principal||0).toLocaleString()}</td><td>${mm.partner_coin||''}</td>`;
                                    pr.classList.add('mismatch-row');
                                    partnersBody.appendChild(pr);

                                        const wr = document.createElement('tr');
                                        wr.innerHTML = `<td>${formatDateMMDDYYYY(dayObj.date||'')}</td><td class="highlight-ref">${mm.ref||''}</td><td>${Number(mm.web_amount||0).toLocaleString()}</td><td>${Number(mm.web_ctp||0).toLocaleString()}</td>`;
                                    // web rows intentionally show no status icon
                                    webBody.appendChild(wr);
                                    rowNo++;
                                });
                                missingWeb.forEach(ref => {
                                    const tr = document.createElement('tr');
                                    tr.innerHTML = `<td>${formatDateMMDDYYYY(dayObj.date||'')}</td><td>${ref}</td><td></td><td></td>`;
                                    partnersBody.appendChild(tr);
                                    rowNo++;
                                });
                            }

                            // update partners/web counts for WORLD INTERNATIONAL COMMUNICATIONS modal (show counts beside headings)
                            try{
                                const partnersCountEl = modal.querySelector('[data-role="partnersCount"]');
                                const webCountEl = modal.querySelector('[data-role="webCount"]');
                                const partnersVolumeEl = modal.querySelector('[data-role="partnersVolume"]');
                                const webVolumeEl = modal.querySelector('[data-role="webVolume"]');
                                const partnersPrincipalPhpEl = modal.querySelector('[data-role="partnersPrincipalPhp"]');
                                const partnersPrincipalUsdEl = modal.querySelector('[data-role="partnersPrincipalUsd"]');
                                const webPrincipalPhpEl = modal.querySelector('[data-role="webPrincipalPhp"]');
                                const webPrincipalUsdEl = modal.querySelector('[data-role="webPrincipalUsd"]');
                                const pCount = partnersBody.querySelectorAll('tr:not(.dup-sep)').length || 0;
                                const wCount = webBody.querySelectorAll('tr:not(.dup-sep)').length || 0;
                                // compute per-currency totals (partners use absolute values)
                                let pPhp = 0, pUsd = 0;
                                Array.from(partnersBody.querySelectorAll('tr:not(.dup-sep)')).forEach(tr => {
                                    const raw = ((tr.cells[2] && tr.cells[2].textContent) || '').replace(/,/g, '');
                                    const val = Number(raw);
                                    const cur = ((tr.cells[3] && tr.cells[3].textContent) || '').toString().trim().toUpperCase();
                                    if(!Number.isFinite(val)) return;
                                    if(cur.indexOf('PHP') !== -1){ pPhp += Math.abs(val); }
                                    else if(cur.indexOf('USD') !== -1){ pUsd += Math.abs(val); }
                                });
                                let wPhp = 0, wUsd = 0;
                                Array.from(webBody.querySelectorAll('tr:not(.dup-sep)')).forEach(tr => {
                                    const raw = ((tr.cells[2] && tr.cells[2].textContent) || '').replace(/,/g, '');
                                    const val = Number(raw);
                                    const cur = ((tr.cells[3] && tr.cells[3].textContent) || '').toString().trim().toUpperCase();
                                    if(!Number.isFinite(val)) return;
                                    if(cur.indexOf('PHP') !== -1){ wPhp += val; }
                                    else if(cur.indexOf('USD') !== -1){ wUsd += val; }
                                });

                                if(partnersCountEl) partnersCountEl.textContent = '(' + ((dayObj.partnersCount ?? pCount).toLocaleString()) + ')';
                                if(webCountEl) webCountEl.textContent = '(' + ((dayObj.webCount ?? wCount).toLocaleString()) + ')';
                                if(partnersVolumeEl) partnersVolumeEl.textContent = 'Volume: ' + pCount.toLocaleString();
                                if(webVolumeEl) webVolumeEl.textContent = 'Volume: ' + wCount.toLocaleString();
                                if(partnersPrincipalPhpEl) partnersPrincipalPhpEl.textContent = 'Principal PHP: ' + pPhp.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' pesos';
                                if(partnersPrincipalUsdEl) partnersPrincipalUsdEl.textContent = 'Principal USD: ' + pUsd.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                if(webPrincipalPhpEl) webPrincipalPhpEl.textContent = 'Principal PHP: ' + wPhp.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' pesos';
                                if(webPrincipalUsdEl) webPrincipalUsdEl.textContent = 'Principal USD: ' + wUsd.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            }catch(e){ console.warn('Error updating WORLD INTERNATIONAL COMMUNICATIONS counts', e); }

                            // wire search/filter within WORLD INTERNATIONAL COMMUNICATIONS modal
                            try{
                                const searchEl = modal.querySelector('[data-role="resultSearch"]');
                                const filterEl = modal.querySelector('[data-role="resultFilter"]');
                                async function modalRenderWicRows(){
                                    // hide modal loading indicator when applying filters
                                    const loaderEl = modal.querySelector('.wic-recon-modal__loading'); if(loaderEl) loaderEl.style.display = 'none';
                                    const q = searchEl && searchEl.value ? String(searchEl.value).trim().toLowerCase() : '';
                                    const filter = filterEl && filterEl.value ? String(filterEl.value) : 'all';
                                    // partners: match Transaction ID (.highlight-ref)
                                    Array.from(partnersBody.querySelectorAll('tr')).forEach(tr => {
                                        if(tr.classList && tr.classList.contains('dup-sep')){ tr.style.display = ''; return; }
                                        const ref = (tr.querySelector('.highlight-ref')?.textContent || tr.cells[1]?.textContent || '').toLowerCase();
                                        let show = true;
                                        if(q && !ref.includes(q)) show = false;
                                        if(filter === 'mismatch'){ show = show && (tr.classList.contains('mismatch-row') || tr.classList.contains('row-mismatch')); }
                                        if(filter === 'duplicates'){ show = show && (tr.classList.contains('dup-row') || tr.classList.contains('row-duplicate')); }
                                        tr.style.display = show ? '' : 'none';
                                    });
                                    // web: match CCREF (.highlight-ref)
                                    Array.from(webBody.querySelectorAll('tr')).forEach(tr => {
                                        if(tr.classList && tr.classList.contains('dup-sep')){ tr.style.display = ''; return; }
                                        const ref = (tr.querySelector('.highlight-ref')?.textContent || tr.cells[0]?.textContent || '').toLowerCase();
                                        let show = true;
                                        if(q && !ref.includes(q)) show = false;
                                        if(filter === 'mismatch'){ show = show && (tr.classList.contains('mismatch-row') || tr.classList.contains('row-mismatch')); }
                                        if(filter === 'duplicates'){ show = show && (tr.classList.contains('dup-row') || tr.classList.contains('row-duplicate')); }
                                        tr.style.display = show ? '' : 'none';
                                    });
                                    // if filter yields no visible rows, show a friendly 'No results' message instead of a loading placeholder
                                    try{
                                        const visibleP = partnersBody.querySelectorAll('tr:not([style*="display: none"])');
                                        const visibleW = webBody.querySelectorAll('tr:not([style*="display: none"])');
                                        const noP = Array.from(visibleP).filter(r=> !r.classList.contains('dup-sep')).length === 0;
                                        const noW = Array.from(visibleW).filter(r=> !r.classList.contains('dup-sep')).length === 0;
                                        // remove any previous no-results placeholders
                                        Array.from(partnersBody.querySelectorAll('.no-results')).forEach(n=>n.parentNode && n.parentNode.removeChild(n));
                                        Array.from(webBody.querySelectorAll('.no-results')).forEach(n=>n.parentNode && n.parentNode.removeChild(n));
                                        if(noP){ const tr = document.createElement('tr'); tr.className = 'no-results'; tr.innerHTML = `<td colspan="4">No matching entries</td>`; partnersBody.appendChild(tr); }
                                        if(noW){ const tr = document.createElement('tr'); tr.className = 'no-results'; tr.innerHTML = `<td colspan="4">No matching entries</td>`; webBody.appendChild(tr); }
                                    }catch(e){ /* ignore */ }
                                }
                                if(searchEl) searchEl.addEventListener('input', modalRenderWicRows);
                                if(filterEl) filterEl.addEventListener('change', modalRenderWicRows);
                                // initialize filter state
                                modalRenderWicRows();
                            }catch(e){ console.warn('Error wiring WORLD INTERNATIONAL COMMUNICATIONS search/filter', e); }

                            // wire close button
                            const closeBtn = modal.querySelector('[data-action="close-wic-recon"]');
                            if(closeBtn){ closeBtn.addEventListener('click', function(){ modal.style.display='none'; try{ document.body.style.overflow=''; }catch(e){} }); }
                            // hide modal loader now that content is rendered
                            if(loadingEl) loadingEl.style.display = 'none';
                        }
                        try{ document.body.removeChild(overlay); }catch(e){}
                        return;
                    }catch(modErr){ console.error('Error opening WORLD INTERNATIONAL COMMUNICATIONS modal', modErr); }
                }

                console.log('[home-section] Day card clicked', { day: d, partner: dayObj?.partner || MBTC_PARTNER_NAME, date: dayObj?.date || null, status: dayObj?.status || null });
                openMbtcReconModal(dayObj || { day: d });
            }catch(err){
                console.error('Error opening recon modal', err);
                openMbtcReconModal({ day: d });
            }finally{
                // remove overlay
                try{ document.body.removeChild(overlay); }catch(e){}
            }
        });

        // Right-click context menu for day cards: View Partner Data / View Web Data
        (function(){
            let menu = null;
            function ensureMenu(){
                if(menu) return menu;
                menu = document.createElement('div');
                menu.id = 'dayCardContextMenu';
                Object.assign(menu.style, {
                    position: 'fixed', zIndex: 200000, background: '#fff', border: '1px solid rgba(0,0,0,0.12)',
                    boxShadow: '0 6px 20px rgba(2,6,23,0.12)', padding: '6px 0', borderRadius: '6px', minWidth: '180px',
                    fontSize: '13px', color: '#222', display: 'none'
                });
                menu.addEventListener('click', function(ev){ ev.stopPropagation(); });
                document.body.appendChild(menu);
                return menu;
            }

            function hideMenu(){ if(menu){ menu.style.display='none'; } }

            function showMenuAt(x,y,dayCard){
                const m = ensureMenu();
                m.innerHTML = '';
                const opt = (label, action) => {
                    const el = document.createElement('div');
                    el.className = 'context-opt';
                    el.textContent = label;
                    Object.assign(el.style, { padding: '8px 12px', cursor: 'pointer' });
                    el.addEventListener('mouseover', ()=> el.style.background = '#f5f5f5');
                    el.addEventListener('mouseout', ()=> el.style.background = '');
                    el.addEventListener('click', function(){ hideMenu(); action(); });
                    m.appendChild(el);
                };
                const dayObj = getDayCardReconData(dayCard);
                const canLock = isLockableReconDay(dayObj);

                if(dayCard.classList.contains('locked-day')){
                    opt('Unlock', async ()=>{
                        const isLocked = dayCard.classList.contains('locked-day');
                        const dayValue = dayCard.getAttribute('data-day') || '';
                        const dateValue = dayCard.getAttribute('data-date') || '';
                        const partnerValue = dayCard.getAttribute('data-partner') || getSelectedPartnerLockKey();

                        if(isLocked){
                            const ok = await persistDayCardLock('unlock', partnerValue, dateValue || dayValue);
                            if(!ok) return;
                            setDayCardLockState(dayCard, false);
                        }
                    });
                } else if(canLock){
                    opt('Lock', async ()=>{
                        const dayValue = dayCard.getAttribute('data-day') || '';
                        const dateValue = dayCard.getAttribute('data-date') || '';
                        const partnerValue = dayCard.getAttribute('data-partner') || getSelectedPartnerLockKey();
                        const okConfirm = await showConfirmModal('Lock this reconciliation?');
                        if(!okConfirm) return;
                        const ok = await persistDayCardLock('lock', partnerValue, dateValue || dayValue);
                        if(!ok) return;
                        setDayCardLockState(dayCard, true);
                    });
                } else {
                    hideMenu();
                    return;
                }
                // position with simple bounds check
                const pad = 8;
                const maxLeft = window.innerWidth - m.offsetWidth - pad;
                const maxTop = window.innerHeight - m.offsetHeight - pad;
                let left = Math.max(pad, Math.min(x, maxLeft));
                let top = Math.max(pad, Math.min(y, maxTop));
                m.style.left = left + 'px';
                m.style.top = top + 'px';
                m.style.display = 'block';
            }

            async function ensureDayDetails(dayNum){
                const daysArr = window._lastMbtcDays || _lastMbtcDays || [];
                let dayObj = daysArr.find(dd => String(dd.day) === String(dayNum));
                if(!dayObj || !Array.isArray(dayObj.rows)){
                    const mVal = (month && month.value) ? month.value : '';
                    const yVal = (year && year.value) ? year.value : '';
                    const range = getSelectedDateRange();
                    const companyName = (company && company.value) ? String(company.value).toUpperCase() : '';
                    const reconFile = isWorldInternationalCommunications(companyName) ? 'wic-recon.php' : (isMetrobankHeadOffice(companyName) ? 'mbtc-recon.php' : (isMoneygram(companyName) ? 'moneygram-recon.php' : (isRcbc(companyName) ? 'rcbc-recon.php' : 'mbtc-recon.php')));
                    const selectedDate = (dayObj && dayObj.date) ? String(dayObj.date) : '';
                    const url = isMetrobankHeadOffice(companyName)
                        ? (location.origin + '/autorecon/src/controllers/recon/' + reconFile + '?start_date=' + encodeURIComponent(range.startDate || '') + '&end_date=' + encodeURIComponent(range.endDate || '') + '&date=' + encodeURIComponent(selectedDate || '') + '&day=' + encodeURIComponent(dayNum) + '&detail=1' + '&partnerName=' + encodeURIComponent(companyName || ''))
                        : (location.origin + '/autorecon/src/controllers/recon/' + reconFile + '?month=' + encodeURIComponent(mVal) + '&year=' + encodeURIComponent(yVal) + '&day=' + encodeURIComponent(dayNum) + '&detail=1' + '&partnerName=' + encodeURIComponent(companyName || ''));
                    try{
                        const res = await fetch(url, { method: 'GET', credentials: 'same-origin' });
                        if(res && res.ok){
                            const txt = await res.text();
                            try{ const payload = JSON.parse(txt); if(payload && Array.isArray(payload.days)){
                                const found = payload.days.find(dd=>String(dd.day)===String(dayNum));
                                if(found){
                                    // update cached array
                                    if(!window._lastMbtcDays) window._lastMbtcDays = daysArr;
                                    const idx = daysArr.findIndex(dd=>String(dd.day)===String(dayNum));
                                    if(idx !== -1) daysArr[idx] = found; else daysArr.push(found);
                                    dayObj = found;
                                }
                            }}catch(e){ console.warn('Could not parse day detail JSON', e); }
                        }
                    }catch(e){ console.warn('Failed fetching day details', e); }
                }
                return dayObj || { day: dayNum };
            }

            async function handleViewData(type, dayCard){
                const day = dayCard.getAttribute('data-day');
                if(!day) return;

                // show fetching overlay
                const overlay = document.createElement('div');
                overlay.className = 'mbtc-global-loading';
                const label = (type === 'partner') ? 'Fetching Partner Data — Please wait…' : 'Fetching Web Data — Please wait…';
                Object.assign(overlay.style, {
                    position: 'fixed', top: '0', left: '0', right: '0', bottom: '0',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    background: 'rgba(0,0,0,0.18)', color: '#fff', zIndex: 99999,
                    fontSize: '1.1rem'
                });
                overlay.textContent = label;
                document.body.appendChild(overlay);

                try{
                const dayObj = await ensureDayDetails(day);

                // build rows for viewer
                const rows = [];
                if(Array.isArray(dayObj.rows) && dayObj.rows.length){
                    // Prefer to send full column sets to the viewer. Filter keys by prefix when possible
                    dayObj.rows.forEach(r => {
                        const out = {};
                        Object.keys(r || {}).forEach(k => {
                            if(type === 'partner'){
                                // include partner-prefixed keys, obvious partner fields, and any key that is not a web-prefixed key
                                if(k.startsWith('partner_') || k === 'partnerName' || k === 'partner' || k === 'ref' || k === 'reference_no' || k === 'no' || !k.startsWith('web_') && !k.startsWith('web')){
                                    out[k] = r[k];
                                }
                            } else {
                                // include web-prefixed keys, obvious web fields, and any key that is not a partner-prefixed key
                                if(k.startsWith('web_') || k === 'ref' || k === 'no' || k === 'amount' || k === 'ctp' || k === 'ctc' || !k.startsWith('partner_') && !k.startsWith('partner')){
                                    out[k] = r[k];
                                }
                            }
                        });
                        if(!out.ref && r.ref) out.ref = r.ref;
                        rows.push(out);
                    });
                } else {
                    // fallback: use diagnostics arrays
                    if(type === 'partner'){
                        const mismatches = Array.isArray(dayObj.mismatches) ? dayObj.mismatches : [];
                        const missingWeb = Array.isArray(dayObj.missing_web_refs) ? dayObj.missing_web_refs : [];
                        mismatches.forEach(mm => rows.push({ ref: mm.ref || '', principal: mm.partner_principal || '', commission: mm.partner_commission || '' }));
                        missingWeb.forEach(ref => rows.push({ ref: ref, principal: '', commission: '' }));
                    } else {
                        const mismatches = Array.isArray(dayObj.mismatches) ? dayObj.mismatches : [];
                        const missingPartner = Array.isArray(dayObj.missing_partner_refs) ? dayObj.missing_partner_refs : [];
                        mismatches.forEach(mm => rows.push({ ref: mm.ref || '', amount: mm.web_amount || '', ctp: mm.web_ctp || '' }));
                        missingPartner.forEach(ref => rows.push({ ref: ref, amount: '', ctp: '' }));
                    }
                }

                const filename = dayObj.filename || dayObj.file || '';
                const selectedRange = getSelectedDateRange();
                const dateStr = dayObj.date || (selectedRange.startDate && selectedRange.endDate ? (selectedRange.startDate + ' to ' + selectedRange.endDate) : String(day));

                // choose viewer URL: prefer the mbtc viewer when METROBANK HEAD OFFICE is selected
                let viewerUrl = '';
                const companyName = (company && company.value) ? String(company.value) : '';
                if(isMetrobankHeadOffice(companyName)){
                    viewerUrl = location.origin + '/autorecon/src/controllers/excelcontrol/mbtc/mbtc-viewer.php';
                } else if(companyName){
                    // try partner-specific viewer path, fallback to the mbtc viewer if not found
                    const candidate = location.origin + '/autorecon/src/controllers/excelcontrol/' + encodeURIComponent(companyName.toLowerCase().replace(/\s+/g,'-')) + '/viewer.php';
                    try{
                        const head = await fetch(candidate, { method: 'HEAD', credentials: 'same-origin' });
                        if(head && head.ok) viewerUrl = candidate;
                        else viewerUrl = location.origin + '/autorecon/src/controllers/excelcontrol/mbtc/mbtc-viewer.php';
                    }catch(e){ viewerUrl = location.origin + '/autorecon/src/controllers/excelcontrol/mbtc/mbtc-viewer.php'; }
                } else {
                    viewerUrl = location.origin + '/autorecon/src/controllers/excelcontrol/mbtc/mbtc-viewer.php';
                }

                try{
                    const resolvedPartnerName = isWorldInternationalCommunications(dayObj && dayObj.partner)
                        ? WIC_PARTNER_NAME
                        : (isMetrobankHeadOffice(dayObj && dayObj.partner) ? MBTC_PARTNER_NAME : (dayObj && dayObj.partner ? dayObj.partner : (companyName || '')));
                    const resp = await fetch(viewerUrl, { method: 'POST', credentials: 'same-origin', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ data: { filename: filename, dateStr: dateStr, rows: rows, viewType: type, partnerName: resolvedPartnerName } }) });
                    const html = await resp.text();
                    showViewerModal(html);
                }catch(e){ console.error('Error loading viewer', e); await showAlertModal('Failed to load viewer'); }
                finally{
                    try{ document.body.removeChild(overlay); }catch(e){}
                }
                }catch(e){
                    try{ document.body.removeChild(overlay); }catch(err){}
                    throw e;
                }
            }

            // viewer modal
            function showViewerModal(html){
                let v = document.getElementById('mbtcViewerOverlay');
                if(v) v.parentNode.removeChild(v);
                v = document.createElement('div'); v.id = 'mbtcViewerOverlay';
                Object.assign(v.style, { position:'fixed', inset:0, background:'rgba(0,0,0,0.45)', zIndex:200001, display:'flex', alignItems:'center', justifyContent:'center', padding:'18px' });
                const box = document.createElement('div');
                Object.assign(box.style, { background:'#fff', width:'95%', height:'90%', borderRadius:'8px', overflow:'auto', boxShadow:'0 18px 40px rgba(2,6,23,0.32)', position:'relative', padding:'14px' });
                const close = document.createElement('button');
                close.type = 'button';
                close.textContent = '×';
                close.setAttribute('aria-label', 'Close');
                Object.assign(close.style, {
                    position:'absolute',
                    right:'12px',
                    top:'12px',
                    zIndex:2,
                    width:'50px',
                    height:'50px',
                    borderRadius:'6px',
                    border:'0',
                    background:'transparent',
                    color:'#5f6368',
                    fontSize:'1.2rem',
                    display:'flex',
                    alignItems:'center',
                    justifyContent:'center',
                    cursor:'pointer',
                    transition:'background .18s ease, transform .16s ease, color .18s ease'
                });
                close.addEventListener('mouseenter', ()=>{
                    close.style.background = 'rgba(15, 23, 42, 0.04)';
                    close.style.transform = 'scale(1.03)';
                    close.style.color = '#dc3545';
                });
                close.addEventListener('mouseleave', ()=>{
                    close.style.background = 'transparent';
                    close.style.transform = 'none';
                    close.style.color = '#5f6368';
                });
                close.addEventListener('click', ()=>{ try{ document.body.removeChild(v); }catch(e){} try{ document.body.style.overflow=''; }catch(e){} });
                box.appendChild(close);
                const wrapper = document.createElement('div'); wrapper.innerHTML = html; wrapper.style.paddingTop = '36px';
                // Execute any scripts contained in the returned HTML (innerHTML doesn't run them)
                try{
                    const scripts = Array.from(wrapper.querySelectorAll('script'));
                    scripts.forEach(s => {
                        const ns = document.createElement('script');
                        // copy attributes
                        Array.from(s.attributes || []).forEach(a=> ns.setAttribute(a.name, a.value));
                        ns.type = s.type || 'text/javascript';
                        if(s.src){
                            // ensure crossorigin/async behavior is preserved
                            ns.async = false;
                            ns.src = s.src;
                            // append to wrapper so it's fetched and executed
                            wrapper.appendChild(ns);
                        } else {
                            ns.text = s.innerHTML || s.textContent || '';
                            wrapper.appendChild(ns);
                        }
                        // remove the inert original
                        s.parentNode && s.parentNode.removeChild(s);
                    });
                }catch(e){ console.warn('Failed to re-run viewer scripts', e); }
                box.appendChild(wrapper);
                v.appendChild(box);
                document.body.appendChild(v);
                try{ document.body.style.overflow = 'hidden'; }catch(e){}
            }

            // attach contextmenu to days container
            daysContainer.addEventListener('contextmenu', function(e){
                const card = e.target.closest && e.target.closest('.day-card');
                if(!card) return; // allow normal context menu elsewhere
                // Only intercept right-click for admins; non-admins should not get the custom menu
                if(!IS_ADMIN){ e.preventDefault(); e.stopPropagation(); return; }
                e.preventDefault(); e.stopPropagation();
                showMenuAt(e.clientX, e.clientY, card);
            });

            // hide on global events
            document.addEventListener('click', hideMenu);
            window.addEventListener('blur', hideMenu);
            window.addEventListener('resize', hideMenu);
            window.addEventListener('scroll', hideMenu, { passive: true });

        })();

        // open modal and populate (shows loading overlay while populating)
        function openMbtcReconModal(dayObj){
            const companyName = (company && company.value) ? String(company.value).toUpperCase() : '';
            let modal = null;
            if(isWorldInternationalCommunications(companyName)) modal = document.getElementById('wicReconViewModal');
            else if(isMoneygram(companyName) || (dayObj && String(dayObj.partner||'').toUpperCase() === 'MONEYGRAM')) modal = document.getElementById('moneygramReconViewModal');
            else if(isRcbc(companyName) || (dayObj && String(dayObj.partner||'').toUpperCase() === 'RCBC')) modal = document.getElementById('rcbcReconViewModal');
            else modal = document.getElementById('mbtcReconViewModal');
            if(!modal) return;
            const loadingEl = modal.querySelector('.mbtc-recon-modal__loading, .wic-recon-modal__loading, .moneygram-recon-modal__loading, .rcbc-recon-modal__loading');
            if(loadingEl) loadingEl.style.display = 'flex';
            // show modal immediately with overlay so users see progress
            modal.style.display = 'block';
            try{ document.body.style.overflow = 'hidden'; } catch(e){}
            // expose partner/date to modal for row-level operations
            try{ modal.dataset.reconDate = String(dayObj.date || ''); modal.dataset.partnerName = String(companyName || ''); }catch(_e){}

            try{
            // populate header metrics
            modal.querySelector('[data-role="summary"]').textContent = `Matched: ${((dayObj.matchedCount||0).toLocaleString())} | Not Matched: ${((dayObj.unmatchedCount||0).toLocaleString())}`;

            // primary totals come from server; if zero, attempt to compute fallback totals from diagnostics so user sees useful numbers
            let principalLeft = Number(dayObj.principal || 0);
            let commissionLeft = Number(dayObj.commission || 0);

            const mismatches = Array.isArray(dayObj.mismatches) ? dayObj.mismatches : [];
            const missingWeb = Array.isArray(dayObj.missing_web_refs) ? dayObj.missing_web_refs : [];
            const missingPartner = Array.isArray(dayObj.missing_partner_refs) ? dayObj.missing_partner_refs : [];
            const duplicates = Array.isArray(dayObj.duplicates) ? dayObj.duplicates : [];

            // compute fallback sums from mismatches if server totals are zero
            if((principalLeft === 0 || commissionLeft === 0) && mismatches.length){
                let pSum = 0, cSum = 0, wSum = 0, wcSum = 0;
                mismatches.forEach(mm => {
                    pSum += Number(mm.partner_principal || 0);
                    cSum += Number(mm.partner_commission || 0);
                    wSum += Number(mm.web_amount || 0);
                    wcSum += Number(mm.web_ctp || 0);
                });
                // prefer server totals if present, else show computed partner/web totals in header
                if(principalLeft === 0) principalLeft = pSum || principalLeft;
                if(commissionLeft === 0) commissionLeft = cSum || commissionLeft;
                // expose web sums as data attributes for potential display
                modal._fallbackWebAmount = wSum;
                modal._fallbackWebCtp = wcSum;
            }

            // show 'Partner / Web' style where possible. Use web fallback if available via modal._fallbackWebAmount
            const webAmt = (typeof modal._fallbackWebAmount === 'number') ? modal._fallbackWebAmount : (Number(dayObj.principal||0));
            const webCtp = (typeof modal._fallbackWebCtp === 'number') ? modal._fallbackWebCtp : (Number(dayObj.commission||0));

            let totalPartnerAmount = Number(dayObj.total_partner_amount);
            let totalWebAmount = Number(dayObj.total_web_amount);

            if(!Number.isFinite(totalPartnerAmount) || !Number.isFinite(totalWebAmount)){
                if(Array.isArray(dayObj.rows) && dayObj.rows.length){
                    totalPartnerAmount = 0;
                    totalWebAmount = 0;
                    dayObj.rows.forEach(r => {
                        totalPartnerAmount += Number(r.partner_principal || 0);
                        totalWebAmount += Number(r.web_amount || 0);
                    });
                } else {
                    totalPartnerAmount = Number(dayObj.principal || 0);
                    totalWebAmount = Number(dayObj.web_principal || webAmt || 0);
                }
            }

            // Spreadsheet formula mapping:
            // AG = D / 1.12 * 0.02
            // AH = D - AG
            // AF (Variance) = AE - C - AH
            // where: C=partner principal, D=partner commission, AE=deposit credit
            const cPrincipal = Number(principalLeft || 0);
            const dCommission = Number(commissionLeft || 0);
            const ag = dCommission ? (dCommission / 1.12 * 0.02) : 0;
            const ah = dCommission - ag;

            const depositCreditRaw = Number(dayObj.deposit_credit || dayObj.depositCredit || 0);
            const aeDepositCredit = Number.isFinite(depositCreditRaw) ? depositCreditRaw : 0;

            let varianceAmount = aeDepositCredit - cPrincipal - ah;

            // fallback when AE is not available in payload
            if(!Number.isFinite(varianceAmount) || aeDepositCredit === 0){
                const explicitVariance = (dayObj.variance !== undefined && dayObj.variance !== null) ? Number(dayObj.variance) : NaN;
                varianceAmount = Number.isFinite(explicitVariance) ? explicitVariance : (totalPartnerAmount - totalWebAmount);
                if(!Number.isFinite(varianceAmount)){
                    if(Array.isArray(dayObj.rows) && dayObj.rows.length){
                        let pRows = 0;
                        let wRows = 0;
                        dayObj.rows.forEach(r => {
                            pRows += Number(r.partner_principal || 0);
                            wRows += Number(r.web_amount || 0);
                        });
                        varianceAmount = pRows - wRows;
                    } else {
                        varianceAmount = Number(principalLeft || 0) - Number(webAmt || 0);
                    }
                }
            }

            const _varEl = modal.querySelector('[data-role="variance"]');
            if(_varEl) _varEl.textContent = Number(varianceAmount).toLocaleString() + ' pesos';

            

            const partnersHead = modal.querySelector('[data-role="partnersHead"]');
            const partnersBody = modal.querySelector('[data-role="partnersBody"]');
            const webHead = modal.querySelector('[data-role="webHead"]');
            const webBody = modal.querySelector('[data-role="webBody"]');

            // modal contains static headers; only clear bodies here
            partnersBody.innerHTML = '';
            webBody.innerHTML = '';

            // collect rows from diagnostics (arrays were declared earlier in this function)
            // `mismatches`, `missingWeb`, `missingPartner`, `duplicates` are already available

            let rowNo = 1;
            // If server provided `rows`, render them as the authoritative full dataset (both matched and unmatched).
            if(Array.isArray(dayObj.rows) && dayObj.rows.length){
                const normalPartnerRows = [];
                const normalWebRows = [];
                const dupPartnerRows = [];
                const dupWebRows = [];

                dayObj.rows.forEach(r => {
                    const ref = r.ref || '';
                    const pVal = Number(r.partner_principal || 0);
                    const pC = Number(r.partner_commission || 0);
                    const wVal = Number(r.web_amount || 0);
                    const wC = Number(r.web_ctp || 0);

                    const pr = document.createElement('tr'); pr.dataset.ref = ref;
                    pr.innerHTML = `<td class="highlight-ref">${ref}</td><td>${dayObj.date||''}</td><td>${pVal? pVal.toLocaleString(): ''}</td><td>${pC? pC.toLocaleString(): ''}</td>`;

                    const wr = document.createElement('tr'); wr.dataset.ref = ref;
                    wr.innerHTML = `<td class="highlight-ref">${ref}</td><td>${dayObj.date||''}</td><td>${wVal? wVal.toLocaleString(): ''}</td><td>${wC? wC.toLocaleString(): ''}</td>`;

                    // determine status for this row (matched / mismatched / missing)
                    const bothPresent = pVal !== 0 && wVal !== 0;
                    const principalMatch = bothPresent ? (Math.abs(pVal - wVal) < 0.01) : false;
                    const commissionMatch = (pC || wC) ? (Math.abs(pC - wC) < 0.01) : true;

                    if(bothPresent && principalMatch && commissionMatch){
                        pr.classList.add('matched-row');
                        wr.classList.add('row-match');
                    } else if(bothPresent && (!principalMatch || !commissionMatch)){
                        pr.classList.add('mismatch-row');
                        wr.classList.add('row-mismatch');
                    } else {
                        // one side missing -> mark partner row as mismatch (status icons shown only on Partners Data)
                        if(pVal === 0){ pr.classList.add('mismatch-row'); wr.classList.add('row-mismatch'); }
                        if(wVal === 0){ pr.classList.add('mismatch-row'); wr.classList.add('row-mismatch'); }
                    }

                    // duplicates: if listed in diagnostics, separate these rows to duplicate buckets
                    const isDup = duplicates.find(dd => String(dd.ref) === String(ref));
                    if(isDup){ pr.classList.add('dup-row'); wr.classList.add('row-duplicate'); dupPartnerRows.push(pr); dupWebRows.push(wr); }
                    else { normalPartnerRows.push(pr); normalWebRows.push(wr); }

                    rowNo++;
                });

                // append normal rows first (preserve ordering), then duplicates grouped at bottom
                normalPartnerRows.forEach(tr => partnersBody.appendChild(tr));
                if(dupPartnerRows.length){
                    const sep = document.createElement('tr'); sep.className = 'dup-sep'; sep.innerHTML = `<td colspan="4">Duplicate entries</td>`;
                    partnersBody.appendChild(sep);
                    dupPartnerRows.forEach(tr => partnersBody.appendChild(tr));
                }

                normalWebRows.forEach(tr => webBody.appendChild(tr));
                if(dupWebRows.length){
                    const sep = document.createElement('tr'); sep.className = 'dup-sep'; sep.innerHTML = `<td colspan="4">Duplicate entries</td>`;
                    webBody.appendChild(sep);
                    dupWebRows.forEach(tr => webBody.appendChild(tr));
                }

            } else {
                // fallback to previous diagnostics-only rendering when `rows` not available
                // mismatches: show both sides
                mismatches.forEach(mm => {
                    const pr = document.createElement('tr');
                    pr.dataset.ref = mm.ref || '';
                    pr.innerHTML = `<td class="highlight-ref">${mm.ref||''}</td><td>${dayObj.date||''}</td><td>${Number(mm.partner_principal||0).toLocaleString()}</td><td>${Number(mm.partner_commission||0).toLocaleString()}</td>`;
                    pr.classList.add('mismatch-row');
                    partnersBody.appendChild(pr);

                    const wr = document.createElement('tr');
                    wr.dataset.ref = mm.ref || '';
                    wr.innerHTML = `<td class="highlight-ref">${mm.ref||''}</td><td>${dayObj.date||''}</td><td>${Number(mm.web_amount||0).toLocaleString()}</td><td>${Number(mm.web_ctp||0).toLocaleString()}</td>`;
                    // mark web row as mismatch (color-only) so Web Data shows red for this row
                    wr.classList.add('row-mismatch');
                    webBody.appendChild(wr);
                    rowNo++;
                });

                // missing web refs -> partner-only rows
                missingWeb.forEach(ref => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `<td>${ref}</td><td>${dayObj.date||''}</td><td></td><td></td>`;
                    // mark duplicates if present
                    if(duplicates.find(d=>d.ref===ref && d.type==='partner')) tr.classList.add('dup-row');
                    partnersBody.appendChild(tr);
                    rowNo++;
                });

                // missing partner refs -> web-only rows (mark as mismatch on web side)
                missingPartner.forEach(ref => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `<td>${ref}</td><td>${dayObj.date||''}</td><td></td><td></td>`;
                    tr.classList.add('row-mismatch');
                    webBody.appendChild(tr);
                    rowNo++;
                });

                // highlight duplicate refs listed in duplicates array
                // mark duplicates only on the Partners Data table (visual indicator only shown there)
                duplicates.forEach(d => {
                    const pRows = modal.querySelectorAll('[data-role="partnersBody"] tr');
                    pRows.forEach(r => { if(r.dataset.ref === d.ref) r.classList.add('dup-row'); });
                    const wRows = modal.querySelectorAll('[data-role="webBody"] tr');
                    wRows.forEach(r => { if(r.dataset.ref === d.ref) r.classList.add('row-duplicate'); });
                });
            }

            // update partners/web counts (count table rows excluding duplicate-separator rows)
            try{
                const partnersCountEl = modal.querySelector('[data-role="partnersCount"]');
                const webCountEl = modal.querySelector('[data-role="webCount"]');
                const partnersVolumeEl = modal.querySelector('[data-role="partnersVolume"]');
                const webVolumeEl = modal.querySelector('[data-role="webVolume"]');
                const partnersPrincipalPhpEl = modal.querySelector('[data-role="partnersPrincipalPhp"]');
                const partnersPrincipalUsdEl = modal.querySelector('[data-role="partnersPrincipalUsd"]');
                const webPrincipalPhpEl = modal.querySelector('[data-role="webPrincipalPhp"]');
                const webPrincipalUsdEl = modal.querySelector('[data-role="webPrincipalUsd"]');
                const pCount = partnersBody.querySelectorAll('tr:not(.dup-sep)').length || 0;
                const wCount = webBody.querySelectorAll('tr:not(.dup-sep)').length || 0;
                                let pPhp = 0, pUsd = 0;
                                Array.from(partnersBody.querySelectorAll('tr:not(.dup-sep)')).forEach(tr => {
                                    const raw = tr.dataset.amount !== undefined ? tr.dataset.amount : (((tr.cells[2] && tr.cells[2].textContent) || '').replace(/,/g, ''));
                                    const val = Number(raw);
                                    const cur = (tr.dataset.currency || (tr.cells[3] && tr.cells[3].textContent) || '').toString().trim().toUpperCase();
                                    if(!Number.isFinite(val)) return;
                                    if(cur.indexOf('PHP') !== -1){ pPhp += Math.abs(val); }
                                    else if(cur.indexOf('USD') !== -1){ pUsd += Math.abs(val); }
                                });
                                let wPhp = 0, wUsd = 0;
                                Array.from(webBody.querySelectorAll('tr:not(.dup-sep)')).forEach(tr => {
                                    const raw = tr.dataset.amount !== undefined ? tr.dataset.amount : (((tr.cells[2] && tr.cells[2].textContent) || '').replace(/,/g, ''));
                                    const val = Number(raw);
                                    const cur = (tr.dataset.currency || (tr.cells[3] && tr.cells[3].textContent) || '').toString().trim().toUpperCase();
                                    if(!Number.isFinite(val)) return;
                                    if(cur.indexOf('PHP') !== -1){ wPhp += val; }
                                    else if(cur.indexOf('USD') !== -1){ wUsd += val; }
                                });
                if(partnersCountEl) partnersCountEl.textContent = '(' + (pCount).toLocaleString() + ')';
                if(webCountEl) webCountEl.textContent = '(' + (wCount).toLocaleString() + ')';
                if(partnersVolumeEl) partnersVolumeEl.textContent = 'Volume: ' + pCount.toLocaleString();
                if(webVolumeEl) webVolumeEl.textContent = 'Volume: ' + wCount.toLocaleString();
                if(partnersPrincipalPhpEl) partnersPrincipalPhpEl.textContent = 'Principal PHP: ' + pPhp.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' pesos';
                if(partnersPrincipalUsdEl) partnersPrincipalUsdEl.textContent = 'Principal USD: ' + pUsd.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                if(webPrincipalPhpEl) webPrincipalPhpEl.textContent = 'Principal PHP: ' + wPhp.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' pesos';
                if(webPrincipalUsdEl) webPrincipalUsdEl.textContent = 'Principal USD: ' + wUsd.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }catch(e){console.warn('Error updating counts', e);} 

            // wire search/filter within modal
            const searchEl = modal.querySelector('[data-role="resultSearch"]');
            const filterEl = modal.querySelector('[data-role="resultFilter"]');
            function modalRenderRows(){
                const q = searchEl && searchEl.value ? String(searchEl.value).trim().toLowerCase() : '';
                const filter = filterEl && filterEl.value ? String(filterEl.value) : 'all';
                // partners: match Part Id / Reference (second column or .highlight-ref)
                Array.from(partnersBody.querySelectorAll('tr')).forEach(tr => {
                    const ref = (tr.querySelector('.highlight-ref')?.textContent || tr.cells[0]?.textContent || '').toLowerCase();
                    let show = true;
                    if(q && !ref.includes(q)) show = false;
                    if(filter === 'mismatch'){ show = show && (tr.classList.contains('mismatch-row') || tr.classList.contains('row-mismatch')); }
                    if(filter === 'duplicates'){ show = show && (tr.classList.contains('dup-row') || tr.classList.contains('row-duplicate')); }
                    tr.style.display = show ? '' : 'none';
                });
                // web: match CCREF (second column) or fallback to .highlight-ref where present
                Array.from(webBody.querySelectorAll('tr')).forEach(tr => {
                    const ccref = (tr.cells[0]?.textContent || tr.querySelector('.highlight-ref')?.textContent || '').toLowerCase();
                    let show = true;
                    if(q && !ccref.includes(q)) show = false;
                    if(filter === 'mismatch'){ show = show && tr.classList.contains('mismatch-row'); }
                    if(filter === 'duplicates'){ show = show && tr.classList.contains('dup-row'); }
                    tr.style.display = show ? '' : 'none';
                });
            }
            if(searchEl && !searchEl._listener){ searchEl.addEventListener('input', modalRenderRows); searchEl._listener = true; }
            if(filterEl && !filterEl._listener){ filterEl.addEventListener('change', modalRenderRows); filterEl._listener = true; }
            modalRenderRows();

            // close handler (generic for all recon modals)
            const closeBtn = modal.querySelector('[data-action^="close-"], [class$="recon-modal__close"]');
            function closeModal(){
                // hide loading overlay, then fade out modal and restore body overflow
                if(loadingEl) loadingEl.style.display = 'none';
                try{ document.body.style.overflow = ''; } catch(e){}
                // add closing class for CSS fade, then hide after transition
                modal.classList.add('closing');
                setTimeout(() => { modal.style.display = 'none'; modal.classList.remove('closing'); }, 200);
            }
            if(closeBtn){ closeBtn.addEventListener('click', closeModal); }
            // also close on backdrop click
            modal.addEventListener('click', function(ev){ if(ev.target === modal) closeModal(); });
            // close on Escape
            document.addEventListener('keydown', function keyHandler(ev){ if(ev.key === 'Escape'){ closeModal(); document.removeEventListener('keydown', keyHandler); } });
            } finally {
                if(loadingEl) loadingEl.style.display = 'none';
            }
        }

        // Export button: generate multi-sheet Excel (.xlsx) including Green Card details and CoverPH summary
        const exportBtn = document.getElementById('hsExport');
        async function ensureSheetJs(){
            if(window.XLSX) return window.XLSX;
            return new Promise((resolve, reject) => {
                const s = document.createElement('script');
                s.src = 'https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js';
                s.onload = () => resolve(window.XLSX);
                s.onerror = () => reject(new Error('Failed to load SheetJS'));
                document.head.appendChild(s);
            });
        }

        function parseCurrencyNumber(txt){
            if(!txt) return 0;
            // Remove non-digit/period/minus characters and parse
            const cleaned = String(txt).replace(/[^0-9.\-]/g,'');
            const n = Number(cleaned);
            return Number.isFinite(n) ? n : 0;
        }

        async function buildAndDownloadWorkbook(){
            try{
                const XLSX = await ensureSheetJs();
                const wb = XLSX.utils.book_new();

                // Sheet 1: Day card details (all day cards)
                const dayCards = Array.from(document.querySelectorAll('#hsDays .day-card'));
                const dayRows = dayCards.map(card => {
                    const day = card.getAttribute('data-day') || '';
                    const partner = (card.querySelector('.meta .partner')?.textContent || '').trim();
                    const principalText = card.querySelector('.amount.principal')?.textContent || '';
                    const commissionText = card.querySelector('.amount.commission')?.textContent || '';
                    const varianceText = card.querySelector('.amount.variance')?.textContent || '';
                    const tooltip = card.getAttribute('data-tooltip') || '';
                    return {
                        Day: day,
                        Partner: partner,
                        Principal: parseCurrencyNumber(principalText),
                        Commission: parseCurrencyNumber(commissionText),
                        Variance: parseCurrencyNumber(varianceText),
                        Tooltip: tooltip
                    };
                });
                const ws1 = XLSX.utils.json_to_sheet(dayRows, {header:['Day','Partner','Principal','Commission','Variance','Tooltip']});
                XLSX.utils.book_append_sheet(wb, ws1, 'DayCards');

                // For WORLD INTERNATIONAL COMMUNICATIONS exports, create individual sheets per day named "1","2",... up to the last
                // day that has any data. Each sheet contains the same columns as DayCards.
                try{
                    const compNow = (company && company.value) ? String(company.value).toUpperCase() : '';
                    if(isWorldInternationalCommunications(compNow)){
                        // Determine last day index that has data
                        let lastDayWithData = 0;
                        for(let i = dayRows.length - 1; i >= 0; i--){
                            const r = dayRows[i] || {};
                            const hasData = (r.Partner && String(r.Partner).trim() !== 'No Data') || (Number(r.Principal) !== 0) || (Number(r.Commission) !== 0) || (Number(r.Variance) !== 0) || (r.Tooltip && String(r.Tooltip).trim() !== '');
                            if(hasData){ lastDayWithData = Number(r.Day) || (i+1); break; }
                        }

                        if(lastDayWithData > 0){
                            for(let d = 1; d <= lastDayWithData; d++){
                                const rowsForDay = dayRows.filter(rr => String(rr.Day) === String(d));
                                // If no row exists for the day, supply an empty placeholder row so sheet still has headers
                                const sheetRows = (rowsForDay && rowsForDay.length) ? rowsForDay : [{ Day: d, Partner: '', Principal: '', Commission: '', Variance: '', Tooltip: '' }];
                                const wsDay = XLSX.utils.json_to_sheet(sheetRows, { header: ['Day','Partner','Principal','Commission','Variance','Tooltip'] });
                                // ensure numeric cells are numbers (json_to_sheet already does numbers for numeric types)
                                XLSX.utils.book_append_sheet(wb, wsDay, String(d));
                            }
                        }
                    }
                    else if(isMetrobankHeadOffice(compNow)){
                        // For METROBANK HEAD OFFICE, also create individual sheets per day named "1","2",... up to last day with data
                        try{
                            let lastDayWithData = 0;
                            for(let i = dayRows.length - 1; i >= 0; i--){
                                const r = dayRows[i] || {};
                                const hasData = (r.Partner && String(r.Partner).trim() !== 'No Data') || (Number(r.Principal) !== 0) || (Number(r.Commission) !== 0) || (Number(r.Variance) !== 0) || (r.Tooltip && String(r.Tooltip).trim() !== '');
                                if(hasData){ lastDayWithData = Number(r.Day) || (i+1); break; }
                            }
                            if(lastDayWithData > 0){
                                for(let d = 1; d <= lastDayWithData; d++){
                                    const rowsForDay = dayRows.filter(rr => String(rr.Day) === String(d));
                                    const sheetRows = (rowsForDay && rowsForDay.length) ? rowsForDay : [{ Day: d, Partner: '', Principal: '', Commission: '', Variance: '', Tooltip: '' }];
                                    const wsDay = XLSX.utils.json_to_sheet(sheetRows, { header: ['Day','Partner','Principal','Commission','Variance','Tooltip'] });
                                    XLSX.utils.book_append_sheet(wb, wsDay, String(d));
                                }
                            }
                        }catch(e){ console.warn('Failed to append per-day METROBANK HEAD OFFICE sheets', e); }
                    }
                }catch(e){ console.warn('Failed to append per-day WORLD INTERNATIONAL COMMUNICATIONS sheets', e); }

                // Sheet 2: Cover PH summary — prefer cached server data if available
                const daysData = window._lastMbtcDays || [];
                const coverRows = [];
                // fallback: build from DOM day cards if _lastMbtcDays not present
                if(!daysData || !daysData.length){
                    const allCards = Array.from(document.querySelectorAll('#hsDays .day-card'));
                    allCards.forEach(card => {
                        const day = card.getAttribute('data-day') || '';
                        const partner = (card.querySelector('.meta .partner')?.textContent || '').trim();
                        const principalText = card.querySelector('.amount.principal')?.textContent || '';
                        const commissionText = card.querySelector('.amount.commission')?.textContent || '';
                        const varianceText = card.querySelector('.amount.variance')?.textContent || '';
                        const state = Array.from(card.classList).find(c => c.indexOf('day-') === 0) || '';
                        coverRows.push({ Day: day, Partner: partner, State: state.replace('day-',''), Principal: parseCurrencyNumber(principalText), Commission: parseCurrencyNumber(commissionText), Variance: parseCurrencyNumber(varianceText) });
                    });
                } else {
                    for(let i=0;i<daysData.length;i++){
                        const d = daysData[i] || {};
                        coverRows.push({
                            Day: d.day || '',
                            Partner: d.partner || '',
                            State: d.status || '',
                            Principal: Number(d.principal || 0),
                            Commission: Number(d.commission || 0),
                            WebPrincipal: Number(d.web_principal || d.webPrincipal || 0),
                            WebCommission: Number(d.web_commission || d.webCommission || 0),
                            DepositCredit: Number(d.deposit_credit || d.depositCredit || 0),
                            Variance: (d.variance !== undefined && d.variance !== null) ? Number(d.variance) : (Number(d.principal||0) - Number(d.web_principal||0)),
                            RowsCount: Array.isArray(d.rows) ? d.rows.length : 0
                        });
                    }
                }
                // Build PESO sheet using the same two-row header + 12-column layout as the CoverPH modal
                const top = ['Date', WIC_PARTNER_NAME, '','WEB KPX','','','NET WEB REPORT','','','PARTNER VS. WEB','',''];
                const sub = ['Date','Vol','Principal','Vol','Principal','Commission','Vol','Principal','Commission','Vol','Principal','Commission'];
                const aoa = [];
                aoa.push(top);
                aoa.push(sub);
                // prefer cached days data, otherwise build from DOM
                const rowsSource = (daysData && daysData.length) ? daysData : (Array.from(document.querySelectorAll('#hsDays .day-card')) || []);
                if(Array.isArray(daysData) && daysData.length){
                    daysData.forEach(d => {
                        const day = d.day || '';
                        const wicVol = d.vol ?? '';
                        const wicP = d.total_partner_amount ?? d.principal ?? 0;
                        const webVol = d.vol ?? '';
                        const webP = d.web_principal ?? 0;
                        const webC = d.web_commission ?? d.commission ?? 0;
                        const netVol = d.vol ?? '';
                        const netP = d.web_principal ?? 0;
                        const netC = d.web_commission ?? d.commission ?? 0;
                        const pvswVol = d.vol ?? '';
                        const pvswP = (d.variance !== undefined && d.variance !== null) ? d.variance : ((d.total_partner_amount ?? 0) - (d.total_web_amount ?? 0));
                        const pvswC = -(d.web_commission ?? d.commission ?? 0);
                        aoa.push([String(day), String(wicVol), wicP, String(webVol), webP, webC, String(netVol), netP, netC, String(pvswVol), pvswP, pvswC]);
                    });
                } else {
                    rowsSource.forEach(card => {
                        if(!card) return;
                        // card may be element or day object fallback
                        if(card.nodeType === 1){
                            const day = card.getAttribute && card.getAttribute('data-day') ? card.getAttribute('data-day') : '';
                            const principalText = card.querySelector && card.querySelector('.amount.principal') ? card.querySelector('.amount.principal').textContent.trim() : '';
                            const commissionText = card.querySelector && card.querySelector('.amount.commission') ? card.querySelector('.amount.commission').textContent.trim() : '';
                            const varianceText = card.querySelector && card.querySelector('.amount.variance') ? card.querySelector('.amount.variance').textContent.trim() : '';
                            const parse = function(txt){ if(!txt) return 0; const m = String(txt).match(/-?[0-9,]+(?:\.[0-9]+)?/); return m ? Number(m[0].replace(/,/g,'')) : 0; };
                            const p = parse(principalText);
                            const c = parse(commissionText);
                            const v = parse(varianceText);
                            // best-effort mapping to columns (we don't have web split at DOM level here)
                            aoa.push([String(day), '', p, '', '', '', '', '', '', '', v, -(c)]);
                        }
                    });
                }

                // append totals row: sum numeric columns where data exists
                (function addTotalsRow(){
                    const cols = 12; // number of columns in this sheet
                    const totals = new Array(cols).fill('');
                    totals[0] = 'TOTAL';
                    for(let C = 1; C < cols; C++){
                        let sum = 0;
                        let has = false;
                        for(let R = 2; R < aoa.length; R++){
                            const row = aoa[R] || [];
                            const val = row[C];
                            if(val === null || val === undefined || val === '') continue;
                            const n = (typeof val === 'number') ? val : (String(val).replace(/[^0-9.\-]/g,'') ? Number(String(val).replace(/[^0-9.\-]/g,'')) : NaN);
                            if(!Number.isNaN(n)){ sum += n; has = true; }
                        }
                        totals[C] = has ? sum : '';
                    }
                    // only push totals if at least one column had numeric data
                    const any = totals.slice(1).some(v => v !== '' && v !== 0);
                    if(any) aoa.push(totals);
                })();

                const ws2 = XLSX.utils.aoa_to_sheet(aoa);
                // apply merges for header rows (same as modal)
                ws2['!merges'] = [
                    {s:{r:0,c:1}, e:{r:0,c:2}},
                    {s:{r:0,c:3}, e:{r:0,c:5}},
                    {s:{r:0,c:6}, e:{r:0,c:8}},
                    {s:{r:0,c:9}, e:{r:0,c:11}}
                ];
                // try to convert numeric-like cells to numbers and set number format
                const totalRows = aoa.length;
                for(let R=2; R<totalRows; R++){
                    for(let C=0; C<=11; C++){
                        const addr = XLSX.utils.encode_cell({r:R,c:C});
                        const cell = ws2[addr];
                        if(!cell || cell.v == null) continue;
                        const text = String(cell.v).trim();
                        const n = (typeof cell.v === 'number') ? cell.v : (String(text).replace(/[^0-9.\-]/g,'') ? Number(String(text).replace(/[^0-9.\-]/g,'')) : null);
                        if(n !== null && !Number.isNaN(n)) { cell.v = n; cell.t = 'n'; cell.z = '#,##0.00'; }
                    }
                }
                ws2['!cols'] = [
                    {wch:6}, {wch:8},{wch:14}, {wch:8},{wch:14},{wch:12}, {wch:8},{wch:14},{wch:12}, {wch:8},{wch:14},{wch:12}
                ];
                // use human-friendly sheet name; for WORLD INTERNATIONAL COMMUNICATIONS use 'PESO'
                const compVal = (company && company.value) ? String(company.value).toUpperCase() : '';
                const sheetName = isWorldInternationalCommunications(compVal) ? 'PESO' : 'Cover PHP';
                XLSX.utils.book_append_sheet(wb, ws2, sheetName);

                // If WORLD INTERNATIONAL COMMUNICATIONS is selected, also append a "View USD" sheet populated with USD summary
                if(isWorldInternationalCommunications(compVal)){
                    // fetch aggregated USD day data from wic-recon.php (mirrors modal logic)
                    async function fetchUsdAggregated(){
                        try{
                            const selM = (month && month.value) ? month.value : '';
                            const selY = (year && year.value) ? year.value : '';
                            const baseUrl = location.origin + '/autorecon/src/controllers/recon/wic-recon.php';
                            const selectedPartner = (company && company.value) ? String(company.value) : 'WORLDCOM INTERNATIONAL COMMUNICATIONS';
                            const listRes = await fetch(baseUrl + '?month='+encodeURIComponent(selM)+'&year='+encodeURIComponent(selY)+'&partnerName='+encodeURIComponent(selectedPartner), {cache:'no-store'});
                            if(!listRes.ok) return [];
                            const listJson = await listRes.json();
                            if(!listJson || !Array.isArray(listJson.days)) return [];
                            const aggregated = [];
                            for(const d of listJson.days){
                                try{
                                    const dayNum = Number(d.day) || 0;
                                    const detailUrl = baseUrl + '?month='+encodeURIComponent(selM)+'&year='+encodeURIComponent(selY)+'&detail=1&day='+encodeURIComponent(dayNum)+'&partnerName='+encodeURIComponent(selectedPartner);
                                    const detailRes = await fetch(detailUrl, {cache:'no-store'});
                                    if(!detailRes.ok){ aggregated.push({ day: dayNum }); continue; }
                                    const detailJson = await detailRes.json();
                                    if(!detailJson || !detailJson.success || !Array.isArray(detailJson.days)) { aggregated.push({ day: dayNum }); continue; }
                                    const detailDay = detailJson.days.find(x=>Number(x.day)===dayNum) || {};
                                    const rows = Array.isArray(detailDay.rows) ? detailDay.rows : [];

                                    // filter rows that indicate USD currency
                                    const usdRows = rows.filter(r => {
                                        try{
                                            const candidates = [r.partner_coin, r.coin, r.web_currency, r.currency, r.partner_currency, r.partner_currency_code];
                                            for(const c of candidates){ if(c && String(c).trim().toUpperCase() === 'USD') return true; }
                                        }catch(e){}
                                        return false;
                                    });

                                    if(usdRows.length === 0){ aggregated.push({ day: dayNum }); continue; }

                                    // aggregate USD rows into day-level sums (keep shape compatible with modal)
                                    let wicVol = 0, wicPrincipal = 0, wicCommission = 0;
                                    let webVol = 0, webPrincipal = 0, webCommission = 0;
                                    usdRows.forEach(r => {
                                        const pPrincipal = Number(r.partner_principal ?? r.partner_amount ?? r.partner_amount ?? 0) || 0;
                                        const wAmt = Number(r.web_amount ?? r.amount ?? 0) || 0;
                                        const wCtp = Number(r.web_ctp ?? r.ctp ?? 0) || 0;
                                        wicVol += 1;
                                        webVol += (wAmt ? 1 : 0);
                                        wicPrincipal += pPrincipal;
                                        webPrincipal += wAmt;
                                        webCommission += wCtp;
                                    });

                                    // compute NET WEB and PVSW using same approach as modal
                                    let dupVolWeb = 0, dupPWeb = 0, dupCWeb = 0;
                                    const netVol = (isFinite(webVol) ? (webVol - dupVolWeb) : '');
                                    const netP = webPrincipal - dupPWeb;
                                    const netC = webCommission - dupCWeb;

                                    const pvswVol = (isFinite(wicVol) && netVol !== '' && !isNaN(Number(netVol))) ? (wicVol - Number(netVol)) : '';
                                    const pvswP = wicPrincipal - netP;
                                    const pvswC = wicCommission - netC;

                                    aggregated.push({
                                        day: dayNum,
                                        vol: webVol,
                                        total_partner_amount: wicPrincipal,
                                        commission: wicCommission,
                                        web_principal: webPrincipal,
                                        web_commission: webCommission,
                                        rows: usdRows,
                                        _computed: { netVol, netP, netC, pvswVol, pvswP, pvswC }
                                    });
                                }catch(err){ aggregated.push({ day: Number(d.day) || 0 }); }
                            }
                            return aggregated;
                        }catch(e){ console.warn('fetchUsdAggregated failed', e); return []; }
                    }

                    try{
                        const usdAgg = await fetchUsdAggregated();
                        const top = ['Date', WIC_PARTNER_NAME + ' USD', '','WEB KPX','','','NET WEB REPORT','','','PARTNER VS. WEB','',''];
                        const sub = ['Date','Vol','Principal','Vol','Principal','COMMISSION','Vol','Principal','COMMISSION','Vol','Principal','COMMISSION'];
                        const aoaUsd = [];
                        aoaUsd.push(top);
                        aoaUsd.push(sub);

                        // Ensure rows for days 1..32 (modal layout)
                        for(let di=1; di<=32; di++){
                            const d = usdAgg.find(x => Number(x.day) === di) || {};
                            const row = [
                                di,
                                (d && d.total_partner_amount !== undefined) ? (d.vol || '') : '',
                                (d && d.total_partner_amount !== undefined) ? (d.total_partner_amount || 0) : '',
                                (d && d.web_principal !== undefined) ? (d.vol || '') : '',
                                (d && d.web_principal !== undefined) ? (d.web_principal || 0) : '',
                                (d && d.web_commission !== undefined) ? (d.web_commission || 0) : '',
                                (d && d._computed) ? (d._computed.netVol || '') : '',
                                (d && d._computed) ? (d._computed.netP || 0) : '',
                                (d && d._computed) ? (d._computed.netC || 0) : '',
                                (d && d._computed) ? (d._computed.pvswVol || '') : '',
                                (d && d._computed) ? (d._computed.pvswP || 0) : '',
                                (d && d._computed) ? (d._computed.pvswC || 0) : ''
                            ];
                            aoaUsd.push(row);
                        }

                        // append totals row (sum numeric columns where any data exists)
                        (function(){
                            const totals = [];
                            let any = false;
                            for(let c=0;c<12;c++){
                                let sum = 0; let colHas = false;
                                for(let r=2;r<aoaUsd.length;r++){
                                    const v = aoaUsd[r][c];
                                    if(v !== null && v !== undefined && v !== ''){ colHas = true; const n = Number(String(v).replace(/,/g,'')) || 0; sum += n; }
                                }
                                if(colHas){ totals.push(sum); any = true; } else { totals.push(''); }
                            }
                            if(any) aoaUsd.push(totals);
                        })();

                        const wsUsd = XLSX.utils.aoa_to_sheet(aoaUsd);
                        wsUsd['!merges'] = [
                            {s:{r:0,c:1}, e:{r:0,c:2}},
                            {s:{r:0,c:3}, e:{r:0,c:5}},
                            {s:{r:0,c:6}, e:{r:0,c:8}},
                            {s:{r:0,c:9}, e:{r:0,c:11}}
                        ];

                        // convert numeric-like cells to numbers for rows starting at R=2
                        const totalRowsUsd = aoaUsd.length;
                        for(let R=2; R<totalRowsUsd; R++){
                            for(let C=0; C<12; C++){
                                const addr = XLSX.utils.encode_cell({r:R,c:C});
                                const cell = wsUsd[addr]; if(!cell || cell.v == null) continue;
                                const text = String(cell.v).trim();
                                const cleaned = text.replace(/[^0-9.\-]/g,'');
                                const n = Number(cleaned);
                                if(!Number.isNaN(n)) { cell.v = n; cell.t = 'n'; cell.z = '#,##0.00'; }
                            }
                        }

                        wsUsd['!cols'] = [
                            {wch:6},
                            {wch:10},{wch:14},
                            {wch:8},{wch:14},{wch:12},
                            {wch:8},{wch:14},{wch:12},
                            {wch:8},{wch:14},{wch:12}
                        ];

                        XLSX.utils.book_append_sheet(wb, wsUsd, 'View USD');
                    }catch(e){ console.warn('Failed to append View USD sheet', e); }
                }

                // Append a totals sheet if window.__coverTotals__ exists
                if(window.__coverTotals__){
                    const totals = window.__coverTotals__;
                    const totRows = [
                        { Metric: 'netVol', Value: totals.netVol || 0 },
                        { Metric: 'netP', Value: totals.netP || 0 },
                        { Metric: 'netC', Value: totals.netC || 0 },
                        { Metric: 'dupP', Value: totals.dupP || 0 },
                        { Metric: 'dupC', Value: totals.dupC || 0 },
                        { Metric: 'pvswP', Value: totals.pvswP || 0 },
                        { Metric: 'pvswC', Value: totals.pvswC || 0 },
                        { Metric: 'depositDebit', Value: totals.depositDebit || 0 },
                        { Metric: 'depositCredit', Value: totals.depositCredit || 0 },
                        { Metric: 'depositVar', Value: totals.depositVar || 0 },
                        { Metric: 'ag', Value: totals.ag || 0 },
                        { Metric: 'ah', Value: totals.ah || 0 }
                    ];
                    const ws3 = XLSX.utils.json_to_sheet(totRows, {header:['Metric','Value']});
                    XLSX.utils.book_append_sheet(wb, ws3, 'CoverTotals');
                }

                // file name
                const comp = (company && company.value) ? company.value : 'company';
                const selectedRange = getSelectedDateRange();
                const startVal = selectedRange.startDate || 'start';
                const endVal = selectedRange.endDate || 'end';
                let fname;
                if(isMetrobankHeadOffice(comp)){
                    fname = `${MBTC_PARTNER_NAME}-${startVal}-to-${endVal}.xlsx`;
                } else if(isWorldInternationalCommunications(comp)){
                    fname = `${WIC_PARTNER_NAME}-${startVal}-to-${endVal}.xlsx`;
                } else {
                    fname = `recon-export-${comp}-${startVal}-to-${endVal}.xlsx`;
                }
                XLSX.writeFile(wb, fname);
            }catch(err){
                console.error('Export failed', err);
                try{ alert('Export failed: ' + err.message); }catch(e){}
            }
        }

        if (exportBtn) {
            exportBtn.addEventListener('click', function(e){
                e.preventDefault();
                if(!isValidDateRange(true)) return;
                // generate and download the workbook
                buildAndDownloadWorkbook();
            });
        }

        // View Cover PH button: opens modal and populates Partner/Web tables (frontend-only)
        const viewCoverBtn = document.getElementById('hsViewCoverPH');
        if(viewCoverBtn){
                viewCoverBtn.addEventListener('click', function(e){
                    e.preventDefault();
                    const days = window._lastMbtcDays || [];
                    const companyName = (company && company.value) ? String(company.value).toUpperCase() : '';
                    const modalId = isWorldInternationalCommunications(companyName) ? 'wicCoverPhModal' : 'mbtcCoverPhModal';
                    const modalRoot = document.getElementById(modalId);
                    const prefix = isWorldInternationalCommunications(companyName) ? 'wic' : 'mbtc';
                    // reset totals
                    let totVol = 0, totP = 0, totC = 0, totVolWeb = 0, totPWeb = 0, totCWeb = 0;
                    // reset derived totals container
                    window.__coverTotals__ = { netVol:0, netP:0, netC:0, dupP:0, dupC:0, pvswP:0, pvswC:0, depositDebit:0, depositCredit:0, depositVar:0, ag:0, ah:0 };
                    // fill unified table rows by day
                    for(let day=1; day<=32; day++){
                        const row = (modalRoot && modalRoot.querySelector) ? modalRoot.querySelector('tr[data-day="'+day+'"]') : null;
                        const dObj = days.find(dd => String(dd.day) === String(day)) || null;
                        const vol = dObj && (dObj.vol || dObj.vol === 0) ? dObj.vol : '';
                        const p = dObj && (dObj.principal || dObj.principal === 0) ? Number(dObj.principal) : 0;
                        const c = dObj && (dObj.commission || dObj.commission === 0) ? Number(dObj.commission) : 0;
                        const wp = dObj && (dObj.web_principal || dObj.web_principal === 0) ? Number(dObj.web_principal) : 0;
                        const wc = dObj && (dObj.web_commission || dObj.web_commission === 0) ? Number(dObj.web_commission) : 0;

                        // compute duplicate sums (only possible when detail rows are present)
                        let dupVolWeb = 0, dupPWeb = 0, dupCWeb = 0;
                        let dupVolPart = 0, dupPPart = 0, dupCPart = 0;
                        try{
                            const dups = Array.isArray(dObj && dObj.duplicates) ? dObj.duplicates : [];
                            const dupWebRefs = dups.filter(dd=>dd.type==='web').map(dd=>String(dd.ref));
                            const dupPartRefs = dups.filter(dd=>dd.type==='partner').map(dd=>String(dd.ref));
                            if(Array.isArray(dObj && dObj.rows) && dObj.rows.length){
                                dObj.rows.forEach(r => {
                                    const ref = String(r.ref || '');
                                    // web duplicates: sum web_amount / web_ctp
                                    if(dupWebRefs.indexOf(ref) !== -1){ dupVolWeb += 1; dupPWeb += Number(r.web_amount || 0); dupCWeb += Number(r.web_ctp || 0); }
                                    // partner duplicates: sum partner_principal / partner_commission
                                    if(dupPartRefs.indexOf(ref) !== -1){ dupVolPart += 1; dupPPart += Number(r.partner_principal || 0); dupCPart += Number(r.partner_commission || 0); }
                                });
                            }
                        }catch(e){ /* ignore */ }

                        // Net Web = WEB KPI - duplicate web amounts (simple model)
                        const netVol = (vol !== '' && !isNaN(Number(vol))) ? Number(vol) - dupVolWeb : '';
                        const netP = wp - dupPWeb;
                        const netC = wc - dupCWeb;

                        // Partner vs Web = MBTC - Net Web
                        const pvswVol = (vol !== '' && !isNaN(Number(vol))) ? Number(vol) - (netVol || 0) : '';
                        const pvswP = p - netP;
                        const pvswC = c - netC;

                        // VAT/withholding adjustments (AG/AH in spreadsheet): AG = commission / 1.12 * 0.02, AH = commission - AG
                        const ag = c ? (c / 1.12 * 0.02) : 0;
                        const ah = c ? (c - ag) : 0;

                        // deposit values if available on day object
                        const depositDebit = Number(dObj && (dObj.deposit_debit || dObj.depositDebit) || 0);
                        const depositCredit = Number(dObj && (dObj.deposit_credit || dObj.depositCredit) || 0);
                        const depositVar = depositCredit - p - ah; // AE - principal - AH (roughly following spreadsheet)

                        // populate row cells if present
                        if(row){
                            const setText = (sel, val) => { const el = row.querySelector(sel); if(el) el.textContent = val; };
                            // partner columns use prefix (mbtc/wic)
                            setText('.' + prefix + '-vol', vol !== '' ? String(vol) : '');
                            setText('.' + prefix + '-principal', p ? p.toLocaleString() : '');
                            setText('.' + prefix + '-commission', c ? c.toLocaleString() : '');

                            setText('.webkpi-vol', vol !== '' ? String(vol) : '');
                            setText('.webkpi-principal', wp ? wp.toLocaleString() : '');
                            setText('.webkpi-commission', wc ? wc.toLocaleString() : '');

                            // duplicate (web) columns
                            setText('.dup-vol', dupVolWeb ? String(dupVolWeb) : '');
                            setText('.dup-principal', dupPWeb ? dupPWeb.toLocaleString() : '');
                            setText('.dup-commission', dupCWeb ? dupCWeb.toLocaleString() : '');

                            // net web columns
                            setText('.netweb-vol', (netVol !== '' && !isNaN(Number(netVol))) ? String(netVol) : '');
                            setText('.netweb-principal', netP ? netP.toLocaleString() : '');
                            setText('.netweb-commission', netC ? netC.toLocaleString() : '');

                            // partner vs web
                            setText('.pvsw-vol', (pvswVol !== '' && !isNaN(Number(pvswVol))) ? String(pvswVol) : '');
                            setText('.pvsw-principal', pvswP ? pvswP.toLocaleString() : '');
                            setText('.pvsw-commission', pvswC ? pvswC.toLocaleString() : '');

                            // deposit columns
                            setText('.deposit-debit', depositDebit ? depositDebit.toLocaleString() : '');
                            setText('.deposit-credit', depositCredit ? depositCredit.toLocaleString() : '');
                            setText('.deposit-variance', Number(depositVar) ? Number(depositVar).toLocaleString() : '');
                            // AG / AH columns
                            setText('.deposit-ag', ag ? ag.toLocaleString() : '');
                            setText('.deposit-ah', ah ? ah.toLocaleString() : '');
                        }

                        // accumulate totals
                        const volNum = (vol !== '' && !isNaN(Number(vol))) ? Number(vol) : 0;
                        totVol += volNum; totP += p; totC += c; totVolWeb += volNum; totPWeb += wp; totCWeb += wc;

                        // accumulate net totals as well (for footer)
                        // create accumulators on outer scope if not present
                        if(typeof window.__coverTotals__ === 'undefined') window.__coverTotals__ = { netVol:0, netP:0, netC:0, dupP:0, dupC:0, pvswP:0, pvswC:0, depositDebit:0, depositCredit:0, depositVar:0 };
                        window.__coverTotals__.netVol += (netVol && !isNaN(Number(netVol))) ? Number(netVol) : 0;
                        window.__coverTotals__.netP += netP || 0;
                        window.__coverTotals__.netC += netC || 0;
                        window.__coverTotals__.dupP += dupPWeb || 0;
                        window.__coverTotals__.dupC += dupCWeb || 0;
                        window.__coverTotals__.pvswP += pvswP || 0;
                        window.__coverTotals__.pvswC += pvswC || 0;
                        window.__coverTotals__.depositDebit += depositDebit || 0;
                        window.__coverTotals__.depositCredit += depositCredit || 0;
                        window.__coverTotals__.depositVar += depositVar || 0;
                        window.__coverTotals__.ag += ag || 0;
                        window.__coverTotals__.ah += ah || 0;
                    }
                    // totals in header (partner / web)
                    const hdrPPart = document.querySelector('#mbtcCoverPhModal [data-role="principal-partner"]');
                    const hdrPWeb = document.querySelector('#mbtcCoverPhModal [data-role="principal-web"]');
                    const hdrCPart = document.querySelector('#mbtcCoverPhModal [data-role="commission-partner"]');
                    const hdrCWeb = document.querySelector('#mbtcCoverPhModal [data-role="commission-web"]');
                    const hdrV = document.querySelector('#mbtcCoverPhModal [data-role="variance"]');
                    if(hdrPPart) hdrPPart.textContent = totP.toLocaleString() + ' pesos';
                    if(hdrPWeb) hdrPWeb.textContent = totPWeb.toLocaleString() + ' pesos';
                    if(hdrCPart) hdrCPart.textContent = totC.toLocaleString() + ' pesos';
                    if(hdrCWeb) hdrCWeb.textContent = totCWeb.toLocaleString() + ' pesos';
                    // show deposit variance total if available from aggregated derived totals, else fallback to partner-web variance
                    const coverTotals = window.__coverTotals__ || {};
                    const depositVarianceTotal = Number(coverTotals.depositVar || 0);
                    const headerVariance = depositVarianceTotal !== 0 ? depositVarianceTotal : (totP - totPWeb);
                    if(hdrV) hdrV.textContent = Number(headerVariance).toLocaleString() + ' pesos';

                    // update footer totals (Vol / Principal / Commission)
                    // support both MBTC and WORLD INTERNATIONAL COMMUNICATIONS modals (data-role may vary)
                    const elTotalMbtc = (modalRoot && (modalRoot.querySelector('[data-role="total-wic"]') || modalRoot.querySelector('[data-role="total-mbtc"]')));
                    const elTotalWeb = (modalRoot && modalRoot.querySelector('[data-role="total-webkpi"]'));
                    if(elTotalMbtc) elTotalMbtc.textContent = totVol.toLocaleString() + ' / ' + totP.toLocaleString() + ' / ' + totC.toLocaleString();
                    if(elTotalWeb) elTotalWeb.textContent = totVolWeb.toLocaleString() + ' / ' + totPWeb.toLocaleString() + ' / ' + totCWeb.toLocaleString();

                    // derived totals: netweb, duplicates, partner-vs-web, deposit
                    const elTotalNetweb = modalRoot ? modalRoot.querySelector('[data-role="total-netweb"]') : null;
                    const elTotalDup = modalRoot ? modalRoot.querySelector('[data-role="total-dup"]') : null;
                    const elTotalPvsw = modalRoot ? modalRoot.querySelector('[data-role="total-pvsw"]') : null;
                    const elTotalDeposit = modalRoot ? modalRoot.querySelector('[data-role="total-deposit"]') : null;
                    const ct = window.__coverTotals__ || { netVol:0, netP:0, netC:0, dupP:0, dupC:0, pvswP:0, pvswC:0, depositDebit:0, depositCredit:0, depositVar:0 };
                    if(elTotalNetweb) elTotalNetweb.textContent = ct.netVol.toLocaleString() + ' / ' + ct.netP.toLocaleString() + ' / ' + ct.netC.toLocaleString();
                    if(elTotalDup) elTotalDup.textContent = ' / ' + ct.dupP.toLocaleString() + ' / ' + ct.dupC.toLocaleString();
                    if(elTotalPvsw) elTotalPvsw.textContent = ' / ' + ct.pvswP.toLocaleString() + ' / ' + ct.pvswC.toLocaleString();
                    if(elTotalDeposit) elTotalDeposit.textContent = ct.depositDebit.toLocaleString() + ' / ' + ct.depositCredit.toLocaleString() + ' / ' + ct.depositVar.toLocaleString();
                    // AG / AH totals
                    const elTotalAg = modalRoot ? modalRoot.querySelector('[data-role="total-ag"]') : null;
                    const elTotalAh = modalRoot ? modalRoot.querySelector('[data-role="total-ah"]') : null;
                    if(elTotalAg) elTotalAg.textContent = Number(ct.ag || 0).toLocaleString();
                    if(elTotalAh) elTotalAh.textContent = Number(ct.ah || 0).toLocaleString();

                        // show modal (MBTC or WORLD INTERNATIONAL COMMUNICATIONS)
                    if(modalRoot){ modalRoot.style.display = 'block'; try{ document.body.style.overflow='hidden'; }catch(e){} }
                });
        }
                    // wire View USD button to open the WORLD INTERNATIONAL COMMUNICATIONS USD modal
            if(_viewUsdBtn){
                _viewUsdBtn.addEventListener('click', function(e){
                    e.preventDefault();
                    const usdModal = document.getElementById('wicUsdModal');
                    if(usdModal){ usdModal.style.display = 'block'; try{ document.body.style.overflow='hidden'; }catch(e){} }
                    else { try{ alert('NAG Ground Breaking PA'); }catch(e){} }
                });
            }
    })();
    </script>

    <script>
    // Floating tooltip to avoid stacking-context issues with sticky header
    (function(){
        const daysContainer = document.getElementById('hsDays');
        if (!daysContainer) return;

        let tooltipEl = document.createElement('div');
        tooltipEl.className = 'floating-tooltip';
        tooltipEl.style.position = 'fixed';
        tooltipEl.style.background = '#2b2b2b';
        tooltipEl.style.color = '#fff';
        tooltipEl.style.padding = '8px 10px';
        tooltipEl.style.borderRadius = '6px';
        tooltipEl.style.boxShadow = '0 8px 20px rgba(2,6,23,0.24)';
        tooltipEl.style.fontSize = '12px';
        tooltipEl.style.lineHeight = '1.3';
        tooltipEl.style.minWidth = '220px';
        tooltipEl.style.zIndex = '99999';
        tooltipEl.style.display = 'none';
        tooltipEl.style.pointerEvents = 'none';
        document.body.appendChild(tooltipEl);

        function showTooltip(target){
            const txt = target.getAttribute('data-tooltip');
            if (!txt) return;
            tooltipEl.textContent = txt;
            tooltipEl.style.display = 'block';
            requestAnimationFrame(() => {
                const rect = target.getBoundingClientRect();
                const tRect = tooltipEl.getBoundingClientRect();
                let left = rect.left + (rect.width/2) - (tRect.width/2);
                left = Math.max(8, Math.min(left, window.innerWidth - tRect.width - 8));
                let top = rect.top - tRect.height - 8;
                if (top < 8) top = rect.bottom + 8;
                tooltipEl.style.left = left + 'px';
                tooltipEl.style.top = top + 'px';
            });
        }

        function hideTooltip(){
            tooltipEl.style.display = 'none';
        }

        daysContainer.addEventListener('mouseover', function(e){
            const t = e.target.closest && e.target.closest('.day-card[data-tooltip]');
            if (t) showTooltip(t);
        });
        daysContainer.addEventListener('mouseout', function(e){
            const t = e.target.closest && e.target.closest('.day-card[data-tooltip]');
            if (t) hideTooltip();
        });
        window.addEventListener('scroll', hideTooltip, {passive:true});
        window.addEventListener('resize', hideTooltip, {passive:true});
    })();
    </script>
        <!-- Sticky behavior is handled by CSS (.home-section__sticky { position: sticky }) -->
</section>
<?php
// Shared recon-close styles (standardize CLOSE button) for all recon view modals
echo '<link rel="stylesheet" href="/autorecon/src/modals/recon-view/recon-close.css">';
// Include MBTC recon modal so it's available when user clicks a day card
include __DIR__ . '/../../../modals/mbtc-view/mbtc-recon-view-modal.php';
// include new Cover PH modals
include __DIR__ . '/../../../modals/mbtc-view/mbtc-coverph-view-modal.php';
// include WORLD INTERNATIONAL COMMUNICATIONS Cover PH modal (PESO)
include __DIR__ . '/../../../modals/wic-view/wic-coverph-view-modal.php';
// include WORLD INTERNATIONAL COMMUNICATIONS USD modal (so View USD can open it)
include __DIR__ . '/../../../modals/wic-view/wic-usd-view-modal.php';
// include WORLD INTERNATIONAL COMMUNICATIONS recon modal so it's available when user clicks a day card
include __DIR__ . '/../../../modals/wic-view/wic-recon-view-modal.php';
// include MONEYGRAM recon modal so it's available when user clicks a day card
include __DIR__ . '/../../../modals/moneygram-view/moneygram-recon-view-modal.php';
// include RCBC recon modal so it's available when user clicks a day card
include __DIR__ . '/../../../modals/rcbc-view/rcbc-recon-view-modal.php';
// include BDO recon modal so it's available when user clicks a day card
include __DIR__ . '/../../../modals/bdo-view/bdo-recon-view-modal.php';
?>
