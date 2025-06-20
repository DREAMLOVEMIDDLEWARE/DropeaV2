<?php
// CREDENCIALES
$gesio_username = 'B70885942';
$gesio_password = 'y1mAY3a2ydg1jYc';
$dropea_email = 'dreamlove@dropea.com';
$dropea_password = 'C4mb14m3.,';

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

function mapSKUsFromDropea($token) {
    echo "📥 Indexando productos Dropea...\n";
    $page = 1;
    $map = [];

    while (true) {
        $url = "https://api.dropea.com/api/products/myproducts?page=$page&limit=100&showWithoutStock=0";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($res, true);
        $products = $json['products'] ?? [];
        if (empty($products)) break;

        foreach ($products as $product) {
            $sku = trim($product['SKU'] ?? '');
            if ($sku) $map[$sku] = $product['id'];
        }

        echo "📦 Página $page: " . count($products) . " productos\n";
        $page++;
    }

    echo "✅ Total SKUs indexados en Dropea: " . count($map) . "\n";
    return $map;
}

function updateProductInDropea($dropeaId, $data, $token) {
    $sku = trim($data["sku"] ?? $data["referencia"] ?? '');
    $price = isset($data["price"]) ? floatval($data["price"]) : 0;
    $stock = isset($data["stock"]) ? intval($data["stock"]) : 0;
    $cost = isset($data["customerPrice"]) ? floatval($data["customerPrice"]) : 0;

    if (empty($sku)) {
        echo "❌ SKU vacío, se omite producto\n";
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
        echo "✅ Producto actualizado correctamente\n\n";
    } else {
        echo "❌ Error al actualizar $sku:\n";
        print_r($json);
    }
}

// === EJECUCIÓN MASIVA ===
$gesioToken = loginToGesio($gesio_username, $gesio_password);
$dropeaToken = loginToDropea($dropea_email, $dropea_password);
$skuMap = mapSKUsFromDropea($dropeaToken);

$page = 1;
$limit = 1000;
$procesados = 0;

while (true) {
    $url = "https://api-dreamlove.gesio.be/products?page=$page&limit=$limit";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: $gesioToken", "Accept: application/json"],
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $res = curl_exec($ch);
    curl_close($ch);

    $products = json_decode($res, true);
    if (!is_array($products) || count($products) === 0) {
        echo "✅ Fin del listado Gesio\n";
        break;
    }

    echo "📄 Página $page de Gesio: " . count($products) . " productos\n";

    foreach ($products as $p) {
        $sku = trim($p['sku'] ?? $p['referencia'] ?? '');
        if (!$sku || !isset($skuMap[$sku])) continue;

        $full = getFullProductFromGesio($p["id"], $gesioToken);
        if (!$full) continue;

        updateProductInDropea($skuMap[$sku], $full, $dropeaToken);
        $procesados++;
    }

    $page++;
}
// Ejemplo: suponer que ya has contado cuántos productos se han actualizado
$fecha = date("Y-m-d H:i:s");
$productos_actualizados = ($productosActualizados); // o el valor correspondiente

$mensaje_log = "[$fecha] Sincronización completada. Productos actualizados: $productos_actualizados" . PHP_EOL;
file_put_contents(__DIR__ . '/log_sync.txt', $mensaje_log, FILE_APPEND);

echo "🎯 Proceso completado. Total productos procesados: $procesados\n";
