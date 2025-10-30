<?php
// /mediciones/conexion.php
$servername = "localhost";
$username = "jmuro";
$password = "Monday.03";
$dbname = "mediciones_particulas";

// Crear la conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar la conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
?>
