<?php
// PUKfm 93.6 - Custom Language Overrides (South African Rand and 15% VAT)
if (!isset($app_list_strings)) {
    $app_list_strings = [];
}
if (!isset($app_list_strings['vat_list'])) {
    $app_list_strings['vat_list'] = [];
}
$app_list_strings['vat_list']['15.0'] = '15%';
