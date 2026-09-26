<?php
// reset-admin.php - Resetear credenciales de admin
// Uso web: visita /admin/reset-admin.php (NO requiere login)
// Uso CLI: php admin/reset-admin.php [usuario] [contraseña]

$baseDir = __DIR__;
$error = '';
$user = null;
$pass = null;
$success = false;

// --- 1. Pedir credenciales si no se dieron ---
if (PHP_SAPI === 'cli') {
    echo "Reset Admin Credentials\n";
    echo "======================\n\n";
    
    $user = $argv[1] ?? readline("Usuario admin: ");
    $pass = $argv[2] ?? readline("Contraseña admin: ");
    
    if (empty($user) || empty($pass)) {
        echo "\nError: Debes proporcionar usuario y contraseña.\n";
        echo "Uso: php reset-admin.php [usuario] [contraseña]\n";
        exit(1);
    }
} else {
    // Modo web: mostrar formulario si no hay POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user = trim($_POST['user'] ?? '');
        $pass = $_POST['password'] ?? '';
        
        if (empty($user) || empty($pass)) {
            $error = 'Usuario y contraseña obligatorios.';
        }
    } else {
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Reset Admin Credentials</title>
<style>
body { font-family: monospace, 'Courier New', Courier; background: #1a1a2e; color: #eee; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
form { background: #16213e; padding: 40px; border: 2px solid #0f3460; max-width: 400px; }
h1 { color: #e94560; margin-top: 0; font-size: 1.4em; }
label { display: block; margin-top: 20px; font-weight: bold; }
input { width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box; background: #0f3460; color: #eee; border: 1px solid #533483; }
button { margin-top: 25px; width: 100%; padding: 12px; background: #e94560; color: white; border: none; cursor: pointer; font-size: 1em; font-weight: bold; }
button:hover { background: #c3304a; }
.err { color: #e94560; background: rgba(233,69,96,0.1); padding: 10px; border-left: 3px solid #e94560; margin-bottom: 15px; }
</style>
</head>
<body>
<form method="POST">
    <h1>RESETEAR LOGIN ADMIN</h1>
    <p>Ingresa nuevas credenciales:</p>
    <label for="user">Usuario:</label>
    <input type="text" id="user" name="user" required autofocus>
    <label for="password">Contraseña:</label>
    <input type="password" id="password" name="password" required>
    <button type="submit">GENERAR NUEVAS CREDENCIALES</button>
</form>
</body>
</html>
<?php
        exit;
    }
}

// --- 2. Si llegamos aquí con user+pass, ejecutar reset ---
if ($user && $pass) {
    try {
        // Generar hash bcrypt válido
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        
        if (!password_verify($pass, $hash)) {
            throw new RuntimeException('Error interno al generar hash. PHP ' . PHP_VERSION . ' no soporta bcrypt.');
        }
        
        // Directorio raíz del CMS (un nivel arriba de admin/)
        $cmsRoot = dirname($baseDir);
        
        // 1) Escribir .env
        $envPath = $cmsRoot . '/.env';
        $envContent = "# Credenciales admin - generadas " . date('c') . "\n";
        $envContent .= "# Archivo protegido con permisos 0600\n";
        $envContent .= "# NO compartir ni subir a repositorios públicos\n";
        $envContent .= "ADMIN_USER=" . addslashes($user) . "\n";
        $envContent .= "ADMIN_PASSWORD_HASH=" . addslashes($hash) . "\n";
        
        if (@file_put_contents($envPath, $envContent) === false) {
            throw new RuntimeException('No se pudo escribir ' . $envPath . '. Verifica permisos de escritura.');
        }
        @chmod($envPath, 0600);
        
        // Verificar que se pudo leer de vuelta (sanity check de escritura)
        if (@file_get_contents($envPath) === false) {
            throw new RuntimeException('Se escribió ' . $envPath . ' pero no se pudo verificar. El archivo puede estar vacío.');
        }
        
        // 2) Escribir admin_config.php como persistencia dual
        $configPath = $cmsRoot . '/admin_config.php';
        $configContent = "<?php\n";
        $configContent .= "// Credenciales admin - NO EDITAR manualmente\n";
        $configContent .= "// Generado: " . date('c') . "\n";
        $configContent .= "define('ADMIN_USER', '" . addslashes($user) . "');\n";
        $configContent .= "define('ADMIN_PASSWORD', '" . addslashes($hash) . "');\n";
        $configContent .= "?>\n";
        
        if (@file_put_contents($configPath, $configContent) === false) {
            throw new RuntimeException('No se pudo escribir ' . $configPath . '. Verifica permisos de escritura.');
        }
        @chmod($configPath, 0600);
        
        // Verificar lectura de admin_config.php también
        if (@file_get_contents($configPath) === false) {
            throw new RuntimeException('Se escribió ' . $configPath . ' pero no se pudo verificar.');
        }
        
        // 3) Marcar éxito
        $success = true;
        
    } catch (Exception $e) {
        $error = htmlspecialchars($e->getMessage());
    }
}

// --- 3. Mostrar resultado ---
if ($success) {
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Credenciales actualizadas</title>
<style>
body { font-family: monospace, 'Courier New', Courier; background: #1a1a2e; color: #eee; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
h1 { color: #27ae60; text-align: center; }
.result { background: #1e3a5f; border-left: 4px solid #27ae60; padding: 20px; max-width: 500px; }
.warning { color: #e67e22; font-size: 0.8em; margin-top: 15px; line-height: 1.5; }
a { color: #3498db; text-decoration: none; padding: 10px 20px; display: inline-block; border: 1px solid #3498db; margin-top: 15px; }
a:hover { background: #3498db; color: white; }
</style>
</head>
<body>
    <div style="text-align: center;">
        <h1>CREDENCIALES ACTUALIZADAS</h1>
        <div class="result">
            <p style="font-size: 1.1em;">Usuario: <strong><?php echo htmlspecialchars($user); ?></strong></p>
            <p style="font-size: 1.1em;">Contraseña: <strong><?php echo htmlspecialchars($pass); ?></strong></p>
        </div>
        <p class="warning"><strong>Importante:</strong><br>
        • Las credenciales se escribieron en .env y admin_config.php<br>
        • El archivo reset-admin.php debe eliminarse después de usarlo por seguridad<br>
        • Guardá esta contraseña en un lugar seguro</p>
        <a href="login.php">Ir al login &rarr;</a>
    </div>
</body>
</html>
<?php
} elseif ($error) {
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Error</title>
<style>
body { font-family: monospace, 'Courier New', Courier; background: #1a1a2e; color: #eee; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
h1 { color: #c0392b; text-align: center; }
.err { background: rgba(192,57,43,0.2); border-left: 4px solid #c0392b; padding: 20px; max-width: 500px; margin-bottom: 15px; }
a { color: #3498db; text-decoration: none; }
</style>
</head>
<body>
    <div style="text-align: center;">
        <h1>ERROR</h1>
        <p class="err"><?php echo $error; ?></p>
        <br>
        <a href="?">Intentar de nuevo &larr;</a>
    </div>
</body>
</html>
<?php
}
