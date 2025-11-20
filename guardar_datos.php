<?php
// /mediciones/guardar_datos.php
require_once __DIR__ . '/../calibraciones/config.php';
require_auth('admin'); // solo admins pueden registrar

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Método no permitido");
}

$pdo = pdo();
$fecha = trim($_POST['fecha'] ?? '');
$turno = (int)($_POST['turno'] ?? 0);
$mediciones = $_POST['mediciones'] ?? [];

// Validaciones básicas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    exit("Fecha inválida");
}
if ($turno !== 1 && $turno !== 2) {
    exit("Turno inválido");
}
if (!is_array($mediciones) || count($mediciones) === 0) {
    exit("No hay mediciones");
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("
        INSERT INTO registros (fecha, turno, posicion, particulas_0_5_um, particulas_5_0_um)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($mediciones as $m) {
        $posicion = trim($m['posicion'] ?? '');
        $p05 = (int)($m['particulas_0_5_um'] ?? 0);
        $p50 = (int)($m['particulas_5_0_um'] ?? 0);

        // Validar posición
        if (!in_array($posicion, ['Corner1','Corner2','Corner3','Corner4','Middle'], true)) {
            throw new Exception("Posición inválida: " . htmlspecialchars($posicion));
        }
        if ($p05 < 0 || $p50 < 0) {
            throw new Exception("Valores de partículas no pueden ser negativos");
        }

        $stmt->execute([$fecha, $turno, $posicion, $p05, $p50]);
    }

    $pdo->commit();
    header("Location: index.html?success=1");
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error al guardar mediciones: " . $e->getMessage());
    http_response_code(500);
    exit("Error al guardar los datos. Inténtalo más tarde.");
}
