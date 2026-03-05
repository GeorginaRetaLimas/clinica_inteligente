<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clínica Asistente</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            /* Celeste tones */
            --bs-primary: #0dcaf0;
            --bs-primary-rgb: 13, 202, 240;
            --bs-body-bg: #f8fcfd;
        }
        .bg-celeste {
            background-color: #0dcaf0 !important;
            color: #fff;
        }
        .btn-celeste {
            background-color: #0dcaf0;
            color: white;
            border: none;
        }
        .btn-celeste:hover {
            background-color: #0baccc;
            color: white;
        }
        body {
            background-color: var(--bs-body-bg);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .navbar-custom {
            background-color: #ffffff;
            border-bottom: 2px solid #0dcaf0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .main-content {
            flex: 1;
        }
    </style>
</head>
<body>

<?php if (isset($_SESSION['medico_id'])): ?>
<nav class="navbar navbar-expand-lg navbar-custom mb-4">
    <div class="container">
        <a class="navbar-brand text-info fw-bold" href="/clinica_app/index.php">
            <i class="bi bi-hospital"></i> ClínicaIA
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/clinica_app/index.php">Inicio</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/clinica_app/modulos/pacientes.php">Pacientes</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/clinica_app/modulos/citas.php">Citas</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/clinica_app/modulos/expedientes.php">Expedientes</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-info fw-semibold" href="/clinica_app/modulos/chat_ia.php">
                        <i class="bi bi-robot"></i> Asistente IA
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['medico_nombre'] ?? 'Usuario'); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item text-danger" href="/clinica_app/logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<?php
endif; ?>

<div class="container main-content">
