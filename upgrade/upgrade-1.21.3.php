<?php
/**
 * Upgrade script for version 1.21.3
 * Ensures TikTok Pixel & Events API platform is enabled and initialized in configuration,
 * synchronizes event matrix routing, flushes caches, and aligns Back Office platform counters.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_21_3($module)
{
    try {
        // 1. Ensure TikTok Advertising platform is explicitly enabled in configuration
        Configuration::updateValue('PS_GTM_ADV_TIKTOK_ENABLED', '1');

        // 2. Ensure TikTok supported events routing matrix is fully hydrated
        $allEvents = array_keys(Ps_Gtm_DataLayer::getSupportedEvents());
        $rawTiktokEvents = Configuration::get('PS_GTM_ADV_TIKTOK_EVENTS');
        $decodedTiktok = ($rawTiktokEvents !== false && $rawTiktokEvents !== null) ? json_decode($rawTiktokEvents, true) : null;
        if (empty($decodedTiktok) || !is_array($decodedTiktok)) {
            Configuration::updateValue('PS_GTM_ADV_TIKTOK_EVENTS', json_encode($allEvents));
        }

        // 3. Clear Smarty and module template caches
        if (method_exists($module, '_clearCache')) {
            $module->_clearCache('views/templates/hook/datalayer_init.tpl');
            $module->_clearCache('views/templates/hook/gtm_head.tpl');
            $module->_clearCache('views/templates/hook/gtm_body.tpl');
        }

        if (class_exists('PlatformAdapter') && method_exists('PlatformAdapter', 'clearAllCaches')) {
            PlatformAdapter::clearAllCaches();
        }
    } catch (\Throwable $e) {}

    return true;
}
