<?php
// admin/cancelar_cita.php

include_once '../includes/db_connect.php'; 
include_once '../includes/secure_session.php'; 

// 1. Verificar Rol de Administrador
if (function_exists('check_admin')) {
    check_admin(); 
} else {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['rol'] !== 'admin') {
        header('Location: ../login.php');
        exit;
    }
}

// Verificar que se proporcionó un ID de cita
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = [
        'text' => 'Error: ID de cita no válido.',
        'class' => 'error-message'
    ];
    header('Location: gestion_citas.php');
    exit;
}

$cita_id = (int)$_GET['id'];
$institucion_actual = $_SESSION['institucion'] ?? 'CSS';

// 2. Consulta y Eliminación (Asegúrate de que la cita pertenezca a la institución)
$sql = "DELETE FROM Citas WHERE id = ? AND institucion = ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("is", $cita_id, $institucion_actual);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['message'] = [
                'text' => "Cita ID **{$cita_id}** ha sido cancelada exitosamente.",
                'class' => 'success-message'
            ];
        } else {
            $_SESSION['message'] = [
                'text' => "Error: No se encontró la cita ID **{$cita_id}** o no pertenece a {$institucion_actual}.",
                'class' => 'error-message'
            ];
        }
    } else {
        $_SESSION['message'] = [
            'text' => 'Error en la base de datos al cancelar la cita.',
            'class' => 'error-message'
        ];
    }
    $stmt->close();
} else {
    $_SESSION['message'] = [
        'text' => 'Error de preparación de consulta.',
        'class' => 'error-message'
    ];
}

$conn->close();

// 3. Redirigir de vuelta a la página de gestión de citas
header('Location: gestion_citas.php');
exit;