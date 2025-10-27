<?php
// admin/dashboard.php (Panel de control del Administrador)

include_once '../includes/db_connect.php'; 
include_once '../includes/secure_session.php'; 

// 1. Verificar Rol de Administrador
// (Asumo que check_admin() existe en secure_session.php)
if (function_exists('check_admin')) {
    check_admin(); 
} else {
    // Fallback si no existe la función
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['rol'] !== 'admin') {
        header('Location: ../login.php');
        exit;
    }
}

$nombre_admin = $_SESSION['nombre'];
// Inicializa la institución desde la sesión o usa 'CSS' por defecto
$institucion_actual = $_SESSION['institucion'] ?? 'CSS'; 

// 2. Manejo del cambio de institución
if (isset($_GET['institucion_elegida'])) {
    $nueva_institucion = $conn->real_escape_string($_GET['institucion_elegida']);
    $_SESSION['institucion'] = $nueva_institucion;
    $institucion_actual = $nueva_institucion;
    header("Location: dashboard.php");
    exit;
}

// 3. Clases de Estilo Dinámicas
$header_class = 'header-css'; 
if ($institucion_actual == 'Instituto Oncológico') {
    $header_class = 'header-oncologico'; 
} elseif ($institucion_actual == 'MINSA') {
    $header_class = 'header-minsa'; 
}

// 4. Obtener lista de TODOS los Especialistas (Lógica de tu código original)
$especialistas = [];
$sql_esp = "SELECT id, nombre, especialidad FROM Especialistas ORDER BY especialidad, nombre";
$result_esp = $conn->query($sql_esp);

while ($row = $result_esp->fetch_assoc()) {
    $especialistas[] = $row;
}

// 5. Obtener TODAS las instituciones asociadas a cada especialista (Lógica de tu código original)
$instituciones_por_especialista = [];
$disponibilidad_check_current = [];

$sql_all_disp = "SELECT especialista_id, GROUP_CONCAT(DISTINCT institucion SEPARATOR ', ') AS lista_instituciones 
                FROM Disponibilidad 
                GROUP BY especialista_id";
$result_all_disp = $conn->query($sql_all_disp);

while ($row = $result_all_disp->fetch_assoc()) {
    $esp_id = $row['especialista_id'];
    $instituciones_por_especialista[$esp_id] = $row['lista_instituciones'];
    
    if (strpos($row['lista_instituciones'], $institucion_actual) !== false) {
        $disponibilidad_check_current[$esp_id] = TRUE;
    }
}

$conn->close();

$instituciones_disponibles = ['CSS', 'MINSA', 'Instituto Oncológico'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Admin | <?php echo htmlspecialchars($institucion_actual); ?></title>
    <link rel="stylesheet" href="../style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Estilos Dinámicos (Asumiendo que están en tu style.css o un bloque style) */
        .header-css { background-color: #004d99 !important; } 
        .header-minsa { background-color: #28a745 !important; } 
        .header-oncologico { background-color: #e83e8c !important; } 
        
        /* Estilos para los botones y contenedores (Mejorados) */
        .container { max-width: 1200px; margin: 30px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .large-btn {
            padding: 15px 25px;
            font-size: 1.1em;
            text-decoration: none;
            border-radius: 8px;
            text-align: center;
            flex-grow: 1;
            min-width: 200px; /* Asegura que no sean demasiado estrechos */
            transition: background-color 0.2s;
        }
        .btn-success { background-color: #28a745; color: white; }
        .btn-info { background-color: #17a2b8; color: white; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-secondary { background-color: #6c757d; color: white; }
        
        /* Estilos de tabla y estado (Mantenidos) */
        .status-ok { color: green; font-weight: bold; }
        .status-pending { color: orange; font-weight: bold; }
        .data-table th:nth-child(4), 
        .data-table td:nth-child(4) { 
            width: 25%; 
        }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="header <?php echo $header_class; ?>">
        <h1>Panel de Administración (<?php echo htmlspecialchars($institucion_actual); ?>)</h1>
        <div class="user-info">
            <span>Bienvenido, <?php echo htmlspecialchars($nombre_admin); ?> (Admin)</span>
            <a href="../logout.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        
        <div class="institution-selector">
            <h3>Institución Seleccionada: <span class="current-institution"><?php echo htmlspecialchars($institucion_actual); ?></span></h3>
            
            <form action="dashboard.php" method="GET" class="inline-form">
                <label for="institucion_elegida">Cambiar a:</label>
                <select id="institucion_elegida" name="institucion_elegida" required>
                    <?php foreach ($instituciones_disponibles as $inst): ?>
                        <option value="<?php echo $inst; ?>" <?php echo ($inst == $institucion_actual) ? 'selected' : ''; ?>>
                            <?php echo $inst; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-primary">Cambiar Vista</button>
            </form>
        </div>
        
        <hr>
        
        <h2>Herramientas de Administración</h2>

       <hr>

<h2>Herramientas de Administración</h2>

<div class="action-buttons">
    
    <a href="gestion_pacientes.php" class="btn-success large-btn">
        <i class="fas fa-users"></i> Gestión de Pacientes
    </a>

    <a href="administrar_citas.php" class="large-btn" style="background-color: #ffc107; color: black;">
        <i class="fas fa-calendar-check"></i> **Gestión de Citas**
    </a>

    <a href="gestion_disponibilidad.php" class="btn-info large-btn">
        <i class="fas fa-calendar-alt"></i> Consultar Citas Disponibles
    </a>
    
    <a href="gestionar_especialistas.php" class="btn-primary large-btn">
         <i class="fas fa-user-md"></i> Crear/Editar Especialistas
    </a>

    <a href="gestionar_disponibilidad.php" class="btn-secondary large-btn">
        <i class="fas fa-clock"></i> Asignar Horarios en <?php echo htmlspecialchars($institucion_actual); ?>
    </a>
</div>

<hr>
        
        <hr>

        <h3>Todos los Especialistas Registrados (<?php echo count($especialistas); ?>)</h3>
        
        <?php if (empty($especialistas)): ?>
            <p class="info-message">No hay especialistas registrados aún. Use el botón "Crear/Editar Especialistas".</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Especialidad</th>
                        <th>Instituciones Asignadas</th> 
                        <th>Estado en <?php echo htmlspecialchars($institucion_actual); ?></th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($especialistas as $esp): 
                        $esp_id = $esp['id'];
                        $instituciones_str = $instituciones_por_especialista[$esp_id] ?? 'Ninguna';
                        $tiene_horario = isset($disponibilidad_check_current[$esp_id]);
                        $estado_clase = $tiene_horario ? 'status-ok' : 'status-pending';
                        $estado_texto = $tiene_horario ? 'Con Horario' : 'Pendiente';
                    ?>
                        <tr>
                            <td><?php echo $esp['id']; ?></td>
                            <td><?php echo htmlspecialchars($esp['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($esp['especialidad']); ?></td>
                            <td><?php echo htmlspecialchars($instituciones_str); ?></td> 
                            <td class="<?php echo $estado_clase; ?>"><?php echo $estado_texto; ?></td>
                            <td>
                                <a href="gestionar_especialistas.php?edit_id=<?php echo $esp['id']; ?>" class="btn-small">Editar Info</a>
                                <a href="gestionar_disponibilidad.php?esp_id=<?php echo $esp['id']; ?>" class="btn-small btn-info">Asignar Horario</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>
</body>
</html>