<?php
/**
 * Advanced Google Tag Manager & GA4 DataLayer Module for PrestaShop
 * Compatibility: PrestaShop 1.7.8.0 - 8.2.x / 9.x
 * Universal Backend-Driven Architecture — Zero DOM Scraping.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/PlatformAdapter.php';
require_once __DIR__ . '/classes/PricingEngine.php';
require_once __DIR__ . '/classes/CanonicalDataModel.php';
require_once __DIR__ . '/classes/ProductIdentityResolver.php';
require_once __DIR__ . '/classes/CustomerDataEngine.php';
require_once __DIR__ . '/classes/PayloadBuilder.php';
require_once __DIR__ . '/classes/DeduplicationManager.php';
require_once __DIR__ . '/classes/WishlistIntegrationLayer.php';
require_once __DIR__ . '/classes/UpdateManager.php';

class Ps_Gtm_DataLayer extends Module
{
    public function __construct()
    {
        $this->name = 'ps_gtm_datalayer';
        $this->tab = 'analytics_stats';
        $this->version = '1.10.3';
        $this->author = 'Analytics Engineer';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7.8.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Enterprise GA4, Meta, TikTok & Google Ads DataLayer');
        $this->description = $this->l('Universal backend-driven DataLayer with direct Meta, TikTok, and Google Ads dynamic catalog matching, zero DOM dependency, authoritative database product identifiers, and sGTM support.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');

        // Keep ps_module database version table synchronized with active version
        if (class_exists('Db')) {
            PlatformAdapter::syncModuleDatabaseVersion($this->name, $this->version);
        }

        // Auto-register back-office hooks seamlessly when admin visits Back Office
        if (defined('_PS_ADMIN_DIR_') && !Configuration::get('PS_GTM_BO_HOOKS_V143')) {
            if (!$this->isRegisteredInHook('displayBackOfficeTop')) {
                $this->registerHook('displayBackOfficeTop');
            }
            if (!$this->isRegisteredInHook('displayBackOfficeHeader')) {
                $this->registerHook('displayBackOfficeHeader');
            }
            Configuration::updateValue('PS_GTM_BO_HOOKS_V143', 1);
        }
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayAfterBodyOpeningTag')
            && $this->registerHook('displayOrderConfirmation')
            && $this->registerHook('actionCartUpdateQuantityBefore')
            && $this->registerHook('displayBackOfficeTop')
            && $this->registerHook('displayBackOfficeHeader')
            && DeduplicationManager::createDatabaseTable()
            && Configuration::updateValue('PS_GTM_CONTAINER_ID', '')
            && Configuration::updateValue('PS_SGTM_TRANSPORT_URL', '')
            && Configuration::updateValue('PS_GTM_CONSENT_MODE', '1')
            && Configuration::updateValue('PS_GTM_EVENT_PREFIX', '')
            && Configuration::updateValue('PS_GTM_CATALOG_ID_STRATEGY', 'composite');
    }

    public function uninstall()
    {
        return parent::uninstall()
            && DeduplicationManager::dropDatabaseTable()
            && Configuration::deleteByName('PS_GTM_CONTAINER_ID')
            && Configuration::deleteByName('PS_SGTM_TRANSPORT_URL')
            && Configuration::deleteByName('PS_GTM_CONSENT_MODE')
            && Configuration::deleteByName('PS_GTM_EVENT_PREFIX')
            && Configuration::deleteByName('PS_GTM_CATALOG_ID_STRATEGY');
    }

    public function getContent()
    {
        // Keep database version synchronized and cache fresh
        PlatformAdapter::syncModuleDatabaseVersion($this->name, $this->version);

        // Auto-register displayBackOfficeTop if upgrading from earlier version
        if (!$this->isRegisteredInHook('displayBackOfficeTop')) {
            $this->registerHook('displayBackOfficeTop');
        }

        $output = '';
        $updateManager = new UpdateManager($this);

        // Display post-update confirmation if redirected
        if (Tools::getValue('module_updated')) {
            $updatedVer = Tools::getValue('new_version', $this->version);
            $output .= $this->displayConfirmation(sprintf(
                $this->l('Enterprise GA4, Meta, TikTok & Google Ads DataLayer successfully updated to version %s! All your settings were preserved.'),
                htmlspecialchars($updatedVer)
            ));
        }

        // Handle 1-Click Update Execution
        if (Tools::isSubmit('submitRunModuleUpdate')) {
            try {
                $downloadUrl = Tools::getValue('update_download_url');
                $checksum = Tools::getValue('update_checksum');
                $versionTarget = Tools::getValue('update_version', 'latest');
                $updateResult = $updateManager->executeUpdate($downloadUrl, $checksum, $versionTarget);

                if (!empty($updateResult['success'])) {
                    if ($versionTarget !== 'latest') {
                        $this->version = $versionTarget;
                    }
                    UpdateManager::clearUpdateCache();
                    PlatformAdapter::syncModuleDatabaseVersion($this->name, $this->version);
                    PlatformAdapter::clearAllCaches();

                    $cleanUrl = $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name . '&module_updated=1&new_version=' . urlencode($this->version);
                    Tools::redirectAdmin($cleanUrl);
                    return '';
                } else {
                    $errMsg = !empty($updateResult['message']) ? $updateResult['message'] : 'Update failed.';
                    $output .= $this->displayError($this->l($errMsg));
                }
            } catch (\Throwable $e) {
                $output .= $this->displayError('Update error: ' . $e->getMessage());
            }
        }

        // Handle Settings Submission
        if (Tools::isSubmit('submit' . $this->name)) {
            $gtmId = trim(Tools::getValue('PS_GTM_CONTAINER_ID'));
            $sgtmUrl = trim(Tools::getValue('PS_SGTM_TRANSPORT_URL'));
            $consent = (int)Tools::getValue('PS_GTM_CONSENT_MODE');
            $rawPrefix = Tools::getValue('PS_GTM_EVENT_PREFIX');
            $prefix = self::normalizePrefix($rawPrefix);
            $catalogStrategy = trim((string)Tools::getValue('PS_GTM_CATALOG_ID_STRATEGY'));
            if (!in_array($catalogStrategy, ['composite', 'id_product', 'reference_sku'])) {
                $catalogStrategy = 'composite';
            }

            if (!empty($gtmId) && !preg_match('/^GTM-[A-Z0-9]+$/', $gtmId)) {
                $output .= $this->displayError($this->l('Invalid Google Tag Manager Container ID format (e.g., GTM-XXXXXXX).'));
            } else {
                Configuration::updateValue('PS_GTM_CONTAINER_ID', $gtmId);
                Configuration::updateValue('PS_SGTM_TRANSPORT_URL', $sgtmUrl);
                Configuration::updateValue('PS_GTM_CONSENT_MODE', $consent);
                Configuration::updateValue('PS_GTM_EVENT_PREFIX', $prefix);
                Configuration::updateValue('PS_GTM_CATALOG_ID_STRATEGY', $catalogStrategy);
                $output .= $this->displayConfirmation($this->l('Settings successfully saved.'));
            }
        }

        $updateInfo = $updateManager->checkForUpdates(true);
        $output .= $this->renderUpdateBanner($updateInfo);

        return $output . $this->renderForm();
    }

    private function renderUpdateBanner($updateInfo)
    {
        // When there is no update available, do not render any notification bar or panel.
        // It remains completely hidden until a new version is published.
        if (empty($updateInfo['has_update'])) {
            return '';
        }

        $html = '<div class="panel">';
        $html .= '<h3><i class="icon-refresh"></i> ' . $this->l('Module Version & Update Status') . '</h3>';
        $html .= '<div class="alert alert-warning">';
        $html .= '<h4><strong>' . sprintf($this->l('New version %s is available!'), htmlspecialchars($updateInfo['latest_version'])) . '</strong></h4>';
        $html .= '<p>' . nl2br(htmlspecialchars($updateInfo['release_notes'])) . '</p>';
        $html .= '<hr>';
        $updateActionUrl = $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name;
        $html .= '<form action="' . Tools::safeOutput($updateActionUrl) . '" method="post" style="display:inline-block;">';
        $html .= '<input type="hidden" name="update_download_url" value="' . htmlspecialchars($updateInfo['download_url']) . '">';
        $html .= '<input type="hidden" name="update_checksum" value="' . htmlspecialchars($updateInfo['checksum']) . '">';
        $html .= '<input type="hidden" name="update_version" value="' . htmlspecialchars($updateInfo['latest_version']) . '">';
        $html .= '<button type="submit" name="submitRunModuleUpdate" class="btn btn-success btn-lg" onclick="return confirm(\'' . $this->l('Are you sure you want to upgrade the module now? All your settings will be preserved.') . '\');">';
        $html .= '<i class="icon-download"></i> ' . $this->l('1-Click Update to Version ') . htmlspecialchars($updateInfo['latest_version']);
        $html .= '</button>';
        $html .= '</form>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    public function hookDisplayBackOfficeTop($params)
    {
        // Auto-register displayBackOfficeTop if not registered
        if (!$this->isRegisteredInHook('displayBackOfficeTop')) {
            $this->registerHook('displayBackOfficeTop');
        }

        // 1. Handle 1-Click Update if triggered from Module Manager or any admin page
        if (Tools::isSubmit('submitRunModuleUpdate')) {
            try {
                $downloadUrl = Tools::getValue('update_download_url');
                $checksum = Tools::getValue('update_checksum');
                $versionTarget = Tools::getValue('update_version', 'latest');

                $updateManager = new UpdateManager($this);
                $updateResult = $updateManager->executeUpdate($downloadUrl, $checksum, $versionTarget);

                if (!empty($updateResult['success'])) {
                    if ($versionTarget !== 'latest') {
                        $this->version = $versionTarget;
                    }
                    UpdateManager::clearUpdateCache();
                    PlatformAdapter::syncModuleDatabaseVersion($this->name, $this->version);
                    PlatformAdapter::clearAllCaches();

                    $cleanUrl = $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name . '&module_updated=1&new_version=' . urlencode($this->version);
                    Tools::redirectAdmin($cleanUrl);
                    return '';
                } else {
                    $errMsg = !empty($updateResult['message']) ? $updateResult['message'] : 'Update failed.';
                    if (isset($this->context->controller->errors) && is_array($this->context->controller->errors)) {
                        $this->context->controller->errors[] = $this->l($errMsg);
                    }
                }
            } catch (\Throwable $e) {
                if (isset($this->context->controller->errors) && is_array($this->context->controller->errors)) {
                    $this->context->controller->errors[] = 'Update error: ' . $e->getMessage();
                }
            }
        }

        // 2. Target Module Manager and Module List pages
        $controllerName = Tools::getValue('controller');
        $tabName = Tools::getValue('tab');
        $requestUri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
        $isModuleManager = (
            $controllerName === 'AdminModules' 
            || $controllerName === 'AdminModulesManage' 
            || $controllerName === 'AdminModulesUpdates' 
            || $controllerName === 'AdminModulesNotifications'
            || $tabName === 'AdminModules'
            || $tabName === 'AdminModulesManage'
            || stripos($requestUri, 'improve/modules') !== false
            || stripos($requestUri, 'module/manage') !== false
            || stripos($requestUri, 'controller=AdminModules') !== false
        );

        // Do not render duplicate banner if already on module configuration page
        if (Tools::getValue('configure') === $this->name) {
            return '';
        }

        if (!$isModuleManager) {
            return '';
        }

        $updateManager = new UpdateManager($this);
        $updateInfo = $updateManager->checkForUpdates();
        if (empty($updateInfo['has_update'])) {
            return '';
        }

        $updateActionUrl = $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name;

        $this->context->smarty->assign([
            'module_name' => $this->name,
            'module_display_name' => $this->displayName,
            'current_version' => $this->version,
            'latest_version' => $updateInfo['latest_version'],
            'release_notes' => $updateInfo['release_notes'],
            'download_url' => $updateInfo['download_url'],
            'checksum' => $updateInfo['checksum'],
            'update_action_url' => $updateActionUrl,
            'current_request_url' => Tools::safeOutput($_SERVER['REQUEST_URI']),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/module_manager_update_banner.tpl');
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        return '';
    }

    public static function normalizePrefix($prefix)
    {
        if (empty($prefix)) {
            return '';
        }

        $cleaned = trim((string)$prefix);
        $cleaned = preg_replace('/[\s\.\-]+/', '_', $cleaned);
        $cleaned = preg_replace('/[^a-zA-Z0-9_]/', '', $cleaned);
        $cleaned = trim($cleaned, '_');

        return $cleaned;
    }

    public static function formatEventName($originalEventName, $prefix = null)
    {
        if ($prefix === null) {
            $prefix = Configuration::get('PS_GTM_EVENT_PREFIX');
        }

        $prefix = self::normalizePrefix($prefix);

        if (empty($prefix)) {
            return (string)$originalEventName;
        }

        if (strpos((string)$originalEventName, $prefix . '_') === 0) {
            return (string)$originalEventName;
        }

        return $prefix . '_' . (string)$originalEventName;
    }

    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit' . $this->name;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => [
                'PS_GTM_CONTAINER_ID' => Configuration::get('PS_GTM_CONTAINER_ID'),
                'PS_SGTM_TRANSPORT_URL' => Configuration::get('PS_SGTM_TRANSPORT_URL'),
                'PS_GTM_EVENT_PREFIX' => Configuration::get('PS_GTM_EVENT_PREFIX'),
                'PS_GTM_CONSENT_MODE' => Configuration::get('PS_GTM_CONSENT_MODE'),
                'PS_GTM_CATALOG_ID_STRATEGY' => Configuration::get('PS_GTM_CATALOG_ID_STRATEGY') ?: 'composite',
            ],
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getConfigForm()]);
    }

    protected function getConfigForm()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Google Tag Manager & DataLayer Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'name' => 'PS_GTM_CONTAINER_ID',
                        'label' => $this->l('GTM Container ID'),
                        'hint' => $this->l('Enter your primary web container ID from Google Tag Manager (e.g. GTM-XXXXXXX).'),
                        'desc' => $this->l('Your primary web container ID (e.g., GTM-XXXXXXX).'),
                        'size' => 20,
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'name' => 'PS_GTM_EVENT_PREFIX',
                        'label' => $this->l('Data Layer Event Prefix'),
                        'hint' => $this->l('Custom prefix prepended to all dataLayer event names (e.g. raihan_view_item). Leave empty for standard GA4 event names.'),
                        'desc' => $this->l('Custom prefix for dataLayer events (e.g. raihan, masum, client_a). Leave blank for standard GA4 event names.'),
                        'placeholder' => $this->l('Enter your custom prefix (e.g., raihan)'),
                        'size' => 30,
                    ],
                    [
                        'type' => 'select',
                        'name' => 'PS_GTM_CATALOG_ID_STRATEGY',
                        'label' => $this->l('Product Catalog Identifier Strategy'),
                        'hint' => $this->l('Choose which product ID format is sent to advertising feeds (Meta, TikTok, Google Ads).'),
                        'desc' => $this->renderCatalogStrategyHelp(),
                        'options' => [
                            'query' => [
                                [
                                    'id' => 'composite',
                                    'name' => $this->l('Composite Database ID: {id_product}_{id_product_attribute} (Recommended / PrestaShop Standard)'),
                                ],
                                [
                                    'id' => 'id_product',
                                    'name' => $this->l('Base Product ID: {id_product} (Always parent database ID)'),
                                ],
                                [
                                    'id' => 'reference_sku',
                                    'name' => $this->l('Reference / SKU: {sku} (Matches physical barcode or catalog SKU)'),
                                ],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'name' => 'PS_SGTM_TRANSPORT_URL',
                        'label' => $this->l('Server-Side GTM Transport URL (Optional)'),
                        'hint' => $this->l('Custom domain endpoint for Server-Side GTM (e.g. https://gtm.yourdomain.com or Stape endpoint).'),
                        'desc' => $this->l('Custom domain endpoint for Server-Side GTM (e.g. https://gtm.yourdomain.com or Stape endpoint).'),
                        'size' => 50,
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable Google Consent Mode v2'),
                        'name' => 'PS_GTM_CONSENT_MODE',
                        'hint' => $this->l('Initializes default denied consent states prior to tag loading for GDPR and DMA compliance.'),
                        'is_bool' => true,
                        'desc' => $this->l('Initialize default denied consent states prior to tag loading for GDPR/DMA compliance.'),
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Enabled')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('Disabled')],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save Settings'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];
    }

    private function renderCatalogStrategyHelp()
    {
        $strategies = [
            'composite' => [
                'id' => 'composite',
                'name' => 'Composite Database ID: {id_product}_{id_product_attribute}',
                'badge' => $this->l('Recommended for 90% of stores'),
                'badge_class' => 'label-success',
                'icon_color' => '#2980b9',
                'desc' => $this->l('Use for 90% of stores. Select this if your feed module (Google Merchant Center Pro, Meta/Facebook, TikTok, Channable) exports variants as individual items (e.g. 68_299) and simple products as 1033. Enables exact variant-level dynamic retargeting.')
            ],
            'id_product' => [
                'id' => 'id_product',
                'name' => 'Base Product ID: {id_product}',
                'badge' => $this->l('Parent-only catalogs'),
                'badge_class' => 'label-warning',
                'icon_color' => '#f39c12',
                'desc' => $this->l('Use if your feed exports only parent products (1 row per product, ignoring variants). Guarantees 100% catalog match rate if your catalog only contains parent IDs (e.g. 68).')
            ],
            'reference_sku' => [
                'id' => 'reference_sku',
                'name' => 'Reference / SKU: {sku}',
                'badge' => $this->l('ERP / Barcode feeds'),
                'badge_class' => 'label-info',
                'icon_color' => '#27ae60',
                'desc' => $this->l('Use if your store is synced with an ERP/PIM (like SAP, NetSuite, Odoo) and your catalog feeds index items by physical SKU/Reference (e.g. FIT09-BLUE-M). Automatically falls back to database ID if SKU is missing.')
            ],
        ];

        $html = '<div id="ps-gtm-strategies-container" style="margin-top: 10px; max-width: 800px;">';
        $html .= '<p class="text-muted" style="margin-bottom: 8px; font-size: 12px;">' . $this->l('Select your catalog strategy below or in the dropdown above. Hover over any option to view feed compatibility details:') . '</p>';
        $html .= '<div style="display: flex; flex-direction: column; gap: 8px;">';

        foreach ($strategies as $key => $s) {
            $html .= '<div class="ps-gtm-strategy-card" data-key="' . htmlspecialchars($key) . '" style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: #fff; border: 1px solid #d5dbdb; border-radius: 6px; cursor: pointer; transition: all 0.2s ease; position: relative;">';
            $html .= '<div style="display: flex; align-items: center; gap: 10px;">';
            $html .= '<i class="icon-circle-blank ps-gtm-card-radio" style="font-size: 18px; color: #bdc3c7;"></i>';
            $html .= '<div>';
            $html .= '<strong class="ps-gtm-card-name" style="color: #2c3e50; font-size: 13px;">' . htmlspecialchars($s['name']) . '</strong>';
            $html .= '<span class="label ' . htmlspecialchars($s['badge_class']) . '" style="font-size: 10px; margin-left: 8px; padding: 3px 8px;">' . htmlspecialchars($s['badge']) . '</span>';
            $html .= '</div>';
            $html .= '</div>';

            $html .= '<div class="ps-gtm-card-hover-box" style="display: flex; align-items: center; gap: 4px; color: #7f8c8d; font-size: 11px; background: #f8f9fa; padding: 4px 10px; border-radius: 4px; border: 1px solid #eaeded;">';
            $html .= '<i class="icon-info-sign" style="color: ' . htmlspecialchars($s['icon_color']) . ';"></i>';
            $html .= '<span>' . $this->l('Hover for details') . '</span>';
            $html .= '</div>';

            // Popover Tooltip
            $html .= '<div class="ps-gtm-card-tooltip" style="display: none; position: absolute; left: 0; top: 100%; margin-top: 6px; width: 100%; background: #ffffff; border: 1px solid ' . htmlspecialchars($s['icon_color']) . '; border-radius: 6px; box-shadow: 0 8px 24px rgba(0,0,0,0.18); padding: 12px 16px; z-index: 99999;">';
            $html .= '<h5 style="margin: 0 0 6px 0; font-weight: bold; color: ' . htmlspecialchars($s['icon_color']) . '; font-size: 13px;">' . htmlspecialchars($s['name']) . '</h5>';
            $html .= '<p style="margin: 0; font-size: 12px; color: #4a5568; line-height: 1.5;">' . $s['desc'] . '</p>';
            $html .= '</div>';

            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';

        $html .= '<script type="text/javascript">
            (function() {
                function syncStrategyCards() {
                    var select = document.querySelector("select[name=\'PS_GTM_CATALOG_ID_STRATEGY\']");
                    if (!select) return;
                    var selectedVal = select.value || "composite";
                    var cards = document.querySelectorAll(".ps-gtm-strategy-card");
                    for (var i = 0; i < cards.length; i++) {
                        var card = cards[i];
                        var key = card.getAttribute("data-key");
                        var radio = card.querySelector(".ps-gtm-card-radio");
                        var nameEl = card.querySelector(".ps-gtm-card-name");
                        if (key === selectedVal) {
                            card.style.background = "#ebf5fb";
                            card.style.borderColor = "#2980b9";
                            card.style.borderWidth = "2px";
                            if (radio) {
                                radio.className = "icon-check-circle ps-gtm-card-radio";
                                radio.style.color = "#2980b9";
                            }
                            if (nameEl) nameEl.style.color = "#1f618d";
                        } else {
                            card.style.background = "#ffffff";
                            card.style.borderColor = "#d5dbdb";
                            card.style.borderWidth = "1px";
                            if (radio) {
                                radio.className = "icon-circle-blank ps-gtm-card-radio";
                                radio.style.color = "#bdc3c7";
                            }
                            if (nameEl) nameEl.style.color = "#2c3e50";
                        }
                    }
                }

                function setupCardListeners() {
                    var cards = document.querySelectorAll(".ps-gtm-strategy-card");
                    var select = document.querySelector("select[name=\'PS_GTM_CATALOG_ID_STRATEGY\']");
                    for (var i = 0; i < cards.length; i++) {
                        (function(card) {
                            var tooltip = card.querySelector(".ps-gtm-card-tooltip");
                            card.addEventListener("mouseenter", function() {
                                if (tooltip) tooltip.style.display = "block";
                            });
                            card.addEventListener("mouseleave", function() {
                                if (tooltip) tooltip.style.display = "none";
                            });
                            card.addEventListener("click", function(e) {
                                var key = card.getAttribute("data-key");
                                if (select && select.value !== key) {
                                    select.value = key;
                                    var evt = document.createEvent("HTMLEvents");
                                    evt.initEvent("change", false, true);
                                    select.dispatchEvent(evt);
                                    syncStrategyCards();
                                }
                            });
                        })(cards[i]);
                    }

                    if (select) {
                        select.addEventListener("change", syncStrategyCards);
                    }
                    syncStrategyCards();
                }

                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", setupCardListeners);
                } else {
                    setupCardListeners();
                }
            })();
        </script>';

        return $html;
    }

    public function hookActionCartUpdateQuantityBefore($params)
    {
        if (!isset($params['product']) || !Validate::isLoadedObject($params['product'])) {
            return;
        }

        $idAttr = isset($params['id_product_attribute']) ? (int)$params['id_product_attribute'] : 0;
        $operator = isset($params['operator']) ? (string)$params['operator'] : 'up';
        $quantity = isset($params['quantity']) ? (int)$params['quantity'] : 1;
        if ($quantity <= 0) {
            $quantity = 1;
        }

        $eventPrefix = Configuration::get('PS_GTM_EVENT_PREFIX');
        $payloadBuilder = new PayloadBuilder($this->context, $eventPrefix);
        $item = $payloadBuilder->formatItem($params['product'], $idAttr, $quantity);

        $canonicalEvent = ($operator === 'down') ? 'remove_from_cart' : 'add_to_cart';
        $this->queuePendingEvent($canonicalEvent, $item);
    }

    public function queuePendingEvent($canonicalEvent, $item)
    {
        $events = [];
        if (!empty($this->context->cookie->ps_gtm_pending_events)) {
            $decoded = json_decode($this->context->cookie->ps_gtm_pending_events, true);
            if (is_array($decoded)) {
                $events = $decoded;
            }
        }

        $eventPrefix = Configuration::get('PS_GTM_EVENT_PREFIX');
        $precision = PlatformAdapter::getCurrencyPrecision($this->context);
        $totalVal = CanonicalDataModel::calculateItemsValue([$item], $precision);
        $currencyCode = $this->context->currency ? (string)$this->context->currency->iso_code : 'EUR';

        $payloadBuilder = new PayloadBuilder($this->context, $eventPrefix);
        $platformNamespaces = $payloadBuilder->buildPlatformNamespaces($canonicalEvent, [$item], $currencyCode, $totalVal);

        $events[] = array_merge([
            'event' => self::formatEventName($canonicalEvent, $eventPrefix),
            'original_event' => $canonicalEvent,
            'ecommerce' => [
                'currency' => $currencyCode,
                'value' => $totalVal,
                'items' => [$item],
            ],
        ], $platformNamespaces);

        $this->context->cookie->ps_gtm_pending_events = json_encode($events);
    }

    public function getAndClearPendingEvents()
    {
        if (empty($this->context->cookie->ps_gtm_pending_events)) {
            return [];
        }

        $events = json_decode($this->context->cookie->ps_gtm_pending_events, true);
        unset($this->context->cookie->ps_gtm_pending_events);
        return is_array($events) ? $events : [];
    }

    public function hookDisplayHeader($params)
    {
        try {
            $gtmId = Configuration::get('PS_GTM_CONTAINER_ID');
            $sgtmUrl = Configuration::get('PS_SGTM_TRANSPORT_URL');
            $consentEnabled = (bool)Configuration::get('PS_GTM_CONSENT_MODE');
            $eventPrefix = (string)Configuration::get('PS_GTM_EVENT_PREFIX');

            $controller = isset($this->context->controller->php_self) ? $this->context->controller->php_self : '';
            $payloadBuilder = new PayloadBuilder($this->context, $eventPrefix);
            $serverPayload = $payloadBuilder->buildPagePayload($controller, $params);

            // Pre-build combinations dictionary for PDP directly from database models
            $combosMap = [];
            if ($controller === 'product') {
                $idProduct = (int)Tools::getValue('id_product');
                if ($idProduct > 0) {
                    $productObj = new Product($idProduct, false, $this->context->language ? (int)$this->context->language->id : 1);
                    if (Validate::isLoadedObject($productObj)) {
                        $combosMap = ProductIdentityResolver::getProductCombinationsMap($productObj, $this->context->language ? (int)$this->context->language->id : 1);
                    }
                }
            }

            $pendingEvents = $this->getAndClearPendingEvents();
            $ajaxUrl = $this->context->link->getModuleLink($this->name, 'ajax', ['ajax' => 1]);
            $currencyPrecision = PlatformAdapter::getCurrencyPrecision($this->context);

            // Pre-hydrate active cart products map (100% backend-driven, ZERO DOM)
            $cartProductsMap = [];
            $cartProductsData = [];
            if ($this->context->cart && Validate::isLoadedObject($this->context->cart)) {
                $prods = $this->context->cart->getProducts();
                if (is_array($prods)) {
                    $isTaxIncl = PlatformAdapter::isTaxIncluded($this->context);
                    foreach ($prods as $p) {
                        $k = (int)$p['id_product'] . '_' . (int)$p['id_product_attribute'];
                        $cartProductsMap[$k] = (int)$p['cart_quantity'];

                        $priceRaw = $isTaxIncl
                            ? (isset($p['price_wt']) ? (float)$p['price_wt'] : (float)$p['price'])
                            : (isset($p['price']) ? (float)$p['price'] : (float)$p['price_wt']);
                        $price = PricingEngine::roundHalfUp($priceRaw, $currencyPrecision);

                        $cartProductsData[$k] = [
                            'item_id' => (string)$p['id_product'],
                            'variant_id' => !empty($p['id_product_attribute']) ? (string)$p['id_product_attribute'] : '',
                            'sku_id' => !empty($p['reference']) ? (string)$p['reference'] : '',
                            'item_name' => !empty($p['name']) ? (string)$p['name'] : '',
                            'item_brand' => !empty($p['manufacturer_name']) ? (string)$p['manufacturer_name'] : '',
                            'item_category' => !empty($p['category']) ? (string)$p['category'] : '',
                            'item_variant' => !empty($p['attributes']) ? (string)$p['attributes'] : (!empty($p['attributes_small']) ? (string)$p['attributes_small'] : ''),
                            'price' => $price,
                            'quantity' => (int)$p['cart_quantity']
                        ];
                    }
                }
            }

            $this->context->smarty->assign([
                'gtm_id' => $gtmId,
                'sgtm_url' => $sgtmUrl,
                'consent_mode_enabled' => $consentEnabled,
                'event_prefix' => $eventPrefix,
                'currency_precision' => $currencyPrecision,
                'ajax_url' => $ajaxUrl,
                'pending_events' => !empty($pendingEvents) ? json_encode($pendingEvents, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                'product_combinations' => json_encode($combosMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'cart_products' => !empty($cartProductsMap) ? json_encode($cartProductsMap, JSON_UNESCAPED_SLASHES) : null,
                'cart_products_data' => !empty($cartProductsData) ? json_encode($cartProductsData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                'wishlist_capabilities' => json_encode(WishlistIntegrationLayer::getWishlistCapabilities(), JSON_UNESCAPED_SLASHES),
                'catalog_id_strategy' => (Configuration::get('PS_GTM_CATALOG_ID_STRATEGY') ?: 'composite'),
                'initial_server_payload' => $serverPayload ? json_encode($serverPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            ]);

            if (is_object($this->context->controller) && method_exists($this->context->controller, 'registerJavascript')) {
                $this->context->controller->registerJavascript(
                    'gtm-datalayer-core',
                    'modules/' . $this->name . '/views/js/datalayer-core.js',
                    ['position' => 'head', 'priority' => 10]
                );

                $this->context->controller->registerJavascript(
                    'gtm-prestashop-adapter',
                    'modules/' . $this->name . '/views/js/prestashop-adapter.js',
                    ['position' => 'bottom', 'priority' => 50]
                );
            } elseif (is_object($this->context->controller) && method_exists($this->context->controller, 'addJS')) {
                $this->context->controller->addJS($this->_path . 'views/js/datalayer-core.js');
                $this->context->controller->addJS($this->_path . 'views/js/prestashop-adapter.js');
            }

            return $this->display(__FILE__, 'views/templates/hook/gtm_head.tpl')
                . $this->display(__FILE__, 'views/templates/hook/datalayer_init.tpl');
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function hookDisplayAfterBodyOpeningTag($params)
    {
        $gtmId = Configuration::get('PS_GTM_CONTAINER_ID');
        if (empty($gtmId)) {
            return '';
        }

        $this->context->smarty->assign(['gtm_id' => $gtmId]);
        return $this->display(__FILE__, 'views/templates/hook/gtm_body.tpl');
    }

    public function hookDisplayOrderConfirmation($params)
    {
        try {
            $order = isset($params['order']) ? $params['order'] : null;
            if (!$order || !Validate::isLoadedObject($order)) {
                return '';
            }

            if (DeduplicationManager::isTransactionTracked($order->id)) {
                return '<!-- GTM DataLayer: Purchase transaction ' . (int)$order->id . ' already recorded -->';
            }

            $eventPrefix = (string)Configuration::get('PS_GTM_EVENT_PREFIX');
            $payloadBuilder = new PayloadBuilder($this->context, $eventPrefix);
            $purchasePayload = $payloadBuilder->buildPurchasePayload($order);

            DeduplicationManager::markTransactionTracked($order->id, $order->reference);

            return '<script type="text/javascript">
                window.dataLayer = window.dataLayer || [];
                window.dataLayer.push({
                    ecommerce: null,
                    meta: null,
                    tiktok: null,
                    google_ads: null
                });
                window.dataLayer.push(' . json_encode($purchasePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ');
            </script>';
        } catch (\Throwable $e) {
            return '';
        }
    }
}
