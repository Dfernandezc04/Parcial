<?php
// admin/gestion_citas.php (o administrar_citas.php)

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

$institucion_actual = $_SESSION['institucion'] ?? 'CSS';
$mensaje = ''; 
$clase_mensaje = '';

// 2. Lógica para mostrar mensaje de éxito/error después de una cancelación o MODIFICACIÓN
if (isset($_SESSION['message'])) {
    $mensaje = $_SESSION['message']['text'];
    $clase_mensaje = $_SESSION['message']['class'];
    unset($_SESSION['message']); // Limpiar el mensaje después de mostrarlo
}

// 3. Consulta de Citas (Usando C.fecha_hora)
$sql = "SELECT 
            C.id AS cita_id,
            C.fecha_hora,    
            C.motivo,
            U.nombre AS paciente_nombre,
            U.cedula AS paciente_cedula,
            E.nombre AS especialista_nombre,
            E.especialidad
        FROM Citas C
        JOIN Usuarios U ON C.paciente_id = U.id
        JOIN Especialistas E ON C.especialista_id = E.id
        WHERE C.institucion = ? 
        ORDER BY C.fecha_hora ASC"; 

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error al preparar la consulta: " . $conn->error);
}

$stmt->bind_param("s", $institucion_actual);
$stmt->execute();
$result = $stmt->get_result();
$citas = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$conn->close();

// 4. Clases de Estilo Dinámicas
$header_class = 'header-css'; 
if ($institucion_actual == 'Instituto Oncológico') {
    $header_class = 'header-oncologico'; 
} elseif ($institucion_actual == 'MINSA') {
    $header_class = 'header-minsa'; 
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Citas | Admin</title>
    <link rel="stylesheet" href="../style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .header-css { background-color: #004d99 !important; } 
        .header-minsa { background-color: #28a745 !important; } 
        .header-oncologico { background-color: #e83e8c !important; } 
        .container { max-width: 1400px; margin: 30px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; font-size: 0.9em; }
        .btn-danger { background-color: #dc3545; color: white; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; }
        /* Añadir estilo para el botón Modificar */
        .btn-info { background-color: #17a2b8; color: white; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; margin-right: 5px; }
        .success-message { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; margin-bottom: 20px; border-radius: 5px; }
        .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 20px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="header <?php echo $header_class; ?>">
        <h1>Gestión de Citas Agendadas | <?php echo htmlspecialchars($institucion_actual); ?></h1>
        <div class="user-info">
            <a href="dashboard.php" class="btn-logout">← Volver al Panel</a>
        </div>
    </div>

    <div class="container">
        <h2>Citas Programadas</h2>
        
        <?php if ($mensaje): ?>
            <div class="<?php echo $clase_mensaje; ?>">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($citas)): ?>
            <p class="info-message">No hay citas agendadas para **<?php echo htmlspecialchars($institucion_actual); ?>**.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID Cita</th>
                        <th>Fecha y Hora</th>
                        <th>Paciente (Cédula)</th>
                        <th>Especialista</th>
                        <th>Especialidad</th>
                        <th>Motivo</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($citas as $cita): ?>
                        <tr>
                            <td><?php echo $cita['cita_id']; ?></td>
                            <td><?php echo date('d/m/Y H:i A', strtotime($cita['fecha_hora'])); ?></td> 
                            <td><?php echo htmlspecialchars($cita['paciente_nombre']); ?> (<?php echo htmlspecialchars($cita['paciente_cedula']); ?>)</td>
                            <td><?php echo htmlspecialchars($cita['especialista_nombre']); ?></td>
                            <td><?php echo htmlspecialchars($cita['especialidad']); ?></td>
                            <td><?php echo htmlspecialchars($cita['motivo']); ?></td>
                            <td>
                                <a href="modificar_cita.php?id=<?php echo $cita['cita_id']; ?>" 
                                   class="btn-info">
                                    <i class="fas fa-edit"></i> Modificar
                                </a>
                                                                <a href="cancelar_cita.php?id=<?php echo $cita['cita_id']; ?>" 
                                   class="btn-danger"
                                   onclick="return confirm('¿Está seguro de que desea cancelar esta cita? Esta acción es irreversible.');">
                                    <i class="fas fa-times-circle"></i> Cancelar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>
</body>
</html>