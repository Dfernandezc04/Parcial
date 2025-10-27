<?php
// admin/gestionar_especialistas.php

include_once '../includes/db_connect.php'; 
include_once '../includes/secure_session.php'; 
check_admin(); // Asegura el acceso solo para administradores

// NOTA: Se mantiene $institucion_actual solo para el header, pero NO para filtrar especialistas.
$institucion_actual = $_SESSION['institucion'] ?? 'CSS';
$mensaje = '';
$edit_id = $_GET['edit_id'] ?? null; // ID del especialista a editar
$especialista_data = ['nombre' => '', 'especialidad' => '']; 
$especialistas = []; // Array para almacenar la lista de especialistas

// -----------------------------------------------------------
// A. PROCESAR FORMULARIO POST (CREAR O ACTUALIZAR)
// -----------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $nombre = trim($_POST['nombre']);
    $especialidad = trim($_POST['especialidad']);
    $action_id = $_POST['action_id'];

    if (empty($nombre) || empty($especialidad)) {
        $mensaje = "<div class='error-message'>🛑 Error: Los campos Nombre y Especialidad son obligatorios.</div>";
    } else {
        
        // Determinar si es una INSERCIÓN o una ACTUALIZACIÓN
        if ($action_id == 'NUEVO') {
            // INSERTAR NUEVO ESPECIALISTA - SIN COLUMNA INSTITUCION
            $sql = "INSERT INTO Especialistas (nombre, especialidad) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $nombre, $especialidad); 
            
            if ($stmt->execute()) {
                $mensaje = "<div class='success-message'>✅ Especialista **{$nombre}** creado exitosamente.</div>";
                // ... (Auditoría) ...
            } else {
                $mensaje = "<div class='error-message'>❌ Error al crear el especialista: " . $stmt->error . "</div>";
            }
            $stmt->close();
            
        } else {
            // ACTUALIZAR ESPECIALISTA EXISTENTE - SIN COLUMNA INSTITUCION
            $sql = "UPDATE Especialistas SET nombre = ?, especialidad = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $nombre, $especialidad, $action_id); 

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $mensaje = "<div class='success-message'>✅ Especialista ID {$action_id} actualizado exitosamente.</div>";
                    // ... (Auditoría) ...
                } else {
                    $mensaje = "<div class='info-message'>ℹ️ No se realizaron cambios.</div>";
                }
            } else {
                $mensaje = "<div class='error-message'>❌ Error al actualizar el especialista: " . $stmt->error . "</div>";
            }
            $stmt->close();
        }
        // Redirigir para limpiar POST
        header("Location: gestionar_especialistas.php?mensaje=" . urlencode(strip_tags($mensaje)));
        exit;
    }
}

// -----------------------------------------------------------
// B. PROCESAR ACCIÓN ELIMINAR (GET)
// -----------------------------------------------------------
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    
    // ELIMINAR ESPECIALISTA - SIN COLUMNA INSTITUCION
    $sql_delete = "DELETE FROM Especialistas WHERE id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $delete_id);
    
    if ($stmt_delete->execute()) {
        if ($stmt_delete->affected_rows > 0) {
            $mensaje = "<div class='success-message'>🗑️ Especialista ID {$delete_id} eliminado exitosamente.</div>";
            // ... (Auditoría) ...
        } else {
            $mensaje = "<div class='error-message'>❌ Error: Especialista no encontrado.</div>";
        }
    } else {
        $mensaje = "<div class='error-message'>❌ Error al eliminar el especialista: " . $stmt_delete->error . "</div>";
    }
    $stmt_delete->close();
    // Redirigir para limpiar GET y mostrar el mensaje
    header("Location: gestionar_especialistas.php?mensaje=" . urlencode(strip_tags($mensaje)));
    exit;
}

// -----------------------------------------------------------
// C. CARGAR DATOS PARA EDICIÓN (GET)
// -----------------------------------------------------------
if ($edit_id) {
    // SELECCIONAR DATOS - SIN COLUMNA INSTITUCION
    $sql_load = "SELECT nombre, especialidad FROM Especialistas WHERE id = ?";
    $stmt_load = $conn->prepare($sql_load);
    $stmt_load->bind_param("i", $edit_id);
    $stmt_load->execute();
    $result = $stmt_load->get_result();

    if ($result->num_rows == 1) {
        $especialista_data = $result->fetch_assoc();
    } else {
        $mensaje = "<div class='error-message'>❌ Error: ID de especialista no encontrado.</div>";
        $edit_id = null;
    }
    $stmt_load->close();
}

// -----------------------------------------------------------
// D. OBTENER LISTA DE ESPECIALISTAS (Para la tabla)
// -----------------------------------------------------------
// LISTAR TODOS LOS ESPECIALISTAS - SIN FILTRO DE INSTITUCION
$sql_list = "SELECT id, nombre, especialidad FROM Especialistas ORDER BY nombre ASC";
$stmt_list = $conn->prepare($sql_list);
// NOTA: No se usa bind_param aquí ya que no hay filtros
$stmt_list->execute();
$result_list = $stmt_list->get_result();

while ($row = $result_list->fetch_assoc()) {
    $especialistas[] = $row;
}
$stmt_list->close();

$conn->close();

// Manejo de mensajes en el GET después de redirección
if (isset($_GET['mensaje'])) {
    $mensaje = urldecode($_GET['mensaje']);
}


$titulo_form = ($edit_id && $especialista_data['nombre']) ? 'Editar: ' . htmlspecialchars($especialista_data['nombre']) : 'Crear Nuevo Especialista';

// Clases de Estilo Dinámicas
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
    <title>Gestión de Especialistas | <?php echo htmlspecialchars($institucion_actual); ?></title>
    <link rel="stylesheet" href="../style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* (Tus estilos CSS aquí, incluyendo los estilos de tabla y botones) */
        .header-css { background-color: #004d99 !important; } 
        .header-minsa { background-color: #28a745 !important; } 
        .header-oncologico { background-color: #e83e8c !important; } 
        .container { max-width: 900px; margin: 30px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn-success { background-color: #28a745; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-secondary { background-color: #6c757d; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block;}
        .large-btn { width: 100%; margin-top: 10px; }
        .success-message, .error-message, .info-message { padding: 10px; margin-bottom: 20px; border-radius: 5px; }
        .success-message { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info-message { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }

        /* Estilos de Tabla y Botones de Acción */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .data-table th, .data-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .data-table th {
            background-color: #343a40;
            color: white;
            font-weight: 600;
        }
        .btn-action {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            margin-right: 5px;
            font-size: 0.9em;
            display: inline-block;
        }
        .btn-edit {
            background-color: #ffc107; /* Amarillo */
            color: #212529;
        }
        .btn-delete {
            background-color: #dc3545; /* Rojo */
            color: white;
        }
    </style>
    <script>
        function confirmarEliminacion(nombre, id) {
            return confirm(`¿Está seguro de que desea eliminar al especialista ${nombre} (ID: ${id})?\nEsta acción es irreversible.`);
        }
    </script>
</head>
<body>
    <div class="header <?php echo $header_class; ?>">
        <h1>Gestión de Especialistas</h1>
        <a href="dashboard.php" class="btn-secondary">← Volver al Panel</a>
    </div>

    <div class="container">
        <h2><?php echo htmlspecialchars($titulo_form); ?></h2>
        
        <?php echo $mensaje; // Mostrar mensajes de éxito o error ?>

        <form action="gestionar_especialistas.php" method="POST">
            
            <input type="hidden" name="action_id" value="<?php echo htmlspecialchars($edit_id ?? 'NUEVO'); ?>">

            <div class="form-group">
                <label for="nombre">Nombre Completo del Especialista (*):</label>
                <input type="text" id="nombre" name="nombre" required 
                        value="<?php echo htmlspecialchars($especialista_data['nombre']); ?>">
            </div>

            <div class="form-group">
                <label for="especialidad">Especialidad (*):</label>
                <input type="text" id="especialidad" name="especialidad" required 
                        value="<?php echo htmlspecialchars($especialista_data['especialidad']); ?>">
            </div>
            
            <button type="submit" class="btn-success large-btn">
                <i class="fas fa-save"></i> <?php echo $edit_id ? 'Guardar Cambios' : 'Registrar Especialista'; ?>
            </button>

            <?php if ($edit_id): ?>
                <a href="gestionar_especialistas.php" class="btn-secondary large-btn">
                    <i class="fas fa-times-circle"></i> Cancelar Edición
                </a>
            <?php endif; ?>

        </form>

        <hr style="margin-top: 30px; margin-bottom: 30px;">
        
        <h2>Todos los Especialistas Registrados (<?php echo count($especialistas); ?>)</h2>

        <?php if (empty($especialistas)): ?>
            <div class="info-message">ℹ️ No hay especialistas registrados.</div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre Completo</th>
                        <th>Especialidad</th>
                        <th>Acciones</th> </tr>
                </thead>
                <tbody>
                    <?php foreach ($especialistas as $esp): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($esp['id']); ?></td>
                            <td><?php echo htmlspecialchars($esp['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($esp['especialidad']); ?></td>
                            <td>
                                <a href="gestionar_especialistas.php?edit_id=<?php echo $esp['id']; ?>" class="btn-action btn-edit" title="Editar especialista">
                                    <i class="fas fa-pencil-alt"></i> Editar
                                </a>
                                
                                <a href="gestionar_especialistas.php?delete_id=<?php echo $esp['id']; ?>" 
                                   onclick="return confirmarEliminacion('<?php echo htmlspecialchars($esp['nombre']); ?>', <?php echo $esp['id']; ?>);" 
                                   class="btn-action btn-delete" title="Eliminar especialista">
                                    <i class="fas fa-trash-alt"></i> Eliminar
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