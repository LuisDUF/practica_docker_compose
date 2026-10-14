<?php
// Configuración de encabezados para API REST
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Credenciales desde variables de entorno (.env)
$db_host = getenv('DB_HOST') ?: 'db';
$db_name = getenv('DB_NAME') ?: 'laboratorio';
$db_user = getenv('DB_USER') ?: 'lab_user';
$db_pass = getenv('DB_PASS') ?: 'lab_password';

$redis_host = getenv('REDIS_HOST') ?: 'redis';
$redis_port = getenv('REDIS_PORT') ?: 6379;

function getDbConnection() {
    global $db_host, $db_name, $db_user, $db_pass;
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        return null;
    }
}

function getRedisConnection() {
    global $redis_host, $redis_port;
    try {
        $redis = new Redis();
        $redis->connect($redis_host, $redis_port);
        return $redis;
    } catch(Exception $e) {
        return null;
    }
}

// 1. Endpoint Health
if ($uri === '/api/health') {
    $db = getDbConnection();
    $redis = getRedisConnection();

    $status = [
        'status' => 'ok',
        'mariadb' => $db ? 'connected' : 'disconnected',
        'redis' => $redis ? 'connected' : 'disconnected'
    ];

    if (!$db || !$redis) {
        http_response_code(503);
        $status['status'] = 'error';
    } else {
        http_response_code(200);
    }
    echo json_encode($status);
    exit();
}

// Función para enviar correo mediante socket SMTP directo a Mailpit (puerto 1025)
function sendSmtpEmail($to, $subject, $body) {
    $smtp_host = getenv('SMTP_HOST') ?: 'mailpit';
    $smtp_port = getenv('SMTP_PORT') ?: 1025;

    $socket = @fsockopen($smtp_host, (int)$smtp_port, $errno, $errstr, 5);
    if (!$socket) {
        error_log("Error conectando a Mailpit SMTP ($smtp_host:$smtp_port): $errstr ($errno)");
        return false;
    }

    $read = function() use ($socket) {
        $data = "";
        while ($str = fgets($socket, 512)) {
            $data .= $str;
            if (substr($str, 3, 1) === " ") break;
        }
        return $data;
    };

    $send = function($cmd) use ($socket, $read) {
        fputs($socket, $cmd . "\r\n");
        return $read();
    };

    $read(); // Bienvenida de Mailpit
    $send("HELO localhost");
    $send("MAIL FROM: <no-reply@laboratorio.local>");
    $send("RCPT TO: <$to>");
    $send("DATA");

    $headers = "From: Sistema Laboratorio <no-reply@laboratorio.local>\r\n" .
               "To: $to\r\n" .
               "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n" .
               "MIME-Version: 1.0\r\n" .
               "Content-Type: text/plain; charset=UTF-8\r\n\r\n";

    $send($headers . $body . "\r\n.");
    $send("QUIT");
    fclose($socket);
    return true;
}

// 2. Endpoints CRUD y Búsqueda
if (preg_match('/^\/api\/equipos(\/([0-9]+))?$/', $uri, $matches)) {
    $id = isset($matches[2]) ? (int)$matches[2] : null;
    $method = $_SERVER['REQUEST_METHOD'];
    $db = getDbConnection();
    $redis = getRedisConnection();

    if (!$db) {
        http_response_code(500);
        echo json_encode(["message" => "Error de conexión a MariaDB"]);
        exit();
    }

    // Función para invalidar todas las cachés de búsquedas/listados
    $invalidarCache = function() use ($redis) {
        if ($redis) {
            $keys = $redis->keys("equipos_lista_*");
            if (!empty($keys)) {
                // Redis extensión requiere pasar array o múltiples argumentos
                call_user_func_array([$redis, 'del'], $keys);
            }
        }
    };

    switch ($method) {
        case 'GET':
            if ($id) {
                // Mostrar un equipo por ID
                $stmt = $db->prepare("SELECT * FROM equipos WHERE id = ?");
                $stmt->execute([$id]);
                $equipo = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($equipo) {
                    echo json_encode($equipo);
                } else {
                    http_response_code(404);
                    echo json_encode(["message" => "Equipo no encontrado"]);
                }
            } else {
                // Listar equipos (Filtro de búsqueda implementado aquí)
                $buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
                $cache_key = "equipos_lista_" . md5($buscar);

                // Contador en Redis
                if ($redis) {
                    $visitas = $redis->incr("equipos_visitas");
                } else {
                    $visitas = 0;
                }

                // Verificar caché
                if ($redis && $redis->exists($cache_key)) {
                    $equipos = json_decode($redis->get($cache_key), true);
                    echo json_encode(["data" => $equipos, "cached" => true, "visitas" => $visitas]);
                    exit();
                }

                // Consulta a MariaDB (con filtro si aplica)
                if ($buscar !== '') {
                    $stmt = $db->prepare("SELECT * FROM equipos WHERE nombre LIKE ? OR descripcion LIKE ? ORDER BY id DESC");
                    $searchTerm = "%$buscar%";
                    $stmt->execute([$searchTerm, $searchTerm]);
                } else {
                    $stmt = $db->query("SELECT * FROM equipos ORDER BY id DESC");
                }
                
                $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Guardar en caché por 30 segundos
                if ($redis) {
                    $redis->setex($cache_key, 30, json_encode($equipos));
                }

                echo json_encode(["data" => $equipos, "cached" => false, "visitas" => $visitas]);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);
            if (!empty($data['nombre'])) {
                $stmt = $db->prepare("INSERT INTO equipos (nombre, descripcion, estado) VALUES (?, ?, ?)");
                $stmt->execute([
                    $data['nombre'], 
                    $data['descripcion'] ?? '', 
                    $data['estado'] ?? 'Disponible'
                ]);
                $nuevo_id = $db->lastInsertId();

                $invalidarCache(); // Invalidamos caché tras la inserción

                // Envío de correo directo a Mailpit vía SMTP
                $to = "admin@laboratorio.local";
                $subject = "Nuevo Equipo Registrado: " . $data['nombre'];
                $message = "Se ha registrado un nuevo equipo en el sistema.\n\n" .
                           "Nombre: " . $data['nombre'] . "\n" .
                           "Descripción: " . ($data['descripcion'] ?? 'N/A') . "\n" .
                           "Estado: " . ($data['estado'] ?? 'Disponible') . "\n" .
                           "Fecha: " . date('Y-m-d H:i:s');
                
                sendSmtpEmail($to, $subject, $message);

                http_response_code(201);
                echo json_encode(["message" => "Equipo creado exitosamente", "id" => $nuevo_id]);
            } else {
                http_response_code(400);
                echo json_encode(["message" => "Datos incompletos. 'nombre' es requerido."]);
            }
            break;

        case 'PUT':
            if ($id) {
                $data = json_decode(file_get_contents("php://input"), true);
                $stmt = $db->prepare("UPDATE equipos SET nombre = ?, descripcion = ?, estado = ? WHERE id = ?");
                $stmt->execute([
                    $data['nombre'], 
                    $data['descripcion'] ?? '', 
                    $data['estado'] ?? 'Disponible',
                    $id
                ]);

                $invalidarCache();

                echo json_encode(["message" => "Equipo actualizado"]);
            }
            break;

        case 'DELETE':
            if ($id) {
                $stmt = $db->prepare("DELETE FROM equipos WHERE id = ?");
                $stmt->execute([$id]);

                $invalidarCache();

                echo json_encode(["message" => "Equipo eliminado"]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(["message" => "Método no permitido"]);
            break;
    }
} else {
    http_response_code(404);
    echo json_encode(["message" => "Ruta no encontrada en la API"]);
}
