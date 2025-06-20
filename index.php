<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestor Dropea - Dreamlove</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 40px;
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
            color: #333;
        }

        .container {
            max-width: 720px;
            margin: auto;
            background: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #222;
        }

        .button-container {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }

        button {
            background-color: #0066ff;
            border: none;
            color: white;
            padding: 14px 22px;
            font-size: 15px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #004fd1;
        }

        #log {
            background-color: #0e1117;
            color: #00FF88;
            padding: 20px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            height: 400px;
            overflow-y: auto;
            border-radius: 8px;
            border: 1px solid #2d2d2d;
            white-space: pre-wrap;
        }

        .error-line {
            color: #ff4f4f;
        }
    </style>
</head>
<body>

    <div class="container">
        <h1>Gestor Dropea - Dreamlove</h1>

        <div class="button-container">
            <form method="post">
                <button name="action" value="subir">🚀 Subir productos</button>
                <button name="action" value="actualizar">🔄 Actualizar productos</button>
                <button name="action" value="borrar">🗑️ Borrar productos</button>
            </form>
        </div>

        <div id="log">
            <?php
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                ini_set('output_buffering', 'off');
                ini_set('zlib.output_compression', false);
                ob_implicit_flush(true);
                while (ob_get_level() > 0) ob_end_flush();

                $action = $_POST["action"];
                $archivo = "";

                switch ($action) {
                    case "subir":
                        echo "🚀 Ejecutando subirProductos.php...\n\n";
                        $archivo = "subirProductos.php";
                        break;
                    case "actualizar":
                        echo "🔄 Ejecutando actualizarTodosProductos.php...\n\n";
                        $archivo = "actualizarTodosProductos.php";
                        break;
                    case "borrar":
                        echo "🗑️ Ejecutando borrarProductos.php...\n\n";
                        $archivo = "borrarProductos.php";
                        break;
                }

                if ($archivo !== "") {
                    $descriptores = [
                        1 => ["pipe", "w"],
                        2 => ["pipe", "w"]
                    ];

                    $process = proc_open("php $archivo", $descriptores, $pipes);

                    if (is_resource($process)) {
                        $status = proc_get_status($process);
                        file_put_contents("pid.txt", $status['pid']);
                        file_put_contents("ejecutando.txt", $archivo);

                        while (!feof($pipes[1])) {
                            $line = fgets($pipes[1]);
                            if ($line !== false) {
                                echo htmlspecialchars($line) . "<br>";
                                @ob_flush();
                                @flush();
                            }
                        }
                        fclose($pipes[1]);

                        while (!feof($pipes[2])) {
                            $line = fgets($pipes[2]);
                            if ($line !== false) {
                                echo "<span class='error-line'>" . htmlspecialchars($line) . "</span><br>";
                                @ob_flush();
                                @flush();
                            }
                        }
                        fclose($pipes[2]);

                        proc_close($process);
                    } else {
                        echo "❌ No se pudo iniciar el proceso.";
                    }
                }
            }
            ?>
        </div>
    </div>

</body>
</html>
