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
    if ((strpos($class, "\mitalteli\\")) && (!preg_match('/ResearchTasksReportModule$/', $class))) {
        $base_NS_len=strlen(__NAMESPACE__)+1;
        $classTmp=substr($class, $base_NS_len);
        $name = dirname(__FILE__) . "/app/" . str_replace('\\', '/',$classTmp) . '.php';
        #error_log("AUTOLOAD CLASS: class:[$class], len:[$base_NS_len], file:[$name]");
        include $name;
    }
}, true, true);  

require __DIR__ . '/app/ResearchTasksReportModule.php';

$moduleService = ResearchTasksReportModule::getClass(ModuleService::class);
return new ResearchTasksReportModule($moduleService);   
