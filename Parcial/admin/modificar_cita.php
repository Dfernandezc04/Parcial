<?php
// admin/modificar_cita.php - Formulario para cambiar la fecha y hora de una cita.

include_once '../includes/db_connect.php'; 
include_once '../includes/secure_session.php'; 

// 1. Verificar Rol y obtener institución
if (function_exists('check_admin')) {
    check_admin(); 
} else {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['rol'] !== 'admin') {
        header('Location: ../login.php');
        exit;
    }
}

$institucion_actual = $_SESSION['institucion'] ?? 'CSS';
$cita_id = $_GET['id'] ?? 0;
$mensaje = ''; 
$clase_mensaje = '';
$cita_actual = null;
$disponibilidad = [];

// 2. Obtener datos de la cita actual
if ($cita_id > 0) {
    $sql_cita = "SELECT C.id, C.paciente_id, U.nombre AS paciente_nombre, C.especialista_id, 
                        E.nombre AS especialista_nombre, E.especialidad, C.fecha_hora, C.motivo
                 FROM Citas C
                 JOIN Usuarios U ON C.paciente_id = U.id
                 JOIN Especialistas E ON C.especialista_id = E.id
                 WHERE C.id = ? AND C.institucion = ?";
    
    $stmt_cita = $conn->prepare($sql_cita);
    $stmt_cita->bind_param("is", $cita_id, $institucion_actual);
    $stmt_cita->execute();
    $result_cita = $stmt_cita->get_result();
    
    if ($result_cita->num_rows == 1) {
        $cita_actual = $result_cita->fetch_assoc();
    } else {
        $_SESSION['message'] = ['text' => 'Cita no encontrada o no pertenece a esta institución.', 'class' => 'error-message'];
        // Redirección al listado principal (Panel anterior)
        header('Location: gestion_citas.php'); 
        exit;
    }
    $stmt_cita->close();
} else {
    // Redirección al listado principal (Panel anterior)
    header('Location: gestion_citas.php'); 
    exit;
}

// 3. Lógica para CAMBIAR/ACTUALIZAR la Cita (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nueva_fecha_hora'])) {
    $nueva_fecha_hora = $_POST['nueva_fecha_hora'];
    
    // Asumo que tu campo de fecha y hora es un DATETIME o TIMESTAMP
    $sql_update = "UPDATE Citas SET fecha_hora = ? WHERE id = ? AND institucion = ?";
    $stmt_update = $conn->prepare($sql_update);
    
    if ($stmt_update) {
        $stmt_update->bind_param("sis", $nueva_fecha_hora, $cita_id, $institucion_actual);
        
        if ($stmt_update->execute()) {
            if ($stmt_update->affected_rows > 0) {
                $_SESSION['message'] = ['text' => "El horario de la cita ID {$cita_id} ha sido modificado exitosamente a " . date('d/m/Y H:i A', strtotime($nueva_fecha_hora)) . ".", 'class' => 'success-message'];
            } else {
                $_SESSION['message'] = ['text' => 'No se realizaron cambios o la cita ya tiene ese horario.', 'class' => 'info-message'];
            }
        } else {
            $_SESSION['message'] = ['text' => 'Error al actualizar la cita en la base de datos: ' . $stmt_update->error, 'class' => 'error-message'];
        }
        $stmt_update->close();
    } else {
        $_SESSION['message'] = ['text' => 'Error de preparación de consulta para la actualización.', 'class' => 'error-message'];
    }
    
    // Redirigir al panel anterior (listado de citas)
    header("Location: gestion_citas.php"); 
    exit;
}

// 4. Lógica para CARGAR Disponibilidad (GET o Clic en Buscar)
if (isset($_GET['fecha_filtro']) && !empty($_GET['fecha_filtro'])) {
    $fecha_filtro = date('Y-m-d', strtotime($_GET['fecha_filtro']));
    $especialista_id = $cita_actual['especialista_id'];
    
    // Consulta para obtener bloques disponibles que NO estén ya ocupados por otra cita
    $sql_dispo = "
        SELECT 
            D.fecha_hora_inicio AS inicio, 
            D.fecha_hora_fin AS fin        
        FROM Disponibilidad D
        LEFT JOIN Citas C ON C.especialista_id = D.especialista_id 
                         AND C.institucion = D.institucion
                         AND C.fecha_hora BETWEEN D.fecha_hora_inicio AND D.fecha_hora_fin 
                         AND C.id != ?  /* Excluir la cita que estamos modificando */
        WHERE D.especialista_id = ? 
          AND D.institucion = ? 
          AND DATE(D.fecha_hora_inicio) = ? 
          AND C.id IS NULL /* Solo bloques que NO tienen citas (NULL) */
        ORDER BY D.fecha_hora_inicio ASC"; 

    $stmt_dispo = $conn->prepare($sql_dispo);
    $stmt_dispo->bind_param("isss", $cita_id, $especialista_id, $institucion_actual, $fecha_filtro);
    $stmt_dispo->execute();
    $result_dispo = $stmt_dispo->get_result();
    
    while ($row = $result_dispo->fetch_assoc()) {
        $disponibilidad[] = $row;
    }
    $stmt_dispo->close();
}

$conn->close();

// 5. Clases de Estilo Dinámicas
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
    <title>Modificar Cita ID <?php echo $cita_id; ?> | Admin</title>
    <link rel="stylesheet" href="../style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    
    <style>
        /* (Mantengo tus estilos existentes) */
        .header-css { background-color: #004d99 !important; } 
        .header-minsa { background-color: #28a745 !important; } 
        .header-oncologico { background-color: #e83e8c !important; } 
        .container { max-width: 900px; margin: 30px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .details-box { background-color: #f4f4f4; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .details-box p { margin: 5px 0; }
        .details-box strong { font-weight: bold; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .input-date { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .btn-primary { background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-primary:hover { background-color: #0056b3; }
        .time-slot { 
            background-color: #e9ecef; 
            border: 1px solid #ced4da; 
            padding: 10px; 
            margin-bottom: 10px; 
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .time-slot:hover { background-color: #dee2e6; }
        .time-slot input[type="radio"] { margin-right: 10px; }
        .btn-success { background-color: #28a745; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
        .success-message, .error-message, .info-message { padding: 10px; margin-bottom: 20px; border-radius: 5px; }
        .success-message { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info-message { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }

        /* Estilos adicionales de Flatpickr para mejor apariencia (opcional) */
        .flatpickr-calendar {
            box-shadow: 0 6px 16px rgba(0,0,0,0.15);
            border: 1px solid #e0e0e0;
            border-radius: 8px; /* Bordes más redondeados */
        }
    </style>
</head>
<body>
    <div class="header <?php echo $header_class; ?>">
        <h1>Modificar Cita ID <?php echo $cita_id; ?> | <?php echo htmlspecialchars($institucion_actual); ?></h1>
        <div class="user-info">
                        <a href="administrar_citas.php" class="btn-logout">← Volver a Citas</a> 
        </div>
    </div>

    <div class="container">
        <h2>Detalles de la Cita</h2>
        
        <div class="details-box">
            <p><strong>Paciente:</strong> <?php echo htmlspecialchars($cita_actual['paciente_nombre']); ?></p>
            <p><strong>Especialista:</strong> Dr. <?php echo htmlspecialchars($cita_actual['especialista_nombre']); ?></p>
            <p><strong>Especialidad:</strong> <?php echo htmlspecialchars($cita_actual['especialidad']); ?></p>
            <p><strong>Horario Actual:</strong> **<?php echo date('d/m/Y H:i A', strtotime($cita_actual['fecha_hora'])); ?>**</p>
            <p><strong>Motivo:</strong> <?php echo htmlspecialchars($cita_actual['motivo']); ?></p>
        </div>

        <hr>

        <h3>Buscar Nuevo Horario Disponible</h3>

        <form action="modificar_cita.php" method="GET" class="form-group">
            <input type="hidden" name="id" value="<?php echo $cita_id; ?>">
            <label for="fecha_filtro">Seleccione la Fecha:</label>
                        <input type="date" id="fecha_filtro" name="fecha_filtro" class="input-date" required 
                   value="<?php echo htmlspecialchars($_GET['fecha_filtro'] ?? date('Y-m-d')); ?>">
            <button type="submit" class="btn-primary">Buscar Disponibilidad</button>
        </form>

        <?php if (isset($_GET['fecha_filtro'])): ?>
            <h4>Horarios Disponibles para el Especialista el <?php echo date('d/m/Y', strtotime($_GET['fecha_filtro'])); ?></h4>
            
            <?php if (empty($disponibilidad)): ?>
                <div class="info-message">No hay bloques disponibles para el **Dr. <?php echo htmlspecialchars($cita_actual['especialista_nombre']); ?>** en esa fecha.</div>
            <?php else: ?>
                <form action="modificar_cita.php?id=<?php echo $cita_id; ?>" method="POST">
                    
                    <?php foreach ($disponibilidad as $slot): 
                        $fecha_hora_inicio = $slot['inicio'];
                        $fecha_hora_fin = $slot['fin'];
                        $hora_inicio = date('H:i A', strtotime($fecha_hora_inicio));
                        $hora_fin = date('H:i A', strtotime($fecha_hora_fin));
                        $display_date = date('d/m/Y', strtotime($fecha_hora_inicio));
                    ?>
                        <div class="time-slot">
                            <label>
                                <input type="radio" name="nueva_fecha_hora" value="<?php echo htmlspecialchars($fecha_hora_inicio); ?>" required>
                                **<?php echo $hora_inicio; ?> - <?php echo $hora_fin; ?>** (<?php echo $display_date; ?>)
                            </label>
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" class="btn-success large-btn" style="width: 100%; margin-top: 20px;">
                        <i class="fas fa-save"></i> Confirmar Nuevo Horario
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

    </div>
    
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializa Flatpickr en el campo con ID 'fecha_filtro'
            flatpickr("#fecha_filtro", {
                // Habilita el modo 'wrap' para permitir el icono de calendario
                wrap: true, 
                // Opciones de configuración
                dateFormat: "Y-m-d", // Formato de fecha que se envía al servidor
                locale: "es",        // Configura el idioma a español
                // Otras opciones para mejor experiencia de usuario:
                minDate: "today",    // No permitir seleccionar fechas pasadas
                disableMobile: true  // Forzar la interfaz de Flatpickr en móviles
            });
        });
    </script>
</body>
</html>