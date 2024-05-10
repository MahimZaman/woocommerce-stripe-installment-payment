<?php

# vendor using composer
require_once( __DIR__ . '/../vendor/autoload.php' );


header('Content-Type: application/json');

# retrieve json from POST body
$json_str = file_get_contents('php://input');
$json_obj = json_decode($json_str, true);
\Stripe\Stripe::setApiKey($json_obj['sKey']);


if (isset($json_obj['selected_plan'])) {
    $confirm_data = [
        'payment_method_options' =>
        [
            'card' => [
                'installments' => [
                    'plan' => $json_obj['selected_plan']
                ]
            ]
                ],
        'return_url' => $json_obj['redirect']
    ];
}

$intent = \Stripe\PaymentIntent::retrieve(
    $json_obj['payment_intent_id']
);

$intent->confirm($params = $confirm_data);

echo json_encode([
    'status' => $intent->status,
]);
