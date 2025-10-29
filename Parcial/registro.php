<?php

// registro.php

// Incluye la conexión a la base de datos
include_once __DIR__ . '/includes/db_connect.php';

$register_error = '';
$register_success = '';

// Valores para mantener el formulario si hay un error
// Usamos el operador de fusión de null (?? '') para evitar el "Undefined array key"
$nombre_post = $_POST['nombre'] ?? ''; // Único campo para Nombre y Apellido
$correo_post = $_POST['correo'] ?? '';
$telefono_post = $_POST['telefono'] ?? '';
$cedula_post = $_POST['cedula'] ?? ''; 
$password_confirm = $_POST['password_confirm'] ?? ''; 
$password = $_POST['password'] ?? '';

// Lógica de Registro (Se activa al enviar el formulario POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Obtener y limpiar datos de entrada
    $nombre = $conn->real_escape_string($nombre_post); // Aquí estará el nombre y apellido juntos
    $correo = $conn->real_escape_string($correo_post);
    $telefono = $conn->real_escape_string($telefono_post);
    $cedula = $conn->real_escape_string($cedula_post);

    // Asignamos valores por defecto para el nuevo paciente
    $rol = 'paciente';
    $institucion_default = 'CSS';

   // 2. Validación estricta
    // Se valida que el campo "nombre" (Nombre y Apellido) no esté vacío
    if (empty($nombre) || empty($correo) || empty($password) || empty($cedula) || empty($password_confirm)) {
        $register_error = "Todos los campos obligatorios (*) deben ser llenados.";
    
    // NUEVA VALIDACIÓN: Verifica que el campo de nombre tenga al menos dos palabras separadas por espacio.
    } elseif (!preg_match('/\s/', trim($nombre))) {
        $register_error = "Por favor, ingresa tu **Nombre y Apellido** completo.";
        
    // Comprobar si las contraseñas coinciden
    } elseif ($password !== $password_confirm) {
        $register_error = "Las contraseñas no coinciden.";

    // Comprobar la longitud mínima de la contraseña
    } elseif (strlen($password) < 8) {
        $register_error = "La contraseña debe tener al menos 8 caracteres.";
    
    // VALIDACIÓN DE CONTRASEÑA ESTRICTA 
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*()_+={}\[\]|\\:;"\'<,>.?\/~`])/', $password)) {
         $register_error = "La contraseña debe contener al menos 8 caracteres, una mayúscula, una minúscula, un número y un carácter especial.";

    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $register_error = "El formato del correo electrónico no es válido.";
    } else {
    
    // VALIDACIÓN DE FORMATO DE CÉDULA (PANAMÁ)
    $cedula_pattern = '/^\d{1,2}-\d{3,4}-\d{3,4}$/';

    if (!preg_match($cedula_pattern, $cedula)) {
        $register_error = "El formato de la Cédula debe ser válido (ej: 8-888-888).";
    } else {

            // 3. Ciberseguridad: Hashing de la Contraseña
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // 4. Inserción en la Base de Datos
            $sql = "INSERT INTO Usuarios (nombre, cedula, correo, telefono, password_hash, rol, institucion_preferida)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            // Se usa $nombre que contiene el nombre y apellido juntos
            $stmt->bind_param("sssssss", $nombre, $cedula, $correo, $telefono, $password_hash, $rol, $institucion_default);

            try {
                if ($stmt->execute()) {
                    $register_success = "✅ ¡Registro exitoso! Ya puedes <a href='index.php'>iniciar sesión</a>.";
                } else {
                    if ($conn->errno == 1062) {
                        $register_error = "El correo electrónico o la Cédula ya están registrados. Por favor, inicia sesión o verifica los datos.";
                    } else {
                        $register_error = "❌ Error al registrar el usuario: " . $conn->error;
                    }
                }
            } catch (Exception $e) {
                $register_error = "❌ Error interno del servidor.";
            }
            
            $stmt->close();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Usuario</title>
    <link rel="stylesheet" href="style.css">
    
    <style>
        /* ESTILOS DEL CONTENEDOR CENTRAL */
        .login-container {
            max-width: 350px; 
            margin: 50px auto; 
            padding: 30px;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            background-color: #fff;
        }

        /* ESTILOS GENERALES DEL FORMULARIO */
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            font-size: 0.9em;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            box-sizing: border-box; 
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        /* ESTILOS DEL BOTÓN */
        .btn-primary {
            display: block;
            width: 100%;
            padding: 10px;
            margin-top: 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }

        /* Mensajes de feedback */
        .error-message { color: #dc3545; background-color: #f8d7da; padding: 10px; border-radius: 4px; border: 1px solid #f5c6cb; }
        .success-message { color: #28a745; background-color: #d4edda; padding: 10px; border-radius: 4px; border: 1px solid #c3e6cb; }
        
        .mt-2 { margin-top: 15px; text-align: center; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Registro de Nuevo Paciente</h2>
        
        <?php if (!empty($register_error)): ?>
            <p class="error-message"><?php echo $register_error; ?></p>
        <?php endif; ?>
        
        <?php if (!empty($register_success)): ?>
            <p class="success-message"><?php echo $register_success; ?></p>
        <?php endif; ?>

        <form action="registro.php" method="POST">
            <div class="form-group">
                <label for="nombre">Nombre y Apellido (*):</label>
                <input type="text" id="nombre" name="nombre" required 
                title="Debe ingresar su nombre y al menos un apellido."
                value="<?php echo htmlspecialchars($nombre_post); ?>">
            </div>

            <div class="form-group">
                <label for="cedula">Cédula (ej: 8-888-888) (*):</label>
                <input type="text" id="cedula" name="cedula" required
                    placeholder="Ej: 8-888-888"
                    pattern="^\d{1,2}-\d{3,4}-\d{3,4}$"
                    title="Formato requerido: X-XXXX-XXX o XX-XXX-XXX"
                    value="<?php echo htmlspecialchars($cedula_post); ?>">
            </div>

            <div class="form-group">
                <label for="correo">Correo Electrónico (*):</label>
                <input type="email" id="correo" name="correo" required value="<?php echo htmlspecialchars($correo_post); ?>">
            </div>

            <div class="form-group">
                <label for="telefono">Teléfono:</label>
                <input type="text" id="telefono" name="telefono" value="<?php echo htmlspecialchars($telefono_post); ?>">
            </div>

            <div class="form-group">
                <label for="password">Contraseña (*):</label>
                <input type="password" id="password" name="password" required
                title="Debe tener al menos 8 caracteres, una mayúscula, una minúscula, un número y un carácter especial."
                >
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirmar Contraseña (*):</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>

            <button type="submit" class="btn-primary btn-login">Registrarse</button>
        </form>
        
        <p class="mt-2">¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a></p>
    </div>
</body>
</html>
