<?php
$logPath = __DIR__ . '/log_sync.txt';
$contenido = file_exists($logPath) ? file_get_contents($logPath) : 'No hay registros disponibles.';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Logs de Sincronización</title>
    <style>
        body { font-family: Arial; background: #f4f4f4; padding: 20px; }
        pre { background: #fff; padding: 15px; border: 1px solid #ccc; white-space: pre-wrap; }
    </style>
</head>
<body>
    <h2>Logs de Sincronización Automática</h2>
    <pre><?= htmlspecialchars($contenido) ?></pre>
</body>
</html>
