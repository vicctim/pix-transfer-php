<?php
session_start();

// Simple test form
$currentStep = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST data received: " . print_r($_POST, true));
    
    if (isset($_POST['next_step'])) {
        $nextStep = (int)$_POST['next_step'];
        error_log("Next step requested: " . $nextStep);
        header("Location: /setup_simple?step=" . $nextStep);
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Setup Simples - Etapa <?php echo $currentStep; ?></title>
</head>
<body>
    <h1>Setup Simples - Etapa <?php echo $currentStep; ?> de 3</h1>
    
    <form method="POST" action="/setup_simple?step=<?php echo $currentStep; ?>">
        <p>Esta é a etapa <?php echo $currentStep; ?>.</p>
        
        <?php if ($currentStep < 3): ?>
            <button type="submit" name="next_step" value="<?php echo $currentStep + 1; ?>">Próximo</button>
        <?php else: ?>
            <p>Fim!</p>
        <?php endif; ?>
    </form>
</body>
</html>