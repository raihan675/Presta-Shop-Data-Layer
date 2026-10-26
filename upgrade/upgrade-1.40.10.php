<?php
/**
 * Upgrade script for version 1.40.10
 * PrestaShop DataLayer - GA4, Meta, Pinterest, TikTok, Google & Microsoft Ads
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_40_10($module)
{
    // Auto-register any missing core or back-office hooks
    $hooks = [
        'displayHeader',
        'displayAfterBodyOpeningTag',
        'displayOrderConfirmation',
        'displayBackOfficeTop',
        'displayBackOfficeHeader',
        'actionCartUpdateQuantityBefore',
        'actionCarrierProcess',
        'actionOrderSlipAdd',
        'actionValidateOrder',
        'actionOrderStatusPostUpdate',
    ];

    foreach ($hooks as $h) {
        if (!$module->isRegisteredInHook($h)) {
            $module->registerHook($h);
        }
    }

    // Clear template caches to force reload of updated hooks and assets
    if (method_exists($module, '_clearCache')) {
        $module->_clearCache('views/templates/admin/configuration.tpl');
        $module->_clearCache('views/templates/hook/datalayer_init.tpl');
        $module->_clearCache('views/templates/hook/gtm_head.tpl');
        $module->_clearCache('views/templates/hook/gtm_body.tpl');
    }

    if (class_exists('PlatformAdapter') && method_exists('PlatformAdapter', 'clearAllCaches')) {
        PlatformAdapter::clearAllCaches();
    }

    return true;
}
