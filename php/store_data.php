<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sensor_data";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
  die("Conexión fallida: " . $conn->connect_error);
}

// Verificar si se enviaron los datos
if (isset($_POST['humidity']) && isset($_POST['temperature'])) {
  $humidity = $_POST['humidity'];
  $temperature = $_POST['temperature'];

  $sql = "INSERT INTO measurements (humidity, temperature) VALUES ('$humidity', '$temperature')";

  if ($conn->query($sql) === TRUE) {
    echo "Nuevo registro creado exitosamente";
  } else {
    echo "Error: " . $sql . "<br>" . $conn->error;
  }
} else {
  echo "Datos no recibidos";
}

$conn->close();
?>