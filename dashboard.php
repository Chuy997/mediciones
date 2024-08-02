<?php
// /mediciones/dashboard.php
include 'conexion.php';

$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';

$sql = "SELECT * FROM registros";
$params = [];
if ($fecha_inicio && $fecha_fin) {
    $sql .= " WHERE fecha BETWEEN ? AND ?";
    $params = [$fecha_inicio, $fecha_fin];
}

$stmt = $conn->prepare($sql);
if ($fecha_inicio && $fecha_fin) {
    $stmt->bind_param("ss", ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$data = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
} else {
    $noDataMessage = "<p style='color: red;'>No hay datos disponibles para el rango de fechas seleccionado</p>";
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Dust</title>
    <link rel="stylesheet" href="/mediciones/styles.css">
    <script src="/mediciones/js/moment.min.js"></script>
    <script src="/mediciones/js/chart.min.js"></script>
    <script src="/mediciones/js/chartjs-adapter-moment.min.js"></script>
    <script src="/mediciones/js/chartjs-plugin-annotation.min.js"></script>
</head>
<body>
    <div class="container">
        <h1>Medición de partículas en ATO</h1>
        <form method="GET" action="dashboard.php">
            <label for="fecha_inicio">Fecha Inicio:</label>
            <input type="date" id="fecha_inicio" name="fecha_inicio" required value="<?php echo htmlspecialchars($fecha_inicio, ENT_QUOTES, 'UTF-8'); ?>">
            
            <label for="fecha_fin">Fecha Fin:</label>
            <input type="date" id="fecha_fin" name="fecha_fin" required value="<?php echo htmlspecialchars($fecha_fin, ENT_QUOTES, 'UTF-8'); ?>">
            
            <input type="submit" value="Filtrar">
        </form>
        
        <?php if (isset($noDataMessage)) echo $noDataMessage; ?>

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

        function getColor(position) {
            const colors = {
                "Corner1": 'rgba(255, 99, 132, 1)',
                "Corner2": 'rgba(54, 162, 235, 1)',
                "Corner3": 'rgba(255, 206, 86, 1)',
                "Corner4": 'rgba(75, 192, 192, 1)',
                "Middle": 'rgba(153, 102, 255, 1)',
                "default": 'rgba(201, 203, 207, 1)'
            };
            return colors[position] || colors["default"];
        }

        function createDatasets(data, key) {
            return posiciones.map(pos => ({
                label: pos,
                data: data.filter(d => d.posicion === pos).map(d => ({ x: d.fecha, y: parseFloat(d[key]) })),
                borderColor: getColor(pos),
                borderWidth: 2,
                fill: false
            }));
        }

        document.addEventListener('DOMContentLoaded', () => {
            const ctx0_5 = document.getElementById('chart0_5').getContext('2d');
            const chart0_5 = new Chart(ctx0_5, {
                type: 'line',
                data: {
                    datasets: createDatasets(data, 'particulas_0_5_um')
                },
                options: {
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                unit: 'day',
                                tooltipFormat: 'DD/MM/YYYY'
                            },
                            title: {
                                display: true,
                                text: 'Fecha'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            min: 0,
                            max: MAX_0_5_UM * 1.1,
                            ticks: {
                                callback: function(value) {
                                    return value.toFixed(2);
                                }
                            },
                            title: {
                                display: true,
                                text: 'Partículas 0.5 µm'
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

            const ctx5_0 = document.getElementById('chart5_0').getContext('2d');
            const chart5_0 = new Chart(ctx5_0, {
                type: 'line',
                data: {
                    datasets: createDatasets(data, 'particulas_5_0_um')
                },
                options: {
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                unit: 'day',
                                tooltipFormat: 'DD/MM/YYYY'
                            },
                            title: {
                                display: true,
                                text: 'Fecha'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            min: 0,
                            max: MAX_5_0_UM * 1.1,
                            ticks: {
                                callback: function(value) {
                                    return value.toFixed(2);
                                }
                            },
                            title: {
                                display: true,
                                text: 'Partículas 5.0 µm'
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
        });
    </script>
</body>
</html>
