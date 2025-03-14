<?php

class TamaraPrestashopValidationModuleFrontController extends ModuleFrontController
{

  public function postProcess()
  {
    PrestaShopLogger::addLog("payment type: ".$_GET['type']);
    $mode = Tools::getValue('mode', Configuration::get('mode'));
    $prod = "https://api.tamara.co/";
    $sandbox = "https://api-sandbox.tamara.co/";
    $cart = $this->context->cart;
    $address = new Address((int)$cart->id_address_delivery);
    $client_country = new Country($address->id_country);
    
    $this->context->cookie->__unset('PaymentOptions');
    $this->context->cookie->__unset('total');
    $this->context->cookie->__unset('single_checkout_enabled');
    $this->context->cookie->__unset('payment_options_count');

    $phone = '';
    if (isset($address->phone)){
      $phone .= $address->phone;
    }

    if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
      Tools::redirect('index.php?controller=order&step=1');
    }

    $authorized = false;
    foreach (Module::getPaymentModules() as $module) {
      if ($module['name'] == 'tamaraprestashop') {
        $authorized = true;
        break;
      }
    }

    if (!$authorized) {
      die($this->module->l('This payment method is not available.', 'validation'));
    }

    $customer = new Customer($cart->id_customer);
    if (!Validate::isLoadedObject($customer))
      Tools::redirect('index.php?controller=order&step=1');

      $total = (float) $this->context->cart->getOrderTotal(true, Cart::BOTH);
 
      $this->module->validateOrder(
        (int) $this->context->cart->id,
        Tools::getValue('AWAITING_TAMARA_PAYMENT', Configuration::get('AWAITING_TAMARA_PAYMENT')),
        $total,
        $this->module->displayName,
        null,
        null,
        (int) $this->context->currency->id,
        false,
        $customer->secure_key
      );

    $callback = $this->context->link->getModuleLink('tamaraprestashop', 'callback', array());
    $notification = $this->context->link->getModuleLink('tamaraprestashop', 'webhook', array());

    $reference_id = mt_rand(10000000,99999999);

    $orderRetrieve = "SELECT `id_order` FROM `" . _DB_PREFIX_ . "orders` WHERE `id_cart`=".$this->context->cart->id."";
    $id_order = Db::getInstance()->getValue($orderRetrieve);

        $orderDetailsRetrieve = 'SELECT `product_name`, `product_quantity`, `product_id` FROM `' . _DB_PREFIX_ . 'order_detail` WHERE `id_order`=' . $id_order;
        $result = Db::getInstance()->executeS($orderDetailsRetrieve);
        PrestaShopLogger:: addLog('######');
        $items_prep = array();
        foreach($result as $res)
        {
            array_push($items_prep, [$res['product_name'], $res['product_quantity'],$res['product_id']]);
        }
        $items = array();
        foreach($items_prep as $it){
            $product = new Product($it[2], false, $this->context->language->id);
            $link = new Link();
            $url = $link->getProductLink($product);

            array_push($items,  [
                "reference_id" => (string)$reference_id,
                "type" => "Digital",
                "name" => $it[0],
                "sku" => "None",
                "quantity" => $it[1],
                "total_amount" => [
                    "amount" => (string)$total,
                    "currency" => $this->context->currency->iso_code
                ],
                "item_url" => $url
            ]);
        }

    $body = array(
      "order_reference_id" => $id_order,
      "order_number"=> $id_order,
      "total_amount" => array(
        "amount" => $total,
        "currency" => $this->context->currency->iso_code
      ),
      "description" => "string",
      "country_code" => $client_country->iso_code,
      "payment_type" => $_GET['type'],
      "instalments" => (int)$_GET['instalment'],
      "items" => $items,
      "consumer" => array(
        "first_name" => $this->context->customer->firstname ,
        "last_name" => $this->context->customer->lastname,
        "phone_number" => $phone,
        "email" => $this->context->customer->email
      ),
      "shipping_address" => array(
        "first_name" => $this->context->customer->firstname,
        "last_name" => $this->context->customer->lastname,
        "line1" => $address->address1,
        "city" => $address->city,
        "country_code" =>$client_country->iso_code,
      ),
      "tax_amount" => array(
        "amount" => (string)$total,
        "currency" => $this->context->currency->iso_code
      ),
      "shipping_amount" => array(
        "amount" => "00.00",
        "currency" => $this->context->currency->iso_code
      ),
      "merchant_url" => array(
        "success" => $callback,
        "failure" => $callback,
        "cancel" => $callback,
        "notification" => $notification
      ),
      "platform"=> "Prestashop"
    );

    $sessionEndpoint = "";
    if ( $mode == 1){
      $sessionEndpoint .= $sandbox . "checkout";
    } elseif($mode == 2) {
      $sessionEndpoint .= $prod . "checkout";
    } else {
      $sessionEndpoint .= $prod . "checkout";
    }
    $ch = curl_init($sessionEndpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt(
      $ch,
      CURLOPT_HTTPHEADER,
      array(
        'Content-Type: application/json', // for define content type that is json
        'Authorization: Bearer '.Tools::getValue('api_token', Configuration::get('api_token')), // send token in header request
      )
    );
    $response = curl_exec($ch);
    PrestaShopLogger::addLog("session res : ".$response);

    $res_decoded = json_decode($response, true);
    curl_close($ch);

    $res = Db::getInstance()->execute('
    INSERT INTO ' . _DB_PREFIX_ . 'tamara
      (order_id, checkout_id, checkout_url, status, id_cart, id_order) VALUES
      ("' . $res_decoded['order_id'] . '", "' . $res_decoded['checkout_id'] . '", "' . $res_decoded['checkout_url']. '", "' .$res_decoded['status']. '", ' .$this->context->cart->id. ', ' .$id_order. ')');

      if(isset($res_decoded['checkout_url'])){
        header("Location:" . $res_decoded['checkout_url']);
      }else{
        header("Location:index.php?controller=404");
      }
    header("Location:" . $res_decoded['checkout_url']);

    $this->setTemplate('module:tamaraprestashop/views/templates/front/payment_return.tpl');
  }
}
