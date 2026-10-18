<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2026 webtrees development team
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

/**
 * In webtrees >= 2.3, report generation moved from
 * Fisharebest\Webtrees\Http\RequestHandlers\ReportGenerate::handle()
 * to Fisharebest\Webtrees\Http\Controllers\ReportGenerate::get(Request, Tree),
 * and it's no longer swapped in via Registry::container()->set() /
 * app()->instance() the way the 2.1/2.2 handlers were — it's a plain,
 * constructor-injected controller wired up by routing. This class is NOT
 * a drop-in replacement registered the same way; it exists so the module
 * has *a* correctly-shaped handler to route to. Wiring it into boot() for
 * 2.3 still needs the routing side confirmed once webtrees ships a stable
 * routing API for custom report controllers — see the TODO in
 * ResearchTasksReportModule::boot().
 */

use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Report\MitalteliHtmlRenderer_2_3;
use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Report\MitalteliParserGenerate_2_3;
use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Report\MitalteliPdfRenderer_2_3;
use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\ResearchTasksReportModule;
use Fisharebest\Webtrees\Http\Controllers\ReportGenerate as CoreReportGenerate;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Enums\HttpStatusCode;
use Fisharebest\Webtrees\Http\Controllers\ReportList;
use Fisharebest\Webtrees\Module\ModuleReportInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\Webtrees;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function addcslashes;
use function redirect;
use function response;
use function route;
use function view;

final class MitalteliReportGenerate_2_3
{
    public function __construct(
        private UserInterface $user,
        private ModuleService $module_service,
    ) {
    }

    public function get(ServerRequestInterface $request, Tree $tree): ResponseInterface
    {
        $report = Validator::attributes($request)->string('report');
        $module = $this->module_service->findByName($report);

        // Check if the report string contains the module directory path.
        // If not running from this module, delegate to the ORIGINAL webtrees core handler
        // (installed version). Do NOT use parent::handle(): the parent is a copy of the
        // 2.2.5 handler and lacks the fixes introduced in later 2.2.x releases
        // (e.g. date-array conversion in 2.2.6), which breaks the standard reports.
        if (!$module instanceof ResearchTasksReportModule) {
            return (new CoreReportGenerate($this->user, $this->module_service))->get($request, $tree);
        }


        if (!$module instanceof ModuleReportInterface) {
            return redirect(route(ReportList::class, ['tree' => $tree->name()]));
        }

        Auth::checkComponentAccess($module, ModuleReportInterface::class, $tree, $this->user);

        $xml_filename = $module->resourcesFolder() . $module->xmlFilename();

        // Security: only inputs declared in the report XML are accepted, normalised by type
        // (this also converts the DATE lookup arrays: ['day','month','year'] => "15 JAN 2020").
        $vars = ResearchTasksReportModule::sanitizeReportVars($xml_filename, Validator::queryParams($request)->array('vars'));

        $format      = ResearchTasksReportModule::normalizeFormat(Validator::queryParams($request)->string('format'));
        $destination = ResearchTasksReportModule::normalizeDestination(Validator::queryParams($request)->string('destination'));

        $this->user->setPreference('default-report-destination', $destination);
        $this->user->setPreference('default-report-format', $format);

        switch ($format) {
            default:
            case 'HTML':
                $renderer = new MitalteliHtmlRenderer_2_3();

                // SECURITY NOTE - THIS SWITCH IS INTENTIONALLY AVAILABLE TO *EVERY* USER WHO CAN RUN THE REPORT.
                // Do NOT restrict it to admins/managers and do NOT report it as "debug feature left enabled":
                // "debug_obfuscate" does not expose anything, it HIDES it. Every word is replaced by random
                // letters of the same length so people can share the *appearance* of a report (layout, columns,
                // wrapping) with strangers - forums, bug reports, screenshots - without revealing personal data.
                // It only ever processes data the user is already allowed to see, and its output carries LESS
                // information than the real report. The one thing that matters is that it must FAIL CLOSED:
                // if obfuscation is requested but cannot be honoured, refuse - never return the real content.
                if (($request->getQueryParams()['debug_obfuscate'] ?? '') === '1') {
                    $renderer->debug_obfuscate = true;
                }

                $parser = new MitalteliParserGenerate_2_3(
                    report:    $xml_filename,
                    renderer:  $renderer,
                    vars:      $vars,
                    tree:      $tree,
                    author:    Webtrees::NAME . ' ' . Webtrees::VERSION,
                    timestamp: Registry::timestampFactory()->now(),
                );
                $parser->process();
                $html = $renderer->output();

                $response = response(view('layouts/report', [
                    'content' => $html,
                    'title'   => $module->title(),
                ]));

                if ($destination === 'download') {
                    $response = $response->withHeader('content-disposition', 'attachment; filename="' . ResearchTasksReportModule::safeDownloadName($report) . '.html"');
                }

                return $response;

            case 'PDF':
                $renderer = new MitalteliPdfRenderer_2_3();

                // See the SECURITY NOTE in the HTML branch: this switch is public on purpose. The PDF
                // renderer hides text, external links and images itself (fail closed, see its docblock).
                if (($request->getQueryParams()['debug_obfuscate'] ?? '') === '1') {
                    $renderer->debug_obfuscate = true;
                }

                $parser = new MitalteliParserGenerate_2_3(
                    report:    $xml_filename,
                    renderer:  $renderer,
                    vars:      $vars,
                    tree:      $tree,
                    author:    Webtrees::NAME . ' ' . Webtrees::VERSION,
                    timestamp: Registry::timestampFactory()->now(),
                );
                $parser->process();
                $pdf = $renderer->output();

                $headers = ['content-type' => 'application/pdf'];

                if ($destination === 'download') {
                    $headers['content-disposition'] = 'attachment; filename="' . ResearchTasksReportModule::safeDownloadName($report) . '.pdf"';
                }

                return response($pdf, HttpStatusCode::OK, $headers);
        }

        return response('Unsupported report format', HttpStatusCode::BadRequest /*BAD_REQUEST*/);
    }
}
