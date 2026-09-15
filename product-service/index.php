<?php
/**
 * PRODUCT SERVICE (PHP / XAMPP version)
 * ----------------------------------------
 * Job: only knows about products. Nothing about orders or payments.
 * Prices are stored in CENTAVOS (PHP peso x 100) because that's the
 * unit PayMongo expects later - we keep it consistent everywhere.
 *
 * Put this folder in: C:\xampp\htdocs\product-service\
 * Test in browser:
 *   http://localhost/product-service/index.php?action=list
 *   http://localhost/product-service/index.php?action=get&id=P001
 */

header('Content-Type: application/json');

// Fake "database" - just an array, good enough for this activity.
$products = [
    'P001' => [
        'id' => 'P001',
        'name' => 'SIA1 T-Shirt',
        'price' => 35000, // = PHP 350.00
        'description' => 'Official SIA1 class shirt',
    ],
    'P002' => [
        'id' => 'P002',
        'name' => 'SIA1 Tote Bag',
        'price' => 15000, // = PHP 150.00
        'description' => 'Canvas tote with class logo',
    ],
    'P003' => [
        'id' => 'P003',
        'name' => 'Short',
        'price' => 39900, // = PHP 399.00
        'description' => 'Casual short',
    ],
    'P004' => [
        'id' => 'P004',
        'name' => 'Jacket',
        'price' => 109900, // = PHP 1,099.00
        'description' => 'Everyday jacket',
    ],
    'P005' => [
        'id' => 'P005',
        'name' => 'Longsleeve',
        'price' => 89900, // = PHP 899.00
        'description' => 'Long sleeve shirt',
    ],
    'P006' => [
        'id' => 'P006',
        'name' => 'Shoes',
        'price' => 255000, // = PHP 2,550.00
        'description' => 'Everyday shoes',
    ],
    'P007' => [
        'id' => 'P007',
        'name' => 'Sandal',
        'price' => 199900, // = PHP 1,999.00
        'description' => 'Comfortable sandal',
    ],
    'P008' => [
        'id' => 'P008',
        'name' => 'Dress',
        'price' => 74900, // = PHP 749.00
        'description' => 'Casual dress',
    ],
];

$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    // ?action=list -> return every product
    echo json_encode(array_values($products));
    exit;
}

if ($action === 'get') {
    // ?action=get&id=P001 -> return one product
    $id = $_GET['id'] ?? '';
    if (!isset($products[$id])) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    echo json_encode($products[$id]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action']);