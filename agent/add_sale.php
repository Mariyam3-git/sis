<?php
// Dedicated Agent Add Customer / Sale page.
// Sales and Dashboard share one maintained controller so shift rules,
// duplicate protection, calculations, and transaction actions stay identical.
$sisAgentPageMode = 'sales';
require __DIR__ . '/dashboard.php';
