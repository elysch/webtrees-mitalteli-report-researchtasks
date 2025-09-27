<?php

declare(strict_types=1);

/**
 * Research Tasks Report module class.
 */

namespace vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace;          

use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Http\RequestHandlers\MitalteliReportGenerate;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Http\RequestHandlers\ReportGenerate;

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
    public const CUSTOM_VERSION = '0.1.0';
    public const GITHUB_REPO = 'webtrees-mitalteli-report-researchtasks';
    public const AUTHOR_WEBSITE = 'https://github.com/elysch/webtrees-mitalteli-report-researchtasks/';
    public const CUSTOM_SUPPORT_URL = self::AUTHOR_WEBSITE . 'issues';

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
        if (version_compare(Webtrees::VERSION, '2.2.0', '>=')) {
            Registry::container()->set(ReportGenerate::class, new MitalteliReportGenerate($this->module_service));
        } else {
            app()->instance(ReportGenerate::class, new MitalteliReportGenerate($this->module_service));
        }
    }

    /**
     * @return string
     */
    public function title(): string
    {
        /* I18N: Name of a module/report */
        return I18N::translate('Research tasks report');
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
        return "\n" . '
            <script type="text/javascript">

            const rowsShowHide=[];
            
            function findAncestor (el, sel) {
                while ((el = el.parentElement) && !((el.matches || el.matchesSelector).call(el,sel)));
                return el;
            }
            
            function showHideRows(arr) {
                for (var i = 0; i < arr.length; i++) {
                    showHide(arr[i].inputRow, arr[i].checkbox);
                }
            }

            function showHide(el, checkbox) {
                if (checkbox.checked == true){
                  // hide
                  el.style.display = "none";
                } else {
                  // show
                  el.style.display = "";
                }
            }
            
            function handleShowHideFormRow(checkboxName, labelText) {
                let inputRow = null;
                let checkbox = document.getElementsByName(checkboxName)[0];
                let labels = document.getElementsByTagName(\'label\');
                for (var i = 0; i < labels.length; i++) {
                    var strTmp = labels[i].innerText;
                    strTmp = (strTmp) ? strTmp.trim() : "";
                    if (strTmp == labelText) {
                        //alert(labels[i].textContent);
                        inputRow=findAncestor(labels[i], ".row");
                        showHide(inputRow,checkbox);inputRow,checkbox
                        rowsShowHide.push( { inputRow,checkbox } );
                        checkbox.setAttribute(\'onChange\', \'showHideRows(rowsShowHide)\');
                    }
                }
            }
            
            function handleDisableFormRow(checkboxName) {
                let inputRow = null;
                let checkbox = document.getElementsByName(checkboxName)[0];
                checkbox.disabled = true;
            }
            
            window.onpageshow = function() {
                handleShowHideFormRow(\'vars[showAllIndividuals]\', \'' . I18N::translate('Individual') . '\');
                //handleShowHideFormRow(\'vars[showAllFamilies]\', \'' . I18N::translate('Family') . '\');
                handleDisableFormRow(\'vars[showAllFamilies]\');
            }
            
            </script>
            ' . "\n";
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
