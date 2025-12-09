<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Principal - Sistema de Vuelos</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <h1>✈️ Sistema de Vuelos</h1>
            <div class="nav-user">
                <span>Bienvenido, <?php echo htmlspecialchars($user['nombre']); ?></span>
                <a href="logout.php" class="btn btn-logout">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="container dashboard">
        <div class="dashboard-header">
            <h2>Panel de Control</h2>
            <p>Selecciona una opción para comenzar</p>
        </div>

        <div class="dashboard-grid">
            <a href="consultar_vuelos.php" class="dashboard-card">
                <div class="card-icon">🔍</div>
                <h3>Consultar Vuelos</h3>
                <p>Busca vuelos por horarios, tarifas y disponibilidad</p>
            </a>

            <a href="reservar_vuelos.php" class="dashboard-card">
                <div class="card-icon">📅</div>
                <h3>Reservar Vuelos</h3>
                <p>Realiza una nueva reserva de vuelo</p>
            </a>

            <a href="mis_reservas.php" class="dashboard-card">
                <div class="card-icon">📋</div>
                <h3>Mis Reservas</h3>
                <p>Consulta y gestiona tus reservas</p>
            </a>

            <a href="perfil.php" class="dashboard-card">
                <div class="card-icon">👤</div>
                <h3>Mi Perfil</h3>
                <p>Actualiza tu información personal</p>
            </a>
        </div>

        <div class="info-section">
            <h3>Información Importante</h3>
            <ul>
                <li>Recuerda llegar al aeropuerto 2 horas antes de tu vuelo nacional y 3 horas antes de vuelos internacionales</li>
                <li>Asegúrate de tener todos tus documentos de viaje en orden</li>
                <li>Puedes modificar o cancelar tus reservas hasta 24 horas antes del vuelo</li>
                <li>Para compras, necesitas tener una tarjeta de crédito válida registrada</li>
            </ul>
        </div>
    </div>
</body>
</html>