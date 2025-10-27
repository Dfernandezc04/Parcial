<?php
// dashboard.php

include_once 'includes/db_connect.php'; 
include_once 'includes/secure_session.php'; 

// 1. Verificar la sesión: Solo permite el acceso a usuarios logeados
check_session();

// Variables de sesión del usuario
$user_id = $_SESSION['user_id'];
$nombre_usuario = $_SESSION['nombre'];
$institucion_actual = $_SESSION['institucion'] ?? 'CSS'; // Aseguramos un valor por defecto

// 2. Lógica para manejar el CAMBIO de institución (si el usuario la cambia en el formulario)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['institucion_elegida'])) {
    $nueva_institucion = $conn->real_escape_string($_POST['institucion_elegida']);
    
    // Actualizar la sesión y la BD
    $_SESSION['institucion'] = $nueva_institucion;
    $institucion_actual = $nueva_institucion;
    
    // Opcional: Actualizar el valor por defecto en la BD del usuario (para la próxima vez que inicie)
    $stmt = $conn->prepare("UPDATE Usuarios SET institucion_preferida = ? WHERE id = ?");
    $stmt->bind_param("si", $nueva_institucion, $user_id);
    $stmt->execute();
    $stmt->close();
}

// 🚨 LÓGICA DE COLOR DINÁMICO
$header_class = 'header-css'; 
if ($institucion_actual == 'Instituto Oncológico') {
    $header_class = 'header-oncologico'; 
} elseif ($institucion_actual == 'MINSA') {
    $header_class = 'header-minsa'; 
}


// 3. Obtener el Historial Básico de Citas del Paciente (Flujo 1.2)
$citas = [];
$sql_citas = "
    SELECT 
        C.id, 
        C.fecha_hora, 
        E.nombre AS especialista_nombre, 
        E.especialidad,
        C.estado
    FROM Citas C
    JOIN Especialistas E ON C.especialista_id = E.id
    WHERE C.paciente_id = ? AND C.institucion = ? 
    ORDER BY C.fecha_hora DESC 
    LIMIT 10"; // Mostrar las últimas 10 citas

$stmt_citas = $conn->prepare($sql_citas);
$stmt_citas->bind_param("is", $user_id, $institucion_actual);
$stmt_citas->execute();
$result_citas = $stmt_citas->get_result();
while ($row = $result_citas->fetch_assoc()) {
    $citas[] = $row;
}
$stmt_citas->close();

// Lista de instituciones disponibles (Para el select)
$instituciones_disponibles = ['CSS', 'MINSA', 'Instituto Oncológico'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Citas | <?php echo $institucion_actual; ?></title>
    <link rel="stylesheet" href="style.css"> 
    
    <style>
        .header-css { background-color: #004d99 !important; }      /* AZUL para CSS */
        .header-minsa { background-color: #28a745 !important; }    /* VERDE para MINSA */
        .header-oncologico { background-color: #e83e8c !important; } /* ROSADO para Oncológico */
        .header {
            /* Asegura que la clase de color sobrescriba el estilo base */
            padding: 20px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        /* Estilos para el estado de las citas (si no están en style.css) */
        .status-programada { color: #007bff; font-weight: bold; }
        .status-cancelada { color: #dc3545; }
        .status-completada { color: #28a745; }

        /* Estilo para el botón de cancelar (Necesario si no está en style.css) */
        .btn-danger {
            background-color: #dc3545; /* Rojo */
            color: white;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
        <div class="header <?php echo $header_class; ?>">
        <h1>Panel de Citas | <?php echo htmlspecialchars($institucion_actual); ?></h1>
        <div class="user-info">
            <span>Bienvenido, <?php echo htmlspecialchars($nombre_usuario); ?></span>
            <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        
        <div class="institution-selector">
            <h3>Institución Actual: <span class="current-institution"><?php echo $institucion_actual; ?></span></h3>
            <form action="dashboard.php" method="POST" class="inline-form">
                <label for="institucion_elegida">Cambiar a:</label>
                <select id="institucion_elegida" name="institucion_elegida">
                    <?php foreach ($instituciones_disponibles as $inst): ?>
                                                <option value="<?php echo $inst; ?>" <?php echo ($inst === $institucion_actual) ? 'selected' : ''; ?>>
                            <?php echo $inst; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-primary">Cambiar</button>
            </form>
        </div>
        
        <hr>

        <div class="action-buttons">
            <a href="agendar.php" class="btn-primary large-btn">📅 AGENDAR NUEVA CITA en <?php echo $institucion_actual; ?></a>
        </div>

        <hr>

        <h2>Historial y Próximas Citas (Últimas 10)</h2>

        <?php if (empty($citas)): ?>
            <p>Aún no tiene citas registradas en <?php echo $institucion_actual; ?>.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha y Hora</th>
                        <th>Especialista</th>
                        <th>Especialidad</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($citas as $cita): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($cita['fecha_hora'])); ?></td>
                            <td><?php echo htmlspecialchars($cita['especialista_nombre']); ?></td>
                            <td><?php echo htmlspecialchars($cita['especialidad']); ?></td>
                            <td class="status-<?php echo strtolower($cita['estado']); ?>">
                                <?php echo ucfirst($cita['estado']); ?>
                            </td>
                            <td>
                                <?php if ($cita['estado'] === 'programada'): ?>
                                                                        <a href="cancelar.php?id=<?php echo $cita['id']; ?>" class="btn-danger small-btn" **onclick="return confirm('¿Está seguro que desea cancelar esta cita? Esta acción no se puede deshacer.');"**>Cancelar</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>
</body>
</html>