document.addEventListener('DOMContentLoaded', () => {
    // Selección de elementos HTML
    const humidityElement = document.getElementById('humidity');
    const temperatureElement = document.getElementById('temperature');
    const humidityChartCtx = document.getElementById('humidityChart').getContext('2d');
    const temperatureChartCtx = document.getElementById('temperatureChart').getContext('2d');
    const humidityIndicator = document.getElementById('humidityIndicator');
    const temperatureIndicator = document.getElementById('temperatureIndicator');

    // Función para crear una nueva gráfica con configuraciones específicas
    const createChart = (ctx, label, yTitle) => {
        return new Chart(ctx, {
            type: 'line',
            data: {
                labels: [], // Etiquetas del eje x, inicialmente vacío
                datasets: [
                    {
                        label: label,
                        data: [], // Datos para la línea principal
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderWidth: 2,
                        fill: true,
                    },
                    {
                        label: 'Límite Inferior',
                        data: [], // Datos para el límite inferior
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1,
                        borderDash: [10, 5], // Línea punteada
                        fill: false,
                    },
                    {
                        label: 'Límite Superior',
                        data: [], // Datos para el límite superior
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1,
                        borderDash: [10, 5], // Línea punteada
                        fill: false,
                    }
                ],
            },
            options: {
                responsive: true, // La gráfica es responsiva
                plugins: {
                    legend: { position: 'top' }, // Posición de la leyenda
                    title: { display: true, text: `Gráfica de ${label}` }, // Título de la gráfica
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        min: 0, // Mínimo del eje y
                        max: 80, // Máximo del eje y
                        title: { display: true, text: yTitle }, // Título del eje y
                    },
                    x: { title: { display: true, text: 'Tiempo' } }, // Título del eje x
                },
            },
        });
    };

    // Crear las gráficas de humedad y temperatura con configuraciones específicas
    const humidityChart = createChart(humidityChartCtx, 'Humedad (%)', 'Porcentaje (%)');
    const temperatureChart = createChart(temperatureChartCtx, 'Temperatura (°C)', 'Grados Celsius (°C)');

    // Función para actualizar los datos de las gráficas y los elementos HTML
    const updateData = async () => {
        try {
            // Obtener los datos desde el servidor
            const response = await fetch('dashboard.php');
            if (!response.ok) throw new Error('Network response was not ok');
            const data = await response.json();

            // Procesar los datos obtenidos
            const labels = data.map(entry => new Date(entry.timestamp).toLocaleTimeString()); // Convertir timestamp a hora legible
            const humidityData = data.map(entry => entry.humidity); // Extraer datos de humedad
            const temperatureData = data.map(entry => entry.temperature); // Extraer datos de temperatura

            // Actualizar elementos HTML con los últimos valores
            humidityElement.textContent = `${humidityData[humidityData.length - 1]}%`;
            temperatureElement.textContent = `${temperatureData[temperatureData.length - 1]}°C`;

            // Actualizar indicadores visuales de humedad y temperatura
            updateIndicator(humidityIndicator, humidityData[humidityData.length - 1], 30, 75);
            updateIndicator(temperatureIndicator, temperatureData[temperatureData.length - 1], 10, 30);

            // Actualizar los datos de las gráficas
            updateChart(humidityChart, labels, humidityData, 30, 75);
            updateChart(temperatureChart, labels, temperatureData, 10, 30);
        } catch (error) {
            console.error('Error al obtener los datos:', error); // Manejo de errores
        }
    };

    // Función para actualizar los datos de una gráfica específica
    const updateChart = (chart, labels, data, lowerLimit, upperLimit) => {
        chart.data.labels = labels;
        chart.data.datasets[0].data = data;
        chart.data.datasets[1].data = Array(data.length).fill(lowerLimit);
        chart.data.datasets[2].data = Array(data.length).fill(upperLimit);
        chart.update();
    };

    // Función para actualizar el indicador visual de un valor específico
    const updateIndicator = (indicator, value, lowerLimit, upperLimit) => {
        if (value < lowerLimit || value > upperLimit) {
            indicator.style.backgroundColor = 'red'; // Fuera de los límites
        } else if (value < lowerLimit + 0.1 * lowerLimit || value > upperLimit - 0.1 * upperLimit) {
            indicator.style.backgroundColor = 'yellow'; // Cerca de los límites
        } else {
            indicator.style.backgroundColor = 'green'; // Dentro de los límites
        }
    };

    // Actualiza los datos cada 60 segundos
    setInterval(updateData, 60000);
    updateData(); // Llama a la función de actualización inmediatamente
});
