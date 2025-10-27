<?php
// index.php (Página de inicio - Selector de Instituciones)

include_once 'includes/db_connect.php'; 
include_once 'includes/secure_session.php'; 

// Si ya está logeado, lo redirigimos a su dashboard
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if ($_SESSION['rol'] == 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

// Definición de las instituciones
$instituciones = [
    'MINSA' => [
        'titulo' => 'MINSA',
        'subtitulo' => 'Ministerio de Salud',
        'descripcion' => 'Sistema de citas para hospitales y centros de salud del MINSA.',
        'color_class' => 'card-minsa',
        'icono' => 'fa-hospital' // Icono más representativo del MINSA (verde)
    ],
    'CSS' => [
        'titulo' => 'CSS',
        'subtitulo' => 'Caja de Seguro Social',
        'descripcion' => 'Sistema de citas para policlínicas y hospitales de la CSS.',
        'color_class' => 'card-css',
        'icono' => 'fa-plus-square' // Icono de cruz (azul)
    ],
    'Oncologico' => [
        'titulo' => 'Instituto Oncológico',
        'subtitulo' => 'Instituto Oncológico Nacional',
        'descripcion' => 'Sistema de citas especializadas en oncología.',
        'color_class' => 'card-oncologico',
        'icono' => 'fa-heart' // Icono de corazón (rosa/púrpura)
    ]
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Citas Médicas | Panamá</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="style.css"> 
    
    <style>
        /* ==================== ESTILOS GENERALES ==================== */
        body { 
            background-color: #f4f7f9; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            color: #333;
        } 
        
        /* ==================== BARRA SUPERIOR ==================== */
        .top-bar {
            display: flex;
            justify-content: space-between; 
            align-items: center;
            padding: 15px 0; 
            background-color: #007bff;
            color: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        /* Logo / nombre del sistema (Alineación Izquierda) */
        .system-branding {
            padding-left: 40px;
        }

        .system-branding h1 {
            font-size: 1.6em;
            color: white;
            margin: 0;
            font-weight: 700;
        }

        .system-branding p {
            font-size: 0.85em;
            color: rgba(255, 255, 255, 0.9);
            margin-top: -5px;
        }

        /* Botón Iniciar Sesión (Alineación Derecha y Pequeño) */
        .btn-login {
            text-decoration: none;
            background-color: white;
            color: #007bff;
            border: none;
            border-radius: 4px 0 0 4px;
            padding: 8px 20px;
            font-size: 0.9em;
            font-weight: 600;
            transition: background-color 0.2s, color 0.2s;
            margin-left: auto;
            margin-right: 0;
        }

        .btn-login:hover {
            background-color: #0056b3;
            color: white;
        }

        /* ==================== CONTENIDO PRINCIPAL ==================== */
        .header-title { 
            text-align: center; 
            margin-top: 50px; 
            padding: 0 20px;
        }

        .header-title h2 {
            font-size: 2.2em;
            font-weight: 700;
            color: #343a40;
            margin-bottom: 15px;
        }

        .header-title p {
            max-width: 700px;
            margin: 0px auto 40px;
            color: #6c757d;
            font-size: 1.1em;
            line-height: 1.6;
        }

        /* ==================== TARJETAS DE INSTITUCIONES ==================== */
        .institution-selector-grid {
            display: flex;
            justify-content: center;
            flex-wrap: wrap; 
            gap: 30px;
            margin-top: 20px;
            padding: 20px;
        }
        
        .institution-card {
            width: 300px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08); 
            padding: 30px;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            flex-direction: column;
            justify-content: flex-start; 
            min-height: 250px; 
        }

        .institution-card:hover {
            transform: translateY(-5px); 
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        }
        
        .institution-icon {
            font-size: 3.5em; 
            width: 80px;
            height: 80px;
            line-height: 80px;
            border-radius: 50%;
            margin: 0 auto 20px; 
            color: white; /* El ícono interior es blanco */
            display: inline-block;
        }
        
        /* Colores de íconos ESPECÍFICOS para que coincida con las imágenes */
        .card-minsa .institution-icon { 
            background-color: #28a745; /* Verde */
        } 
        .card-css .institution-icon { 
            background-color: #007bff; /* Azul */
        }  
        .card-oncologico .institution-icon { 
            background-color: #dc3545; /* Rojo/Rosa fuerte (usando un color de Bootstrap para un rojo de salud) */
        } 
        
        /* Texto dentro de las tarjetas */
        .institution-card h3 {
            font-size: 1.6em;
            color: #343a40;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .card-subtitle { 
            font-size: 0.95em; 
            color: #6c757d; 
            margin-bottom: 15px; 
        }

        .card-description { 
            font-size: 0.9em; 
            color: #888; 
            line-height: 1.5;
            min-height: 50px; 
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    
    <div class="top-bar">
        <div class="system-branding">
            <h1>Sistema de Citas Médicas</h1>
            <p>República de Panamá</p>
        </div>
        <a href="login.php" class="btn-login">Iniciar Sesión</a>
    </div>

    <div class="header-title">
        <h2>Bienvenido al Sistema de Gestión de Citas Médicas</h2>
        <p>Seleccione la institución de salud donde desea agendar su cita médica. Nuestro sistema le permite gestionar sus citas de manera rápida y segura.</p>
    </div>

    <div class="institution-selector-grid">
        
        <?php foreach ($instituciones as $inst): ?>
            <div class="institution-card <?php echo $inst['color_class']; ?>">
                <div class="card-content">
                    <div class="institution-icon">
                        <i class="fas <?php echo $inst['icono']; ?>"></i>
                    </div>
                    <h3><?php echo htmlspecialchars($inst['titulo']); ?></h3>
                    <p class="card-subtitle"><?php echo htmlspecialchars($inst['subtitulo']); ?></p>
                    <p class="card-description"><?php echo htmlspecialchars($inst['descripcion']); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</body>
</html>