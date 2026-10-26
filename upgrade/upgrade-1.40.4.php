<?php
/**
 * Upgrade script for version 1.40.4
 * PrestaShop DataLayer - GA4, Meta, Pinterest, TikTok, Google & Microsoft Ads
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_40_4($module)
{
    // Clean up deprecated events (page_view and select_item) from stored active platform arrays if present
    $platforms = ['META', 'TIKTOK', 'GADS', 'PINTEREST', 'MICROSOFT'];
    foreach ($platforms as $p) {
        $raw = Configuration::get('PS_GTM_ADV_' . $p . '_EVENTS');
        if ($raw !== false && $raw !== null) {
            $events = json_decode($raw, true);
            if (is_array($events)) {
                $cleaned = array_values(array_diff($events, ['page_view', 'select_item']));
                Configuration::updateValue('PS_GTM_ADV_' . $p . '_EVENTS', json_encode($cleaned));
            }
        }
    }

    $rawActive = Configuration::get('PS_GTM_ACTIVE_EVENTS');
    if ($rawActive !== false && $rawActive !== null) {
        $activeEvts = json_decode($rawActive, true);
        if (is_array($activeEvts)) {
            $cleanedActive = array_values(array_diff($activeEvts, ['page_view', 'select_item']));
            Configuration::updateValue('PS_GTM_ACTIVE_EVENTS', json_encode($cleanedActive));
        }
    }

    // Flush Smarty and module template caches
    if (method_exists($module, '_clearCache')) {
        $module->_clearCache('views/templates/admin/configuration.tpl');
    }

    if (class_exists('PlatformAdapter') && method_exists('PlatformAdapter', 'clearAllCaches')) {
        PlatformAdapter::clearAllCaches();
    }

    return true;
}
