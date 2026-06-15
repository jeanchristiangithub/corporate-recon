<?php
// dashboard fragment: basic account banner and two overview cards
require_once __DIR__ . '/../../../controllers/usercontroller.php';

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
	</div>

	<div class="quote-card" aria-label="Motivational quote">
		<h3>Motivational Quotes</h3>
		<p><?= htmlspecialchars($quoteOfTheDay, ENT_QUOTES, 'UTF-8') ?></p>
	</div>

</section>
<?php include __DIR__ . '/../../../modals/user/add-user-modal.php'; ?>

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
