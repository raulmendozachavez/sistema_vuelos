<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$conn = getConnection();

// Obtener ID de reserva
$id_reserva = intval($_GET['reserva'] ?? 0);

// Verificar que la reserva pertenece al usuario
$stmt = $conn->prepare("SELECT r.*, u.nombre, u.apellido, u.email
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
                        co.nombre as ciudad_origen, co.codigo_iata as codigo_origen, co.aeropuerto as aeropuerto_origen,
                        cd.nombre as ciudad_destino, cd.codigo_iata as codigo_destino, cd.aeropuerto as aeropuerto_destino,
                        a.nombre as aerolinea, a.codigo as codigo_aerolinea, a.telefono as telefono_aerolinea
                        FROM detalle_reserva dr
                        JOIN vuelos v ON dr.id_vuelo = v.id_vuelo
                        JOIN ciudades co ON v.id_ciudad_origen = co.id_ciudad
                        JOIN ciudades cd ON v.id_ciudad_destino = cd.id_ciudad
                        JOIN aerolineas a ON v.id_aerolinea = a.id_aerolinea
                        WHERE dr.id_reserva = ?");
$stmt->bind_param("i", $id_reserva);
$stmt->execute();
$detalles = $stmt->get_result();

// Obtener información de pago si existe
$stmt = $conn->prepare("SELECT * FROM pagos WHERE id_reserva = ? ORDER BY fecha_pago DESC LIMIT 1");
$stmt->bind_param("i", $id_reserva);
$stmt->execute();
$pago = $stmt->get_result()->fetch_assoc();

$primer_detalle = $detalles->fetch_assoc();
$detalles->data_seek(0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Reserva #<?php echo $reserva['codigo_reserva']; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .ticket-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .ticket {
            background: white;
            border: 3px solid #667eea;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .ticket-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .ticket-code {
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 3px;
            margin: 10px 0;
        }
        
        .ticket-status {
            display: inline-block;
            padding: 8px 20px;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .flight-details-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 15px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        
        .route-visual {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
        }
        
        .airport-info {
            text-align: center;
            flex: 1;
        }
        
        .airport-code {
            font-size: 42px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .airport-name {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .flight-time {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin: 10px 0;
        }
        
        .flight-date {
            font-size: 14px;
            color: #999;
        }
        
        .flight-arrow {
            flex: 0.5;
            text-align: center;
        }
        
        .plane-icon {
            font-size: 48px;
            color: #667eea;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 15px;
        }
        
        .info-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .info-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .passengers-list {
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .passenger-item {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .passenger-item:last-child {
            border-bottom: none;
        }
        
        .passenger-name {
            font-weight: 600;
            color: #333;
        }
        
        .passenger-doc {
            color: #666;
            font-size: 14px;
        }
        
        .passenger-seat {
            background: #667eea;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        .payment-info {
            background: #d4edda;
            border: 2px solid #c3e6cb;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .payment-info h4 {
            color: #155724;
            margin-bottom: 15px;
        }
        
        .total-amount-large {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            text-align: center;
            padding: 20px 0;
            border-top: 2px dashed #e0e0e0;
            border-bottom: 2px dashed #e0e0e0;
            margin: 20px 0;
        }
        
        .important-notes {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .important-notes h4 {
            color: #856404;
            margin-bottom: 15px;
        }
        
        .important-notes ul {
            margin-left: 20px;
            color: #856404;
        }
        
        .important-notes li {
            margin-bottom: 8px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        @media print {
            .navbar, .action-buttons {
                display: none;
            }
            
            body {
                background: white;
                padding: 0;
            }
            
            .container {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <h1>✈️ Sistema de Vuelos</h1>
            <div class="nav-user">
                <a href="mis_reservas.php" class="btn btn-secondary">Volver a Mis Reservas</a>
                <a href="dashboard.php" class="btn btn-secondary">Panel Principal</a>
                <a href="logout.php" class="btn btn-logout">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="ticket-container">
            <div class="ticket">
                <div class="ticket-header">
                    <h1>🎫 Boleto Electrónico</h1>
                    <div class="ticket-code"><?php echo $reserva['codigo_reserva']; ?></div>
                    <div class="ticket-status">
                        Estado: <?php echo strtoupper($reserva['estado']); ?>
                    </div>
                </div>

                <!-- Información del Vuelo -->
                <div class="flight-details-section">
                    <div class="section-title">✈️ Información del Vuelo</div>
                    
                    <div class="route-visual">
                        <div class="airport-info">
                            <div class="airport-code"><?php echo $primer_detalle['codigo_origen']; ?></div>
                            <div class="airport-name"><?php echo htmlspecialchars($primer_detalle['ciudad_origen']); ?></div>
                            <div class="airport-name"><?php echo htmlspecialchars($primer_detalle['aeropuerto_origen']); ?></div>
                            <div class="flight-time"><?php echo date('H:i', strtotime($primer_detalle['fecha_salida'])); ?></div>
                            <div class="flight-date"><?php echo strftime('%A, %d de %B de %Y', strtotime($primer_detalle['fecha_salida'])); ?></div>
                        </div>
                        
                        <div class="flight-arrow">
                            <div class="plane-icon">✈️</div>
                        </div>
                        
                        <div class="airport-info">
                            <div class="airport-code"><?php echo $primer_detalle['codigo_destino']; ?></div>
                            <div class="airport-name"><?php echo htmlspecialchars($primer_detalle['ciudad_destino']); ?></div>
                            <div class="airport-name"><?php echo htmlspecialchars($primer_detalle['aeropuerto_destino']); ?></div>
                            <div class="flight-time"><?php echo date('H:i', strtotime($primer_detalle['fecha_llegada'])); ?></div>
                            <div class="flight-date"><?php echo strftime('%A, %d de %B de %Y', strtotime($primer_detalle['fecha_llegada'])); ?></div>
                        </div>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Aerolínea</div>
                            <div class="info-value"><?php echo htmlspecialchars($primer_detalle['aerolinea']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Número de Vuelo</div>
                            <div class="info-value"><?php echo $primer_detalle['numero_vuelo']; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Clase</div>
                            <div class="info-value"><?php echo ucfirst($primer_detalle['clase']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Contacto Aerolínea</div>
                            <div class="info-value"><?php echo htmlspecialchars($primer_detalle['telefono_aerolinea'] ?? 'N/A'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Información de Pasajeros -->
                <div class="flight-details-section">
                    <div class="section-title">👥 Pasajeros</div>
                    <div class="passengers-list">
                        <?php 
                        $detalles->data_seek(0);
                        while ($detalle = $detalles->fetch_assoc()): 
                        ?>
                            <div class="passenger-item">
                                <div>
                                    <div class="passenger-name">
                                        <?php echo htmlspecialchars($detalle['nombre_pasajero'] . ' ' . $detalle['apellido_pasajero']); ?>
                                    </div>
                                    <div class="passenger-doc">
                                        Documento: <?php echo htmlspecialchars($detalle['documento_pasajero']); ?>
                                    </div>
                                </div>
                                <div>
                                    <?php if ($detalle['numero_asiento']): ?>
                                        <span class="passenger-seat">Asiento <?php echo $detalle['numero_asiento']; ?></span>
                                    <?php else: ?>
                                        <span class="passenger-seat">Por asignar</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Información de la Reserva -->
                <div class="flight-details-section">
                    <div class="section-title">📋 Detalles de la Reserva</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Titular</div>
                            <div class="info-value"><?php echo htmlspecialchars($reserva['nombre'] . ' ' . $reserva['apellido']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($reserva['email']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Fecha de Reserva</div>
                            <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($reserva['fecha_reserva'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Estado</div>
                            <div class="info-value">
                                <span class="status status-<?php echo $reserva['estado']; ?>">
                                    <?php echo ucfirst($reserva['estado']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total -->
                <div class="total-amount-large">
                    TOTAL: $<?php echo number_format($reserva['total'], 2); ?>
                </div>

                <!-- Información de Pago -->
                <?php if ($pago): ?>
                <div class="payment-info">
                    <h4>✅ Información de Pago</h4>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Fecha de Pago</div>
                            <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($pago['fecha_pago'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Método</div>
                            <div class="info-value"><?php echo ucfirst(str_replace('_', ' ', $pago['metodo_pago'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Tarjeta</div>
                            <div class="info-value">**** **** **** <?php echo $pago['numero_tarjeta_ultimos4']; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Estado del Pago</div>
                            <div class="info-value">
                                <span class="status status-<?php echo $pago['estado']; ?>">
                                    <?php echo ucfirst($pago['estado']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Notas Importantes -->
                <div class="important-notes">
                    <h4>⚠️ Información Importante</h4>
                    <ul>
                        <li>Llegue al aeropuerto con 2 horas de anticipación para vuelos nacionales y 3 horas para internacionales</li>
                        <li>Presente este código de reserva y un documento de identidad válido en el mostrador</li>
                        <li>Verifique los requisitos de documentación para el país de destino</li>
                        <li>El equipaje de mano no debe exceder 10 kg</li>
                        <li>Consulte las políticas de equipaje de la aerolínea</li>
                        <li>Guarde este boleto electrónico o imprímalo para presentarlo en el aeropuerto</li>
                    </ul>
                </div>

                <div class="action-buttons">
                    <button onclick="window.print()" class="btn btn-primary">🖨️ Imprimir Boleto</button>
                    <a href="mis_reservas.php" class="btn btn-secondary">Volver a Mis Reservas</a>
                    <?php if ($reserva['estado'] === 'pendiente'): ?>
                        <a href="comprar_boleto.php?reserva=<?php echo $id_reserva; ?>" class="btn btn-primary">💳 Pagar Ahora</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>