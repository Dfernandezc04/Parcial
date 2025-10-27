<?php
// includes/secure_session.php

// Iniciar o reanudar la sesión
session_start();

// Función para verificar si hay una sesión activa
function check_session() {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        // Redirigir al login si no está autenticado
        header("Location: /citas-utp/index.php"); 
        exit;
    }
}

// Función para verificar si el usuario tiene el rol de administrador
function check_admin() {
    check_session(); // Primero verifica la sesión
    if ($_SESSION['rol'] !== 'admin') {
        // Redirigir si no es administrador
        header("Location: /parcial/dashboard.php"); 
        exit;
    }
}
?>