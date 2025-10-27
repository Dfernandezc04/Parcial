<?php
// admin/gestionar_disponibilidad.php

include_once '../includes/db_connect.php'; 
include_once '../includes/secure_session.php'; 
check_admin();

$institucion_actual = $_SESSION['institucion'] ?? 'CSS';
$especialista_id_elegido = $_GET['esp_id'] ?? null;
$especialistas = [];
$disponibilidad_actual = [];
$fechas_ocupadas = []; 
$especialista_nombre = 'N/A';

// 🚨 1. MANEJO DE MENSAJES DE SESIÓN (Flash Messages)
$mensaje = '';
if (isset($_SESSION['admin_message'])) {
    $mensaje = $_SESSION['admin_message'];
    unset($_SESSION['admin_message']); // Borrar el mensaje después de mostrarlo
}

// 2. Obtener la lista de todos los especialistas
$sql_esp = "SELECT id, nombre, especialidad FROM Especialistas ORDER BY nombre";
$result_esp = $conn->query($sql_esp);
while ($row = $result_esp->fetch_assoc()) {
    $especialistas[] = $row;
}

// 3. Lógica para OBTENER FECHAS OCUPADAS Y DISPONIBILIDAD ACTUAL
if ($especialista_id_elegido) {
    // A. Obtener nombre del especialista
    $sql_nombre = "SELECT nombre FROM Especialistas WHERE id = ?";
    $stmt_nombre = $conn->prepare($sql_nombre);
    $stmt_nombre->bind_param("i", $especialista_id_elegido);
    $stmt_nombre->execute();
    $especialista_nombre = $stmt_nombre->get_result()->fetch_assoc()['nombre'] ?? 'Desconocido';
    $stmt_nombre->close();

    // B. Obtener TODOS los bloques de disponibilidad del especialista (para detectar conflictos)
    $sql_disp = "SELECT id, DATE(fecha_hora_inicio) AS fecha, fecha_hora_inicio, fecha_hora_fin, institucion 
                 FROM Disponibilidad 
                 WHERE especialista_id = ? 
                 ORDER BY fecha_hora_inicio ASC";
    $stmt_disp = $conn->prepare($sql_disp);
    $stmt_disp->bind_param("i", $especialista_id_elegido);
    $stmt_disp->execute();
    $result_disp = $stmt_disp->get_result();

    while ($row = $result_disp->fetch_assoc()) {
        $fecha_str = $row['fecha'];
        
        $fechas_ocupadas[$fecha_str] = $row['institucion']; 

        // Solo agregar a la lista de "Disponibilidad Actual" si pertenece a esta institución
        if ($row['institucion'] === $institucion_actual) {
            $disponibilidad_actual[] = $row;
        }
    }
    $stmt_disp->close();
}


// 4. Lógica para PROCESAR el formulario POST (CREAR NUEVA DISPONIBILIDAD)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion']) && $_POST['accion'] == 'crear') {
    
    $especialista_id = (int)$_POST['especialista_id'];
    $fecha = $_POST['fecha'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fin = $_POST['hora_fin'];
    
    $fecha_hora_inicio = $fecha . ' ' . $hora_inicio . ':00';
    $fecha_hora_fin = $fecha . ' ' . $hora_fin . ':00';
    
    $mensaje_temp = '';
    
    // VALIDACIÓN 1: Conflicto de tiempo
    if (strtotime($fecha_hora_inicio) >= strtotime($fecha_hora_fin)) {
        $mensaje_temp = "<div class='error-message'>🛑 Error: La hora de inicio debe ser anterior a la hora de fin.</div>";
    } 
    // VALIDACIÓN 2: Conflicto de institución o doble asignación en la misma fecha
    elseif (isset($fechas_ocupadas[$fecha])) {
        $institucion_conflicto = $fechas_ocupadas[$fecha];
        
        if ($institucion_conflicto !== $institucion_actual) {
             $mensaje_temp = "<div class='error-message'>🛑 Error: El Dr. ya tiene asignada la fecha ({$fecha}) en la institución **{$institucion_conflicto}**.</div>";
        } else {
             $mensaje_temp = "<div class='error-message'>🛑 Error: El Dr. ya tiene un bloque asignado el {$fecha} en **{$institucion_actual}**.</div>";
        }
    }
    else {
        // Inserción en la tabla Disponibilidad
        $sql = "INSERT INTO Disponibilidad (especialista_id, fecha_hora_inicio, fecha_hora_fin, institucion) 
                VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isss", $especialista_id, $fecha_hora_inicio, $fecha_hora_fin, $institucion_actual);

        if ($stmt->execute()) {
            $mensaje_temp = "<div class='success-message'>✅ Bloque asignado exitosamente para {$institucion_actual}.</div>";
            $conn->query("INSERT INTO RegistrosActividad (usuario_id, accion) VALUES ('{$_SESSION['user_id']}', 'Asignó disponibilidad a ID {$especialista_id}')");
        } else {
            $mensaje_temp = "<div class='error-message'>❌ Error al asignar disponibilidad: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
    
    // 🚨 ALMACENAR MENSAJE Y REDIRIGIR
    $_SESSION['admin_message'] = $mensaje_temp;
    header("Location: gestion_disponibilidad.php?esp_id={$especialista_id}");
    exit;
}

// 5. Lógica para ELIMINAR (Mantenida)
if (isset($_GET['delete_id']) && $especialista_id_elegido) {
    $delete_id = (int)$_GET['delete_id'];
    
    $sql_del = "DELETE FROM Disponibilidad WHERE id = ? AND institucion = ?";
    $stmt_del = $conn->prepare($sql_del);
    $stmt_del->bind_param("is", $delete_id, $institucion_actual);
    
    $mensaje_temp = '';
    if ($stmt_del->execute()) {
        $mensaje_temp = "<div class='success-message'>🗑️ Bloque de disponibilidad eliminado exitosamente.</div>";
        $conn->query("INSERT INTO RegistrosActividad (usuario_id, accion) VALUES ('{$_SESSION['user_id']}', 'Eliminó disponibilidad ID: {$delete_id}')");
    } else {
         $mensaje_temp = "<div class='error-message'>❌ Error al eliminar el bloque.</div>";
    }
    $stmt_del->close();
    
    // 🚨 ALMACENAR MENSAJE Y REDIRIGIR
    $_SESSION['admin_message'] = $mensaje_temp;
    header("Location: gestion_disponibilidad.php?esp_id={$especialista_id_elegido}");
    exit;
}


// 6. Generar lista de días disponibles (UX MEJORADA)
$dias_futuros = [];
$today = new DateTime();

for ($i = 0; $i < 30; $i++) { 
    $date = clone $today;
    $date->add(new DateInterval("P{$i}D"));
    $fecha_str = $date->format('Y-m-d');
    
    // Solo agregamos la fecha si NO está en el array de fechas ocupadas (conflicto)
    if (!isset($fechas_ocupadas[$fecha_str])) {
        $dias_futuros[$fecha_str] = $date->format('d/m/Y (l)');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Disponibilidad | <?php echo htmlspecialchars($institucion_actual); ?></title>
    <link rel="stylesheet" href="../style.css"> 
</head>
<body>
    <div class="header">
        <h1>Asignar Horarios en <?php echo htmlspecialchars($institucion_actual); ?></h1>
        <a href="dashboard.php" class="btn-secondary">← Volver al Panel</a>
    </div>

    <div class="container">
        <?php echo $mensaje; // Mostrar mensajes de éxito o error (AHORA FUNCIONA) ?>

        <h2>1. Seleccionar Especialista</h2>
        <form action="gestion_disponibilidad.php" method="GET" class="inline-form">
            <label for="esp_id">Especialista:</label>
            <select id="esp_id" name="esp_id" required>
                <option value="">Seleccione...</option>
                <?php foreach ($especialistas as $esp): ?>
                    <option value="<?php echo $esp['id']; ?>" <?php echo ($esp['id'] == $especialista_id_elegido) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($esp['nombre']) . ' (' . htmlspecialchars($esp['especialidad']) . ')'; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-primary">Ver Horarios</button>
        </form>
        
        <hr>

        <?php if ($especialista_id_elegido): ?>
            
            <h3>2. Asignar Nuevo Bloque para <?php echo htmlspecialchars($especialista_nombre); ?></h3>

            <form action="gestion_disponibilidad.php" method="POST" class="filter-form">
                <input type="hidden" name="accion" value="crear">
                <input type="hidden" name="especialista_id" value="<?php echo htmlspecialchars($especialista_id_elegido); ?>">
                
                <div>
                    <label for="fecha">Fecha Disponible:</label>
                    <select id="fecha" name="fecha" required>
                        <option value="">-- Seleccione una fecha --</option>
                        <?php 
                        if (empty($dias_futuros)) {
                            echo '<option value="" disabled>No hay fechas libres en los próximos 30 días.</option>';
                        } else {
                            foreach ($dias_futuros as $fecha_val => $fecha_label) {
                                echo "<option value='{$fecha_val}'>{$fecha_label}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                
                <div>
                    <label for="hora_inicio">Hora Inicio:</label>
                    <input type="time" id="hora_inicio" name="hora_inicio" required value="08:00">
                </div>
                
                <div>
                    <label for="hora_fin">Hora Fin:</label>
                    <input type="time" id="hora_fin" name="hora_fin" required value="12:00">
                </div>
                
                <button type="submit" class="btn-success" <?php echo empty($dias_futuros) ? 'disabled' : ''; ?>>Guardar Bloque</button>
            </form>

            <hr>

            <h3>3. Disponibilidad Actual en <?php echo htmlspecialchars($institucion_actual); ?></h3>

            <?php if (empty($disponibilidad_actual)): ?>
                <p class="info-message">Este especialista no tiene bloques de disponibilidad asignados en **<?php echo htmlspecialchars($institucion_actual); ?>**.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Hora Inicio</th>
                            <th>Hora Fin</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($disponibilidad_actual as $disp): ?>
                            <tr>
                                <td><?php echo date('d/m/Y (l)', strtotime($disp['fecha_hora_inicio'])); ?></td>
                                <td><?php echo date('H:i', strtotime($disp['fecha_hora_inicio'])); ?></td>
                                <td><?php echo date('H:i', strtotime($disp['fecha_hora_fin'])); ?></td>
                                <td>
                                    <a href="gestion_disponibilidad.php?delete_id=<?php echo $disp['id']; ?>&esp_id=<?php echo htmlspecialchars($especialista_id_elegido); ?>" 
                                       class="btn-danger small-btn" 
                                       onclick="return confirm('¿Está seguro de eliminar este bloque?')">Eliminar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <?php // Mostrar conflictos de otras instituciones
            $conflictos_externos = array_filter($fechas_ocupadas, function($inst) use ($institucion_actual) {
                return $inst !== $institucion_actual;
            });
            ?>
            <?php if (!empty($conflictos_externos)): ?>
                <div class="error-message">
                    <h4>Fechas no disponibles (Conflicto con otra Institución):</h4>
                    <ul>
                        <?php foreach ($conflictos_externos as $fecha => $inst_conflicto): ?>
                            <li>**<?php echo date('d/m/Y', strtotime($fecha)); ?>**: Ocupado en **<?php echo htmlspecialchars($inst_conflicto); ?>**.</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

        <?php elseif (!empty($especialistas)): ?>
            <p class="info-message">Por favor, seleccione un especialista para asignar horarios.</p>
        <?php endif; ?>

    </div>
</body>
</html>