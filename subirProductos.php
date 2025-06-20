<?php
// DATOS DE ACCESO
$gesio_username = 'B70885942';
$gesio_password = 'y1mAY3a2ydg1jYc';
$dropea_email = 'dreamlove@dropea.com';
$dropea_password = 'C4mb14m3.,';
$dropea_create_url = 'https://api.dropea.com/api/products/product';

ini_set('max_execution_time', 0);
ini_set('memory_limit', '1024M');

// LOGIN
function loginToGesio($username, $password) {
    $data = json_encode(['username' => $username, 'password' => $password]);
    $ch = curl_init('https://api-dreamlove.gesio.be/login_check');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POSTFIELDS => $data, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POST => true, CURLOPT_SSL_VERIFYPEER => false]);
    $response = curl_exec($ch); curl_close($ch);
    $res = json_decode($response, true);
    if (!isset($res['token'])) die("❌ No se recibió token de Gesio\n");
    echo "🔐 Token Gesio recibido correctamente\n";
    return "Bearer " . $res['token'];
}

function loginToDropea($email, $password) {
    $data = json_encode(['email' => $email, 'password' => $password]);
    $ch = curl_init('https://api.dropea.com/api/login');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POSTFIELDS => $data, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POST => true, CURLOPT_SSL_VERIFYPEER => false]);
    $response = curl_exec($ch); curl_close($ch);
    $res = json_decode($response, true);
    if (!isset($res['authToken'])) die("❌ No se recibió authToken\n");
    echo "🔐 Token Dropea recibido correctamente\n";
    return $res['authToken'];
}

// OBTENER TODOS LOS SKUs EN DROPEA
function getAllSkusFromDropea($token) {
    $page = 1;
    $limit = 100;
    $skuMap = [];

    while (true) {
        $url = "https://api.dropea.com/api/products/myproducts?page=$page&limit=$limit&sort=id&direction=asc";
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"], CURLOPT_SSL_VERIFYPEER => false]);
        $response = curl_exec($ch); curl_close($ch);
        $res = json_decode($response, true);

        if (!$res['success'] || empty($res['products'])) break;

        foreach ($res['products'] as $prod) {
            if (!empty($prod['SKU'])) {
                $skuMap[$prod['SKU']] = true;
            }
        }

        echo "📦 Página $page Dropea: " . count($res['products']) . " productos\n";
        $page++;
    }

    echo "✅ Total productos Dropea indexados: " . count($skuMap) . "\n";
    return $skuMap;
}

// GET FULL PRODUCT
function getFullProductFromGesio($id, $token) {
    $url = "https://api-dreamlove.gesio.be/products/$id";
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ["Authorization: $token", "Accept: application/json"], CURLOPT_SSL_VERIFYPEER => false]);
    $response = curl_exec($ch); curl_close($ch);
    return json_decode($response, true);
}

// SUBIR IMÁGENES
function uploadImageToDropea($fileUrl, $token) {
    $tmpFile = tempnam(sys_get_temp_dir(), 'img_');
    file_put_contents($tmpFile, file_get_contents($fileUrl));
    $cfile = new CURLFile($tmpFile, mime_content_type($tmpFile), basename($fileUrl));

    $ch = curl_init('https://api.dropea.com/api/products/upload');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POSTFIELDS => ['file' => $cfile], CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"], CURLOPT_SSL_VERIFYPEER => false]);

    $response = curl_exec($ch);
    curl_close($ch);
    unlink($tmpFile);

    return json_decode($response, true);
}

// IMÁGENES
function extractImageUrls($product, $tokenDropea) {
    $images = [];
    if (!empty($product['images'])) {
        $order = 0;
        foreach ($product['images'] as $img) {
            foreach ($img['image']['files'] ?? [] as $file) {
                if (!empty($file['url']) && filter_var($file['url'], FILTER_VALIDATE_URL)) {
                    $uploadRes = uploadImageToDropea($file['url'], $tokenDropea);
                    if ($uploadRes['success'] ?? false) {
                        $images[] = [
                            'url' => $uploadRes['file']['url'] ?? '',
                            'name' => $uploadRes['file']['name'] ?? '',
                            'order' => $order++
                        ];
                    }
                }
            }
        }
    }
    return $images;
}

// CREAR PRODUCTO EN DROPEA
function createProductOnDropea($product, $token) {
    $sku = $product["sku"] ?? $product["referencia"] ?? "";
    if (!$sku) {
        echo "⚠️ Producto sin SKU, omitido\n";
        return false;
    }

    echo "\n==============================\n";
    echo "📦 Subiendo producto SKU: $sku\n";

    $price = floatval($product["customerPrice"] ?? $product["price"] ?? 0);
    $cost = floatval($product["customerPrice"] ?? 0);

    $payload = [
        "title" => $product["name"] ?? "Sin nombre",
        "name" => $product["name"] ?? "Sin nombre",
        "description" => $product["description"] ?? "",
        "SKU" => $sku,
        "price" => $price,
        "cost_price" => $cost,
        "manufacture_price" => $cost,
        "fulfillment_cost" => 0,
        "fullfill" => false,
        "stock" => intval($product["stock"] ?? 0),
        "product_category_id" => 50,
        "product_state_id" => 1,
        "state" => "activo",
        "files" => extractImageUrls($product, $token),
        "variants" => []
    ];

    $ch = curl_init($GLOBALS['dropea_create_url']);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_HTTPHEADER => ['Content-Type: application/json', "Authorization: Bearer $token"], CURLOPT_POST => true, CURLOPT_SSL_VERIFYPEER => false]);
    $response = curl_exec($ch); curl_close($ch);
    $res = json_decode($response, true);

    if ($res['success'] ?? false) {
        echo "✅ Producto subido: $sku\n";
        return true;
    } else {
        echo "❌ Error subiendo $sku\n";
        print_r($res);
        return false;
    }
}

// FUNCIÓN PRINCIPAL
function syncGesioToDropea($tokenGesio, $tokenDropea) {
    $limit = 100;
    $page = 23;
    $lastPage = 43;
    $count = 0;
    $skuMap = getAllSkusFromDropea($tokenDropea);

    while ($page <= $lastPage) {
        $url = "https://api-dreamlove.gesio.be/products?page=$page&limit=$limit";
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ["Authorization: $tokenGesio", "Accept: application/json"], CURLOPT_SSL_VERIFYPEER => false]);
        $response = curl_exec($ch); curl_close($ch);

        $products = json_decode($response, true);
        if (!is_array($products) || count($products) === 0) {
            echo "⛔ Página $page vacía. Siguiente...\n";
            $page++;
            continue;
        }

        echo "📄 Página $page Gesio: " . count($products) . " productos\n";

        foreach ($products as $product) {
            $full = getFullProductFromGesio($product["id"], $tokenGesio);
            if (!$full) continue;

            $tags = $full['tags'] ?? [];
            if (!in_array('/tags/13', $tags)) continue;

            $sku = $full["sku"] ?? $full["referencia"] ?? "";
            if (!$sku || isset($skuMap[$sku])) {
                echo "⏭️ Producto $sku ya subido. Saltando.\n";
                continue;
            }

            if (createProductOnDropea($full, $tokenDropea)) {
                $count++;
                $skuMap[$sku] = true;
            }
        }

        $page++;
    }

    echo "\n🎯 Proceso terminado. Total nuevos productos subidos: $count\n";
}


// EJECUCIÓN
$gesioToken = loginToGesio($gesio_username, $gesio_password);
$dropeaToken = loginToDropea($dropea_email, $dropea_password);
syncGesioToDropea($gesioToken, $dropeaToken);
?>
