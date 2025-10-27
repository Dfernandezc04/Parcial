<?php
// login.php (Página y lógica de Inicio de Sesión)

include_once 'includes/db_connect.php'; 
include_once 'includes/secure_session.php'; // Esto debe contener session_start()

// Redirigir si el usuario ya está logeado (solo por seguridad)
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if ($_SESSION['rol'] == 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}


$login_error = '';

// Lógica de Inicio de Sesión (Se activa al enviar el formulario POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Usar la conexión a la base de datos para sanear la entrada
    $correo = $conn->real_escape_string($_POST['correo']);
    $password = $_POST['password'];

    // 1. Buscar usuario
    // Se asume que el campo en la tabla es 'correo'
    $sql = "SELECT id, nombre, password_hash, rol, institucion_preferida FROM Usuarios WHERE correo = ?";
    
    // Usar sentencias preparadas para mayor seguridad
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // 2. Verificar contraseña (Ciberseguridad: Hashing)
            if (password_verify($password, $user['password_hash'])) {
                
                // Éxito: Crear variables de Sesión
                $_SESSION['loggedin'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nombre'] = $user['nombre'];
                $_SESSION['rol'] = $user['rol'];
                $_SESSION['institucion'] = $user['institucion_preferida'];

                // 3. Redirigir según el rol
                if ($user['rol'] == 'admin') {
                    header("Location: admin/dashboard.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit;

            } else {
                $login_error = "Credenciales incorrectas (Contraseña).";
            }
        } else {
            $login_error = "Credenciales incorrectas (Usuario no encontrado).";
        }
        $stmt->close();
    } else {
         $login_error = "Error de base de datos en la preparación de la consulta.";
    }

}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - Sistema de Citas Médicas</title>
    <link rel="stylesheet" href="style.css">
    
    <style>
        body { background-color: #f4f7f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .login-container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); width: 350px; text-align: center; }
        .login-container h2 { color: #007bff; margin-bottom: 5px; }
        .login-container p { color: #6c757d; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #333; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn-login { width: 100%; padding: 10px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1.0em; font-weight: 600; transition: background-color 0.2s; }
        .btn-login:hover { background-color: #0056b3; }
        .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .login-container a { color: #007bff; text-decoration: none; font-weight: 600; }
        .login-container a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Sistema de Citas Médicas</h2>
        <p>Inicie sesión para acceder a su cuenta.</p>
        
        <?php if (!empty($login_error)): ?>
            <p class="error-message"><?php echo $login_error; ?></p>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="correo">Correo Electrónico:</label>
                <input type="email" id="correo" name="correo" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn-login">Ingresar</button>
        </form>
        <p style="margin-top: 15px;">¿No tienes cuenta? <a href="registro.php">Regístrate aquí</a></p>
    </div>
</body>
</html>