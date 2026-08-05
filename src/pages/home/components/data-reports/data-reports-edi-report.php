<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../config/db.php';

$ediReportPartners = [];

try {
    $statement = masterDataConnection()->query(
        "SELECT DISTINCT partner_name
         FROM corpo_partner_masterfile
         WHERE partner_name IS NOT NULL AND partner_name <> ''
         ORDER BY partner_name"
    );

    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $partnerName) {
        $partnerName = trim((string) $partnerName);
        if ($partnerName !== '') {
            $ediReportPartners[] = $partnerName;
        }
    }
} catch (Throwable $exception) {
    $ediReportPartners = [];
}
?>

<section id="ediReportSection" class="edi-report-section" aria-label="EDI Report" style="display:none; padding:1rem">
    <h2 class="edi-report-title">EDI Report</h2>

    <form id="ediReportFilters" class="edi-report-filters" novalidate>
        <label class="edi-report-field edi-report-field--partner">
            <span>Corporate Partner</span>
            <div class="edi-report-autocomplete">
                <input
                    id="ediReportPartner"
                    name="partner"
                    type="text"
                    placeholder="Select corporate partner"
                    autocomplete="off"
                    aria-autocomplete="list"
                    aria-controls="ediReportPartnerSuggestions"
                    aria-expanded="false"
                    required
                >
                <ul id="ediReportPartnerSuggestions" role="listbox" hidden>
                    <?php foreach ($ediReportPartners as $partnerName): ?>
                        <li
                            role="option"
                            tabindex="-1"
                            data-value="<?= htmlspecialchars($partnerName, ENT_QUOTES, 'UTF-8') ?>"
                        ><?= htmlspecialchars($partnerName, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </label>

        <label class="edi-report-field edi-report-field--month">
            <span>Month</span>
            <input id="ediReportMonth" name="month" type="month" required>
        </label>

        <button id="ediReportGenerate" class="edi-report-generate" type="submit">Generate</button>
    </form>
</section>

<script>
(() => {
    const form = document.getElementById('ediReportFilters');
    const input = document.getElementById('ediReportPartner');
    const list = document.getElementById('ediReportPartnerSuggestions');
    if (!form || !input || !list) return;

    const options = Array.from(list.querySelectorAll('[role="option"]'));
    let activeIndex = -1;

    const visibleOptions = () => options.filter((option) => !option.hidden);

    const setActiveOption = (index) => {
        const visible = visibleOptions();
        visible.forEach((option) => option.classList.remove('is-active'));
        activeIndex = index < 0 || visible.length === 0
            ? -1
            : (index + visible.length) % visible.length;

        if (activeIndex >= 0) {
            visible[activeIndex].classList.add('is-active');
            visible[activeIndex].scrollIntoView({ block: 'nearest' });
        }
    };

    const openList = () => {
        list.hidden = visibleOptions().length === 0;
        input.setAttribute('aria-expanded', list.hidden ? 'false' : 'true');
    };

    const closeList = () => {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        setActiveOption(-1);
    };

    const filterOptions = () => {
        const query = input.value.trim().toLocaleLowerCase();
        options.forEach((option) => {
            option.hidden = query !== '' &&
                !option.dataset.value.toLocaleLowerCase().includes(query);
        });
        activeIndex = -1;
        openList();
    };

    const selectOption = (option) => {
        input.value = option.dataset.value;
        closeList();
        input.focus();
    };

    input.addEventListener('focus', filterOptions);
    input.addEventListener('input', filterOptions);
    input.addEventListener('keydown', (event) => {
        const visible = visibleOptions();
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            openList();
            setActiveOption(activeIndex + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            openList();
            setActiveOption(activeIndex - 1);
        } else if (event.key === 'Enter' && !list.hidden && activeIndex >= 0) {
            event.preventDefault();
            selectOption(visible[activeIndex]);
        } else if (event.key === 'Escape') {
            closeList();
        }
    });

    list.addEventListener('mousedown', (event) => {
        const option = event.target.closest('[role="option"]');
        if (!option) return;
        event.preventDefault();
        selectOption(option);
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.edi-report-autocomplete')) closeList();
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
    });
})();
</script>
