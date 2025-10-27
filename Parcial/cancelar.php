<?php
// cancelar.php

include_once 'includes/db_connect.php';
include_once 'includes/secure_session.php';

// Redirigir si no está logeado o es admin (asumiendo que solo pacientes pueden cancelar)
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['rol'] !== 'paciente') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$cita_id = (int)$_GET['id']; // Capturamos el ID del enlace

if ($cita_id > 0) {
    // Usar UPDATE en lugar de DELETE para cambiar el estado a 'cancelada' (es mejor práctica)
    $sql = "UPDATE Citas SET estado = 'cancelada' WHERE id = ? AND paciente_id = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $cita_id, $user_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            header('Location: dashboard.php?success=cita_cancelada');
        } else {
            header('Location: dashboard.php?error=cita_no_encontrada');
        }
        $stmt->close();
    }
} else {
    header('Location: dashboard.php?error=no_id');
}
exit;
?>