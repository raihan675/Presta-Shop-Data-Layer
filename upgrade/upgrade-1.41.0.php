<?php
/**
 * Upgrade script for version 1.41.0
 * Adds OpenAI / ChatGPT Ads DataLayer Integration with ISO 4217 Minor Currency Units.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_41_0($module)
{
    try {
        // Register default configuration settings for OpenAI Ads if not present
        if (class_exists('Configuration')) {
            if (Configuration::get('PS_GTM_ADV_OPENAI_ENABLED') === false) {
                Configuration::updateValue('PS_GTM_ADV_OPENAI_ENABLED', '1');
            }

            if (Configuration::get('PS_GTM_ADV_OPENAI_EVENTS') === false) {
                $allEvents = [
                    'view_item_list',
                    'view_search_results',
                    'view_item',
                    'add_to_cart',
                    'remove_from_cart',
                    'view_cart',
                    'begin_checkout',
                    'add_shipping_info',
                    'add_payment_info',
                    'purchase',
                    'add_to_wishlist',
                ];
                Configuration::updateValue('PS_GTM_ADV_OPENAI_EVENTS', json_encode($allEvents));
            }
        }

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

        // Clear module and template caches to force reload of updated hooks and assets
        if (is_object($module)) {
            if (method_exists($module, 'clearModuleCache')) {
                $module->clearModuleCache();
            }
            if (method_exists($module, '_clearCache')) {
                $module->_clearCache('views/templates/admin/configuration.tpl');
                $module->_clearCache('views/templates/hook/datalayer_init.tpl');
                $module->_clearCache('views/templates/hook/gtm_head.tpl');
                $module->_clearCache('views/templates/hook/gtm_body.tpl');
                $module->_clearCache('*');
            }
        }

        if (class_exists('PlatformAdapter') && method_exists('PlatformAdapter', 'clearAllCaches')) {
            PlatformAdapter::clearAllCaches();
        }
    } catch (\Throwable $e) {}

    return true;
}
