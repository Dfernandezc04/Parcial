<?php
// agendar.php

include_once 'includes/db_connect.php'; 
include_once 'includes/secure_session.php'; 
check_session(); // Asegura que solo usuarios logeados accedan

$user_id = $_SESSION['user_id'];
$institucion_actual = $_SESSION['institucion'];
$especialidad_elegida = $_GET['especialidad'] ?? '';
$fecha_elegida = $_GET['fecha'] ?? date('Y-m-d'); // Por defecto, hoy

$mensaje = '';
$horarios_disponibles = []; // Ahora contendrá slots de 30 minutos
$especialidades = []; // Para llenar el filtro

// 1. Obtener la lista de ESPECIALIDADES disponibles en la institución actual
$sql_esp = "SELECT DISTINCT E.especialidad FROM Disponibilidad D 
            JOIN Especialistas E ON D.especialista_id = E.id 
            WHERE D.institucion = ?";
$stmt_esp = $conn->prepare($sql_esp);
$stmt_esp->bind_param("s", $institucion_actual);
$stmt_esp->execute();
$result_esp = $stmt_esp->get_result();
while ($row = $result_esp->fetch_assoc()) {
    $especialidades[] = $row['especialidad'];
}
$stmt_esp->close();


// 2. Lógica para mostrar HORARIOS DISPONIBLES (Divididos en slots de 30 min)
if (!empty($especialidad_elegida) && !empty($fecha_elegida)) {
    
    // A. Primero, obtenemos TODAS las citas ya reservadas para este día/institución
    $horarios_reservados = [];
    $sql_reservas = "SELECT especialista_id, fecha_hora FROM Citas 
                    WHERE institucion = ? AND DATE(fecha_hora) = ?";
    $stmt_reservas = $conn->prepare($sql_reservas);
    $stmt_reservas->bind_param("ss", $institucion_actual, $fecha_elegida);
    $stmt_reservas->execute();
    $result_reservas = $stmt_reservas->get_result();
    
    while ($reserva = $result_reservas->fetch_assoc()) {
        // Clave: ID del especialista - Hora exacta (ej: '4-08:00')
        $clave = $reserva['especialista_id'] . '-' . date('H:i', strtotime($reserva['fecha_hora']));
        $horarios_reservados[$clave] = true;
    }
    $stmt_reservas->close();


    // B. Obtenemos los bloques de Disponibilidad AÚN ABIERTOS
    $sql_disp = "
        SELECT 
            D.fecha_hora_inicio, 
            D.fecha_hora_fin, 
            E.id AS especialista_id,
            E.nombre AS especialista_nombre 
        FROM Disponibilidad D
        JOIN Especialistas E ON D.especialista_id = E.id
        WHERE E.especialidad = ? 
          AND D.institucion = ? 
          AND DATE(D.fecha_hora_inicio) = ? 
        ORDER BY D.fecha_hora_inicio ASC";

    $stmt_disp = $conn->prepare($sql_disp);
    $stmt_disp->bind_param("sss", $especialidad_elegida, $institucion_actual, $fecha_elegida);
    $stmt_disp->execute();
    $result_disp = $stmt_disp->get_result();

    if ($result_disp->num_rows > 0) {
        // C. Dividir los bloques en slots de 30 minutos y filtrar los reservados
        while ($row = $result_disp->fetch_assoc()) {
            $inicio = strtotime($row['fecha_hora_inicio']);
            $fin = strtotime($row['fecha_hora_fin']);
            $especialista_id = $row['especialista_id'];
            
            // Iterar cada 30 minutos
            for ($tiempo = $inicio; $tiempo < $fin; $tiempo += (30 * 60)) { 
                $hora_slot = date('H:i', $tiempo);
                $hora_cita_db = date('Y-m-d H:i:s', $tiempo);
                
                $clave_reserva = $especialista_id . '-' . $hora_slot;
                
                // Si el slot NO está en la lista de reservados, es un horario disponible
                if (!isset($horarios_reservados[$clave_reserva])) {
                    $horarios_disponibles[] = [
                        'slot_inicio' => $hora_slot,
                        'slot_fin' => date('H:i', $tiempo + (30 * 60)),
                        'especialista_id' => $especialista_id,
                        'especialista_nombre' => $row['especialista_nombre'],
                        'fecha_cita_db' => $hora_cita_db // Formato completo para la inserción
                    ];
                }
            }
        }
    } else {
        $mensaje = "No hay disponibilidad para la fecha y especialidad seleccionada.";
    }

    $stmt_disp->close();
}


// 3. Lógica para GUARDAR la CITA (Al enviar el formulario de agendar)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion']) && $_POST['accion'] == 'agendar') {
    
    // hora_inicio ahora es el valor completo (Y-m-d H:i:s)
    $fecha_cita = $_POST['hora_inicio']; 
    $especialista_id = (int)$_POST['especialista_id'];
    $motivo = $conn->real_escape_string($_POST['motivo']);
    
    $sql_insert = "INSERT INTO Citas (paciente_id, especialista_id, fecha_hora, motivo, institucion, estado) 
                   VALUES (?, ?, ?, ?, ?, 'programada')";
    
    $stmt_insert = $conn->prepare($sql_insert);
    
    // 🚨 LÍNEA 140 (aproximadamente): Usamos 'iisss' (dos integers, tres strings)
    $stmt_insert->bind_param("iisss", $user_id, $especialista_id, $fecha_cita, $motivo, $institucion_actual);

    try {
        if ($stmt_insert->execute()) {
            $mensaje = "<div class='success-message'>✅ Cita programada exitosamente para el " . date('d/m/Y H:i', strtotime($fecha_cita)) . "</div>";
            // Lógica de auditoría
            $conn->query("INSERT INTO RegistrosActividad (usuario_id, accion) VALUES ('$user_id', 'Agendó cita para $fecha_cita')");

        } else {
          // Error de clave única (si ya tiene una cita a esa hora o el slot está ocupado)
            if ($conn->errno == 1062) { 
                 // Mensaje genérico para conflicto de reserva
                 $mensaje = "<div class='error-message'>🛑 **Lo sentimos, esta cita ya ha sido reservada.** Por favor, elija otro horario.</div>";
            } else {
                 $mensaje = "<div class='error-message'>❌ Error al agendar la cita. Intente de nuevo.</div>";
            }
        }
    } catch (Exception $e) {
        $mensaje = "<div class='error-message'>❌ Error en la base de datos: " . $e->getMessage() . "</div>";
    }

    $stmt_insert->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agendar Cita en <?php echo $institucion_actual; ?></title>
    <link rel="stylesheet" href="style.css"> 
    <style>
        /* Estilos adicionales para los slots, si no están en style.css */
        .availability-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }
        .slot-card {
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 15px;
            width: 280px; /* Ancho fijo para las tarjetas de slot */
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            background-color: #f9f9f9;
        }
        .slot-time {
            font-size: 1.2em;
            font-weight: bold;
            color: #004d99;
            margin-bottom: 5px;
        }
        .slot-card textarea {
            width: 100%;
            height: 60px;
            margin: 10px 0;
            padding: 5px;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Agendar Cita | <?php echo $institucion_actual; ?></h1>
        <a href="dashboard.php" class="btn-secondary">← Volver al Panel</a>
    </div>

    <div class="container">
        <?php echo $mensaje; ?>

        <form action="agendar.php" method="GET" class="filter-form">
            <h3>Filtrar Disponibilidad</h3>
            <input type="hidden" name="institucion" value="<?php echo $institucion_actual; ?>">

            <label for="fecha">Fecha:</label>
            <input type="date" id="fecha" name="fecha" value="<?php echo $fecha_elegida; ?>" required>

            <label for="especialidad">Especialidad:</label>
            <select id="especialidad" name="especialidad" required>
                <option value="">Seleccione...</option>
                <?php foreach ($especialidades as $esp): ?>
                    <option value="<?php echo $esp; ?>" <?php echo ($esp == $especialidad_elegida) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($esp); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="btn-primary">Buscar Horarios</button>
        </form>

        <hr>

        <?php if (!empty($horarios_disponibles)): ?>
            <h2>Horarios Disponibles para el <?php echo date('d/m/Y', strtotime($fecha_elegida)); ?></h2>
            
                        <div class="availability-grid">
                
                <?php foreach ($horarios_disponibles as $slot): ?>
                <div class="slot-card">
                                        <p class="slot-time"><?php echo $slot['slot_inicio'] . ' - ' . $slot['slot_fin']; ?></p>
                    <p>Especialista: **<?php echo htmlspecialchars($slot['especialista_nombre']); ?>**</p>
                    <p>Especialidad: **<?php echo htmlspecialchars($especialidad_elegida); ?>**</p>

                    <form action="agendar.php?especialidad=<?php echo $especialidad_elegida; ?>&fecha=<?php echo $fecha_elegida; ?>" method="POST">
                        <input type="hidden" name="accion" value="agendar">
                        
                                                <input type="hidden" name="hora_inicio" value="<?php echo $slot['fecha_cita_db']; ?>"> 
                        
                        <input type="hidden" name="especialista_id" value="<?php echo $slot['especialista_id']; ?>">
                        
                        <textarea name="motivo" placeholder="Motivo de la cita (Máx. 100 caracteres)" maxlength="100" required></textarea>
                        
                        <button type="submit" class="btn-success">Reservar este Slot</button>
                    </form>
                </div>
                <?php endforeach; ?>

            </div>
        <?php elseif ($especialidad_elegida): ?>
             <p class="info-message">No se encontraron horarios disponibles para **<?php echo htmlspecialchars($especialidad_elegida); ?>** el **<?php echo date('d/m/Y', strtotime($fecha_elegida)); ?>** en **<?php echo $institucion_actual; ?>**.</p>
        <?php endif; ?>

    </div>
</body>
</html>