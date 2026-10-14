CREATE DATABASE IF NOT EXISTS laboratorio;
USE laboratorio;

CREATE TABLE IF NOT EXISTS equipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    estado ENUM('Disponible', 'En Uso', 'Mantenimiento', 'Baja') DEFAULT 'Disponible',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO equipos (nombre, descripcion, estado) VALUES
('Microscopio Óptico', 'Microscopio binocular de campo claro. Ideal para biología.', 'Disponible'),
('Centrífuga', 'Centrífuga de mesa de alta velocidad, soporta hasta 15,000 RPM.', 'En Uso'),
('Espectrofotómetro', 'Espectrofotómetro UV-Vis de doble haz. Marca ZXY.', 'Mantenimiento');
