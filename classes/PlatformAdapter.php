<?php
/**
 * PlatformAdapter: Universal PrestaShop Platform & Capability Adapter.
 * Detects runtime environment, provides version-agnostic APIs, and bridges
 * architectural differences across PrestaShop 1.7.8 through 8.2.x and 9.x.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class PlatformAdapter
{
    /**
     * Returns true if running on PrestaShop 1.7.x.
     *
     * @return bool
     */
    public static function isPrestaShop17()
    {
        return version_compare(_PS_VERSION_, '1.7.0.0', '>=') && version_compare(_PS_VERSION_, '8.0.0.0', '<');
    }

    /**
     * Returns true if running on PrestaShop 8.x.
     *
     * @return bool
     */
    public static function isPrestaShop8()
    {
        return version_compare(_PS_VERSION_, '8.0.0.0', '>=') && version_compare(_PS_VERSION_, '9.0.0.0', '<');
    }

    /**
     * Returns true if running on PrestaShop 9.x or higher.
     *
     * @return bool
     */
    public static function isPrestaShop9OrHigher()
    {
        return version_compare(_PS_VERSION_, '9.0.0.0', '>=');
    }

    /**
     * Resolves the authoritative currency precision (number of decimal places)
     * configured for the active shop/currency context across any PrestaShop version.
     *
     * @param Context|null $context
     * @return int
     */
    public static function getCurrencyPrecision(Context $context = null)
    {
        if (!$context) {
            $context = Context::getContext();
        }

        // PrestaShop 1.7.6+ and 8.x/9.x expose precision directly on Currency model
        if ($context && $context->currency && isset($context->currency->precision) && is_numeric($context->currency->precision)) {
            $precision = (int)$context->currency->precision;
            if ($precision >= 0) {
                return $precision;
            }
        }

        // Global PrestaShop configuration fallback
        $configuredPrecision = Configuration::get('PS_PRICE_DISPLAY_PRECISION');
        if ($configuredPrecision !== false && is_numeric($configuredPrecision)) {
            return (int)$configuredPrecision;
        }

        if (defined('_PS_PRICE_DISPLAY_PRECISION_')) {
            return (int)_PS_PRICE_DISPLAY_PRECISION_;
        }

        return 2;
    }

    /**
     * Determines whether product prices should be displayed tax-inclusive for the current customer/group.
     *
     * @param Context|null $context
     * @return bool
     */
    public static function isTaxIncluded(Context $context = null)
    {
        if (!$context) {
            $context = Context::getContext();
        }

        $idCustomer = ($context && $context->customer) ? (int)$context->customer->id : 0;

        if (class_exists('Product') && method_exists('Product', 'getTaxCalculationMethod')) {
            return !Product::getTaxCalculationMethod($idCustomer);
        }

        // Default to tax-inclusive in European stores
        return true;
    }

    /**
     * Resolves the active PrestaShop rounding mode (default: ROUND_HALF_UP).
     *
     * @return int
     */
    public static function getRoundingMode()
    {
        $mode = Configuration::get('PS_PRICE_ROUND_MODE');
        if ($mode !== false && is_numeric($mode)) {
            switch ((int)$mode) {
                case 0: // PS_ROUND_UP
                    return defined('PHP_ROUND_HALF_UP') ? PHP_ROUND_HALF_UP : 1;
                case 1: // PS_ROUND_DOWN
                    return defined('PHP_ROUND_HALF_DOWN') ? PHP_ROUND_HALF_DOWN : 2;
                case 2: // PS_ROUND_HALF_UP
                default:
                    return defined('PHP_ROUND_HALF_UP') ? PHP_ROUND_HALF_UP : 1;
            }
        }

        return defined('PHP_ROUND_HALF_UP') ? PHP_ROUND_HALF_UP : 1;
    }

    /**
     * Automatically keeps ps_module database record synchronized with the module's declared version.
     * Prevents false "Upgrade available" flags in the PrestaShop Module Manager across all versions.
     *
     * @param string $moduleName
     * @param string $currentVersion
     * @return bool
     */
    public static function syncModuleDatabaseVersion($moduleName, $currentVersion)
    {
        if (empty($moduleName) || empty($currentVersion) || !class_exists('Db')) {
            return false;
        }

        try {
            return (bool)Db::getInstance()->execute(
                'UPDATE `' . _DB_PREFIX_ . 'module` SET `version` = \'' . pSQL($currentVersion) . '\' WHERE `name` = \'' . pSQL($moduleName) . '\''
            );
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Clears all layers of PrestaShop cache safely across PrestaShop 1.7.8, 8.x, and 9.x,
     * including Smarty, legacy XML, and Symfony container catalog caches.
     */
    public static function clearAllCaches()
    {
        try {
            // 1. Clear Smarty cache
            if (class_exists('Tools') && method_exists('Tools', 'clearSmartyCache')) {
                @Tools::clearSmartyCache();
            }

            // 2. Clear XML cache
            if (class_exists('Tools') && method_exists('Tools', 'clearXMLCache')) {
                @Tools::clearXMLCache();
            }

            // 3. Clear Symfony Module Catalog cache (PrestaShop 1.7.8 - 8.x / 9.x)
            if (class_exists('\PrestaShop\PrestaShop\Adapter\SymfonyContainer')) {
                try {
                    $container = \PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance();
                    if ($container && $container->has('prestashop.adapter.module.data_provider')) {
                        $dataProvider = $container->get('prestashop.adapter.module.data_provider');
                        if ($dataProvider && method_exists($dataProvider, 'clearCatalogCache')) {
                            @$dataProvider->clearCatalogCache();
                        }
                    }
                } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {}
    }
}
