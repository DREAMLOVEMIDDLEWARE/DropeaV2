<?php
// CREDENCIALES
$gesio_username = 'B70885942';
$gesio_password = 'y1mAY3a2ydg1jYc';
$dropea_email = 'dreamlove@dropea.com';
$dropea_password = 'C4mb14m3.,';

// LOGIN GESIO
function loginToGesio($username, $password) {
    $data = json_encode(['username' => $username, 'password' => $password]);
    $ch = curl_init('https://api-dreamlove.gesio.be/login_check');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POST => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $res = json_decode($response, true);
    if (!isset($res['token'])) die("❌ No se recibió token Gesio\n");
    return "Bearer " . $res['token'];
}

// LOGIN DROPEA
function loginToDropea($email, $password) {
    $data = json_encode(['email' => $email, 'password' => $password]);
    $ch = curl_init('https://api.dropea.com/api/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POST => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $res = json_decode($response, true);
    if (!isset($res['authToken'])) die("❌ No se recibió token Dropea\n");
    return $res['authToken'];
}

// OBTENER PRODUCTO COMPLETO DE GESIO
function getFullProductFromGesio($id, $token) {
    $url = "https://api-dreamlove.gesio.be/products/$id";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: $token", "Accept: application/json"],
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// BUSCAR ID del producto en Dropea por SKU
function getProductIdFromDropeaBySku($sku, $token) {
    $page = 1;
    while (true) {
        $url = "https://api.dropea.com/api/products/myproducts?page=$page&limit=100&showWithoutStock=0";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $res = json_decode($response, true);
        $products = $res['products'] ?? [];
        if (empty($products)) break;
        foreach ($products as $product) {
            if (trim($product['SKU']) === trim($sku)) {
                return $product['id'];
            }
        }
        $page++;
    }
    return null;
}

// ACTUALIZAR EN DROPEA
function updateProduct($dropeaId, $data, $token) {
    $sku = trim($data["sku"] ?? $data["referencia"] ?? '');
    $price = isset($data["price"]) ? floatval($data["price"]) : 0;
    $stock = isset($data["stock"]) ? intval($data["stock"]) : 0;
    $cost = isset($data["customerPrice"]) ? floatval($data["customerPrice"]) : 0;

    if (empty($sku)) {
        echo "❌ SKU vacío\n";
        return;
    }

    echo "🔁 Actualizando SKU $sku | Precio: $price | Coste: $cost | Stock: $stock\n";

    $urlGet = "https://api.dropea.com/api/products/product/$dropeaId";
    $ch = curl_init($urlGet);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $existing = json_decode(curl_exec($ch), true);
    curl_close($ch);

    $files = $existing['product']['files'] ?? [];

    $payload = [
        "title" => $data["name"] ?? "Sin nombre",
        "description" => $data["description"] ?? "",
        "SKU" => $sku,
        "manufacture_price" => $cost,
        "price" => $price,
        "cost_price" => $cost,
        "fulfillment_cost" => 0,
        "fullfill" => false,
        "stock" => $stock,
        "product_category_id" => 50,
        "product_state_id" => 1,
        "files" => $files
    ];

    $ch = curl_init("https://api.dropea.com/api/products/product/$dropeaId");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "PUT",
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ],
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $res = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($res, true);
    if (isset($json['success']) && $json['success']) {
        echo "✅ Producto actualizado correctamente\n";
    } else {
        echo "❌ Error al actualizar:\n";
        print_r($json);
    }
}

// === EJECUCIÓN ===
$gesioToken = loginToGesio($gesio_username, $gesio_password);
$dropeaToken = loginToDropea($dropea_email, $dropea_password);

$gesioProduct = getFullProductFromGesio(39632, $gesioToken);
if (!$gesioProduct) die("❌ No se pudo obtener el producto de Gesio\n");

$dropeaId = getProductIdFromDropeaBySku($gesioProduct["sku"], $dropeaToken);
if (!$dropeaId) die("❌ Producto con SKU {$gesioProduct["sku"]} no encontrado en Dropea\n");

updateProduct($dropeaId, $gesioProduct, $dropeaToken);
