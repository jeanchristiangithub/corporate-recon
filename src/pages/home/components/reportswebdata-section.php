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

		.reports-webdata-content .autocomplete-item.is-empty {
			color: #6b7280;
			cursor: default;
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

		.reports-webdata-content .txn-view-btn {
			border: 1px solid #dc3545;
			background: #fff;
			color: #dc3545;
			border-radius: 999px;
			padding: 2px 10px;
			font-size: 0.72rem;
			font-weight: 700;
			line-height: 1.35;
			cursor: pointer;
			white-space: nowrap;
		}

		.reports-webdata-content .txn-view-btn:hover {
			background: #dc3545;
			color: #fff;
		}

		.txn-detail-modal {
			position: fixed;
			inset: 0;
			z-index: 12000;
			display: none;
			align-items: center;
			justify-content: center;
		}

		.txn-detail-modal__overlay {
			position: absolute;
			inset: 0;
			background: rgba(15, 23, 42, 0.48);
		}

		.txn-detail-modal__dialog {
			position: relative;
			width: min(1120px, 96vw);
			max-height: 88vh;
			display: flex;
			flex-direction: column;
			background: #fff;
			border-radius: 8px;
			box-shadow: 0 18px 44px rgba(15, 23, 42, 0.24);
			overflow: hidden;
		}

		.txn-detail-modal__head {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 16px 20px;
			background: #df3044;
			border-bottom: 1px solid #c92539;
		}

		.txn-detail-modal__head h4 {
			margin: 0;
			color: #fff;
			font-size: 1.15rem;
			font-weight: 700;
		}

		.txn-detail-modal__body {
			padding: 16px 18px;
			overflow: auto;
		}

		.txn-detail-columns { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:24px; }
		.txn-detail-section { min-width:0; }
		.txn-detail-section__title { margin:0 0 10px; padding-bottom:8px; border-bottom:1px solid #d8dee7; color:#252a34; font-size:1rem; font-weight:700; }
		.txn-detail-section__title .material-icons { color:#df3044; margin-right:6px; font-size:18px; vertical-align:middle; }
		.txn-detail-list { margin:0; }
		.txn-detail-row { display:grid; grid-template-columns:minmax(145px,38%) minmax(0,1fr); gap:10px; padding:2px 0; font-size:.9rem; line-height:1.35; }
		.txn-detail-row dt { margin:0; color:#252a34; font-weight:700; }
		.txn-detail-row dd { margin:0; color:#656b73; overflow-wrap:anywhere; }
		.txn-detail-status { display:inline-block; padding:2px 8px; border-radius:5px; background:#f6b900; color:#1f2937; font-size:.75rem; font-weight:800; }
		.txn-detail-amounts { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:20px; margin-top:24px; padding-top:16px; border-top:1px solid #edf0f4; text-align:center; }
		.txn-detail-amount__label { display:block; color:#252a34; font-size:.9rem; font-weight:700; }
		.txn-detail-amount__value { display:block; margin-top:4px; color:#df3044; font-size:1.45rem; font-weight:800; overflow-wrap:anywhere; }
		/* Keep this modal independent from the Partner Data modal's shared class names. */
		#rwdTxnDetailModal .txn-detail-modal__dialog { width:min(1120px,96vw); }
		#rwdTxnDetailModal .txn-detail-list { display:block; margin:0; }
		#rwdTxnDetailModal .txn-detail-row { display:grid; grid-template-columns:minmax(145px,38%) minmax(0,1fr); width:100%; }
		#rwdTxnDetailModal .txn-detail-row dt,
		#rwdTxnDetailModal .txn-detail-row dd { min-width:0; }

		.txn-detail-close {
			border: 0;
			background: transparent;
			color: #fff;
			padding: 0 4px;
			font-size: 1.8rem;
			font-weight: 300;
			cursor: pointer;
			transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
		}

		.txn-detail-close:hover,
		.txn-detail-close:focus-visible {
			background: transparent;
			color: #ffe1e5;
			box-shadow: none;
			outline: none;
		}
		@media (max-width: 760px) {
			.txn-detail-columns { grid-template-columns:1fr; }
			.txn-detail-amounts { grid-template-columns:1fr; }
			#rwdTxnDetailModal .txn-detail-row { grid-template-columns:minmax(130px,42%) minmax(0,1fr); }
		}

		.reports-webdata-content .rwd-results-layout { display:grid; grid-template-columns:minmax(230px, 280px) minmax(0, 1fr); gap:12px; align-items:start; }
		.reports-webdata-content .rwd-filter-card { border:1px solid #e6eef6; border-radius:8px; background:#fff; padding:18px 16px; color:#001234; }
		.reports-webdata-content .rwd-filter-card__title { margin:0 0 18px; text-align:center; color:#e52f3f; font-size:0.78rem; font-weight:800; text-transform:uppercase; }
		.reports-webdata-content .rwd-filter-card__item { margin:0 0 18px; }
		.reports-webdata-content .rwd-filter-card__item:last-child { margin-bottom:0; }
		.reports-webdata-content .rwd-filter-card__label { display:block; margin-bottom:8px; color:#ef3340; font-size:0.78rem; font-weight:800; text-transform:uppercase; }
		.reports-webdata-content .rwd-filter-card__value { display:block; color:#001234; font-size:0.95rem; font-weight:800; overflow-wrap:anywhere; }
		.reports-webdata-content .rwd-filter-card__money { display:block; margin:0 0 10px; font-size:0.9rem; font-weight:800; }
		.reports-webdata-content .rwd-filter-card__money--php { color:#00704a; }
		.reports-webdata-content .rwd-filter-card__money--usd { color:#0b4aa2; }
		@media (max-width: 900px) {
			.reports-webdata-content .rwd-results-layout { grid-template-columns:1fr; }
		}
	</style>
	<div class="reports-inner" style="padding:.25rem">
	<style>
		/* Layout for sticky top filters and scrollable records area */
		.reports-webdata-content .rwd-results-table-wrap {
			overflow-x: auto;
			/* vertical scrolling area will be set dynamically by JS */
		}
		.reports-webdata-content .rwd-results-table {
			min-width: max-content;
		}
		.reports-webdata-content .rwd-results-table td {
			white-space: nowrap;
		}
		.reports-webdata-content .rwd-results-table-wrap table thead th {
			position: sticky;
			top: 0;
			z-index: 5;
			background: #f9fafb;
		}
	</style>
		<h3 style="margin:0 0 0.25rem;color:#1f2937;font-size:1.125rem;font-weight:600">KPX Web Data Transactions</h3>
		<!-- <p style="margin:0 0 1rem;color:#6b7280;font-size:0.9rem">View all transactions uploaded via the ML Web Data Uploader, filtered by corporate partner.</p> -->

		<form id="reportsWebdataForm" style="background:#fff;border:1px solid #e6eef6;border-radius:8px;padding:0.75rem;display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap">
			<label for="rwdPartner" style="flex:1;display:flex;flex-direction:column;gap:0.25rem;font-size:0.75rem;color:#6b7280;min-width:300px">CORPORATE PARTNER
				<div class="autocomplete-field">
					<input id="rwdPartner" name="partner" placeholder="Search corporate partner" autocomplete="off" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;background:#fff;width:100%;box-sizing:border-box;color:#111;font-size:.9rem;outline:none">
					<ul class="autocomplete-list" id="rwdPartnerSuggestions" role="listbox" hidden></ul>
				</div>
			</label>

			<div class="rwd-duration" style="display:flex;align-items:flex-end;gap:.5rem;flex-wrap:wrap">
				<div style="display:flex;flex-direction:column;gap:0.25rem">
					<label class="rwd-duration-label" style="font-size:.75rem;color:#6b7280;white-space:nowrap;">Start date <span style="color:#dc2626;margin-left:4px" aria-hidden="true">*</span></label>
					<input id="rwdStartDate" name="start_date" type="date" class="rwd-duration-input" aria-label="Start date" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;background:#fff;min-width:12ch;box-sizing:border-box;font-size:.95rem;">
				</div>
				<span class="rwd-duration-sep" style="color:#6b7280;font-weight:600;margin-bottom:8px">—</span>
				<div style="display:flex;flex-direction:column;gap:0.25rem">
					<label class="rwd-duration-label" style="font-size:.75rem;color:#6b7280;white-space:nowrap;">End date <span style="color:#dc2626;margin-left:4px" aria-hidden="true">*</span></label>
					<input id="rwdEndDate" name="end_date" type="date" class="rwd-duration-input" aria-label="End date" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;background:#fff;min-width:12ch;box-sizing:border-box;font-size:.95rem;">
				</div>
			</div>

			<div style="display:flex;align-items:flex-end;gap:0.5rem;margin-left:auto;flex-wrap:nowrap">
				<label for="rwdCurrencyFilter" style="display:flex;flex-direction:column;gap:0.25rem;font-size:.75rem;color:#6b7280;min-width:8ch">
					<span style="font-size:0.75rem;color:#6b7280">CURRENCY</span>
					<select id="rwdCurrencyFilter" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;background:#fff;color:#111;min-width:8ch;font-size:.9rem;outline:none">
						<option value="">ALL</option>
						<option value="PHP">PHP</option>
						<option value="USD">USD</option>
					</select>
				</label>
				<label style="display:flex;flex-direction:column;gap:0.25rem;font-size:.75rem;color:#6b7280;min-width:10ch">
					<span style="font-size:0.75rem;color:#6b7280">TRANSACTION TYPE</span>
					<select id="rwdType" name="type" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;background:#fff;min-width:10ch;font-size:.9rem;outline:none">
						<option value="">ALL</option>
						<option value="payout">PAYOUT</option>
						<option value="payout_cancelled">PAYOUT CANCELLED</option>
						<option value="sendout">SENDOUT</option>
						<option value="sendout_cancelled">SENDOUT CANCELLED</option>
					</select>
				</label>
				<button type="button" id="rwdViewBtn" class="material-btn material-btn--primary" style="padding:0.55rem 1rem;border-radius:6px">View transactions</button>
				<button type="button" id="rwdExportBtn" class="material-btn material-btn--secondary" style="display:none;padding:0.55rem 1rem;border-radius:6px;background:#198754;border-color:#198754;color:#fff;white-space:nowrap">Export Excel</button>
				<button type="button" id="rwdClearBtn" class="material-btn material-btn--secondary" style="padding:0.55rem 1rem;border-radius:6px">Clear filters</button>
			</div>

			<!-- Additional multi-column filters: MAINZONE, ZONE, REGION, AREA, BRANCH NAME, BRANCH ID -->
			<div class="rwd-multi-filters" id="rwdExtraFilters" style="flex:0 0 100%;display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center">
				<label style="flex:1;min-width:12ch;display:flex;flex-direction:column;gap:0.25rem;font-size:0.75rem;color:#6b7280">
					<span style="font-size:0.75rem;color:#6b7280">MAINZONE</span>
					<div class="autocomplete-field">
						<input id="rwdMainzone" name="mainzone" placeholder="Search mainzone..." autocomplete="off" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;background:#fff;width:100%;box-sizing:border-box;font-size:.9rem;outline:none">
						<ul class="autocomplete-list" id="rwdMainzoneSuggestions" role="listbox" hidden></ul>
					</div>
				</label>
				<label style="flex:1;min-width:12ch;display:flex;flex-direction:column;gap:0.25rem;font-size:0.75rem;color:#6b7280">
					<span style="font-size:0.75rem;color:#6b7280">ZONE</span>
					<div class="autocomplete-field">
						<input id="rwdZone" name="zone" placeholder="Search zone..." autocomplete="off" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;background:#fff;width:100%;box-sizing:border-box;font-size:.9rem;outline:none">
						<ul class="autocomplete-list" id="rwdZoneSuggestions" role="listbox" hidden></ul>
					</div>
				</label>
				<label style="flex:1;min-width:12ch;display:flex;flex-direction:column;gap:0.25rem;font-size:0.75rem;color:#6b7280">
					<span style="font-size:0.75rem;color:#6b7280">REGION</span>
					<div class="autocomplete-field">
						<input id="rwdRegion" name="region" placeholder="Search region..." autocomplete="off" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;background:#fff;width:100%;box-sizing:border-box;font-size:.9rem;outline:none">
						<ul class="autocomplete-list" id="rwdRegionSuggestions" role="listbox" hidden></ul>
					</div>
				</label>
				<label style="flex:1;min-width:12ch;display:flex;flex-direction:column;gap:0.25rem;font-size:0.75rem;color:#6b7280">
					<span style="font-size:0.75rem;color:#6b7280">AREA</span>
					<div class="autocomplete-field">
						<input id="rwdArea" name="area" placeholder="Search area..." autocomplete="off" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;background:#fff;width:100%;box-sizing:border-box;font-size:.9rem;outline:none">
						<ul class="autocomplete-list" id="rwdAreaSuggestions" role="listbox" hidden></ul>
					</div>
				</label>
				<label style="flex:1;min-width:12ch;display:flex;flex-direction:column;gap:0.25rem;font-size:0.75rem;color:#6b7280">
					<span style="font-size:0.75rem;color:#6b7280">BRANCH NAME</span>
					<div class="autocomplete-field">
						<input id="rwdBranchName" name="branch_name" placeholder="Search branch name..." autocomplete="off" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;background:#fff;width:100%;box-sizing:border-box;font-size:.9rem;outline:none">
						<ul class="autocomplete-list" id="rwdBranchNameSuggestions" role="listbox" hidden></ul>
					</div>
				</label>
				<label style="flex:1;min-width:12ch;display:flex;flex-direction:column;gap:0.25rem;font-size:0.75rem;color:#6b7280">
					<span style="font-size:0.75rem;color:#6b7280">BRANCH ID</span>
					<div class="autocomplete-field">
						<input id="rwdBranchId" name="branch_id" placeholder="Search branch id..." autocomplete="off" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;background:#fff;width:100%;box-sizing:border-box;font-size:.9rem;outline:none">
						<ul class="autocomplete-list" id="rwdBranchIdSuggestions" role="listbox" hidden></ul>
					</div>
				</label>
			</div>
		</form>

		<!-- Results container -->
		<div id="rwdResults" class="rwd-results" style="margin-top:1.5rem;display:none">
			<h4 id="rwdResultsTitle" style="display:none;margin:0"></h4>
			<p id="rwdResultsSummary" style="display:none;margin:0"></p>
			<div class="rwd-results-layout">
				<aside class="rwd-filter-card" aria-label="Filter results">
					<h5 class="rwd-filter-card__title">Filter Results</h5>
					<div class="rwd-filter-card__item">
						<span class="rwd-filter-card__label">Corporate Partner:</span>
						<span class="rwd-filter-card__value" id="rwdCardPartner">ALL</span>
					</div>
					<div class="rwd-filter-card__item">
						<span class="rwd-filter-card__label">Transaction Date:</span>
						<span class="rwd-filter-card__value" id="rwdCardDateRange">ALL</span>
					</div>
					<div class="rwd-filter-card__item">
						<span class="rwd-filter-card__label">Transaction Type:</span>
						<span class="rwd-filter-card__value" id="rwdCardType">ALL</span>
					</div>
					<div class="rwd-filter-card__item">
						<span class="rwd-filter-card__label">Currency:</span>
						<span class="rwd-filter-card__value" id="rwdCardCurrency">ALL</span>
					</div>
					<div class="rwd-filter-card__item">
						<span class="rwd-filter-card__label">Volume:</span>
						<span class="rwd-filter-card__value" id="rwdCardVolume">0</span>
					</div>
					<div class="rwd-filter-card__item">
						<span class="rwd-filter-card__label">Principal:</span>
						<span class="rwd-filter-card__money rwd-filter-card__money--php" id="rwdCardPrincipalPhp">PHP: 0.00</span>
						<span class="rwd-filter-card__money rwd-filter-card__money--usd" id="rwdCardPrincipalUsd">USD: 0.00</span>
					</div>
				</aside>
				<div class="rwd-results-table-wrap" style="overflow-x:auto;border:1px solid #e6eef6;border-radius:8px">
					<table id="rwdResultsTable" class="rwd-results-table" style="width:100%;border-collapse:collapse;font-size:0.6rem">
						<thead style="background:#f9fafb;border-bottom:1px solid #e6eef6">
							<tr>
								<th id="rwdDateHeader" style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">Date Claimed</th>
								<th style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">Branch</th>
								<th style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">Branch ID</th>
								<th style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">Control Series</th>
								<th style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">KPTN</th>
								<th style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">CCREF NO</th>
								<th style="padding:0.5rem;text-align:right;color:#6b7280;font-weight:600;white-space:nowrap">Amount</th>
								<th id="rwdThCharge" style="padding:0.5rem;text-align:right;color:#6b7280;font-weight:600;white-space:nowrap">Charge</th>
								<th style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">Currency</th>
								<th style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">Sender</th>
								<th style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">Beneficiary</th>
								<th id="rwdThOperator" style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">Operator</th>
								<th style="padding:0.5rem;text-align:center;color:#6b7280;font-weight:600;white-space:nowrap">Details</th>
							</tr>
						</thead>
						<tbody id="rwdResultsBody">
						</tbody>
					</table>
				</div>
			</div>
			<div id="rwdPagination" class="rwd-pagination" style="display:none">
				<div id="rwdPaginationInfo" class="rwd-pagination-info"></div>
				<div class="rwd-pagination-actions">
					<button type="button" id="rwdPrevBtn" class="material-btn material-btn--secondary" style="padding:0.55rem 1rem;border-radius:6px">Previous</button>
					<button type="button" id="rwdNextBtn" class="material-btn material-btn--secondary" style="padding:0.55rem 1rem;border-radius:6px">Next</button>
				</div>
			</div>
		</div>
	</div>

	<div id="rwdTxnDetailModal" class="txn-detail-modal" aria-hidden="true">
		<div class="txn-detail-modal__overlay" data-action="close-rwd-detail"></div>
		<div class="txn-detail-modal__dialog" role="dialog" aria-modal="true" aria-label="KPX Web Transaction Details">
			<div class="txn-detail-modal__head">
				<h4> KPX Web Transaction Details</h4>
				<button type="button" class="txn-detail-close" data-action="close-rwd-detail" aria-label="Close">&times;</button>
			</div>
			<div class="txn-detail-modal__body" data-role="rwdTxnDetailBody">Loading...</div>
		</div>
	</div>

	<script>
	(function(){
		const form = document.getElementById('reportsWebdataForm');
		const input = document.getElementById('rwdPartner');
		const viewBtn = document.getElementById('rwdViewBtn');
		const resultsDiv = document.getElementById('rwdResults');
		const resultsTitle = document.getElementById('rwdResultsTitle');
		const resultsSummary = document.getElementById('rwdResultsSummary');
		const resultsBody = document.getElementById('rwdResultsBody');
		const resultsWrap = document.querySelector('#rwdResults .rwd-results-table-wrap');
		const dateHeader = document.getElementById('rwdDateHeader');
		const operatorHeader = document.getElementById('rwdThOperator');
		const chargeHeader = document.getElementById('rwdThCharge');
		const exportBtn = document.getElementById('rwdExportBtn');
		const currencyFilter = document.getElementById('rwdCurrencyFilter');
		const pagination = document.getElementById('rwdPagination');
		const paginationInfo = document.getElementById('rwdPaginationInfo');
		const prevBtn = document.getElementById('rwdPrevBtn');
		const nextBtn = document.getElementById('rwdNextBtn');
		const filterCard = {
			partner: document.getElementById('rwdCardPartner'),
			dateRange: document.getElementById('rwdCardDateRange'),
			type: document.getElementById('rwdCardType'),
			currency: document.getElementById('rwdCardCurrency'),
			volume: document.getElementById('rwdCardVolume'),
			principalPhp: document.getElementById('rwdCardPrincipalPhp'),
			principalUsd: document.getElementById('rwdCardPrincipalUsd')
		};
		const PAGE_SIZE = 10000;
		const VIRTUAL_ROW_HEIGHT = 48;
		const VIRTUAL_BUFFER_ROWS = 12;
		let currentFilters = null;
		let lastReportData = null;
		let virtualWebRows = [];
		let virtualWebIsSendout = false;
		let autoFilterTimer = null;
		let isClearingFilters = false;
		const autoFilterIds = ['rwdType','rwdMainzone','rwdZone','rwdRegion','rwdArea','rwdBranchName','rwdBranchId'];
		const partners = <?= json_encode($partners) ?>;

		// Local partner autocomplete mirrors Partner Data Reports.
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
				inputEl.dispatchEvent(new Event('input', { bubbles: true }));
				closeSuggestions();
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

		// Reusable live autocomplete for all fields
		function attachRemoteAutocomplete(inputEl, fetchSuggestions, opts){
			if(!inputEl) return;
			const container = inputEl.closest('.autocomplete-field');
			const list = container ? container.querySelector('.autocomplete-list') : null;
			if(!list) return;

			const options = Object.assign({
				debounceMs: 180,
				prependAllOption: false,
				allOptionLabel: 'ALL'
			}, opts || {});

			let activeIndex = -1;
			let debounceTimer = null;
			let requestSeq = 0;

			function normalize(v){ return String(v || '').trim().toLowerCase(); }
			function dedupe(values){
				const out = [];
				const seen = new Set();
				(values || []).forEach(v => {
					const val = String(v || '').trim();
					if(!val) return;
					const key = normalize(val);
					if(seen.has(key)) return;
					seen.add(key);
					out.push(val);
				});
				return out;
			}

			function closeSuggestions(){ list.hidden = true; list.innerHTML = ''; activeIndex = -1; }
			function applyActive(items){ items.forEach((it,i)=> it.classList.toggle('is-active', i===activeIndex)); }
			function selectValue(v){
				inputEl.value = (v === null || typeof v === 'undefined') ? '' : String(v);
				inputEl.dispatchEvent(new Event('input',{bubbles:true}));
				closeSuggestions();
				inputEl.dispatchEvent(new Event('change',{bubbles:true}));
			}

			async function renderSuggestions(){
				const currentSeq = ++requestSeq;
				const query = String(inputEl.value || '').trim();
				let values = [];
				try{
					values = await fetchSuggestions(query);
					if(!Array.isArray(values)) values = [];
				} catch(e){ console.warn('Autocomplete fetch error', e); }
				if(currentSeq !== requestSeq) return;

				let toRender = dedupe(values);
				const hasTypedQuery = query !== '';
				if(options.prependAllOption && !(hasTypedQuery && toRender.length === 0)){
					toRender = toRender.filter(v => normalize(v) !== normalize(options.allOptionLabel));
					toRender.unshift(options.allOptionLabel);
				}

				list.innerHTML = '';
				if(toRender.length === 0){
					const empty = document.createElement('li');
					empty.className = 'autocomplete-item is-empty';
					empty.setAttribute('role', 'option');
					empty.setAttribute('aria-disabled', 'true');
					empty.textContent = 'No results found';
					list.appendChild(empty);
					list.hidden = false;
					activeIndex = -1;
					return;
				}

				toRender.forEach((it, idx)=>{
					const li = document.createElement('li');
					li.className = 'autocomplete-item';
					li.setAttribute('role', 'option');
					li.textContent = it;
					li.addEventListener('mousedown', function(e){ e.preventDefault(); selectValue(it); });
					li.addEventListener('mouseenter', function(){ activeIndex = idx; applyActive(Array.from(list.children)); });
					list.appendChild(li);
				});
				activeIndex = -1; list.hidden = false;
			}

			inputEl.addEventListener('input', function(){
				if(debounceTimer) clearTimeout(debounceTimer);
				debounceTimer = setTimeout(renderSuggestions, options.debounceMs);
			});

			inputEl.addEventListener('focus', async function(){
				if(debounceTimer) clearTimeout(debounceTimer);
				await renderSuggestions();
			});

			inputEl.addEventListener('click', function(){
				if(list.hidden){ renderSuggestions(); }
			});

			inputEl.addEventListener('keydown', function(e){
				const items = Array.from(list.querySelectorAll('.autocomplete-item:not(.is-empty)'));
				if(list.hidden || items.length === 0) return;
				if(e.key==='ArrowDown'){ e.preventDefault(); activeIndex = (activeIndex+1)%items.length; applyActive(items); }
				else if(e.key==='ArrowUp'){ e.preventDefault(); activeIndex = activeIndex<=0?items.length-1:activeIndex-1; applyActive(items); }
				else if(e.key==='Enter'){ if(activeIndex>=0 && activeIndex<items.length){ e.preventDefault(); selectValue(items[activeIndex].textContent||''); } }
				else if(e.key==='Escape'){ closeSuggestions(); }
			});

			document.addEventListener('click', function(ev){ if(!container.contains(ev.target)) closeSuggestions(); });
		}

		function getActiveDependencyFilters(){
			const values = {
				mainzone: String((document.getElementById('rwdMainzone') || {}).value || '').trim(),
				zone: String((document.getElementById('rwdZone') || {}).value || '').trim(),
				region: String((document.getElementById('rwdRegion') || {}).value || '').trim(),
				branch_name: String((document.getElementById('rwdBranchName') || {}).value || '').trim()
			};
			Object.keys(values).forEach(k => {
				if(values[k].toUpperCase() === 'ALL') values[k] = '';
			});
			return values;
		}

		async function fetchBranchProfileSuggestions(column, q){
			const params = new URLSearchParams({ column, q: String(q || '') });
			const deps = getActiveDependencyFilters();
			if(column !== 'mainzone' && deps.mainzone) params.set('mainzone', deps.mainzone);
			if(column !== 'zone' && deps.zone) params.set('zone', deps.zone);
			if(column !== 'region' && deps.region) params.set('region', deps.region);
			if(column !== 'branch_name' && deps.branch_name) params.set('branch_name', deps.branch_name);
			const res = await fetch(`${getAppBasePath()}/src/controllers/masterdata/branch-profile-values.php?${params.toString()}`);
			if(!res.ok) return [];
			const obj = await res.json();
			if(obj && obj.success && Array.isArray(obj.values)) return obj.values;
			return [];
		}

		attachPartnerAutocomplete(input, partners);
		attachRemoteAutocomplete(document.getElementById('rwdMainzone'), q => fetchBranchProfileSuggestions('mainzone', q), { prependAllOption: true });
		attachRemoteAutocomplete(document.getElementById('rwdZone'), q => fetchBranchProfileSuggestions('zone', q), { prependAllOption: true });
		attachRemoteAutocomplete(document.getElementById('rwdRegion'), q => fetchBranchProfileSuggestions('region', q), { prependAllOption: true });
		attachRemoteAutocomplete(document.getElementById('rwdArea'), q => fetchBranchProfileSuggestions('area', q), { prependAllOption: true });
		attachRemoteAutocomplete(document.getElementById('rwdBranchName'), q => fetchBranchProfileSuggestions('branch_name', q), { prependAllOption: true });
		attachRemoteAutocomplete(document.getElementById('rwdBranchId'), q => fetchBranchProfileSuggestions('branch_id', q), { prependAllOption: true });

		// --- Branch name autofill: area & branch_id ---
		(function(){
			const branchEl = document.getElementById('rwdBranchName');
			const areaEl = document.getElementById('rwdArea');
			const bidEl = document.getElementById('rwdBranchId');

			if(!branchEl) return;

			async function fetchBranchDetails(name){
				if(!name) return null;
				try{
					const params = new URLSearchParams({ branch_name: name });
					const res = await fetch(getAppBasePath() + '/src/controllers/masterdata/branch-profile-details.php?' + params.toString());
					if(!res.ok) return null;
					const obj = await res.json();
					if(obj && obj.success && obj.data) return obj.data;
				} catch(e){ console.warn('fetchBranchDetails error', e); }
				return null;
			}

			function applyAutofill(area, branch_id){
				if(areaEl){
					// only overwrite if empty or previously autofilled
					if(!areaEl.value || areaEl.dataset.autofilled === 'true'){
						areaEl.value = area || '';
						areaEl.dataset.autofilled = 'true';
					}
				}
				if(bidEl){
					if(!bidEl.value || bidEl.dataset.autofilled === 'true'){
						bidEl.value = branch_id || '';
						bidEl.dataset.autofilled = 'true';
					}
				}
			}

			function clearAutofilledTargets(){
				if(areaEl){ areaEl.value = ''; areaEl.dataset.autofilled = ''; }
				if(bidEl){ bidEl.value = ''; bidEl.dataset.autofilled = ''; }
			}

			// When branch name is selected via dropdown, autocomplete's selectValue will set the input.
			// Listen for change events to trigger autofill for exact matches.
			branchEl.addEventListener('change', async function(){
				const v = String(branchEl.value || '').trim();
				if(!v){ clearAutofilledTargets(); return; }
				if(v.toUpperCase() === 'ALL'){ clearAutofilledTargets(); return; }
				const data = await fetchBranchDetails(v);
				if(data){ applyAutofill(data.area, data.branch_id); }
			});

			// On Enter key: attempt exact-match fetch
			branchEl.addEventListener('keydown', async function(e){
				if(e.key === 'Enter'){
					e.preventDefault();
					const v = String(branchEl.value || '').trim();
					if(!v){ clearAutofilledTargets(); return; }
					if(v.toUpperCase() === 'ALL'){ clearAutofilledTargets(); return; }
					const data = await fetchBranchDetails(v);
					if(data){ applyAutofill(data.area, data.branch_id); }
				}
			});

			// On blur: if value present try to autofill
			branchEl.addEventListener('blur', async function(){
				const v = String(branchEl.value || '').trim();
				if(!v){ clearAutofilledTargets(); return; }
				if(v.toUpperCase() === 'ALL'){ clearAutofilledTargets(); return; }
				const data = await fetchBranchDetails(v);
				if(data){ applyAutofill(data.area, data.branch_id); }
			});

			// If user clears branch name manually, clear targets immediately
			branchEl.addEventListener('input', function(){ if(!String(branchEl.value || '').trim()) clearAutofilledTargets(); });

			// Manual override: if user types into area or branch id, mark as not autofilled
			if(areaEl){ areaEl.addEventListener('input', function(){ areaEl.dataset.autofilled = 'false'; }); }
			if(bidEl){ bidEl.addEventListener('input', function(){ bidEl.dataset.autofilled = 'false'; }); }

			// --- BRANCH NAME cascade to MAINZONE/ZONE/REGION ---
			async function fetchParentValues(column, branchName){
				try{
					const params = new URLSearchParams({ column, q: '' });
					if(branchName && String(branchName).trim().toUpperCase() !== 'ALL'){
						params.set('branch_name', branchName);
					} else {
						// if no branchName provided, include other parent filters if present
						const mz = (document.getElementById('rwdMainzone') || {}).value || '';
						const z = (document.getElementById('rwdZone') || {}).value || '';
						const r = (document.getElementById('rwdRegion') || {}).value || '';
						if(mz && String(mz).trim().toUpperCase() !== 'ALL') params.set('mainzone', mz);
						if(z && String(z).trim().toUpperCase() !== 'ALL') params.set('zone', z);
						if(r && String(r).trim().toUpperCase() !== 'ALL') params.set('region', r);
					}
					const res = await fetch(getAppBasePath() + '/src/controllers/masterdata/branch-profile-values.php?' + params.toString());
					if(!res.ok) return [];
					const obj = await res.json();
					if(obj && obj.success && Array.isArray(obj.values)) return obj.values;
				} catch(e){ console.warn('fetchParentValues error', e); }
				return [];
			}

			function renderHiddenParentList(inputEl, values){
				const container = inputEl ? inputEl.closest('.autocomplete-field') : null;
				const list = container ? container.querySelector('.autocomplete-list') : null;
				if(!list) return;
				list.innerHTML = '';
				const toRender = Array.isArray(values) ? values.slice() : [];
				if(toRender.indexOf('ALL') === -1) toRender.unshift('ALL');
				toRender.forEach(it => {
					const li = document.createElement('li');
					li.className = 'autocomplete-item';
					li.textContent = it;
					li.addEventListener('mousedown', function(e){ e.preventDefault(); inputEl.value = it; inputEl.dispatchEvent(new Event('input',{bubbles:true})); list.hidden = true; list.innerHTML = ''; inputEl.dispatchEvent(new Event('change',{bubbles:true})); });
					list.appendChild(li);
				});
				list.hidden = true;
			}

			async function applyBranchNameToParents(branchName){
				const [mzs, zs, rs] = await Promise.all([
					fetchParentValues('mainzone', branchName),
					fetchParentValues('zone', branchName),
					fetchParentValues('region', branchName)
				]);

				const mainEl = document.getElementById('rwdMainzone');
				const zoneElP = document.getElementById('rwdZone');
				const regElP = document.getElementById('rwdRegion');
				if(mainEl) renderHiddenParentList(mainEl, mzs);
				if(zoneElP) renderHiddenParentList(zoneElP, zs);
				if(regElP) renderHiddenParentList(regElP, rs);

				// Clear invalid selections
				if(mainEl){ const cur = String(mainEl.value || '').trim(); if(cur && cur.toUpperCase() !== 'ALL' && mzs.indexOf(cur) === -1){ mainEl.value = ''; mainEl.dispatchEvent(new Event('input',{bubbles:true})); mainEl.dispatchEvent(new Event('change',{bubbles:true})); } }
				if(zoneElP){ const cur = String(zoneElP.value || '').trim(); if(cur && cur.toUpperCase() !== 'ALL' && zs.indexOf(cur) === -1){ zoneElP.value = ''; zoneElP.dispatchEvent(new Event('input',{bubbles:true})); zoneElP.dispatchEvent(new Event('change',{bubbles:true})); } }
				if(regElP){ const cur = String(regElP.value || '').trim(); if(cur && cur.toUpperCase() !== 'ALL' && rs.indexOf(cur) === -1){ regElP.value = ''; regElP.dispatchEvent(new Event('input',{bubbles:true})); regElP.dispatchEvent(new Event('change',{bubbles:true})); } }
			}

			// Hook branch name changes to recalc parents
			branchEl.addEventListener('change', async function(){
				const v = String(branchEl.value || '').trim();
				if(!v || v.toUpperCase() === 'ALL'){
					await applyBranchNameToParents('');
					return;
				}
				await applyBranchNameToParents(v);
			});

			branchEl.addEventListener('keydown', async function(e){
				if(e.key === 'Enter'){
					e.preventDefault();
					const v = String(branchEl.value || '').trim();
					if(!v || v.toUpperCase() === 'ALL'){
						await applyBranchNameToParents('');
						return;
					}
					await applyBranchNameToParents(v);
				}
			});

			branchEl.addEventListener('blur', async function(){
				const v = String(branchEl.value || '').trim();
				if(!v || v.toUpperCase() === 'ALL'){
					await applyBranchNameToParents('');
					return;
				}
				await applyBranchNameToParents(v);
			});

			branchEl.addEventListener('input', function(){ if(!String(branchEl.value || '').trim()) { applyBranchNameToParents(''); } });
		})();

		// --- REGION autofill: zone & mainzone ---
		(function(){
			const regionEl = document.getElementById('rwdRegion');
			const zoneEl = document.getElementById('rwdZone');
			const mainzoneEl = document.getElementById('rwdMainzone');

			if(!regionEl) return;

			async function fetchRegionDetails(region){
				if(!region) return null;
				try{
					const params = new URLSearchParams({ region: region });
					const res = await fetch(getAppBasePath() + '/src/controllers/masterdata/region-profile-details.php?' + params.toString());
					if(!res.ok) return null;
					const obj = await res.json();
					if(obj && obj.success && obj.data) return obj.data;
				} catch(e){ console.warn('fetchRegionDetails error', e); }
				return null;
			}

			function applyRegionAutofill(zone, mainzone){
				if(zoneEl){
					if(!zoneEl.value || zoneEl.dataset.autofilled === 'true'){
						zoneEl.value = zone || '';
						zoneEl.dataset.autofilled = 'true';
					}
				}
				if(mainzoneEl){
					if(!mainzoneEl.value || mainzoneEl.dataset.autofilled === 'true'){
						mainzoneEl.value = mainzone || '';
						mainzoneEl.dataset.autofilled = 'true';
					}
				}
			}

			function clearRegionAutofill(){
				if(zoneEl){ zoneEl.value = ''; zoneEl.dataset.autofilled = ''; }
				if(mainzoneEl){ mainzoneEl.value = ''; mainzoneEl.dataset.autofilled = ''; }
			}

			regionEl.addEventListener('change', async function(){
				const v = String(regionEl.value || '').trim();
				if(!v){ clearRegionAutofill(); return; }
				if(v.toUpperCase() === 'ALL'){ clearRegionAutofill(); return; }
				const data = await fetchRegionDetails(v);
				if(data){ applyRegionAutofill(data.zone, data.mainzone); }
			});

			regionEl.addEventListener('keydown', async function(e){
				if(e.key === 'Enter'){
					e.preventDefault();
					const v = String(regionEl.value || '').trim();
					if(!v){ clearRegionAutofill(); return; }
					if(v.toUpperCase() === 'ALL'){ clearRegionAutofill(); return; }
					const data = await fetchRegionDetails(v);
					if(data){ applyRegionAutofill(data.zone, data.mainzone); }
				}
			});

			regionEl.addEventListener('blur', async function(){
				const v = String(regionEl.value || '').trim();
				if(!v){ clearRegionAutofill(); return; }
				if(v.toUpperCase() === 'ALL'){ clearRegionAutofill(); return; }
				const data = await fetchRegionDetails(v);
				if(data){ applyRegionAutofill(data.zone, data.mainzone); }
			});

			regionEl.addEventListener('input', function(){ if(!String(regionEl.value || '').trim()) clearRegionAutofill(); });

			if(zoneEl){ zoneEl.addEventListener('input', function(){ zoneEl.dataset.autofilled = 'false'; }); }
			if(mainzoneEl){ mainzoneEl.addEventListener('input', function(){ mainzoneEl.dataset.autofilled = 'false'; }); }
		})();

		// --- BRANCH ID autofill: branch_name, area, region, zone, mainzone ---
		(function(){
			const bidEl = document.getElementById('rwdBranchId');
			const branchNameEl = document.getElementById('rwdBranchName');
			const areaEl2 = document.getElementById('rwdArea');
			const regionEl2 = document.getElementById('rwdRegion');
			const zoneEl2 = document.getElementById('rwdZone');
			const mainzoneEl2 = document.getElementById('rwdMainzone');

			if(!bidEl) return;

			async function fetchByBranchId(id){
				if(!id) return null;
				try{
					const params = new URLSearchParams({ branch_id: id });
					const res = await fetch(getAppBasePath() + '/src/controllers/masterdata/branch-id-profile-details.php?' + params.toString());
					if(!res.ok) return null;
					const obj = await res.json();
					if(obj && obj.success && obj.data) return obj.data;
				} catch(e){ console.warn('fetchByBranchId error', e); }
				return null;
			}

			function applyBranchIdAutofill(data){
				if(!data) return;
				if(branchNameEl){ if(!branchNameEl.value || branchNameEl.dataset.autofilled === 'true'){ branchNameEl.value = data.branch_name || ''; branchNameEl.dataset.autofilled = 'true'; } }
				if(areaEl2){ if(!areaEl2.value || areaEl2.dataset.autofilled === 'true'){ areaEl2.value = data.area || ''; areaEl2.dataset.autofilled = 'true'; } }
				if(regionEl2){ if(!regionEl2.value || regionEl2.dataset.autofilled === 'true'){ regionEl2.value = data.region || ''; regionEl2.dataset.autofilled = 'true'; } }
				if(zoneEl2){ if(!zoneEl2.value || zoneEl2.dataset.autofilled === 'true'){ zoneEl2.value = data.zone || ''; zoneEl2.dataset.autofilled = 'true'; } }
				if(mainzoneEl2){ if(!mainzoneEl2.value || mainzoneEl2.dataset.autofilled === 'true'){ mainzoneEl2.value = data.mainzone || ''; mainzoneEl2.dataset.autofilled = 'true'; } }
			}

			function clearBranchIdAutofill(){
				if(branchNameEl){ branchNameEl.value = ''; branchNameEl.dataset.autofilled = ''; }
				if(areaEl2){ areaEl2.value = ''; areaEl2.dataset.autofilled = ''; }
				if(regionEl2){ regionEl2.value = ''; regionEl2.dataset.autofilled = ''; }
				if(zoneEl2){ zoneEl2.value = ''; zoneEl2.dataset.autofilled = ''; }
				if(mainzoneEl2){ mainzoneEl2.value = ''; mainzoneEl2.dataset.autofilled = ''; }
			}

			bidEl.addEventListener('change', async function(){
				const v = String(bidEl.value || '').trim();
				if(!v){ clearBranchIdAutofill(); return; }
				if(v.toUpperCase() === 'ALL'){ clearBranchIdAutofill(); return; }
				const data = await fetchByBranchId(v);
				if(data){ applyBranchIdAutofill(data); }
			});

			bidEl.addEventListener('keydown', async function(e){
				if(e.key === 'Enter'){
					e.preventDefault();
					const v = String(bidEl.value || '').trim();
					if(!v){ clearBranchIdAutofill(); return; }
					if(v.toUpperCase() === 'ALL'){ clearBranchIdAutofill(); return; }
					const data = await fetchByBranchId(v);
					if(data){ applyBranchIdAutofill(data); }
				}
			});

			bidEl.addEventListener('blur', async function(){
				const v = String(bidEl.value || '').trim();
				if(!v){ clearBranchIdAutofill(); return; }
				if(v.toUpperCase() === 'ALL'){ clearBranchIdAutofill(); return; }
				const data = await fetchByBranchId(v);
				if(data){ applyBranchIdAutofill(data); }
			});

			bidEl.addEventListener('input', function(){ if(!String(bidEl.value || '').trim()) clearBranchIdAutofill(); });

			// Manual override: typing into any of the target fields will mark them as not autofilled
			if(branchNameEl){ branchNameEl.addEventListener('input', function(){ branchNameEl.dataset.autofilled = 'false'; }); }
			if(areaEl2){ areaEl2.addEventListener('input', function(){ areaEl2.dataset.autofilled = 'false'; }); }
			if(regionEl2){ regionEl2.addEventListener('input', function(){ regionEl2.dataset.autofilled = 'false'; }); }
			if(zoneEl2){ zoneEl2.addEventListener('input', function(){ zoneEl2.dataset.autofilled = 'false'; }); }
			if(mainzoneEl2){ mainzoneEl2.addEventListener('input', function(){ mainzoneEl2.dataset.autofilled = 'false'; }); }
		})();

		// --- Parent cascade: MAINZONE / ZONE / REGION filter AREA, BRANCH NAME, BRANCH ID ---
		(function(){
			const mainzoneEl = document.getElementById('rwdMainzone');
			const zoneEl = document.getElementById('rwdZone');
			const regionEl = document.getElementById('rwdRegion');
			const childCols = ['area','branch_name','branch_id'];

			if(!mainzoneEl || !zoneEl || !regionEl) return;

			async function fetchFiltered(column){
				try{
					const params = new URLSearchParams({ column, q: '' });
					const mz = String(mainzoneEl.value || '').trim();
					const z = String(zoneEl.value || '').trim();
					const r = String(regionEl.value || '').trim();
					if(mz && mz.toUpperCase() !== 'ALL') params.set('mainzone', mz);
					if(z && z.toUpperCase() !== 'ALL') params.set('zone', z);
					if(r && r.toUpperCase() !== 'ALL') params.set('region', r);
					const res = await fetch(getAppBasePath() + '/src/controllers/masterdata/branch-profile-values.php?' + params.toString());
					if(!res.ok) return [];
					const obj = await res.json();
					if(obj && obj.success && Array.isArray(obj.values)) return obj.values;
				} catch(e){ console.warn('fetchFiltered error', e); }
				return [];
			}

			function renderHiddenListFor(inputEl, values){
				const container = inputEl ? inputEl.closest('.autocomplete-field') : null;
				const list = container ? container.querySelector('.autocomplete-list') : null;
				if(!list) return;
				list.innerHTML = '';
				const toRender = Array.isArray(values) ? values.slice() : [];
				if(toRender.indexOf('ALL') === -1) toRender.unshift('ALL');
				toRender.forEach(it => {
					const li = document.createElement('li');
					li.className = 'autocomplete-item';
					li.textContent = it;
					li.addEventListener('mousedown', function(e){ e.preventDefault(); inputEl.value = it; inputEl.dispatchEvent(new Event('input',{bubbles:true})); list.hidden = true; list.innerHTML = ''; inputEl.dispatchEvent(new Event('change',{bubbles:true})); });
					list.appendChild(li);
				});
				// populate but keep hidden — do not auto-open
				list.hidden = true;
			}

			async function applyCascadeToChildren(){
				// fetch allowed values for each child and populate their suggestion lists hidden
				const promises = childCols.map(col => fetchFiltered(col));
				const results = await Promise.all(promises);
				childCols.forEach((col, idx) => {
					const inputEl = document.getElementById('rwd' + col.charAt(0).toUpperCase() + col.slice(1));
					if(!inputEl) return;
					renderHiddenListFor(inputEl, results[idx]);
					// clear value if current selection not in allowed values (and not ALL/empty)
					const cur = String(inputEl.value || '').trim();
					if(cur && cur.toUpperCase() !== 'ALL' && results[idx].indexOf(cur) === -1){
						inputEl.value = '';
						inputEl.dispatchEvent(new Event('input',{bubbles:true}));
						inputEl.dispatchEvent(new Event('change',{bubbles:true}));
					}
				});
			}

			// wire parent listeners — on change/enter/blur/input recalc children
			[mainzoneEl, zoneEl, regionEl].forEach(parent => {
				parent.addEventListener('change', applyCascadeToChildren);
				parent.addEventListener('keydown', function(e){ if(e.key==='Enter'){ e.preventDefault(); applyCascadeToChildren(); } });
				parent.addEventListener('blur', applyCascadeToChildren);
				parent.addEventListener('input', function(){ if(!String(parent.value||'').trim()) applyCascadeToChildren(); });
			});

			// initial population (do not open lists)
			// applyCascadeToChildren(); // optional: leave until user interaction
		})();

		// Do not pre-populate branch filters; keep inputs blank so placeholder shows

		const clearBtn = document.getElementById('rwdClearBtn');

		function clearAllFilters(){
			try{
				isClearingFilters = true;
				if (autoFilterTimer) {
					clearTimeout(autoFilterTimer);
					autoFilterTimer = null;
				}
				// Only clear branch/location-related filters. Keep partner and date range intact.
				const mainEl = document.getElementById('rwdMainzone');
				const zoneEl = document.getElementById('rwdZone');
				const regionEl = document.getElementById('rwdRegion');
				const areaEl = document.getElementById('rwdArea');
				const branchNameEl = document.getElementById('rwdBranchName');
				const branchIdEl = document.getElementById('rwdBranchId');

				if(mainEl){ mainEl.value = ''; mainEl.dispatchEvent(new Event('change',{bubbles:true})); }
				if(zoneEl){ zoneEl.value = ''; zoneEl.dispatchEvent(new Event('change',{bubbles:true})); }
				if(regionEl){ regionEl.value = ''; regionEl.dispatchEvent(new Event('change',{bubbles:true})); }
				if(areaEl){ areaEl.value = ''; areaEl.dispatchEvent(new Event('change',{bubbles:true})); }
				if(branchNameEl){ branchNameEl.value = ''; branchNameEl.dispatchEvent(new Event('change',{bubbles:true})); }
				if(branchIdEl){ branchIdEl.value = ''; branchIdEl.dispatchEvent(new Event('change',{bubbles:true})); }

				// Hide the results table for the selected partner and clear stored report data.
				// Partner input and Start/End dates are intentionally retained.
				try{
					resultsBody.innerHTML = '';
					if(resultsDiv){ resultsDiv.style.display = 'none'; if(resultsDiv.dataset) resultsDiv.dataset.reportData = ''; }
					if(pagination) pagination.style.display = 'none';
					if(exportBtn) exportBtn.style.display = 'none';
					virtualWebRows = [];
					lastReportData = null;
					currentFilters = null;
				} catch(e) { console.warn('Error hiding results after clearing filters', e); }
			} catch (e) { console.warn('Error clearing filters', e); }
			finally { isClearingFilters = false; }
		}

		if(clearBtn){
			clearBtn.addEventListener('click', function(e){ e.preventDefault(); clearAllFilters(); });
		}

		// Format currency
		function formatCurrency(value, currency) {
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

		function formatFilterCardDate(value) {
			const raw = String(value || '').trim();
			const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
			if (!match) return raw;
			const monthIndex = Number(match[2]) - 1;
			const day = Number(match[3]);
			const year = Number(match[1]);
			const date = new Date(year, monthIndex, day);
			if (isNaN(date.getTime())) return raw;
			return date.toLocaleDateString('en-US', { month: 'long', day: '2-digit', year: 'numeric' });
		}

		function updateFilterResultsCard(data) {
			if (!data) return;
			const typeValue = String(data.type || '').trim();
			const currencyValue = String(data.currency_filter || '').trim();
			const startDate = String(data.start_date || '').trim();
			const endDate = String(data.end_date || '').trim();
			let dateRange = 'ALL';
			if (startDate && endDate) {
				dateRange = startDate === endDate
					? formatFilterCardDate(startDate)
					: `${formatFilterCardDate(startDate)} to ${formatFilterCardDate(endDate)}`;
			} else if (startDate) {
				dateRange = `from ${formatFilterCardDate(startDate)}`;
			} else if (endDate) {
				dateRange = `until ${formatFilterCardDate(endDate)}`;
			}

			if (filterCard.partner) filterCard.partner.textContent = data.partner || 'ALL';
			if (filterCard.dateRange) filterCard.dateRange.textContent = dateRange;
			if (filterCard.type) filterCard.type.textContent = typeValue ? typeValue.replace(/_/g, ' ').toUpperCase() : 'ALL';
			if (filterCard.currency) filterCard.currency.textContent = currencyValue || 'ALL';
			if (filterCard.volume) filterCard.volume.textContent = formatNumber(data.count || 0);
			if (filterCard.principalPhp) {
				filterCard.principalPhp.textContent = `PHP: ${formatCurrency(Math.abs(Number(data.php_total || 0)), 'PHP')}`;
				filterCard.principalPhp.style.display = currencyValue === 'USD' ? 'none' : '';
			}
			if (filterCard.principalUsd) {
				filterCard.principalUsd.textContent = `USD: ${formatCurrency(Math.abs(Number(data.usd_total || 0)), 'USD')}`;
				filterCard.principalUsd.style.display = currencyValue === 'PHP' ? 'none' : '';
			}
		}

		function getAppBasePath() {
			const parts = window.location.pathname.split('/').filter(Boolean);
			return parts.length > 0 ? `/${parts[0]}` : '';
		}

		function escapeHtml(value) {
			return String(value === null || value === undefined ? '' : value)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#039;');
		}

		function formatDetailDateValue(value) {
			const raw = String(value === null || value === undefined ? '' : value).trim();
			if (!raw) return '';
			const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
			if (!match) return raw;
			const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
			const month = monthNames[Number(match[2]) - 1];
			if (!month) return raw;
			const hour = match[4] || '00';
			const minute = match[5] || '00';
			const second = match[6] || '00';
			const period = Number(hour) >= 12 ? 'PM' : 'AM';
			return month + ' ' + match[3] + ', ' + match[1] + ' ' + hour + ':' + minute + ':' + second + ' ' + period;
		}

		function renderDetailGrid(data) {
			if (!data || !Object.keys(data).length) return '<div style="color:#6b7280;padding:12px 0">Transaction details not found.</div>';

			const hasValue = value => String(value === null || value === undefined ? '' : value).trim() !== '';
			const value = (key, isDate = false) => {
				const raw = data[key];
				if (!hasValue(raw)) return '-';
				return isDate ? formatDetailDateValue(raw) : String(raw);
			};
			const row = (label, displayValue, valueClass = '') => '<div class="txn-detail-row"><dt>' + escapeHtml(label) + ':</dt><dd>' + (valueClass ? '<span class="' + valueClass + '">' : '') + escapeHtml(displayValue) + (valueClass ? '</span>' : '') + '</dd></div>';
			const hasCancelled = hasValue(data.date_cancelled);
			const hasClaimed = hasValue(data.date_claimed);
			const hasSend = hasValue(data.date_send);
			const isPayout = hasClaimed;
			const isCancelled = hasCancelled && (hasClaimed || hasSend);
			const money = key => {
				const number = Number(data[key]);
				const formatted = Number.isFinite(number) ? number.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : value(key);
				return formatted;
			};

			let transactionRows = row('CAD Status', '-');
			if (hasCancelled && hasClaimed) {
				transactionRows += row('Date Cancelled', value('date_cancelled', true));
				transactionRows += row('Date Claimed', value('date_claimed', true));
			} else if (hasCancelled && hasSend) {
				transactionRows += row('Date Cancelled', value('date_cancelled', true));
				transactionRows += row('Date Send', value('date_send', true));
			} else if (hasClaimed) {
				transactionRows += row('Date Claimed', value('date_claimed', true));
			} else if (hasSend) {
				transactionRows += row('Date Send', value('date_send', true));
			}
			transactionRows += row('Control Series Number', value('control_series_no'));
			transactionRows += row('KPTN', value('kptn'));
			transactionRows += row('CCREF Number', value('ccref_no'));
			transactionRows += row('Currency', value('currency'));
			transactionRows += row('Sender Name', value('sender_name'));
			if (hasClaimed) {
				transactionRows += row('Sender Country', value('sender_country'));
				transactionRows += row('Beneficiary Name', value('beneficiary_receiver'));
			} else if (hasSend) {
				transactionRows += row('Receiver Country', value('receiver_country'));
			}
			transactionRows += row('Receiver Name', hasCancelled && hasClaimed ? value('receiver_name') : (isPayout ? value('receiver_kyc') : value('receiver_name')));
			transactionRows += row('Receiver Phone Number', value('receiver_phone'));

			let branchRows = row('Operator', value('operator'));
			branchRows += row('Branch Name', value('branch'));
			branchRows += row('Branch ID', value('branch_id'));
			branchRows += row('Remote Operator', value('remote_operator'));
			branchRows += row('Remote Branch', value('remote_branch'));
			if (isCancelled) branchRows += row('Other Details', value('other_details'));
			branchRows += row('Uploaded Date', value('created_at', true));
			branchRows += row('Uploaded By', hasValue(data.uploaded_by_name) ? value('uploaded_by_name') : value('uploaded_by'));

			let amountCards = '<div class="txn-detail-amount"><span class="txn-detail-amount__label">Principal Amount</span><span class="txn-detail-amount__value">' + escapeHtml(money('amount')) + '</span></div>';
			if (isPayout) {
				amountCards += '<div class="txn-detail-amount"><span class="txn-detail-amount__label">Charge to Customer</span><span class="txn-detail-amount__value">' + escapeHtml(money('ctc')) + '</span></div>';
				amountCards += '<div class="txn-detail-amount"><span class="txn-detail-amount__label">Charge to Partner</span><span class="txn-detail-amount__value">' + escapeHtml(money('ctp')) + '</span></div>';
			} else if (hasSend) {
				amountCards += '<div class="txn-detail-amount"><span class="txn-detail-amount__label">Charge</span><span class="txn-detail-amount__value">' + escapeHtml(money('charge')) + '</span></div>';
			}

			return '<div class="txn-detail-columns">' +
				'<section class="txn-detail-section"><h5 class="txn-detail-section__title"><span class="material-icons" aria-hidden="true">info</span>Transaction Information</h5><dl class="txn-detail-list">' + transactionRows + '</dl></section>' +
				'<section class="txn-detail-section"><h5 class="txn-detail-section__title"><span class="material-icons" aria-hidden="true">account_balance</span>Branch Information</h5><dl class="txn-detail-list">' + branchRows + '</dl></section>' +
				'</div><div class="txn-detail-amounts">' + amountCards + '</div>';
		}

		function closeWebDetailModal() {
			const modal = document.getElementById('rwdTxnDetailModal');
			if (!modal) return;
			modal.style.display = 'none';
			modal.setAttribute('aria-hidden', 'true');
		}

		async function openWebDetailModal(id) {
			const modal = document.getElementById('rwdTxnDetailModal');
			const body = modal ? modal.querySelector('[data-role="rwdTxnDetailBody"]') : null;
			if (!modal || !body || !id) return;
			body.innerHTML = '<div style="color:#6b7280;padding:12px 0">Loading...</div>';
			modal.style.display = 'flex';
			modal.setAttribute('aria-hidden', 'false');
			try {
				const res = await fetch(`${getAppBasePath()}/src/controllers/recon/moneygram-web-transaction-details.php?id=${encodeURIComponent(id)}`, { method: 'GET' });
				const json = await res.json();
				if (!res.ok || !(json && json.success && json.data)) {
					body.innerHTML = '<div style="color:#6b7280;padding:12px 0">Transaction details not found.</div>';
					return;
				}
				body.innerHTML = renderDetailGrid(json.data);
			} catch (err) {
				console.error('KPX Web detail error', err);
				body.innerHTML = '<div style="color:#6b7280;padding:12px 0">Transaction details not found.</div>';
			}
		}

		document.querySelectorAll('[data-action="close-rwd-detail"]').forEach(btn => {
			btn.addEventListener('click', closeWebDetailModal);
		});

			// --- Date validation: require both Start and End date before enabling filters/View ---
			const startDateEl = document.getElementById('rwdStartDate');
			const endDateEl = document.getElementById('rwdEndDate');
			const filterIds = ['rwdMainzone','rwdZone','rwdRegion','rwdArea','rwdBranchName','rwdBranchId'];

			function showDurationAlert(message){
				if(window.Swal){
					return Swal.fire({
						text: String(message || ''),
						icon: 'warning',
						confirmButtonText: 'OK',
						confirmButtonColor: '#dc3545',
						heightAuto: false
					});
				}
				alert(message);
				return Promise.resolve();
			}

			function syncEndDateToStartDate(){
				if(startDateEl && endDateEl && startDateEl.value){
					endDateEl.value = startDateEl.value;
				}
				updateDateValidationState();
			}

			function setFiltersEnabled(enabled){
				filterIds.forEach(id => {
					const el = document.getElementById(id);
					if(!el) return;
					el.disabled = !enabled;
					if(!enabled){ el.classList.add('rwd-disabled'); } else { el.classList.remove('rwd-disabled'); }
				});
				viewBtn.disabled = !enabled;
			}

			function showDateRequiredMessage(e){
				e && e.preventDefault && e.preventDefault();
				showDurationAlert('Please select Start Date and End Date first.');
			}

			function updateDateValidationState(){
				const s = String(startDateEl.value || '').trim();
				const t = String(endDateEl.value || '').trim();
				const ok = (s !== '' && t !== '');
				setFiltersEnabled(ok);
			}

			// On load: disable filters and view button
			setFiltersEnabled(false);

			// Wire date input events
			if(startDateEl){ startDateEl.addEventListener('change', syncEndDateToStartDate); startDateEl.addEventListener('input', syncEndDateToStartDate); }
			if(endDateEl){ endDateEl.addEventListener('change', updateDateValidationState); endDateEl.addEventListener('input', updateDateValidationState); }

			// Intercept clicks on autocomplete containers to show message when disabled
			filterIds.forEach(id => {
				const el = document.getElementById(id);
				if(!el) return;
				const container = el.closest('.autocomplete-field');
				if(container){
					container.addEventListener('click', function(ev){ if(el.disabled){ ev.preventDefault(); showDateRequiredMessage(ev); } }, true);
				}
				// also intercept label clicks by listening on parent form
				const label = el.closest('label');
				if(label){ label.addEventListener('click', function(ev){ if(el.disabled){ ev.preventDefault(); showDateRequiredMessage(ev); } }, true); }
			});

			// Ensure clicks on the View button are validated against the actual date values (avoid stale disabled checks)
			const viewBtnContainer = viewBtn.parentElement;
			if(viewBtnContainer){
				viewBtnContainer.addEventListener('click', function(ev){
					// If the event was already handled by the button's own listener, do nothing
					if(ev.defaultPrevented) return;
					const clickedView = ev.target.closest && ev.target.closest('#rwdViewBtn');
					if(!clickedView) return; // ignore clicks on other buttons
					const s = String(startDateEl.value || '').trim();
					const t = String(endDateEl.value || '').trim();
					if(!s || !t){ ev.preventDefault(); showDateRequiredMessage(ev); }
				});
			}

		// Format date
		function formatDate(dateStr) {
			if (!dateStr) return '';
			const date = new Date(dateStr);
			if (isNaN(date.getTime())) return dateStr;
			return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
		}

		function createWebSpacerRow(height, colSpan) {
			const tr = document.createElement('tr');
			const td = document.createElement('td');
			td.colSpan = colSpan;
			td.style.height = Math.max(0, height) + 'px';
			td.style.padding = '0';
			td.style.border = '0';
			tr.appendChild(td);
			return tr;
		}

		function createWebResultRow(row, visibleIndex, isSendout) {
			const tr = document.createElement('tr');
			tr.style.borderBottom = '1px solid #f0f0f0';
			if (visibleIndex % 2 === 1) tr.style.backgroundColor = '#fafafa';

			const cells = [
				formatDate(row['report_date']),
				row['branch'] || '',
				row['branch_id'] || '',
				row['control_series_no'] || '',
				row['kptn'] || '',
				row['ccref_no'] || '',
				formatCurrency(row['amount'], row['currency']),
				isSendout ? formatCurrency(row['charge'] || 0, row['currency']) : '',
				row['currency'] || '',
				row['sender_name'] || '',
				row['beneficiary_receiver'] || '',
				row['operator'] || '',
				''
			];

			cells.forEach((cellValue, cellIdx) => {
				const td = document.createElement('td');
				td.style.padding = '0.75rem';
				td.style.color = '#1f2937';
				td.style.whiteSpace = 'nowrap';
				if (cellIdx === 6 || (isSendout && cellIdx === 7)) {
					td.style.textAlign = 'right';
					td.style.fontFamily = 'monospace';
				}
				if (!isSendout && cellIdx === 7) {
					td.style.display = 'none';
				}
				if (cellIdx === 12) {
					td.style.textAlign = 'center';
					const id = row['id'] || '';
					if (id) {
						const btn = document.createElement('button');
						btn.type = 'button';
						btn.className = 'txn-view-btn';
						btn.textContent = 'View';
						btn.dataset.txnId = id;
						td.appendChild(btn);
					}
				} else {
					td.textContent = cellValue;
				}
				tr.appendChild(td);
			});

			return tr;
		}

		function renderVirtualWebRows() {
			if (!resultsWrap) return;
			const rows = virtualWebRows || [];
			const colSpan = virtualWebIsSendout ? 13 : 12;
			const scrollTop = resultsWrap.scrollTop || 0;
			if (!rows.length) return;

			const viewportHeight = resultsWrap.clientHeight || 480;
			const startIndex = Math.max(0, Math.floor(scrollTop / VIRTUAL_ROW_HEIGHT) - VIRTUAL_BUFFER_ROWS);
			const visibleCount = Math.ceil(viewportHeight / VIRTUAL_ROW_HEIGHT) + (VIRTUAL_BUFFER_ROWS * 2);
			const endIndex = Math.min(rows.length, startIndex + visibleCount);
			const fragment = document.createDocumentFragment();
			const topHeight = startIndex * VIRTUAL_ROW_HEIGHT;
			const bottomHeight = Math.max(0, (rows.length - endIndex) * VIRTUAL_ROW_HEIGHT);

			if (topHeight > 0) fragment.appendChild(createWebSpacerRow(topHeight, colSpan));
			for (let i = startIndex; i < endIndex; i++) {
				fragment.appendChild(createWebResultRow(rows[i], i, virtualWebIsSendout));
			}
			if (bottomHeight > 0) fragment.appendChild(createWebSpacerRow(bottomHeight, colSpan));
			resultsBody.replaceChildren(fragment);
			if (resultsWrap.scrollTop !== scrollTop) resultsWrap.scrollTop = scrollTop;
		}

		if (resultsWrap) {
			resultsWrap.addEventListener('scroll', function() {
				window.requestAnimationFrame(renderVirtualWebRows);
			});
		}

		resultsBody.addEventListener('click', function(event) {
			const btn = event.target.closest ? event.target.closest('.txn-view-btn[data-txn-id]') : null;
			if (!btn) return;
			openWebDetailModal(btn.dataset.txnId);
		});

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
					mainzone: currentFilters.mainzone || '',
					zone: currentFilters.zone || '',
					region: currentFilters.region || '',
					area: currentFilters.area || '',
					branch_name: currentFilters.branch_name || '',
					branch_id: currentFilters.branch_id || '',
					type: currentFilters.type || '',
					currency: currentFilters.currency || '',
					page: String(page),
					per_page: String(PAGE_SIZE)
				});

				const response = await fetch(`${getAppBasePath()}/src/controllers/excelcontrol/ml-web-data-report.php?${params.toString()}`);
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
			// Validate corporate partner and dates before running
			if (!input.value || input.value.trim() === '') {
				await showDurationAlert('Please select a corporate partner to view transactions.');
				input.focus();
				return;
			}

			const sd = String(document.getElementById('rwdStartDate').value || '').trim();
			const ed = String(document.getElementById('rwdEndDate').value || '').trim();
			if (!sd || !ed) {
				await showDurationAlert('Please select Start Date and End Date first.');
				return;
			}
			if (sd > ed) {
				await showDurationAlert('Start Date cannot be greater than End Date.');
				return;
			}

			const partner = input.value;
			const startDate = document.getElementById('rwdStartDate').value || '';
			const endDate = document.getElementById('rwdEndDate').value || '';
			const mainzone = document.getElementById('rwdMainzone').value || '';
			const zone = document.getElementById('rwdZone').value || '';
			const region = document.getElementById('rwdRegion').value || '';
			const area = document.getElementById('rwdArea').value || '';
			const branch_name = document.getElementById('rwdBranchName').value || '';
			const branch_id = document.getElementById('rwdBranchId').value || '';
            const type = document.getElementById('rwdType') ? (document.getElementById('rwdType').value || '') : '';
            const currency = currencyFilter ? (currencyFilter.value || '') : '';

			currentFilters = { partner, startDate, endDate, mainzone, zone, region, area, branch_name, branch_id, type, currency };
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

		function scheduleAutoFilterReport(delay) {
			if (isClearingFilters || !currentFilters) return;
			const partnerValue = String(input.value || '').trim();
			const startDateValue = String((document.getElementById('rwdStartDate') || {}).value || '').trim();
			const endDateValue = String((document.getElementById('rwdEndDate') || {}).value || '').trim();
			if (!partnerValue || !startDateValue || !endDateValue) return;
			if (autoFilterTimer) clearTimeout(autoFilterTimer);
			autoFilterTimer = setTimeout(async function() {
				autoFilterTimer = null;
				if (isClearingFilters || !currentFilters) return;
				await runReport();
			}, delay);
		}

		autoFilterIds.forEach(function(id) {
			const el = document.getElementById(id);
			if (!el) return;
			el.addEventListener('input', function() {
				if (el.disabled) return;
				scheduleAutoFilterReport(600);
			});
			el.addEventListener('change', function() {
				if (el.disabled) return;
				scheduleAutoFilterReport(150);
			});
		});

		if (currencyFilter) {
			currencyFilter.addEventListener('change', function() {
				if (!currentFilters) return;
				currentFilters.currency = currencyFilter.value || '';
				fetchTransactions(1);
			});
		}

		// Display results in table
		function displayResults(data) {
			updateFilterResultsCard(data);
			const activeDateLabel = String(data.date_label || 'Date Claimed');
			if(dateHeader) dateHeader.textContent = activeDateLabel;
			// Toggle Operator header to Charge for SENDOUT reports
			const isSendout = ['sendout', 'sendout_cancelled'].includes(String((data.type || '')).toLowerCase());
			if(operatorHeader) operatorHeader.textContent = 'Operator';
			if(chargeHeader) chargeHeader.style.display = isSendout ? '' : 'none';

			resultsTitle.textContent = '';
			resultsSummary.textContent = '';
			resultsSummary.style.display = 'none';

			// Clear table
			resultsBody.innerHTML = '';
			virtualWebRows = [];

			if (!data.rows || data.rows.length === 0) {
				if (exportBtn) exportBtn.style.display = 'none';
				resultsBody.innerHTML = `<tr><td colspan="${isSendout ? 13 : 12}" style="padding:1rem;text-align:center;color:#9ca3af">No transactions found</td></tr>`;
				pagination.style.display = 'none';
				resultsDiv.style.display = 'block';
				return;
			}

			if (exportBtn) exportBtn.style.display = '';
			virtualWebRows = data.rows || [];
			virtualWebIsSendout = isSendout;
			if (resultsWrap) resultsWrap.scrollTop = 0;
			renderVirtualWebRows();

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

		// Export to Excel (calls server-side generator)
		exportBtn.addEventListener('click', async function() {
			try {
				// Build filters from current inputs
				const partner = (document.getElementById('rwdPartner') || {}).value || '';
				const startDate = (document.getElementById('rwdStartDate') || {}).value || '';
				const endDate = (document.getElementById('rwdEndDate') || {}).value || '';
				const type = (document.getElementById('rwdType') || {}).value || '';
				const mainzone = (document.getElementById('rwdMainzone') || {}).value || '';
				const zone = (document.getElementById('rwdZone') || {}).value || '';
				const region = (document.getElementById('rwdRegion') || {}).value || '';
				const area = (document.getElementById('rwdArea') || {}).value || '';
				const branch_name = (document.getElementById('rwdBranchName') || {}).value || '';
				const branch_id = (document.getElementById('rwdBranchId') || {}).value || '';
				const currency = currencyFilter ? (currencyFilter.value || '') : '';

				if (!startDate || !endDate) {
					await showDurationAlert('Please select Start Date and End Date first.');
					return;
				}
				if (startDate > endDate) {
					await showDurationAlert('Start Date cannot be greater than End Date.');
					return;
				}

				const params = new URLSearchParams({
					partner: partner,
					start_date: startDate,
					end_date: endDate,
					type: type || '',
					mainzone: mainzone || '',
					zone: zone || '',
					region: region || '',
					area: area || '',
					branch_name: branch_name || '',
					branch_id: branch_id || '',
					currency: currency || ''
				});

				// Build filename on client side (sanitization on server too)
				function sanitizeFilename(s) { return (s || '').replace(/[\\/:*?"<>|]+/g, '_'); }
				const partnerPart = (partner || '').replace(/\s+/g, '_');
				const typePart = (type || '') === '' ? 'ALL' : type.toUpperCase();
				const currencyPart = currency || 'ALL';
				const branchPart = branch_name ? sanitizeFilename(branch_name) : '';
				const filenameParts = [partnerPart, `${startDate}_to_${endDate}`, typePart, currencyPart];
				if (branchPart) filenameParts.push(branchPart);
				const filename = filenameParts.join('_') + '.xlsx';

				exportBtn.disabled = true;
				exportBtn.textContent = 'Preparing...';

				const res = await fetch(getAppBasePath() + '/src/controllers/excelcontrol/ml-web-data-export.php?' + params.toString(), { method: 'GET' });

				const contentType = res.headers.get('Content-Type') || '';
				if (!res.ok) {
					// Try to parse JSON error
					let msg = 'Export failed';
					try { const j = await res.json(); if (j && j.error) msg = j.error; } catch (e) {}
					alert(msg);
					return;
				}

				if (contentType.indexOf('application/json') !== -1) {
					const j = await res.json();
					alert(j && j.error ? j.error : 'No transactions available to export.');
					return;
				}

				const blob = await res.blob();
				const link = document.createElement('a');
				const url = URL.createObjectURL(blob);
				link.href = url;
				link.download = filename;
				document.body.appendChild(link);
				link.click();
				document.body.removeChild(link);
				URL.revokeObjectURL(url);

			} catch (err) {
				console.error('Export error', err);
				alert('Failed to export transactions: ' + err.message);
			} finally {
				exportBtn.disabled = false;
				exportBtn.textContent = 'Export Excel';
			}
		});

		// Auto-load default partner data if already selected (METROBANK)
		if (input.value && input.value.trim() !== '') {
			// Optionally auto-load on page load for convenience
		}

		// Compute and set max height for the records table wrapper so only records scroll.
		function setRecordsScrollHeight(){
			try{
				const wrap = document.querySelector('#rwdResults .rwd-results-table-wrap');
				if(!wrap) return;
				const rect = wrap.getBoundingClientRect();
				const topOffset = rect.top; // distance from viewport top to wrapper top
				// Compute reserved space from pagination/footer and a small margin
				let reserved = 24; // base margin
				const pag = document.getElementById('rwdPagination');
				if(pag){ const ph = pag.getBoundingClientRect().height || 0; reserved += ph + 8; }
				// Available viewport height for the wrapper
				let avail = Math.max(160, window.innerHeight - topOffset - reserved);
				// Apply height so the wrapper expands to fill remaining space
				wrap.style.height = avail + 'px';
				wrap.style.maxHeight = 'none';
				wrap.style.overflowY = 'auto';
			} catch(e){ console.warn('setRecordsScrollHeight error', e); }
		}

		window.addEventListener('resize', setRecordsScrollHeight);
		// Recompute whenever results are displayed
		const origDisplayResults = displayResults;
		displayResults = function(data){ origDisplayResults(data); setTimeout(function(){ setRecordsScrollHeight(); renderVirtualWebRows(); },50); };

		// Initial compute in case layout is pre-populated
		setTimeout(setRecordsScrollHeight,200);
	})();
	</script>
</div>
