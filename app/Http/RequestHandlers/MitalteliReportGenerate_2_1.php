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
use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Report\MitalteliPdfRenderer_2_1;
use Fisharebest\Webtrees\Http\RequestHandlers\ReportGenerate;
use Fisharebest\Webtrees\Http\RequestHandlers\ReportListPage;


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
class MitalteliReportGenerate_2_1 extends ReportGenerate
{

    public function getFromParentPrivatePropertyWithReflection(string $attribute_name) {
        $reflectionClass = new \ReflectionClass('Fisharebest\Webtrees\Http\RequestHandlers\ReportGenerate');
        $property = $reflectionClass->getProperty($attribute_name);
        $property->setAccessible(true); // Make the private property accessible
        return $property->getValue($this); // Get the value from the current object instance
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

        #error_log((new \ReflectionClass($this))->getShortName() . "-" . __FUNCTION__ .
        #    "-HHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHH");

        $tree   = Validator::attributes($request)->tree();
        $user   = Validator::attributes($request)->user();
        $report = Validator::attributes($request)->string('report');
        $module = $this->getFromParentPrivatePropertyWithReflection('module_service')->findByName($report);

        if (!$module instanceof ModuleReportInterface) {
            return redirect(route(ReportListPage::class, ['tree' => $tree->name()]));
        }

        Auth::checkComponentAccess($module, ModuleReportInterface::class, $tree, $user);

        $varnames  = Validator::queryParams($request)->array('varnames');
        $vars      = Validator::queryParams($request)->array('vars');
        $variables = [];

        foreach ($varnames as $name) {
            $variables[$name]['id'] = $vars[$name] ?? '';
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
                ob_start();
                new MitalteliReportParserGenerate_2_1($xml_filename, new MitalteliPdfRenderer_2_1(), $variables, $tree);
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
