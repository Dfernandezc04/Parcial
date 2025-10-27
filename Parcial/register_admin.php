<?php
// register_admin.php (¡BORRAR DESPUÉS DE USAR!)
include_once 'includes/db_connect.php';

$nombre = "Dario Fernandez";
$correo = "admin@css.pa";
$password = "Prueba123"; // ¡Cambia esta contraseña!
$rol = "admin";
$institucion = "CSS";

// 1. Hashing Seguro (Ciberseguridad)
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// 2. Insertar en la BD
$sql = "INSERT INTO Usuarios (nombre, correo, password_hash, rol, institucion_preferida) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssss", $nombre, $correo, $password_hash, $rol, $institucion);

if ($stmt->execute()) {
    echo "<h2>Usuario Admin Creado Exitosamente: admin@css.pa</h2>";
    echo "<p>Contraseña: " . $password . " (Solo para la primera vez)</p>";
    echo "<p style='color:red;'>¡Ahora borre este archivo 'register_admin.php'!</p>";
} else {
    echo "Error al crear usuario: " . $conn->error;
}
$stmt->close();
$conn->close();
?>