<?php

declare(strict_types=1);

namespace vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Http\RequestHandlers;

/**
 * Mirrors Fisharebest\Webtrees\Http\Controllers\ReportSetup::get() exactly
 * (that class is `final`, so it can't be extended), swapping ParserSetup
 * for MitalteliParserSetup_2_3. The post() method needed no changes at all
 * -- copied verbatim, purely for convenience so both live in one class and
 * boot() only needs one bind() for Controllers\ReportSetup::class.
 *
 * If core's ReportSetup::get()/post() changes in a later 2.3-dev commit,
 * re-diff this file against it -- this is a full copy, not a delegate, so
 * it will silently drift out of sync with unrelated core changes (new input
 * "lookup" types, view data, etc.) that have nothing to do with this
 * module's custom tags.
 */

use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Report\MitalteliParserSetup_2_3;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Html;
use Fisharebest\Webtrees\Http\Controllers\ReportGenerate as CoreReportGenerateController_2_3;
use Fisharebest\Webtrees\Http\Controllers\ReportList;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\ModuleReportInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Report\PlaceholderExpander;
use Fisharebest\Webtrees\Report\VariableTable;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function redirect;
use function route;

final class MitalteliReportSetup_2_3
{
    use ViewResponseTrait;

    public function __construct(
        private UserInterface $user,
        private ModuleService $module_service,
    ) {
    }

    public function get(ServerRequestInterface $request, Tree $tree): ResponseInterface
    {
        $report = Validator::attributes($request)->string('report');
        $module = $this->module_service->findByName($report);

        if (!$module instanceof ModuleReportInterface) {
            return redirect(route(ReportList::class, ['tree' => $tree->name()]));
        }

        Auth::checkComponentAccess($module, ModuleReportInterface::class, $tree, $this->user);

        $xref = Validator::queryParams($request)->isXref()->string('xref', '');

        // Individual preseleccionado (p. ej. el individuo del usuario autenticado).
        $indi_default = Registry::individualFactory()->make($xref, $tree);

        // Familia preseleccionada: la del propio xref si es una familia; si es un
        // individuo, su primera familia como cónyuge o, si no tiene, la familia
        // de la que es hijo/a. Si no existe ninguna, el selector queda vacío.
        $fam_default = Registry::familyFactory()->make($xref, $tree);
        if ($fam_default === null && $indi_default !== null) {
            $fam_default = $indi_default->spouseFamilies()->first()
                ?? $indi_default->childFamilies()->first();
        }

        $filename = $module->resourcesFolder() . $module->xmlFilename();
        $parser   = new MitalteliParserSetup_2_3($filename);
        $parser->process();
        $description = $parser->reportDescription();
        $title       = $parser->reportTitle();
        $inputs      = [];

        foreach ($parser->reportInputs() as $n => $input) {
            $attributes = [
                'id'    => 'input-' . $n,
                'name'  => 'vars[' . $input->name . ']',
                'class' => $input->type === 'checkbox' ? 'form-control-check' : 'form-control',
            ];

            $control = '';
            $extra   = '';

            switch ($input->lookup) {
                case 'INDI':
                    $control = view('components/select-individual', [
                        'id'         => 'input-' . $n,
                        'name'       => 'vars[' . $input->name . ']',
                        'individual' => $indi_default,
                        'tree'       => $tree,
                        'required'   => true,
                    ]);
                    break;

                case 'FAM':
                    $control = view('components/select-family', [
                        'id'       => 'input-' . $n,
                        'name'     => 'vars[' . $input->name . ']',
                        'family'   => $fam_default,
                        'tree'     => $tree,
                        'required' => true,
                    ]);
                    break;

                case 'SOUR':
                    $control = view('components/select-source', [
                        'id'       => 'input-' . $n,
                        'name'     => 'vars[' . $input->name . ']',
                        'family'   => Registry::sourceFactory()->make($xref, $tree),
                        'tree'     => $tree,
                        'required' => true,
                    ]);
                    break;

                case 'DATE':
                    $dmy = I18N::language()->dateOrder();

                    $attributes += [
                        'type'                         => 'text',
                        'value'                        => $input->default,
                        'dir'                          => 'ltr',
                        'data-wt-reformat-date-order'  => $dmy,
                    ];
                    $control = '<input ' . Html::attributes($attributes) . '>';
                    $extra   = view('edit/input-addon-calendar', ['id' => 'input-' . $n]);
                    break;

                default:
                    switch ($input->type) {
                        case 'text':
                            $attributes += [
                                'type'  => 'text',
                                'value' => $input->default,
                            ];
                            $control = '<input ' . Html::attributes($attributes) . '>';
                            break;

                        case 'checkbox':
                            $attributes += [
                                'type'    => 'checkbox',
                                'checked' => (bool) $input->default,
                            ];
                            $control = '<input ' . Html::attributes($attributes) . '>';
                            break;

                        case 'select':
                            $options = [];
                            foreach (explode('|', $input->options) as $option) {
                                [$key, $value] = explode('=>', $option);
                                $placeholder_expander = new PlaceholderExpander(new VariableTable([]));
                                $options[$key]        = $placeholder_expander->applyI18nFunctions($value);
                            }
                            $control = view('components/select', ['name' => 'vars[' . $input->name . ']', 'id' => 'input-' . $n, 'selected' => $input->default, 'options' => $options]);
                            break;
                    }
            }

            $inputs[] = $input->withControl($control, $extra);
        }

        $destination = $this->user->getPreference('default-report-destination', 'view');
        $format      = $this->user->getPreference('default-report-format', 'PDF');

        return $this->viewResponse('report-setup-page', [
            'description' => $description,
            'destination' => $destination,
            'format'      => $format,
            'inputs'      => $inputs,
            'report'      => $report,
            'title'       => $title,
            'tree'        => $tree,
        ]);
    }

    public function post(ServerRequestInterface $request, Tree $tree): ResponseInterface
    {
        $report = Validator::attributes($request)->string('report');
        $module = $this->module_service->findByName($report);

        if (!$module instanceof ModuleReportInterface) {
            return redirect(route(ReportList::class, ['tree' => $tree->name()]));
        }

        Auth::checkComponentAccess($module, ModuleReportInterface::class, $tree, $this->user);

        return redirect(route(CoreReportGenerateController_2_3::class, [
            'tree'        => $tree->name(),
            'report'      => $report,
            'destination' => Validator::parsedBody($request)->string('destination'),
            'format'      => Validator::parsedBody($request)->string('format'),
            'varnames'    => Validator::parsedBody($request)->list('varnames'),
            'vars'        => Validator::parsedBody($request)->array('vars'),
        ]));
    }
}
