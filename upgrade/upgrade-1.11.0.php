<?php
/**
 * Upgrade script for version 1.11.0
 * Initializes Advertising Platform Data Layer Settings (Master, Meta, TikTok, Google Ads, and 11-Event Routing Matrix).
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_11_0($module)
{
    try {
        if (Configuration::get('PS_GTM_ADV_MASTER_ENABLED') === false) {
            Configuration::updateValue('PS_GTM_ADV_MASTER_ENABLED', '1');
        }
        if (Configuration::get('PS_GTM_ADV_META_ENABLED') === false) {
            Configuration::updateValue('PS_GTM_ADV_META_ENABLED', '1');
        }
        if (Configuration::get('PS_GTM_ADV_TIKTOK_ENABLED') === false) {
            Configuration::updateValue('PS_GTM_ADV_TIKTOK_ENABLED', '1');
        }
        if (Configuration::get('PS_GTM_ADV_GADS_ENABLED') === false) {
            Configuration::updateValue('PS_GTM_ADV_GADS_ENABLED', '1');
        }

        $allEvents = [
            'view_item_list',
            'select_item',
            'view_item',
            'add_to_cart',
            'remove_from_cart',
            'view_cart',
            'begin_checkout',
            'add_shipping_info',
            'add_payment_info',
            'purchase',
            'add_to_wishlist'
        ];

        if (Configuration::get('PS_GTM_ADV_META_EVENTS') === false) {
            Configuration::updateValue('PS_GTM_ADV_META_EVENTS', json_encode($allEvents));
        }
        if (Configuration::get('PS_GTM_ADV_TIKTOK_EVENTS') === false) {
            Configuration::updateValue('PS_GTM_ADV_TIKTOK_EVENTS', json_encode($allEvents));
        }
        if (Configuration::get('PS_GTM_ADV_GADS_EVENTS') === false) {
            Configuration::updateValue('PS_GTM_ADV_GADS_EVENTS', json_encode($allEvents));
        }

        $adapterPath = dirname(__FILE__) . '/../classes/PlatformAdapter.php';
        if (file_exists($adapterPath)) {
            require_once $adapterPath;
            if (class_exists('PlatformAdapter')) {
                PlatformAdapter::syncModuleDatabaseVersion($module->name, $module->version);
                PlatformAdapter::clearAllCaches();
            }
        }
    } catch (\Throwable $e) {}
    return true;
}
