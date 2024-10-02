<?php
// /mediciones/guardar_datos.php
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fecha = $_POST['fecha'];
    $turno = $_POST['turno'];
    $mediciones = $_POST['mediciones'];

    foreach ($mediciones as $medicion) {
        $posicion = $medicion['posicion'];
        $particulas_0_5_um = $medicion['particulas_0_5_um'];
        $particulas_5_0_um = $medicion['particulas_5_0_um'];

        $sql = "INSERT INTO registros (fecha, turno, posicion, particulas_0_5_um, particulas_5_0_um)
                VALUES ('$fecha', $turno, '$posicion', $particulas_0_5_um, $particulas_5_0_um)";

        if (!$conn->query($sql) === TRUE) {
            echo "Error: " . $sql . "<br>" . $conn->error;
            exit();
        }
    }
    $conn->close();
    header("Location: index.html");
    exit();
} else {
    echo "Método no permitido";
}
?>
