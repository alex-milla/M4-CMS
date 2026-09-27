<?php
// Script de setup inicial del CMS
require_once 'config.php';
require_once 'db/functions.php';

// Verificar si ya está configurado: no permitir reinstalación si existe
// la marca de setup o credenciales previas (.env / admin_config.php).
$setup_file = __DIR__ . '/.setup_completed';
$env_file   = __DIR__ . '/.env';
$cfg_file   = __DIR__ . '/admin_config.php';
if (file_exists($setup_file) || file_exists($env_file) || file_exists($cfg_file)) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_user = trim($_POST['admin_user'] ?? '');
    $admin_pass = $_POST['admin_pass'] ?? '';
    
    if (empty($admin_user) || empty($admin_pass)) {
        $error = 'Todos los campos son obligatorios';
    } else {
        // Crear el esquema completo (posts, settings, categories, widgets) + migraciones
        try {
            createTable();

            // Guardar configuración de administrador (hash REAL bcrypt) en .env y admin_config.php
            $admin_hash = password_hash($admin_pass, PASSWORD_DEFAULT);
            
            // Escribir .env
            $cmsRoot = __DIR__;
            $envContent = "# Credenciales admin - generadas " . date('c') . "\n";
            $envContent .= "# Archivo protegido - NO compartir ni subir a repositorios públicos\n";
            $envContent .= "ADMIN_USER=" . addslashes($admin_user) . "\n";
            $envContent .= "ADMIN_PASSWORD_HASH=" . addslashes($admin_hash) . "\n";
            
            if (!file_put_contents($cmsRoot . '/.env', $envContent)) {
                throw new RuntimeException('No se pudo escribir .env');
            }
            @chmod($cmsRoot . '/.env', 0600);
            
            // Escribir admin_config.php (persistencia dual)
            if (!defined('ADMIN_USER')) define('ADMIN_USER', 'placeholder');
            if (!defined('ADMIN_PASSWORD')) define('ADMIN_PASSWORD', 'placeholder');
            
            $config_content = "<?php\n";
            $config_content .= "// Credenciales admin - NO EDITAR manualmente\n";
            $config_content .= "// Generado: " . date('c') . "\n";
            $config_content .= "define('ADMIN_USER', '" . addslashes($admin_user) . "');\n";
            $config_content .= "define('ADMIN_PASSWORD', '" . addslashes($admin_hash) . "');\n";
            $config_content .= "?>";
            
            if (!file_put_contents($cmsRoot . '/admin_config.php', $config_content)) {
                throw new RuntimeException('No se pudo escribir admin_config.php');
            }
            @chmod($cmsRoot . '/admin_config.php', 0600);
            
            // Marcar como configurado
            file_put_contents($setup_file, 'completed');

            $success = 'Configuración completada exitosamente. Ahora puedes acceder al área de administración.';

            // Auto-eliminar setup.php tras instalacion exitosa
            @unlink(__FILE__);

        } catch (Exception $e) {
            $error = 'Error en la configuración: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Setup - M4 CMS</title>
    <link rel="stylesheet" href="assets/finsec.css">
</head>
<body class="theme-finsec">
    <div class="container">
        <header>
            <h1>M4 CMS SETUP</h1>
        </header>
        
        <main>
            <h2>Configuración Inicial</h2>
            
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div style="background-color: #00aa00; color: #000000; padding: 15px; margin-bottom: 20px; border: 1px solid #00aa00;">
                    <?php echo $success; ?>
                    <br><br>
                    <a href="index.php" class="button">Ir al sitio</a>
                </div>
            <?php else: ?>
                <p>Completa la configuración inicial del CMS:</p>
                
                <form method="POST" action="">
                    <div>
                        <label for="admin_user">Usuario Admin:</label>
                        <input type="text" id="admin_user" name="admin_user" required>
                    </div>
                    
                    <div>
                        <label for="admin_pass">Contraseña Admin:</label>
                        <input type="password" id="admin_pass" name="admin_pass" required>
                    </div>
                    
                    <button type="submit">Configurar</button>
                </form>
                
                <p><strong>Nota:</strong> Esta contraseña se utilizará para acceder al área de administración del CMS.</p>
            <?php endif; ?>
        </main>
        
        <footer>
            <p>M4 CMS</p>
        </footer>
    </div>
</body>
</html>