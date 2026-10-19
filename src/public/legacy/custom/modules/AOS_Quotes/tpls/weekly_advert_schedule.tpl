{* Radio Weekly Advert Schedule Template - Embedded inside AOS_Quotes EditView form *}
<div id="weekly_advert_schedule_wrapper" style="margin: 15px 0; padding: 15px; border: 1px solid #d0d7de; border-radius: 6px; background: #ffffff;">
    <h4 style="margin-top: 0; margin-bottom: 12px; color: #1f2328; font-weight: 600;">
        {$RADIO_STATION_NAME|default:'Community Radio FM'} Weekly Advert Schedule
    </h4>

    {if !$RADIO_STATION_CONFIGURED}
    <div style="margin-bottom: 12px; padding: 8px 12px; background: #eef8f9; border: 1px solid #1B8FA3; border-radius: 4px; font-size: 13px; color: #0f5664;">
        📻 <strong>Station Setup Guide:</strong> Need to customize your station name, logo, show schedule, or rate cards? 
        <a href="./index.php?module=Administration&action=radio_station_wizard" target="_blank" style="color: #5E2B83; font-weight: bold; text-decoration: underline;">Launch the Station Setup Wizard</a>.
    </div>
    {/if}

    <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end; margin-bottom: 14px; padding: 10px 12px; background: #f6f8fa; border: 1px solid #d8dee4; border-radius: 4px;">
        <div>
            <label for="campaign_start_date_c" style="display: block; font-weight: 600; margin-bottom: 4px;">
                Campaign Start Date
            </label>
            <input type="date"
                   id="campaign_start_date_c"
                   name="campaign_start_date_c"
                   value="{$RADIO_START_DATE|escape:'html'}"
                   class="radio-date-input"
                   style="padding: 5px 8px; border: 1px solid #ccc; border-radius: 4px;" />
        </div>
        <div>
            <label for="campaign_end_date_c" style="display: block; font-weight: 600; margin-bottom: 4px;">
                Campaign End Date
            </label>
            <input type="date"
                   id="campaign_end_date_c"
                   name="campaign_end_date_c"
                   value="{$RADIO_END_DATE|escape:'html'}"
                   class="radio-date-input"
                   style="padding: 5px 8px; border: 1px solid #ccc; border-radius: 4px;" />
        </div>

        <div>
            <label for="spot_duration_c" style="display: block; font-weight: 600; margin-bottom: 4px;">
                Spot Duration
            </label>
            <select id="spot_duration_c"
                    name="spot_duration_c"
                    style="padding: 5px 8px; border: 1px solid #ccc; border-radius: 4px; font-weight: 500;">
                <option value="15 sec" {if $RADIO_SPOT_DURATION == '15 sec'}selected{/if}>15 sec (50% Rate)</option>
                <option value="30 sec" {if $RADIO_SPOT_DURATION == '30 sec' || empty($RADIO_SPOT_DURATION)}selected{/if}>30 sec (Standard)</option>
                <option value="45 sec" {if $RADIO_SPOT_DURATION == '45 sec'}selected{/if}>45 sec (150% Rate)</option>
                <option value="60 sec" {if $RADIO_SPOT_DURATION == '60 sec'}selected{/if}>60 sec (200% Rate)</option>
            </select>
        </div>

        <div>
            <label for="industry_category_c" style="display: block; font-weight: 600; margin-bottom: 4px;">
                Advertiser Category
            </label>
            <select id="industry_category_c"
                    name="industry_category_c"
                    style="padding: 5px 8px; border: 1px solid #ccc; border-radius: 4px; font-weight: 500;">
                <option value="other" {if $RADIO_INDUSTRY_CATEGORY == 'other'}selected{/if}>General / Other</option>
                <option value="automotive" {if $RADIO_INDUSTRY_CATEGORY == 'automotive'}selected{/if}>Automotive & Dealerships</option>
                <option value="dining" {if $RADIO_INDUSTRY_CATEGORY == 'dining'}selected{/if}>Restaurants & Fast Food</option>
                <option value="hospitality" {if $RADIO_INDUSTRY_CATEGORY == 'hospitality'}selected{/if}>Nightlife, Bars & Events</option>
                <option value="financial" {if $RADIO_INDUSTRY_CATEGORY == 'financial'}selected{/if}>Banking & Financial</option>
                <option value="campus" {if $RADIO_INDUSTRY_CATEGORY == 'campus'}selected{/if}>Campus Retail & Student Services</option>
                <option value="telecom" {if $RADIO_INDUSTRY_CATEGORY == 'telecom'}selected{/if}>Telecom & Tech</option>
                <option value="health" {if $RADIO_INDUSTRY_CATEGORY == 'health'}selected{/if}>Healthcare & Fitness</option>
                <option value="education" {if $RADIO_INDUSTRY_CATEGORY == 'education'}selected{/if}>Education & Departments</option>
            </select>
        </div>

        <div>
            <label for="applied_package_c" style="display: block; font-weight: 600; margin-bottom: 4px;">
                Package Preset
            </label>
            <select id="applied_package_c"
                    name="applied_package_c"
                    style="padding: 5px 8px; border: 1px solid #ccc; border-radius: 4px; font-weight: 500;">
                <option value="">-- Custom Schedule --</option>
                <option value="option_1" {if $RADIO_APPLIED_PACKAGE == 'option_1'}selected{/if}>Option 1: 50 Prime Recorded Ads (Weekly - R5,999)</option>
                <option value="option_2" {if $RADIO_APPLIED_PACKAGE == 'option_2'}selected{/if}>Option 2: 50 Prime Live Reads (Weekly - R6,999)</option>
                <option value="option_3" {if $RADIO_APPLIED_PACKAGE == 'option_3'}selected{/if}>Option 3: 120 Prime Recorded Ads (Monthly - R15,999)</option>
                <option value="option_4" {if $RADIO_APPLIED_PACKAGE == 'option_4'}selected{/if}>Option 4: 120 Prime Live Reads (Monthly - R16,999)</option>
            </select>
        </div>

        <div>
            <button type="button"
                    id="radio_toggle_avails"
                    class="button"
                    style="padding: 6px 14px; background: #0969da; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                📊 Station Avails
            </button>
        </div>

        <!-- RADIO_CLEAR_ALL_SPOTS_START -->
        <div>
            <button type="button"
                    id="radio_clear_all_spots"
                    class="button"
                    style="padding: 6px 14px; background: #d9534f; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                Clear All Spots
            </button>
        </div>
        <!-- RADIO_CLEAR_ALL_SPOTS_END -->
    </div>

    <div id="radio_capacity_warning_msg"
         style="display: none; margin-bottom: 12px; padding: 10px 14px; background: #fff8c5; border: 1px solid #d4a72c; color: #7d4e00; border-radius: 4px; font-weight: 600;">
    </div>

    <div id="radio_conflict_warning_msg"
         style="display: none; margin-bottom: 12px; padding: 10px 14px; background: #fff1e5; border: 1px solid #f97515; color: #9a3412; border-radius: 4px; font-weight: 600;">
    </div>

    <div id="radio_schedule_validation_msg"
         style="display: none; margin-bottom: 12px; padding: 8px 12px; background: #ffebe9; border: 1px solid #ff818266; color: #cf222e; border-radius: 4px; font-weight: 600;">
    </div>

    <div id="radio_weekly_total_bar"
         style="display: flex; flex-wrap: wrap; gap: 24px; align-items: center; margin-bottom: 12px; padding: 10px 14px; background: #ddf4ff; border: 1px solid #54aeff66; border-radius: 4px; font-size: 14px; font-weight: 600; color: #0969da;">
        <div>
            Total Spots: <span id="radio_total_spots_detail">{$RADIO_TOTAL_SPOTS|default:0}</span>
        </div>
        <div>
            Weekly spots: <span id="radio_weekly_total">{$RADIO_WEEKLY_SPOTS|default:0}</span>
        </div>
        <div id="radio_sync_status" style="font-size: 12px; font-weight: normal; color: #57606a;"></div>
    </div>

    <div style="overflow-x: auto;">
        <table id="radio_schedule_table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead>
                <tr style="background: #24292f; color: #ffffff; text-align: center;">
                    <th style="padding: 8px; border: 1px solid #d0d7de; text-align: left; min-width: 160px;">Slot</th>
                    <th style="padding: 8px; border: 1px solid #d0d7de; text-align: center; min-width: 110px;">Time</th>
                    <th style="padding: 8px; border: 1px solid #d0d7de; width: 75px;">Mon</th>
                    <th style="padding: 8px; border: 1px solid #d0d7de; width: 75px;">Tue</th>
                    <th style="padding: 8px; border: 1px solid #d0d7de; width: 75px;">Wed</th>
                    <th style="padding: 8px; border: 1px solid #d0d7de; width: 75px;">Thu</th>
                    <th style="padding: 8px; border: 1px solid #d0d7de; width: 75px;">Fri</th>
                    <th style="padding: 8px; border: 1px solid #d0d7de; width: 75px;">Sat</th>
                    <th style="padding: 8px; border: 1px solid #d0d7de; width: 75px;">Sun</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$RADIO_SLOTS key=slotKey item=slot}
                    {assign var="hourCount" value=$slot.hours|@count}
                    {assign var="hourIndex" value=0}
                    {foreach from=$slot.hours key=hourKey item=hourInfo}
                        <tr style="border-bottom: 1px solid #d8dee4; background: {if $hourIndex % 2 == 0}#ffffff{else}#f6f8fa{/if};">
                            {if $hourIndex == 0}
                                <td rowspan="{$hourCount}"
                                    style="padding: 8px; border: 1px solid #d0d7de; font-weight: 600; vertical-align: middle; background: #f0f4f8;">
                                    <div>{$slot.label|escape:'html'}</div>
                                    <div style="font-size: 11px; color: #57606a; font-weight: normal;">
                                        {$slot.part_number|escape:'html'} (R{$slot.rate|string_format:"%.2f"})
                                    </div>
                                </td>
                            {/if}
                            <td style="padding: 6px 8px; border: 1px solid #d0d7de; text-align: center; font-family: monospace; white-space: nowrap;">
                                {$hourInfo.label|escape:'html'}
                            </td>
                            {foreach from=$RADIO_DAYS key=dayKey item=dayInfo}
                                {assign var="fieldName" value="`$dayKey`_`$slotKey`_`$hourKey`_c"}
                                {assign var="cellVal" value=$RADIO_CELL_VALUES[$fieldName]|default:0}
                                <td style="padding: 4px; border: 1px solid #d0d7de; text-align: center;">
                                    <input type="number"
                                           min="0"
                                           step="1"
                                           id="{$fieldName}"
                                           name="{$fieldName}"
                                           value="{$cellVal}"
                                           data-day="{$dayKey}"
                                           data-php-w="{$dayInfo.php_w}"
                                           data-slot="{$slotKey}"
                                           data-part-number="{$slot.part_number|escape:'html'}"
                                           data-hour="{$hourKey}"
                                           class="radio-schedule-cell"
                                           style="width: 58px; padding: 4px; text-align: center; border: 1px solid #c0c7cf; border-radius: 3px;" />
                                </td>
                            {/foreach}
                        </tr>
                        {assign var="hourIndex" value=$hourIndex+1}
                    {/foreach}
                {/foreach}
            </tbody>
        </table>
    </div>
</div>

<!-- RADIO_SAVE_BUTTON_FIX_START -->
<script type="text/javascript">
{literal}
(function() {
    var RADIO_PART_NUMBERS = {
        'PRE-BREAKFAST': true,
        'MORNING-BUZZ': true,
        'MIDMORNING-BANTER': true,
        'MIDDAY-PULSE': true,
        'CRUISE-CONTROL': true,
        'SPECIALTY-SHOW-1': true,
        'SPECIALTY-SHOW-2': true,
        'MUSIC-UNLIMITED': true
    };

    var RADIO_SLOT_RATES = {
        'PRE-BREAKFAST': 300.00,
        'MORNING-BUZZ': 320.00,
        'MIDMORNING-BANTER': 280.00,
        'MIDDAY-PULSE': 260.00,
        'CRUISE-CONTROL': 320.00,
        'SPECIALTY-SHOW-1': 240.00,
        'SPECIALTY-SHOW-2': 220.00,
        'MUSIC-UNLIMITED': 120.00
    };

    var RADIO_SLOT_DESCRIPTIONS = {
        'PRE-BREAKFAST': 'One 30 second prerecorded advertisement played between 05:00 and 06:00.',
        'MORNING-BUZZ': 'One 30 second prerecorded advertisement played between 06:00 and 09:00.',
        'MIDMORNING-BANTER': 'One 30 second prerecorded advertisement played between 09:00 and 12:00.',
        'MIDDAY-PULSE': 'One 30 second prerecorded advertisement played between 12:00 and 15:00.',
        'CRUISE-CONTROL': 'One 30 second prerecorded advertisement played between 15:00 and 18:00.',
        'SPECIALTY-SHOW-1': 'One 30 second prerecorded advertisement played between 18:00 and 21:00.',
        'SPECIALTY-SHOW-2': 'One 30 second prerecorded advertisement played between 21:00 and 00:00.',
        'MUSIC-UNLIMITED': 'One 30 second prerecorded advertisement played between 00:00 and 05:00.'
    };

    var ajaxSeq = 0;
    var debounceTimer = null;

    function parseYmd(dateStr) {
        if (!dateStr) return null;
        var m = String(dateStr).trim().match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!m) return null;
        var y = parseInt(m[1], 10);
        var mo = parseInt(m[2], 10);
        var d = parseInt(m[3], 10);
        var dt = new Date(Date.UTC(y, mo - 1, d, 12, 0, 0));
        if (dt.getUTCFullYear() !== y || (dt.getUTCMonth() + 1) !== mo || dt.getUTCDate() !== d) {
            return null;
        }
        return dt;
    }

    function weekdayOccurrences(startDt, endDt, phpWeekday) {
        if (!startDt || !endDt || endDt.getTime() < startDt.getTime()) {
            return 0;
        }
        var startW = startDt.getUTCDay();
        var offset = (phpWeekday - startW + 7) % 7;
        var firstMs = startDt.getTime() + (offset * 86400000);
        if (firstMs > endDt.getTime()) {
            return 0;
        }
        var diffDays = Math.round((endDt.getTime() - firstMs) / 86400000);
        return 1 + Math.floor(diffDays / 7);
    }

    function getScheduleInputs() {
        return Array.prototype.slice.call(
            document.querySelectorAll('#weekly_advert_schedule_wrapper input.radio-schedule-cell')
        );
    }

    function showValidationMessage(msg) {
        var box = document.getElementById('radio_schedule_validation_msg');
        if (!box) return;
        if (msg) {
            box.textContent = msg;
            box.style.display = 'block';
        } else {
            box.textContent = '';
            box.style.display = 'none';
        }
    }

    function applyProductsToQuoteDom(response) {
        if (!response || !Array.isArray(response.active_products)) {
            return;
        }

        var wantedByPart = {};
        for (var i = 0; i < response.active_products.length; i++) {
            var prod = response.active_products[i];
            var q = (prod.total_qty > 0) ? prod.total_qty : prod.weekly_qty;
            if (q > 0) {
                wantedByPart[prod.part_number] = prod;
            }
        }

        if (typeof window.insertGroup === 'function' && !document.getElementById('product_group0')) {
            try { window.insertGroup(); } catch (e) {}
        }
        var headEl = document.getElementById('product_group0_head');
        if (headEl) {
            headEl.style.display = '';
        }

        var partInputs = document.querySelectorAll('input[id^="product_part_number"]');
        var existingRadioRowByPart = {};

        for (var j = 0; j < partInputs.length; j++) {
            var pInput = partInputs[j];
            var m = pInput.id.match(/^product_part_number(\d+)$/);
            if (!m) continue;
            var idx = m[1];
            var deletedEl = document.getElementById('product_deleted' + idx);
            if (deletedEl && deletedEl.value === '1') {
                continue;
            }
            var partVal = (pInput.value || '').trim().toUpperCase();
            if (RADIO_PART_NUMBERS[partVal]) {
                if (wantedByPart[partVal] && existingRadioRowByPart[partVal] === undefined) {
                    existingRadioRowByPart[partVal] = idx;
                } else {
                    if (typeof window.markLineDeleted === 'function') {
                        try { window.markLineDeleted(idx, 'product_'); } catch (e) {}
                    } else if (deletedEl) {
                        deletedEl.value = '1';
                        var rowEl = document.getElementById('product_body' + idx);
                        if (rowEl) rowEl.style.display = 'none';
                    }
                }
            }
        }

        for (var k = 0; k < response.active_products.length; k++) {
            var item = response.active_products[k];
            var lineQty = (item.total_qty > 0) ? item.total_qty : item.weekly_qty;
            if (lineQty <= 0) continue;

            var rowIdx = existingRadioRowByPart[item.part_number];
            if (rowIdx === undefined && typeof window.insertProductLine === 'function') {
                try {
                    rowIdx = window.insertProductLine('product_group0', '0');
                } catch (e) {
                    rowIdx = undefined;
                }
            }

            if (rowIdx !== undefined) {
                var setField = function(prefix, val) {
                    var el = document.getElementById(prefix + rowIdx);
                    if (el) el.value = val;
                };
                setField('product_name', item.product_name || item.part_number);
                setField('product_part_number', item.part_number);
                if (item.product_id) {
                    setField('product_product_id', item.product_id);
                }
                setField('product_product_qty', lineQty);
                setField('product_product_list_price', Number(item.unit_price).toFixed(2));
                setField('product_product_unit_price', Number(item.unit_price).toFixed(2));
                setField('product_vat', item.vat_rate || '15.0');
                setField('product_vat_amt', Number(item.vat_amt).toFixed(2));
                setField('product_product_total_price', Number(item.net_total).toFixed(2));
                setField('product_item_description', item.description || '');
                setField('product_description', '');

                if (typeof window.calculateLine === 'function') {
                    try { window.calculateLine(rowIdx, 'product_'); } catch (e) {}
                }
            }
        }

        if (typeof window.calculateTotal === 'function') {
            try { window.calculateTotal('group0'); } catch (e) {}
            try { window.calculateTotal(); } catch (e) {}
        }

        if (response.totals) {
            var setSummary = function(id, val) {
                var el = document.getElementById(id);
                if (el) el.value = Number(val).toFixed(2);
            };
            setSummary('total_amt', response.totals.total_amt);
            setSummary('subtotal_amount', response.totals.subtotal_amount);
            setSummary('tax_amount', response.totals.tax_amount);
            setSummary('total_amount', response.totals.total_amount);
        }
    }

    function computeLocalTotals() {
        var startInput = document.getElementById('campaign_start_date_c');
        var endInput = document.getElementById('campaign_end_date_c');
        var rawStart = startInput ? startInput.value.trim() : '';
        var rawEnd = endInput ? endInput.value.trim() : '';

        var startDt = parseYmd(rawStart);
        var endDt = parseYmd(rawEnd);
        var dateError = '';
        var dateRangeValid = false;

        if ((rawStart !== '' && !startDt) || (rawEnd !== '' && !endDt)) {
            dateError = 'Please enter valid Campaign Start Date and Campaign End Date (YYYY-MM-DD).';
        } else if (startDt && endDt) {
            if (endDt.getTime() < startDt.getTime()) {
                dateError = 'Campaign End Date cannot be earlier than Campaign Start Date.';
            } else {
                dateRangeValid = true;
            }
        }

        var repeats = {};
        if (dateRangeValid) {
            for (var w = 0; w <= 6; w++) {
                repeats[w] = weekdayOccurrences(startDt, endDt, w);
            }
        }

        var inputs = getScheduleInputs();
        var weeklySpots = 0;
        var totalSpots = 0;
        var hasInvalidQuantity = false;
        var payloadCells = {};
        var slotTotals = {};
        for (var part in RADIO_SLOT_RATES) {
            slotTotals[part] = { weekly: 0, total: 0 };
        }

        for (var i = 0; i < inputs.length; i++) {
            var inp = inputs[i];
            var rawVal = inp.value.trim();
            var qty = 0;
            if (rawVal !== '') {
                if (!/^\d+$/.test(rawVal)) {
                    hasInvalidQuantity = true;
                    inp.style.borderColor = '#cf222e';
                } else {
                    qty = parseInt(rawVal, 10);
                    inp.style.borderColor = '#c0c7cf';
                }
            } else {
                inp.style.borderColor = '#c0c7cf';
            }

            payloadCells[inp.name] = qty;
            if (qty > 0) {
                weeklySpots += qty;
                var partNum = inp.getAttribute('data-part-number');
                if (partNum && slotTotals[partNum]) {
                    slotTotals[partNum].weekly += qty;
                }
                if (dateRangeValid) {
                    var phpW = parseInt(inp.getAttribute('data-php-w'), 10);
                    var reps = repeats[phpW] || 0;
                    totalSpots += qty * reps;
                    if (partNum && slotTotals[partNum]) {
                        slotTotals[partNum].total += qty * reps;
                    }
                }
            }
        }

        var validationMsg = dateError;
        if (!validationMsg && hasInvalidQuantity) {
            validationMsg = 'Schedule quantities must be nonnegative whole numbers (0, 1, 2, ...).';
        }
        if (!validationMsg && !dateRangeValid && (rawStart === '' || rawEnd === '')) {
            showValidationMessage('Tip: Select Campaign Start and End Dates to calculate multi-week totals.');
        } else {
            showValidationMessage(validationMsg);
        }

        var weeklyEl = document.getElementById('radio_weekly_total');
        var totalEl = document.getElementById('radio_total_spots_detail');
        if (weeklyEl) weeklyEl.textContent = String(weeklySpots);
        if (totalEl) totalEl.textContent = String(dateRangeValid ? totalSpots : 0);

        // Immediate synchronous DOM update so line items and totals update instantly on user input
        if (validationMsg === '') {
            var localActive = [];
            var subtotal = 0.0;
            var tax = 0.0;
            var grandTotal = 0.0;
            for (var pn in slotTotals) {
                var st = slotTotals[pn];
                var q = dateRangeValid ? st.total : st.weekly;
                if (q > 0) {
                    var rate = RADIO_SLOT_RATES[pn] || 0;
                    var net = q * rate;
                    var v = Math.round(net * 0.15 * 100) / 100;
                    var g = Math.round((net + v) * 100) / 100;
                    subtotal += net;
                    tax += v;
                    grandTotal += g;
                    localActive.push({
                        part_number: pn,
                        product_name: pn,
                        product_id: '',
                        unit_price: rate,
                        total_qty: q,
                        weekly_qty: st.weekly,
                        net_total: net,
                        vat_amt: v,
                        vat_rate: '15.0',
                        gross_total: g,
                        description: RADIO_SLOT_DESCRIPTIONS[pn] || ''
                    });
                }
            }
            applyProductsToQuoteDom({
                active_products: localActive,
                totals: {
                    total_amt: subtotal,
                    subtotal_amount: subtotal,
                    tax_amount: tax,
                    total_amount: grandTotal
                }
            });
        }

        return {
            valid: (validationMsg === ''),
            start_date: rawStart,
            end_date: rawEnd,
            weekly_spots: weeklySpots,
            total_spots: dateRangeValid ? totalSpots : 0,
            cells: payloadCells
        };
    }

    function triggerAjaxSync(state) {
        if (!state.valid) return;
        var currentSeq = ++ajaxSeq;
        var statusEl = document.getElementById('radio_sync_status');
        if (statusEl) statusEl.textContent = 'Syncing schedule totals...';

        var formEl = document.getElementById('EditView');
        var recordId = '';
        if (formEl && formEl.elements['record']) {
            recordId = formEl.elements['record'].value || '';
        }

        var durEl = document.getElementById('spot_duration_c');
        var catEl = document.getElementById('industry_category_c');
        var pkgEl = document.getElementById('applied_package_c');

        var payload = {
            quote_id: recordId,
            campaign_start_date_c: state.start_date,
            campaign_end_date_c: state.end_date,
            spot_duration_c: durEl ? durEl.value : '30 sec',
            industry_category_c: catEl ? catEl.value : 'other',
            applied_package_c: pkgEl ? pkgEl.value : '',
            cells: state.cells
        };

        fetch('index.php?entryPoint=radio_schedule_ajax_sync', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (currentSeq !== ajaxSeq) {
                return;
            }
            if (data && data.success) {
                var weeklyEl = document.getElementById('radio_weekly_total');
                var totalEl = document.getElementById('radio_total_spots_detail');
                if (weeklyEl) weeklyEl.textContent = String(data.weekly_spots);
                if (totalEl) totalEl.textContent = String(data.total_spots);
                applyProductsToQuoteDom(data);
                if (statusEl) statusEl.textContent = '';

                // Handle capacity warnings
                var capEl = document.getElementById('radio_capacity_warning_msg');
                if (capEl) {
                    if (data.capacity && !data.capacity.valid && data.capacity.violations) {
                        var vKeys = Object.keys(data.capacity.violations);
                        capEl.innerHTML = '⚠️ <strong>Station Capacity Alert:</strong> ' + vKeys.length +
                            ' hourly slot(s) exceed the maximum broadcast limit of 20 spots/hour! Please adjust quantities to avoid overbooking.';
                        capEl.style.display = 'block';
                    } else {
                        capEl.style.display = 'none';
                    }
                }

                // Handle category conflict warnings
                var confEl = document.getElementById('radio_conflict_warning_msg');
                if (confEl) {
                    if (data.conflicts && data.conflicts.length > 0) {
                        var compNames = [];
                        for (var c = 0; c < data.conflicts.length; c++) {
                            compNames.push(data.conflicts[c].campaign_name + ' (' + data.conflicts[c].day + ' ' + data.conflicts[c].slot_label + ')');
                        }
                        confEl.innerHTML = '⚠️ <strong>Category Conflict Alert:</strong> Competing advertiser(s) in category <em>' +
                            (catEl ? catEl.options[catEl.selectedIndex].text : '') + '</em> already scheduled in matching slots: ' +
                            compNames.slice(0, 3).join(', ') + (compNames.length > 3 ? ' and more...' : '');
                        confEl.style.display = 'block';
                    } else {
                        confEl.style.display = 'none';
                    }
                }
            } else if (data && data.message) {
                showValidationMessage(data.message);
                if (statusEl) statusEl.textContent = '';
            }
        })
        .catch(function() {
            if (currentSeq === ajaxSeq && statusEl) {
                statusEl.textContent = '';
            }
        });
    }

    function scheduleRecalcAndSync(immediate) {
        var state = computeLocalTotals();
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }
        if (immediate) {
            triggerAjaxSync(state);
        } else {
            debounceTimer = setTimeout(function() {
                triggerAjaxSync(state);
            }, 220);
        }
    }

    var availsActive = false;
    function toggleStationAvails() {
        var startInput = document.getElementById('campaign_start_date_c');
        var endInput = document.getElementById('campaign_end_date_c');
        var startVal = startInput ? startInput.value : '';
        var endVal = endInput ? endInput.value : '';
        var btn = document.getElementById('radio_toggle_avails');

        if (availsActive) {
            availsActive = false;
            if (btn) btn.style.background = '#0969da';
            var inputs = getScheduleInputs();
            for (var k = 0; k < inputs.length; k++) {
                inputs[k].style.background = '#ffffff';
                inputs[k].removeAttribute('title');
            }
            return;
        }

        if (!startVal || !endVal) {
            alert('Please select both Campaign Start Date and End Date to view station avails.');
            return;
        }

        var formEl = document.getElementById('EditView');
        var recordId = formEl && formEl.elements['record'] ? formEl.elements['record'].value : '';
        var state = computeLocalTotals();

        fetch('index.php?entryPoint=radio_schedule_ajax_sync', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'check_avails',
                quote_id: recordId,
                campaign_start_date_c: startVal,
                campaign_end_date_c: endVal,
                cells: state.cells
            })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data && data.success && data.capacity && data.capacity.capacity_matrix) {
                availsActive = true;
                if (btn) btn.style.background = '#1a7f37';
                var matrix = data.capacity.capacity_matrix;
                for (var f in matrix) {
                    var cellEl = document.getElementById(f);
                    if (cellEl && matrix[f]) {
                        var item = matrix[f];
                        var status = item.status;
                        if (status === 'full') {
                            cellEl.style.background = '#ffebe9';
                        } else if (status === 'filling') {
                            cellEl.style.background = '#fff8c5';
                        } else {
                            cellEl.style.background = '#dafbe1';
                        }
                        cellEl.title = item.day + ' ' + item.label + ' — Booked: ' + item.booked +
                            '/20 spots (' + item.remaining + ' remaining)';
                    }
                }
            }
        });
    }

    function applyPackagePreset(pkgKey) {
        if (!pkgKey) return;
        fetch('index.php?entryPoint=radio_schedule_ajax_sync&action=get_packages')
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data && data.packages && data.packages[pkgKey]) {
                var pkg = data.packages[pkgKey];
                var allInputs = getScheduleInputs();
                for (var i = 0; i < allInputs.length; i++) {
                    allInputs[i].value = '0';
                    allInputs[i].style.borderColor = '#c0c7cf';
                }
                var prime = pkg.prime_slots || {};
                for (var f in prime) {
                    var inputEl = document.getElementById(f);
                    if (inputEl) {
                        inputEl.value = String(prime[f]);
                    }
                }
                scheduleRecalcAndSync(true);
            }
        });
    }

    function initScheduleEvents() {
        var wrapper = document.getElementById('weekly_advert_schedule_wrapper');
        if (!wrapper) return;

        var editForm = document.getElementById('EditView') || document.forms['EditView'];
        if (editForm && !editForm.contains(wrapper)) {
            editForm.appendChild(wrapper);
        }

        var inputs = getScheduleInputs();
        for (var i = 0; i < inputs.length; i++) {
            inputs[i].addEventListener('input', function() { scheduleRecalcAndSync(false); });
            inputs[i].addEventListener('change', function() { scheduleRecalcAndSync(false); });
        }

        var startInput = document.getElementById('campaign_start_date_c');
        var endInput = document.getElementById('campaign_end_date_c');
        if (startInput) {
            startInput.addEventListener('change', function() { scheduleRecalcAndSync(true); });
            startInput.addEventListener('input', function() { scheduleRecalcAndSync(false); });
        }
        if (endInput) {
            endInput.addEventListener('change', function() { scheduleRecalcAndSync(true); });
            endInput.addEventListener('input', function() { scheduleRecalcAndSync(false); });
        }

        var durSelect = document.getElementById('spot_duration_c');
        if (durSelect) {
            durSelect.addEventListener('change', function() { scheduleRecalcAndSync(true); });
        }

        var catSelect = document.getElementById('industry_category_c');
        if (catSelect) {
            catSelect.addEventListener('change', function() { scheduleRecalcAndSync(true); });
        }

        var pkgSelect = document.getElementById('applied_package_c');
        if (pkgSelect) {
            pkgSelect.addEventListener('change', function() { applyPackagePreset(pkgSelect.value); });
        }

        var availsBtn = document.getElementById('radio_toggle_avails');
        if (availsBtn) {
            availsBtn.addEventListener('click', function(e) {
                e.preventDefault();
                toggleStationAvails();
            });
        }

        var clearBtn = document.getElementById('radio_clear_all_spots');
        if (clearBtn) {
            clearBtn.addEventListener('click', function(e) {
                e.preventDefault();
                var allInputs = getScheduleInputs();
                for (var j = 0; j < allInputs.length; j++) {
                    allInputs[j].value = '0';
                    allInputs[j].style.borderColor = '#c0c7cf';
                }
                scheduleRecalcAndSync(true);
            });
        }

        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                var state = computeLocalTotals();
                if (!state.valid) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            });
        }

        computeLocalTotals();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initScheduleEvents);
    } else {
        initScheduleEvents();
    }
})();
{/literal}
</script>
<!-- RADIO_SAVE_BUTTON_FIX_END -->
