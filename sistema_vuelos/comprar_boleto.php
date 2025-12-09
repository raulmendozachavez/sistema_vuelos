<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$conn = getConnection();

$error = '';
$success = '';

// Obtener reserva
$id_reserva = intval($_GET['reserva'] ?? 0);

$stmt = $conn->prepare("SELECT r.*, u.nombre, u.apellido, u.email, u.numero_tarjeta
                        FROM reservas r
                        JOIN usuarios u ON r.id_usuario = u.id_usuario
                        WHERE r.id_reserva = ? AND r.id_usuario = ?");
$stmt->bind_param("ii", $id_reserva, $user['id_usuario']);
$stmt->execute();
$reserva = $stmt->get_result()->fetch_assoc();

if (!$reserva) {
    header('Location: mis_reservas.php');
    exit();
}

// Obtener detalles de la reserva
$stmt = $conn->prepare("SELECT dr.*, v.numero_vuelo, v.fecha_salida, v.fecha_llegada,
                        co.nombre as ciudad_origen, co.codigo_iata as codigo_origen,
                        cd.nombre as ciudad_destino, cd.codigo_iata as codigo_destino,
                        a.nombre as aerolinea
                        FROM detalle_reserva dr
                        JOIN vuelos v ON dr.id_vuelo = v.id_vuelo
                        JOIN ciudades co ON v.id_ciudad_origen = co.id_ciudad
                        JOIN ciudades cd ON v.id_ciudad_destino = cd.id_ciudad
                        JOIN aerolineas a ON v.id_aerolinea = a.id_aerolinea
                        WHERE dr.id_reserva = ?");
$stmt->bind_param("i", $id_reserva);
$stmt->execute();
$detalles = $stmt->get_result();

// Procesar pago
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pagar'])) {
    $numero_tarjeta = $_POST['numero_tarjeta'];
    $nombre_tarjeta = $_POST['nombre_tarjeta'];
    $fecha_expiracion = $_POST['fecha_expiracion'];
    $cvv = $_POST['cvv'];
    
    $conn->begin_transaction();
    
    try {
        // Simular procesamiento de pago
        $ultimos4 = substr($numero_tarjeta, -4);
        
        // Registrar pago
        $stmt = $conn->prepare("INSERT INTO pagos (id_reserva, monto, numero_tarjeta_ultimos4, estado) VALUES (?, ?, ?, 'aprobado')");
        $stmt->bind_param("ids", $id_reserva, $reserva['total'], $ultimos4);
        $stmt->execute();
        
        // Actualizar estado de reserva
        $stmt = $conn->prepare("UPDATE reservas SET estado = 'pagada' WHERE id_reserva = ?");
        $stmt->bind_param("i", $id_reserva);
        $stmt->execute();
        
        // Actualizar tarjeta del usuario si no tiene
        if (empty($user['numero_tarjeta'])) {
            $stmt = $conn->prepare("UPDATE usuarios SET numero_tarjeta = ? WHERE id_usuario = ?");
            $stmt->bind_param("si", $numero_tarjeta, $user['id_usuario']);
            $stmt->execute();
        }
        
        $conn->commit();
        $success = 'Pago procesado exitosamente. Tu reserva está confirmada.';
        $reserva['estado'] = 'pagada';
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = 'Error al procesar el pago: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprar Boleto</title>
    <link rel="stylesheet" href="css/style.css">
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
        <h2>Compra de Boleto</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
                <br><br>
                <strong>Código de Reserva: <?php echo $reserva['codigo_reserva']; ?></strong>
                <br>
                <a href="mis_reservas.php" class="btn btn-primary">Ver Mis Reservas</a>
            </div>
        <?php endif; ?>

        <div class="purchase-container">
            <div class="reservation-summary">
                <h3>Resumen de la Reserva</h3>
                <div class="summary-item">
                    <strong>Código de Reserva:</strong> <?php echo $reserva['codigo_reserva']; ?>
                </div>
                <div class="summary-item">
                    <strong>Estado:</strong> 
                    <span class="status status-<?php echo $reserva['estado']; ?>">
                        <?php echo ucfirst($reserva['estado']); ?>
                    </span>
                </div>
                <div class="summary-item">
                    <strong>Fecha de Reserva:</strong> <?php echo date('d/m/Y H:i', strtotime($reserva['fecha_reserva'])); ?>
                </div>
                
                <hr>
                
                <h4>Detalles del Vuelo</h4>
                <?php 
                $detalles->data_seek(0);
                $primer_detalle = $detalles->fetch_assoc();
                ?>
                <div class="flight-info">
                    <div class="info-row">
                        <strong>Vuelo:</strong> <?php echo $primer_detalle['aerolinea'] . ' - ' . $primer_detalle['numero_vuelo']; ?>
                    </div>
                    <div class="info-row">
                        <strong>Ruta:</strong> <?php echo $primer_detalle['ciudad_origen'] . ' (' . $primer_detalle['codigo_origen'] . ') → ' . $primer_detalle['ciudad_destino'] . ' (' . $primer_detalle['codigo_destino'] . ')'; ?>
                    </div>
                    <div class="info-row">
                        <strong>Salida:</strong> <?php echo date('d/m/Y H:i', strtotime($primer_detalle['fecha_salida'])); ?>
                    </div>
                    <div class="info-row">
                        <strong>Llegada:</strong> <?php echo date('d/m/Y H:i', strtotime($primer_detalle['fecha_llegada'])); ?>
                    </div>
                    <div class="info-row">
                        <strong>Clase:</strong> <?php echo ucfirst($primer_detalle['clase']); ?>
                    </div>
                </div>
                
                <hr>
                
                <h4>Pasajeros</h4>
                <?php 
                $detalles->data_seek(0);
                while ($detalle = $detalles->fetch_assoc()): 
                ?>
                    <div class="passenger-info">
                        <strong><?php echo htmlspecialchars($detalle['nombre_pasajero'] . ' ' . $detalle['apellido_pasajero']); ?></strong>
                        <div>Doc: <?php echo htmlspecialchars($detalle['documento_pasajero']); ?></div>
                        <div>Precio: $<?php echo number_format($detalle['precio'], 2); ?></div>
                    </div>
                <?php endwhile; ?>
                
                <hr>
                
                <div class="total-amount">
                    <strong>Total:</strong> <span class="amount">$<?php echo number_format($reserva['total'], 2); ?></span>
                </div>
            </div>

            <?php if ($reserva['estado'] === 'pendiente'): ?>
            <div class="payment-form">
                <h3>Información de Pago</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Número de Tarjeta</label>
                        <input type="text" name="numero_tarjeta" placeholder="1234 5678 9012 3456" 
                               value="<?php echo $user['numero_tarjeta'] ?? ''; ?>" 
                               pattern="[0-9]{13,19}" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Nombre en la Tarjeta</label>
                        <input type="text" name="nombre_tarjeta" 
                               value="<?php echo $reserva['nombre'] . ' ' . $reserva['apellido']; ?>" 
                               required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Fecha de Expiración</label>
                            <input type="text" name="fecha_expiracion" placeholder="MM/AA" 
                                   pattern="[0-9]{2}/[0-9]{2}" required>
                        </div>
                        
                        <div class="form-group">
                            <label>CVV</label>
                            <input type="text" name="cvv" placeholder="123" 
                                   pattern="[0-9]{3,4}" required>
                        </div>
                    </div>
                    
                    <div class="payment-note">
                        <p>💳 Tu pago es seguro. Los datos de tu tarjeta están protegidos.</p>
                        <p>📧 Recibirás una confirmación por email a: <?php echo $reserva['email']; ?></p>
                    </div>
                    
                    <button type="submit" name="pagar" class="btn btn-primary btn-large">
                        Pagar $<?php echo number_format($reserva['total'], 2); ?>
                    </button>
                </form>
            </div>
            <?php else: ?>
            <div class="payment-confirmed">
                <h3>✅ Pago Confirmado</h3>
                <p>Tu boleto ha sido comprado exitosamente.</p>
                <p>Puedes recoger tus boletos en el mostrador del aeropuerto o recibirlos por correo electrónico.</p>
                <a href="mis_reservas.php" class="btn btn-primary">Ver Mis Reservas</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>