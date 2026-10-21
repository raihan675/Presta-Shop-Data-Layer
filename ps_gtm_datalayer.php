<?php
/**
 * Advanced Google Tag Manager & GA4 DataLayer Module for PrestaShop
 * Compatibility: PrestaShop 1.7.8.0 - 8.2.x / 9.x
 * Universal Backend-Driven Architecture — Zero DOM Scraping.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/ProductIdentityResolver.php';
require_once __DIR__ . '/classes/CustomerDataEngine.php';
require_once __DIR__ . '/classes/PayloadBuilder.php';
require_once __DIR__ . '/classes/DeduplicationManager.php';
require_once __DIR__ . '/classes/UpdateManager.php';

class Ps_Gtm_DataLayer extends Module
{
    public function __construct()
    {
        $this->name = 'ps_gtm_datalayer';
        $this->tab = 'analytics_stats';
        $this->version = '1.4.2';
        $this->author = 'Analytics Engineer';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7.8.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Enterprise GA4 & sGTM DataLayer');
        $this->description = $this->l('Universal backend-driven DataLayer with zero DOM dependency, authoritative database product identifiers, and sGTM support.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayAfterBodyOpeningTag')
            && $this->registerHook('displayOrderConfirmation')
            && $this->registerHook('actionCartUpdateQuantityBefore')
            && DeduplicationManager::createDatabaseTable()
            && Configuration::updateValue('PS_GTM_CONTAINER_ID', '')
            && Configuration::updateValue('PS_SGTM_TRANSPORT_URL', '')
            && Configuration::updateValue('PS_GTM_CONSENT_MODE', '1')
            && Configuration::updateValue('PS_GTM_EVENT_PREFIX', '');
    }

    public function uninstall()
    {
        return parent::uninstall()
            && DeduplicationManager::dropDatabaseTable()
            && Configuration::deleteByName('PS_GTM_CONTAINER_ID')
            && Configuration::deleteByName('PS_SGTM_TRANSPORT_URL')
            && Configuration::deleteByName('PS_GTM_CONSENT_MODE')
            && Configuration::deleteByName('PS_GTM_EVENT_PREFIX');
    }

    public function getContent()
    {
        $output = '';
        $updateManager = new UpdateManager($this);

        // Handle 1-Click Update Execution
        if (Tools::isSubmit('submitRunModuleUpdate')) {
            $downloadUrl = Tools::getValue('update_download_url');
            $checksum = Tools::getValue('update_checksum');
            $updateResult = $updateManager->executeUpdate($downloadUrl, $checksum);

            if ($updateResult['success']) {
                $output .= $this->displayConfirmation($this->l($updateResult['message']));
            } else {
                $output .= $this->displayError($this->l($updateResult['message']));
            }
        }

        // Handle Settings Submission
        if (Tools::isSubmit('submit' . $this->name)) {
            $gtmId = trim(Tools::getValue('PS_GTM_CONTAINER_ID'));
            $sgtmUrl = trim(Tools::getValue('PS_SGTM_TRANSPORT_URL'));
            $consent = (int)Tools::getValue('PS_GTM_CONSENT_MODE');
            $rawPrefix = Tools::getValue('PS_GTM_EVENT_PREFIX');
            $prefix = self::normalizePrefix($rawPrefix);

            if (!empty($gtmId) && !preg_match('/^GTM-[A-Z0-9]+$/', $gtmId)) {
                $output .= $this->displayError($this->l('Invalid Google Tag Manager Container ID format (e.g., GTM-XXXXXXX).'));
            } else {
                Configuration::updateValue('PS_GTM_CONTAINER_ID', $gtmId);
                Configuration::updateValue('PS_SGTM_TRANSPORT_URL', $sgtmUrl);
                Configuration::updateValue('PS_GTM_CONSENT_MODE', $consent);
                Configuration::updateValue('PS_GTM_EVENT_PREFIX', $prefix);
                $output .= $this->displayConfirmation($this->l('Settings successfully saved.'));
            }
        }

        $updateInfo = $updateManager->checkForUpdates();
        $output .= $this->renderUpdateBanner($updateInfo);

        return $output . $this->renderForm();
    }

    private function renderUpdateBanner($updateInfo)
    {
        $html = '<div class="panel">';
        $html .= '<h3><i class="icon-refresh"></i> ' . $this->l('Module Version & Update Status') . '</h3>';

        if (isset($updateInfo['has_update']) && $updateInfo['has_update']) {
            $html .= '<div class="alert alert-warning">';
            $html .= '<h4><strong>' . sprintf($this->l('New version %s is available!'), htmlspecialchars($updateInfo['latest_version'])) . '</strong></h4>';
            $html .= '<p>' . nl2br(htmlspecialchars($updateInfo['release_notes'])) . '</p>';
            $html .= '<hr>';
            $html .= '<form action="' . Tools::safeOutput($_SERVER['REQUEST_URI']) . '" method="post" style="display:inline-block;">';
            $html .= '<input type="hidden" name="update_download_url" value="' . htmlspecialchars($updateInfo['download_url']) . '">';
            $html .= '<input type="hidden" name="update_checksum" value="' . htmlspecialchars($updateInfo['checksum']) . '">';
            $html .= '<button type="submit" name="submitRunModuleUpdate" class="btn btn-success btn-lg" onclick="return confirm(\'' . $this->l('Are you sure you want to upgrade the module now? All your settings will be preserved.') . '\');">';
            $html .= '<i class="icon-download"></i> ' . $this->l('1-Click Update to Version ') . htmlspecialchars($updateInfo['latest_version']);
            $html .= '</button>';
            $html .= '</form>';
            $html .= '</div>';
        } else {
            $html .= '<div class="alert alert-success" style="margin-bottom:0;">';
            $html .= '<i class="icon-check-circle"></i> ' . sprintf($this->l('Module is up-to-date (Version %s). No manual re-upload required.'), $this->version);
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
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
                        'desc' => $this->l('Your primary web container ID (e.g., GTM-XXXXXXX).'),
                        'size' => 20,
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'name' => 'PS_GTM_EVENT_PREFIX',
                        'label' => $this->l('Data Layer Event Prefix'),
                        'desc' => $this->l('Custom prefix for dataLayer events (e.g. raihan, masum, client_a). Leave blank for standard GA4 event names.'),
                        'placeholder' => $this->l('Enter your custom prefix (e.g., raihan)'),
                        'size' => 30,
                    ],
                    [
                        'type' => 'text',
                        'name' => 'PS_SGTM_TRANSPORT_URL',
                        'label' => $this->l('Server-Side GTM Transport URL (Optional)'),
                        'desc' => $this->l('Custom domain endpoint for Server-Side GTM (e.g. https://gtm.yourdomain.com or Stape endpoint).'),
                        'size' => 50,
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable Google Consent Mode v2'),
                        'name' => 'PS_GTM_CONSENT_MODE',
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
        $events[] = [
            'event' => self::formatEventName($canonicalEvent, $eventPrefix),
            'original_event' => $canonicalEvent,
            'ecommerce' => [
                'currency' => $this->context->currency ? $this->context->currency->iso_code : 'EUR',
                'value' => round((float)$item['price'] * (int)$item['quantity'], 2),
                'items' => [$item],
            ],
        ];

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

        $this->context->smarty->assign([
            'gtm_id' => $gtmId,
            'sgtm_url' => $sgtmUrl,
            'consent_mode_enabled' => $consentEnabled,
            'event_prefix' => $eventPrefix,
            'ajax_url' => $ajaxUrl,
            'pending_events' => !empty($pendingEvents) ? json_encode($pendingEvents, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            'product_combinations' => json_encode($combosMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'initial_server_payload' => $serverPayload ? json_encode($serverPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
        ]);

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

        return $this->display(__FILE__, 'views/templates/hook/gtm_head.tpl')
            . $this->display(__FILE__, 'views/templates/hook/datalayer_init.tpl');
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
            window.dataLayer.push({ ecommerce: null });
            window.dataLayer.push(' . json_encode($purchasePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ');
        </script>';
    }
}
