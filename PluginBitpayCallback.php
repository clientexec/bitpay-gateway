<?php
require_once 'modules/admin/models/PluginCallback.php';
require_once 'modules/admin/models/StatusAliasGateway.php' ;
require_once 'modules/billing/models/class.gateway.plugin.php';
require_once 'modules/billing/models/Invoice_EventLog.php';
require_once 'modules/admin/models/Error_EventLog.php';

class PluginBitpayCallback extends PluginCallback
{

    function processCallback()
    {
        $data = file_get_contents("php://input");
        $json = json_decode($data, true);
        CE_Lib::log(4, "Callback: ". print_r($json, true));
        $bpInvoiceId = $json['id'];
        $invoiceData = $this->getInvoice($bpInvoiceId);

        $cPlugin = new Plugin($invoiceData['posData'], "bitpay", $this->user);
        $cPlugin->setAmount($invoiceData['price']);
        $cPlugin->setAction('charge');

        switch ($invoiceData['status']) {
            case 'paid':
                $transaction = "BitPay payment of {$invoiceData['price']} has been received.";
                $cPlugin->PaymentPending($transaction, $bpInvoiceId);
                break;

            case 'confirmed':
                $transaction = "BitPay payment of {$invoiceData['price']} has been confirmed.";
                $cPlugin->PaymentPending($transaction, $bpInvoiceId);
                break;

            case 'complete':
                $transaction = "BitPay payment of {$invoiceData['price']} has been completed.";
                $cPlugin->PaymentAccepted($invoiceData['price'], $transaction, $bpInvoiceId);
                break;

            case 'expired':
            case 'invalid':
                $transaction = 'Invalid Transaction';
                $cPlugin->PaymentRejected($transaction);
                break;
        }
    }

    private function getInvoice($invoiceId)
    {
        $url = 'https://bitpay.com/api/invoice/';
        if ($this->settings->get('plugin_bitpay_Use Testing Environment?') == '1') {
            $url = 'https://test.bitpay.com/api/invoice/';
        }

        $url .= $invoiceId;

        $ch = curl_init($url);

        $header = [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($this->settings->get('plugin_bitpay_Legacy API Key')),
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

        return $response;

    }
}