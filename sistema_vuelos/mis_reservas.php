<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$conn = getConnection();

$mensaje = '';

// Cancelar reserva
if (isset($_GET['cancelar'])) {
    $id_reserva = intval($_GET['cancelar']);
    
    $conn->begin_transaction();
    
    try {
        // Verificar que la reserva pertenece al usuario
        $stmt = $conn->prepare("SELECT r.*, dr.id_vuelo 
                                FROM reservas r
                                JOIN detalle_reserva dr ON r.id_reserva = dr.id_reserva
                                WHERE r.id_reserva = ? AND r.id_usuario = ? AND r.estado != 'cancelada'");
        $stmt->bind_param("ii", $id_reserva, $user['id_usuario']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $reserva = $result->fetch_assoc();
            
            // Contar pasajeros
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM detalle_reserva WHERE id_reserva = ?");
            $stmt->bind_param("i", $id_reserva);
            $stmt->execute();
            $num_pasajeros = $stmt->get_result()->fetch_assoc()['total'];
            
            // Devolver asientos al vuelo
            $stmt = $conn->prepare("UPDATE vuelos SET asientos_disponibles = asientos_disponibles + ? WHERE id_vuelo = ?");
            $stmt->bind_param("ii", $num_pasajeros, $reserva['id_vuelo']);
            $stmt->execute();
            
            // Cancelar reserva
            $stmt = $conn->prepare("UPDATE reservas SET estado = 'cancelada' WHERE id_reserva = ?");
            $stmt->bind_param("i", $id_reserva);
            $stmt->execute();
            
            $conn->commit();
            $mensaje = 'Reserva cancelada exitosamente';
        }
    } catch (Exception $e) {
        $conn->rollback();
        $mensaje = 'Error al cancelar la reserva';
    }
}

// Obtener reservas del usuario
$stmt = $conn->prepare("SELECT DISTINCT r.*, 
                        (SELECT COUNT(*) FROM detalle_reserva WHERE id_reserva = r.id_reserva) as num_pasajeros
                        FROM reservas r
                        WHERE r.id_usuario = ?
                        ORDER BY r.fecha_reserva DESC");
$stmt->bind_param("i", $user['id_usuario']);
$stmt->execute();
$reservas = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Reservas</title>
    <link rel="stylesheet" href="css/style.css">
    <script>
        function confirmarCancelacion(codigo) {
            return confirm('¿Estás seguro de que deseas cancelar la reserva ' + codigo + '?');
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
        <h2>Mis Reservas</h2>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if ($reservas->num_rows === 0): ?>
            <div class="no-results">
                <p>No tienes reservas registradas.</p>
                <a href="consultar_vuelos.php" class="btn btn-primary">Buscar Vuelos</a>
            </div>
        <?php else: ?>
            <div class="reservations-list">
                <?php while ($reserva = $reservas->fetch_assoc()): ?>
                    <?php
                    // Obtener detalles de la reserva
                    $stmt_det = $conn->prepare("SELECT dr.*, v.numero_vuelo, v.fecha_salida, v.fecha_llegada,
                                                co.nombre as ciudad_origen, co.codigo_iata as codigo_origen,
                                                cd.nombre as ciudad_destino, cd.codigo_iata as codigo_destino,
                                                a.nombre as aerolinea
                                                FROM detalle_reserva dr
                                                JOIN vuelos v ON dr.id_vuelo = v.id_vuelo
                                                JOIN ciudades co ON v.id_ciudad_origen = co.id_ciudad
                                                JOIN ciudades cd ON v.id_ciudad_destino = cd.id_ciudad
                                                JOIN aerolineas a ON v.id_aerolinea = a.id_aerolinea
                                                WHERE dr.id_reserva = ?
                                                LIMIT 1");
                    $stmt_det->bind_param("i", $reserva['id_reserva']);
                    $stmt_det->execute();
                    $detalle = $stmt_det->get_result()->fetch_assoc();
                    ?>
                    
                    <div class="reservation-card">
                        <div class="reservation-header">
                            <div>
                                <h3>Reserva #<?php echo $reserva['codigo_reserva']; ?></h3>
                                <span class="reservation-date">
                                    Realizada el <?php echo date('d/m/Y H:i', strtotime($reserva['fecha_reserva'])); ?>
                                </span>
                            </div>
                            <span class="status status-<?php echo $reserva['estado']; ?>">
                                <?php echo ucfirst($reserva['estado']); ?>
                            </span>
                        </div>
                        
                        <?php if ($detalle): ?>
                        <div class="reservation-details">
                            <div class="detail-row">
                                <strong>Vuelo:</strong> 
                                <?php echo $detalle['aerolinea'] . ' - ' . $detalle['numero_vuelo']; ?>
                            </div>
                            
                            <div class="flight-route-mini">
                                <div>
                                    <strong><?php echo $detalle['codigo_origen']; ?></strong>
                                    <div><?php echo $detalle['ciudad_origen']; ?></div>
                                    <div class="time"><?php echo date('d/m/Y H:i', strtotime($detalle['fecha_salida'])); ?></div>
                                </div>
                                <div class="arrow">→</div>
                                <div>
                                    <strong><?php echo $detalle['codigo_destino']; ?></strong>
                                    <div><?php echo $detalle['ciudad_destino']; ?></div>
                                    <div class="time"><?php echo date('d/m/Y H:i', strtotime($detalle['fecha_llegada'])); ?></div>
                                </div>
                            </div>
                            
                            <div class="detail-row">
                                <strong>Clase:</strong> <?php echo ucfirst($detalle['clase']); ?>
                            </div>
                            
                            <div class="detail-row">
                                <strong>Pasajeros:</strong> <?php echo $reserva['num_pasajeros']; ?>
                            </div>
                            
                            <div class="detail-row">
                                <strong>Total:</strong> 
                                <span class="price-highlight">$<?php echo number_format($reserva['total'], 2); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="reservation-actions">
                            <?php if ($reserva['estado'] === 'pendiente'): ?>
                                <a href="comprar_boleto.php?reserva=<?php echo $reserva['id_reserva']; ?>" 
                                   class="btn btn-primary btn-small">
                                    💳 Pagar Ahora
                                </a>
                                <a href="?cancelar=<?php echo $reserva['id_reserva']; ?>" 
                                   class="btn btn-danger btn-small"
                                   onclick="return confirmarCancelacion('<?php echo $reserva['codigo_reserva']; ?>')">
                                    ❌ Cancelar
                                </a>
                            <?php elseif ($reserva['estado'] === 'pagada'): ?>
                                <a href="ver_detalle.php?reserva=<?php echo $reserva['id_reserva']; ?>" 
                                   class="btn btn-secondary btn-small">
                                    📄 Ver Detalles
                                </a>
                                <?php
                                // Permitir cancelación si faltan más de 24 horas
                                $horas_restantes = (strtotime($detalle['fecha_salida']) - time()) / 3600;
                                if ($horas_restantes > 24):
                                ?>
                                <a href="?cancelar=<?php echo $reserva['id_reserva']; ?>" 
                                   class="btn btn-danger btn-small"
                                   onclick="return confirmarCancelacion('<?php echo $reserva['codigo_reserva']; ?>')">
                                    ❌ Cancelar
                                </a>
                                <?php endif; ?>
                            <?php elseif ($reserva['estado'] === 'cancelada'): ?>
                                <span class="canceled-note">Reserva cancelada</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php $conn->close(); ?>