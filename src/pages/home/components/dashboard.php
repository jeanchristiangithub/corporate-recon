<?php
// dashboard fragment: basic account banner and two overview cards
require_once __DIR__ . '/../../../controllers/usercontroller.php';
require_once __DIR__ . '/../../../config/db.php';

$isDashboardAdmin = isset($_SESSION['user']['role'])
	&& strcasecmp((string) $_SESSION['user']['role'], 'Admin') === 0;

$lastLoginDisplay = '—';
try {
	$sessionLastLogin = (string) ($_SESSION['last_login_at'] ?? '');
	if ($sessionLastLogin !== '') {
		$ts = strtotime($sessionLastLogin);
		if ($ts !== false) {
			$lastLoginDisplay = date('F j, Y h:i:s A', $ts);
		}
	}

	$id = (string) ($_SESSION['user']['id_number'] ?? '');
	if ($lastLoginDisplay === '—' && $id !== '') {
		$uc = new UserController();
		$latest = $uc->latestUserLogByIdNumber($id);
		if ($latest && !empty($latest['datemodified'])) {
			$ts = strtotime($latest['datemodified']);
			if ($ts !== false) {
				$lastLoginDisplay = date('F j, Y h:i:s A', $ts);
			}
		}
	}
} catch (Throwable $e) {
	// fallback: keep placeholder
}

$unmappedBranches = [];
$unmappedBranchLookupAvailable = true;
try {
	$filePdo = fileRecDbConnection();
	$webDataColumns = $filePdo->query('SHOW COLUMNS FROM ml_web_data')->fetchAll(PDO::FETCH_COLUMN);
	$webDataColumns = array_map('strtolower', array_map('strval', $webDataColumns));

	if (!in_array('branch_id', $webDataColumns, true)) {
		$unmappedBranchLookupAvailable = false;
	} else {
		$partnerColumn = in_array('partnername', $webDataColumns, true) ? '`partnerName`' : "''";
		$createdColumn = in_array('created_at', $webDataColumns, true) ? '`created_at`' : 'NULL';
		$sql = 'SELECT TRIM(`branch_id`) AS branch_id, ' . $partnerColumn . ' AS partner_name, '
			. 'MIN(' . $createdColumn . ') AS first_detected, MAX(' . $createdColumn . ') AS last_detected, COUNT(*) AS transaction_count '
			. 'FROM `ml_web_data` WHERE `branch_id` IS NOT NULL AND TRIM(`branch_id`) <> \'\' '
			. 'GROUP BY TRIM(`branch_id`), ' . $partnerColumn . ' ORDER BY MAX(' . $createdColumn . ') DESC';
		$candidates = $filePdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

		// MoneyGram's "new branch" warning is raised from Partner Data. Include those
		// records even when a matching KPX Web Data row has not been uploaded yet.
		try {
			$moneygramColumns = $filePdo->query('SHOW COLUMNS FROM moneygram_partner_data')->fetchAll(PDO::FETCH_COLUMN);
			$moneygramColumns = array_map('strtolower', array_map('strval', $moneygramColumns));
			if (in_array('branch_id', $moneygramColumns, true)) {
				$moneygramCreatedColumn = in_array('created_at', $moneygramColumns, true) ? '`created_at`' : 'NULL';
				$moneygramSql = "SELECT TRIM(`branch_id`) AS branch_id, 'MONEYGRAM' AS partner_name, "
					. 'MIN(' . $moneygramCreatedColumn . ') AS first_detected, MAX(' . $moneygramCreatedColumn . ') AS last_detected, COUNT(*) AS transaction_count '
					. 'FROM `moneygram_partner_data` WHERE `branch_id` IS NOT NULL AND TRIM(`branch_id`) <> \'\' '
					. 'GROUP BY TRIM(`branch_id`) ORDER BY MAX(' . $moneygramCreatedColumn . ') DESC';
				$candidates = array_merge($candidates, $filePdo->query($moneygramSql)->fetchAll(PDO::FETCH_ASSOC));
			}
		} catch (Throwable $ignored) {
			// The consolidated KPX source remains usable when MoneyGram data is unavailable.
		}

		$mergedCandidates = [];
		foreach ($candidates as $candidate) {
			$branchId = trim((string) ($candidate['branch_id'] ?? ''));
			$partnerName = trim((string) ($candidate['partner_name'] ?? ''));
			$key = $branchId . '|' . strtoupper($partnerName);
			if ($branchId === '') continue;
			if (!isset($mergedCandidates[$key])) {
				$mergedCandidates[$key] = $candidate;
				continue;
			}
			$existing = $mergedCandidates[$key];
			$firstDates = array_filter([$existing['first_detected'] ?? null, $candidate['first_detected'] ?? null]);
			$lastDates = array_filter([$existing['last_detected'] ?? null, $candidate['last_detected'] ?? null]);
			$existing['first_detected'] = $firstDates !== [] ? min($firstDates) : null;
			$existing['last_detected'] = $lastDates !== [] ? max($lastDates) : null;
			$existing['transaction_count'] = (int) ($existing['transaction_count'] ?? 0) + (int) ($candidate['transaction_count'] ?? 0);
			$mergedCandidates[$key] = $existing;
		}
		$candidates = array_values($mergedCandidates);

		$knownBranchIds = [];
		$uniqueIds = array_values(array_unique(array_filter(array_map(static function (array $row): string {
			return trim((string) ($row['branch_id'] ?? ''));
		}, $candidates))));

		if ($uniqueIds !== []) {
			$masterPdo = masterDataConnection();
			foreach (array_chunk($uniqueIds, 500) as $idChunk) {
				$placeholders = implode(',', array_fill(0, count($idChunk), '?'));
				$stmt = $masterPdo->prepare("SELECT TRIM(branch_id) FROM branch_profile WHERE TRIM(branch_id) IN ($placeholders)");
				$stmt->execute($idChunk);
				foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $knownId) {
					$knownBranchIds[trim((string) $knownId)] = true;
				}
			}
		}

		foreach ($candidates as $candidate) {
			$branchId = trim((string) ($candidate['branch_id'] ?? ''));
			if ($branchId !== '' && !isset($knownBranchIds[$branchId])) {
				$unmappedBranches[] = $candidate;
			}
		}
	}
} catch (Throwable $e) {
	$unmappedBranchLookupAvailable = false;
	$unmappedBranches = [];
}

$unmappedBranchIds = array_values(array_unique(array_map(static function (array $row): string {
	return trim((string) ($row['branch_id'] ?? ''));
}, $unmappedBranches)));
$unmappedBranchCount = count($unmappedBranchIds);
$unmappedTransactionCount = array_sum(array_map(static function (array $row): int {
	return (int) ($row['transaction_count'] ?? 0);
}, $unmappedBranches));

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
			// A genuinely new branch belongs on the New Branch IDs card, not here.
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

$motivationalQuotes = [
	'Small progress every day adds up to meaningful results.',
	'Accuracy first, speed follows.',
	'Stay focused on the next clear step.',
	'Great work is built one careful check at a time.',
	'Consistency turns difficult work into reliable output.',
	'Make today cleaner, clearer, and better than yesterday.',
	'Every resolved mismatch is progress the team can trust.',
	'Discipline in the details creates confidence in the results.',
	'Clear records today prevent confusion tomorrow.',
	'One accurate entry can save hours of rework.',
	'Strong teams rely on careful hands and steady minds.',
	'Review the details, then move forward with confidence.',
	'Good results come from patient, consistent effort.',
	'Each completed task strengthens the whole process.',
	'Focus on quality, and the numbers will tell the story.',
	'Reliable work starts with one thoughtful decision.',
	'Progress is built by finishing what matters most.',
	'Every careful check protects the integrity of the work.',
	'Do the simple things well, and the complex work becomes easier.',
	'Trust grows when every result can be explained clearly.',
	'Steady attention turns busy work into dependable results.',
	'Correct the small gaps before they become bigger questions.',
	'Clean data today gives the team better decisions tomorrow.',
	'The best progress is progress you can verify.',
	'Careful work creates fewer surprises and stronger outcomes.',
	'Each accurate reconciliation brings the bigger picture into focus.',
	'Take the time to get it right; the record will carry the proof.',
	'Reliable output starts with honest review and steady follow-through.',
	'Keep the process clear, and the results will be easier to trust.',
	'One focused review can prevent many repeated corrections.',
	'Precision is a habit built through consistent attention.',
	'Finish each check with the same care you started with.',
];
$quoteOfTheDay = $motivationalQuotes[array_rand($motivationalQuotes)];
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

	<div class="dashboard-banner" style="position:relative;overflow:visible">
		<div class="welcome">Welcome back, <span class="user-name"><?= htmlspecialchars(strtoupper((string) ($_SESSION['user']['firstname'] ?? 'User')), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars(strtoupper((string) ($_SESSION['user']['lastname'] ?? '')) , ENT_QUOTES, 'UTF-8') ?>!</span></div>
		<div class="user-meta">
			<div class="user-id"><?= htmlspecialchars((string) ($_SESSION['user']['employee_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
				<div class="avatar" aria-hidden="true"><?= substr(htmlspecialchars((string) ($_SESSION['user']['firstname'] ?? 'U'), ENT_QUOTES, 'UTF-8'),0,1) ?></div>

				<?php if (isset($_SESSION['user']['role']) && strcasecmp((string)$_SESSION['user']['role'], 'Admin') === 0): ?>
					<button id="openAddUserModal" class="material-btn add-user-btn" title="Add user">Add User</button>
				<?php endif; ?>
		</div>
	</div>

	<div class="dashboard-grid">
		<div class="card">
			<h3>Account Information</h3>
			<div class="card-body">
				<dl class="account-dl">
					<dt>Full Name:</dt>
					<dd><?= htmlspecialchars((string) (trim(($_SESSION['user']['firstname'] ?? '').' '.($_SESSION['user']['lastname'] ?? ''))), ENT_QUOTES, 'UTF-8') ?></dd>

					<dt>Access Level:</dt>
					<dd><span class="role"><?= htmlspecialchars((string) ($_SESSION['user']['role'] ?? 'Public'), ENT_QUOTES, 'UTF-8') ?></span></dd>


					<dt>Status:</dt>
					<dd><span class="status"><span class="dot" aria-hidden="true"></span><span class="lbl">Online</span></span></dd>
				</dl>
			</div>
		</div>

		<div class="card">
			<h3>System Overview</h3>
			<div class="card-body">
				<div class="sys-line">Last Login: <?= htmlspecialchars($lastLoginDisplay, ENT_QUOTES, 'UTF-8') ?></div>
			</div>
		</div>

		<?php if ($isDashboardAdmin): ?>
		<div class="card new-branches-card<?= $unmappedBranchCount > 0 ? ' has-unmapped-branches' : '' ?>">
			<h3>New Branch IDs</h3>
			<div class="card-body new-branches-summary">
				<?php if (!$unmappedBranchLookupAvailable): ?>
					<div class="new-branches-state is-unavailable">
						<span class="material-icons" aria-hidden="true">cloud_off</span>
						<span>Branch information is currently unavailable.</span>
					</div>
				<?php elseif ($unmappedBranchCount === 0): ?>
					<div class="new-branches-state is-clear">
						<span class="material-icons" aria-hidden="true">verified</span>
						<span>All detected branch IDs are in master data.</span>
					</div>
				<?php else: ?>
					<div class="new-branches-metric">
						<strong><?= number_format($unmappedBranchCount) ?></strong>
						<span>unmapped <?= $unmappedBranchCount === 1 ? 'branch' : 'branches' ?></span>
					</div>
					<p><?= number_format($unmappedTransactionCount) ?> affected <?= $unmappedTransactionCount === 1 ? 'transaction' : 'transactions' ?></p>
					<button id="openUnmappedBranchesModal" type="button" class="new-branches-view-btn">
						<span class="material-icons" aria-hidden="true">account_tree</span>
						View branches
					</button>
				<?php endif; ?>
			</div>
		</div>

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

	<?php if ($isDashboardAdmin && $unmappedBranchCount > 0): ?>
		<div id="unmappedBranchesModal" class="unmapped-branches-modal" role="dialog" aria-modal="true" aria-labelledby="unmappedBranchesModalTitle" aria-hidden="true">
			<div class="unmapped-branches-modal__card">
				<div class="unmapped-branches-modal__head">
					<div>
						<h2 id="unmappedBranchesModalTitle">New Branch IDs</h2>
						<p>These IDs were detected in uploaded KPX or Partner Data but are not yet in the branch master data.</p>
					</div>
					<button type="button" class="unmapped-branches-modal__close" aria-label="Close"><span class="material-icons" aria-hidden="true">close</span></button>
				</div>
				<div class="unmapped-branches-modal__table-wrap">
					<table class="unmapped-branches-table">
						<thead><tr><th>Branch ID</th><th>Partner</th><th>First Detected</th><th>Last Detected</th><th>Transactions</th></tr></thead>
						<tbody>
						<?php foreach ($unmappedBranches as $branch): ?>
							<tr>
								<td><strong><?= htmlspecialchars((string) ($branch['branch_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></td>
								<td><?= htmlspecialchars((string) ($branch['partner_name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8') ?></td>
								<td><?= !empty($branch['first_detected']) ? htmlspecialchars(date('M j, Y g:i A', strtotime((string) $branch['first_detected'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
								<td><?= !empty($branch['last_detected']) ? htmlspecialchars(date('M j, Y g:i A', strtotime((string) $branch['last_detected'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
								<td><?= number_format((int) ($branch['transaction_count'] ?? 0)) ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div class="unmapped-branches-modal__foot">
					<span>Add these branch IDs to <code>branch_profile</code> to resolve them.</span>
					<div class="unmapped-branches-modal__actions">
						<button id="exportUnmappedBranches" type="button" class="new-branches-view-btn unmapped-branches-export-btn">
							<span class="material-icons" aria-hidden="true">file_download</span>
							Export
						</button>
						<button type="button" class="new-branches-view-btn unmapped-branches-modal__done">Done</button>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<?php if ($isDashboardAdmin && $missingMoneygramLegacyCount > 0): ?>
		<div id="missingLegacyModal" class="unmapped-branches-modal" role="dialog" aria-modal="true" aria-labelledby="missingLegacyModalTitle" aria-hidden="true">
			<div class="unmapped-branches-modal__card">
				<div class="unmapped-branches-modal__head">
					<div>
						<h2 id="missingLegacyModalTitle">Missing Legacy IDs</h2>
						<p>These branches exist in master data but the Legacy ID for the listed corporate partner is blank.</p>
					</div>
					<button type="button" class="unmapped-branches-modal__close" aria-label="Close"><span class="material-icons" aria-hidden="true">close</span></button>
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
				<div class="unmapped-branches-modal__foot">
					<span>Register the corresponding corporate-partner Legacy ID to resolve these entries.</span>
					<button type="button" class="new-branches-view-btn unmapped-branches-modal__done">Done</button>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<div class="quote-card" aria-label="Motivational quote">
		<h3>Motivational Quotes</h3>
		<p><?= htmlspecialchars($quoteOfTheDay, ENT_QUOTES, 'UTF-8') ?></p>
	</div>

</section>
<?php include __DIR__ . '/../../../modals/user/add-user-modal.php'; ?>

<script>
(function(){
	const modal = document.getElementById('unmappedBranchesModal');
	const openButton = document.getElementById('openUnmappedBranchesModal');
	if (!modal || !openButton) return;
	const closeButtons = modal.querySelectorAll('.unmapped-branches-modal__close, .unmapped-branches-modal__done');
	const exportButton = document.getElementById('exportUnmappedBranches');

	function openModal(){
		modal.classList.add('is-open');
		modal.setAttribute('aria-hidden', 'false');
		document.body.classList.add('has-dashboard-modal');
		const closeButton = modal.querySelector('.unmapped-branches-modal__close');
		if (closeButton) closeButton.focus();
	}
	function closeModal(){
		modal.classList.remove('is-open');
		modal.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('has-dashboard-modal');
		openButton.focus();
	}

	openButton.addEventListener('click', openModal);
	closeButtons.forEach(button => button.addEventListener('click', closeModal));
	if (exportButton) {
		exportButton.addEventListener('click', async function(){
			const table = modal.querySelector('.unmapped-branches-table');
			if (!table) return;
			const rows = Array.from(table.querySelectorAll('tbody tr')).map(function(row){
				const cells = row.querySelectorAll('td');
				return {
					branch_id: String(cells[0] ? cells[0].textContent : '').trim(),
					partner: String(cells[1] ? cells[1].textContent : '').trim(),
					first_detected: String(cells[2] ? cells[2].textContent : '').trim(),
					last_detected: String(cells[3] ? cells[3].textContent : '').trim(),
					transactions: parseInt(String(cells[4] ? cells[4].textContent : '0').replace(/,/g, ''), 10) || 0
				};
			});
			const originalHtml = exportButton.innerHTML;
			exportButton.disabled = true;
			exportButton.innerHTML = '<span class="material-icons" aria-hidden="true">hourglass_top</span>Exporting...';
			try {
				const response = await fetch(window.autoreconBaseUrl + '/src/controllers/excelcontrol/unmapped-branches-export.php', {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ rows: rows })
				});
				if (!response.ok) {
					let message = 'Failed to export the Excel file.';
					try { const error = await response.json(); if (error && error.error) message = error.error; } catch (e) {}
					throw new Error(message);
				}
				const blob = await response.blob();
				const url = URL.createObjectURL(blob);
				const link = document.createElement('a');
				link.href = url;
				link.download = 'new-branch-ids-' + new Date().toISOString().slice(0, 10) + '.xlsx';
				document.body.appendChild(link);
				link.click();
				link.remove();
				setTimeout(function(){ URL.revokeObjectURL(url); }, 0);
			} catch (error) {
				const message = error && error.message ? error.message : 'Failed to export the Excel file.';
				if (window.Swal && typeof window.Swal.fire === 'function') {
					window.Swal.fire({ icon: 'error', title: 'Export Failed', text: message, confirmButtonColor: '#dc3545' });
				} else {
					window.alert(message);
				}
			} finally {
				exportButton.disabled = false;
				exportButton.innerHTML = originalHtml;
			}
		});
	}
	modal.addEventListener('click', event => { if (event.target === modal) closeModal(); });
	document.addEventListener('keydown', event => {
		if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
	});
})();
</script>

<script>
(function(){
	const modal = document.getElementById('missingLegacyModal');
	const openButton = document.getElementById('openMissingLegacyModal');
	if (!modal || !openButton) return;
	const closeButtons = modal.querySelectorAll('.unmapped-branches-modal__close, .unmapped-branches-modal__done');

	function openModal(){
		modal.classList.add('is-open');
		modal.setAttribute('aria-hidden', 'false');
		document.body.classList.add('has-dashboard-modal');
		const closeButton = modal.querySelector('.unmapped-branches-modal__close');
		if (closeButton) closeButton.focus();
	}
	function closeModal(){
		modal.classList.remove('is-open');
		modal.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('has-dashboard-modal');
		openButton.focus();
	}

	openButton.addEventListener('click', openModal);
	closeButtons.forEach(button => button.addEventListener('click', closeModal));
	modal.addEventListener('click', event => { if (event.target === modal) closeModal(); });
	document.addEventListener('keydown', event => {
		if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
	});
})();
</script>

<script>
(function(){
	if (typeof window === 'undefined') return;
	if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

	function rand(min,max){ return Math.floor(Math.random()*(max-min+1))+min }

	function launchConfetti(container, count){
		const colors = ['#dc3545','#f87171','#fc9aa0','#ffd166','#34d399','#60a5fa'];
		for(let i=0;i<count;i++){
			const el = document.createElement('div');
			el.className = 'confetti-piece';
			const left = Math.random()*100;
			el.style.left = left + '%';
			el.style.background = colors[rand(0,colors.length-1)];
			const duration = (Math.random()*1.2 + 0.9).toFixed(2) + 's';
			const delay = (Math.random()*0.6).toFixed(2) + 's';
			el.style.animation = `confettiFall ${duration} cubic-bezier(.2,.6,.4,1) ${delay} forwards`;
			// small horizontal drift using transform translateX via CSS variable
			el.style.transform = `translateY(0)`;
			container.appendChild(el);
			// remove when done
			setTimeout(()=>{ try{ container.removeChild(el) }catch(e){} }, (parseFloat(duration)+parseFloat(delay))*1000 + 200);
		}
	}

	// Confetti removed
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
