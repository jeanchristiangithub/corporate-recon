<?php
// dashboard fragment: Legacy IDs overview
require_once __DIR__ . '/../../../config/db.php';

$isDashboardAdmin = isset($_SESSION['user']['role'])
	&& strcasecmp((string) $_SESSION['user']['role'], 'Admin') === 0;

$missingMoneygramLegacyBranches = [];
$missingLegacyLookupAvailable = true;
try {
	$filePdo = fileRecDbConnection();
	$moneygramColumns = $filePdo->query('SHOW COLUMNS FROM moneygram_partner_data')->fetchAll(PDO::FETCH_COLUMN);
	$moneygramColumns = array_map('strtolower', array_map('strval', $moneygramColumns));
	if (!in_array('branch_id', $moneygramColumns, true)) {
		$missingLegacyLookupAvailable = false;
	} else {
		$createdColumn = in_array('created_at', $moneygramColumns, true) ? '`created_at`' : 'NULL';
		$legacyColumn = in_array('legacy_id', $moneygramColumns, true)
			? "MAX(NULLIF(TRIM(`legacy_id`), ''))"
			: 'NULL';
		$sql = 'SELECT TRIM(`branch_id`) AS branch_id, ' . $legacyColumn . ' AS detected_legacy_id, '
			. 'MIN(' . $createdColumn . ') AS first_detected, MAX(' . $createdColumn . ') AS last_detected, COUNT(*) AS transaction_count '
			. 'FROM `moneygram_partner_data` WHERE `branch_id` IS NOT NULL AND TRIM(`branch_id`) <> \'\' '
			. 'GROUP BY TRIM(`branch_id`) ORDER BY MAX(' . $createdColumn . ') DESC';
		$legacyCandidates = $filePdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

		$legacyCandidateIds = array_values(array_unique(array_filter(array_map(static function (array $row): string {
			return trim((string) ($row['branch_id'] ?? ''));
		}, $legacyCandidates))));
		$profilesByBranchId = [];
		if ($legacyCandidateIds !== []) {
			$masterPdo = masterDataConnection();
			foreach (array_chunk($legacyCandidateIds, 500) as $idChunk) {
				$placeholders = implode(',', array_fill(0, count($idChunk), '?'));
				$stmt = $masterPdo->prepare("SELECT TRIM(branch_id) AS branch_id, branch_name, legacyid_moneygram FROM branch_profile WHERE TRIM(branch_id) IN ($placeholders)");
				$stmt->execute($idChunk);
				foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $profile) {
					$profilesByBranchId[trim((string) ($profile['branch_id'] ?? ''))] = $profile;
				}
			}
		}

		foreach ($legacyCandidates as $candidate) {
			$branchId = trim((string) ($candidate['branch_id'] ?? ''));
			$profile = $profilesByBranchId[$branchId] ?? null;
			if ($profile === null || trim((string) ($profile['legacyid_moneygram'] ?? '')) !== '') continue;
			$candidate['branch_name'] = trim((string) ($profile['branch_name'] ?? ''));
			$candidate['partner_name'] = 'MONEYGRAM';
			$missingMoneygramLegacyBranches[] = $candidate;
		}
	}
} catch (Throwable $e) {
	$missingLegacyLookupAvailable = false;
	$missingMoneygramLegacyBranches = [];
}

$missingMoneygramLegacyCount = count($missingMoneygramLegacyBranches);
$missingMoneygramLegacyTransactionCount = array_sum(array_map(static function (array $row): int {
	return (int) ($row['transaction_count'] ?? 0);
}, $missingMoneygramLegacyBranches));

?>
<section class="dashboard-root" aria-label="Dashboard">
	<link rel="stylesheet" href="./components/dashboard.css">

<?php
	$userCreateError = $_SESSION['user_create_error'] ?? '';
	$userCreateSuccess = $_SESSION['user_create_success'] ?? '';
	unset($_SESSION['user_create_error'], $_SESSION['user_create_success']);
?>

<?php if ($userCreateError !== ''): ?>
	<div data-role="user-create-alert" style="margin:0.6rem 0;padding:0.6rem;background:#ffe6e6;border:1px solid #f5c2c2;border-radius:6px;color:#8b1e1e"><?= htmlspecialchars($userCreateError, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($userCreateSuccess !== ''): ?>
	<div data-role="user-create-alert" style="margin:0.6rem 0;padding:0.6rem;background:#ecfdf5;border:1px solid #bbf7d0;border-radius:6px;color:#065f46"><?= htmlspecialchars($userCreateSuccess, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

	<div class="dashboard-page-label">Dashboard</div>

	<div class="dashboard-grid dashboard-grid--legacy-only">
		<?php if ($isDashboardAdmin): ?>
		<div class="card legacy-ids-card<?= $missingMoneygramLegacyCount > 0 ? ' has-missing-legacy' : '' ?>">
			<h3>Legacy IDs</h3>
			<div class="card-body new-branches-summary">
				<?php if (!$missingLegacyLookupAvailable): ?>
					<div class="new-branches-state is-unavailable">
						<span class="material-icons" aria-hidden="true">cloud_off</span>
						<span>Legacy ID information is currently unavailable.</span>
					</div>
				<?php elseif ($missingMoneygramLegacyCount === 0): ?>
					<div class="new-branches-state is-clear">
						<span class="material-icons" aria-hidden="true">verified</span>
						<span>All detected partner branches have registered Legacy IDs.</span>
					</div>
				<?php else: ?>
					<div class="new-branches-metric legacy-ids-metric">
						<strong><?= number_format($missingMoneygramLegacyCount) ?></strong>
						<span>missing Legacy <?= $missingMoneygramLegacyCount === 1 ? 'ID' : 'IDs' ?></span>
					</div>
					<p><?= number_format($missingMoneygramLegacyTransactionCount) ?> affected <?= $missingMoneygramLegacyTransactionCount === 1 ? 'record' : 'records' ?></p>
					<button id="openMissingLegacyModal" type="button" class="new-branches-view-btn legacy-ids-view-btn">
						<span class="material-icons" aria-hidden="true">badge</span>
						View Legacy IDs
					</button>
				<?php endif; ?>
			</div>
		</div>
		<?php endif; ?>
	</div>

	<?php if ($isDashboardAdmin && $missingMoneygramLegacyCount > 0): ?>
		<div id="missingLegacyModal" class="unmapped-branches-modal" role="dialog" aria-modal="true" aria-labelledby="missingLegacyModalTitle" aria-hidden="true">
			<div class="unmapped-branches-modal__card">
				<div class="unmapped-branches-modal__head">
					<div>
						<h2 id="missingLegacyModalTitle">Missing Legacy IDs</h2>
						<!-- <p>These branches exist in master data but the Legacy ID for the listed corporate partner is blank.</p> -->
					</div>
					<button type="button" class="unmapped-branches-modal__close" aria-label="Close"><span class="material-icons" aria-hidden="true">close</span></button>
				</div>
				<div class="legacy-ids-table-container">
					<div class="legacy-ids-table-toolbar">
						<label class="legacy-ids-search">
							<span class="material-icons" aria-hidden="true">search</span>
							<input id="missingLegacySearch" type="search" placeholder="Search" autocomplete="off" aria-label="Search missing Legacy IDs">
						</label>
					</div>
					<div class="unmapped-branches-modal__table-wrap">
						<table class="unmapped-branches-table legacy-ids-table">
							<thead><tr><th>Branch ID</th><th>Branch Name</th><th>Corporate Partner</th><th>Detected Legacy ID</th><th>First Detected</th><th>Last Detected</th><th>Records</th></tr></thead>
							<tbody>
							<?php foreach ($missingMoneygramLegacyBranches as $branch): ?>
								<tr>
									<td><strong><?= htmlspecialchars((string) ($branch['branch_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></td>
									<td><?= htmlspecialchars((string) ($branch['branch_name'] ?: 'Unnamed branch'), ENT_QUOTES, 'UTF-8') ?></td>
									<td><?= htmlspecialchars((string) ($branch['partner_name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8') ?></td>
									<td><?= trim((string) ($branch['detected_legacy_id'] ?? '')) !== '' ? htmlspecialchars((string) $branch['detected_legacy_id'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
									<td><?= !empty($branch['first_detected']) ? htmlspecialchars(date('M j, Y g:i A', strtotime((string) $branch['first_detected'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
									<td><?= !empty($branch['last_detected']) ? htmlspecialchars(date('M j, Y g:i A', strtotime((string) $branch['last_detected'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
									<td><?= number_format((int) ($branch['transaction_count'] ?? 0)) ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<div class="legacy-ids-pagination" aria-label="Missing Legacy IDs pagination">
						<div class="legacy-ids-pagination__buttons">
							<button id="missingLegacyPrev" type="button" class="legacy-ids-page-btn" aria-label="Previous page">
								<span class="material-icons" aria-hidden="true">chevron_left</span>
							</button>
							<button id="missingLegacyNext" type="button" class="legacy-ids-page-btn" aria-label="Next page">
								<span class="material-icons" aria-hidden="true">chevron_right</span>
							</button>
						</div>
					</div>
				</div>
				<div class="unmapped-branches-modal__foot">
					<span>Register the corresponding corporate-partner Legacy ID to resolve these entries.</span>
					<button type="button" class="new-branches-view-btn unmapped-branches-modal__done">Done</button>
				</div>
			</div>
		</div>
	<?php endif; ?>

</section>

<script>
(function(){
	const modal = document.getElementById('missingLegacyModal');
	const openButton = document.getElementById('openMissingLegacyModal');
	if (!modal || !openButton) return;
	const closeButtons = modal.querySelectorAll('.unmapped-branches-modal__close, .unmapped-branches-modal__done');
	const searchInput = document.getElementById('missingLegacySearch');
	const prevButton = document.getElementById('missingLegacyPrev');
	const nextButton = document.getElementById('missingLegacyNext');
	const rows = Array.from(modal.querySelectorAll('.legacy-ids-table tbody tr'));
	const rowsPerPage = 5;
	let currentPage = 1;
	let filteredRows = rows;

	function updateLegacyTable(){
		const totalRows = filteredRows.length;
		const totalPages = Math.max(1, Math.ceil(totalRows / rowsPerPage));
		currentPage = Math.min(Math.max(currentPage, 1), totalPages);
		const start = (currentPage - 1) * rowsPerPage;
		const end = start + rowsPerPage;
		const visibleRows = new Set(filteredRows.slice(start, end));

		rows.forEach(row => {
			row.hidden = !visibleRows.has(row);
		});

		if (prevButton) prevButton.disabled = currentPage <= 1;
		if (nextButton) nextButton.disabled = currentPage >= totalPages;
	}

	function filterLegacyRows(){
		const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
		filteredRows = query === ''
			? rows
			: rows.filter(row => row.textContent.toLowerCase().includes(query));
		currentPage = 1;
		updateLegacyTable();
	}

	function openModal(){
		modal.classList.add('is-open');
		modal.setAttribute('aria-hidden', 'false');
		document.body.classList.add('has-dashboard-modal');
		filterLegacyRows();
		if (searchInput) searchInput.focus();
	}
	function closeModal(){
		modal.classList.remove('is-open');
		modal.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('has-dashboard-modal');
		openButton.focus();
	}

	openButton.addEventListener('click', openModal);
	closeButtons.forEach(button => button.addEventListener('click', closeModal));
	if (searchInput) searchInput.addEventListener('input', filterLegacyRows);
	if (prevButton) {
		prevButton.addEventListener('click', () => {
			currentPage -= 1;
			updateLegacyTable();
		});
	}
	if (nextButton) {
		nextButton.addEventListener('click', () => {
			currentPage += 1;
			updateLegacyTable();
		});
	}
	updateLegacyTable();
	modal.addEventListener('click', event => { if (event.target === modal) closeModal(); });
	document.addEventListener('keydown', event => {
		if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
	});
})();
</script>

<!-- 3D tilt interaction for dashboard cards (prefers-reduced-motion respected) -->
<script>
(function(){
	if (typeof window === 'undefined') return;
	if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

	const cards = document.querySelectorAll('.dashboard-grid .card');
	if (!cards || cards.length === 0) return;

	cards.forEach(card => {
		card.style.willChange = 'transform,box-shadow';
		function onMove(e){
			const r = card.getBoundingClientRect();
			const px = (e.clientX - r.left) / r.width;
			const py = (e.clientY - r.top) / r.height;
			const rotY = (px - 0.5) * 18; // left/right
			const rotX = (0.5 - py) * 10; // up/down
			const tz = 18;
			card.style.transform = `perspective(900px) translateZ(${tz}px) rotateX(${rotX}deg) rotateY(${rotY}deg)`;
			card.style.boxShadow = `${-rotY}px ${Math.abs(rotX)+8}px 30px rgba(10,20,30,0.12)`;
		}
		function onLeave(){
			card.style.transform = '';
			card.style.boxShadow = '';
		}
		card.addEventListener('mousemove', onMove);
		card.addEventListener('mouseleave', onLeave);
		card.addEventListener('blur', onLeave);
	});
})();
</script>
