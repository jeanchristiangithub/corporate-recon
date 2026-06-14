<?php
// Reports UI: Web data transactions (filtered by corporate partner)
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
	</style>
	<div class="reports-inner" style="padding:.25rem">
	<style>
		/* Layout for sticky top filters and scrollable records area */
		.reports-webdata-content .rwd-results-table-wrap {
			overflow-x: auto;
			/* vertical scrolling area will be set dynamically by JS */
		}
		.reports-webdata-content .rwd-results-table-wrap table thead th {
			position: sticky;
			top: 0;
			z-index: 5;
			background: #f9fafb;
		}
	</style>
		<h3 style="margin:0 0 0.25rem;color:#1f2937;font-size:1.125rem;font-weight:600">KPX Web data transactions</h3>
		<p style="margin:0 0 1rem;color:#6b7280;font-size:0.9rem">View all transactions uploaded via the ML Web Data Uploader, filtered by corporate partner.</p>

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

			<div style="display:flex;align-items:flex-end;gap:0.5rem;margin-left:auto">
				<label style="display:flex;flex-direction:column;gap:0.25rem;font-size:.75rem;color:#6b7280;min-width:10ch">
					<span style="font-size:0.75rem;color:#6b7280">TRANSACTION TYPE</span>
					<select id="rwdType" name="type" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;background:#fff;min-width:10ch;font-size:.9rem;outline:none">
						<option value="">ALL</option>
						<option value="payout">PAYOUT</option>
						<option value="sendout">SENDOUT</option>
					</select>
				</label>
				<button type="button" id="rwdViewBtn" class="material-btn material-btn--primary" style="padding:0.55rem 1rem;border-radius:6px">View transactions</button>
				<button type="button" id="rwdClearBtn" class="material-btn material-btn--secondary" style="padding:0.55rem 1rem;border-radius:6px">Clear filters</button>
			</div>
		</form>

		<!-- Additional multi-column filters: MAINZONE, ZONE, REGION, AREA, BRANCH NAME, BRANCH ID -->
		<div class="rwd-multi-filters" style="margin-top:0.75rem;">
			<form id="rwdExtraFilters" style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center">
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
			</form>
		</div>

		<!-- Results container -->
		<div id="rwdResults" class="rwd-results" style="margin-top:1.5rem;display:none">
			<div class="rwd-results-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;padding-bottom:0.75rem;border-bottom:2px solid #e6eef6">
				<div>
					<h4 id="rwdResultsTitle" style="margin:0;color:#1f2937;font-size:1rem;font-weight:600"></h4>
					<p id="rwdResultsSummary" style="margin:0.25rem 0 0 0;color:#6b7280;font-size:0.9rem"></p>
				</div>
				<button type="button" id="rwdExportBtn" class="material-btn material-btn--secondary" style="padding:0.25rem 1rem;border-radius:6px">Export Excel</button>
			</div>
			<div class="rwd-results-table-wrap" style="overflow-x:auto;border:1px solid #e6eef6;border-radius:8px">
				<table id="rwdResultsTable" class="rwd-results-table" style="width:100%;border-collapse:collapse;font-size:0.6rem">
					<thead style="background:#f9fafb;border-bottom:1px solid #e6eef6">
						<tr>
							<th style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">No.</th>
							<th style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">Control Series</th>
							<th id="rwdDateHeader" style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">Date Claimed</th>
							<th style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">CCREF NO</th>
							<th style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">Currency</th>
							<th style="padding:0.5rem;text-align:right;color:#6b7280;font-weight:600;white-space:nowrap">Amount</th>
							<th style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">Sender</th>
							<th style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">Beneficiary</th>
							<th id="rwdThOperator" style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">Operator</th>
							<th style="padding:0.5rem;text-align:left;color:#6b7280;font-weight:600;white-space:nowrap">Branch</th>
						</tr>
					</thead>
					<tbody id="rwdResultsBody">
					</tbody>
				</table>
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

	<script>
	(function(){
		const form = document.getElementById('reportsWebdataForm');
		const input = document.getElementById('rwdPartner');
		const viewBtn = document.getElementById('rwdViewBtn');
		const resultsDiv = document.getElementById('rwdResults');
		const resultsTitle = document.getElementById('rwdResultsTitle');
		const resultsSummary = document.getElementById('rwdResultsSummary');
		const resultsBody = document.getElementById('rwdResultsBody');
		const dateHeader = document.getElementById('rwdDateHeader');
		const operatorHeader = document.getElementById('rwdThOperator');
		const exportBtn = document.getElementById('rwdExportBtn');
		const pagination = document.getElementById('rwdPagination');
		const paginationInfo = document.getElementById('rwdPaginationInfo');
		const prevBtn = document.getElementById('rwdPrevBtn');
		const nextBtn = document.getElementById('rwdNextBtn');
		const PAGE_SIZE = 10000;
		let currentFilters = null;
		let lastReportData = null;

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
				closeSuggestions();
				inputEl.dispatchEvent(new Event('input',{bubbles:true}));
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

		async function fetchPartnerSuggestions(q){
			const params = new URLSearchParams({ q: String(q || '') });
			const res = await fetch(`${getAppBasePath()}/src/controllers/masterdata/corpo-partner-values.php?${params.toString()}`);
			if(!res.ok) return [];
			const obj = await res.json();
			if(obj && obj.success && Array.isArray(obj.values)) return obj.values;
			return [];
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

		attachRemoteAutocomplete(input, fetchPartnerSuggestions);
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
					li.addEventListener('mousedown', function(e){ e.preventDefault(); inputEl.value = it; inputEl.dispatchEvent(new Event('input',{bubbles:true})); inputEl.dispatchEvent(new Event('change',{bubbles:true})); });
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
					li.addEventListener('mousedown', function(e){ e.preventDefault(); inputEl.value = it; inputEl.dispatchEvent(new Event('input',{bubbles:true})); inputEl.dispatchEvent(new Event('change',{bubbles:true})); });
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
					lastReportData = null;
					currentFilters = null;
				} catch(e) { console.warn('Error hiding results after clearing filters', e); }
			} catch (e) { console.warn('Error clearing filters', e); }
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

		function getAppBasePath() {
			const parts = window.location.pathname.split('/').filter(Boolean);
			return parts.length > 0 ? `/${parts[0]}` : '';
		}

			// --- Date validation: require both Start and End date before enabling filters/View ---
			const startDateEl = document.getElementById('rwdStartDate');
			const endDateEl = document.getElementById('rwdEndDate');
			const filterIds = ['rwdMainzone','rwdZone','rwdRegion','rwdArea','rwdBranchName','rwdBranchId'];

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
				alert('Please select Start Date and End Date first.');
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
			if(startDateEl){ startDateEl.addEventListener('change', updateDateValidationState); startDateEl.addEventListener('input', updateDateValidationState); }
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
				alert('Please select a corporate partner to view transactions.');
				input.focus();
				return;
			}

			const sd = String(document.getElementById('rwdStartDate').value || '').trim();
			const ed = String(document.getElementById('rwdEndDate').value || '').trim();
			if (!sd || !ed) {
				alert('Please select Start Date and End Date first.');
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

			currentFilters = { partner, startDate, endDate, mainzone, zone, region, area, branch_name, branch_id, type };
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

		// Display results in table
		function displayResults(data) {
			const activeDateLabel = String(data.date_label || 'Date Claimed');
			if(dateHeader) dateHeader.textContent = activeDateLabel;
			// Toggle Operator header to Charge for SENDOUT reports
			const isSendout = String((data.type || '')).toLowerCase() === 'sendout';
			if(operatorHeader) operatorHeader.textContent = isSendout ? 'Charge' : 'Operator';
			resultsTitle.textContent = `${data.partner} — ${formatNumber(data.count)} transaction${data.count !== 1 ? 's' : ''}`;

			let dateRange = '';
			if (data.start_date && data.end_date) {
				dateRange = ` (${data.start_date} to ${data.end_date})`;
			} else if (data.start_date) {
				dateRange = ` (from ${data.start_date})`;
			} else if (data.end_date) {
				dateRange = ` (until ${data.end_date})`;
			}

			// Render date range and currency totals (separate badges for PHP and USD)
			const phpTotal = Number(data.php_total || 0);
			const usdTotal = Number(data.usd_total || 0);
			const phpBadge = `<span class="badge php">PHP Total: ₱${formatCurrency(phpTotal, 'PHP')}</span>`;
			const usdBadge = `<span class="badge usd">USD Total: $${formatCurrency(usdTotal, 'USD')}</span>`;
			// Show Charge total only for SENDOUT
			const chargeTotal = Number(data.charge_total || 0);
			const chargeBadge = `<span class="badge charge">Charge Total: ₱${formatCurrency(chargeTotal, 'PHP')}</span>`;
			resultsSummary.innerHTML = `${dateRange} <span class="currency-summary">${phpBadge} ${usdBadge}${isSendout ? ' ' + chargeBadge : ''}</span>`;

			// Clear table
			resultsBody.innerHTML = '';

			if (!data.rows || data.rows.length === 0) {
				resultsBody.innerHTML = '<tr><td colspan="10" style="padding:1rem;text-align:center;color:#9ca3af">No transactions found</td></tr>';
				pagination.style.display = 'none';
				// still display totals (will be 0.00 if none)
				const phpTotalEmpty = Number(data.php_total || 0);
				const usdTotalEmpty = Number(data.usd_total || 0);
				const phpBadgeEmpty = `<span class="badge php">PHP Total: ₱${formatCurrency(phpTotalEmpty, 'PHP')}</span>`;
				const usdBadgeEmpty = `<span class="badge usd">USD Total: $${formatCurrency(usdTotalEmpty, 'USD')}</span>`;
				const chargeTotalEmpty = Number(data.charge_total || 0);
				const chargeBadgeEmpty = `<span class="badge charge">Charge Total: ₱${formatCurrency(chargeTotalEmpty, 'PHP')}</span>`;
				resultsSummary.innerHTML = `${dateRange} <span class="currency-summary">${phpBadgeEmpty} ${usdBadgeEmpty}${isSendout ? ' ' + chargeBadgeEmpty : ''}</span>`;
				resultsDiv.style.display = 'block';
				return;
			}

			// Populate table
			data.rows.forEach((row, idx) => {
				const tr = document.createElement('tr');
				tr.style.borderBottom = '1px solid #f0f0f0';
				if (idx % 2 === 1) tr.style.backgroundColor = '#fafafa';

				const isSendoutRow = isSendout;
				const operatorCellValue = isSendoutRow ? formatCurrency(row['charge'] || 0, row['currency']) : (row['operator'] || '');
				const cells = [
					row['no'] || '',
					row['control_series_no'] || '',
					formatDate(row['report_date']),
					row['ccref_no'] || '',
					row['currency'] || '',
					formatCurrency(row['amount'], row['currency']),
					row['sender_name'] || '',
					row['beneficiary_receiver'] || '',
					operatorCellValue,
					row['branch'] || ''
				];

				cells.forEach((cellValue, cellIdx) => {
					const td = document.createElement('td');
					td.style.padding = '0.75rem';
					td.style.color = '#1f2937';
					if (cellIdx === 5) {
						td.style.textAlign = 'right';
						td.style.fontFamily = 'monospace';
					}
					td.textContent = cellValue;
					tr.appendChild(td);
				});

				resultsBody.appendChild(tr);
			});

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
					branch_id: branch_id || ''
				});

				// Build filename on client side (sanitization on server too)
				function sanitizeFilename(s) { return (s || '').replace(/[\\/:*?"<>|]+/g, '_'); }
				const partnerPart = (partner || '').replace(/\s+/g, '_');
				const typePart = (type || '') === '' ? 'ALL' : type.toUpperCase();
				const branchPart = branch_name ? sanitizeFilename(branch_name) : '';
				const filenameParts = [partnerPart, `${startDate}_to_${endDate}`, typePart];
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
				const wrap = document.querySelector('.rwd-results-table-wrap');
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
		window.addEventListener('scroll', setRecordsScrollHeight);
		// Recompute whenever results are displayed
		const origDisplayResults = displayResults;
		displayResults = function(data){ origDisplayResults(data); setTimeout(setRecordsScrollHeight,50); };

		// Initial compute in case layout is pre-populated
		setTimeout(setRecordsScrollHeight,200);
	})();
	</script>
</div>
