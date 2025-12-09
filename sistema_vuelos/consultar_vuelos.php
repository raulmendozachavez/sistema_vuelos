<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$conn = getConnection();

// Obtener ciudades
$ciudades = $conn->query("SELECT * FROM ciudades ORDER BY nombre");

// Variables de búsqueda
$vuelos = [];
$busqueda_realizada = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['buscar'])) {
    $origen = $_GET['origen'] ?? '';
    $destino = $_GET['destino'] ?? '';
    $fecha = $_GET['fecha'] ?? '';
    $clase = $_GET['clase'] ?? '';
    $orden = $_GET['orden'] ?? 'horario';
    
    $query = "SELECT v.*, 
              co.nombre as ciudad_origen, co.codigo_iata as codigo_origen,
              cd.nombre as ciudad_destino, cd.codigo_iata as codigo_destino,
              a.nombre as aerolinea, a.codigo as codigo_aerolinea
              FROM vuelos v
              JOIN ciudades co ON v.id_ciudad_origen = co.id_ciudad
              JOIN ciudades cd ON v.id_ciudad_destino = cd.id_ciudad
              JOIN aerolineas a ON v.id_aerolinea = a.id_aerolinea
              WHERE v.asientos_disponibles > 0 
              AND v.estado_vuelo IN ('programado', 'en_hora')";
    
    $params = [];
    $types = '';
    
    if ($origen) {
        $query .= " AND v.id_ciudad_origen = ?";
        $params[] = $origen;
        $types .= 'i';
    }
    
    if ($destino) {
        $query .= " AND v.id_ciudad_destino = ?";
        $params[] = $destino;
        $types .= 'i';
    }
    
    if ($fecha) {
        $query .= " AND DATE(v.fecha_salida) = ?";
        $params[] = $fecha;
        $types .= 's';
    }
    
    // Ordenamiento
    if ($orden === 'precio') {
        if ($clase === 'economica') {
            $query .= " ORDER BY v.precio_economica ASC";
        } elseif ($clase === 'ejecutiva') {
            $query .= " ORDER BY v.precio_ejecutiva ASC";
        } elseif ($clase === 'primera') {
            $query .= " ORDER BY v.precio_primera ASC";
        } else {
            $query .= " ORDER BY v.precio_economica ASC";
        }
    } else {
        $query .= " ORDER BY v.fecha_salida ASC";
    }
    
    $stmt = $conn->prepare($query);
    if (count($params) > 0) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $vuelos[] = $row;
    }
    
    $busqueda_realizada = true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultar Vuelos</title>
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
        <h2>Consultar Vuelos</h2>
        
        <div class="search-form">
            <form method="GET" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label>Ciudad de Origen</label>
                        <select name="origen">
                            <option value="">Todas</option>
                            <?php while ($ciudad = $ciudades->fetch_assoc()): ?>
                                <option value="<?php echo $ciudad['id_ciudad']; ?>"
                                    <?php echo (isset($_GET['origen']) && $_GET['origen'] == $ciudad['id_ciudad']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ciudad['nombre']) . ' (' . $ciudad['codigo_iata'] . ')'; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Ciudad de Destino</label>
                        <select name="destino">
                            <option value="">Todas</option>
                            <?php 
                            $ciudades->data_seek(0);
                            while ($ciudad = $ciudades->fetch_assoc()): ?>
                                <option value="<?php echo $ciudad['id_ciudad']; ?>"
                                    <?php echo (isset($_GET['destino']) && $_GET['destino'] == $ciudad['id_ciudad']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ciudad['nombre']) . ' (' . $ciudad['codigo_iata'] . ')'; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Fecha</label>
                        <input type="date" name="fecha" value="<?php echo $_GET['fecha'] ?? ''; ?>" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Clase</label>
                        <select name="clase">
                            <option value="">Todas</option>
                            <option value="economica" <?php echo (isset($_GET['clase']) && $_GET['clase'] === 'economica') ? 'selected' : ''; ?>>Económica</option>
                            <option value="ejecutiva" <?php echo (isset($_GET['clase']) && $_GET['clase'] === 'ejecutiva') ? 'selected' : ''; ?>>Ejecutiva</option>
                            <option value="primera" <?php echo (isset($_GET['clase']) && $_GET['clase'] === 'primera') ? 'selected' : ''; ?>>Primera Clase</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Ordenar por</label>
                        <select name="orden">
                            <option value="horario" <?php echo (isset($_GET['orden']) && $_GET['orden'] === 'horario') ? 'selected' : ''; ?>>Horario</option>
                            <option value="precio" <?php echo (isset($_GET['orden']) && $_GET['orden'] === 'precio') ? 'selected' : ''; ?>>Precio</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" name="buscar" class="btn btn-primary">🔍 Buscar Vuelos</button>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($busqueda_realizada): ?>
            <div class="results-section">
                <h3>Resultados de la Búsqueda (<?php echo count($vuelos); ?> vuelos encontrados)</h3>
                
                <?php if (empty($vuelos)): ?>
                    <p class="no-results">No se encontraron vuelos con los criterios seleccionados.</p>
                <?php else: ?>
                    <?php foreach ($vuelos as $vuelo): ?>
                        <div class="flight-card">
                            <div class="flight-header">
                                <h4><?php echo htmlspecialchars($vuelo['aerolinea']); ?> - <?php echo htmlspecialchars($vuelo['numero_vuelo']); ?></h4>
                                <span class="badge badge-<?php echo $vuelo['tipo_vuelo']; ?>">
                                    <?php echo $vuelo['tipo_vuelo'] === 'directo' ? '✈️ Directo' : '🔄 Con escala'; ?>
                                </span>
                            </div>
                            
                            <div class="flight-route">
                                <div class="route-point">
                                    <div class="city-code"><?php echo $vuelo['codigo_origen']; ?></div>
                                    <div class="city-name"><?php echo htmlspecialchars($vuelo['ciudad_origen']); ?></div>
                                    <div class="time"><?php echo date('H:i', strtotime($vuelo['fecha_salida'])); ?></div>
                                    <div class="date"><?php echo date('d/m/Y', strtotime($vuelo['fecha_salida'])); ?></div>
                                </div>
                                
                                <div class="route-arrow">
                                    <div class="duration"><?php echo floor($vuelo['duracion_minutos'] / 60) . 'h ' . ($vuelo['duracion_minutos'] % 60) . 'm'; ?></div>
                                    <div class="arrow">→</div>
                                </div>
                                
                                <div class="route-point">
                                    <div class="city-code"><?php echo $vuelo['codigo_destino']; ?></div>
                                    <div class="city-name"><?php echo htmlspecialchars($vuelo['ciudad_destino']); ?></div>
                                    <div class="time"><?php echo date('H:i', strtotime($vuelo['fecha_llegada'])); ?></div>
                                    <div class="date"><?php echo date('d/m/Y', strtotime($vuelo['fecha_llegada'])); ?></div>
                                </div>
                            </div>
                            
                            <div class="flight-details">
                                <div class="detail-item">
                                    <strong>Asientos disponibles:</strong> <?php echo $vuelo['asientos_disponibles']; ?>
                                </div>
                                <div class="detail-item">
                                    <strong>Estado:</strong> 
                                    <span class="status status-<?php echo $vuelo['estado_vuelo']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $vuelo['estado_vuelo'])); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="flight-prices">
                                <div class="price-option">
                                    <div class="price-class">Económica</div>
                                    <div class="price-amount">$<?php echo number_format($vuelo['precio_economica'], 2); ?></div>
                                </div>
                                <div class="price-option">
                                    <div class="price-class">Ejecutiva</div>
                                    <div class="price-amount">$<?php echo number_format($vuelo['precio_ejecutiva'], 2); ?></div>
                                </div>
                                <div class="price-option">
                                    <div class="price-class">Primera</div>
                                    <div class="price-amount">$<?php echo number_format($vuelo['precio_primera'], 2); ?></div>
                                </div>
                            </div>
                            
                            <div class="flight-actions">
                                <a href="reservar_vuelos.php?vuelo=<?php echo $vuelo['id_vuelo']; ?>" class="btn btn-primary">
                                    Reservar este Vuelo
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php $conn->close(); ?>