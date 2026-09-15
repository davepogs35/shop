<?php
/**
 * PAYMENT SERVICE (PHP / XAMPP version)
 * ----------------------------------------
 * Job: the ONLY service that ever talks to PayMongo.
 * The secret key lives only in config.php, which lives only here.
 *
 * Flow:
 * 1. Order Service sends us order details (name, amount, etc.)
 * 2. We call PayMongo's Checkout API to create a "checkout session"
 * 3. PayMongo gives us back a checkout_url (their own hosted payment page)
 * 4. We hand that URL back to the Order Service
 *
 * Put this folder in: C:\xampp\htdocs\payment-service\
 *
 * Endpoint:
 *   POST ?action=checkout
 *   body: {"orderId": "ORD-1001", "itemName": "...", "description": "...",
 *          "amount": 35000, "quantity": 1}
 */

header('Content-Type: application/json');
require __DIR__ . '/config.php';

// Where PayMongo sends the customer back to after paying.
// In a real deployment these would be real pages on your site.
$BASE_URL = 'https://yourapp.example';

$action = $_GET['action'] ?? '';
$body = json_decode(file_get_contents('php://input'), true) ?? [];

if ($action !== 'checkout' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown action or wrong method']);
    exit;
}

if (PAYMONGO_SECRET_KEY === 'sk_test_your_key_here') {
    http_response_code(500);
    echo json_encode(['error' => 'Set your real sandbox key in config.php first']);
    exit;
}

$orderId = $body['orderId'] ?? '';
$itemName = $body['itemName'] ?? '';
$description = $body['description'] ?? '';
$amount = $body['amount'] ?? 0;      // already in centavos - do not multiply again
$quantity = $body['quantity'] ?? 1;

$payload = [
    'data' => [
        'attributes' => [
            'line_items' => [
                [
                    'name' => $itemName,
                    'quantity' => $quantity,
                    'amount' => $amount,
                    'currency' => 'PHP',
                    'description' => $description,
                ],
            ],
            'payment_method_types' => ['card', 'gcash', 'paymaya'],
            'description' => "Order #$orderId",
            'send_email_receipt' => false,
            'show_line_items' => true,
            'success_url' => "$BASE_URL/order/$orderId/success",
            'cancel_url' => "$BASE_URL/order/$orderId/cancel",
        ],
    ],
];

// PayMongo wants the secret key sent as a Basic Auth username,
// Base64-encoded, with an empty password.
$authHeader = 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':');

$ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    "Authorization: $authHeader",
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not reach PayMongo', 'details' => $curlError]);
    exit;
}

$data = json_decode($response, true);

if ($httpCode >= 400) {
    http_response_code($httpCode);
    echo json_encode(['error' => 'PayMongo request failed', 'details' => $data]);
    exit;
}

// This is the link the customer gets redirected to for the actual
// card/GCash/Maya entry - fully hosted by PayMongo.
$checkoutUrl = $data['data']['attributes']['checkout_url'] ?? null;
echo json_encode(['checkout_url' => $checkoutUrl]);
