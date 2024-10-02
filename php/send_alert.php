<?php
include('C:/xampp/htdocs/MonitoringDashboard/config/config.php');
require 'C:/xampp/htdocs/MonitoringDashboard/phpmailer/src/PHPMailer.php';
require 'C:/xampp/htdocs/MonitoringDashboard/phpmailer/src/SMTP.php';
require 'C:/xampp/htdocs/MonitoringDashboard/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

$sql = "SELECT * FROM measurements WHERE timestamp >= NOW() - INTERVAL 2 HOUR ORDER BY timestamp ASC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $outOfRange = false;
    $message = 'Se detectaron los siguientes valores fuera de rango:<br><br>';
    $messageText = 'Se detectaron los siguientes valores fuera de rango:\n\n';

    while ($row = $result->fetch_assoc()) {
        $timestamp = $row['timestamp'];
        $humidity = $row['humidity'];
        $temperature = $row['temperature'];

        if ($humidity < 30 || $humidity > 75) {
            $message .= "Humedad fuera de rango: " . $humidity . "% a las " . $timestamp . "<br>";
            $messageText .= "Humedad fuera de rango: " . $humidity . "% a las " . $timestamp . "\n";
            $outOfRange = true;
        }
        if ($temperature < 10 || $temperature > 30) {
            $message .= "Temperatura fuera de rango: " . $temperature . "°C a las " . $timestamp . "<br>";
            $messageText .= "Temperatura fuera de rango: " . $temperature . "°C a las " . $timestamp . "\n";
            $outOfRange = true;
        }
    }

    if ($outOfRange) {
        // Enviar el correo
        $mail = new PHPMailer(true);
        try {
            // Configuración del servidor
            $mail->isSMTP();
            $mail->Host = 'smtp.exmail.qq.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'jesus.muro@zhongli-la.com';
            $mail->Password = 'Chuy.12#$'; // Reemplaza con tu contraseña real
            $mail->SMTPSecure = 'ssl';
            $mail->Port = 465;

            // Destinatarios
            $mail->setFrom('jesus.muro@zhongli-la.com', 'Monitoring Dashboard');
            $mail->addAddress('jesus.muro@xinya-la.com','ingenieria@zhongli-la.com','rene.pineda@zhongli-la.com','calidad@zhongli-la.com',);

            // Contenido del correo
            $mail->isHTML(true);
            $mail->Subject = 'Alerta de Sensor';
            $mail->Body    = $message . "<br>Por favor, tome medidas para corregir el problema.";
            $mail->AltBody = $messageText . "\nPor favor, tome medidas para corregir el problema.";

            $mail->send();
            echo 'Correo enviado exitosamente.';
        } catch (Exception $e) {
            echo "Error al enviar el correo: {$mail->ErrorInfo}";
        }
    } else {
        echo "No se encontraron valores fuera de rango.";
    }
} else {
    echo "No se encontraron datos en las últimas 2 horas.";
}

$conn->close();
?>
