<section class="hero-section" aria-label="Hero">
    <style>
    /* Scoped hero badge and headline overrides to make the eyebrow badge visually dominant */
    .hero-section .hero__eyebrow{
        display: inline-flex;
        align-items: center;
        gap: 10px;
        color: #dc3545;
        background: rgba(220,53,69,0.06);
        border: 1px solid rgba(220,53,69,0.15);
        border-radius: 999px;
        font-weight: 700;
        letter-spacing: 1px;
        line-height: 1.2;
        /* mobile-first baseline */
        font-size: 18px;
        padding: 10px 18px;
        box-sizing: border-box;
    }

    .hero-section .hero__eyebrow svg{ flex: 0 0 auto; width: 16px; height: 16px; }

    .hero-section .hero__title{
        margin-top: 0.6rem;
        margin-bottom: 0.75rem;
        /* slightly reduced headline to keep badge visually dominant */
        font-size: 28px;
        line-height: 1.05;
        font-weight: 700;
    }

    /* Larger screens: make badge significantly larger than headline */
    @media (min-width: 768px){
        .hero-section .hero__eyebrow{ font-size: 34px; padding: 16px 28px; }
        .hero-section .hero__eyebrow svg{ width: 20px; height: 20px; }
        .hero-section .hero__title{ font-size: 30px; }
    }

    @media (min-width: 1100px){
        .hero-section .hero__eyebrow{ font-size: 36px; padding: 16px 32px; }
        .hero-section .hero__title{ font-size: 32px; }
    }

    /* Small screens: scale down headline while keeping badge prominent */
    @media (max-width: 480px){
        .hero-section .hero__eyebrow{ font-size: 16px; padding: 8px 12px; }
        .hero-section .hero__title{ font-size: 20px; }
    }
    </style>
    <div class="hero-section__inner">

        <div class="hero__content">
            <div class="hero__content-inner">

                <p class="hero__eyebrow">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="12" r="4" fill="#dc3545"/>
                        <circle cx="12" cy="12" r="9" stroke="#dc3545" stroke-width="2" opacity="0.3"/>
                    </svg>
                   Corporate Partners Reconciliation System
                </p>

                <h1 class="hero__title">
                    Automate reconciliation<br>
                    with <em>speed and confidence</em>
                </h1>

                <p class="hero__lead">
                    AutoRecon automatically compares your Office and Partner reconciliation files,
                    surfaces matches and discrepancies, removes manual checking, and helps teams act
                    faster with accurate results.
                </p>

                <div class="hero__ctas">
                    <button type="button" class="material-btn material-btn--primary" data-open-login>
                        Get Started
                    </button>
                </div>

                <ul class="hero__features" aria-label="Key features">
                    <li>
                        <span class="feature__icon" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M20 6L9 17l-5-5" stroke="#dc3545" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span class="feature__text">
                            <strong>Fast file comparison</strong>
                            <span class="feature__sub">Compare large files quickly</span>
                        </span>
                    </li>
                    <li>
                        <span class="feature__icon" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="11" cy="11" r="7" stroke="#dc3545" stroke-width="2"/>
                                <path d="M16.5 16.5L21 21" stroke="#dc3545" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <span class="feature__text">
                            <strong>Discrepancy detection</strong>
                            <span class="feature__sub">Pinpoint mismatches automatically</span>
                        </span>
                    </li>
                    <!-- 'Detailed audit trail' feature card removed per request -->
                </ul>

            </div>
        </div>

        <div class="hero__visual" role="img" aria-label="Illustration showing file reconciliation">
            <div class="hero__illustration">
                <svg width="420" height="300" viewBox="0 0 420 300" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <!-- Office file card -->
                    <rect x="14" y="24" width="172" height="116" rx="12" fill="#fff" stroke="#e2e8f0" stroke-width="1.5"/>
                    <rect x="26" y="40" width="80" height="8" rx="4" fill="#e2e8f0"/>
                    <rect x="26" y="56" width="120" height="6" rx="3" fill="#f1f5f9"/>
                    <rect x="26" y="68" width="100" height="6" rx="3" fill="#f1f5f9"/>
                    <rect x="26" y="80" width="110" height="6" rx="3" fill="#f1f5f9"/>
                    <rect x="26" y="92" width="90" height="6" rx="3" fill="#f1f5f9"/>
                    <text x="26" y="122" font-family="system-ui" font-size="10" fill="#94a3b8" font-weight="500"> KPX Web File</text>

                    <!-- Partner file card -->
                    <rect x="234" y="44" width="172" height="116" rx="12" fill="#fff" stroke="#e2e8f0" stroke-width="1.5"/>
                    <rect x="246" y="60" width="80" height="8" rx="4" fill="#e2e8f0"/>
                    <rect x="246" y="76" width="120" height="6" rx="3" fill="#f1f5f9"/>
                    <rect x="246" y="88" width="100" height="6" rx="3" fill="#f1f5f9"/>
                    <rect x="246" y="100" width="110" height="6" rx="3" fill="#f1f5f9"/>
                    <rect x="246" y="112" width="90" height="6" rx="3" fill="#f1f5f9"/>
                    <text x="246" y="142" font-family="system-ui" font-size="10" fill="#94a3b8" font-weight="500">Partner File</text>

                    <!-- Match lines -->
                    <line class="match-line match-line--ok" x1="186" y1="72" x2="234" y2="84" stroke="#22c55e" stroke-width="1.5" stroke-dasharray="4 3" opacity="0.7"/>
                    <line class="match-line match-line--ok" x1="186" y1="84" x2="234" y2="96" stroke="#22c55e" stroke-width="1.5" stroke-dasharray="4 3" opacity="0.7"/>
                    <line class="match-line match-line--warn" x1="186" y1="96" x2="234" y2="108" stroke="#dc3545" stroke-width="1.5" stroke-dasharray="4 3" opacity="0.8"/>

                    <!-- Center reconciliation badge -->
                    <circle cx="210" cy="188" r="52" fill="#fff1f2" stroke="rgba(220,53,69,0.15)" stroke-width="1.5"/>
                    <circle class="badge-ring--spin" cx="210" cy="188" r="52" fill="none" stroke="#dc3545" stroke-width="2" stroke-dasharray="24 16" stroke-linecap="round" opacity="0.55"/>
                    <circle cx="210" cy="188" r="38" fill="#fff" stroke="rgba(220,53,69,0.1)" stroke-width="1"/>
                    <!-- Checkmark -->
                    <path d="M196 188l10 10 18-20" stroke="#dc3545" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>

                    <!-- Result tags -->
                    <rect x="42" y="186" width="88" height="26" rx="13" fill="#dcfce7" />
                    <text id="matched-text" x="86" y="203" font-family="system-ui" font-size="10" fill="#16a34a" font-weight="600" text-anchor="middle">✓ 248 matched</text>

                    <rect x="290" y="186" width="88" height="26" rx="13" fill="#fee2e2"/>
                    <text id="mismatched-text" x="334" y="203" font-family="system-ui" font-size="10" fill="#dc3545" font-weight="600" text-anchor="middle">✗ 3 mismatched</text>

                    <!-- Connecting lines to badge -->
                    <line x1="130" y1="186" x2="170" y2="188" stroke="#cbd5e1" stroke-width="1.5"/>
                    <line x1="250" y1="188" x2="290" y2="188" stroke="#cbd5e1" stroke-width="1.5"/>

                    <!-- Progress bar -->
                    <rect x="42" y="252" width="336" height="6" rx="3" fill="#f1f5f9"/>
                    <rect x="42" y="252" width="310" height="6" rx="3" fill="url(#progressGrad)" id="recon-fill"/>
                    <text id="recon-text" x="42" y="274" font-family="system-ui" font-size="9" fill="#94a3b8">98.8% reconciliation rate</text>

                    <defs>
                        <linearGradient id="progressGrad" x1="0" y1="0" x2="1" y2="0">
                            <stop offset="0%" stop-color="#dc3545"/>
                            <stop offset="100%" stop-color="#f87171"/>
                        </linearGradient>
                    </defs>
                </svg>
            </div>
        </div>

    </div>
</section>

<script>
(function(){
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    function rand(min,max){ return Math.floor(Math.random()*(max-min+1))+min; }

    function animateValue(el, start, end, duration) {
        const startTime = performance.now();
        function frame(now){
            const t = Math.min(1, (now - startTime) / duration);
            const value = Math.round(start + (end - start) * t);
            el.textContent = (el.dataset.prefix||'') + value + (el.dataset.suffix||'');
            if (t < 1) requestAnimationFrame(frame);
        }
        requestAnimationFrame(frame);
    }

    function animateNumber(el, start, end, duration, decimals) {
        const startTime = performance.now();
        function frame(now){
            const t = Math.min(1, (now - startTime) / duration);
            const value = start + (end - start) * t;
            el.textContent = (el.dataset.prefix||'') + value.toFixed(decimals) + (el.dataset.suffix||'');
            if (t < 1) requestAnimationFrame(frame);
        }
        requestAnimationFrame(frame);
    }

    function animateRectWidth(el, startW, endW, duration) {
        const startTime = performance.now();
        function frame(now){
            const t = Math.min(1, (now - startTime) / duration);
            const w = startW + (endW - startW) * t;
            el.setAttribute('width', String(w));
            if (t < 1) requestAnimationFrame(frame);
        }
        requestAnimationFrame(frame);
    }

    const matchedEl = document.getElementById('matched-text');
    const mismatchedEl = document.getElementById('mismatched-text');
    const reconFill = document.getElementById('recon-fill');
    const reconText = document.getElementById('recon-text');
    if (!matchedEl || !mismatchedEl) return;

    // store prefix/suffix so we only update the number
    matchedEl.dataset.prefix = '✓ ';
    matchedEl.dataset.suffix = ' matched';
    mismatchedEl.dataset.prefix = '✗ ';
    mismatchedEl.dataset.suffix = ' mismatched';

    matchedEl.dataset.current = parseInt((matchedEl.textContent||'').replace(/[^0-9]/g,''),10) || 0;
    mismatchedEl.dataset.current = parseInt((mismatchedEl.textContent||'').replace(/[^0-9]/g,''),10) || 0;
    if (reconText) {
        // parse existing percent number from text like "98.8%"
        const pct = parseFloat((reconText.textContent||'').replace(/[^0-9\.]/g,''));
        reconText.dataset.current = isNaN(pct) ? 0 : pct;
    }

    function scheduleUpdate(){
        const nextMatched = rand(220, 260);
        const nextFlagged = rand(0, 9);
        const curMatched = parseInt(matchedEl.dataset.current,10) || 0;
        const curFlagged = parseInt(mismatchedEl.dataset.current,10) || 0;
        animateValue(matchedEl, curMatched, nextMatched, 800);
        animateValue(mismatchedEl, curFlagged, nextFlagged, 700);
        // animate reconciliation percent and progress bar
        if (reconText && reconFill) {
            const maxW = 336; // background width
            const curW = parseFloat(reconFill.getAttribute('width')) || 0;
            const curPct = (curW / maxW) * 100;
            const nextPct = Math.max(0, Math.min(100, Math.round((nextMatched / (nextMatched + Math.max(1,nextFlagged))) * 1000) / 10));
            // set prefixes/suffix for text
            reconText.dataset.prefix = '';
            reconText.dataset.suffix = '% reconciliation rate';
            animateNumber(reconText, curPct, nextPct, 900, 1);
            const targetW = (nextPct / 100) * maxW;
            animateRectWidth(reconFill, curW, targetW, 900);
            reconText.dataset.current = nextPct;
        }
        matchedEl.dataset.current = nextMatched;
        mismatchedEl.dataset.current = nextFlagged;
        // next update in 2.5-4s
        setTimeout(scheduleUpdate, 2500 + Math.random()*1500);
    }

    setTimeout(scheduleUpdate, 600);
})();
</script>