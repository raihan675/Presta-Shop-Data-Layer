<?php
/**
 * Upgrade script for version 1.32.0
 * Adds GA4 Measurement ID parameter encoding and Server GTM Preview Header injection
 * for native sGTM GA4 Client acceptance (HTTP 200) and instant real-time debugging.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_32_0($module)
{
    try {
        // Register any missing server-to-server hooks
        if (is_object($module)) {
            if (!$module->isRegisteredInHook('actionOrderStatusPostUpdate')) {
                $module->registerHook('actionOrderStatusPostUpdate');
            }
            if (!$module->isRegisteredInHook('actionOrderSlipAdd')) {
                $module->registerHook('actionOrderSlipAdd');
            }
            if (!$module->isRegisteredInHook('actionValidateOrder')) {
                $module->registerHook('actionValidateOrder');
            }
            if (!$module->isRegisteredInHook('displayBackOfficeTop')) {
                $module->registerHook('displayBackOfficeTop');
            }
            if (!$module->isRegisteredInHook('displayBackOfficeHeader')) {
                $module->registerHook('displayBackOfficeHeader');
            }
        }

        if (class_exists('Configuration')) {
            if (Configuration::get('PS_SGTM_GA4_MEASUREMENT_ID') === false) {
                Configuration::updateValue('PS_SGTM_GA4_MEASUREMENT_ID', '');
            }
            if (Configuration::get('PS_SGTM_PREVIEW_HEADER') === false) {
                Configuration::updateValue('PS_SGTM_PREVIEW_HEADER', '');
            }
        }

        if (is_object($module) && method_exists($module, 'clearModuleCache')) {
            $module->clearModuleCache();
        }

        if (class_exists('PlatformAdapter') && method_exists('PlatformAdapter', 'clearAllCaches')) {
            PlatformAdapter::clearAllCaches();
        }
    } catch (\Throwable $e) {}

    return true;
}
