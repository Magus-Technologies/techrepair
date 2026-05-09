<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireLogin();

$pageTitle = 'Test Footer';
require_once __DIR__ . '/../../includes/header.php';
?>

<h1>TEST FOOTER</h1>
<p>Si ves el script en consola, el footer funciona.</p>

<?php
$pageScripts = <<<'JS'
<script>
console.log('=== FOOTER FUNCIONA ===');
alert('El footer está funcionando correctamente');
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
