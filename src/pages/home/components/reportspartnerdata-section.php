<?php
// Reports UI: Web data transactions (filtered by corporate partner)
// Loads partner list from master file (UI-only)
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
	$partners = [];
}
?>
<div class="reports-webdata-content">
	<style>
		.reports-webdata-content .autocomplete-field {
			position: relative;
			width: min(100%, 72ch);
		}

		.reports-webdata-content .autocomplete-list {
			position: absolute;
			top: calc(100% + 4px);
			left: 0;
			right: 0;
			min-width: 100%;
			max-height: 260px;
			overflow-y: auto;
			margin: 0;
			padding: 4px 0;
			list-style: none;
			background: #fff;
			border: 1px solid #e6eef6;
			border-radius: 6px;
			box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
			box-sizing: border-box;
			z-index: 50;
		}

		.reports-webdata-content .autocomplete-item {
			padding: 8px 10px;
			font-size: 0.9rem;
			color: #1f2937;
			cursor: pointer;
		}

		.reports-webdata-content .autocomplete-item:hover,
		.reports-webdata-content .autocomplete-item.is-active {
			background: #f3f4f6;
		}

		.reports-webdata-content .rwd-pagination {
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 0.75rem;
			flex-wrap: wrap;
			margin-top: 0.9rem;
		}

		.reports-webdata-content .rwd-pagination-info {
			color: #6b7280;
			font-size: 0.9rem;
		}

		.reports-webdata-content .rwd-pagination-actions {
			display: flex;
			gap: 0.5rem;
			align-items: center;
		}

		/* Partner table: sticky header and scrollable body */
		.rpd-results .rwd-results-table-wrap {
			overflow: auto;
			/* height will be set dynamically by JS */
		}

		.rpd-results .rwd-results-table-wrap table thead th {
			position: sticky;
			top: 0;
			z-index: 6;
			background: #f9fafb;
		}

		/* Validation modal and invalid field styles */
		.rpd-text-danger { color: #dc3545; font-weight: 700; margin-left: 6px; }
		.rpd-invalid { border-color: #dc3545 !important; box-shadow: 0 0 0 4px rgba(220,53,69,0.06) !important; }

		.rpd-modal-overlay { position: fixed; inset: 0; background: rgba(2,6,23,0.55); display: flex; align-items: center; justify-content: center; z-index: 9999; }
		.rpd-modal { width: 420px; background: #fff; border-radius: 16px; box-shadow: 0 20px 40px rgba(2,6,23,0.24); padding: 18px; animation: rpdModalIn 220ms ease both; }
		.rpd-modal-header { display:flex; align-items:center; gap:10px; font-weight:800; color:#111; margin-bottom:8px; }
		.rpd-modal-body { color:#374151; font-size:0.95rem; margin-bottom:14px; }
		.rpd-modal-list { margin:8px 0 0 16px; color:#1f2937; }
		.rpd-modal-footer { display:flex; justify-content:center; }
		.rpd-modal-ok { background:#dc3545; color:#fff; border:none; border-radius:8px; padding:10px 24px; font-weight:600; cursor:pointer; }
		.rpd-modal-ok:hover { background:#b02a37; }

		.rpd-summary-badges { display:inline-flex; gap:10px; align-items:center; flex-wrap:wrap; margin-left:10px; }
		.rpd-summary-badge { display:inline-flex; align-items:center; padding:6px 12px; border-radius:999px; font-weight:700; font-size:0.9rem; line-height:1; box-shadow:0 2px 6px rgba(2,6,23,0.06); }
		.rpd-summary-badge--php { background:#ecfdf5; color:#065f46; }
		.rpd-summary-badge--usd { background:#eff6ff; color:#1e3a8a; }

		@keyframes rpdModalIn { from { opacity:0; transform: translateY(-6px) scale(0.98); } to { opacity:1; transform: translateY(0) scale(1); } }
	</style>
	<div class="reports-inner" style="padding:.25rem">
		<h3 style="margin:0 0 0.25rem;color:#1f2937;font-size:1.125rem;font-weight:600">Partner data transactions</h3>
		<p style="margin:0 0 1rem;color:#6b7280;font-size:0.9rem">View all transactions uploaded via the Partner Data Uploader, filtered by corporate partner.</p>

		<form id="rpdForm" style="background:#fff;border:1px solid #e6eef6;border-radius:8px;padding:0.75rem;display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap">
			<label for="rpdPartner" style="flex:1;display:flex;flex-direction:column;gap:0.25rem;font-size:0.75rem;color:#6b7280;min-width:300px">CORPORATE PARTNER
				<div class="autocomplete-field">
					<input id="rpdPartner" name="partner" placeholder="Search corporate partner" autocomplete="off" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;background:#fff;width:100%;box-sizing:border-box;color:#111;font-size:.9rem;outline:none">
					<ul class="autocomplete-list" id="rpdPartnerSuggestions" role="listbox" hidden></ul>
				</div>
			</label>

			<div class="rwd-duration" style="display:flex;align-items:flex-end;gap:.5rem;flex-wrap:wrap">
				<div style="display:flex;flex-direction:column;gap:0.25rem">
					<label class="rwd-duration-label" style="font-size:.75rem;color:#6b7280;white-space:nowrap;">Start date <span class="rpd-text-danger">*</span></label>
					<input id="rpdStartDate" name="start_date" type="date" class="rwd-duration-input" aria-label="Start date" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;background:#fff;min-width:12ch;box-sizing:border-box;font-size:.95rem;">
				</div>
				<span class="rwd-duration-sep" style="color:#6b7280;font-weight:600;margin-bottom:8px">—</span>
				<div style="display:flex;flex-direction:column;gap:0.25rem">
					<label class="rwd-duration-label" style="font-size:.75rem;color:#6b7280;white-space:nowrap;">End date <span class="rpd-text-danger">*</span></label>
					<input id="rpdEndDate" name="end_date" type="date" class="rwd-duration-input" aria-label="End date" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;background:#fff;min-width:12ch;box-sizing:border-box;font-size:.95rem;">
				</div>
			</div>

			<div style="display:flex;align-items:flex-end;gap:0.5rem;margin-left:auto">
				<button type="button" id="rpdViewBtn" class="material-btn material-btn--primary" style="padding:0.55rem 1rem;border-radius:6px">View transactions</button>
			</div>
		</form>

		<!-- Validation modal (hidden by default) -->
		<div id="rpdModalOverlay" class="rpd-modal-overlay" style="display:none">
			<div id="rpdRequiredModal" class="rpd-modal" role="dialog" aria-modal="true" aria-labelledby="rpdModalHeader">
				<div class="rpd-modal-header"><span style="color:#f59e0b;font-size:20px">⚠</span><div id="rpdModalHeader">Required Fields</div></div>
				<div class="rpd-modal-body">
					<p>Please complete the following required fields:</p>
					<ul id="rpdMissingList" class="rpd-modal-list"></ul>
				</div>
				<div class="rpd-modal-footer">
					<button id="rpdModalOkBtn" class="rpd-modal-ok">OK</button>
				</div>
			</div>
		</div>

		<!-- Results container -->
		<div id="rpdResults" class="rwd-results" style="margin-top:1.5rem;display:none">
			<div class="rwd-results-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;padding-bottom:0.75rem;border-bottom:2px solid #e6eef6">
				<div>
					<h4 id="rpdResultsTitle" style="margin:0;color:#1f2937;font-size:1rem;font-weight:600"></h4>
					<p id="rpdResultsSummary" style="margin:0.25rem 0 0 0;color:#6b7280;font-size:0.9rem"></p>
				</div>
				<button type="button" id="rpdExportBtn" class="material-btn material-btn--secondary" style="padding:0.25rem 1rem;border-radius:6px">Export Excel</button>
			</div>
			<div class="rwd-results-table-wrap" style="overflow-x:auto;border:1px solid #e6eef6;border-radius:8px">
				<table id="rpdResultsTable" class="rwd-results-table" style="width:100%;border-collapse:collapse;font-size:0.6rem">
					<thead style="background:#f9fafb;border-bottom:1px solid #e6eef6">
						<tr>
							<th id="rpdThNo" style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">No.</th>
							<th id="rpdThControlSeries" style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">Control Series</th>
							<th id="rpdThDateClaimed" style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">Date Claimed</th>
							<th id="rpdThCcrefNo" style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">CCREF NO</th>
							<th id="rpdThCurrency" style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">Currency</th>
							<th id="rpdThAmount" style="padding:0.5rem;text-align:right;color:#6b7280;font-weight:600;white-space:nowrap">Amount</th>
							<th id="rpdThSender" style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">Sender</th>
							<th id="rpdThBeneficiary" style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">Beneficiary</th>
							<th id="rpdThOperator" style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">Operator</th>
							<th id="rpdThBranch" style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">Branch</th>
						</tr>
					</thead>
					<tbody id="rpdResultsBody">
					</tbody>
				</table>
			</div>
			<div id="rpdPagination" class="rwd-pagination" style="display:none">
				<div id="rpdPaginationInfo" class="rwd-pagination-info"></div>
				<div class="rwd-pagination-actions">
					<button type="button" id="rpdPrevBtn" class="material-btn material-btn--secondary" style="padding:0.55rem 1rem;border-radius:6px">Previous</button>
					<button type="button" id="rpdNextBtn" class="material-btn material-btn--secondary" style="padding:0.55rem 1rem;border-radius:6px">Next</button>
				</div>
			</div>
		</div>
	</div>

	<script>
	(function(){
		const form = document.getElementById('rpdForm');
		const input = document.getElementById('rpdPartner');
		const viewBtn = document.getElementById('rpdViewBtn');
		const resultsDiv = document.getElementById('rpdResults');
		const resultsTitle = document.getElementById('rpdResultsTitle');
		const resultsSummary = document.getElementById('rpdResultsSummary');
		const resultsBody = document.getElementById('rpdResultsBody');
		const exportBtn = document.getElementById('rpdExportBtn');
		const pagination = document.getElementById('rpdPagination');
		const paginationInfo = document.getElementById('rpdPaginationInfo');
		const prevBtn = document.getElementById('rpdPrevBtn');
		const nextBtn = document.getElementById('rpdNextBtn');
		const partners = <?= json_encode($partners) ?>;
		const PAGE_SIZE = 10000;
		let currentFilters = null;
		let lastReportData = null;

		// Autocomplete function
		function attachPartnerAutocomplete(inputEl, suggestions){
			const container = inputEl ? inputEl.closest('.autocomplete-field') : null;
			const list = container ? container.querySelector('.autocomplete-list') : null;
			if(!inputEl || !container || !list) return;

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
				inputEl.value = value;
				closeSuggestions();
				inputEl.dispatchEvent(new Event('input', { bubbles: true }));
				inputEl.dispatchEvent(new Event('change', { bubbles: true }));
			}

			function renderSuggestions(){
				const matches = getMatches(inputEl.value);
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

			inputEl.addEventListener('input', renderSuggestions);
			inputEl.addEventListener('focus', renderSuggestions);
			inputEl.addEventListener('keydown', function(event){
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

		// Initialize autocomplete
		attachPartnerAutocomplete(input, partners);

		// Modal helpers
		const modalOverlay = document.getElementById('rpdModalOverlay');
		const missingListEl = document.getElementById('rpdMissingList');
		const modalOkBtn = document.getElementById('rpdModalOkBtn');

		function showRequiredModal(missing) {
			if (!Array.isArray(missing)) missing = [String(missing || '')];
			missingListEl.innerHTML = '';
			missing.forEach(it => {
				const li = document.createElement('li');
				li.textContent = it;
				missingListEl.appendChild(li);
			});
			// show overlay
			modalOverlay.style.display = 'flex';
		}

		function hideRequiredModal() {
			modalOverlay.style.display = 'none';
			// clear invalid markers
			const sd = document.getElementById('rpdStartDate');
			const ed = document.getElementById('rpdEndDate');
			if (sd) sd.classList.remove('rpd-invalid');
			if (ed) ed.classList.remove('rpd-invalid');
		}

		modalOkBtn.addEventListener('click', function(){ hideRequiredModal(); input.focus(); });

		// Remove red border on input when user fills value
		const sdEl = document.getElementById('rpdStartDate');
		const edEl = document.getElementById('rpdEndDate');
		if (sdEl) sdEl.addEventListener('input', function(){ if (sdEl.value) sdEl.classList.remove('rpd-invalid'); });
		if (edEl) edEl.addEventListener('input', function(){ if (edEl.value) edEl.classList.remove('rpd-invalid'); });

		// Format currency
		function formatCurrency(value, currency) {
			if (value === '' || value === null || value === undefined || isNaN(Number(value))) return '';
			const num = parseFloat(value);
			try {
				return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
			} catch (e) {
				return num.toFixed(2);
			}
		}

		// Format currency but allow zero to be rendered as 0.00 (used for totals)
		function formatCurrencyAllowZero(value) {
			const num = Number(value);
			if (!Number.isFinite(num)) return '0.00';
			try {
				return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
			} catch (e) {
				return num.toFixed(2);
			}
		}

		function formatNumber(value) {
			const num = Number(value || 0);
			if (!Number.isFinite(num)) return String(value || '0');
			return num.toLocaleString('en-US');
		}

		function getAppBasePath() {
			const parts = window.location.pathname.split('/').filter(Boolean);
			return parts.length > 0 ? `/${parts[0]}` : '';
		}

		// Format date
		function formatDate(dateStr) {
			if (!dateStr) return '';
			const date = new Date(dateStr);
			if (isNaN(date.getTime())) return dateStr;
			return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
		}

		async function fetchTransactions(page) {
			if (!currentFilters) return;

			viewBtn.disabled = true;
			viewBtn.textContent = 'Loading...';
			prevBtn.disabled = true;
			nextBtn.disabled = true;

			try {
				const params = new URLSearchParams({
					partner: currentFilters.partner,
					start_date: currentFilters.startDate,
					end_date: currentFilters.endDate,
					page: String(page),
					per_page: String(PAGE_SIZE)
				});

				const response = await fetch(`${getAppBasePath()}/src/controllers/excelcontrol/partner-data-report.php?${params.toString()}`);
				const data = await response.json();

				if (!data.success) {
					alert('Error: ' + (data.error || 'Unknown error'));
					return;
				}

				lastReportData = data;
				displayResults(data);
			} catch (error) {
				console.error('Error:', error);
				alert('Failed to fetch transactions: ' + error.message);
			} finally {
				viewBtn.disabled = false;
				viewBtn.textContent = 'View transactions';
			}
		}

		async function runReport() {
			// Validate required fields: partner, start date, end date
			const missing = [];
			const partnerVal = input.value && input.value.trim() ? input.value.trim() : '';
			const startDate = (document.getElementById('rpdStartDate') || {}).value || '';
			const endDate = (document.getElementById('rpdEndDate') || {}).value || '';

			// clear previous markers
			if (sdEl) sdEl.classList.remove('rpd-invalid');
			if (edEl) edEl.classList.remove('rpd-invalid');

			if (!partnerVal) missing.push('Corporate Partner');
			if (!startDate) { missing.push('Start Date'); if (sdEl) sdEl.classList.add('rpd-invalid'); }
			if (!endDate) { missing.push('End Date'); if (edEl) edEl.classList.add('rpd-invalid'); }

			if (missing.length > 0) {
				showRequiredModal(missing);
				if (!partnerVal) input.focus();
				return;
			}

			currentFilters = { partner: partnerVal, startDate, endDate };
			await fetchTransactions(1);
		}

		form.addEventListener('submit', async function(e) {
			e.preventDefault();
			await runReport();
		});

		// View transactions click handler
		viewBtn.addEventListener('click', async function(e) {
			e.preventDefault();
			await runReport();
		});

		function isWorldcomPartner(name) {
			return /worldcom|\bwic\b/i.test(String(name || ''));
		}

		function isMetrobankHeadOfficePartner(name) {
			return /metrobank\s+head\s+office|\bmbtc\b/i.test(String(name || ''));
		}

		function isMoneygramPartner(name) {
			return /^moneygram$/i.test(String(name || '').trim());
		}

		function applyPartnerColumnConfig(partnerName) {
			const isWic = isWorldcomPartner(partnerName);
			const isMbtc = isMetrobankHeadOfficePartner(partnerName);
			const noHeader = document.getElementById('rpdThNo');
			const controlSeriesHeader = document.getElementById('rpdThControlSeries');
			const dateClaimedHeader = document.getElementById('rpdThDateClaimed');
			const ccrefHeader = document.getElementById('rpdThCcrefNo');
			const currencyHeader = document.getElementById('rpdThCurrency');
			const amountHeader = document.getElementById('rpdThAmount');
			const senderHeader = document.getElementById('rpdThSender');
			const beneficiaryHeader = document.getElementById('rpdThBeneficiary');
			const operatorHeader = document.getElementById('rpdThOperator');
			const branchHeader = document.getElementById('rpdThBranch');

			if (isMbtc) {
				noHeader.textContent = 'Date';
				controlSeriesHeader.textContent = 'Time';
				dateClaimedHeader.textContent = 'Reference No.';
				ccrefHeader.textContent = 'RTS Tracer No.';
				currencyHeader.textContent = 'Provider';
				amountHeader.textContent = 'Beneficiary Name';
				senderHeader.textContent = 'Remitter Name';
				beneficiaryHeader.textContent = 'Payout Amount PHP';
				operatorHeader.textContent = 'USD';
				branchHeader.textContent = 'Agent Commission in PHP';

				ccrefHeader.style.display = '';
				senderHeader.style.display = '';
				beneficiaryHeader.style.display = '';
				operatorHeader.style.display = '';
				branchHeader.style.display = '';
				currencyHeader.style.textAlign = 'left';
				amountHeader.style.textAlign = 'left';
				beneficiaryHeader.style.textAlign = 'right';
				operatorHeader.style.textAlign = 'right';
				branchHeader.style.textAlign = 'right';
				return;
			}

			noHeader.textContent = 'No.';
			controlSeriesHeader.textContent = isWic ? 'Transaction Id' : 'Control Series';
			dateClaimedHeader.textContent = isWic ? 'Date' : 'Date Claimed';
			// For WIC we hide the original CCREF NO column (it was renamed earlier),
			// and display Currency as "Coin" and the numeric Amount as the last column.
			ccrefHeader.textContent = 'CCREF NO';
			ccrefHeader.style.display = isWic ? 'none' : '';
			currencyHeader.textContent = isWic ? 'Coin' : 'Currency';
			amountHeader.textContent = isWic ? 'Amount' : 'Amount';
			senderHeader.textContent = 'Sender';
			beneficiaryHeader.textContent = 'Beneficiary';
			operatorHeader.textContent = 'Operator';
			branchHeader.textContent = 'Branch';

			// Align headers for WIC: Coin (currency code) left, Amount (numeric) right
			currencyHeader.style.textAlign = isWic ? 'left' : 'left';
			amountHeader.style.textAlign = isWic ? 'right' : 'right';
			beneficiaryHeader.style.textAlign = 'left';
			operatorHeader.style.textAlign = 'left';
			branchHeader.style.textAlign = 'left';

			const hiddenDisplay = isWic ? 'none' : '';
			['rpdThSender', 'rpdThBeneficiary', 'rpdThOperator', 'rpdThBranch'].forEach(function(id) {
				document.getElementById(id).style.display = hiddenDisplay;
			});
		}

		// Display results in table
		function displayResults(data) {
			resultsTitle.textContent = `${data.partner} — ${formatNumber(data.count)} transaction${data.count !== 1 ? 's' : ''}`;

			let dateRange = '';
			if (data.start_date && data.end_date) {
				dateRange = ` (${data.start_date} to ${data.end_date})`;
			} else if (data.start_date) {
				dateRange = ` (from ${data.start_date})`;
			} else if (data.end_date) {
				dateRange = ` (until ${data.end_date})`;
			}

			if (isMoneygramPartner(data.partner)) {
				const phpTotal = Number(data.moneygram_php_total || 0);
				const usdTotal = Number(data.moneygram_usd_total || 0);
				resultsSummary.innerHTML = `${dateRange}<span class="rpd-summary-badges"><span class="rpd-summary-badge rpd-summary-badge--php">PHP Total: ₱${formatCurrencyAllowZero(phpTotal)}</span><span class="rpd-summary-badge rpd-summary-badge--usd">USD Total: $${formatCurrencyAllowZero(usdTotal)}</span></span>`;
			} else {
				resultsSummary.textContent = dateRange;
			}

			const isMoneygram = isMoneygramPartner(data.partner);
			const isWic = isWorldcomPartner(data.partner);
			const isMbtc = isMetrobankHeadOfficePartner(data.partner);
			if(!isMoneygram){
				applyPartnerColumnConfig(data.partner);
			}
			const visibleColCount = isMoneygram ? 9 : (isWic ? 5 : 10);

			// Clear table
			resultsBody.innerHTML = '';

			if (!data.rows || data.rows.length === 0) {
				resultsBody.innerHTML = `<tr><td colspan="${visibleColCount}" style="padding:1rem;text-align:center;color:#9ca3af">No transactions found</td></tr>`;
				pagination.style.display = 'none';
				resultsDiv.style.display = 'block';
				return;
			}

			// Populate table
			if(isMoneygram){
				// Moneygram: 9 specific columns with proper formatting (includes Legacy ID)
				const mgCols = ['transaction_id','reference_id','tran_date','tran_type','base_tran_amt','total_tran_amt','settlement_currency','agent_name','legacy_id'];
				const mgHeaders = ['Transaction ID','Reference ID','Tran Date','Tran Type','Base Tran Amt','Total Tran Amt','Settlement Currency','Agent Name','Legacy ID'];
				const mgAmtCols = new Set(['base_tran_amt','total_tran_amt']);
				const thead = document.querySelector('#rpdResultsTable thead');
				if(thead){
					const tr = document.createElement('tr');
					mgHeaders.forEach((h, i) => {
						const th = document.createElement('th');
						th.style.padding = '0.5rem';
						th.style.color = '#6b7280';
						th.style.fontWeight = '700';
						th.style.whiteSpace = 'nowrap';
						th.style.background = '#f9fafb';
						// Right align amounts, center legacy id, left otherwise
						if (mgAmtCols.has(mgCols[i])) th.style.textAlign = 'right';
						else if (mgCols[i] === 'legacy_id') th.style.textAlign = 'center';
						else th.style.textAlign = 'left';
						th.textContent = h;
						tr.appendChild(th);
					});
					thead.innerHTML = '';
					thead.appendChild(tr);
				}
				const mgRows = Array.isArray(data.moneygram_rows) ? data.moneygram_rows : [];
				mgRows.forEach((row, idx) => {
					const tr = document.createElement('tr');
					tr.style.borderBottom = '1px solid #f0f0f0';
					if (idx % 2 === 1) tr.style.backgroundColor = '#fafafa';
					mgCols.forEach(col => {
						const td = document.createElement('td');
						td.style.padding = '0.75rem';
						td.style.color = '#1f2937';
						const raw = (row[col] === null || row[col] === undefined) ? '' : row[col];
						if(mgAmtCols.has(col)){
							td.style.textAlign = 'right';
							td.style.fontFamily = 'monospace';
							td.textContent = raw !== '' ? formatCurrency(raw) : '';
						} else if (col === 'legacy_id') {
							td.style.textAlign = 'center';
							td.textContent = raw !== '' ? String(raw) : '';
						} else {
							td.textContent = String(raw);
						}
						tr.appendChild(td);
					});
					resultsBody.appendChild(tr);
				});
			} else {
				data.rows.forEach((row, idx) => {
				const tr = document.createElement('tr');
				tr.style.borderBottom = '1px solid #f0f0f0';
				if (idx % 2 === 1) tr.style.backgroundColor = '#fafafa';

				const allCells = [
					row['no'] || '',
					row['control_series_no'] || '',
					formatDate(row['date_claimed']),
					row['ccref_no'] || '',
					row['currency'] || '',
					formatCurrency(row['amount'], row['currency']),
					row['sender_name'] || '',
					row['beneficiary_receiver'] || '',
					row['operator'] || '',
					row['branch'] || ''
				];

				const mbtcCells = [
					row['partner_date'] || row['date_claimed'] || '',
					row['partner_time'] || '',
					row['partner_reference_no'] || row['control_series_no'] || '',
					row['partner_rts_tracer_no'] || '',
					row['partner_provider'] || '',
					row['partner_beneficiary_name'] || row['beneficiary_receiver'] || '',
					row['partner_remitter_name'] || row['sender_name'] || '',
					formatCurrency(row['partner_php'], 'PHP'),
					formatCurrency(row['partner_usd'], 'USD'),
					formatCurrency(row['partner_in_php'], 'PHP')
				];

				// For WIC exclude the CCREF NO column (index 3). Final WIC columns: No., Transaction Id, Date, Coin, Amount
				const cells = isMbtc
					? mbtcCells
					: (isWic ? [allCells[0], allCells[1], allCells[2], allCells[4], allCells[5]] : allCells);
				const amountCellIdx = isWic ? 4 : 5;

				cells.forEach((cellValue, cellIdx) => {
					const td = document.createElement('td');
					td.style.padding = '0.75rem';
					td.style.color = '#1f2937';
					if (isMbtc && (cellIdx === 7 || cellIdx === 8 || cellIdx === 9)) {
						td.style.textAlign = 'right';
						td.style.fontFamily = 'monospace';
					}
					if (!isMbtc && cellIdx === amountCellIdx) {
						td.style.textAlign = 'right';
						td.style.fontFamily = 'monospace';
					}
					td.textContent = cellValue;
					tr.appendChild(td);
				});

					resultsBody.appendChild(tr);
				});
			}

			resultsDiv.style.display = 'block';

			const page = Number(data.page || 1);
			const perPage = Number(data.per_page || PAGE_SIZE);
			const totalPages = Number(data.total_pages || 1);
			const totalCount = Number(data.count || 0);
			const startRow = totalCount === 0 ? 0 : ((page - 1) * perPage) + 1;
			const endRow = Math.min(page * perPage, totalCount);
			paginationInfo.textContent = `Showing ${startRow.toLocaleString('en-US')} to ${endRow.toLocaleString('en-US')} of ${totalCount.toLocaleString('en-US')} transactions (Page ${page} of ${totalPages})`;
			prevBtn.disabled = page <= 1;
			nextBtn.disabled = page >= totalPages;
			pagination.style.display = totalPages > 1 ? 'flex' : 'none';

			// Store data for export
			resultsDiv.dataset.reportData = JSON.stringify(data);
		}

		prevBtn.addEventListener('click', function() {
			if (!lastReportData) return;
			const page = Number(lastReportData.page || 1);
			if (page <= 1) return;
			fetchTransactions(page - 1);
		});

		nextBtn.addEventListener('click', function() {
			if (!lastReportData) return;
			const page = Number(lastReportData.page || 1);
			const totalPages = Number(lastReportData.total_pages || 1);
			if (page >= totalPages) return;
			fetchTransactions(page + 1);
		});

		// Export to CSV
		exportBtn.addEventListener('click', function() {
			if (!resultsDiv.dataset.reportData) return;

			const data = JSON.parse(resultsDiv.dataset.reportData);
			const partner = data.partner;
				const rows = data.rows;
const csvIsMoneygram = isMoneygramPartner(partner);
			const csvIsWic = isWorldcomPartner(partner);
			const csvIsMbtc = isMetrobankHeadOfficePartner(partner);

			// Moneygram: request server-side Excel export including Legacy ID
			if(csvIsMoneygram){
				const params = new URLSearchParams({ partner: partner, start_date: data.start_date || '', end_date: data.end_date || '' });
				const url = `${getAppBasePath()}/src/controllers/excelcontrol/partner-data-export.php?${params.toString()}`;
				exportBtn.disabled = true;
				exportBtn.textContent = 'Preparing...';
				fetch(url).then(async (resp) => {
					if (!resp.ok) {
						const txt = await resp.text();
						try { const j = JSON.parse(txt); alert('Export error: ' + (j.error || txt)); }
						catch(e){ alert('Export error: ' + txt); }
						return;
					}
					const blob = await resp.blob();
					const disposition = resp.headers.get('Content-Disposition') || '';
					let filename = `moneygram-${new Date().toISOString().split('T')[0]}.xlsx`;
					const m = /filename\*=UTF-8''([^;]+)/i.exec(disposition) || /filename="?([^";]+)"?/i.exec(disposition);
					if (m && m[1]) filename = decodeURIComponent(m[1]);
					const link = document.createElement('a');
					const urlB = URL.createObjectURL(blob);
					link.href = urlB;
					link.download = filename;
					document.body.appendChild(link);
					link.click();
					document.body.removeChild(link);
					URL.revokeObjectURL(urlB);
				}).catch(err => {
					console.error('Export error', err);
					alert('Export failed: ' + err.message);
				}).finally(() => { exportBtn.disabled = false; exportBtn.textContent = 'Export Excel'; });

				return;
			}

			if (!rows || rows.length === 0) {
				alert('No data to export');
				return;
			}

				// CSV headers
				let headers;
				headers = csvIsMbtc
				? ['Date', 'Time', 'Reference No.', 'RTS Tracer No.', 'Provider', 'Beneficiary Name', 'Remitter Name', 'Payout Amount PHP', 'USD', 'In PHP']
				: (csvIsWic
				? ['No.', 'Transaction Id', 'Date', 'Coin', 'Amount']
					: ['No.', 'Control Series', 'Date Claimed', 'CCREF NO', 'Currency', 'Amount', 'Sender', 'Beneficiary', 'Operator', 'Branch']);

			// Build CSV content
			let csv = headers.map(h => `"${h}"`).join(',') + '\n';

				rows.forEach(row => {
				const allValues = [
					row['no'] || '',
					row['control_series_no'] || '',
					row['date_claimed'] || '',
					row['ccref_no'] || '',
					row['currency'] || '',
					row['amount'] || '',
					row['sender_name'] || '',
					row['beneficiary_receiver'] || '',
					row['operator'] || '',
					row['branch'] || ''
				];
				const mbtcValues = [
					row['partner_date'] || row['date_claimed'] || '',
					row['partner_time'] || '',
					row['partner_reference_no'] || row['control_series_no'] || '',
					row['partner_rts_tracer_no'] || '',
					row['partner_provider'] || '',
					row['partner_beneficiary_name'] || row['beneficiary_receiver'] || '',
					row['partner_remitter_name'] || row['sender_name'] || '',
					row['partner_php'] || '',
					row['partner_usd'] || '',
					row['partner_in_php'] || ''
				];
				const values = csvIsMbtc
					? mbtcValues
					: (csvIsWic ? [allValues[0], allValues[1], allValues[2], allValues[4], allValues[5]] : allValues);
				const line = values.map(v => `"${(v || '').toString().replace(/"/g, '""')}"`).join(',');

				csv += line + '\n';
				});

			// Download CSV
			const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
			const link = document.createElement('a');
			const url = URL.createObjectURL(blob);
			link.setAttribute('href', url);
			link.setAttribute('download', `ml-web-data-${partner.replace(/\s+/g, '-')}-${new Date().toISOString().split('T')[0]}.csv`);
			link.style.visibility = 'hidden';
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
		});

		// Auto-load default partner data if already selected (METROBANK)
		if (input.value && input.value.trim() !== '') {
			// Optionally auto-load on page load for convenience
		}

		// Compute and set max height for the partner records table wrapper so only records scroll.
		function setPartnerRecordsHeight(){
			try{
				const wrap = document.querySelector('#rpdResults .rwd-results-table-wrap');
				if(!wrap) return;
				const rect = wrap.getBoundingClientRect();
				const topOffset = rect.top;
				let reserved = 24;
				const pag = document.getElementById('rpdPagination');
				if(pag){ reserved += (pag.getBoundingClientRect().height || 0) + 8; }
				const avail = Math.max(160, window.innerHeight - topOffset - reserved);
				wrap.style.height = avail + 'px';
				wrap.style.overflow = 'auto';
			} catch(e){ console.warn('setPartnerRecordsHeight error', e); }
		}

		window.addEventListener('resize', setPartnerRecordsHeight);
		window.addEventListener('scroll', setPartnerRecordsHeight);
		const origDisplayResultsRpd = displayResults;
		displayResults = function(data){ origDisplayResultsRpd(data); setTimeout(setPartnerRecordsHeight,50); };
		setTimeout(setPartnerRecordsHeight,200);
	})();
	</script>
</div>
