<?php
// CREDENCIALES DROPEA
$dropea_email = 'dreamlove@dropea.com';
$dropea_password = 'C4mb14m3.,';

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

function deleteProductFromDropea($id, $token) {
    $url = "https://api.dropea.com/api/products/product/$id";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => "DELETE",
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $res = json_decode($response, true);
    if (isset($res['success']) && $res['success']) {
        echo "🗑️ Producto ID $id eliminado correctamente\n";
    } else {
        echo "⚠️ Error al eliminar ID $id\n";
        print_r($res);
    }
}

// === INICIO ===
$dropeaToken = loginToDropea($dropea_email, $dropea_password);
$page = 1;
$eliminados = 0;

while (true) {
    $url = "https://api.dropea.com/api/products/myproducts?page=$page&limit=100&showWithoutStock=0";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $dropeaToken"],
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $res = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($res, true);
    $products = $data['products'] ?? [];
    if (empty($products)) break;

    echo "🔍 Página $page - Revisando " . count($products) . " productos...\n";

    foreach ($products as $product) {
        $title = strtolower(trim($product['title'] ?? ''));
        if (strpos($title, 'black&silver') === 0) {
            $id = $product['id'];
            deleteProductFromDropea($id, $dropeaToken);
            $eliminados++;
        }
    }

    $page++;
}

echo "✅ Proceso completado. Total productos eliminados: $eliminados\n";
