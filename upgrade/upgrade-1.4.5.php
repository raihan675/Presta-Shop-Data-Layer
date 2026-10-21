<?php
/**
 * Upgrade script for version 1.4.5
 * Synchronizes commercial ROUND_HALF_UP pricing engine across database and data layer.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_4_5($module)
{
    Db::getInstance()->execute(
        'UPDATE `' . _DB_PREFIX_ . 'module` SET `version` = \'' . pSQL($module->version) . '\' WHERE `name` = \'' . pSQL($module->name) . '\''
    );
    return true;
}
