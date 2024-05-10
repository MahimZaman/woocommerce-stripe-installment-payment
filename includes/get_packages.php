<?php

try{
    require_once( __DIR__ . '/../vendor/autoload.php' );

    header( 'Content-Type: application/json' );

    $json_str = file_get_contents('php://input');
    $json_obj = json_decode($json_str);

    \Stripe\Stripe::setApiKey($json_obj->sKey);


    $intent = \Stripe\PaymentIntent::create([
        'payment_method' => $json_obj->payment_method_id, 
        'amount' => $json_obj->amount,
        'currency' => 'MXN',
        'payment_method_options' => [
            'card' => [
                'installments' => [
                    'enabled' => true
                ]
            ]
        ]
    ]);

    echo json_encode([
        'intent_id' => $intent->id,
        'intent' => $intent, 
        'available_plans' => $intent->payment_method_options->card->installments->available_plans
    ]);

}catch(\Stripe\Exception\CardException $e){
    echo 'Card Error Message is: ' . $e->getError()->message . "\n";
    echo json_encode([
        'error_message' => $e->getError()->message
    ]);
}catch(\Stripe\Exception\InvalidRequestException $e){
    echo 'Invalid Parameters Message is: ' . $e->getError()->message . "\n";
    echo json_encode([
        'error_message' => $e->getError()->message 
    ]);
}
