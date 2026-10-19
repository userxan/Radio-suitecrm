<?php

if (!isset($hook_array) || !is_array($hook_array)) {
    $hook_array = [];
}
if (!isset($hook_array['after_retrieve']) || !is_array($hook_array['after_retrieve'])) {
    $hook_array['after_retrieve'] = [];
}
if (!isset($hook_array['after_save']) || !is_array($hook_array['after_save'])) {
    $hook_array['after_save'] = [];
}

$hook_array['after_retrieve'][] = [
    10,
    'Load Radio Quote Weekly Schedule Custom Fields',
    'custom/modules/AOS_Quotes/RadioQuoteScheduleProducts.php',
    'RadioQuoteScheduleProducts',
    'loadScheduleFields',
];

$hook_array['after_save'][] = [
    90,
    'Sync Radio Quote Weekly Schedule Products',
    'custom/modules/AOS_Quotes/RadioQuoteScheduleProducts.php',
    'RadioQuoteScheduleProducts',
    'syncSelectedProducts',
];
