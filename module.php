<?php

declare(strict_types=1);

/**
 * Research tasks report.
 */

namespace vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace;          

use Fisharebest\Webtrees\Services\ModuleService;
use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\ResearchTasksReportModule;          

// Auto-load class files
spl_autoload_register(function ($class) {
    $prefix = __NAMESPACE__ . '\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0 || preg_match('/ResearchTasksReportModule$/', $class)) {
        return;
    }

    $relative = substr($class, strlen($prefix));

    // Defensive: class names only (letters, digits, underscore and namespace separators).
    if (preg_match('/^[A-Za-z0-9_\\\\]+$/', $relative) !== 1) {
        return;
    }

    $name = __DIR__ . '/app/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($name)) {
        require $name;
    }
}, true, true);  

require __DIR__ . '/app/ResearchTasksReportModule.php';

$moduleService = ResearchTasksReportModule::getClass(ModuleService::class);
return new ResearchTasksReportModule($moduleService);   
