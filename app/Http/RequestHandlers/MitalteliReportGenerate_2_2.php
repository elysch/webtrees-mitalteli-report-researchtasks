<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2025 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Http\RequestHandlers;

use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Report\MitalteliReportParserGenerate_2_1;
use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Report\MitalteliHtmlRenderer;
use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Report\MitalteliObfuscator;
use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Report\MitalteliPdfRenderer_2_1;
use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Http\RequestHandlers\ReportGenerate2205Base;
use Fisharebest\Webtrees\Http\RequestHandlers\ReportListPage;
use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\ResearchTasksReportModule;


use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\ModuleReportInterface;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function addcslashes;
use function ob_get_clean;
use function ob_start;
use function redirect;
use function response;
use function route;

/**
 * Show all available reports.
 */
class MitalteliReportGenerate_2_2 extends ReportGenerate2205Base
{

    public function getFromParentPrivatePropertyWithReflection(string $attribute_name) {
        // 1. Apuntamos a la clase base que REALMENTE declaró la propiedad privada
        $parentClass = 'vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Http\RequestHandlers\ReportGenerate2205Base';
        $reflectionClass = new \ReflectionClass($parentClass);
        
        try {
            $property = $reflectionClass->getProperty($attribute_name);
            $property->setAccessible(true); // Necesario para acceder a 'private'
            
            // 2. Extraemos el valor de la instancia actual ($this) 
            // pero bajo el contexto de la clase que declaramos en el paso 1
            return $property->getValue($this); 
        } catch (\ReflectionException $e) {
            return null;
        }
    }


    /**
     * A list of available reports.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree   = Validator::attributes($request)->tree();
        $user   = Validator::attributes($request)->user();
        $report = Validator::attributes($request)->string('report');
        $module = $this->getFromParentPrivatePropertyWithReflection('module_service')->findByName($report);

        $coreWebtreesCustomizationDir = ResearchTasksReportModule::getCoreWebtreesCustomizationDirectory();

        // Check if the report string contains the module directory path
        // if not running from this module, redirect to the standard ReportGenerate handler
        if (!str_contains($report, $coreWebtreesCustomizationDir)) {
            return parent::{__FUNCTION__}($request);
        }

        if (!$module instanceof ModuleReportInterface) {
            return redirect(route(ReportListPage::class, ['tree' => $tree->name()]));
        }

        Auth::checkComponentAccess($module, ModuleReportInterface::class, $tree, $user);

        $varnames  = Validator::queryParams($request)->array('varnames');
        $vars      = Validator::queryParams($request)->array('vars');
        $variables = [];

        foreach ($varnames as $name) {
            $value = $vars[$name] ?? '';
            // In webtrees, inputs with lookup="DATE" are submitted as an array with
            // sub-keys 'day', 'month', 'year'. Since webtrees 2.2.6, substituteVars()
            // calls addcslashes() on these values and requires them to be strings.
            // Convert any date array to a GEDCOM-format date string (e.g. "15 JAN 2020").
            // Verifies it is an array AND it contains at least one of day, month or year value
            if (version_compare(Webtrees::VERSION, '2.2.6', '>=')) {
                if (is_array($value) && (isset($value['year']) || isset($value['month']) || isset($value['day']))) {
                    $dateParts = array_filter(array_map('trim', [
                        $value['day']   ?? '',
                        $value['month'] ?? '',
                        $value['year']  ?? ''
                    ]), 'strlen');

                    $value = implode(' ', $dateParts);
                }
            }
            $variables[$name]['id'] = $value;
        }

        $xml_filename = $module->resourcesFolder() . $module->xmlFilename();
        $format       = Validator::queryParams($request)->string('format');
        $destination  = Validator::queryParams($request)->string('destination');

        $user->setPreference('default-report-destination', $destination);
        $user->setPreference('default-report-format', $format);

        switch ($format) {
            default:
            case 'HTML':
                ob_start();
                new MitalteliReportParserGenerate_2_1($xml_filename, new MitalteliHtmlRenderer(), $variables, $tree);
                $html = ob_get_clean();

                // DEBUG MODE: add &debug_obfuscate=1 to URL to obfuscate personal data.
                // Replaces each word with random letters of the same length so
                // word-wrap behaves identically to the real content.
                if (($request->getQueryParams()['debug_obfuscate'] ?? '') === '1') {
                    $html = (new MitalteliObfuscator())->obfuscateHtml($html);
                }

                $this->layout = 'layouts/report';

                $response = $this->viewResponse('report-page', [
                    'content' => $html,
                    'title'   => I18N::translate('Report'),
                ]);

                if ($destination === 'download') {
                    $response = $response->withHeader('content-disposition', 'attachment; filename="' . addcslashes($report, '"') . '.html"');
                }

                return $response;

            case 'PDF':
                $pdf_renderer = new MitalteliPdfRenderer_2_1();
                // DEBUG MODE: add &debug_obfuscate=1 to URL to generate an ofuscated PDF.
                if (($request->getQueryParams()['debug_obfuscate'] ?? '') === '1') {
                    $pdf_renderer->debug_obfuscate = true;
                }
                ob_start();
                new MitalteliReportParserGenerate_2_1($xml_filename, $pdf_renderer, $variables, $tree);
                $pdf = ob_get_clean();

                $headers = ['content-type' => 'application/pdf'];

                if ($destination === 'download') {
                    $headers['content-disposition'] = 'attachment; filename="' . addcslashes($report, '"') . '.pdf"';
                }

                return response($pdf, StatusCodeInterface::STATUS_OK, $headers);
        }

        // Fallback: return a generic error response if format is not handled
        return response('Invalid report format', StatusCodeInterface::STATUS_BAD_REQUEST);
    }
}
