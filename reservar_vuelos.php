<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$conn = getConnection();

$error = '';
$success = '';
$vuelo_seleccionado = null;

// Si viene un ID de vuelo desde la consulta
if (isset($_GET['vuelo'])) {
    $id_vuelo = intval($_GET['vuelo']);
    $stmt = $conn->prepare("SELECT v.*, 
              co.nombre as ciudad_origen, co.codigo_iata as codigo_origen,
              cd.nombre as ciudad_destino, cd.codigo_iata as codigo_destino,
              a.nombre as aerolinea
              FROM vuelos v
              JOIN ciudades co ON v.id_ciudad_origen = co.id_ciudad
              JOIN ciudades cd ON v.id_ciudad_destino = cd.id_ciudad
              JOIN aerolineas a ON v.id_aerolinea = a.id_aerolinea
              WHERE v.id_vuelo = ?");
    $stmt->bind_param("i", $id_vuelo);
    $stmt->execute();
    $result = $stmt->get_result();
    $vuelo_seleccionado = $result->fetch_assoc();
}

// Procesar reserva
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservar'])) {
    $id_vuelo = intval($_POST['id_vuelo']);
    $num_pasajeros = intval($_POST['num_pasajeros']);
    $clase = $_POST['clase'];
    
    $conn->begin_transaction();
    
    try {
        // Obtener información del vuelo
        $stmt = $conn->prepare("SELECT * FROM vuelos WHERE id_vuelo = ? FOR UPDATE");
        $stmt->bind_param("i", $id_vuelo);
        $stmt->execute();
        $vuelo = $stmt->get_result()->fetch_assoc();
        
        if (!$vuelo) {
            throw new Exception("Vuelo no encontrado");
        }
        
        if ($vuelo['asientos_disponibles'] < $num_pasajeros) {
            throw new Exception("No hay suficientes asientos disponibles");
        }
        
        // Calcular precio
        $precio_unitario = 0;
        switch ($clase) {
            case 'economica':
                $precio_unitario = $vuelo['precio_economica'];
                break;
            case 'ejecutiva':
                $precio_unitario = $vuelo['precio_ejecutiva'];
                break;
            case 'primera':
                $precio_unitario = $vuelo['precio_primera'];
                break;
        }
        
        $total = $precio_unitario * $num_pasajeros;
        
        // Crear reserva
        $codigo_reserva = generarCodigoReserva();
        $stmt = $conn->prepare("INSERT INTO reservas (codigo_reserva, id_usuario, total, estado) VALUES (?, ?, ?, 'pendiente')");
        $stmt->bind_param("sid", $codigo_reserva, $user['id_usuario'], $total);
        $stmt->execute();
        $id_reserva = $conn->insert_id;
        
        // Agregar pasajeros
        for ($i = 0; $i < $num_pasajeros; $i++) {
            $nombre_pasajero = $_POST['nombre_pasajero'][$i];
            $apellido_pasajero = $_POST['apellido_pasajero'][$i];
            $documento_pasajero = $_POST['documento_pasajero'][$i];
            
            $stmt = $conn->prepare("INSERT INTO detalle_reserva (id_reserva, id_vuelo, nombre_pasajero, apellido_pasajero, documento_pasajero, clase, precio) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissssd", $id_reserva, $id_vuelo, $nombre_pasajero, $apellido_pasajero, $documento_pasajero, $clase, $precio_unitario);
            $stmt->execute();
        }
        
        // Actualizar asientos disponibles
        $nuevos_asientos = $vuelo['asientos_disponibles'] - $num_pasajeros;
        $stmt = $conn->prepare("UPDATE vuelos SET asientos_disponibles = ? WHERE id_vuelo = ?");
        $stmt->bind_param("ii", $nuevos_asientos, $id_vuelo);
        $stmt->execute();
        
        $conn->commit();
        
        $_SESSION['reserva_exitosa'] = $codigo_reserva;
        header('Location: comprar_boleto.php?reserva=' . $id_reserva);
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

$vuelos = $conn->query("SELECT v.*, 
          co.nombre as ciudad_origen, co.codigo_iata as codigo_origen,
          cd.nombre as ciudad_destino, cd.codigo_iata as codigo_destino,
          a.nombre as aerolinea
          FROM vuelos v
          JOIN ciudades co ON v.id_ciudad_origen = co.id_ciudad
          JOIN ciudades cd ON v.id_ciudad_destino = cd.id_ciudad
          JOIN aerolineas a ON v.id_aerolinea = a.id_aerolinea
          WHERE v.asientos_disponibles > 0 
          AND v.estado_vuelo IN ('programado', 'en_hora')
          AND v.fecha_salida >= NOW()
          ORDER BY v.fecha_salida ASC
          LIMIT 20");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservar Vuelos</title>
    <link rel="stylesheet" href="css/style.css">
    <script>
        function actualizarPasajeros() {
            const num = document.getElementById('num_pasajeros').value;
            const container = document.getElementById('pasajeros_container');
            container.innerHTML = '';
            
            for (let i = 0; i < num; i++) {
                container.innerHTML += `
                    <div class="pasajero-form">
                        <h4>Pasajero ${i + 1}</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nombre</label>
                                <input type="text" name="nombre_pasajero[]" required>
                            </div>
                            <div class="form-group">
                                <label>Apellido</label>
                                <input type="text" name="apellido_pasajero[]" required>
                            </div>
                            <div class="form-group">
                                <label>Documento (DNI/Pasaporte)</label>
                                <input type="text" name="documento_pasajero[]" required>
                            </div>
                        </div>
                    </div>
                `;
            }
        }
        
        function calcularTotal() {
            const vuelo = document.getElementById('id_vuelo');
            const clase = document.getElementById('clase').value;
            const num = document.getElementById('num_pasajeros').value;
            
            if (!vuelo.value) return;
            
            const option = vuelo.options[vuelo.selectedIndex];
            let precio = 0;
            
            if (clase === 'economica') precio = parseFloat(option.dataset.economica);
            else if (clase === 'ejecutiva') precio = parseFloat(option.dataset.ejecutiva);
            else if (clase === 'primera') precio = parseFloat(option.dataset.primera);
            
            const total = precio * num;
            document.getElementById('total_preview').textContent = '$' + total.toFixed(2);
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
        <h2>Reservar Vuelo</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="reservation-form">
            <form method="POST" action="">
                <div class="form-section">
                    <h3>Seleccionar Vuelo</h3>
                    <div class="form-group">
                        <label>Vuelo</label>
                        <select name="id_vuelo" id="id_vuelo" required onchange="calcularTotal()">
                            <option value="">Seleccione un vuelo</option>
                            <?php while ($v = $vuelos->fetch_assoc()): ?>
                                <option value="<?php echo $v['id_vuelo']; ?>"
                                    data-economica="<?php echo $v['precio_economica']; ?>"
                                    data-ejecutiva="<?php echo $v['precio_ejecutiva']; ?>"
                                    data-primera="<?php echo $v['precio_primera']; ?>"
                                    <?php echo ($vuelo_seleccionado && $vuelo_seleccionado['id_vuelo'] == $v['id_vuelo']) ? 'selected' : ''; ?>>
                                    <?php echo $v['numero_vuelo'] . ' - ' . $v['ciudad_origen'] . ' → ' . $v['ciudad_destino'] . ' - ' . date('d/m/Y H:i', strtotime($v['fecha_salida'])); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Detalles de la Reserva</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Clase</label>
                            <select name="clase" id="clase" required onchange="calcularTotal()">
                                <option value="economica">Económica</option>
                                <option value="ejecutiva">Ejecutiva</option>
                                <option value="primera">Primera Clase</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Número de Pasajeros</label>
                            <input type="number" name="num_pasajeros" id="num_pasajeros" min="1" max="9" value="1" required onchange="actualizarPasajeros(); calcularTotal()">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Información de Pasajeros</h3>
                    <div id="pasajeros_container">
                        <div class="pasajero-form">
                            <h4>Pasajero 1</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Nombre</label>
                                    <input type="text" name="nombre_pasajero[]" required>
                                </div>
                                <div class="form-group">
                                    <label>Apellido</label>
                                    <input type="text" name="apellido_pasajero[]" required>
                                </div>
                                <div class="form-group">
                                    <label>Documento (DNI/Pasaporte)</label>
                                    <input type="text" name="documento_pasajero[]" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="total-section">
                    <h3>Total a Pagar: <span id="total_preview">$0.00</span></h3>
                </div>

                <div class="form-actions">
                    <button type="submit" name="reservar" class="btn btn-primary btn-large">Continuar con la Reserva</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>