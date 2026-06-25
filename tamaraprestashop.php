<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
require_once _PS_MODULE_DIR_ . 'tamaraprestashop/TamaraConfiguration.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

class TamaraPrestashop extends PaymentModule
{

    protected $_html = '';

    const ELIGIBILITY_TIMEOUT_PRODUCTION_MS = 200;
    const ELIGIBILITY_TIMEOUT_SANDBOX_MS = 3000;
    const ELIGIBILITY_FALLBACK_EMAIL = 'precheck-fallback@example.com';
    const PAYMENT_OPTIONS_CACHE_TTL = 300;

    public function __construct()
    {
        $this->name = 'tamaraprestashop';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'Tamara Team';
        $this->need_instance = 0;
        $this->controllers = array('validation', 'callback', 'webhook');
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Tamara');
        $this->description = $this->l('Buy now Pay later');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    public function getValueOfOption($orderNum, $jsonString)
    {
        $array = json_decode(json_encode(json_decode($jsonString)), true);
        $returnedVal = "";
        if ($array[$orderNum] == '1') {
            $returnedVal .= 'on';
        }
        return $returnedVal ?? null;
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submit' . $this->name)) {

            if (($this->notValidSettings((string)Tools::getValue('public_key')) == true) || ($this->notValidSettings((string)Tools::getValue('api_token')) == true) ||
                ($this->notValidSettings((string)Tools::getValue('not_url')) == true)
            ) {
                $output = $this->displayError('Fill mandatory fields');
            }
            if (!$this->merchantFound((string)Tools::getValue('api_token'), (string)Tools::getValue('mode'))) {
                $output = $this->displayError('Merchant not found, please retype your API Token.');
            } else {
                if (Tools::getValue('enable_plugin_1') !== 'on') {
                    Module::getInstanceByName('tamaraprestashop')->disable();
                }
                if (Tools::getValue('enable_plugin_1') == 'on') {
                    Module::getInstanceByName('tamaraprestashop')->enable();
                }
                TamaraConfiguration::set('enable_plugin', $this->fixUpdateCheckboxValue(Tools::getValue('enable_plugin_1')));
                TamaraConfiguration::set('mode', Tools::getValue('mode'));
                TamaraConfiguration::set('public_key', Tools::getValue('public_key'));
                TamaraConfiguration::set('api_token', Tools::getValue('api_token'));
                TamaraConfiguration::set('not_url', Tools::getValue('not_url'));
                TamaraConfiguration::set('product_widget_pos', Tools::getValue('product_widget_pos'));
                TamaraConfiguration::set('cart_widget_pos', Tools::getValue('cart_widget_pos'));

                $output = $this->displayConfirmation($this->l('Settings updated'));
            }
        }
        return $output . $this->displayForm();
    }

    public function fixUpdateCheckboxValue($val, $mutliCheckboxVal = false)
    {
        if ($val == 'on' && $mutliCheckboxVal == false) {
            return '1';
        } elseif ($val == 'on' && $mutliCheckboxVal == 'one') {
            return '1';
        } elseif ($val == 'on' && $mutliCheckboxVal == 'two') {
            return '1';
        } elseif ($val == 'on' && $mutliCheckboxVal == 'three') {
            return '1';
        }
    }

    public function notValidSettings($param)
    {
        if (empty($param) == '1') {
            return true;
        } else {
            return false;
        }
    }

    public function merchantFound($token, $mode)
    {
        $merchantConfig = '';
        $prod = "https://api.tamara.co/";
        $sandbox = "https://api-sandbox.tamara.co/";
        if ($mode == 1) {
            $merchantConfig .= $sandbox . "merchants/configs";
        } elseif ($mode == 2) {
            $merchantConfig .= $prod . "merchants/configs";
        } else {
            $merchantConfig .= $prod . "merchants/configs";
        }

        $ch = curl_init($merchantConfig);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/json', // for define content type that is json
                'Authorization: Bearer ' . $token, // send token in header request
            )
        );
        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_status == '200') {
            return true;
        } else {
            return false;
        }

    }

    public function displayForm()
    {

        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                ],
                'input' => [
                    [
                        'type' => 'checkbox',
                        'label' => $this->l('Enable/Disable Plugin'),
                        'name' => 'enable_plugin',
                        'required' => false,
                        'values' => array(
                            'query' => array(
                                array('key' => '1', 'name' => 'Enable Tamara Payment')
                            ),
                            'id' => 'key',
                            'name' => 'name',
                        ),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Mode'),
                        'name' => 'mode',
                        'required' => true,
                        'options' => array(
                            'query' => array(
                                array('key' => '1', 'name' => 'Sandbox'),
                                array('key' => '2', 'name' => 'Production')
                            ),
                            'id' => 'key',
                            'name' => 'name'
                        ),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Public Key'),
                        'name' => 'public_key',
                        'size' => 20,
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('API Token'),
                        'name' => 'api_token',
                        'required' => true,
                        'class' => 'xl',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Notification Token'),
                        'name' => 'not_url',
                        'size' => 20,
                        'required' => true,
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Tamara product widget position'),
                        'name' => 'product_widget_pos',
                        'required' => true,
                        'options' => array(
                            'query' => [['key' => 'hookDisplayCheckoutSubtotalDetails', 'name' => 'hookDisplayCheckoutSubtotalDetails']],
                            'id' => 'key',
                            'name' => 'name'
                        ),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Tamara cart widget position'),
                        'name' => 'cart_widget_pos',
                        'required' => true,
                        'options' => array(
                            'query' => [['key' => 'hookDisplayCheckoutSubtotalDetails', 'name' => 'hookDisplayCheckoutSubtotalDetails']],
                            'id' => 'key',
                            'name' => 'name'
                        ),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper = new HelperForm();

        $helper->table = $this->table;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&' . http_build_query(['configure' => $this->name]);
        $helper->submit_action = 'submit' . $this->name;

        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');

        $helper->fields_value['enable_plugin_1'] = $this->fixDisplayCheckboxValue(TamaraConfiguration::get('enable_plugin'));
        $helper->fields_value['mode'] = Tools::getValue('mode', TamaraConfiguration::get('mode'));
        $helper->fields_value['public_key'] = Tools::getValue('public_key', TamaraConfiguration::get('public_key'));
        $helper->fields_value['api_token'] = Tools::getValue('api_token', TamaraConfiguration::get('api_token'));
        $helper->fields_value['not_url'] = Tools::getValue('not_url', TamaraConfiguration::get('not_url'));
        $helper->fields_value['product_widget_pos'] = Tools::getValue('product_widget_pos', TamaraConfiguration::get('product_widget_pos'));
        $helper->fields_value['cart_widget_pos'] = Tools::getValue('cart_widget_pos', TamaraConfiguration::get('cart_widget_pos'));

        return $helper->generateForm([$form]);
    }

    public function fixDisplayCheckboxValue($val)
    {
        if (($val == '1') || ($val == 'one') || ($val == 'two') || ($val == 'three')) {
            return 'on';
        } else {
            return null;
        }
    }

    public function install()
    {
        if (!$this->addTamaraTable())
            return false;
        if (!$this->addTamaraMerchantTable())
            return false;
        if (!$this->registerWebhook())
            return false;
        if (
            !parent::install() || !$this->registerHook('paymentOptions') || !$this->registerHook('paymentReturn') ||
            !$this->registerHook('actionObjectOrderAddBefore') || !$this->registerHook('actionObjectOrderAddAfter') || !$this->registerHook('actionValidateOrder') || !$this->addOrderState($this->l('Awaiting Tamara Payment'))
            || !$this->registerHook('actionOrderStatusPostUpdate') || !$this->registerHook('actionProductCancel') || !$this->registerHook('displayHeader') || !$this->registerHook('header')
            || !$this->registerHook('displayProductAdditionalInfo') || !$this->registerHook('displayCheckoutSubtotalDetails')
            || !$this->registerHook('actionPresentCart') || !$this->registerHook('displayProductPriceBlock')
        ) {
            PrestaShopLogger::addLog("could not install ");

            return false;
        }
        PrestaShopLogger::addLog("installleddd!!!!");
        return true;
    }

    public function addTamaraTable()
    {
        $sql_content = "CREATE TABLE IF NOT EXISTS `PREFIX_tamara` (
            `id` int NOT NULL AUTO_INCREMENT ,
            `order_id` varchar(255) NOT NULL,
            `checkout_id` varchar(255) NOT NULL,
            `checkout_url` varchar(255) NOT NULL,
            `status` varchar(255) NOT NULL,
            `id_cart` int NOT NULL,
            `id_order` int NOT NULL,
            `capture_id` varchar(255),
            `cancel_id` varchar(255),
            `refund_id` varchar(255),
            PRIMARY KEY (`id`)
          )DEFAULT CHARSET=UTF8;";
        $sql_content = str_replace('PREFIX_', _DB_PREFIX_, $sql_content);
        $sql_requests = preg_split("/;\s*[\r\n]+/", $sql_content);
        $result = true;
        foreach ($sql_requests as $request)
            if (!empty($request))
                $result &= Db::getInstance()->execute(trim($request));

        PrestaShopLogger::addLog("addTamaraTable res: " . $result);
        return $result;
    }

    public function addTamaraMerchantTable()
    {
        $sql_content = "CREATE TABLE IF NOT EXISTS `PREFIX_tamara_merchant` (
            `merchant_id` varchar(255) NOT NULL,
            `webhook_id` varchar(255) NOT NULL
          )DEFAULT CHARSET=UTF8;";
        $sql_content = str_replace('PREFIX_', _DB_PREFIX_, $sql_content);
        $sql_requests = preg_split("/;\s*[\r\n]+/", $sql_content);
        $result = true;
        foreach ($sql_requests as $request)
            if (!empty($request))
                $result &= Db::getInstance()->execute(trim($request));

        PrestaShopLogger::addLog("addTamaraMerchantTable res: " . $result);
        return $result;
    }

    public function registerWebhook()
    {
        $param = "webhooks";
        $registerWebhookEndpoint = $this->getMode($param);
        $payload = array("url" => $this->context->link->getModuleLink('tamaraprestashop', 'webhook', array()),
            "events" => ["order_approved"]);
        $ch = curl_init($registerWebhookEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/json', // for define content type that is json
                'Authorization: Bearer ' . Tools::getValue('api_token', TamaraConfiguration::get('api_token')), // send token in header request
            )
        );
        $response = curl_exec($ch);
        $res_decoded = json_decode($response, true);
        curl_close($ch);
        if (isset($res_decoded['webhook_id'])) {
            Db::getInstance()->execute('
    INSERT INTO ' . _DB_PREFIX_ . 'tamara_merchant (webhook_id, merchant_id) VALUES
      ("' . $res_decoded['webhook_id'] . '", "' . $res_decoded['webhook_id'] . '")');
        }
        return true;
    }

    public function getMode($endpoint)
    {
        if ((int) TamaraConfiguration::get('mode', 1) === 1) {
            return "https://api-sandbox.tamara.co/" . $endpoint;
        } elseif ((int) TamaraConfiguration::get('mode', 2) === 2) {
            return "https://api.tamara.co/" . $endpoint;
        } else {
            return "https://api.tamara.co/" . $endpoint;
        }
    }

    private function getEligibilityTimeoutMs()
    {
        return (int) TamaraConfiguration::get('mode', 1) === 1
            ? self::ELIGIBILITY_TIMEOUT_SANDBOX_MS
            : self::ELIGIBILITY_TIMEOUT_PRODUCTION_MS;
    }

    public function addOrderState($name)
    {
        $state_exist = false;
        $languages = Language::getLanguages(false);

        // Check if the order state already exists in any language
        $states = OrderState::getOrderStates((int)$this->context->language->id);
        foreach ($states as $state) {
            foreach ($languages as $lang) {
                if (isset($state['name']) && $state['name'] === $name) {
                    $state_exist = true;
                    break 2;
                }
            }
        }

        if (!$state_exist) {
            $order_state = new OrderState();
            $order_state->color = '#00ffff';
            $order_state->send_email = false;
            $order_state->module_name = $this->name;
            $order_state->invoice = false;
            $order_state->hidden = false;
            $order_state->logable = true;

            // Set the order state name for all active languages
            foreach ($languages as $lang) {
                $order_state->name[$lang['id_lang']] = $name;
            }

            if ($order_state->add()) {
                PrestaShopLogger::addLog('Order state added: ' . $name);
                Configuration::updateValue('AWAITING_TAMARA_PAYMENT', $order_state->id);
            } else {
                PrestaShopLogger::addLog('Install: Cannot create order state: ' . $name);
            }
        }

        return true;
    }


    public function uninstall()
    {
        if (!parent::uninstall())
            return false;

        # TODO dublicate code, clean it.
        $sql_content = 'DROP TABLE `PREFIX_tamara`;';
        $sql_content2 = 'DROP TABLE `PREFIX_tamara_merchant`;';
        $sql_content = str_replace('PREFIX_', _DB_PREFIX_, $sql_content);
        Db::getInstance()->execute($sql_content);
        $sql_content2 = str_replace('PREFIX_', _DB_PREFIX_, $sql_content2);
        Db::getInstance()->execute($sql_content2);
        PrestaShopLogger::addLog("UNINSTALLED!!!!");
        return true;
    }
    public function hookActionOrderStatusPostUpdate($params)
    {
        $newStatus = "";
        $idOrder = 0;
        foreach ($params as $key => $value) {

            if (is_object($value)) {

                foreach ($value as $k => $v) {

                    if ($k == 'name' && ($v == 'Shipped' || $v == 'Canceled')) {
                        $newStatus = $newStatus . "" . $v;
                    }

                    //break statement
                }
            }

            if ($key == "id_order") {
                $idOrder = $value + $idOrder;
                break;
            }
        }

        $orderRetrieveTot = 'SELECT `total_price_tax_incl` FROM `' . _DB_PREFIX_ . 'order_detail` WHERE `id_order`=' . $idOrder;
        $total_price_tax_incl = Db::getInstance()->getValue($orderRetrieveTot);
        $total_formatted = number_format($total_price_tax_incl, 2, '.', '');

        $orderRetrieve = 'SELECT `order_id` FROM `' . _DB_PREFIX_ . 'tamara` WHERE `id_order`=' . $idOrder;
        $order_id = Db::getInstance()->getValue($orderRetrieve);

        if ($newStatus == "Shipped") {

            $payload = array(
                "order_id" => "" . $order_id,
                "total_amount" => array(
                    "amount" => (float)$total_formatted,
                    "currency" => $this->context->currency->iso_code
                )
            );
            $param = "payments/capture";
            $captureEndpoint = $this->getMode($param);
            $ch = curl_init($captureEndpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                array(
                    'Content-Type: application/json', // for define content type that is json
                    'Authorization: Bearer ' . Tools::getValue('api_token', TamaraConfiguration::get('api_token')), // send token in header request
                )
            );
            $response = curl_exec($ch);
            $res_decoded = json_decode($response, true);
            $sql1 = "UPDATE " . _DB_PREFIX_ . "tamara SET capture_id='" . $res_decoded['capture_id'] . "' WHERE order_id='" . $res_decoded['order_id'] . "'";
            $sql2 = "UPDATE " . _DB_PREFIX_ . "tamara SET status='" . $res_decoded['status'] . "' WHERE order_id='" . $res_decoded['order_id'] . "'";
            Db::getInstance()->execute($sql1);
            Db::getInstance()->execute($sql2);
            curl_close($ch);
        }
        if ($newStatus == "Canceled") {
            $payload = array(
                "total_amount" => array(
                    "amount" => "" . $total_formatted,
                    "currency" => $this->context->currency->iso_code
                )
            );

            $param = "orders/" . $order_id . "/cancel";
            $cancelEndpoint = $this->getMode($param);
            $ch = curl_init($cancelEndpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                array(
                    'Content-Type: application/json', // for define content type that is json
                    'Authorization: Bearer ' . Tools::getValue('api_token', TamaraConfiguration::get('api_token')), // send token in header request
                )
            );
            $response = curl_exec($ch);
            $res_decoded = json_decode($response, true);
            curl_close($ch);
            if (isset($res_decoded['cancel_id'])) {
                $sql1 = "UPDATE " . _DB_PREFIX_ . "tamara SET cancel_id='" . $res_decoded['cancel_id'] . "' WHERE order_id='" . $order_id . "'";
                $sql2 = "UPDATE " . _DB_PREFIX_ . "tamara SET status='" . $res_decoded['status'] . "' WHERE order_id='" . $order_id . "'";
                Db::getInstance()->execute($sql1);
                Db::getInstance()->execute($sql2);
            } else {
                PrestaShopLogger::addLog("Could not cancel order: " . $order_id);
                PrestaShopLogger::addLog("Response: " . $response);
                throw new Exception('');
            }
        }
    }

    public function hookActionProductCancel($params)
    {
        $idCart = 0;
        $action = 0;
        foreach ($params as $key => $value) {
            if ($key == 'order') {
                foreach ($value as $k => $v) {
                    if ($k == 'id_cart') {
                        $idCart = $v + $idCart;
                        break;
                    }
                }
            } elseif ($key == "action") {
                $action = $value + $action;

                break;
            }
        }
        $query1 = 'SELECT `id_order`  FROM `' . _DB_PREFIX_ . 'orders` WHERE `id_cart`=' . $idCart;
        $id_order = Db::getInstance()->getValue($query1);
        $query2 = 'SELECT `total_paid_tax_incl`  FROM `' . _DB_PREFIX_ . 'orders` WHERE `id_order`=' . $id_order;
        $total_paid_tax_incl = Db::getInstance()->getValue($query2);
        $query3 = 'SELECT `order_id`, `capture_id` FROM `' . _DB_PREFIX_ . 'tamara` WHERE `id_cart`=' . $idCart;
        $orderRetrieve = Db::getInstance()->getRow($query3);
        $amount_format = number_format($total_paid_tax_incl, 2, '.', '');
        if ($action == 3) { //RETURN PRODUCT BTN(after shipping) => refund request
            $payload = array(
                "order_id" => "" . $orderRetrieve['order_id'],
                "refunds" => array(
                    array(
                        "capture_id" => "" . $orderRetrieve['capture_id'],
                        "total_amount" => array(
                            "amount" => "" . $amount_format,
                            "currency" => $this->context->currency->iso_code
                        )
                    )
                )
            );
            $param = "payments/refund";
            $refundEndpoint = $this->getMode($param);
            $ch = curl_init($refundEndpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                array(
                    'Content-Type: application/json', // for define content type that is json
                    'Authorization: Bearer ' . Tools::getValue('api_token', TamaraConfiguration::get('api_token')), // send token in header request
                )
            );
            $response = curl_exec($ch);
            $res_decoded = json_decode($response, true);
            curl_close($ch);
            if (isset($res_decoded['refunds'])) {
                $refund_id = "";
                foreach ($res_decoded['refunds'][0] as $key => $value) {
                    if ($key == 'refund_id') {
                        $refund_id = $refund_id . "" . $value;
                    }
                }

                $sql1 = "UPDATE " . _DB_PREFIX_ . "tamara SET refund_id='" . $refund_id . "' WHERE order_id='" . $orderRetrieve['order_id'] . "'";
                $sql2 = "UPDATE " . _DB_PREFIX_ . "tamara SET status='" . $res_decoded['status'] . "' WHERE order_id='" . $orderRetrieve['order_id'] . "'";
                Db::getInstance()->execute($sql1);
                Db::getInstance()->execute($sql2);
            } else {
                PrestaShopLogger::addLog("Could not refund order: " . $orderRetrieve['order_id']);
                PrestaShopLogger::addLog("Response: " . $response);
                throw new Exception('');
            }
        } elseif ($action == 1) { // STANDARD REFUND BTN (before shipping) => cancel request
        } elseif ($action == 0) { // CANCEL_PRODUCT
        }
    }

    public function hookDisplayHeader($params)
    {
        return $this->hookHeader($params);
    }

    private function isCheckoutPage()
    {
        if (!isset($this->context->controller)) {
            return false;
        }

        $controller = $this->context->controller;
        if ($controller instanceof OrderControllerCore) {
            return true;
        }
        if (isset($controller->php_self) && $controller->php_self === 'order') {
            return true;
        }
        if (Tools::getValue('controller') === 'order') {
            return true;
        }

        $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if (strpos($requestUri, '/order') !== false) {
            return true;
        }

        return false;
    }

    public function hookHeader()
    {
        if ($this->isCheckoutPage()) {
            $this->context->controller->registerStylesheet(
                'module-tamaraprestashop-checkout',
                'modules/' . $this->name . '/views/css/tamara-checkout.css',
                ['media' => 'all', 'priority' => 200]
            );
            $this->context->controller->registerJavascript(
                'module-tamaraprestashop-checkout',
                'modules/' . $this->name . '/views/js/tamara-checkout.js',
                ['position' => 'bottom', 'priority' => 200]
            );
        }

        $this->context->controller->addJS($this->_path . 'views/js/main.js', 'all');
        $url = "";
        $installmentWidgetUrl = "";
        if (Tools::getValue('mode', TamaraConfiguration::get('mode')) == 1) {
            $url .= "https://cdn-sandbox.tamara.co/widget-v2/tamara-widget.js";
            $installmentWidgetUrl = "https://cdn-sandbox.tamara.co/widget/installment-plan.min.js";
        } else {
            $url .= "https://cdn.tamara.co/widget-v2/tamara-widget.js";
            $installmentWidgetUrl = "https://cdn.tamara.co/widget/installment-plan.min.js";
        }
        $this->smarty->assign([
            'public_key' => TamaraConfiguration::get('public_key'),
            'lang' => $this->context->language->iso_code,
            'country' => Tools::getValue('PS_LOCALE_COUNTRY', Configuration::get('PS_LOCALE_COUNTRY')),
            'url' => $url,
            'currency' => $this->context->currency->iso_code,
            'installmentWidgetUrl' => $installmentWidgetUrl
        ]);

        return $this->display(__FILE__, '/themes/header.tpl');
    }

    public function hookDisplayProductAdditionalInfo($params)
    {
        echo '<tamara-widget type="tamara-summary" amount="' . $params['product']['rounded_display_price'] . '" inline-type="2"></tamara-widget>';
    }

    public function hookDisplayCheckoutSubtotalDetails($params)
    {
        return $this->display(__FILE__, 'getContent.tpl');
    }

    public function hookActionPresentCart($params)
    {
        $this->smarty->assign('total_in_cart', $params['presentedCart']['totals']['total']['amount']);
    }

    public function getCustomerPhone(Address $address, $currency = null)
    {
        $phone = trim((string) $address->phone);
        if ($phone === '') {
            $phone = trim((string) $address->phone_mobile);
        }

        return $this->normalizePhoneNumber($phone, $currency);
    }

    public function normalizePhoneNumber($phone, $currency = null)
    {
        $phone = trim((string) $phone);
        $phone = str_replace(' ', '', $phone);

        if ($phone === '') {
            return '';
        }

        $phone = preg_replace('/\D/', '', $phone);

        if ($phone === '') {
            return '';
        }

        if (strpos($phone, '966') === 0 || strpos($phone, '971') === 0) {
            return $phone;
        }

        $phone = ltrim($phone, '0');

        if ($phone === '') {
            return '';
        }

        if (strpos($phone, '5') !== 0) {
            return $phone;
        }

        $currency = strtoupper((string) $currency);
        if ($currency === 'SAR') {
            return '966' . $phone;
        }
        if ($currency === 'AED') {
            return '971' . $phone;
        }

        return $phone;
    }

    private function getCustomerEmail(Customer $customer)
    {
        $email = trim((string) $customer->email);

        return Validate::isEmail($email) ? $email : self::ELIGIBILITY_FALLBACK_EMAIL;
    }

    private function getMerchantCountryIso()
    {
        $shopCountryId = (int) Configuration::get('PS_COUNTRY_DEFAULT');
        if ($shopCountryId) {
            return strtoupper(Country::getIsoById($shopCountryId));
        }

        $address = new Address((int) $this->context->cart->id_address_delivery);
        if (Validate::isLoadedObject($address)) {
            return strtoupper((new Country((int) $address->id_country))->iso_code);
        }

        return 'SA';
    }

    private function getCountryPaymentLabels($countryIso)
    {
        $countryIso = strtoupper($countryIso);

        if ($countryIso === 'SA') {
            return [
                'title_en' => 'Tamara',
                'subtitle_en' => 'Monthly Payments. Sharia Compliant.',
                'title_ar' => 'تمارا',
                'subtitle_ar' => 'دفعات شهرية. متوافقة مع الشريعة',
            ];
        }

        if ($countryIso === 'AE') {
            return [
                'title_en' => 'Tamara',
                'subtitle_en' => 'Monthly Payments',
                'title_ar' => 'تمارا',
                'subtitle_ar' => 'دفعات شهريه',
            ];
        }

        return [
            'title_en' => 'Tamara',
            'subtitle_en' => 'Monthly Payments',
            'title_ar' => 'تمارا',
            'subtitle_ar' => 'دفعات شهريه',
        ];
    }

    private function getPaymentLabelForDisplay($countryIso, $includeSubtitle = true, $currencyIso = null)
    {
        if ($currencyIso === null) {
            $currencyIso = $this->context->currency->iso_code;
        }
        $currencyIso = strtoupper((string) $currencyIso);
        $isAr = $this->context->language->iso_code === 'ar';

        if ($currencyIso === 'AED') {
            if (!$includeSubtitle) {
                return $isAr ? 'تمارا' : 'Tamara';
            }

            return $isAr ? 'دفعات شهريه' : 'Monthly Payments';
        }

        $labels = $this->getCountryPaymentLabels($countryIso);
        $title = $isAr ? $labels['title_ar'] : $labels['title_en'];

        if (!$includeSubtitle) {
            return $title;
        }

        $subtitle = $isAr ? $labels['subtitle_ar'] : $labels['subtitle_en'];

        return $subtitle;
    }

    private function getTamaraLogoUrl()
    {
        return $this->context->language->iso_code === 'ar'
            ? 'https://cdn.tamara.co/widget-v2/assets/tamara-grad-ar.ab6b918f.svg'
            : 'https://cdn.tamara.co/widget-v2/assets/tamara-grad-en.a044e01d.svg';
    }

    /**
     * @return string eligible|ineligible|timeout
     */
    private function checkPreCheckoutEligibility($amount, $currency, $phone, $email)
    {
        $payload = [
            'order' => [
                'amount' => (float) $amount,
                'currency' => $currency,
            ],
            'customer' => [
                'phone' => $phone,
                'email' => $email,
            ],
        ];

        $endpoint = $this->getMode('pre-checkout/v1/eligibility');
        $timeoutMs = $this->getEligibilityTimeoutMs();

        PrestaShopLogger::addLog(
            'Tamara pre-checkout eligibility request: '
            . json_encode([
                'endpoint' => $endpoint,
                'timeout_ms' => $timeoutMs,
                'payload' => $payload,
            ])
        );

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . TamaraConfiguration::get('api_token'),
            ],
            CURLOPT_CONNECTTIMEOUT_MS => $timeoutMs,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlErrno === CURLE_OPERATION_TIMEDOUT) {
            PrestaShopLogger::addLog(
                'Tamara pre-checkout eligibility timeout/error: '
                . json_encode([
                    'curl_errno' => $curlErrno,
                    'curl_error' => $curlError,
                    'http_code' => $httpCode,
                ])
            );

            return 'timeout';
        }

        if ($httpCode !== 200) {
            PrestaShopLogger::addLog(
                'Tamara pre-checkout eligibility HTTP error: '
                . json_encode([
                    'http_code' => $httpCode,
                    'response' => $response,
                ])
            );

            return 'timeout';
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || !array_key_exists('is_eligible', $decoded)) {
            PrestaShopLogger::addLog(
                'Tamara pre-checkout eligibility invalid response: '
                . json_encode([
                    'http_code' => $httpCode,
                    'response' => $response,
                ])
            );

            return 'timeout';
        }

        $result = $decoded['is_eligible'] ? 'eligible' : 'ineligible';

        PrestaShopLogger::addLog(
            'Tamara pre-checkout eligibility result: '
            . json_encode([
                'result' => $result,
                'is_eligible' => (bool) $decoded['is_eligible'],
                'response' => $decoded,
            ])
        );

        return $result;
    }

    private function buildPaymentOptionsCacheKey($countryIso, $amount, $phone, $eligibilityStatus)
    {
        return sprintf(
            'tmr_po_%s_%s_%s_%s',
            strtoupper($countryIso),
            (int) round((float) $amount * 100),
            $this->removeSpecialCharacters($phone),
            $eligibilityStatus
        );
    }

    private function getUnavailableTamaraPaymentOption()
    {
        $logoURL = $this->getTamaraLogoUrl();

        $option = new PaymentOption();
        $option
            ->setModuleName($this->name)
            ->setCallToActionText($this->l('Tamara option is not available right now.', 'tamaraprestashop'))
            ->setLogo(Media::getMediaPath($logoURL))
            ->setAdditionalInformation(
                $this->fetch('module:tamaraprestashop/views/templates/front/payment_option_unavailable.tpl')
            );

        return $option;
    }

    private function buildPaymentOptionsFromCache(array $payment_options, $amount, $single_checkout_enabled = null, $payment_options_count = null)
    {
        if ($single_checkout_enabled === null) {
            $single_checkout_enabled = $this->context->cookie->__get('single_checkout_enabled');
        }
        if ($payment_options_count === null) {
            $payment_options_count = count($payment_options);
        }

        $result = [];
        foreach ($payment_options as $index) {
            foreach ($index as $ind) {
                $result[] = $this->getExternalPaymentOption(
                    $amount,
                    $ind[0],
                    $ind[1],
                    $ind[2],
                    $ind[3],
                    $single_checkout_enabled,
                    $payment_options_count
                );
            }
        }

        return $result;
    }

    private function fetchTamaraPaymentOptions(Cart $cart, Address $address, $eligibilityStatus = 'skipped')
    {
        $client_country = new Country((int) $address->id_country);
        $amount = (float) $cart->getOrderTotal(true, Cart::BOTH);
        $phone = $this->getCustomerPhone($address, $this->context->currency->iso_code);
        $cacheKey = $this->buildPaymentOptionsCacheKey(
            $client_country->iso_code,
            $amount,
            $phone,
            $eligibilityStatus
        );

        if (
            $eligibilityStatus !== 'ineligible'
            && $this->context->cookie->__isset($cacheKey)
            && $this->context->cookie->__isset('tmr-payment-options-cookie-time')
            && (time() - (int) $this->context->cookie->__get('tmr-payment-options-cookie-time') < self::PAYMENT_OPTIONS_CACHE_TTL)
        ) {
            $cached = json_decode($this->context->cookie->__get($cacheKey), true);
            if (is_array($cached) && !empty($cached)) {
                return $this->buildPaymentOptionsFromCache($cached, $amount);
            }
        }

        $payload = [
            'country' => $client_country->iso_code,
            'order_value' => [
                'amount' => $amount,
                'currency' => $this->context->currency->iso_code,
            ],
            'phone_number' => $phone,
            'is_vip' => true,
        ];

        $precheckEndpoint = $this->getMode('checkout/payment-options-pre-check');
        $this->context->cookie->__set('total', (string) $amount);
        $this->context->cookie->write();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $precheckEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . TamaraConfiguration::get('api_token'),
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        PrestaShopLogger::addLog('payment-options-pre-check res: ' . $response);
        $res_decoded = json_decode($response, true);

        if (!is_array($res_decoded) || empty($res_decoded['has_available_payment_options'])) {
            return [];
        }

        $single_checkout_enabled = $res_decoded['single_checkout_enabled'];
        $this->context->cookie->__set('single_checkout_enabled', $single_checkout_enabled);
        $this->context->cookie->write();

        $payment_options = [];
        $counter = 1;

        foreach ($res_decoded['available_payment_labels'] as $value) {
            foreach ($value as $k => $v) {
                if ($k === 'payment_type') {
                    ${"payment_type$counter"} = $v;
                }
                if ($k === 'instalment') {
                    ${"instalment$counter"} = $v;
                }
                if ($k === 'description_en') {
                    ${"description_en$counter"} = $v;
                }
                if ($k === 'description_ar') {
                    ${"description_ar$counter"} = $v;
                }
            }
            ++$counter;
        }

        for ($i = 1; $i < $counter; ++$i) {
            $payment_options[] = [
                $i => [
                    ${"payment_type$i"},
                    ${"instalment$i"},
                    ${"description_en$i"},
                    ${"description_ar$i"},
                ],
            ];
        }

        $this->context->cookie->__set($cacheKey, json_encode($payment_options));
        $this->context->cookie->__set('tmr-payment-options-cookie-time', time());
        $this->context->cookie->write();

        return $this->buildPaymentOptionsFromCache(
            $payment_options,
            $amount,
            $single_checkout_enabled,
            count($payment_options)
        );
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active || !$this->checkCurrency($params['cart'])) {
            return;
        }

        $cart = $params['cart'];
        $address = new Address((int) $cart->id_address_delivery);
        $customer = new Customer((int) $cart->id_customer);
        $amount = (float) $cart->getOrderTotal(true, Cart::BOTH);
        $currency = $this->context->currency->iso_code;
        $phone = $this->getCustomerPhone($address, $currency);

        if ($phone === '') {
            return $this->fetchTamaraPaymentOptions($cart, $address, 'skipped');
        }

        $email = $this->getCustomerEmail($customer);
        $eligibility = $this->checkPreCheckoutEligibility($amount, $currency, $phone, $email);


        if ($eligibility === 'ineligible') {
            return [$this->getUnavailableTamaraPaymentOption()];
        }

        return $this->fetchTamaraPaymentOptions($cart, $address, $eligibility);
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function removeSpecialCharacters($str)
    {
        $str = str_replace(' ', '-', $str); // Replaces all spaces with hyphens.
        return preg_replace('/[^A-Za-z0-9\-]/', '', $str);
    }

    public function getExternalPaymentOption($total, $type, $instalment, $desc_en, $desc_ar, $single_checkout_enabled, $payment_options_count)
    {
        $countryIso = $this->getMerchantCountryIso();
        $logoURL = $this->getTamaraLogoUrl();
        $label = $this->getPaymentLabelForDisplay($countryIso, true, $this->context->currency->iso_code);

        $externalOption = new PaymentOption();
        $externalOption->setModuleName($this->name);
        $this->context->smarty->assign('total', $total);
        $this->context->smarty->assign('instalment', $instalment);
        $this->context->smarty->assign('public_key', Tools::getValue('public_key', TamaraConfiguration::get('public_key')));
        $this->context->smarty->assign('lang', $this->context->language->iso_code);
        $this->context->smarty->assign('country', Tools::getValue('PS_LOCALE_COUNTRY', Configuration::get('PS_LOCALE_COUNTRY')));
        $this->context->smarty->assign('currency', $this->context->currency->iso_code);
        $this->context->smarty->assign('configData',['badgePosition' => '', 'showExtraContent' => 'full', 'hidePayInX' => false]);
        PrestaShopLogger::addLog('  ' . $payment_options_count);
        PrestaShopLogger::addLog('  ' . $single_checkout_enabled);

        if (($single_checkout_enabled == 0) && ($payment_options_count > 1)) {
            PrestaShopLogger::addLog('Single not enabled');
            PrestaShopLogger::addLog('$$$$$$$$$$');
            PrestaShopLogger::addLog('' . $payment_options_count);
            if ((strpos($desc_en, 'Split') !== false) || strpos($desc_ar, 'قسم') !== false) {
                $externalOption->setCallToActionText($label)
                    ->setAction($this->context->link->getModuleLink($this->name, 'validation', array('type' => $type, 'instalment' => $instalment), true))
                    ->setAdditionalInformation($this->context->smarty->fetch('module:tamaraprestashop/views/templates/front/payment_option.tpl'))
                    ->setLogo(Media::getMediaPath($logoURL));
            } else {
                $externalOption->setCallToActionText($label)
                    ->setAction($this->context->link->getModuleLink($this->name, 'validation', array('type' => $type, 'instalment' => $instalment), true))
                    ->setLogo(Media::getMediaPath($logoURL));
            }
        } else {
            PrestaShopLogger::addLog('Single enabled');
            if ($type == "PAY_NOW" || $type == "PAY_NEXT_MONTH") {
                $externalOption->setCallToActionText($label)
                    ->setAdditionalInformation($this->context->smarty->fetch('module:tamaraprestashop/views/templates/front/pif.tpl'))
                    ->setAction($this->context->link->getModuleLink($this->name, 'validation', array('type' => $type, 'instalment' => $instalment), true))
                    ->setLogo(Media::getMediaPath($logoURL));
            } else {
                $externalOption->setCallToActionText($label)
                    ->setAction($this->context->link->getModuleLink($this->name, 'validation', array('type' => $type, 'instalment' => $instalment), true))
                    ->setAdditionalInformation($this->context->smarty->fetch('module:tamaraprestashop/views/templates/front/payment_option_single.tpl'))
                    ->setLogo(Media::getMediaPath($logoURL));
            }
        }
        return $externalOption;
    }


    public function hookPaymentReturn($params)
    {
        return $this->display(__FILE__, 'payment_return.tpl');
    }
}
