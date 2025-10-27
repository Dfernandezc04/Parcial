<?php
// admin/gestion_pacientes.php (Gestión de registros de pacientes)

include_once '../includes/db_connect.php'; 
include_once '../includes/secure_session.php'; 

// 1. Verificar Autenticación y Rol
check_admin(); 

// 2. Lógica para ELIMINAR PACIENTE (Debe ser usado con EXTREMA precaución)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $paciente_id = (int)$_GET['id'];
    
    // Antes de eliminar el usuario, se debe considerar:
    // 1. Cancelar o eliminar sus citas pendientes (para mantener la integridad de la BD)
    
    // Eliminación del usuario (asumiendo que la BD maneja las dependencias/citas)
    $stmt = $conn->prepare("DELETE FROM Usuarios WHERE id = ? AND rol = 'paciente'");
    $stmt->bind_param("i", $paciente_id);
    $stmt->execute();
    $stmt->close();
    
    header('Location: gestion_pacientes.php?success=paciente_eliminado');
    exit;
}


// 3. Obtener la Lista de Pacientes
$pacientes = [];
$sql = "SELECT id, cedula, nombre, correo, institucion_preferida FROM Usuarios WHERE rol = 'paciente' ORDER BY nombre ASC";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pacientes[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Pacientes - Admin</title>
    <link rel="stylesheet" href="../style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .admin-container { max-width: 1200px; margin: 50px auto; padding: 30px; background: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); }
        h2 { color: #007bff; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 30px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .data-table th { background-color: #343a40; color: white; font-weight: 600; }
        .data-table tr:nth-child(even) { background-color: #f8f8f8; }
        
        .btn-action { padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 0.9em; margin-right: 5px; }
        .btn-delete { background-color: #dc3545; color: white; }
        .btn-delete:hover { background-color: #c82333; }
    </style>
</head>
<body>
    <div class="admin-container">
        <a href="dashboard.php" class="btn-secondary" style="float: right;">&larr; Volver al Panel</a>
        <h2>Gestión de Pacientes</h2>

        <?php if (isset($_GET['success'])): ?>
            <div style="background-color: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px;">
                Paciente eliminado exitosamente.
            </div>
        <?php endif; ?>

        <?php if (count($pacientes) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cédula</th>
                        <th>Nombre Completo</th>
                        <th>Correo</th>
                        <th>Institución Pref.</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pacientes as $paciente): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($paciente['id']); ?></td>
                            <td><?php echo htmlspecialchars($paciente['cedula'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($paciente['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($paciente['correo']); ?></td>
                            <td><?php echo htmlspecialchars($paciente['institucion_preferida']); ?></td>
                            <td>
                                <a href="gestion_pacientes.php?action=delete&id=<?php echo $paciente['id']; ?>" 
                                   class="btn-action btn-delete" 
                                   onclick="return confirm('ADVERTENCIA: ¿Está seguro de eliminar a este paciente? Esto eliminará todos sus datos.');">
                                    Eliminar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay pacientes registrados en el sistema.</p>
        <?php endif; ?>

    </div>
</body>
</html>