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

		.rpd-results .rwd-results-table.rpd-table--wic {
			width: 760px !important;
			table-layout: fixed;
			border-collapse: collapse;
		}

		.rpd-results .rwd-results-table.rpd-table--wic th,
		.rpd-results .rwd-results-table.rpd-table--wic td {
			padding: 8px 10px !important;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}

		.rpd-results .rwd-results-table.rpd-table--wic #rpdThControlSeries,
		.rpd-results .rwd-results-table.rpd-table--wic tbody td:nth-child(1) {
			width: 220px;
			text-align: left !important;
		}

		.rpd-results .rwd-results-table.rpd-table--wic #rpdThDateClaimed,
		.rpd-results .rwd-results-table.rpd-table--wic tbody td:nth-child(2) {
			width: 220px;
			text-align: left !important;
		}

		.rpd-results .rwd-results-table.rpd-table--wic #rpdThCurrency,
		.rpd-results .rwd-results-table.rpd-table--wic tbody td:nth-child(3) {
			width: 180px;
			text-align: left !important;
		}

		.rpd-results .rwd-results-table.rpd-table--wic #rpdThAmount,
		.rpd-results .rwd-results-table.rpd-table--wic tbody td:nth-child(4) {
			width: 140px;
			text-align: center !important;
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
		.rpd-summary-label { color:#1f2937; font-weight:800; font-size:0.95rem; }
		.rpd-summary-group { display:inline-flex; gap:8px; align-items:center; flex-wrap:wrap; }
		.rpd-summary-badge { display:inline-flex; align-items:center; padding:6px 12px; border-radius:999px; font-weight:700; font-size:0.9rem; line-height:1; box-shadow:0 2px 6px rgba(2,6,23,0.06); }
		.rpd-summary-badge--php { background:#ecfdf5; color:#065f46; }
		.rpd-summary-badge--usd { background:#eff6ff; color:#1e3a8a; }

		.reports-webdata-content .rpd-results-layout {
			display: grid;
			grid-template-columns: 280px minmax(0, 1fr);
			gap: 0.75rem;
			align-items: stretch;
		}

		.reports-webdata-content .rpd-results-card {
			border: 1px solid #e6eef6;
			border-radius: 8px;
			background: #fff;
			padding: 1rem;
			min-height: 340px;
			height: 100%;
			box-sizing: border-box;
			box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
		}

		.reports-webdata-content .rpd-results-card__title {
			margin: 0 0 1.5rem;
			color: #dc3545;
			font-size: 0.78rem;
			font-weight: 800;
			text-align: center;
			text-transform: uppercase;
		}

		.reports-webdata-content .rpd-results-card__section {
			margin-top: 1.25rem;
		}

		.reports-webdata-content .rpd-results-card__label {
			margin: 0;
			color: #dc3545;
			font-size: 0.78rem;
			font-weight: 800;
			text-transform: uppercase;
		}

		.reports-webdata-content .rpd-results-card__value {
			margin: 0.35rem 0 0;
			color: #111827;
			font-size: 0.9rem;
			font-weight: 700;
			overflow-wrap: anywhere;
		}

		.reports-webdata-content .rpd-results-card__subvalue {
			margin: 0.55rem 0 0;
			font-size: 0.82rem;
			font-weight: 700;
			overflow-wrap: anywhere;
		}

		.reports-webdata-content .rpd-results-card__subvalue--php {
			color: #065f46;
		}

		.reports-webdata-content .rpd-results-card__subvalue--usd {
			color: #1e3a8a;
		}

		.reports-webdata-content .rpd-results-main {
			min-width: 0;
			display: flex;
			flex-direction: column;
			height: 100%;
		}

		.reports-webdata-content .rpd-results-main .rwd-results-table-wrap {
			flex: 1 1 auto;
		}

		@media (max-width: 900px) {
			.reports-webdata-content .rpd-results-layout {
				grid-template-columns: 1fr;
			}

			.reports-webdata-content .rpd-results-card {
				min-height: 0;
			}
		}

		.reports-webdata-content .rwd-results-header {
			gap: 1rem;
			align-items: flex-start !important;
		}

		.reports-webdata-content .rwd-results-header > div:first-child {
			flex: 1 1 auto;
			min-width: 0;
		}

		.reports-webdata-content .rwd-results-header > div:last-child {
			flex: 0 0 auto;
			flex-wrap: nowrap !important;
		}

		.reports-webdata-content #rpdExportBtn {
			background: #198754;
			border-color: #198754;
			color: #fff;
			white-space: nowrap;
		}

		.reports-webdata-content #rpdExportBtn:hover:not(:disabled) {
			background: #157347;
			border-color: #157347;
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
			width: min(780px, 94vw);
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
			padding: 14px 18px;
			border-top: 4px solid #dc3545;
			border-bottom: 1px solid #e5e7eb;
		}

		.txn-detail-modal__head h4 {
			margin: 0;
			color: #1f2937;
			font-size: 1rem;
			font-weight: 700;
		}

		.txn-detail-modal__body {
			padding: 16px 18px;
			overflow: auto;
		}

		.txn-detail-grid {
			margin: 0;
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 10px 14px;
		}

		.txn-detail-item {
			min-width: 0;
			padding: 8px 10px;
			border: 1px solid #e5e7eb;
			border-radius: 6px;
			background: #f8fafc;
		}

		.txn-detail-item dt {
			margin: 0 0 4px;
			color: #6b7280;
			font-size: 0.72rem;
			font-weight: 700;
			text-transform: uppercase;
		}

		.txn-detail-item dd {
			margin: 0;
			color: #1f2937;
			font-size: 0.86rem;
			overflow-wrap: anywhere;
		}

		.txn-detail-close {
			border: 1px solid #e5e7eb;
			background: #fff;
			color: #374151;
			border-radius: 999px;
			padding: 8px 18px;
			font-weight: 700;
			cursor: pointer;
			transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
		}

		.txn-detail-close:hover,
		.txn-detail-close:focus-visible {
			background: #dc3545;
			border-color: #dc3545;
			color: #fff;
			box-shadow: 0 8px 18px rgba(220, 53, 69, 0.22);
			outline: none;
		}

		@keyframes rpdModalIn { from { opacity:0; transform: translateY(-6px) scale(0.98); } to { opacity:1; transform: translateY(0) scale(1); } }
	</style>
	<div class="reports-inner" style="padding:.25rem">
		<h3 style="margin:0 0 0.25rem;color:#1f2937;font-size:1.125rem;font-weight:600">Partner Data Transactions</h3>
		<!-- <p style="margin:0 0 1rem;color:#6b7280;font-size:0.9rem">View all transactions uploaded via the Partner Data Uploader, filtered by corporate partner.</p> -->

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
				<label for="rpdCurrencyFilter" style="display:flex;flex-direction:column;gap:0.25rem;font-size:.75rem;color:#6b7280;min-width:10ch">
					<span style="font-size:0.75rem;color:#6b7280">CURRENCY</span>
					<select id="rpdCurrencyFilter" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;background:#fff;min-width:10ch;font-size:.9rem;outline:none">
						<option value="" selected>Select Currency</option>
						<!-- <option value="">ALL</option> -->
						<option value="PHP">PHP</option>
						<option value="USD">USD</option>
					</select>
				</label>
				<label style="display:flex;flex-direction:column;gap:0.25rem;font-size:.75rem;color:#6b7280;min-width:10ch">
					<span style="font-size:0.75rem;color:#6b7280">TRANSACTION TYPE</span>
					<select id="rpdType" name="type" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;background:#fff;min-width:10ch;font-size:.9rem;outline:none">
						<option value="" selected>Select Transaction Type</option>
						<!-- <option value="">ALL</option> -->
						<option value="payout">PAYOUT</option>
						<option value="payout-cancelled">PAYOUT CANCELLED</option>
						<option value="sendout">SENDOUT</option>
						<option value="sendout-cancelled">SENDOUT CANCELLED</option>
					</select>
				</label>
				<button type="button" id="rpdViewBtn" class="material-btn material-btn--primary" style="padding:0.55rem 1rem;border-radius:6px">View transactions</button>
				<button type="button" id="rpdExportBtn" class="material-btn material-btn--secondary" style="padding:0.55rem 1rem;border-radius:6px;display:none">Export to Excel</button>
				<button type="button" id="rpdHideBtn" class="material-btn material-btn--secondary" style="padding:0.55rem 1rem;border-radius:6px;display:none">Clear</button>
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
			<div class="rpd-results-layout">
				<aside class="rpd-results-card" aria-label="Results summary">
					<h5 class="rpd-results-card__title">Filter Results</h5>
					<div class="rpd-results-card__section">
						<p class="rpd-results-card__label">Corporate Partner:</p>
						<p id="rpdCardPartner" class="rpd-results-card__value">-</p>
					</div>
					<div class="rpd-results-card__section">
						<p class="rpd-results-card__label">Transaction Date:</p>
						<p id="rpdCardTransactionDate" class="rpd-results-card__value">-</p>
					</div>
					<div class="rpd-results-card__section">
						<p class="rpd-results-card__label">Currency:</p>
						<p id="rpdCardCurrency" class="rpd-results-card__value">ALL</p>
					</div>
					<div class="rpd-results-card__section">
						<p class="rpd-results-card__label">Transaction Type:</p>
						<p id="rpdCardTransactionType" class="rpd-results-card__value">ALL</p>
					</div>
					<div class="rpd-results-card__section">
						<p class="rpd-results-card__label">Volume:</p>
						<p id="rpdCardVolume" class="rpd-results-card__value">0</p>
					</div>
					<div class="rpd-results-card__section">
						<p class="rpd-results-card__label">Principal:</p>
						<p id="rpdCardPrincipalPhp" class="rpd-results-card__subvalue rpd-results-card__subvalue--php">PHP: 0.00</p>
						<p id="rpdCardPrincipalUsd" class="rpd-results-card__subvalue rpd-results-card__subvalue--usd">USD: 0.00</p>
					</div>
					<div id="rpdCardCommissionSection" class="rpd-results-card__section">
						<p class="rpd-results-card__label">Commission:</p>
						<p id="rpdCardCommissionPhp" class="rpd-results-card__subvalue rpd-results-card__subvalue--php">PHP: 0.00</p>
						<p id="rpdCardCommissionUsd" class="rpd-results-card__subvalue rpd-results-card__subvalue--usd">USD: 0.00</p>
					</div>
				</aside>
				<div class="rpd-results-main">
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
		</div>
	</div>

	<div id="rpdTxnDetailModal" class="txn-detail-modal" aria-hidden="true">
		<div class="txn-detail-modal__overlay" data-action="close-rpd-detail"></div>
		<div class="txn-detail-modal__dialog" role="dialog" aria-modal="true" aria-label="MONEYGRAM Partner Transaction Details">
			<div class="txn-detail-modal__head">
				<h4>MONEYGRAM Partner Transaction Details</h4>
				<button type="button" class="txn-detail-close" data-action="close-rpd-detail">Close</button>
			</div>
			<div class="txn-detail-modal__body" data-role="rpdTxnDetailBody">Loading...</div>
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
		const resultsWrap = document.querySelector('#rpdResults .rwd-results-table-wrap');
		const exportBtn = document.getElementById('rpdExportBtn');
		const hideBtn = document.getElementById('rpdHideBtn');
		const typeFilter = document.getElementById('rpdType');
		const currencyFilter = document.getElementById('rpdCurrencyFilter');
		const pagination = document.getElementById('rpdPagination');
		const paginationInfo = document.getElementById('rpdPaginationInfo');
		const prevBtn = document.getElementById('rpdPrevBtn');
		const nextBtn = document.getElementById('rpdNextBtn');
		const cardPartner = document.getElementById('rpdCardPartner');
		const cardTransactionDate = document.getElementById('rpdCardTransactionDate');
		const cardTransactionType = document.getElementById('rpdCardTransactionType');
		const cardCurrency = document.getElementById('rpdCardCurrency');
		const cardVolume = document.getElementById('rpdCardVolume');
		const cardPrincipalPhp = document.getElementById('rpdCardPrincipalPhp');
		const cardPrincipalUsd = document.getElementById('rpdCardPrincipalUsd');
		const cardCommissionSection = document.getElementById('rpdCardCommissionSection');
		const cardCommissionPhp = document.getElementById('rpdCardCommissionPhp');
		const cardCommissionUsd = document.getElementById('rpdCardCommissionUsd');
		const partners = <?= json_encode($partners) ?>;
		const DEFAULT_PAGE_SIZE = 10000;
		const VIRTUAL_ROW_HEIGHT = 48;
		const VIRTUAL_BUFFER_ROWS = 12;
		let currentFilters = null;
		let lastReportData = null;
		let virtualPartnerRows = [];
		let virtualPartnerMode = 'standard';
		let virtualPartnerColCount = 10;

		function updateResultsCard(data, totals, hideCommission) {
			const filters = currentFilters || {};
			const startDate = filters.startDate || (data && data.start_date) || '';
			const endDate = filters.endDate || (data && data.end_date) || '';
			const transactionDate = startDate && endDate
				? (startDate === endDate ? formatDateLong(startDate) : `${formatDateLong(startDate)} to ${formatDateLong(endDate)}`)
				: (startDate ? `From ${formatDateLong(startDate)}` : (endDate ? `Until ${formatDateLong(endDate)}` : '-'));
			const transactionType = filters.type ? String(filters.type).toUpperCase() : 'ALL';
			const selectedCurrency = filters.currency || 'ALL';
			const count = Number(data && data.page_count !== undefined ? data.page_count : (data && data.count ? data.count : 0));
			if (cardPartner) cardPartner.textContent = (data && data.partner) || filters.partner || '-';
			if (cardTransactionDate) cardTransactionDate.textContent = transactionDate;
			if (cardTransactionType) cardTransactionType.textContent = transactionType;
			if (cardCurrency) cardCurrency.textContent = selectedCurrency;
			if (cardVolume) cardVolume.textContent = formatNumber(count);
			if (cardPrincipalPhp) {
				cardPrincipalPhp.textContent = `PHP: ${formatCurrencyAllowZero(totals.phpTotal)}`;
				cardPrincipalPhp.style.display = selectedCurrency === 'USD' ? 'none' : '';
			}
			if (cardPrincipalUsd) {
				cardPrincipalUsd.textContent = `USD: ${formatCurrencyAllowZero(totals.usdTotal)}`;
				cardPrincipalUsd.style.display = selectedCurrency === 'PHP' ? 'none' : '';
			}
			if (cardCommissionSection) cardCommissionSection.style.display = hideCommission ? 'none' : '';
			if (cardCommissionPhp) {
				cardCommissionPhp.textContent = `PHP: ${formatCurrencyAllowZero(totals.phpCommissionTotal)}`;
				cardCommissionPhp.style.display = selectedCurrency === 'USD' ? 'none' : '';
			}
			if (cardCommissionUsd) {
				cardCommissionUsd.textContent = `USD: ${formatCurrencyAllowZero(totals.usdCommissionTotal)}`;
				cardCommissionUsd.style.display = selectedCurrency === 'PHP' ? 'none' : '';
			}
		}

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

		// Initialize autocomplete
		attachPartnerAutocomplete(input, partners);

		// Modal helpers
		const modalOverlay = document.getElementById('rpdModalOverlay');
		const missingListEl = document.getElementById('rpdMissingList');
		const modalOkBtn = document.getElementById('rpdModalOkBtn');

		function showRequiredModal(missing) {
			if (!Array.isArray(missing)) missing = [String(missing || '')];
			if (window.Swal) {
				const html = '<ul style="margin:0;text-align:left;padding-left:1.25rem;">' + missing.map(function(it){
					return '<li>' + escapeHtml(String(it || '')) + '</li>';
				}).join('') + '</ul>';
				return Swal.fire({
					title: 'Required Fields',
					html: html,
					icon: 'warning',
					confirmButtonText: 'OK',
					confirmButtonColor: '#dc3545',
					heightAuto: false
				});
			}
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
		function showDurationAlert(message) {
			if (window.Swal) {
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
		function syncEndDateToStartDate() {
			if (sdEl && edEl && sdEl.value) {
				edEl.value = sdEl.value;
				edEl.classList.remove('rpd-invalid');
			}
			if (sdEl && sdEl.value) sdEl.classList.remove('rpd-invalid');
		}
		if (sdEl) {
			sdEl.addEventListener('input', syncEndDateToStartDate);
			sdEl.addEventListener('change', syncEndDateToStartDate);
		}
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

		function formatCurrencyAbsolute(value) {
			if (value === '' || value === null || value === undefined || isNaN(Number(value))) return '';
			const num = Math.abs(parseFloat(value));
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
			const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})(.*)$/);
			if (!match) return raw;
			return match[2] + '-' + match[3] + '-' + match[1] + (match[4] || '');
		}

		function isAmountDetailField(key) {
			return /(^|_)(amount|amt|php|usd|charge|fee|comm|commission|total|principal)(_|$)/i.test(String(key || ''));
		}

		function renderDetailGrid(data) {
			const entries = Object.entries(data || {});
			if (!entries.length) return '<div style="color:#6b7280;padding:12px 0">Transaction details not found.</div>';
			return '<dl class="txn-detail-grid">' + entries.map(([key, value]) => {
				const displayValue = isAmountDetailField(key) ? formatCurrencyAbsolute(value) : formatDetailDateValue(value);
				return '<div class="txn-detail-item"><dt>' + escapeHtml(key) + '</dt><dd>' + escapeHtml(displayValue) + '</dd></div>';
			}).join('') + '</dl>';
		}

		function closePartnerDetailModal() {
			const modal = document.getElementById('rpdTxnDetailModal');
			if (!modal) return;
			modal.style.display = 'none';
			modal.setAttribute('aria-hidden', 'true');
		}

		async function openPartnerDetailModal(id) {
			const modal = document.getElementById('rpdTxnDetailModal');
			const body = modal ? modal.querySelector('[data-role="rpdTxnDetailBody"]') : null;
			if (!modal || !body || !id) return;
			body.innerHTML = '<div style="color:#6b7280;padding:12px 0">Loading...</div>';
			modal.style.display = 'flex';
			modal.setAttribute('aria-hidden', 'false');
			try {
				const res = await fetch(`${getAppBasePath()}/src/controllers/recon/moneygram-partner-transaction-details.php?id=${encodeURIComponent(id)}`, { method: 'GET' });
				const json = await res.json();
				if (!res.ok || !(json && json.success && json.data)) {
					body.innerHTML = '<div style="color:#6b7280;padding:12px 0">Transaction details not found.</div>';
					return;
				}
				body.innerHTML = renderDetailGrid(json.data);
			} catch (err) {
				console.error('MONEYGRAM partner detail error', err);
				body.innerHTML = '<div style="color:#6b7280;padding:12px 0">Transaction details not found.</div>';
			}
		}

		document.querySelectorAll('[data-action="close-rpd-detail"]').forEach(btn => {
			btn.addEventListener('click', closePartnerDetailModal);
		});

		// Format date
		function formatDateLong(dateStr) {
			if (!dateStr) return '';
			const value = String(dateStr).trim();
			const dateOnly = value.split(/\s+/)[0];
			const parts = dateOnly.match(/^(\d{4})-(\d{2})-(\d{2})$/);
			const date = parts
				? new Date(Number(parts[1]), Number(parts[2]) - 1, Number(parts[3]))
				: new Date(value);
			if (isNaN(date.getTime())) return value;
			return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: '2-digit' });
		}

		function formatDate(dateStr) {
			return formatDateLong(dateStr);
		}

		function formatDateMonthDayYear(dateStr) {
			return formatDateLong(dateStr);
		}

		async function fetchTransactions(page) {
			if (!currentFilters) return;

			viewBtn.disabled = true;
			viewBtn.textContent = 'Loading...';
			prevBtn.disabled = true;
			nextBtn.disabled = true;
			if (exportBtn) exportBtn.disabled = true;

			try {
				const params = new URLSearchParams({
					partner: currentFilters.partner,
					start_date: currentFilters.startDate,
					end_date: currentFilters.endDate,
					type: currentFilters.type || '',
					settlement_currency: currentFilters.currency || '',
					page: String(page),
					per_page: String(DEFAULT_PAGE_SIZE)
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
			if (startDate > endDate) {
				await showDurationAlert('Start Date cannot be greater than End Date.');
				return;
			}

			currentFilters = { partner: partnerVal, startDate, endDate, type: typeFilter ? typeFilter.value : '', currency: currencyFilter ? currencyFilter.value : '' };
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

		// Currency and transaction type are applied only when View transactions is clicked.

		function isWorldcomPartner(name) {
			return /worldcom|\bwic\b/i.test(String(name || ''));
		}

		function isMetrobankHeadOfficePartner(name) {
			return /metrobank\s+head\s+office|\bmbtc\b/i.test(String(name || ''));
		}

		function isMoneygramPartner(name) {
			return /^moneygram$/i.test(String(name || '').trim());
		}

		function hideReportData() {
			resultsBody.innerHTML = '';
			if (resultsDiv) {
				resultsDiv.style.display = 'none';
				if (resultsDiv.dataset) resultsDiv.dataset.reportData = '';
			}
			if (pagination) pagination.style.display = 'none';
			if (hideBtn) hideBtn.style.display = 'none';
			if (exportBtn) {
				exportBtn.style.display = 'none';
				exportBtn.disabled = false;
				exportBtn.textContent = 'Export to Excel';
			}
			virtualPartnerRows = [];
			lastReportData = null;
			currentFilters = null;
		}

		if (hideBtn) {
			hideBtn.addEventListener('click', function(e) {
				e.preventDefault();
				hideReportData();
			});
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
			noHeader.style.display = isWic ? 'none' : '';
			controlSeriesHeader.textContent = isWic ? 'Date' : 'Control Series';
			dateClaimedHeader.textContent = isWic ? 'Transaction ID' : 'Date Claimed';
			ccrefHeader.textContent = 'CCREF NO';
			ccrefHeader.style.display = isWic ? 'none' : '';
			currencyHeader.textContent = isWic ? 'Amount' : 'Currency';
			amountHeader.textContent = isWic ? 'Coin' : 'Amount';
			senderHeader.textContent = 'Sender';
			beneficiaryHeader.textContent = 'Beneficiary';
			operatorHeader.textContent = 'Operator';
			branchHeader.textContent = 'Branch';

			currencyHeader.style.textAlign = 'left';
			amountHeader.style.textAlign = isWic ? 'center' : 'right';
			beneficiaryHeader.style.textAlign = 'left';
			operatorHeader.style.textAlign = 'left';
			branchHeader.style.textAlign = 'left';

			const hiddenDisplay = isWic ? 'none' : '';
			['rpdThSender', 'rpdThBeneficiary', 'rpdThOperator', 'rpdThBranch'].forEach(function(id) {
				document.getElementById(id).style.display = hiddenDisplay;
			});
		}

		const moneygramCols = ['tran_date','agent_name','legacy_id','account_number','reference_id','tran_type','tran_fx_rate','fx_rev_share_amt','settlement_currency','base_amt','comm_amt','orig_cntry','rcv_cntry'];
		const moneygramAmtCols = new Set(['fx_rev_share_amt','base_amt','comm_amt']);

		function createPartnerSpacerRow(height, colSpan) {
			const tr = document.createElement('tr');
			const td = document.createElement('td');
			td.colSpan = colSpan;
			td.style.height = Math.max(0, height) + 'px';
			td.style.padding = '0';
			td.style.border = '0';
			tr.appendChild(td);
			return tr;
		}

		function createMoneygramResultRow(row, visibleIndex) {
			const tr = document.createElement('tr');
			tr.style.borderBottom = '1px solid #f0f0f0';
			if (visibleIndex % 2 === 1) tr.style.backgroundColor = '#fafafa';

			moneygramCols.forEach(col => {
				const td = document.createElement('td');
				td.style.padding = '0.75rem';
				td.style.color = '#1f2937';
				const raw = (row[col] === null || row[col] === undefined) ? '' : row[col];
				if(col === 'tran_date'){
					td.textContent = formatDateMonthDayYear(raw);
				} else if(col === 'tran_type'){
					const value = String(raw || '').trim();
					td.textContent = value.toUpperCase() === 'REC' ? 'REC(PAY OUT)' : value;
				} else if(moneygramAmtCols.has(col)){
					td.style.textAlign = 'right';
					td.style.fontFamily = 'monospace';
					td.textContent = raw !== '' ? formatCurrencyAbsolute(raw) : '';
				} else if(col === 'tran_fx_rate'){
					td.style.textAlign = 'right';
					td.style.fontFamily = 'monospace';
					td.textContent = raw !== '' ? String(raw) : '';
				} else if (col === 'legacy_id') {
					td.style.textAlign = 'center';
					td.textContent = raw !== '' ? String(raw) : '';
				} else {
					td.textContent = String(raw);
				}
				tr.appendChild(td);
			});

			const viewTd = document.createElement('td');
			viewTd.style.padding = '0.75rem';
			viewTd.style.color = '#1f2937';
			viewTd.style.textAlign = 'center';
			const id = row['id'] || '';
			if(id){
				const viewBtn = document.createElement('button');
				viewBtn.type = 'button';
				viewBtn.className = 'txn-view-btn';
				viewBtn.textContent = 'View';
				viewBtn.dataset.txnId = id;
				viewTd.appendChild(viewBtn);
			}
			tr.appendChild(viewTd);
			return tr;
		}

		function createStandardPartnerResultRow(row, visibleIndex, mode) {
			const tr = document.createElement('tr');
			tr.style.borderBottom = '1px solid #f0f0f0';
			if (visibleIndex % 2 === 1) tr.style.backgroundColor = '#fafafa';

			const allCells = [
				row['no'] || '',
				row['control_series_no'] || '',
				formatDate(row['date_claimed']),
				row['ccref_no'] || '',
				row['currency'] || '',
				formatCurrencyAbsolute(row['amount']),
				row['sender_name'] || '',
				row['beneficiary_receiver'] || '',
				row['operator'] || '',
				row['branch'] || ''
			];

			const mbtcCells = [
				formatDateLong(row['partner_date'] || row['date_claimed'] || ''),
				row['partner_time'] || '',
				row['partner_reference_no'] || row['control_series_no'] || '',
				row['partner_rts_tracer_no'] || '',
				row['partner_provider'] || '',
				row['partner_beneficiary_name'] || row['beneficiary_receiver'] || '',
				row['partner_remitter_name'] || row['sender_name'] || '',
				formatCurrencyAbsolute(row['partner_php']),
				formatCurrencyAbsolute(row['partner_usd']),
				formatCurrencyAbsolute(row['partner_in_php'])
			];

			const isMbtcMode = mode === 'mbtc';
			const isWicMode = mode === 'wic';
			const cells = isMbtcMode ? mbtcCells : (isWicMode ? [allCells[2], allCells[1], allCells[5], allCells[4]] : allCells);
			const amountCellIdx = isWicMode ? 2 : 5;

			cells.forEach((cellValue, cellIdx) => {
				const td = document.createElement('td');
				td.style.padding = '0.75rem';
				td.style.color = '#1f2937';
				if (isMbtcMode && (cellIdx === 7 || cellIdx === 8 || cellIdx === 9)) {
					td.style.textAlign = 'right';
					td.style.fontFamily = 'monospace';
				}
				if (!isMbtcMode && cellIdx === amountCellIdx && !isWicMode) {
					td.style.textAlign = 'right';
					td.style.fontFamily = 'monospace';
				}
				if (isWicMode && cellIdx === amountCellIdx) {
					td.style.textAlign = 'left';
					td.style.fontFamily = 'monospace';
				}
				if (isWicMode && cellIdx === 3) {
					td.style.textAlign = 'center';
				}
				td.textContent = cellValue;
				tr.appendChild(td);
			});

			return tr;
		}

		function renderVirtualPartnerRows() {
			if (!resultsWrap) return;
			const rows = virtualPartnerRows || [];
			const scrollTop = resultsWrap.scrollTop || 0;
			if (!rows.length) return;

			const viewportHeight = resultsWrap.clientHeight || 480;
			const startIndex = Math.max(0, Math.floor(scrollTop / VIRTUAL_ROW_HEIGHT) - VIRTUAL_BUFFER_ROWS);
			const visibleCount = Math.ceil(viewportHeight / VIRTUAL_ROW_HEIGHT) + (VIRTUAL_BUFFER_ROWS * 2);
			const endIndex = Math.min(rows.length, startIndex + visibleCount);
			const fragment = document.createDocumentFragment();
			const topHeight = startIndex * VIRTUAL_ROW_HEIGHT;
			const bottomHeight = Math.max(0, (rows.length - endIndex) * VIRTUAL_ROW_HEIGHT);

			if (topHeight > 0) fragment.appendChild(createPartnerSpacerRow(topHeight, virtualPartnerColCount));
			for (let i = startIndex; i < endIndex; i++) {
				fragment.appendChild(virtualPartnerMode === 'moneygram'
					? createMoneygramResultRow(rows[i], i)
					: createStandardPartnerResultRow(rows[i], i, virtualPartnerMode));
			}
			if (bottomHeight > 0) fragment.appendChild(createPartnerSpacerRow(bottomHeight, virtualPartnerColCount));
			resultsBody.replaceChildren(fragment);
			if (resultsWrap.scrollTop !== scrollTop) resultsWrap.scrollTop = scrollTop;
		}

		if (resultsWrap) {
			resultsWrap.addEventListener('scroll', function() {
				window.requestAnimationFrame(renderVirtualPartnerRows);
			});
		}

		resultsBody.addEventListener('click', function(event) {
			const btn = event.target.closest ? event.target.closest('.txn-view-btn[data-txn-id]') : null;
			if (!btn) return;
			openPartnerDetailModal(btn.dataset.txnId);
		});

		// Display results in table
		function displayResults(data) {
			if (resultsTitle) resultsTitle.textContent = data.partner || 'Partner data transactions';

			let dateRange = '';
			if (data.start_date && data.end_date) {
				dateRange = ` (${data.start_date} to ${data.end_date})`;
			} else if (data.start_date) {
				dateRange = ` (from ${data.start_date})`;
			} else if (data.end_date) {
				dateRange = ` (until ${data.end_date})`;
			}

			const isMoneygram = isMoneygramPartner(data.partner);
			const isWic = isWorldcomPartner(data.partner);
			const isMbtc = isMetrobankHeadOfficePartner(data.partner);
			const phpTotal = Math.abs(Number(data.php_total !== undefined ? data.php_total : (data.moneygram_php_total || 0)));
			const usdTotal = Math.abs(Number(data.usd_total !== undefined ? data.usd_total : (data.moneygram_usd_total || 0)));
			const phpCommissionTotal = Math.abs(Number(data.php_commission_total !== undefined ? data.php_commission_total : (data.moneygram_commission_php_total || 0)));
			const usdCommissionTotal = Math.abs(Number(data.usd_commission_total !== undefined ? data.usd_commission_total : (data.moneygram_commission_usd_total || 0)));
			updateResultsCard(data, {
				phpTotal: phpTotal,
				usdTotal: usdTotal,
				phpCommissionTotal: phpCommissionTotal,
				usdCommissionTotal: usdCommissionTotal
			}, isWic);
			if (resultsSummary) {
				resultsSummary.textContent = dateRange ? dateRange.replace(/^\s*\(|\)\s*$/g, '') : '';
				resultsSummary.style.display = resultsSummary.textContent ? '' : 'none';
			}
			const resultsTable = document.getElementById('rpdResultsTable');
			if (resultsTable) {
				resultsTable.classList.toggle('rpd-table--wic', isWic);
			}
			if(!isMoneygram){
				applyPartnerColumnConfig(data.partner);
			}
			const visibleColCount = isMoneygram ? 14 : (isWic ? 4 : 10);
			const rowsForDisplay = isMoneygram ? (Array.isArray(data.moneygram_rows) ? data.moneygram_rows : []) : (Array.isArray(data.rows) ? data.rows : []);

			// Clear table
			resultsBody.innerHTML = '';
			virtualPartnerRows = [];

			if (rowsForDisplay.length === 0) {
				resultsBody.innerHTML = `<tr><td colspan="${visibleColCount}" style="padding:1rem;text-align:center;color:#9ca3af">No transactions found</td></tr>`;
				pagination.style.display = 'none';
				resultsDiv.style.display = 'block';
				if (hideBtn) hideBtn.style.display = '';
				if (exportBtn) {
					exportBtn.style.display = 'none';
					exportBtn.disabled = true;
				}
				return;
			}

			if(isMoneygram){
				const mgHeaders = ['Tran Date','Agent Name','Legacy ID','Account Number','Reference ID','Tran Type','Tran Fx Rate','Fx Rev Share Amt','Settlement Currency','Base Amt','Comm Amt','Orig Cntry','Rcv Cntry','Details'];
				const mgNumericCols = new Set(['tran_fx_rate','fx_rev_share_amt','base_amt','comm_amt']);
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
						const colName = moneygramCols[i] || '';
						if (mgNumericCols.has(colName)) th.style.textAlign = 'right';
						else if (colName === 'legacy_id' || h === 'Details') th.style.textAlign = 'center';
						else th.style.textAlign = 'left';
						th.textContent = h;
						tr.appendChild(th);
					});
					thead.innerHTML = '';
					thead.appendChild(tr);
				}
				virtualPartnerRows = rowsForDisplay;
				virtualPartnerMode = 'moneygram';
				virtualPartnerColCount = 14;
			} else {
				virtualPartnerRows = rowsForDisplay;
				virtualPartnerMode = isMbtc ? 'mbtc' : (isWic ? 'wic' : 'standard');
				virtualPartnerColCount = visibleColCount;
			}
			if (resultsWrap) resultsWrap.scrollTop = 0;
			renderVirtualPartnerRows();

			resultsDiv.style.display = 'block';
			if (hideBtn) hideBtn.style.display = '';
			if (exportBtn) {
				exportBtn.style.display = '';
				exportBtn.disabled = false;
			}

			const page = Number(data.page || 1);
			const perPage = Number(data.per_page || DEFAULT_PAGE_SIZE);
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

		// Export to CSV
		exportBtn.addEventListener('click', function() {
			if (!lastReportData) return;

			const data = lastReportData;
			const partner = data.partner;
				const rows = data.rows;
const csvIsMoneygram = isMoneygramPartner(partner);
			const csvIsWic = isWorldcomPartner(partner);
			const csvIsMbtc = isMetrobankHeadOfficePartner(partner);

			// Moneygram: request server-side Excel export including Legacy ID
			if(csvIsMoneygram){
				const params = new URLSearchParams({ partner: partner, start_date: data.start_date || '', end_date: data.end_date || '', type: typeFilter ? typeFilter.value : '', settlement_currency: currencyFilter ? currencyFilter.value : '' });
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
				}).finally(() => { exportBtn.disabled = false; exportBtn.textContent = 'Export to Excel'; });

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
				? ['Date', 'Transaction ID', 'Amount', 'Coin']
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
					: (csvIsWic ? [allValues[2], allValues[1], allValues[5], allValues[4]] : allValues);
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
		const origDisplayResultsRpd = displayResults;
		displayResults = function(data){ origDisplayResultsRpd(data); setTimeout(function(){ setPartnerRecordsHeight(); renderVirtualPartnerRows(); },50); };
		setTimeout(setPartnerRecordsHeight,200);
	})();
	</script>
</div>
