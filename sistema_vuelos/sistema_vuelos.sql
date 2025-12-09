-- Base de Datos: Sistema de Reserva de Vuelos
-- Crear base de datos
CREATE DATABASE IF NOT EXISTS sistema_vuelos;
USE sistema_vuelos;

-- Tabla de usuarios
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    telefono VARCHAR(20),
    direccion TEXT,
    numero_tarjeta VARCHAR(20),
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de aerolíneas
CREATE TABLE aerolineas (
    id_aerolinea INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    codigo VARCHAR(10) UNIQUE NOT NULL,
    pais VARCHAR(50),
    telefono VARCHAR(20),
    estado ENUM('activa', 'inactiva') DEFAULT 'activa'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de ciudades
CREATE TABLE ciudades (
    id_ciudad INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    codigo_iata VARCHAR(3) UNIQUE NOT NULL,
    pais VARCHAR(50) NOT NULL,
    aeropuerto VARCHAR(150)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de vuelos
CREATE TABLE vuelos (
    id_vuelo INT AUTO_INCREMENT PRIMARY KEY,
    numero_vuelo VARCHAR(20) UNIQUE NOT NULL,
    id_aerolinea INT NOT NULL,
    id_ciudad_origen INT NOT NULL,
    id_ciudad_destino INT NOT NULL,
    fecha_salida DATETIME NOT NULL,
    fecha_llegada DATETIME NOT NULL,
    duracion_minutos INT,
    capacidad_total INT NOT NULL,
    asientos_disponibles INT NOT NULL,
    precio_economica DECIMAL(10,2) NOT NULL,
    precio_ejecutiva DECIMAL(10,2) NOT NULL,
    precio_primera DECIMAL(10,2) NOT NULL,
    tipo_vuelo ENUM('directo', 'escala') DEFAULT 'directo',
    estado_vuelo ENUM('programado', 'en_hora', 'retrasado', 'cancelado', 'completado') DEFAULT 'programado',
    FOREIGN KEY (id_aerolinea) REFERENCES aerolineas(id_aerolinea),
    FOREIGN KEY (id_ciudad_origen) REFERENCES ciudades(id_ciudad),
    FOREIGN KEY (id_ciudad_destino) REFERENCES ciudades(id_ciudad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de reservas
CREATE TABLE reservas (
    id_reserva INT AUTO_INCREMENT PRIMARY KEY,
    codigo_reserva VARCHAR(10) UNIQUE NOT NULL,
    id_usuario INT NOT NULL,
    fecha_reserva TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('pendiente', 'confirmada', 'pagada', 'cancelada') DEFAULT 'pendiente',
    total DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de detalles de reserva
CREATE TABLE detalle_reserva (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_reserva INT NOT NULL,
    id_vuelo INT NOT NULL,
    nombre_pasajero VARCHAR(200) NOT NULL,
    apellido_pasajero VARCHAR(200) NOT NULL,
    documento_pasajero VARCHAR(50) NOT NULL,
    clase ENUM('economica', 'ejecutiva', 'primera') NOT NULL,
    numero_asiento VARCHAR(10),
    precio DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_reserva) REFERENCES reservas(id_reserva),
    FOREIGN KEY (id_vuelo) REFERENCES vuelos(id_vuelo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de pagos
CREATE TABLE pagos (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_reserva INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    metodo_pago ENUM('tarjeta_credito', 'tarjeta_debito', 'paypal') DEFAULT 'tarjeta_credito',
    numero_tarjeta_ultimos4 VARCHAR(4),
    fecha_pago TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('procesando', 'aprobado', 'rechazado') DEFAULT 'procesando',
    FOREIGN KEY (id_reserva) REFERENCES reservas(id_reserva)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertar datos de ejemplo

-- Aerolíneas
INSERT INTO aerolineas (nombre, codigo, pais, telefono) VALUES
('LATAM Airlines', 'LA', 'Chile', '+56-2-2565-2000'),
('Avianca', 'AV', 'Colombia', '+57-1-401-3434'),
('Copa Airlines', 'CM', 'Panamá', '+507-217-2672'),
('American Airlines', 'AA', 'Estados Unidos', '+1-800-433-7300'),
('Iberia', 'IB', 'España', '+34-902-111-500');

-- Ciudades
INSERT INTO ciudades (nombre, codigo_iata, pais, aeropuerto) VALUES
('Lima', 'LIM', 'Perú', 'Aeropuerto Internacional Jorge Chávez'),
('Buenos Aires', 'EZE', 'Argentina', 'Aeropuerto Internacional Ministro Pistarini'),
('Santiago', 'SCL', 'Chile', 'Aeropuerto Internacional Arturo Merino Benítez'),
('Bogotá', 'BOG', 'Colombia', 'Aeropuerto Internacional El Dorado'),
('Ciudad de México', 'MEX', 'México', 'Aeropuerto Internacional de la Ciudad de México'),
('Miami', 'MIA', 'Estados Unidos', 'Aeropuerto Internacional de Miami'),
('Madrid', 'MAD', 'España', 'Aeropuerto Adolfo Suárez Madrid-Barajas'),
('Panamá', 'PTY', 'Panamá', 'Aeropuerto Internacional de Tocumen');

-- Vuelos de ejemplo (próxima semana)
INSERT INTO vuelos (numero_vuelo, id_aerolinea, id_ciudad_origen, id_ciudad_destino, fecha_salida, fecha_llegada, duracion_minutos, capacidad_total, asientos_disponibles, precio_economica, precio_ejecutiva, precio_primera, tipo_vuelo, estado_vuelo) VALUES
('LA2435', 1, 1, 2, '2025-12-09 08:00:00', '2025-12-09 13:30:00', 330, 180, 45, 350.00, 850.00, 1500.00, 'directo', 'programado'),
('LA2436', 1, 2, 1, '2025-12-09 15:00:00', '2025-12-09 20:30:00', 330, 180, 68, 350.00, 850.00, 1500.00, 'directo', 'programado'),
('AV125', 2, 1, 4, '2025-12-10 09:30:00', '2025-12-10 13:00:00', 210, 150, 92, 280.00, 720.00, 1300.00, 'directo', 'programado'),
('AV126', 2, 4, 1, '2025-12-10 14:30:00', '2025-12-10 18:00:00', 210, 150, 87, 280.00, 720.00, 1300.00, 'directo', 'programado'),
('CM315', 3, 1, 8, '2025-12-11 10:00:00', '2025-12-11 13:45:00', 225, 160, 120, 320.00, 780.00, 1400.00, 'directo', 'programado'),
('AA958', 4, 1, 6, '2025-12-12 22:00:00', '2025-12-13 05:30:00', 450, 200, 56, 450.00, 1200.00, 2200.00, 'directo', 'programado'),
('IB6401', 5, 1, 7, '2025-12-13 18:00:00', '2025-12-14 13:00:00', 720, 250, 134, 850.00, 2100.00, 3800.00, 'directo', 'programado'),
('LA1234', 1, 1, 3, '2025-12-09 11:00:00', '2025-12-09 15:30:00', 270, 180, 102, 320.00, 800.00, 1450.00, 'directo', 'programado'),
('AV230', 2, 1, 5, '2025-12-14 07:00:00', '2025-12-14 13:30:00', 390, 170, 78, 480.00, 1150.00, 2000.00, 'escala', 'programado'),
('CM420', 3, 8, 6, '2025-12-15 16:00:00', '2025-12-15 19:30:00', 210, 160, 95, 280.00, 700.00, 1250.00, 'directo', 'programado');

-- Usuario de prueba (password: 12345678)
INSERT INTO usuarios (nombre, apellido, email, password, telefono, direccion, numero_tarjeta) VALUES
('Juan', 'Pérez', 'juan.perez@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+51-999-888-777', 'Av. Principal 123, Lima', '4532123456789012'),
('María', 'García', 'maria.garcia@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+51-988-777-666', 'Jr. Los Olivos 456, Lima', '5412987654321098');

-- Crear índices para mejorar rendimiento
CREATE INDEX idx_vuelos_fecha ON vuelos(fecha_salida);
CREATE INDEX idx_vuelos_origen_destino ON vuelos(id_ciudad_origen, id_ciudad_destino);
CREATE INDEX idx_reservas_usuario ON reservas(id_usuario);
CREATE INDEX idx_reservas_codigo ON reservas(codigo_reserva);