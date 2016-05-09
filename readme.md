## Introduction

This library provides API for the simplest possible online payment via PayU
where automatic payment confirmation is enabled.

## Installation

```sh
$ composer require lightools/payu
```

## Simple usage

```php
// 1. redirect to payment gate
$httpClient = new Bitbang\Http\Clients\CurlClient();
$xmlLoader = new Lightools\Xml\XmlLoader();
$payu = new Lightools\PayU\PayU($posId, $posAuthKey, $key1, $key2, $httpClient, $xmlLoader);
$payment = new Lightools\PayU\NewPayment(
    $orderId, // your order identification
    $priceAmount, // in crowns (not in hellers)
    PayU::CHANNEL_TEST,
    $paymentDescription,
    $clientFirstname,
    $clientSurname,
    $clientEmail
);

$ipAddress = $_SERVER['REMOTE_ADDR'];
$redirectUrl = $payu->getRedirectUrl($payment, $ipAddress);

header("Location: $redirectUrl");
exit();

// 2. accept payment update (on "UrlOnline")
try {
    $post = filter_input_array(INPUT_POST);
    $status = $payu->getPaymentStatus($post);

    $status->getOrderId();
    $status->getStatus(); // e.g. PaymentStatus::STATUS_PAID

    echo 'OK';
    exit();

} catch (InvalidRequestException $ex) {
    // invalid request received (e.g. some data missing)

} catch (InvalidSignatureException $ex) {
    // invalid signature in request or response

} catch (RequestFailedException $ex) {
    // HTTP request to PayU failed
}
```

## How to run tests

```sh
$ vendor/bin/tester tests
```

