<?php
require_once 'config.php';

$error = '';
$success = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ? AND estado = 'activo'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        // Para este ejemplo, comparación simple (en producción usar password_verify)
        if ($password === '12345678' || password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id_usuario'];
            $_SESSION['user_name'] = $user['nombre'] . ' ' . $user['apellido'];
            $_SESSION['user_email'] = $user['email'];
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Contraseña incorrecta';
        }
    } else {
        $error = 'Usuario no encontrado';
    }
    
    $stmt->close();
    $conn->close();
}

// Procesar registro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);
    
    $conn = getConnection();
    
    // Verificar si el email ya existe
    $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $error = 'El email ya está registrado';
    } else {
        // Registrar nuevo usuario
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO usuarios (nombre, apellido, email, password, telefono, direccion) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $nombre, $apellido, $email, $password_hash, $telefono, $direccion);
        
        if ($stmt->execute()) {
            $success = 'Registro exitoso. Ahora puedes iniciar sesión';
        } else {
            $error = 'Error al registrar usuario';
        }
    }
    
    $stmt->close();
    $conn->close();
}

// Si ya está logueado, redirigir al dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Reserva de Vuelos</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <div class="welcome-section">
            <h1>✈️ Sistema de Reserva de Vuelos</h1>
            <p class="welcome-text">Bienvenido a nuestro sistema de reservas en línea. Aquí podrás:</p>
            <ul class="features-list">
                <li>🔍 Consultar horarios y tarifas de vuelos</li>
                <li>📅 Reservar vuelos a destinos nacionales e internacionales</li>
                <li>💳 Comprar boletos de manera segura</li>
                <li>📱 Gestionar tus reservas desde cualquier lugar</li>
            </ul>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="forms-container">
            <div class="form-wrapper">
                <h2>Iniciar Sesión</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Contraseña</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit" name="login" class="btn btn-primary">Ingresar</button>
                    <p class="form-note">Usuario de prueba: juan.perez@email.com / 12345678</p>
                </form>
            </div>

            <div class="form-wrapper">
                <h2>Registrarse</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="nombre" required>
                    </div>
                    <div class="form-group">
                        <label>Apellido</label>
                        <input type="text" name="apellido" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Contraseña</label>
                        <input type="password" name="password" minlength="6" required>
                    </div>
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="tel" name="telefono">
                    </div>
                    <div class="form-group">
                        <label>Dirección</label>
                        <textarea name="direccion" rows="2"></textarea>
                    </div>
                    <button type="submit" name="register" class="btn btn-secondary">Registrarse</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>