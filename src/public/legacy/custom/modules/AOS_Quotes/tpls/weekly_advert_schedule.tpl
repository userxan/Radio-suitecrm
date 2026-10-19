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

    <div style="background: #f6f8fa; border: 1px solid #d8dee4; border-radius: 6px; padding: 14px 16px; margin-bottom: 14px;">
        <div style="display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end;">
            <div style="flex: 1 1 140px; min-width: 140px;">
                <label for="campaign_start_date_c" style="display: block; font-size: 11px; font-weight: 700; color: #57606a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; white-space: nowrap;">
                    Campaign Start Date
                </label>
                <input type="date"
                       id="campaign_start_date_c"
                       name="campaign_start_date_c"
                       value="{$RADIO_START_DATE|escape:'html'}"
                       class="radio-date-input"
                       style="box-sizing: border-box; width: 100%; height: 38px; padding: 6px 10px; font-size: 13px; font-family: inherit; color: #1f2328; background-color: #ffffff; border: 1px solid #d0d7de; border-radius: 6px;" />
            </div>

            <div style="flex: 1 1 140px; min-width: 140px;">
                <label for="campaign_end_date_c" style="display: block; font-size: 11px; font-weight: 700; color: #57606a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; white-space: nowrap;">
                    Campaign End Date
                </label>
                <input type="date"
                       id="campaign_end_date_c"
                       name="campaign_end_date_c"
                       value="{$RADIO_END_DATE|escape:'html'}"
                       class="radio-date-input"
                       style="box-sizing: border-box; width: 100%; height: 38px; padding: 6px 10px; font-size: 13px; font-family: inherit; color: #1f2328; background-color: #ffffff; border: 1px solid #d0d7de; border-radius: 6px;" />
            </div>

            <div style="flex: 1 1 160px; min-width: 150px;">
                <label for="spot_duration_c" style="display: block; font-size: 11px; font-weight: 700; color: #57606a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; white-space: nowrap;">
                    Spot Duration
                </label>
                <select id="spot_duration_c"
                        name="spot_duration_c"
                        style="box-sizing: border-box; width: 100%; height: 38px; padding: 6px 10px; font-size: 13px; font-family: inherit; color: #1f2328; background-color: #ffffff; border: 1px solid #d0d7de; border-radius: 6px; font-weight: 500;">
                    <option value="15 sec" {if $RADIO_SPOT_DURATION == '15 sec'}selected{/if}>15 sec (50% Rate)</option>
                    <option value="30 sec" {if $RADIO_SPOT_DURATION == '30 sec' || empty($RADIO_SPOT_DURATION)}selected{/if}>30 sec (Standard Rate)</option>
                    <option value="45 sec" {if $RADIO_SPOT_DURATION == '45 sec'}selected{/if}>45 sec (150% Rate)</option>
                    <option value="60 sec" {if $RADIO_SPOT_DURATION == '60 sec'}selected{/if}>60 sec (200% Rate)</option>
                </select>
            </div>

            <div style="flex: 1.2 1 200px; min-width: 190px;">
                <label for="industry_category_c" style="display: block; font-size: 11px; font-weight: 700; color: #57606a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; white-space: nowrap;">
                    Advertiser Category
                </label>
                <select id="industry_category_c"
                        name="industry_category_c"
                        style="box-sizing: border-box; width: 100%; height: 38px; padding: 6px 10px; font-size: 13px; font-family: inherit; color: #1f2328; background-color: #ffffff; border: 1px solid #d0d7de; border-radius: 6px; font-weight: 500;">
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

            <div style="flex: 1.6 1 270px; min-width: 250px;">
                <label for="applied_package_c" style="display: block; font-size: 11px; font-weight: 700; color: #57606a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; white-space: nowrap;">
                    Package Preset
                </label>
                <select id="applied_package_c"
                        name="applied_package_c"
                        style="box-sizing: border-box; width: 100%; height: 38px; padding: 6px 10px; font-size: 13px; font-family: inherit; color: #1f2328; background-color: #ffffff; border: 1px solid #d0d7de; border-radius: 6px; font-weight: 500;">
                    <option value="">-- Custom Schedule --</option>
                    {foreach from=$RADIO_PACKAGES key=pkgKey item=pkg}
                        <option value="{$pkgKey}" {if $RADIO_APPLIED_PACKAGE == $pkgKey}selected{/if}>{$pkg.name} ({if $pkg.type == 'monthly'}Monthly{else}Weekly{/if} - {$RADIO_CURRENCY|default:'R'}{$pkg.rate|string_format:'%.2f'})</option>
                    {/foreach}
                </select>
            </div>
        </div>

        <div style="display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: center; gap: 12px; margin-top: 14px; padding-top: 12px; border-top: 1px solid #d8dee4;">
            <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
                <button type="button"
                        id="radio_clear_all_spots"
                        class="button"
                        style="height: 34px; padding: 0 14px; background: #cf222e; color: #ffffff; border: 1px solid #cf222e; border-radius: 6px; cursor: pointer; font-size: 12.5px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center;">
                    Clear All Spots
                </button>
            </div>
        </div>
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
                                    <div style="font-size: 11px; color: #57606a; font-weight: normal; margin-top: 3px;">
                                        {$slot.part_number|escape:'html'}
                                        <div style="margin-top: 4px; font-size: 10px; line-height: 1.4;">
                                            <span style="display: inline-block; background: #dafbe1; color: #116329; padding: 1px 5px; border-radius: 3px; font-weight: 600;">Mon-Fri: {$slot.weekday_rate_label}</span>
                                            <span style="display: inline-block; background: #fff8c5; color: #7d4e00; padding: 1px 5px; border-radius: 3px; font-weight: 600; margin-top: 2px;">Sat-Sun: {$slot.weekend_rate_label}</span>
                                        </div>
                                    </div>
                                </td>
                            {/if}
                            <td style="padding: 6px 8px; border: 1px solid #d0d7de; text-align: center; font-family: monospace; white-space: nowrap;">
                                {$hourInfo.label|escape:'html'}
                            </td>
                            {foreach from=$RADIO_DAYS key=dayKey item=dayInfo}
                                {assign var="fieldName" value="`$dayKey`_`$slotKey`_`$hourKey`_c"}
                                {assign var="cellVal" value=$RADIO_CELL_VALUES[$fieldName]|default:0}
                                {assign var="cellRate" value=$RADIO_CELL_RATES[$fieldName]|default:$slot.rate}
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
                                           data-rate="{$cellRate}"
                                           title="{$dayInfo.label} {$hourInfo.label} - Rate: R{$cellRate|string_format:'%.2f'} / spot"
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

    {* Multi-Item Rate Card Bundle Selector (Social Media, Live Reads, Interviews & Sponsorships) *}
    <div id="radio_ratecard_extras_panel" style="margin-top: 16px; background: #f8fafc; border: 1px solid #d0d7de; border-radius: 6px; padding: 14px 16px;">
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px;">
            <div>
                <h5 style="margin: 0 0 3px 0; font-size: 13.5px; color: #5E2B83; font-weight: 700;">
                    ➕ Bundle Rate Card Extras (Social Media, Live Reads, Interviews &amp; Outside Broadcasts)
                </h5>
                <div style="font-size: 12px; color: #57606a;">
                    Add non-spot rate card items directly to this Quote's line items alongside your broadcast schedule.
                </div>
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                <select id="radio_extra_item_select"
                        style="height: 36px; min-width: 320px; padding: 6px 10px; font-size: 13px; border: 1px solid #d0d7de; border-radius: 6px; background: #ffffff; color: #1f2328;">
                    <option value="">-- Select Rate Card Extra Item --</option>
                    {foreach from=$RADIO_EXTRAS_CATALOGUE key=catGroup item=catItems}
                        <optgroup label="{$catGroup|escape:'html'}">
                            {foreach from=$catItems item=extItem}
                                <option value="{$extItem.part_number|escape:'html'}"
                                        data-id="{$extItem.id|escape:'html'}"
                                        data-name="{$extItem.name|escape:'html'}"
                                        data-price="{$extItem.price}"
                                        data-desc="{$extItem.description|escape:'html'}">
                                    {$extItem.name|escape:'html'} ({$RADIO_CURRENCY|default:'R'}{$extItem.price|string_format:'%.2f'})
                                </option>
                            {/foreach}
                        </optgroup>
                    {/foreach}
                </select>
                <input type="number"
                       id="radio_extra_item_qty"
                       min="1"
                       step="1"
                       value="1"
                       title="Quantity"
                       style="width: 68px; height: 36px; box-sizing: border-box; padding: 6px; text-align: center; font-size: 13px; border: 1px solid #d0d7de; border-radius: 6px;" />
                <button type="button"
                        id="radio_add_extra_btn"
                        class="button"
                        style="height: 36px; padding: 0 16px; background: #1a7f37; color: #ffffff; border: 1px solid #1a7f37; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600;">
                    + Add Extra to Quote
                </button>
                <span id="radio_extra_added_msg" style="font-size: 12px; color: #1a7f37; font-weight: 600; display: none;"></span>
            </div>
        </div>
    </div>
</div>

<!-- RADIO_SAVE_BUTTON_FIX_START -->
<script type="text/javascript">
window.RADIO_CONFIG_PACKAGES = {$RADIO_PACKAGES_JSON|default:'{}'};
window.RADIO_EXTRAS_CATALOGUE = {$RADIO_EXTRAS_JSON|default:'{}'};
</script>
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
        'PRE-BREAKFAST': 120.00,
        'MORNING-BUZZ': 350.00,
        'MIDMORNING-BANTER': 320.00,
        'MIDDAY-PULSE': 350.00,
        'CRUISE-CONTROL': 350.00,
        'SPECIALTY-SHOW-1': 300.00,
        'SPECIALTY-SHOW-2': 220.00,
        'MUSIC-UNLIMITED': 120.00
    };

    var RADIO_SLOT_BASE_RATES = {
        'PRE-BREAKFAST': 120.00,
        'MORNING-BUZZ': 350.00,
        'MIDMORNING-BANTER': 320.00,
        'MIDDAY-PULSE': 350.00,
        'CRUISE-CONTROL': 350.00,
        'SPECIALTY-SHOW-1': 300.00,
        'SPECIALTY-SHOW-2': 220.00,
        'MUSIC-UNLIMITED': 120.00
    };

    function getRateCardHourlyPrice(dayKey, hourKey) {
        var isWeekend = (dayKey === 'sat' || dayKey === 'sun');
        if (!isWeekend) {
            switch (hourKey) {
                case '0000_0100': case '0100_0200': case '0200_0300':
                case '0300_0400': case '0400_0500': case '0500_0600':
                    return 120.00;
                case '0600_0700': case '0700_0800': case '0800_0900':
                    return 350.00;
                case '0900_1000': case '1000_1100': case '1100_1200':
                    return 320.00;
                case '1200_1300': case '1300_1400': case '1400_1500':
                    return 350.00;
                case '1500_1600': case '1600_1700': case '1700_1800':
                    return 350.00;
                case '1800_1900': case '1900_2000': case '2000_2100':
                    return 300.00;
                case '2100_2200': case '2200_2300': case '2300_0000':
                    return 220.00;
                default:
                    return 220.00;
            }
        } else {
            switch (hourKey) {
                case '0000_0100': case '0100_0200': case '0200_0300':
                case '0300_0400': case '0400_0500': case '0500_0600':
                    return 120.00;
                case '0600_0700': case '0700_0800': case '0800_0900':
                case '0900_1000':
                    return 300.00;
                case '1000_1100': case '1100_1200': case '1200_1300':
                case '1300_1400': case '1400_1500': case '1500_1600':
                case '1600_1700': case '1700_1800':
                    return 350.00;
                case '1800_1900': case '1900_2000': case '2000_2100':
                case '2100_2200':
                    return 220.00;
                case '2200_2300': case '2300_0000':
                    return 120.00;
                default:
                    return 220.00;
            }
        }
    }

    var RADIO_PACKAGES = (typeof window.RADIO_CONFIG_PACKAGES === 'object' && window.RADIO_CONFIG_PACKAGES) ? window.RADIO_CONFIG_PACKAGES : {
        'option_1': { rate: 5999.00, total_spots: 50, type: 'weekly' },
        'option_2': { rate: 6999.00, total_spots: 50, type: 'weekly' },
        'option_3': { rate: 15999.00, total_spots: 120, type: 'monthly' },
        'option_4': { rate: 16999.00, total_spots: 120, type: 'monthly' }
    };

    var RADIO_DURATION_MULTIPLIERS = {
        '15 sec': 0.50,
        '30 sec': 1.00,
        '45 sec': 1.50,
        '60 sec': 2.00
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
                setField('product_product_unit_price', Number(item.unit_price).toFixed(2));
                setField('product_vat_amt', Number(item.vat_amt).toFixed(2));
                setField('product_product_total_price', Number(item.net_total).toFixed(2));
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

    function updateCellTooltips() {
        var durEl = document.getElementById('spot_duration_c');
        var dur = durEl ? durEl.value : '30 sec';
        var durMult = RADIO_DURATION_MULTIPLIERS[dur] || 1.0;
        var inputs = getScheduleInputs();
        for (var i = 0; i < inputs.length; i++) {
            var inp = inputs[i];
            var dayKey = inp.getAttribute('data-day');
            var hourKey = inp.getAttribute('data-hour');
            if (dayKey && hourKey) {
                var baseRate = getRateCardHourlyPrice(dayKey, hourKey);
                var curRate = Math.round(baseRate * durMult * 100) / 100;
                var dayLabel = dayKey.charAt(0).toUpperCase() + dayKey.slice(1);
                var hourLabel = hourKey.replace('_', ' - ');
                inp.title = dayLabel + ' ' + hourLabel + ' - Rate: R' + curRate.toFixed(2) + ' / spot (' + dur + ')';
            }
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
        var durEl = document.getElementById('spot_duration_c');
        var dur = durEl ? durEl.value : '30 sec';
        var durMult = RADIO_DURATION_MULTIPLIERS[dur] || 1.0;

        var slotTotals = {};
        for (var part in RADIO_SLOT_RATES) {
            slotTotals[part] = { weekly: 0, total: 0, weeklyNet: 0.0, totalNet: 0.0 };
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
                var dayKey = inp.getAttribute('data-day');
                var hourKey = inp.getAttribute('data-hour');
                var baseCellRate = getRateCardHourlyPrice(dayKey, hourKey);
                var cellRate = Math.round(baseCellRate * durMult * 100) / 100;
                var cellWeeklyNet = Math.round(qty * cellRate * 100) / 100;

                if (partNum && slotTotals[partNum]) {
                    slotTotals[partNum].weekly += qty;
                    slotTotals[partNum].weeklyNet = Math.round((slotTotals[partNum].weeklyNet + cellWeeklyNet) * 100) / 100;
                }
                if (dateRangeValid) {
                    var phpW = parseInt(inp.getAttribute('data-php-w'), 10);
                    var reps = repeats[phpW] || 0;
                    var cellPlays = qty * reps;
                    totalSpots += cellPlays;
                    if (partNum && slotTotals[partNum]) {
                        slotTotals[partNum].total += cellPlays;
                        var cellTotalNet = Math.round(cellPlays * cellRate * 100) / 100;
                        slotTotals[partNum].totalNet = Math.round((slotTotals[partNum].totalNet + cellTotalNet) * 100) / 100;
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
            var pkgEl = document.getElementById('applied_package_c');
            var pkgKey = pkgEl ? pkgEl.value : '';
            var isPkg = Boolean(pkgKey && RADIO_PACKAGES[pkgKey]);
            var pkgDef = isPkg ? RADIO_PACKAGES[pkgKey] : null;

            var localActive = [];
            var subtotal = 0.0;
            var tax = 0.0;
            var grandTotal = 0.0;

            if (isPkg) {
                var pkgBaseRate = Math.round(pkgDef.rate * durMult * 100) / 100;
                var activeTotalQty = dateRangeValid ? totalSpots : weeklySpots;
                var packageCount = Math.max(1, Math.round(activeTotalQty / (pkgDef.total_spots || 1)));
                var targetSubtotal = Math.round(packageCount * pkgBaseRate * 100) / 100;

                var activeSlotsList = [];
                for (var pn in slotTotals) {
                    var st = slotTotals[pn];
                    var q = dateRangeValid ? st.total : st.weekly;
                    if (q > 0) {
                        activeSlotsList.push({ pn: pn, q: q, st: st });
                    }
                }

                var allocatedNet = 0.0;
                for (var k = 0; k < activeSlotsList.length; k++) {
                    var item = activeSlotsList[k];
                    var net;
                    if (k === activeSlotsList.length - 1) {
                        net = Math.round((targetSubtotal - allocatedNet) * 100) / 100;
                    } else {
                        net = Math.round((item.q / (activeTotalQty || 1)) * targetSubtotal * 100) / 100;
                    }
                    allocatedNet = Math.round((allocatedNet + net) * 100) / 100;
                    var unitPrice = item.q > 0 ? (Math.round((net / item.q) * 10000) / 10000) : 0;
                    var v = Math.round(net * 0.15 * 100) / 100;
                    var g = Math.round((net + v) * 100) / 100;
                    subtotal = Math.round((subtotal + net) * 100) / 100;
                    tax = Math.round((tax + v) * 100) / 100;
                    grandTotal = Math.round((grandTotal + g) * 100) / 100;
                    localActive.push({
                        part_number: item.pn,
                        product_name: item.pn,
                        product_id: '',
                        unit_price: unitPrice,
                        total_qty: item.q,
                        weekly_qty: item.st.weekly,
                        net_total: net,
                        vat_amt: v,
                        vat_rate: '15.0',
                        gross_total: g,
                        description: RADIO_SLOT_DESCRIPTIONS[item.pn] || ''
                    });
                }
            } else {
                for (var pn in slotTotals) {
                    var st = slotTotals[pn];
                    var q = dateRangeValid ? st.total : st.weekly;
                    if (q > 0) {
                        var net = dateRangeValid ? st.totalNet : st.weeklyNet;
                        net = Math.round(net * 100) / 100;
                        var unitPrice = q > 0 ? (Math.round((net / q) * 10000) / 10000) : (RADIO_SLOT_RATES[pn] || 0);
                        var v = Math.round(net * 0.15 * 100) / 100;
                        var g = Math.round((net + v) * 100) / 100;
                        subtotal = Math.round((subtotal + net) * 100) / 100;
                        tax = Math.round((tax + v) * 100) / 100;
                        grandTotal = Math.round((grandTotal + g) * 100) / 100;
                        localActive.push({
                            part_number: pn,
                            product_name: pn,
                            product_id: '',
                            unit_price: unitPrice,
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

    function applyPackagePreset(pkgKey) {
        if (!pkgKey) {
            scheduleRecalcAndSync(true);
            return;
        }
        fetch('index.php?entryPoint=radio_schedule_ajax_sync&action=get_packages')
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data && data.packages && data.packages[pkgKey]) {
                var pkg = data.packages[pkgKey];

                var startInput = document.getElementById('campaign_start_date_c');
                var endInput = document.getElementById('campaign_end_date_c');
                var sVal = (startInput && startInput.value) ? startInput.value.trim() : '';
                if (!sVal) {
                    var now = new Date();
                    var yr = now.getFullYear();
                    var mo = (now.getMonth() + 1 < 10 ? '0' : '') + (now.getMonth() + 1);
                    var da = (now.getDate() < 10 ? '0' : '') + now.getDate();
                    sVal = yr + '-' + mo + '-' + da;
                    if (startInput) startInput.value = sVal;
                }
                var sDate = parseYmd(sVal);
                if (sDate && endInput) {
                    var daysToAdd = (pkg.type === 'weekly') ? 6 : 27;
                    var eDate = new Date(sDate.getTime() + (daysToAdd * 86400000));
                    var ey = eDate.getUTCFullYear();
                    var em = (eDate.getUTCMonth() + 1 < 10 ? '0' : '') + (eDate.getUTCMonth() + 1);
                    var ed = (eDate.getUTCDate() < 10 ? '0' : '') + eDate.getUTCDate();
                    endInput.value = ey + '-' + em + '-' + ed;
                }

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

    function addRateCardExtraToQuote() {
        var sel = document.getElementById('radio_extra_item_select');
        var qtyEl = document.getElementById('radio_extra_item_qty');
        var msgEl = document.getElementById('radio_extra_added_msg');
        if (!sel || !sel.value) {
            alert('Please select a Rate Card Extra item to add.');
            return;
        }
        var opt = sel.options[sel.selectedIndex];
        var partNum = sel.value;
        var prodId = opt.getAttribute('data-id') || '';
        var prodName = opt.getAttribute('data-name') || partNum;
        var unitPrice = parseFloat(opt.getAttribute('data-price') || '0') || 0.0;
        var prodDesc = opt.getAttribute('data-desc') || '';
        var qty = Math.max(1, parseInt(qtyEl ? qtyEl.value : '1', 10) || 1);

        if (typeof window.insertGroup === 'function' && !document.getElementById('product_group0')) {
            try { window.insertGroup(); } catch (e) {}
        }
        var headEl = document.getElementById('product_group0_head');
        if (headEl) headEl.style.display = '';

        var targetIdx = undefined;
        var partInputs = document.querySelectorAll('input[id^="product_part_number"]');
        for (var i = 0; i < partInputs.length; i++) {
            var m = partInputs[i].id.match(/^product_part_number(\d+)$/);
            if (!m) continue;
            var idx = m[1];
            var delEl = document.getElementById('product_deleted' + idx);
            if (delEl && delEl.value === '1') continue;
            if ((partInputs[i].value || '').trim().toUpperCase() === partNum.toUpperCase()) {
                targetIdx = idx;
                break;
            }
        }

        if (targetIdx === undefined && typeof window.insertProductLine === 'function') {
            try {
                targetIdx = window.insertProductLine('product_group0', '0');
            } catch (e) {}
        }

        if (targetIdx !== undefined) {
            var setF = function(prefix, val) {
                var el = document.getElementById(prefix + targetIdx);
                if (el) el.value = val;
            };
            var net = Math.round(qty * unitPrice * 100) / 100;
            var vat = Math.round(net * 0.15 * 100) / 100;
            setF('product_name', prodName);
            setF('product_part_number', partNum);
            if (prodId) setF('product_product_id', prodId);
            setF('product_product_qty', qty);
            setF('product_product_list_price', unitPrice.toFixed(2));
            setF('product_product_unit_price', unitPrice.toFixed(2));
            setF('product_vat', '15.0');
            setF('product_vat_amt', vat.toFixed(2));
            setF('product_product_total_price', net.toFixed(2));
            setF('product_item_description', prodDesc);

            if (typeof window.calculateLine === 'function') {
                try { window.calculateLine(targetIdx, 'product_'); } catch (e) {}
            }
            if (typeof window.calculateTotal === 'function') {
                try { window.calculateTotal('group0'); } catch (e) {}
                try { window.calculateTotal(); } catch (e) {}
            }
            if (msgEl) {
                msgEl.textContent = '✓ Added ' + qty + 'x ' + prodName;
                msgEl.style.display = 'inline';
                setTimeout(function() { msgEl.style.display = 'none'; }, 3500);
            }
        }
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
            durSelect.addEventListener('change', function() {
                updateCellTooltips();
                scheduleRecalcAndSync(true);
            });
        }

        var catSelect = document.getElementById('industry_category_c');
        if (catSelect) {
            catSelect.addEventListener('change', function() { scheduleRecalcAndSync(true); });
        }

        var pkgSelect = document.getElementById('applied_package_c');
        if (pkgSelect) {
            pkgSelect.addEventListener('change', function() { applyPackagePreset(pkgSelect.value); });
        }

        var addExtraBtn = document.getElementById('radio_add_extra_btn');
        if (addExtraBtn) {
            addExtraBtn.addEventListener('click', function(e) {
                e.preventDefault();
                addRateCardExtraToQuote();
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
                var pkgEl = document.getElementById('applied_package_c');
                if (pkgEl) {
                    pkgEl.value = '';
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

        updateCellTooltips();
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
