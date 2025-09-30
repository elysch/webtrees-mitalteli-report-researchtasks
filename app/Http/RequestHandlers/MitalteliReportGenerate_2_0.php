<?php

namespace vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Http\RequestHandlers;

use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Report\MitalteliReportParserGenerate_2_0;
use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Report\MitalteliHtmlRenderer;
use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Report\MitalteliPdfRenderer_2_0;
use Fisharebest\Webtrees\Http\RequestHandlers\ReportGenerate;
use Fisharebest\Webtrees\Http\RequestHandlers\ReportListPage;


use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\ModuleReportInterface;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Report\HtmlRenderer;
use Fisharebest\Webtrees\Report\PdfRenderer;
use Fisharebest\Webtrees\Report\ReportParserGenerate;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Tree;
use Psr\Http\Server\RequestHandlerInterface;

use function addcslashes;
use function assert;
use function ob_get_clean;
use function ob_start;
use function redirect;
use function response;
use function route;


/**
 * Show all available reports.
 */
class MitalteliReportGenerate_2_0 extends ReportGenerate
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
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $user = $request->getAttribute('user');
        assert($user instanceof UserInterface);

        $data_filesystem = Registry::filesystem()->data();

        $report = $request->getAttribute('report');
        $module = $this->getFromParentPrivatePropertyWithReflection('module_service')->findByName($report);

        if (!$module instanceof ModuleReportInterface) {
            return redirect(route(ReportListPage::class, ['tree' => $tree->name()]));
        }

        Auth::checkComponentAccess($module, ModuleReportInterface::class, $tree, $user);

        $varnames  = $request->getQueryParams()['varnames'] ?? [];
        $vars      = $request->getQueryParams()['vars'] ?? [];
        $variables = [];

        foreach ($varnames as $name) {
            $variables[$name]['id'] = $vars[$name] ?? '';
        }

        $xml_filename = $module->resourcesFolder() . $module->xmlFilename();

        $format      = $request->getQueryParams()['format'] ?? '';
        $destination = $request->getQueryParams()['destination'] ?? '';

        $user->setPreference('default-report-destination', $destination);
        $user->setPreference('default-report-format', $format);

        switch ($format) {
            default:
            case 'HTML':
                ob_start();
                new MitalteliReportParserGenerate_2_0($xml_filename, new MitalteliHtmlRenderer(), $variables, $tree, $data_filesystem);
                $html = ob_get_clean();

                $this->layout = 'layouts/report';

                $response = $this->viewResponse('report-page', [
                    'content' => $html,
                    'title'   => I18N::translate('Report'),
                ]);

                if ($destination === 'download') {
                    $response = $response->withHeader('Content-Disposition', 'attachment; filename="' . addcslashes($report, '"') . '.html"');
                }

                return $response;

            case 'PDF':
                ob_start();
                new MitalteliReportParserGenerate_2_0($xml_filename, new MitalteliPdfRenderer_2_0(), $variables, $tree, $data_filesystem);
                $pdf = ob_get_clean();

                $headers = ['Content-Type' => 'application/pdf'];

                if ($destination === 'download') {
                    $headers['Content-Disposition'] = 'attachment; filename="' . addcslashes($report, '"') . '.pdf"';
                }

                return response($pdf, StatusCodeInterface::STATUS_OK, $headers);
        }

        // Fallback: return a generic error response if format is not handled
        return response('Invalid report format', StatusCodeInterface::STATUS_BAD_REQUEST);
    }
}
