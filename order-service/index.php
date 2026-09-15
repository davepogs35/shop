<?php
/**
 * ORDER SERVICE (PHP / XAMPP version)
 * ----------------------------------------
 * Job: create orders from products, and track their status
 * (pending -> paid / cancelled).
 *
 * It does NOT talk to PayMongo directly. It asks the PAYMENT SERVICE
 * to start a checkout instead. This keeps "what is an order" separate
 * from "how do we get paid".
 *
 * Orders are saved to data/orders.json since PHP doesn't keep
 * variables in memory between requests like Node.js does.
 *
 * Put this folder in: C:\xampp\htdocs\order-service\
 *
 * Endpoints (all via index.php):
 *   POST ?action=create        body: {"productId": "P001", "quantity": 1}
 *   GET  ?action=get&id=ORD-1001
 *   POST ?action=updateStatus  body: {"id": "ORD-1001", "status": "paid"}
 */

header('Content-Type: application/json');

// Change these if your services live somewhere other than localhost.
$PRODUCT_SERVICE_URL = 'http://localhost/product-service/index.php';
$PAYMENT_SERVICE_URL = 'http://localhost/payment-service/index.php';
$ORDERS_FILE = __DIR__ . '/data/orders.json';

// ---- tiny helper functions -------------------------------------------

function loadOrders($file) {
    if (!file_exists($file)) return [];
    $content = file_get_contents($file);
    return $content ? json_decode($content, true) : [];
}

function saveOrders($file, $orders) {
    file_put_contents($file, json_encode($orders, JSON_PRETTY_PRINT));
}

// Simple POST-JSON helper using cURL, so we can call the other services.
function postJson($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function getJson($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// ---- routing ------------------------------------------------------------

$action = $_GET['action'] ?? '';
$body = json_decode(file_get_contents('php://input'), true) ?? [];

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = $body['productId'] ?? '';
    $quantity = $body['quantity'] ?? 1;

    // Step 1: get product details from the Product Service
    $product = getJson("$PRODUCT_SERVICE_URL?action=get&id=" . urlencode($productId));
    if (!$product || isset($product['error'])) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit;
    }

    // Step 2: create the order record
    $orders = loadOrders($ORDERS_FILE);
    $orderId = 'ORD-' . (1001 + count($orders));
    $order = [
        'id' => $orderId,
        'productId' => $product['id'],
        'productName' => $product['name'],
        'quantity' => $quantity,
        'amount' => $product['price'] * $quantity, // still in centavos
        'status' => 'pending',
    ];
    $orders[$orderId] = $order;
    saveOrders($ORDERS_FILE, $orders);

    // Step 3: ask Payment Service to create a PayMongo checkout session
    $paymentData = postJson("$PAYMENT_SERVICE_URL?action=checkout", [
        'orderId' => $order['id'],
        'itemName' => $product['name'],
        'description' => $product['description'],
        'amount' => $order['amount'],
        'quantity' => $quantity,
    ]);

    // Step 4: return the checkout URL so the customer can pay
    echo json_encode([
        'order' => $order,
        'checkout_url' => $paymentData['checkout_url'] ?? null,
        'payment_error' => $paymentData['error'] ?? null,
    ]);
    exit;
}

if ($action === 'get') {
    $id = $_GET['id'] ?? '';
    $orders = loadOrders($ORDERS_FILE);
    if (!isset($orders[$id])) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit;
    }
    echo json_encode($orders[$id]);
    exit;
}

if ($action === 'updateStatus' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $body['id'] ?? '';
    $status = $body['status'] ?? '';
    $orders = loadOrders($ORDERS_FILE);
    if (!isset($orders[$id])) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit;
    }
    $orders[$id]['status'] = $status;
    saveOrders($ORDERS_FILE, $orders);
    echo json_encode($orders[$id]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action or wrong method']);
