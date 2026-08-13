<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../config/db.php';

$ediReportPartners = [];
$ediReportBranchFilters = [
    'mainzone' => [],
    'zone' => [],
    'region' => [],
    'ml_matic_status' => [],
    'branch_name' => [],
];

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

    foreach (array_keys($ediReportBranchFilters) as $column) {
        $excludeHeadOffice = in_array($column, ['mainzone', 'zone'], true)
            ? " AND UPPER(TRIM(`$column`)) <> 'HO'"
            : '';
        $branchStatement = masterDataConnection()->query(
            "SELECT DISTINCT `$column`
             FROM branch_profile
             WHERE `$column` IS NOT NULL AND TRIM(`$column`) <> ''$excludeHeadOffice
             ORDER BY `$column`"
        );
        $ediReportBranchFilters[$column] = array_values(array_filter(
            array_map(
                static fn($value): string => trim((string) $value),
                $branchStatement->fetchAll(PDO::FETCH_COLUMN)
            ),
            static fn(string $value): bool => $value !== ''
        ));
    }
} catch (Throwable $exception) {
    $ediReportPartners = [];
    $ediReportBranchFilters = array_fill_keys(array_keys($ediReportBranchFilters), []);
}
?>

<section id="ediReportSection" class="edi-report-section" aria-label="EDI Report" style="display:none; padding:1rem">
    <h2 class="edi-report-title">EDI Report</h2>

    <form id="ediReportFilters" class="edi-report-filters">
        <label class="edi-report-field edi-report-field--partner">
            <span>Corporate Partner <i class="edi-report-required" aria-hidden="true">*</i></span>
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
                <button
                    id="ediReportPartnerClear"
                    class="edi-report-autocomplete-clear"
                    type="button"
                    aria-label="Clear selected corporate partner"
                    title="Clear selected corporate partner"
                    hidden
                >&times;</button>
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
            <span>Month <i class="edi-report-required" aria-hidden="true">*</i></span>
            <input id="ediReportMonth" name="month" type="month" required>
        </label>

        <?php
        $ediReportSelectFields = [
            'mainzone' => ['Mainzone', 'Mainzone'],
            'zone' => ['Zone', 'Zone'],
            'region' => ['Region', 'Region'],
            'ml_matic_status' => ['Branch Status', 'BranchStatus'],
        ];
        ?>
        <?php foreach ($ediReportSelectFields as $fieldName => [$fieldLabel, $fieldIdSuffix]): ?>
            <label class="edi-report-field edi-report-field--select">
                <span>
                    <?= htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8') ?>
                    <?php if ($fieldName === 'ml_matic_status'): ?>
                        <i class="edi-report-required" aria-hidden="true">*</i>
                    <?php endif; ?>
                </span>
                <select
                    id="ediReport<?= $fieldIdSuffix ?>"
                    name="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>"
                    <?= $fieldName === 'ml_matic_status' ? 'required' : '' ?>
                >
                    <option value="">Select a <?= htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php foreach ($ediReportBranchFilters[$fieldName] as $fieldValue): ?>
                        <?php if ($fieldName === 'region'): ?>
                            <?php continue; ?>
                        <?php endif; ?>
                        <option value="<?= htmlspecialchars($fieldValue, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($fieldValue, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if ($fieldName === 'zone'): ?>
                        <option value="Showroom">SHOWROOM</option>
                    <?php endif; ?>
                </select>
            </label>
        <?php endforeach; ?>

        <label class="edi-report-field edi-report-field--branch">
            <span>Branch Name</span>
            <div class="edi-report-autocomplete">
                <input
                    id="ediReportBranchName"
                    name="branch_name"
                    type="text"
                    placeholder="Select branch name"
                    autocomplete="off"
                    aria-autocomplete="list"
                    aria-controls="ediReportBranchNameSuggestions"
                    aria-expanded="false"
                >
                <input id="ediReportBranchId" name="branch_id" type="hidden" value="">
                <button
                    id="ediReportBranchNameClear"
                    class="edi-report-autocomplete-clear"
                    type="button"
                    aria-label="Clear selected branch"
                    title="Clear selected branch"
                    hidden
                >&times;</button>
                <ul id="ediReportBranchNameSuggestions" role="listbox" hidden>
                </ul>
            </div>
        </label>

        <button id="ediReportGenerate" class="edi-report-generate" type="submit">Generate</button>
        <button id="ediReportExportExcel" class="edi-report-export" type="button" disabled>Export to Excel</button>
    </form>

    <section id="ediReportMoneygramTableCard" class="edi-report-table-card" aria-label="MoneyGram EDI report results" hidden>
        <div class="edi-report-table-wrap">
            <table class="edi-report-table">
            <thead>
                <tr>
                    <th rowspan="4" scope="col">Branch ID</th>
                    <th rowspan="4" scope="col">Code</th>
                    <th rowspan="4" scope="col">Branch Name</th>
                    <th rowspan="4" scope="col">Region Description</th>
                    <th colspan="16" scope="colgroup">MoneyGram</th>
                    <th rowspan="4" scope="col">Branch Status</th>
                </tr>
                <tr>
                    <th colspan="8" scope="colgroup">Payout</th>
                    <th colspan="8" scope="colgroup">Sendout</th>
                </tr>
                <tr>
                    <th colspan="4" scope="colgroup">PHP</th>
                    <th colspan="4" scope="colgroup">USD</th>
                    <th colspan="4" scope="colgroup">PHP</th>
                    <th colspan="4" scope="colgroup">USD</th>
                </tr>
                <tr>
                    <?php for ($ediHeaderGroup = 0; $ediHeaderGroup < 4; $ediHeaderGroup++): ?>
                        <th scope="col">Count</th>
                        <th scope="col">Principal</th>
                        <th scope="col">Charge</th>
                        <th scope="col">FX Share</th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody id="ediReportTableBody">
                <tr class="edi-report-empty-row">
                    <?php for ($ediColumn = 0; $ediColumn < 21; $ediColumn++): ?>
                        <td>&nbsp;</td>
                    <?php endfor; ?>
                </tr>
            </tbody>
            </table>
        </div>
    </section>
</section>

<script>
(() => {
    const form = document.getElementById('ediReportFilters');
    if (!form) return;
    const regionOptionsEndpoint = <?= json_encode(
        (string) ($appBaseUrl ?? '') . '/src/controllers/masterdata/edi-region-options.php',
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
    const branchOptionsEndpoint = <?= json_encode(
        (string) ($appBaseUrl ?? '') . '/src/controllers/masterdata/edi-branch-options.php',
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
    const reportResultsEndpoint = <?= json_encode(
        (string) ($appBaseUrl ?? '') . '/src/controllers/data-reports/edi-report-results.php',
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
    const reportExportEndpoint = <?= json_encode(
        (string) ($appBaseUrl ?? '') . '/src/controllers/data-reports/edi-report-export.php',
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
    const exportButton = document.getElementById('ediReportExportExcel');
    let latestReportRows = [];

    const setupAutocomplete = (inputId, listId, clearButtonId = '', submittedValueInputId = '') => {
        const input = document.getElementById(inputId);
        const list = document.getElementById(listId);
        const clearButton = clearButtonId ? document.getElementById(clearButtonId) : null;
        const submittedValueInput = submittedValueInputId
            ? document.getElementById(submittedValueInputId)
            : null;
        if (!input || !list) return;

        const allOptions = () => Array.from(list.querySelectorAll('[role="option"]'));
        let activeIndex = -1;
        const visibleOptions = () => allOptions().filter((option) => !option.hidden);
        const updateClearButton = () => {
            if (clearButton) {
                clearButton.hidden = input.value.trim() === '' ||
                    input.value.trim().toLocaleLowerCase() === 'all';
            }
        };

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
            allOptions().forEach((option) => {
                option.hidden = query !== '' &&
                    !option.dataset.value.toLocaleLowerCase().includes(query);
            });
            activeIndex = -1;
            openList();
        };
        const selectOption = (option) => {
            input.value = option.dataset.value;
            if (submittedValueInput) {
                submittedValueInput.value = option.dataset.submitValue || '';
            }
            updateClearButton();
            closeList();
            input.focus();
            input.dispatchEvent(new Event('change', { bubbles: true }));
        };

        input.addEventListener('focus', () => {
            allOptions().forEach((option) => { option.hidden = false; });
            activeIndex = -1;
            openList();
            input.select();
        });
        input.addEventListener('input', () => {
            if (submittedValueInput) submittedValueInput.value = '';
            updateClearButton();
            filterOptions();
        });
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
        clearButton?.addEventListener('click', () => {
            input.value = '';
            if (submittedValueInput) submittedValueInput.value = '';
            updateClearButton();
            input.focus();
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
        document.addEventListener('click', (event) => {
            if (!event.target.closest(`#${inputId}`) && !event.target.closest(`#${listId}`)) {
                closeList();
            }
        });
    };

    setupAutocomplete(
        'ediReportPartner',
        'ediReportPartnerSuggestions',
        'ediReportPartnerClear'
    );
    setupAutocomplete(
        'ediReportBranchName',
        'ediReportBranchNameSuggestions',
        'ediReportBranchNameClear',
        'ediReportBranchId'
    );

    const partnerInput = document.getElementById('ediReportPartner');
    const moneygramTableCard = document.getElementById('ediReportMoneygramTableCard');
    const updateMoneygramTableVisibility = () => {
        if (!partnerInput || !moneygramTableCard) return;
        moneygramTableCard.hidden = partnerInput.value.trim().toLocaleUpperCase() !== 'MONEYGRAM';
    };
    partnerInput?.addEventListener('input', updateMoneygramTableVisibility);
    partnerInput?.addEventListener('change', updateMoneygramTableVisibility);
    updateMoneygramTableVisibility();

    const mainzoneSelect = document.getElementById('ediReportMainzone');
    const zoneSelect = document.getElementById('ediReportZone');
    if (mainzoneSelect && zoneSelect) {
        const zoneOptions = Array.from(zoneSelect.options).map((option) => ({
            value: option.value,
            label: option.textContent
        }));
        const zonesByMainzone = {
            LNCR: ['LZN', 'NCR', 'Showroom'],
            VISMIN: ['VIS', 'MIN', 'Showroom']
        };

        const updateZoneOptions = () => {
            const selectedMainzone = mainzoneSelect.value.trim().toLocaleUpperCase();
            const allowedZones = zonesByMainzone[selectedMainzone] || [];
            const previousZone = zoneSelect.value;

            zoneSelect.replaceChildren(...zoneOptions
                .filter((option) => option.value === '' ||
                    allowedZones.includes(option.value))
                .map((option) => new Option(option.label, option.value)));

            zoneSelect.value = Array.from(zoneSelect.options)
                .some((option) => option.value === previousZone)
                ? previousZone
                : '';
        };

        mainzoneSelect.addEventListener('change', updateZoneOptions);
        updateZoneOptions();
    }

    const regionSelect = document.getElementById('ediReportRegion');
    let regionRequestSequence = 0;
    const showroomRegions = {
        LZN: { label: 'LUZON SHOWROOM', value: 'LZN' },
        MIN: { label: 'MINDANAO SHOWROOM', value: 'MIN' },
        NCR: { label: 'NCR SHOWROOM', value: 'NCR' },
        VIS: { label: 'VISAYAS SHOWROOM', value: 'VIS' }
    };
    const showroomRegionsByMainzone = {
        LNCR: [showroomRegions.LZN, showroomRegions.NCR],
        VISMIN: [showroomRegions.VIS, showroomRegions.MIN]
    };

    const updateRegionOptions = async () => {
        if (!mainzoneSelect || !zoneSelect || !regionSelect) return;
        const requestSequence = ++regionRequestSequence;
        const mainzone = mainzoneSelect.value.trim();
        const zone = zoneSelect.value.trim();
        regionSelect.replaceChildren(new Option('Select a Region', ''));
        if (!mainzone) return;

        try {
            const params = new URLSearchParams({ mainzone, zone });
            const response = await fetch(`${regionOptionsEndpoint}?${params.toString()}`, {
                headers: { Accept: 'application/json' }
            });
            const payload = await response.json();
            if (requestSequence !== regionRequestSequence) return;
            if (!response.ok || !payload.success || !Array.isArray(payload.regions)) {
                throw new Error(payload.error || 'Unable to load regions.');
            }

            payload.regions.forEach((region) => {
                const label = String(region.description || '').trim();
                const value = String(region.code || label).trim();
                if (label) regionSelect.add(new Option(label, value));
            });

            const normalizedMainzone = mainzone.toLocaleUpperCase();
            const showroomOptions = !zone || zone.toLocaleUpperCase() === 'SHOWROOM'
                ? (showroomRegionsByMainzone[normalizedMainzone] || [])
                : [];
            showroomOptions.forEach((showroomRegion) => {
                regionSelect.add(new Option(showroomRegion.label, showroomRegion.value));
            });
        } catch (error) {
            if (requestSequence !== regionRequestSequence) return;
            console.error(error);
        }
    };

    mainzoneSelect?.addEventListener('change', updateRegionOptions);
    zoneSelect?.addEventListener('change', updateRegionOptions);
    updateRegionOptions();

    const statusSelect = document.getElementById('ediReportBranchStatus');
    const branchInput = document.getElementById('ediReportBranchName');
    const branchIdInput = document.getElementById('ediReportBranchId');
    const branchList = document.getElementById('ediReportBranchNameSuggestions');
    const branchClearButton = document.getElementById('ediReportBranchNameClear');
    let branchRequestSequence = 0;

    const updateBranchOptions = async () => {
        if (!statusSelect || !branchInput || !branchList) return;
        const requestSequence = ++branchRequestSequence;
        branchInput.value = '';
        if (branchIdInput) branchIdInput.value = '';
        branchClearButton?.setAttribute('hidden', '');
        branchList.replaceChildren();
        branchList.hidden = true;
        branchInput.setAttribute('aria-expanded', 'false');
        if (!statusSelect.value) return;

        try {
            const params = new URLSearchParams({
                status: statusSelect.value,
                mainzone: mainzoneSelect?.value || '',
                zone: zoneSelect?.value || '',
                region: regionSelect?.value || ''
            });
            const response = await fetch(`${branchOptionsEndpoint}?${params.toString()}`, {
                headers: { Accept: 'application/json' }
            });
            const payload = await response.json();
            if (requestSequence !== branchRequestSequence) return;
            if (!response.ok || !payload.success || !Array.isArray(payload.branches)) {
                throw new Error(payload.error || 'Unable to load branches.');
            }

            const fragment = document.createDocumentFragment();
            payload.branches.forEach((branch) => {
                const name = String(branch.name || '').trim();
                const id = String(branch.id || '').trim();
                if (!name || !id) return;
                const option = document.createElement('li');
                option.setAttribute('role', 'option');
                option.tabIndex = -1;
                option.dataset.value = name;
                option.dataset.submitValue = id;
                option.textContent = name;
                fragment.appendChild(option);
            });
            branchList.appendChild(fragment);
        } catch (error) {
            if (requestSequence !== branchRequestSequence) return;
            console.error(error);
        }
    };

    statusSelect?.addEventListener('change', updateBranchOptions);
    mainzoneSelect?.addEventListener('change', updateBranchOptions);
    zoneSelect?.addEventListener('change', updateBranchOptions);
    regionSelect?.addEventListener('change', updateBranchOptions);
    updateBranchOptions();

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const generateButton = document.getElementById('ediReportGenerate');
        const tableBody = document.getElementById('ediReportTableBody');
        if (!tableBody || !statusSelect) return;

        const originalButtonText = generateButton?.textContent || 'Generate';
        if (generateButton) {
            generateButton.disabled = true;
            generateButton.textContent = 'Generating...';
        }

        try {
            const params = new URLSearchParams({
                status: statusSelect.value,
                mainzone: mainzoneSelect?.value || '',
                zone: zoneSelect?.value || '',
                region: regionSelect?.value || '',
                branch_id: branchIdInput?.value || '',
                month: document.getElementById('ediReportMonth')?.value || ''
            });
            const response = await fetch(`${reportResultsEndpoint}?${params.toString()}`, {
                headers: { Accept: 'application/json' }
            });
            const payload = await response.json();
            if (!response.ok || !payload.success || !Array.isArray(payload.rows)) {
                throw new Error(payload.error || 'Unable to generate the report.');
            }

            tableBody.replaceChildren();
            latestReportRows = payload.rows;
            if (exportButton) exportButton.disabled = payload.rows.length === 0;
            if (payload.rows.length === 0) {
                const row = tableBody.insertRow();
                const cell = row.insertCell();
                cell.colSpan = 21;
                cell.textContent = 'No branch records found for the selected filters.';
                cell.className = 'edi-report-no-results';
                return;
            }

            const fragment = document.createDocumentFragment();
            const formatCount = (value) => {
                const number = Number(value || 0);
                return number === 0 ? '' : new Intl.NumberFormat('en-US', {
                    maximumFractionDigits: 0
                }).format(number);
            };
            const formatAmount = (value) => {
                const number = Number(value || 0);
                return Math.abs(number) < 0.005 ? '' : new Intl.NumberFormat('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }).format(number);
            };
            payload.rows.forEach((record) => {
                const row = document.createElement('tr');
                const php = record.metrics?.PHP || {};
                const usd = record.metrics?.USD || {};
                const values = [
                    record.branch_id || '',
                    record.code || '',
                    record.branch_name || '',
                    record.region_description || '',
                    formatCount(php.payout_count),
                    formatAmount(php.payout_principal),
                    formatAmount(php.payout_charge),
                    formatAmount(php.payout_fx_share),
                    formatCount(usd.payout_count),
                    formatAmount(usd.payout_principal),
                    formatAmount(usd.payout_charge),
                    formatAmount(usd.payout_fx_share),
                    formatCount(php.sendout_count),
                    formatAmount(php.sendout_principal),
                    formatAmount(php.sendout_charge),
                    formatAmount(php.sendout_fx_share),
                    formatCount(usd.sendout_count),
                    formatAmount(usd.sendout_principal),
                    formatAmount(usd.sendout_charge),
                    formatAmount(usd.sendout_fx_share),
                    record.ml_matic_status || ''
                ];
                values.forEach((value) => {
                    const cell = document.createElement('td');
                    cell.textContent = String(value);
                    row.appendChild(cell);
                });
                fragment.appendChild(row);
            });
            tableBody.appendChild(fragment);
        } catch (error) {
            latestReportRows = [];
            if (exportButton) exportButton.disabled = true;
            tableBody.replaceChildren();
            const row = tableBody.insertRow();
            const cell = row.insertCell();
            cell.colSpan = 21;
            cell.textContent = error.message || 'Unable to generate the report.';
            cell.className = 'edi-report-no-results edi-report-no-results--error';
        } finally {
            if (generateButton) {
                generateButton.disabled = false;
                generateButton.textContent = originalButtonText;
            }
        }
    });

    exportButton?.addEventListener('click', async () => {
        if (latestReportRows.length === 0) return;
        const originalText = exportButton.textContent;
        exportButton.disabled = true;
        exportButton.textContent = 'Exporting...';
        try {
            const response = await fetch(reportExportEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
                body: JSON.stringify({
                    month: document.getElementById('ediReportMonth')?.value || '',
                    rows: latestReportRows
                })
            });
            if (!response.ok) throw new Error('Unable to export the EDI report.');
            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            const month = document.getElementById('ediReportMonth')?.value || 'report';
            link.href = url;
            link.download = `EDI_Report_${month.replace('-', '_')}.xlsx`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
        } catch (error) {
            window.alert(error.message || 'Unable to export the EDI report.');
        } finally {
            exportButton.disabled = latestReportRows.length === 0;
            exportButton.textContent = originalText;
        }
    });
})();
</script>
