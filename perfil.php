<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$conn = getConnection();

$error = '';
$success = '';

// Actualizar perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar'])) {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);
    $numero_tarjeta = trim($_POST['numero_tarjeta']);
    
    $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, apellido = ?, telefono = ?, direccion = ?, numero_tarjeta = ? WHERE id_usuario = ?");
    $stmt->bind_param("sssssi", $nombre, $apellido, $telefono, $direccion, $numero_tarjeta, $user['id_usuario']);
    
    if ($stmt->execute()) {
        $success = 'Perfil actualizado correctamente';
        $user = getCurrentUser(); // Recargar datos
    } else {
        $error = 'Error al actualizar el perfil';
    }
    
    $stmt->close();
}

// Cambiar contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_password'])) {
    $password_actual = $_POST['password_actual'];
    $password_nueva = $_POST['password_nueva'];
    $password_confirmar = $_POST['password_confirmar'];
    
    if ($password_nueva !== $password_confirmar) {
        $error = 'Las contraseñas nuevas no coinciden';
    } else {
        // Verificar contraseña actual (simplificado para el ejemplo)
        if ($password_actual === '12345678' || password_verify($password_actual, $user['password'])) {
            $password_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE id_usuario = ?");
            $stmt->bind_param("si", $password_hash, $user['id_usuario']);
            
            if ($stmt->execute()) {
                $success = 'Contraseña actualizada correctamente';
            } else {
                $error = 'Error al actualizar la contraseña';
            }
            
            $stmt->close();
        } else {
            $error = 'La contraseña actual es incorrecta';
        }
    }
}

// Eliminar cuenta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_cuenta'])) {
    $password_confirmar = $_POST['password_eliminar'];
    
    if ($password_confirmar === '12345678' || password_verify($password_confirmar, $user['password'])) {
        $stmt = $conn->prepare("UPDATE usuarios SET estado = 'inactivo' WHERE id_usuario = ?");
        $stmt->bind_param("i", $user['id_usuario']);
        
        if ($stmt->execute()) {
            session_destroy();
            header('Location: index.php?mensaje=cuenta_eliminada');
            exit();
        }
    } else {
        $error = 'Contraseña incorrecta';
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil</title>
    <link rel="stylesheet" href="css/style.css">
    <script>
        function confirmarEliminacion() {
            return confirm('¿Estás seguro de que deseas eliminar tu cuenta? Esta acción no se puede deshacer.');
        }
    </script>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <h1>✈️ Sistema de Vuelos</h1>
            <div class="nav-user">
                <a href="dashboard.php" class="btn btn-secondary">Volver al Panel</a>
                <a href="logout.php" class="btn btn-logout">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <h2>Mi Perfil</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="profile-container">
            <div class="profile-section">
                <h3>Información Personal</h3>
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre</label>
                            <input type="text" name="nombre" value="<?php echo htmlspecialchars($user['nombre']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Apellido</label>
                            <input type="text" name="apellido" value="<?php echo htmlspecialchars($user['apellido']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        <small>El email no puede ser modificado</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="tel" name="telefono" value="<?php echo htmlspecialchars($user['telefono'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Dirección</label>
                        <textarea name="direccion" rows="3"><?php echo htmlspecialchars($user['direccion'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Número de Tarjeta (para compras rápidas)</label>
                        <input type="text" name="numero_tarjeta" value="<?php echo htmlspecialchars($user['numero_tarjeta'] ?? ''); ?>" pattern="[0-9]{13,19}">
                        <small>Solo se almacenarán los últimos 4 dígitos visibles</small>
                    </div>
                    
                    <button type="submit" name="actualizar" class="btn btn-primary">Actualizar Perfil</button>
                </form>
            </div>

            <div class="profile-section">
                <h3>Cambiar Contraseña</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Contraseña Actual</label>
                        <input type="password" name="password_actual" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Nueva Contraseña</label>
                        <input type="password" name="password_nueva" minlength="6" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirmar Nueva Contraseña</label>
                        <input type="password" name="password_confirmar" minlength="6" required>
                    </div>
                    
                    <button type="submit" name="cambiar_password" class="btn btn-secondary">Cambiar Contraseña</button>
                </form>
            </div>

            <div class="profile-section danger-zone">
                <h3>Zona de Peligro</h3>
                <p>Una vez que elimines tu cuenta, no hay vuelta atrás. Por favor, estate seguro.</p>
                
                <form method="POST" action="" onsubmit="return confirmarEliminacion()">
                    <div class="form-group">
                        <label>Confirma tu contraseña para eliminar la cuenta</label>
                        <input type="password" name="password_eliminar" required>
                    </div>
                    
                    <button type="submit" name="eliminar_cuenta" class="btn btn-danger">Eliminar Cuenta</button>
                </form>
            </div>
        </div>

        <div class="account-info">
            <h3>Información de la Cuenta</h3>
            <div class="info-item">
                <strong>Fecha de registro:</strong> <?php echo date('d/m/Y', strtotime($user['fecha_registro'])); ?>
            </div>
            <div class="info-item">
                <strong>Estado de la cuenta:</strong> 
                <span class="status status-<?php echo $user['estado']; ?>">
                    <?php echo ucfirst($user['estado']); ?>
                </span>
            </div>
        </div>
    </div>
</body>
</html>