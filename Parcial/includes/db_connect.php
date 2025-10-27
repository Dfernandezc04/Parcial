<?php
// includes/db_connect.php

// Define tus credenciales de la BD (Cámbialas según tu configuración de XAMPP)
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Usuario por defecto de XAMPP
define('DB_PASSWORD', '');     // Contraseña por defecto de XAMPP (vacía)
define('DB_NAME', 'citas_css'); // Nombre de la BD

// Intento de conexión a la BD
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar la conexión
if ($conn->connect_error) {
    die("Error de conexión a la base de datos: " . $conn->connect_error);
}

// Opcional, pero recomendado: Establecer el conjunto de caracteres a UTF8
$conn->set_charset("utf8");

// La variable $conn está lista para usarse en el resto del código.
?>