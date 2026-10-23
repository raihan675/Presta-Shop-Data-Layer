<?php
/**
 * Upgrade script for version 1.16.0
 * Aligns Pinterest ecommerce data layer with official Pinterest Tag standards:
 * updates standard event names (InitiateCheckout, AddPaymentInfo, AddToWishList),
 * removes contents array in favor of strictly official line_items,
 * and adds SHA-256 hashed user_data (em, ph, fn, ln, ct, st, zp, country) for Enhanced Matching.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_16_0($module)
{
    // Clear Smarty and module template caches
    if (method_exists($module, '_clearCache')) {
        $module->_clearCache('views/templates/hook/datalayer_init.tpl');
        $module->_clearCache('views/templates/hook/gtm_head.tpl');
        $module->_clearCache('views/templates/hook/gtm_body.tpl');
    }

    if (class_exists('PlatformAdapter') && method_exists('PlatformAdapter', 'clearAllCaches')) {
        PlatformAdapter::clearAllCaches();
    }

    return true;
}
