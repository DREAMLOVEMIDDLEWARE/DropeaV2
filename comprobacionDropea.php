<?php

// CREDENCIALES
$dropea_email = 'dreamlove@dropea.com';
$dropea_password = 'C4mb14m3.,';

// LOGIN
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
    if (!isset($res['authToken'])) {
        die("❌ No se recibió authToken\n");
    }
    echo "🔐 Token Dropea recibido correctamente\n";
    return $res['authToken'];
}

// OBTENER PRODUCTOS
function obtenerProductos($token, $page = 1, $limit = 100) {
    $url = "https://api.dropea.com/api/products/myproducts?page=$page&limit=$limit&sort=id&direction=asc&showWithoutStock=0";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            "Accept: application/json"
        ],
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// === EJECUCIÓN ===
$token = loginToDropea($dropea_email, $dropea_password);
$page = 1;
$limit = 100;
$total = 0;

echo "📦 Recorriendo productos...\n";

while (true) {
    $resultado = obtenerProductos($token, $page, $limit);
    $productos = $resultado['products'] ?? [];

    if (empty($productos)) {
        echo "✅ No hay más productos en la página $page.\n";
        break;
    }

    foreach ($productos as $producto) {
        $id = $producto['id'] ?? 'N/A';
        $sku = $producto['SKU'] ?? ($producto['sku'] ?? 'SIN SKU');
        echo "🆔 ID: $id | 🔖 SKU: $sku\n";
        $total++;
    }

    $page++;
}

echo "\n✅ Total de productos listados: $total\n";
