<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
require_once _PS_MODULE_DIR_ . 'tamaraprestashop/TamaraConfiguration.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

class TamaraPrestashop extends PaymentModule
{

    protected $_html = '';
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

    public function hookHeader()
    {
        // if a global JS code is needed
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

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $address = new Address((int)$this->context->cart->id_address_delivery);
        $client_country = new Country($address->id_country);
        $paymentOptionsCacheKey = sprintf("%s_%s_%s_%s", $client_country->iso_code, (float)$this->context->cart->getOrderTotal(true, Cart::BOTH) * 100, $this->removeSpecialCharacters($address->phone), '1');
        if ($this->context->cookie->__isset($paymentOptionsCacheKey)
            && $this->context->cookie->__isset('tmr-payment-options-cookie-time')
            && (time() - intval($this->context->cookie->__get('tmr-payment-options-cookie-time')) < 300)
        ) {
            $PO = json_decode($this->context->cookie->__get($paymentOptionsCacheKey), true);
            $PO2 = [];
            foreach ($PO as $index) {
                foreach ($index as $ind) {
                    array_push($PO2, $this->getExternalPaymentOption((float)$this->context->cart->getOrderTotal(true, Cart::BOTH), $ind[0], $ind[1], $ind[2], $ind[3], $this->context->cookie->__get('single_checkout_enabled'), count($PO)));
                }
            }
            return $PO2;
        } else {
            $address = new Address((int)$this->context->cart->id_address_delivery);
            $client_country = new Country($address->id_country);
            $payload = array(
                "country" => $client_country->iso_code,
                "order_value" => array(
                    "amount" => (float)$this->context->cart->getOrderTotal(true, Cart::BOTH),
                    "currency" => $this->context->currency->iso_code
                ),
                "phone_number" => $address->phone,
                "is_vip" => true
            );
            $param = "checkout/payment-options-pre-check";
            $precheckEndpoint = $this->getMode($param);
            $total = "";
            foreach ($payload["order_value"] as $k => $v) {
                if ($k == 'amount')
                    $total .= $v;
            }
            $this->context->cookie->__set('total', $total);
            $this->context->cookie->write();
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $precheckEndpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt(
                $ch, CURLOPT_HTTPHEADER,
                array(
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . Tools::getValue('api_token', TamaraConfiguration::get('api_token')),
                )
            );
            $response = curl_exec($ch);
            PrestaShopLogger::addLog("payment-options-pre-check res: " . $response);
            $res_decoded = json_decode($response, true);
            curl_close($ch);
            $single_checkout_enabled = $res_decoded['single_checkout_enabled'];
            $this->context->cookie->__set('single_checkout_enabled', $single_checkout_enabled);
            $this->context->cookie->write();
            if ($res_decoded['has_available_payment_options'] == 1) {
                $labels = $res_decoded['available_payment_labels'];

                $counter = 1;
                $payment_options = [];
                foreach ($labels as $value) {
                    foreach ($value as $k => $v) {

                        if ($k == 'payment_type') {
                            ${"payment_type$counter"} = $v;
                        }
                        if ($k == 'instalment') {
                            ${"instalment$counter"} = $v;
                        }
                        if ($k == 'description_en') {
                            ${"description_en$counter"} = $v;
                        }
                        if ($k == 'description_ar') {
                            ${"description_ar$counter"} = $v;
                        }
                    }
                    $counter++;
                }

                for ($i = 1; $i < $counter; $i++) {
                    array_push($payment_options, array($i => array(${"payment_type$i"}, ${"instalment$i"}, ${"description_en$i"}, ${"description_ar$i"})));
                }
                $this->context->cookie->__set($paymentOptionsCacheKey, json_encode($payment_options));
                $this->context->cookie->write();
                $this->context->cookie->__set('tmr-payment-options-cookie-time', time());
                $this->context->cookie->write();

                $payment_options2 = [];
                foreach ($payment_options as $index) {
                    foreach ($index as $ind) {
                        array_push($payment_options2, $this->getExternalPaymentOption($total, $ind[0], $ind[1], $ind[2], $ind[3], $single_checkout_enabled, count($payment_options)));
                    }
                }
                return $payment_options2;
            } else {
                return [];
            }
        }
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
        $label = "";
        $logoURL = "";
        if ($this->context->language->iso_code == 'ar') {
            $logoURL .= 'https://cdn.tamara.co/widget-v2/assets/tamara-grad-ar.ab6b918f.svg';
            $label .= $desc_ar;
        } elseif ($this->context->language->iso_code == 'en') {
            $logoURL .= 'https://cdn.tamara.co/widget-v2/assets/tamara-grad-en.a044e01d.svg';
            $label .= $desc_en;
        }
        $externalOption = new PaymentOption();
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
