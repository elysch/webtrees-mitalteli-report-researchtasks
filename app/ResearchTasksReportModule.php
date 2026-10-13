<?php

declare(strict_types=1);

/**
 * Research Tasks Report module class.
 */

namespace vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace;          

use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Http\RequestHandlers\MitalteliReportGenerate_2_0;
use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Http\RequestHandlers\MitalteliReportGenerate_2_1;
use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Http\RequestHandlers\MitalteliReportGenerate_2_2;
use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Http\RequestHandlers\MitalteliReportGenerate_2_3;
use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Http\RequestHandlers\MitalteliReportSetup_2_3;
use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Http\RequestHandlers\MitalteliReportSetupPage_Legacy;
use Fisharebest\Webtrees\Http\RequestHandlers\ReportSetupPage;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Http\RequestHandlers\ReportGenerate;
use Fisharebest\Webtrees\Http\Controllers\ReportGenerate as CoreReportGenerateController_2_3;
use Fisharebest\Webtrees\Http\Controllers\ReportSetup as CoreReportSetupController_2_3;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleReportInterface;
use Fisharebest\Webtrees\Module\ModuleReportTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Webtrees;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Localization\Translation;

use VERSION;

class ResearchTasksReportModule extends AbstractModule implements ModuleCustomInterface, ModuleReportInterface, ModuleGlobalInterface {
    use ModuleCustomTrait;
    use ModuleReportTrait;
    public ModuleService $module_service;

    public const CUSTOM_AUTHOR = 'elysch';
    public const CUSTOM_VERSION = '3.4.0';
    public const GITHUB_REPO = 'webtrees-mitalteli-report-researchtasks';
    public const AUTHOR_WEBSITE = 'https://github.com/elysch/webtrees-mitalteli-report-researchtasks/';
    public const CUSTOM_SUPPORT_URL = self::AUTHOR_WEBSITE . 'issues';

    /* The report title must be exactly the same as in resources/report.xml in two places at least*/
    public const REPORT_TITLE = 'Research tasks report';
    
    /**
     *
     * @param ModuleService $module_service
     */
    public function __construct(ModuleService $module_service)
    {
        $this->module_service = $module_service;
    }

    public function boot(): void
    {
        if (version_compare(Webtrees::VERSION, '2.3.0-dev', '>=')) {
            // La página de PARÁMETROS del reporte (antes de generarlo) usa un
            // controlador/parser distinto al de generación -- hay que
            // interceptar los DOS.
            Registry::container()->bind(
                CoreReportSetupController_2_3::class,
                MitalteliReportSetup_2_3::class
            );
            Registry::container()->bind(
                CoreReportGenerateController_2_3::class,
                MitalteliReportGenerate_2_3::class
            );
        } elseif (version_compare(Webtrees::VERSION, '2.2.0', '>=')) {
            Registry::container()->set(ReportGenerate::class, new MitalteliReportGenerate_2_2($this->module_service));
            Registry::container()->set(ReportSetupPage::class, new MitalteliReportSetupPage_Legacy(self::getClass(ReportSetupPage::class)));
        } elseif (version_compare(Webtrees::VERSION, '2.1.0', '>=')) {
            app()->instance(ReportGenerate::class, new MitalteliReportGenerate_2_1($this->module_service));
            app()->instance(ReportSetupPage::class, new MitalteliReportSetupPage_Legacy(self::getClass(ReportSetupPage::class)));
        } else {
            app()->instance(ReportGenerate::class, new MitalteliReportGenerate_2_0($this->module_service));
            app()->instance(ReportSetupPage::class, new MitalteliReportSetupPage_Legacy(self::getClass(ReportSetupPage::class)));
        }
    }

    /**
     * Get the directory name of this module.
     *
     * @return string
     */
    public static function getCoreWebtreesCustomizationDirectory(): string
    {
        # __DIR__ returns the directory of this file, which is like /path/to/webtrees/modules_v4/mitalteli-report-researchtasks/app
        $real_dir = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR);
        if ($real_dir === false) {
            throw new \RuntimeException('Could not resolve the parent directory of ' . __DIR__);
        }

        return basename($real_dir);
    }

    /**
     * @return string
     */
    public function title(): string
    {
        /* I18N: Name of a module/report */
        return I18N::translate(ResearchTasksReportModule::REPORT_TITLE);
    }

    /**
     * Where does this module store its resources
     *
     * @return string
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . '/../resources/';
    }

    /**
     * A sentence describing what this module does.
     *
     * @return string
     */
    public function description(): string
    {
        /* I18N: Description of the “Research tasks report” module */
        return I18N::translate('A research tasks Report with filters and other options.');
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleAuthorName()
     */
    public function customModuleAuthorName(): string
    {
        return self::CUSTOM_AUTHOR;
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleVersion()
     */
    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    /**
     * A URL that will provide the latest stable version of this module.
     *
     * @return string
     */
    public function customModuleLatestVersionUrl(): string
    {
        return 'https://raw.githubusercontent.com/' . self::CUSTOM_AUTHOR . '/' . self::GITHUB_REPO . '/main/latest-version.txt';
    }

     /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleSupportUrl()
     */
    public function customModuleSupportUrl(): string
    {
        return self::AUTHOR_WEBSITE;
    }

    /**
     * Name of the XML report file, relative to the resources folder.
     *
     * @return string
     */
    public function xmlFilename(): string
    {
        if (version_compare(Webtrees::VERSION, '2.3.0-dev', '>=')) {
            return 'report-2.3.xml';
        }
             
        return 'report.xml';
    }

    /**
     * Raw content, to be added at the end of the <head> element.
     * Typically, this will be <link> and <meta> elements.
     *
     * @return string
     */
    public function headContent(): string
    {
        return '';
    }

    /**
     * Raw content, to be added at the end of the <body> element.
     * Typically, this will be <script> elements.
     *
     * https://getbootstrap.com/docs/5.2/components/tooltips/#enable-tooltips
     *
     * @return string
     */
    public function bodyContent(): string
    {
        // Only acts on the report setup form: every lookup is by input NAME, so it does nothing
        // (and throws nothing) on any other webtrees page where this script is also injected.
        $messages = json_encode([
            // Shown next to "Show all individuals" / "Show all families", depending on their state
            'indiChecked'   => I18N::translate('To select a specific individual, uncheck this option. The report will then show all families.'),
            'indiUnchecked' => I18N::translate('Select an individual below. To show all individuals instead, check this option.'),
            'famChecked'    => I18N::translate('To select a specific family, uncheck this option. The report will then show all individuals.'),
            'famUnchecked'  => I18N::translate('Select a family below. To show all families instead, check this option.'),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return "\n" . str_replace('__MIT_MESSAGES__', $messages, <<<'JS'
            <script type="text/javascript">
            (function () {
                // Translated help texts (injected by PHP).
                var messages = __MIT_MESSAGES__;

                // [checkbox name, name of the control that the checkbox hides]
                var pairs = [
                    ['vars[showAllIndividuals]', 'vars[pid]'],
                    ['vars[showAllFamilies]',    'vars[fid]']
                ];

                function findAncestor(el, sel) {
                    while ((el = el.parentElement) && !el.matches(sel)) {}
                    return el;
                }

                // Checked => hide the row AND drop "required" from its controls: webtrees marks the
                // individual/family selectors as required, and a hidden empty required field
                // would silently block submitting the form.
                // Help text under a checkbox, telling the user how to pick a specific record.
                function updateHint(checkbox) {
                    var key = checkbox.name === 'vars[showAllIndividuals]' ? 'indi' : 'fam';
                    var hint = checkbox.parentElement.querySelector('.mit-hint');
                    if (!hint) {
                        hint = document.createElement('div');
                        hint.className = 'form-text mit-hint';
                        checkbox.parentElement.appendChild(hint);
                    }
                    hint.textContent = messages[key + (checkbox.checked ? 'Checked' : 'Unchecked')];
                }

                function apply(checkbox, row) {
                    updateHint(checkbox);
                    var hide = checkbox.checked;
                    row.style.display = hide ? 'none' : '';
                    row.querySelectorAll('select, input, textarea').forEach(function (c) {
                        if (c.dataset.mitRequired === undefined) {
                            c.dataset.mitRequired = c.required ? '1' : '0';
                        }
                        c.required = hide ? false : (c.dataset.mitRequired === '1');
                    });
                }

                // Current elements for one [checkbox, control] pair (looked up each time, so it
                // always reflects the live DOM), or null if the pair is not on this page.
                function getItem(pair) {
                    var checkbox = document.getElementsByName(pair[0])[0];
                    var control  = document.getElementsByName(pair[1])[0];
                    if (!checkbox || !control) { return null; }
                    var row = findAncestor(control, '.row');
                    if (!row) { return null; }
                    return { checkbox: checkbox, control: control, row: row };
                }

                // Selecting one specific individual switches the family side to "all families",
                // and selecting one specific family switches the individual side to "all
                // individuals". [chosen pair, pair that must fall back to "show all"]
                // Setting .checked from code fires no event, so apply() is called explicitly.
                function switchSides(chosenPair, otherPair) {
                    var chosen = getItem(chosenPair);
                    var other  = getItem(otherPair);
                    if (!chosen || !other || chosen.control.value === '') { return; }
                    chosen.checkbox.checked = false;
                    other.checkbox.checked  = true;
                    apply(chosen.checkbox, chosen.row);
                    apply(other.checkbox, other.row);
                }

                function init(event) {
                    pairs.forEach(function (pair) {
                        var item = getItem(pair);
                        if (!item) { return; }
                        apply(item.checkbox, item.row);
                        if (!item.checkbox.dataset.mitBound) {
                            item.checkbox.dataset.mitBound = '1';
                            item.checkbox.addEventListener('change', function () {
                                var current = getItem(pair);
                                if (current) { apply(current.checkbox, current.row); }
                            });
                        }
                    });

                    // pairs are [checkbox, control]; the controls are what we listen to.
                    var indiPair = pairs[0], famPair = pairs[1];
                    [[indiPair, famPair], [famPair, indiPair]].forEach(function (link) {
                        var item = getItem(link[0]);
                        if (!item || item.control.dataset.mitSwitchBound) { return; }
                        item.control.dataset.mitSwitchBound = '1';
                        var handler = function () { switchSides(link[0], link[1]); };
                        // webtrees 2.2+ (Tom Select) dispatches a native "change" event on the
                        // original <select>; 2.0/2.1 (select2) only triggers it through jQuery.
                        item.control.addEventListener('change', handler);
                        if (window.jQuery) { window.jQuery(item.control).on('change', handler); }
                    });

                    // First load only: a prefilled selector (the report opened from an
                    // individual/family page via ?xref=...) must not stay hidden behind the
                    // "show all" default. Not on pageshow, to keep the user's own state.
                    if (!event || event.type !== 'pageshow') {
                        var indi = getItem(indiPair), fam = getItem(famPair);
                        if (indi && fam) {
                            var indiSet = indi.control.value !== '', famSet = fam.control.value !== '';
                            if (indiSet && !famSet) { switchSides(indiPair, famPair); }
                            else if (famSet && !indiSet) { switchSides(famPair, indiPair); }
                        }
                    }
                }

                init();
                window.addEventListener('pageshow', init);
            })();
            </script>
JS
        ) . "\n";
    }

    /**
     * Additional translations for module.
     *
     * @param string $language
     *
     * @return string[]
     */
    public function customTranslations(string $language): array
    {
        $file = $this->resourcesFolder() . 'lang' . DIRECTORY_SEPARATOR . $language . '.php';

        return file_exists($file) ? (new Translation($file))->asArray() : [];
    }

    /**
     * A breaking change in webtrees 2.2.0 changes how the classes are retrieved.
     * This function allows support for both 2.1.X and 2.2.X versions
     * @param $class
     * @return mixed
     */
    static function getClass($class)
    {
        if (version_compare(Webtrees::VERSION, '2.2.0', '>=')) {
            return Registry::container()->get($class);
        } else {
            return app($class);
        }
    }
};
