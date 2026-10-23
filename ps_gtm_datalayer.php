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
        $this->version = '1.14.2';
        $this->author = 'Raihan';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('E-Commerce DataLayer: GA4, Meta, Pinterest, TikTok & Google Ads');
        $this->description = $this->l('Universal backend-driven DataLayer with direct Meta, Pinterest, TikTok, and Google Ads dynamic catalog matching, zero DOM dependency, authoritative database product identifiers, and sGTM support.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');

        // Keep ps_module database version table synchronized with active version
        if (class_exists('Db')) {
            PlatformAdapter::syncModuleDatabaseVersion($this->name, $this->version);
        }

        // Auto-register core hooks seamlessly without requiring module reinstall
        if (!$this->isRegisteredInHook('actionCarrierProcess')) {
            $this->registerHook('actionCarrierProcess');
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
        $allEvents = array_keys(self::getSupportedEvents());
        return parent::install()
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayAfterBodyOpeningTag')
            && $this->registerHook('displayOrderConfirmation')
            && $this->registerHook('actionCartUpdateQuantityBefore')
            && $this->registerHook('actionCarrierProcess')
            && $this->registerHook('displayBackOfficeTop')
            && $this->registerHook('displayBackOfficeHeader')
            && DeduplicationManager::createDatabaseTable()
            && Configuration::updateValue('PS_GTM_CONTAINER_ID', '')
            && Configuration::updateValue('PS_SGTM_TRANSPORT_URL', '')
            && Configuration::updateValue('PS_GTM_CONSENT_MODE', '1')
            && Configuration::updateValue('PS_GTM_EVENT_PREFIX', '')
            && Configuration::updateValue('PS_GTM_CATALOG_ID_STRATEGY', 'composite')
            && Configuration::updateValue('PS_GTM_ADV_MASTER_ENABLED', '1')
            && Configuration::updateValue('PS_GTM_ADV_META_ENABLED', '1')
            && Configuration::updateValue('PS_GTM_ADV_TIKTOK_ENABLED', '1')
            && Configuration::updateValue('PS_GTM_ADV_GADS_ENABLED', '1')
            && Configuration::updateValue('PS_GTM_ADV_PINTEREST_ENABLED', '1')
            && Configuration::updateValue('PS_GTM_ADV_META_EVENTS', json_encode($allEvents))
            && Configuration::updateValue('PS_GTM_ADV_TIKTOK_EVENTS', json_encode($allEvents))
            && Configuration::updateValue('PS_GTM_ADV_GADS_EVENTS', json_encode($allEvents))
            && Configuration::updateValue('PS_GTM_ADV_PINTEREST_EVENTS', json_encode($allEvents));
    }

    public function uninstall()
    {
        return parent::uninstall()
            && DeduplicationManager::dropDatabaseTable()
            && Configuration::deleteByName('PS_GTM_CONTAINER_ID')
            && Configuration::deleteByName('PS_SGTM_TRANSPORT_URL')
            && Configuration::deleteByName('PS_GTM_CONSENT_MODE')
            && Configuration::deleteByName('PS_GTM_EVENT_PREFIX')
            && Configuration::deleteByName('PS_GTM_CATALOG_ID_STRATEGY')
            && Configuration::deleteByName('PS_GTM_ADV_MASTER_ENABLED')
            && Configuration::deleteByName('PS_GTM_ADV_META_ENABLED')
            && Configuration::deleteByName('PS_GTM_ADV_TIKTOK_ENABLED')
            && Configuration::deleteByName('PS_GTM_ADV_GADS_ENABLED')
            && Configuration::deleteByName('PS_GTM_ADV_PINTEREST_ENABLED')
            && Configuration::deleteByName('PS_GTM_ADV_META_EVENTS')
            && Configuration::deleteByName('PS_GTM_ADV_TIKTOK_EVENTS')
            && Configuration::deleteByName('PS_GTM_ADV_GADS_EVENTS')
            && Configuration::deleteByName('PS_GTM_ADV_PINTEREST_EVENTS');
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
                $this->l('E-Commerce DataLayer: GA4, Meta, TikTok & Google Ads successfully updated to version %s! All your settings were preserved.'),
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
            // 1. Process Core Settings if submitted
            if (Tools::getValue('PS_GTM_CONTAINER_ID') !== false) {
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
                    $output .= $this->displayConfirmation($this->l('Google Tag Manager & DataLayer settings successfully saved.'));
                }
            }

            // 2. Process Advertising Platform Settings if submitted
            if (Tools::getValue('PS_GTM_ADV_SUBMITTED') || Tools::getValue('PS_GTM_ADV_MASTER_ENABLED') !== false) {
                $advMaster = Tools::getValue('PS_GTM_ADV_MASTER_ENABLED') ? '1' : '0';
                $advMeta = Tools::getValue('PS_GTM_ADV_META_ENABLED') ? '1' : '0';
                $advTiktok = Tools::getValue('PS_GTM_ADV_TIKTOK_ENABLED') ? '1' : '0';
                $advGads = Tools::getValue('PS_GTM_ADV_GADS_ENABLED') ? '1' : '0';
                $advPinterest = Tools::getValue('PS_GTM_ADV_PINTEREST_ENABLED') ? '1' : '0';

                Configuration::updateValue('PS_GTM_ADV_MASTER_ENABLED', $advMaster);
                Configuration::updateValue('PS_GTM_ADV_META_ENABLED', $advMeta);
                Configuration::updateValue('PS_GTM_ADV_TIKTOK_ENABLED', $advTiktok);
                Configuration::updateValue('PS_GTM_ADV_GADS_ENABLED', $advGads);
                Configuration::updateValue('PS_GTM_ADV_PINTEREST_ENABLED', $advPinterest);

                $metaEvts = [];
                $tiktokEvts = [];
                $gadsEvts = [];
                $pinterestEvts = [];
                foreach (array_keys(self::getSupportedEvents()) as $evtName) {
                    if (Tools::getValue('adv_meta_' . $evtName)) {
                        $metaEvts[] = $evtName;
                    }
                    if (Tools::getValue('adv_tiktok_' . $evtName)) {
                        $tiktokEvts[] = $evtName;
                    }
                    if (Tools::getValue('adv_gads_' . $evtName)) {
                        $gadsEvts[] = $evtName;
                    }
                    if (Tools::getValue('adv_pinterest_' . $evtName)) {
                        $pinterestEvts[] = $evtName;
                    }
                }
                Configuration::updateValue('PS_GTM_ADV_META_EVENTS', json_encode($metaEvts));
                Configuration::updateValue('PS_GTM_ADV_TIKTOK_EVENTS', json_encode($tiktokEvts));
                Configuration::updateValue('PS_GTM_ADV_GADS_EVENTS', json_encode($gadsEvts));
                Configuration::updateValue('PS_GTM_ADV_PINTEREST_EVENTS', json_encode($pinterestEvts));

                $output .= $this->displayConfirmation($this->l('Advertising platform dataLayer settings successfully saved.'));
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
                'PS_GTM_ADV_MASTER_ENABLED' => Configuration::get('PS_GTM_ADV_MASTER_ENABLED') !== '0' ? 1 : 0,
                'PS_GTM_ADV_META_ENABLED' => Configuration::get('PS_GTM_ADV_META_ENABLED') !== '0' ? 1 : 0,
                'PS_GTM_ADV_TIKTOK_ENABLED' => Configuration::get('PS_GTM_ADV_TIKTOK_ENABLED') !== '0' ? 1 : 0,
                'PS_GTM_ADV_GADS_ENABLED' => Configuration::get('PS_GTM_ADV_GADS_ENABLED') !== '0' ? 1 : 0,
                'PS_GTM_ADV_PINTEREST_ENABLED' => Configuration::get('PS_GTM_ADV_PINTEREST_ENABLED') !== '0' ? 1 : 0,
                'PS_GTM_ADV_MATRIX_TABLE' => $this->renderAdvertisingMatrixTable(),
            ],
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getConfigForm(), $this->getAdvertisingConfigForm()]);
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
                    'title' => $this->l('Save Core Settings'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];
    }

    protected function getAdvertisingConfigForm()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Advertising Platform Data Layer Settings'),
                    'icon' => 'icon-bullhorn',
                ],
                'description' => $this->l('Configure dynamic catalog-matching and remarketing payloads for Meta Pixel/CAPI, Pinterest Tag/CAPI, TikTok Events API, and Google Ads Dynamic Remarketing. Toggle platforms globally or customize exact event routing via the interactive matrix below.'),
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable Advertising DataLayers (Master Switch)'),
                        'name' => 'PS_GTM_ADV_MASTER_ENABLED',
                        'hint' => $this->l('Master toggle. When disabled, all advertising platform namespaces (meta, tiktok, google_ads, pinterest) are suppressed from the dataLayer across all events.'),
                        'is_bool' => true,
                        'desc' => $this->l('Master switch to enable or disable Meta, Pinterest, TikTok, and Google Ads namespaces across all events.'),
                        'values' => [
                            ['id' => 'adv_master_on', 'value' => 1, 'label' => $this->l('Enabled')],
                            ['id' => 'adv_master_off', 'value' => 0, 'label' => $this->l('Disabled')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Meta Pixel & Conversions API (CAPI)'),
                        'name' => 'PS_GTM_ADV_META_ENABLED',
                        'hint' => $this->l('Exposes the "meta" object payload with content_type, content_ids, contents, currency, and value matching your catalog strategy.'),
                        'is_bool' => true,
                        'desc' => $this->l('Outputs the "meta" namespace formatted for Meta Pixel and CAPI tags.'),
                        'values' => [
                            ['id' => 'adv_meta_on', 'value' => 1, 'label' => $this->l('Enabled')],
                            ['id' => 'adv_meta_off', 'value' => 0, 'label' => $this->l('Disabled')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Pinterest Tag & Conversions API (CAPI)'),
                        'name' => 'PS_GTM_ADV_PINTEREST_ENABLED',
                        'hint' => $this->l('Exposes the "pinterest" object payload with line_items, product_ids, value, currency, order_quantity, and catalog matching IDs for Pinterest Tag and Conversions API.'),
                        'is_bool' => true,
                        'desc' => $this->l('Outputs the "pinterest" namespace formatted for Pinterest Tag and Pinterest CAPI.'),
                        'values' => [
                            ['id' => 'adv_pinterest_on', 'value' => 1, 'label' => $this->l('Enabled')],
                            ['id' => 'adv_pinterest_off', 'value' => 0, 'label' => $this->l('Disabled')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('TikTok Pixel & Events API'),
                        'name' => 'PS_GTM_ADV_TIKTOK_ENABLED',
                        'hint' => $this->l('Exposes the "tiktok" object payload with content_id, contents, currency, and value formatted for TikTok.'),
                        'is_bool' => true,
                        'desc' => $this->l('Outputs the "tiktok" namespace formatted for TikTok Pixel and Events API.'),
                        'values' => [
                            ['id' => 'adv_tiktok_on', 'value' => 1, 'label' => $this->l('Enabled')],
                            ['id' => 'adv_tiktok_off', 'value' => 0, 'label' => $this->l('Disabled')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Google Ads Dynamic Remarketing'),
                        'name' => 'PS_GTM_ADV_GADS_ENABLED',
                        'hint' => $this->l('Exposes the "google_ads" object payload with ecomm_prodid, ecomm_pagetype, ecomm_totalvalue, and items matching Google Merchant Center feeds.'),
                        'is_bool' => true,
                        'desc' => $this->l('Outputs the "google_ads" namespace formatted for Google Ads Remarketing.'),
                        'values' => [
                            ['id' => 'adv_gads_on', 'value' => 1, 'label' => $this->l('Enabled')],
                            ['id' => 'adv_gads_off', 'value' => 0, 'label' => $this->l('Disabled')],
                        ],
                    ],
                    [
                        'type' => 'free',
                        'label' => $this->l('Event-Level Platform Routing Matrix'),
                        'name' => 'PS_GTM_ADV_MATRIX_TABLE',
                        'desc' => $this->l('Select which ecommerce events should expose data for each advertising platform. Use the buttons above each column or row to quickly select or deselect all.'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save Advertising Settings'),
                    'class' => 'btn btn-primary pull-right',
                ],
            ],
        ];
    }

    public static function getSupportedEvents()
    {
        return [
            'view_item_list' => [
                'name' => 'view_item_list',
                'label' => 'View Item List',
                'desc' => 'Viewing category, search results, brand/manufacturer, or catalog listing',
                'meta_spec' => 'fbq: ViewCategory, CAPI: ViewCategory, content_type: product_group, content_ids, contents',
                'tiktok_spec' => 'ttq: ViewContent, Events API: ViewContent, content_type: product_group, contents',
                'gads_spec' => 'ecomm_pagetype: category/searchresults, ecomm_prodid: [ids], ecomm_totalvalue',
                'pinterest_spec' => 'pintrk: ViewCategory, CAPI: view_category, category_name, line_items, value, currency',
            ],
            'view_search_results' => [
                'name' => 'view_search_results',
                'label' => 'View Search Results',
                'desc' => 'Customer viewing on-site search results with search keyword and matching products',
                'meta_spec' => 'fbq: Search, CAPI: Search, search_string, content_ids, contents',
                'tiktok_spec' => 'ttq: Search, Events API: Search, query, content_ids, contents',
                'gads_spec' => 'Excluded (Custom remarketing rule)',
                'pinterest_spec' => 'pintrk: Search, CAPI: search, search_query, line_items, product_ids',
            ],
            'select_item' => [
                'name' => 'select_item',
                'label' => 'Select Item',
                'desc' => 'Clicking on a product link or miniature card from any listing',
                'meta_spec' => 'fbq: ViewContent, CAPI: ViewContent, content_type: product, content_ids, contents, value',
                'tiktok_spec' => 'ttq: ViewContent, Events API: ViewContent, content_type: product, content_id, contents',
                'gads_spec' => 'ecomm_pagetype: product, ecomm_prodid: catalog_id, ecomm_totalvalue',
                'pinterest_spec' => 'pintrk: PageVisit, CAPI: page_visit, line_items: [{product_id, ...}], value, currency, order_quantity: 1',
            ],
            'view_item' => [
                'name' => 'view_item',
                'label' => 'View Item (PDP & Variants)',
                'desc' => 'Product detail page view and combination/variant switching',
                'meta_spec' => 'fbq: ViewContent, CAPI: ViewContent, content_type: product, content_ids, content_name, contents',
                'tiktok_spec' => 'ttq: ViewContent, Events API: ViewContent, content_type: product, content_id, content_name, contents',
                'gads_spec' => 'ecomm_pagetype: product, ecomm_prodid: catalog_id, ecomm_totalvalue',
                'pinterest_spec' => 'pintrk: PageVisit, CAPI: page_visit, line_items: [{product_id, ...}], value, currency, order_quantity: 1',
            ],
            'add_to_cart' => [
                'name' => 'add_to_cart',
                'label' => 'Add to Cart',
                'desc' => 'Adding a product or combination to the shopping cart',
                'meta_spec' => 'fbq: AddToCart, CAPI: AddToCart, content_type: product, content_ids, contents, currency, value',
                'tiktok_spec' => 'ttq: AddToCart, Events API: AddToCart, content_type: product, content_id, contents, quantity',
                'gads_spec' => 'ecomm_pagetype: cart, ecomm_prodid: [ids], ecomm_totalvalue',
                'pinterest_spec' => 'pintrk: AddToCart, CAPI: add_to_cart, line_items: [{product_id, ...}], value, currency, order_quantity',
            ],
            'remove_from_cart' => [
                'name' => 'remove_from_cart',
                'label' => 'Remove from Cart',
                'desc' => 'Removing an item or decrementing line item quantity in the cart',
                'meta_spec' => 'fbq: Custom (RemoveFromCart), CAPI: Custom, content_ids, contents, currency, value',
                'tiktok_spec' => 'ttq: Custom (RemoveFromCart), Events API: Custom, content_id, contents, quantity',
                'gads_spec' => 'ecomm_pagetype: cart, ecomm_prodid: [ids], ecomm_totalvalue',
                'pinterest_spec' => 'pintrk: Custom (RemoveFromCart), CAPI: custom, line_items, value, currency, order_quantity',
            ],
            'view_cart' => [
                'name' => 'view_cart',
                'label' => 'View Cart',
                'desc' => 'Visiting the shopping cart summary page (/cart)',
                'meta_spec' => 'fbq: ViewContent, CAPI: ViewContent, content_type: product, content_ids, contents, num_items',
                'tiktok_spec' => 'ttq: ViewContent, Events API: ViewContent, content_type: product, contents, quantity',
                'gads_spec' => 'ecomm_pagetype: cart, ecomm_prodid: [ids], ecomm_totalvalue',
                'pinterest_spec' => 'pintrk: PageVisit, CAPI: page_visit, line_items, value, currency, order_quantity',
            ],
            'begin_checkout' => [
                'name' => 'begin_checkout',
                'label' => 'Begin Checkout',
                'desc' => 'Proceeding to checkout funnel step 1 (/order)',
                'meta_spec' => 'fbq: InitiateCheckout, CAPI: InitiateCheckout, content_ids, num_items, contents, value',
                'tiktok_spec' => 'ttq: InitiateCheckout, Events API: InitiateCheckout, contents, quantity, value',
                'gads_spec' => 'ecomm_pagetype: cart, ecomm_prodid: [ids], ecomm_totalvalue',
                'pinterest_spec' => 'pintrk: Custom (InitiateCheckout), CAPI: custom, line_items, value, currency, order_quantity',
            ],
            'add_shipping_info' => [
                'name' => 'add_shipping_info',
                'label' => 'Add Shipping Info',
                'desc' => 'Selecting a carrier or delivery method in checkout step 3',
                'meta_spec' => 'fbq: AddShippingInfo, CAPI: AddShippingInfo, content_ids, contents, currency, value',
                'tiktok_spec' => 'ttq: AddShippingInfo, Events API: AddShippingInfo, contents, quantity, currency, value',
                'gads_spec' => 'ecomm_pagetype: cart, ecomm_prodid: [ids], ecomm_totalvalue',
                'pinterest_spec' => 'pintrk: Custom (AddShippingInfo), CAPI: custom, line_items, value, currency, property',
            ],
            'add_payment_info' => [
                'name' => 'add_payment_info',
                'label' => 'Add Payment Info',
                'desc' => 'Selecting a payment gateway or option in checkout step 4',
                'meta_spec' => 'fbq: AddPaymentInfo, CAPI: AddPaymentInfo, content_ids, contents, currency, value',
                'tiktok_spec' => 'ttq: AddPaymentInfo, Events API: AddPaymentInfo, contents, quantity, currency, value',
                'gads_spec' => 'ecomm_pagetype: cart, ecomm_prodid: [ids], ecomm_totalvalue',
                'pinterest_spec' => 'pintrk: Custom (AddPaymentInfo), CAPI: custom, line_items, value, currency, property',
            ],
            'purchase' => [
                'name' => 'purchase',
                'label' => 'Purchase',
                'desc' => 'Order completed with transaction details, order_totals, and user_data',
                'meta_spec' => 'fbq: Purchase, CAPI: Purchase, order_id, content_ids, num_items, contents, currency, value',
                'tiktok_spec' => 'ttq: CompletePayment, Events API: CompletePayment, order_id, contents, quantity, value',
                'gads_spec' => 'ecomm_pagetype: purchase, ecomm_prodid: [ids], ecomm_totalvalue',
                'pinterest_spec' => 'pintrk: Checkout, CAPI: checkout, order_id, value, currency, order_quantity, line_items',
            ],
            'add_to_wishlist' => [
                'name' => 'add_to_wishlist',
                'label' => 'Add to Wishlist',
                'desc' => 'Adding an item to wishlist/favorites via ps_wishlist or blockwishlist',
                'meta_spec' => 'fbq: AddToWishlist, CAPI: AddToWishlist, content_ids, contents, currency, value',
                'tiktok_spec' => 'ttq: AddToWishlist, Events API: AddToWishlist, content_id, contents, currency, value',
                'gads_spec' => 'ecomm_pagetype: other, ecomm_prodid: catalog_id, ecomm_totalvalue',
                'pinterest_spec' => 'pintrk: Custom (AddWishList), CAPI: custom, line_items, value, currency',
            ],
        ];
    }

    public function renderAdvertisingMatrixTable()
    {
        $events = self::getSupportedEvents();
        $rawMeta = Configuration::get('PS_GTM_ADV_META_EVENTS');
        $rawTiktok = Configuration::get('PS_GTM_ADV_TIKTOK_EVENTS');
        $rawGads = Configuration::get('PS_GTM_ADV_GADS_EVENTS');
        $rawPinterest = Configuration::get('PS_GTM_ADV_PINTEREST_EVENTS');

        $metaEvents = ($rawMeta !== false && $rawMeta !== null) ? json_decode($rawMeta, true) : array_keys($events);
        $tiktokEvents = ($rawTiktok !== false && $rawTiktok !== null) ? json_decode($rawTiktok, true) : array_keys($events);
        $gadsEvents = ($rawGads !== false && $rawGads !== null) ? json_decode($rawGads, true) : array_keys($events);
        $pinterestEvents = ($rawPinterest !== false && $rawPinterest !== null) ? json_decode($rawPinterest, true) : array_keys($events);

        if (!is_array($metaEvents)) $metaEvents = array_keys($events);
        if (!is_array($tiktokEvents)) $tiktokEvents = array_keys($events);
        if (!is_array($gadsEvents)) $gadsEvents = array_keys($events);
        if (!is_array($pinterestEvents)) $pinterestEvents = array_keys($events);

        $html = '<div id="ps-gtm-adv-matrix-container" style="max-width: 1050px; margin-top: 5px;">';
        $html .= '<input type="hidden" name="PS_GTM_ADV_SUBMITTED" value="1" />';

        // Convenience controls bar
        $html .= '<div style="background: #f8f9fa; border: 1px solid #dcdde1; border-radius: 6px; padding: 12px 16px; margin-bottom: 12px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px;">';
        $html .= '<div style="font-size: 12px; font-weight: bold; color: #2c3e50;"><i class="icon-sliders"></i> ' . $this->l('Quick Controls:') . '</div>';
        $html .= '<div style="display: flex; flex-wrap: wrap; gap: 8px;">';
        $html .= '<button type="button" class="btn btn-default btn-xs" onclick="psGtmSetColumn(\'meta\', true)"><i class="icon-check" style="color:#27ae60;"></i> ' . $this->l('All Meta') . '</button>';
        $html .= '<button type="button" class="btn btn-default btn-xs" onclick="psGtmSetColumn(\'meta\', false)"><i class="icon-times" style="color:#e74c3c;"></i> ' . $this->l('None') . '</button>';
        $html .= '<span style="border-left: 1px solid #ccc; margin: 0 4px;"></span>';
        $html .= '<button type="button" class="btn btn-default btn-xs" onclick="psGtmSetColumn(\'pinterest\', true)"><i class="icon-check" style="color:#27ae60;"></i> ' . $this->l('All Pinterest') . '</button>';
        $html .= '<button type="button" class="btn btn-default btn-xs" onclick="psGtmSetColumn(\'pinterest\', false)"><i class="icon-times" style="color:#e74c3c;"></i> ' . $this->l('None') . '</button>';
        $html .= '<span style="border-left: 1px solid #ccc; margin: 0 4px;"></span>';
        $html .= '<button type="button" class="btn btn-default btn-xs" onclick="psGtmSetColumn(\'tiktok\', true)"><i class="icon-check" style="color:#27ae60;"></i> ' . $this->l('All TikTok') . '</button>';
        $html .= '<button type="button" class="btn btn-default btn-xs" onclick="psGtmSetColumn(\'tiktok\', false)"><i class="icon-times" style="color:#e74c3c;"></i> ' . $this->l('None') . '</button>';
        $html .= '<span style="border-left: 1px solid #ccc; margin: 0 4px;"></span>';
        $html .= '<button type="button" class="btn btn-default btn-xs" onclick="psGtmSetColumn(\'gads\', true)"><i class="icon-check" style="color:#27ae60;"></i> ' . $this->l('All Google Ads') . '</button>';
        $html .= '<button type="button" class="btn btn-default btn-xs" onclick="psGtmSetColumn(\'gads\', false)"><i class="icon-times" style="color:#e74c3c;"></i> ' . $this->l('None') . '</button>';
        $html .= '<span style="border-left: 1px solid #ccc; margin: 0 4px;"></span>';
        $html .= '<button type="button" class="btn btn-success btn-xs" onclick="psGtmSetMatrix(true)"><i class="icon-check-circle"></i> ' . $this->l('Enable All') . '</button>';
        $html .= '<button type="button" class="btn btn-warning btn-xs" onclick="psGtmSetMatrix(false)"><i class="icon-ban"></i> ' . $this->l('Disable All') . '</button>';
        $html .= '</div>';
        $html .= '</div>';

        // Table
        $html .= '<div class="table-responsive">';
        $html .= '<table class="table table-bordered table-striped" style="background:#fff; margin-bottom: 0;">';
        $html .= '<thead>';
        $html .= '<tr style="background:#f1f2f6;">';
        $html .= '<th style="width: 35px; text-align: center;">#</th>';
        $html .= '<th>' . $this->l('Ecommerce Event') . '</th>';
        $html .= '<th style="width: 150px; text-align: center;"><i class="icon-facebook" style="color: #1877f2;"></i> ' . $this->l('Meta Pixel / CAPI') . '</th>';
        $html .= '<th style="width: 150px; text-align: center;"><i class="icon-pinterest" style="color: #bd081c;"></i> ' . $this->l('Pinterest Tag / CAPI') . '</th>';
        $html .= '<th style="width: 150px; text-align: center;"><i class="icon-music" style="color: #000;"></i> ' . $this->l('TikTok Events API') . '</th>';
        $html .= '<th style="width: 160px; text-align: center;"><i class="icon-google" style="color: #ea4335;"></i> ' . $this->l('Google Ads Remarketing') . '</th>';
        $html .= '<th style="width: 100px; text-align: center;">' . $this->l('Row Actions') . '</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        $rowIdx = 1;
        foreach ($events as $k => $e) {
            $isMeta = in_array($k, $metaEvents);
            $isPinterest = in_array($k, $pinterestEvents);
            $isTiktok = in_array($k, $tiktokEvents);
            $isGads = in_array($k, $gadsEvents);

            $html .= '<tr class="ps-gtm-matrix-row" data-row="' . $rowIdx . '">';
            $html .= '<td style="text-align: center; vertical-align: middle; font-weight: bold; color: #7f8c8d;">' . $rowIdx . '</td>';
            $html .= '<td style="vertical-align: middle;">';
            $html .= '<div style="display: flex; align-items: center; justify-content: space-between; position: relative;">';
            $html .= '<div>';
            $html .= '<strong style="color: #2c3e50; font-size: 13px;"><code>' . htmlspecialchars($e['name']) . '</code></strong>';
            $html .= '<div style="font-size: 11px; color: #7f8c8d; margin-top: 2px;">' . htmlspecialchars($e['desc']) . '</div>';
            $html .= '</div>';
            
            // Tooltip trigger
            $html .= '<div class="ps-gtm-info-popover-trigger" style="cursor: pointer; padding: 4px 8px; color: #3498db; background: #ebf5fb; border-radius: 4px; font-size: 11px; margin-left: 10px; white-space: nowrap;">';
            $html .= '<i class="icon-info-sign"></i> ' . $this->l('Specs');
            $html .= '<div class="ps-gtm-info-popover-body" style="display: none; position: absolute; left: 0; top: 100%; margin-top: 6px; width: 420px; background: #ffffff; border: 1px solid #3498db; border-radius: 6px; box-shadow: 0 8px 24px rgba(0,0,0,0.18); padding: 12px 14px; z-index: 99999; text-align: left;">';
            $html .= '<strong style="color: #2980b9; font-size: 12px;">' . htmlspecialchars($e['name']) . ' ' . $this->l('Platform Namespaces') . '</strong>';
            $html .= '<div style="margin-top: 6px; font-size: 11px; color: #2c3e50; line-height: 1.4;">';
            $html .= '<div style="margin-bottom: 4px;"><strong>Meta:</strong> <code>' . htmlspecialchars($e['meta_spec']) . '</code></div>';
            $html .= '<div style="margin-bottom: 4px;"><strong>Pinterest:</strong> <code>' . htmlspecialchars($e['pinterest_spec']) . '</code></div>';
            $html .= '<div style="margin-bottom: 4px;"><strong>TikTok:</strong> <code>' . htmlspecialchars($e['tiktok_spec']) . '</code></div>';
            $html .= '<div><strong>Google Ads:</strong> <code>' . htmlspecialchars($e['gads_spec']) . '</code></div>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';

            $html .= '</div>';
            $html .= '</td>';

            // Meta Checkbox
            $html .= '<td style="text-align: center; vertical-align: middle;">';
            $html .= '<input type="checkbox" name="adv_meta_' . $k . '" class="ps-gtm-cb ps-gtm-col-meta ps-gtm-row-' . $rowIdx . '" value="1" ' . ($isMeta ? 'checked="checked"' : '') . ' style="transform: scale(1.3); cursor: pointer;" />';
            $html .= '</td>';

            // Pinterest Checkbox
            $html .= '<td style="text-align: center; vertical-align: middle;">';
            $html .= '<input type="checkbox" name="adv_pinterest_' . $k . '" class="ps-gtm-cb ps-gtm-col-pinterest ps-gtm-row-' . $rowIdx . '" value="1" ' . ($isPinterest ? 'checked="checked"' : '') . ' style="transform: scale(1.3); cursor: pointer;" />';
            $html .= '</td>';

            // TikTok Checkbox
            $html .= '<td style="text-align: center; vertical-align: middle;">';
            $html .= '<input type="checkbox" name="adv_tiktok_' . $k . '" class="ps-gtm-cb ps-gtm-col-tiktok ps-gtm-row-' . $rowIdx . '" value="1" ' . ($isTiktok ? 'checked="checked"' : '') . ' style="transform: scale(1.3); cursor: pointer;" />';
            $html .= '</td>';

            // Google Ads Checkbox
            $html .= '<td style="text-align: center; vertical-align: middle;">';
            if ($k === 'view_search_results') {
                $html .= '<span class="badge" style="background: #eaeded; color: #7f8c8d; font-size: 10px; font-weight: normal; padding: 3px 6px;">' . $this->l('Excluded') . '</span>';
            } else {
                $html .= '<input type="checkbox" name="adv_gads_' . $k . '" class="ps-gtm-cb ps-gtm-col-gads ps-gtm-row-' . $rowIdx . '" value="1" ' . ($isGads ? 'checked="checked"' : '') . ' style="transform: scale(1.3); cursor: pointer;" />';
            }
            $html .= '</td>';

            // Row Action
            $html .= '<td style="text-align: center; vertical-align: middle;">';
            $html .= '<div class="btn-group">';
            $html .= '<button type="button" class="btn btn-default btn-xs" onclick="psGtmSetRow(' . $rowIdx . ', true)" title="' . $this->l('Enable all platforms for this event') . '"><i class="icon-check text-success"></i></button>';
            $html .= '<button type="button" class="btn btn-default btn-xs" onclick="psGtmSetRow(' . $rowIdx . ', false)" title="' . $this->l('Disable all platforms for this event') . '"><i class="icon-times text-danger"></i></button>';
            $html .= '</div>';
            $html .= '</td>';

            $html .= '</tr>';
            $rowIdx++;
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
        $html .= '</div>';

        // Embedded Script for interactive matrix operations and hover popovers
        $html .= '<script type="text/javascript">
            function psGtmSetColumn(col, state) {
                var cbs = document.querySelectorAll(".ps-gtm-col-" + col);
                for (var i = 0; i < cbs.length; i++) {
                    cbs[i].checked = state;
                }
            }
            function psGtmSetRow(rowIdx, state) {
                var cbs = document.querySelectorAll(".ps-gtm-row-" + rowIdx);
                for (var i = 0; i < cbs.length; i++) {
                    cbs[i].checked = state;
                }
            }
            function psGtmSetMatrix(state) {
                var cbs = document.querySelectorAll(".ps-gtm-cb");
                for (var i = 0; i < cbs.length; i++) {
                    cbs[i].checked = state;
                }
            }
            (function() {
                function setupPopovers() {
                    var triggers = document.querySelectorAll(".ps-gtm-info-popover-trigger");
                    for (var i = 0; i < triggers.length; i++) {
                        (function(trigger) {
                            var body = trigger.querySelector(".ps-gtm-info-popover-body");
                            if (!body) return;
                            trigger.addEventListener("mouseenter", function() {
                                body.style.display = "block";
                            });
                            trigger.addEventListener("mouseleave", function() {
                                body.style.display = "none";
                            });
                        })(triggers[i]);
                    }
                }
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", setupPopovers);
                } else {
                    setupPopovers();
                }
            })();
        </script>';

        return $html;
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

    public function hookActionCarrierProcess($params)
    {
        try {
            $cart = isset($params['cart']) ? $params['cart'] : $this->context->cart;
            if (!Validate::isLoadedObject($cart) || $cart->nbProducts() === 0) {
                return;
            }

            $idCarrier = (int)$cart->id_carrier;
            $carrierName = '';
            if ($idCarrier > 0) {
                $carrier = new Carrier($idCarrier, $this->context->language ? (int)$this->context->language->id : 1);
                if (Validate::isLoadedObject($carrier)) {
                    $carrierName = (string)$carrier->name;
                }
            }
            if (empty($carrierName)) {
                $carrierName = 'Standard Delivery';
            }

            $this->queueCheckoutEvent('add_shipping_info', $cart, ['shipping_tier' => $carrierName]);
        } catch (\Throwable $e) {}
    }

    public function queueCheckoutEvent($canonicalEvent, Cart $cart, $extraEcommerceParams = [])
    {
        if (!Validate::isLoadedObject($cart) || $cart->nbProducts() === 0) {
            return;
        }

        $eventPrefix = Configuration::get('PS_GTM_EVENT_PREFIX');
        $payloadBuilder = new PayloadBuilder($this->context, $eventPrefix);

        if ($canonicalEvent === 'add_shipping_info') {
            $shippingTier = isset($extraEcommerceParams['shipping_tier']) ? $extraEcommerceParams['shipping_tier'] : '';
            $payload = $payloadBuilder->buildAddShippingInfoPayload($shippingTier);
        } elseif ($canonicalEvent === 'add_payment_info') {
            $paymentType = isset($extraEcommerceParams['payment_type']) ? $extraEcommerceParams['payment_type'] : '';
            $payload = $payloadBuilder->buildAddPaymentInfoPayload($paymentType);
        } else {
            return;
        }

        if (!$payload) {
            return;
        }

        $events = [];
        if (!empty($this->context->cookie->ps_gtm_pending_events)) {
            $decoded = json_decode($this->context->cookie->ps_gtm_pending_events, true);
            if (is_array($decoded)) {
                $events = $decoded;
            }
        }

        // Deduplication in cookie: avoid duplicate checkout step events
        foreach ($events as $existing) {
            if (isset($existing['original_event']) && $existing['original_event'] === $canonicalEvent) {
                if ($canonicalEvent === 'add_shipping_info' && isset($existing['ecommerce']['shipping_tier']) && isset($payload['ecommerce']['shipping_tier']) && $existing['ecommerce']['shipping_tier'] === $payload['ecommerce']['shipping_tier']) {
                    return;
                }
                if ($canonicalEvent === 'add_payment_info' && isset($existing['ecommerce']['payment_type']) && isset($payload['ecommerce']['payment_type']) && $existing['ecommerce']['payment_type'] === $payload['ecommerce']['payment_type']) {
                    return;
                }
            }
        }

        $events[] = $payload;
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
            $isSearch = PayloadBuilder::isSearchController($controller, $this->context);
            $payloadBuilder = new PayloadBuilder($this->context, $eventPrefix);
            $serverPayload = $payloadBuilder->buildPagePayload($controller, $params);

            $initialPayloads = [];
            if ($isSearch) {
                $searchQuery = PayloadBuilder::getSearchQuery();
                if ($searchQuery !== '') {
                    $searchPayload = $payloadBuilder->buildViewSearchResultsPayload($searchQuery);
                    if ($searchPayload) {
                        $initialPayloads[] = $searchPayload;
                    }
                }
            }
            if ($serverPayload) {
                $initialPayloads[] = $serverPayload;
            }

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

            // Pre-hydrate Page Context for listing tracking & select_item fallback
            $pageContext = [
                'controller' => $controller,
                'list_id' => '',
                'list_name' => '',
            ];
            if ($controller === 'index') {
                $pageContext['list_id'] = 'home_featured';
                $pageContext['list_name'] = 'Home Page - Featured Products';
            } elseif ($controller === 'category') {
                $category = (isset($this->context->controller->category) && Validate::isLoadedObject($this->context->controller->category))
                    ? $this->context->controller->category
                    : (method_exists($this->context->controller, 'getCategory') ? $this->context->controller->getCategory() : null);
                $idCategory = ($category && Validate::isLoadedObject($category)) ? (int)$category->id : (int)Tools::getValue('id_category');
                if ($idCategory) {
                    if (!$category || !Validate::isLoadedObject($category)) {
                        $category = new Category($idCategory, $this->context->language ? (int)$this->context->language->id : 1);
                    }
                    if (Validate::isLoadedObject($category)) {
                        $pageContext['list_id'] = 'category_' . $idCategory;
                        $pageContext['list_name'] = (string)$category->name;
                    }
                }
            } elseif ($controller === 'product') {
                $pageContext['list_id'] = 'related_products';
                $pageContext['list_name'] = 'Related Products';
            } elseif ($controller === 'cart') {
                $pageContext['list_id'] = 'cross_sell';
                $pageContext['list_name'] = 'Cart Cross-Sell';
            } elseif ($isSearch) {
                $searchQuery = PayloadBuilder::getSearchQuery();
                $pageContext['list_id'] = 'search_results';
                $pageContext['list_name'] = $searchQuery ? 'Search: ' . $searchQuery : 'Search Results';
            } elseif ($controller === 'prices-drop') {
                $pageContext['list_id'] = 'on_sale';
                $pageContext['list_name'] = 'On Sale Products';
            } elseif ($controller === 'new-products') {
                $pageContext['list_id'] = 'new_products';
                $pageContext['list_name'] = 'New Products';
            } elseif ($controller === 'best-sales') {
                $pageContext['list_id'] = 'best_sellers';
                $pageContext['list_name'] = 'Best Sellers';
            } elseif ($controller === 'manufacturer') {
                $idManufacturer = (int)Tools::getValue('id_manufacturer');
                if ($idManufacturer) {
                    $manufacturer = new Manufacturer($idManufacturer, $this->context->language ? (int)$this->context->language->id : 1);
                    if (Validate::isLoadedObject($manufacturer)) {
                        $pageContext['list_id'] = 'brand_' . $idManufacturer;
                        $pageContext['list_name'] = (string)$manufacturer->name;
                    }
                }
            }

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

            $advMaster = Configuration::get('PS_GTM_ADV_MASTER_ENABLED') !== '0';
            $advMeta = Configuration::get('PS_GTM_ADV_META_ENABLED') !== '0';
            $advTiktok = Configuration::get('PS_GTM_ADV_TIKTOK_ENABLED') !== '0';
            $advGads = Configuration::get('PS_GTM_ADV_GADS_ENABLED') !== '0';
            $advPinterest = Configuration::get('PS_GTM_ADV_PINTEREST_ENABLED') !== '0';

            $rawMeta = Configuration::get('PS_GTM_ADV_META_EVENTS');
            $rawTiktok = Configuration::get('PS_GTM_ADV_TIKTOK_EVENTS');
            $rawGads = Configuration::get('PS_GTM_ADV_GADS_EVENTS');
            $rawPinterest = Configuration::get('PS_GTM_ADV_PINTEREST_EVENTS');

            $metaEvents = ($rawMeta !== false && $rawMeta !== null) ? json_decode($rawMeta, true) : array_keys(self::getSupportedEvents());
            $tiktokEvents = ($rawTiktok !== false && $rawTiktok !== null) ? json_decode($rawTiktok, true) : array_keys(self::getSupportedEvents());
            $gadsEvents = ($rawGads !== false && $rawGads !== null) ? json_decode($rawGads, true) : array_keys(self::getSupportedEvents());
            $pinterestEvents = ($rawPinterest !== false && $rawPinterest !== null) ? json_decode($rawPinterest, true) : array_keys(self::getSupportedEvents());

            $advSettings = [
                'master_enabled' => (bool)$advMaster,
                'meta_enabled' => (bool)$advMeta,
                'tiktok_enabled' => (bool)$advTiktok,
                'gads_enabled' => (bool)$advGads,
                'pinterest_enabled' => (bool)$advPinterest,
                'meta_events' => is_array($metaEvents) ? $metaEvents : array_keys(self::getSupportedEvents()),
                'tiktok_events' => is_array($tiktokEvents) ? $tiktokEvents : array_keys(self::getSupportedEvents()),
                'gads_events' => is_array($gadsEvents) ? $gadsEvents : array_keys(self::getSupportedEvents()),
                'pinterest_events' => is_array($pinterestEvents) ? $pinterestEvents : array_keys(self::getSupportedEvents()),
            ];

            // Pre-hydrate authoritative carrier names directly from database model (Zero DOM)
            $carriersMap = [];
            if (class_exists('Carrier')) {
                $idLang = $this->context->language ? (int)$this->context->language->id : 1;
                $carriers = Carrier::getCarriers($idLang, true, false, false, null, Carrier::ALL_CARRIERS);
                if (is_array($carriers)) {
                    foreach ($carriers as $c) {
                        $carriersMap[(int)$c['id_carrier']] = (string)$c['name'];
                    }
                }
            }

            // Pre-hydrate installed payment method names directly from module instances (Zero DOM)
            $paymentMethodsMap = [];
            if (class_exists('PaymentModule')) {
                $installedPaymentModules = PaymentModule::getInstalledPaymentModules();
                if (is_array($installedPaymentModules)) {
                    foreach ($installedPaymentModules as $pm) {
                        $modInstance = Module::getInstanceByName($pm['name']);
                        if ($modInstance && Validate::isLoadedObject($modInstance)) {
                            $paymentMethodsMap[$pm['name']] = (string)$modInstance->displayName;
                        }
                    }
                }
            }

            // Pre-hydrate authoritative Payment Options Map for checkout step 4
            $paymentOptionsMap = [];
            if ($controller === 'order' && class_exists('PaymentOptionsFinder')) {
                try {
                    $finder = new PaymentOptionsFinder();
                    $presentOptions = $finder->present();
                    if (is_array($presentOptions)) {
                        foreach ($presentOptions as $moduleOptions) {
                            if (is_array($moduleOptions)) {
                                foreach ($moduleOptions as $opt) {
                                    $optId = isset($opt['id']) ? (string)$opt['id'] : '';
                                    $optName = isset($opt['call_to_action_text']) ? (string)$opt['call_to_action_text'] : '';
                                    $modName = isset($opt['module_name']) ? (string)$opt['module_name'] : '';
                                    if ($optId && $optName) {
                                        $paymentOptionsMap[$optId] = $optName;
                                    }
                                    if ($modName && $optName) {
                                        $paymentOptionsMap[$modName] = $optName;
                                    }
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {}
            }
            if (is_array($paymentMethodsMap)) {
                foreach ($paymentMethodsMap as $pmKey => $pmName) {
                    if (!isset($paymentOptionsMap[$pmKey])) {
                        $paymentOptionsMap[$pmKey] = $pmName;
                    }
                }
            }

            // Pre-hydrate selected carrier name from cart if active
            $selectedCarrierName = '';
            if ($this->context->cart && Validate::isLoadedObject($this->context->cart)) {
                $idCarrier = (int)$this->context->cart->id_carrier;
                if ($idCarrier > 0 && isset($carriersMap[$idCarrier])) {
                    $selectedCarrierName = $carriersMap[$idCarrier];
                }
            }

            // Pre-hydrate active cart voucher / coupon code (GA4 coupon parameter)
            $cartCoupon = '';
            if ($this->context->cart && Validate::isLoadedObject($this->context->cart)) {
                $cartRules = $this->context->cart->getCartRules();
                if (!empty($cartRules)) {
                    $codes = array_filter(array_map(function($r) { return !empty($r['code']) ? trim($r['code']) : ''; }, $cartRules));
                    if (!empty($codes)) {
                        $cartCoupon = implode(', ', $codes);
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
                'advertising_settings' => json_encode($advSettings, JSON_UNESCAPED_SLASHES),
                'carriers_map' => json_encode($carriersMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'payment_methods_map' => json_encode($paymentMethodsMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'payment_options_map' => json_encode($paymentOptionsMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'selected_carrier' => $selectedCarrierName,
                'cart_coupon' => $cartCoupon,
                'page_context' => json_encode($pageContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'initial_server_payload' => !empty($initialPayloads) ? json_encode((count($initialPayloads) === 1 ? $initialPayloads[0] : $initialPayloads), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
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
                    google_ads: null,
                    pinterest: null
                });
                window.dataLayer.push(' . json_encode($purchasePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ');
            </script>';
        } catch (\Throwable $e) {
            return '';
        }
    }
}
