<?php

require_once 'modules/billing/models/class.gateway.plugin.php';

class PluginBitpay extends GatewayPlugin
{
    public function getVariables()
    {
        $variables = array (
            lang("Plugin Name") => array (
                "type"          => "hidden",
                "description"   => lang("How CE sees this plugin (not to be confused with the Signup Name)"),
                "value"         => lang("BitPay")
            ),
            lang("Legacy API Key") => array (
                "type"          => "text",
                "description"   => lang("Enter your Legacy API Key from your bitpay.com merchant account"),
                "value"         => ""
            ),
            lang("Transaction Speed") => array (
                "type"          => "options",
                "description"   => lang("Select the transaction speed to confirm payment"),
                "options"       => [
                    'low'    => lang('Low'),
                    'medium' => lang('Medium'),
                    'high'   => lang('High')
                ]
            ),
            lang("Use Testing Environment?") => array(
                "type"          => "yesno",
                "description"   => lang("Select YES if you wish to use the testing environment instead of the live environment"),
                "value"         => "0"
            ),
            lang("Signup Name") => array (
                "type"          => "text",
                "description"   => lang("Select the name to display in the signup process for this payment type. Example: eCheck or Credit Card."),
                "value"         => "Bitcoin (BTC)"
            )

        );
        return $variables;
    }

    public function credit($params)
    {
    }

    public function singlepayment($params, $test = false)
    {
        $data = [];
        $data['price'] = $params['invoiceTotal'];
        $data['currency'] = $params['userCurrency'];
        $data['orderId'] = $params['invoiceNumber'];
        $data['itemDesc'] = $params['invoiceDescription'];
        $data['notificationURL'] = $params['clientExecURL'] . '/plugins/gateways/bitpay/callback.php';
        $data['redirectURL'] = $params['invoiceviewURLSuccess'];
        $data['transactionSpeed'] = $params['plugin_bitpay_Transaction Speed'];
        $data['fullNotifications'] = true;
        $data['buyerName'] = $params['userFirstName'] . ' ' . $params['userLastName'];
        $data['buyerAddress1'] = $params['userAddress'];
        $data['buyerCity'] = $params['userCity'];
        $data['buyerState'] = $params['userState'];
        $data['buyerZip'] = $params['userZipcode'];
        $data['buyerEmail'] = $params['userEmail'];
        $data['buyerPhone'] = $params['userPhone'];
        $data['posData'] = $params['invoiceNumber'];

        CE_Lib::log(4, 'BitPay Params: ' . print_r($data, true));
        $data = json_encode($data);
        $return = $this->makeRequest($params, $data, true);

        if (isset($return['error'])) {
            $cPlugin = new Plugin($params['invoiceNumber'], "bitpay", $this->user);
            $cPlugin->setAmount($params['invoiceTotal']);
            $cPlugin->setAction('charge');
            $cPlugin->PaymentRejected($this->user->lang("There was an error performing this operation.") . ' ' . $return['error']['message']);
            return $this->user->lang("There was an error performing this operation.") . ' ' . $return['error']['message'];
        }

        header('Location: ' . $return['url']);
        exit;
    }

    private function makeRequest($params, $data, $post = false)
    {
        $url = 'https://bitpay.com/api/invoice/';
        if ($params['plugin_bitpay_Use Testing Environment?'] == '1') {
            $url = 'https://test.bitpay.com/api/invoice/';
        }

        CE_Lib::log(4, 'Making request to: ' . $url);
        $ch = curl_init($url);
        if ($post === true) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $header = [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($params['plugin_bitpay_Legacy API Key']),
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($ch);
        if (!$response) {
            throw new CE_Exception('cURL BitPay Error: ' . curl_error($ch) . '( ' .curl_errno($ch) . ')');
        }
        curl_close($ch);
        $response = json_decode($response, true);
        CE_Lib::log(4, 'BitPay Response: ' . print_r($response, true));

        return $response;
    }
}