<?php
// /mediciones/dashboard.php
include 'conexion.php';

$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';

$sql = "SELECT * FROM registros";
if ($fecha_inicio && $fecha_fin) {
    $sql .= " WHERE fecha BETWEEN '$fecha_inicio' AND '$fecha_fin'";
}

$result = $conn->query($sql);

$data = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
} else {
    echo "<p style='color: red;'>No hay datos disponibles para el rango de fechas seleccionado</p>";
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Dust</title>
    <style>
        body {
            background-color: #121212;
            color: #ffffff;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 1.2rem;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            overflow: hidden;
        }

        .container {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            box-sizing: border-box;
        }

        h1 {
            text-align: center;
            margin-bottom: 20px;
            font-size: 2.5rem;
        }

        form {
        display: flex;
        justify-content: center;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        background-color: #2c2c2c;
        padding: 20px;
        border-radius: 10px;
        }

        form label {
        margin: 10px;
        font-size: 1.2rem;
        }

        form input[type="date"] {
        padding: 10px;
        border: 1px solid #444;
        border-radius: 5px;
        background-color: #3c3c3c;
        color: #ffffff;
        margin: 10px;
        }

        form input[type="submit"] {
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        background-color: #6200ea;
        color: #ffffff;
        cursor: pointer;
        font-size: 1.2rem;
        margin: 10px;
        transition: background-color 0.3s;
        }

        form input[type="submit"]:hover {
        background-color: #3700b3;
        }

        .charts {
            display: flex;
            justify-content: space-between;
            width: 100%;
            height: 60%;
        }

        .chart-container {
            position: relative;
            width: 48%;
            height: 100%;
        }

        canvas {
            background-color: #1e1e1e;
            border-radius: 10px;
            width: 100%;
            height: 100%;
        }

        .indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: green;
        }

        footer {
            text-align: center;
            padding: 10px;
            background-color: #1e1e1e;
            border-top: 1px solid #333;
            color: #888;
            font-size: 1rem;
            width: 100%;
            position: absolute;
            bottom: 0;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@1.0.0"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@1.0.2"></script>
</head>
<body>
    <div class="container">
        <h1>Medicion de particulas en ATO</h1>
        <form method="GET" action="dashboard.php">
            <label for="fecha_inicio">Fecha Inicio:</label>
            <input type="date" id="fecha_inicio" name="fecha_inicio" required value="<?php echo $fecha_inicio; ?>">
            
            <label for="fecha_fin">Fecha Fin:</label>
            <input type="date" id="fecha_fin" name="fecha_fin" required value="<?php echo $fecha_fin; ?>">
            
            <input type="submit" value="Filtrar">
        </form>
        
        <div class="charts">
            <div class="chart-container">
                <canvas id="chart0_5"></canvas>
            </div>
            <div class="chart-container">
                <canvas id="chart5_0"></canvas>
            </div>
        </div>
    </div>

    <footer>
        © 2024 Argmand Inc. Todos los derechos reservados.
    </footer>

    <script>
        const data = <?php echo json_encode($data); ?>;
        const MAX_0_5_UM = 10500000;
        const MAX_5_0_UM = 87900;

        const posiciones = ["Corner1", "Corner2", "Corner3", "Corner4", "Middle"];

        const datasets0_5 = posiciones.map(pos => {
            return {
                label: pos,
                data: data.filter(d => d.posicion === pos).map(d => ({x: d.fecha, y: parseFloat(d.particulas_0_5_um)})),
                borderColor: getColor(pos),
                borderWidth: 2,
                fill: false
            };
        });

        const datasets5_0 = posiciones.map(pos => {
            return {
                label: pos,
                data: data.filter(d => d.posicion === pos).map(d => ({x: d.fecha, y: parseFloat(d.particulas_5_0_um)})),
                borderColor: getColor(pos),
                borderWidth: 2,
                fill: false
            };
        });

        function getColor(position) {
            switch(position) {
                case "Corner1": return 'rgba(255, 99, 132, 1)';
                case "Corner2": return 'rgba(54, 162, 235, 1)';
                case "Corner3": return 'rgba(255, 206, 86, 1)';
                case "Corner4": return 'rgba(75, 192, 192, 1)';
                case "Middle": return 'rgba(153, 102, 255, 1)';
                default: return 'rgba(201, 203, 207, 1)';
            }
        }

        new Chart(document.getElementById('chart0_5'), {
            type: 'line',
            data: {
                datasets: datasets0_5
            },
            options: {
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day',
                            tooltipFormat: 'DD/MM/YYYY'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        min: 0,
                        max: MAX_0_5_UM * 1.1, // slightly above the maximum to provide some space
                        ticks: {
                            callback: function(value) {
                                return value.toFixed(2);
                            }
                        }
                    }
                },
                plugins: {
                    annotation: {
                        annotations: {
                            maxLine0_5: {
                                type: 'line',
                                yMin: MAX_0_5_UM,
                                yMax: MAX_0_5_UM,
                                borderColor: 'rgba(255, 0, 0, 0.5)',
                                borderWidth: 2,
                                label: {
                                    content: 'Límite Máximo (0.5 µm)',
                                    enabled: true,
                                    position: 'end',
                                    backgroundColor: 'rgba(255, 0, 0, 0.5)'
                                }
                            }
                        }
                    }
                }
            }
        });

        new Chart(document.getElementById('chart5_0'), {
            type: 'line',
            data: {
                datasets: datasets5_0
            },
            options: {
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day',
                            tooltipFormat: 'DD/MM/YYYY'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        min: 0,
                        max: MAX_5_0_UM * 1.1, // slightly above the maximum to provide some space
                        ticks: {
                            callback: function(value) {
                                return value.toFixed(2);
                            }
                        }
                    }
                },
                plugins: {
                    annotation: {
                        annotations: {
                            maxLine5_0: {
                                type: 'line',
                                yMin: MAX_5_0_UM,
                                yMax: MAX_5_0_UM,
                                borderColor: 'rgba(255, 0, 0, 0.5)',
                                borderWidth: 2,
                                label: {
                                    content: 'Límite Máximo (5.0 µm)',
                                    enabled: true,
                                    position: 'end',
                                    backgroundColor: 'rgba(255, 0, 0, 0.5)'
                                }
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
