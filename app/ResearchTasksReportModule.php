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


class ResearchTasksReportModule extends AbstractModule implements ModuleCustomInterface, ModuleReportInterface, ModuleGlobalInterface {
    use ModuleCustomTrait;
    use ModuleReportTrait;
    public ModuleService $module_service;

    public const CUSTOM_AUTHOR = 'elysch';
    public const CUSTOM_VERSION = '3.4.2';
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
            // The report PARAMETERS page (before generating the report) uses a
            // different controller/parser than generation -- both have to be
            // intercepted.
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
            'indiChecked'   => I18N::translate('To select a specific individual, uncheck this option.'),
            'indiUnchecked' => I18N::translate('Select an individual below. To show all individuals instead, check this option.'),
            'famChecked'    => I18N::translate('To select a specific family, uncheck this option.'),
            'famUnchecked'  => I18N::translate('Select a family below. To show all families instead, check this option.'),
            // Checkbox added next to the "view" / "download" buttons
            'newTab'        => I18N::translate('Open the report in a new tab'),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        // webtrees < 2.2 does not keep the selected options when the user comes back to the
        // parameters page (2.2+ does), so the form state is restored from sessionStorage there.
        $restore = version_compare(Webtrees::VERSION, '2.2.0', '<') ? 'true' : 'false';

        return "\n" . str_replace(['__MIT_MESSAGES__', '__MIT_RESTORE__'], [$messages, $restore], <<<'JS'
            <script type="text/javascript">
            (function () {
                // Translated help texts (injected by PHP).
                var messages = __MIT_MESSAGES__;

                // true on webtrees < 2.2 (see PHP side): restore the form when coming back.
                var restoreForm = __MIT_RESTORE__;
                var STATE_KEY   = 'mitalteli-researchtasks-form';
                var NEWTAB_KEY  = 'mitalteli-researchtasks-newtab';

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

                // ---- Form state (webtrees < 2.2) ---------------------------------------------

                function getForm() {
                    var probe = document.getElementsByName(pairs[0][0])[0];
                    return probe ? probe.closest('form') : null;
                }

                function isBackNavigation() {
                    try {
                        var nav = window.performance && performance.getEntriesByType
                            ? performance.getEntriesByType('navigation')[0] : null;
                        if (nav) { return nav.type === 'back_forward'; }
                        return window.performance && performance.navigation
                            && performance.navigation.type === 2;
                    } catch (e) { return false; }
                }

                function saveState(form) {
                    try {
                        var state = {};
                        Array.prototype.forEach.call(form.elements, function (el) {
                            if (!el.name || el.type === 'hidden' || el.type === 'submit' || el.type === 'button') { return; }
                            if (el.type === 'checkbox') {
                                state[el.name] = { checked: el.checked };
                            } else if (el.type === 'radio') {
                                if (el.checked) { state[el.name] = { value: el.value }; }
                            } else if (el.tagName === 'SELECT') {
                                var opt = el.options[el.selectedIndex];
                                state[el.name] = { value: el.value, text: opt ? opt.text : '' };
                            } else {
                                state[el.name] = { value: el.value };
                            }
                        });
                        window.sessionStorage.setItem(STATE_KEY, JSON.stringify(state));
                    } catch (e) { /* storage unavailable: nothing is remembered */ }
                }

                function restoreState(form) {
                    var state;
                    try { state = JSON.parse(window.sessionStorage.getItem(STATE_KEY) || 'null'); } catch (e) { return; }
                    if (!state) { return; }
                    var checkboxes = [];
                    Array.prototype.forEach.call(form.elements, function (el) {
                        var saved = state[el.name];
                        if (!saved || !el.name || el.type === 'hidden' || el.type === 'submit' || el.type === 'button') { return; }
                        if (el.type === 'checkbox') {
                            checkboxes.push([el, saved.checked]);
                        } else if (el.type === 'radio') {
                            el.checked = (el.value === saved.value);
                        } else if (el.tagName === 'SELECT') {
                            // Individual/family selectors only hold their current option: recreate it.
                            var found = Array.prototype.some.call(el.options, function (o) { return o.value === saved.value; });
                            if (!found && saved.value !== '') {
                                el.add(new Option(saved.text, saved.value, true, true));
                            }
                            el.value = saved.value;
                        } else {
                            el.value = saved.value;
                        }
                    });
                    // Checkboxes last: init() then hides/shows the matching selector rows from them.
                    checkboxes.forEach(function (c) { c[0].checked = c[1]; });
                }

                // ---- "Open the report in a new tab" ------------------------------------------

                function addNewTabOption(form) {
                    if (form.dataset.mitNewTab) { return; }
                    form.dataset.mitNewTab = '1';

                    var buttons = form.querySelector('button[type="submit"][name="destination"]');
                    if (!buttons) { return; }

                    var wrap = document.createElement('div');
                    wrap.className = 'form-check mb-2';
                    var box = document.createElement('input');
                    box.type = 'checkbox';
                    box.className = 'form-check-input';
                    box.id = 'mit-new-tab';
                    try { box.checked = window.localStorage.getItem(NEWTAB_KEY) === '1'; } catch (e) {}
                    var label = document.createElement('label');
                    label.className = 'form-check-label';
                    label.htmlFor = 'mit-new-tab';
                    label.textContent = messages.newTab;
                    wrap.appendChild(box);
                    wrap.appendChild(label);
                    buttons.parentElement.insertBefore(wrap, buttons.parentElement.firstChild);

                    box.addEventListener('change', function () {
                        try { window.localStorage.setItem(NEWTAB_KEY, box.checked ? '1' : '0'); } catch (e) {}
                    });

                    form.addEventListener('submit', function (event) {
                        // A download needs no tab; only "view" opens the report in a new one.
                        var submitter = event.submitter;
                        var download  = submitter && submitter.name === 'destination' && submitter.value === 'download';
                        if (box.checked && !download) {
                            form.target = '_blank';
                        } else {
                            form.removeAttribute('target');
                        }
                    });
                }

                function initForm() {
                    var form = getForm();
                    if (!form || form.dataset.mitFormInit) { return; }
                    form.dataset.mitFormInit = '1';

                    addNewTabOption(form);

                    if (restoreForm) {
                        form.addEventListener('submit', function () { saveState(form); });
                        // Only when coming back (history): a fresh open from the menu keeps the defaults
                        // and the preselected individual.
                        if (isBackNavigation()) { restoreState(form); }
                    }
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

                initForm();
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

    /**
     * Inputs declared in the report XML file.
     *
     * @return array<string,array{type:string,lookup:string,default:?string,options:array<int,string>}>
     */
    private static function declaredReportInputs(string $xml_filename): array
    {
        static $cache = [];

        if (isset($cache[$xml_filename])) {
            return $cache[$xml_filename];
        }

        $inputs   = [];
        $previous = libxml_use_internal_errors(true);
        $xml      = simplexml_load_file($xml_filename, \SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($previous);

        if ($xml !== false) {
            foreach ($xml->xpath('//Input') ?: [] as $input) {
                $name = (string) $input['name'];

                if ($name === '') {
                    continue;
                }

                $options = [];
                foreach (explode('|', (string) $input['options']) as $option) {
                    $key = trim(explode('=>', $option, 2)[0]);
                    if ($key !== '') {
                        $options[] = $key;
                    }
                }

                $inputs[$name] = [
                    'type'    => (string) $input['type'],
                    'lookup'  => (string) $input['lookup'],
                    'default' => isset($input['default']) ? (string) $input['default'] : null,
                    'options' => $options,
                ];
            }
        }

        return $cache[$xml_filename] = $inputs;
    }

    /**
     * Security: build the report variables ONLY from the inputs declared in the report XML,
     * normalising every value according to its declared type. Anything undeclared is dropped.
     * This prevents parameter injection into the CONTAINS_R filter string (NEEDLE='...'),
     * "$var" self-references in the variable substitution loop, and out-of-range select values.
     *
     * @param array<mixed> $raw Raw "vars" query parameter
     *
     * @return array<string,string> name => sanitised value
     */
    public static function sanitizeReportVars(string $xml_filename, array $raw): array
    {
        $clean = [];

        foreach (self::declaredReportInputs($xml_filename) as $name => $def) {
            $value = $raw[$name] ?? '';

            // <Input lookup="DATE"> may be submitted as ['day','month','year'].
            if (is_array($value)) {
                $parts = [];
                foreach (['day', 'month', 'year'] as $part) {
                    if (isset($value[$part]) && is_scalar($value[$part]) && trim((string) $value[$part]) !== '') {
                        $parts[] = trim((string) $value[$part]);
                    }
                }
                $value = implode(' ', $parts);
            }

            $value = is_scalar($value) ? (string) $value : '';
            $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? '';
            $value = mb_check_encoding($value, 'UTF-8') ? trim($value) : '';

            switch ($def['type']) {
                case 'checkbox':
                    // Browsers submit "on" for a checked box without a value attribute.
                    $value = ($value !== '' && $value !== '0' && strtolower($value) !== 'off') ? 'on' : '';
                    break;

                case 'select':
                    if (!in_array($value, $def['options'], true) && !in_array(rawurldecode($value), $def['options'], true)) {
                        $value = $def['default'] ?? ($def['options'][0] ?? '');
                    }
                    break;

                default:
                    if ($def['lookup'] === '') {
                        // Free text. Besides the strictly sanitised "$name", two extra variables:
                        //  - "$name_b64": the exact text (any character) as base64url. Its alphabet has no quotes,
                        //    spaces, "$" or "\", so it can travel inside key='value' filter strings safely.
                        //    The parser decodes it (NEEDLE_B64) and still applies preg_quote().
                        //  - "$name_show": for DISPLAY only; keeps quotes and symbols, drops "$", "@", "<", ">".
                        $text = mb_substr($value, 0, 100, 'UTF-8');

                        $clean[$name . '_b64']  = rtrim(strtr(base64_encode($text), '+/', '-_'), '=');
                        $clean[$name . '_show'] = self::defuseArithmetic(trim(preg_replace('/[$@<>]+/u', '', $text) ?? ''));
                    }

                    $value = self::sanitizeTextVar($def['lookup'], $value);
                    break;
            }

            $clean[$name] = $value;
        }

        return $clean;
    }

    private static function sanitizeTextVar(string $lookup, string $value): string
    {
        switch ($lookup) {
            case 'INDI':
            case 'FAM':
            case 'SOUR':
            case 'REPO':
            case 'NOTE':
            case 'OBJE':
            case 'SUBM':
                // GEDCOM xref: letters, digits and a few separators only.
                return preg_match('/^[A-Za-z0-9_.:-]{0,30}$/', $value) === 1 ? $value : '';

            case 'DATE':
                // GEDCOM date text, e.g. "BET 1900 AND 1910", "15 JAN 2020", "1900/01".
                return mb_substr(preg_replace('/[^\p{L}\p{N} .\/-]+/u', '', $value) ?? '', 0, 40);

            default:
                // Free text (e.g. filter_rt). It is later embedded in a key='value' filter string
                // and a regular expression: remove quotes, "$", "\" and "@" and cap the length.
                return self::defuseArithmetic(mb_substr(trim(preg_replace('/[\'"`$\\\\@]+/u', '', $value) ?? ''), 0, 100));
        }
    }

    /** Only HTML or PDF are valid report formats. */
    /** @param mixed $format */
    public static function normalizeFormat($format): string
    {
        return $format === 'PDF' ? 'PDF' : 'HTML';
    }

    /** Only "view" or "download" are valid destinations. */
    /** @param mixed $destination */
    public static function normalizeDestination($destination): string
    {
        return $destination === 'download' ? 'download' : 'view';
    }

    /** Safe file name (no quotes, CR/LF or path separators) for Content-Disposition. */
    public static function safeDownloadName(string $report): string
    {
        $name = trim(preg_replace('/[^A-Za-z0-9._-]+/', '_', $report) ?? '', '._');

        return $name === '' ? 'report' : $name;
    }

    /**
     * The report engine passes any variable value that looks like "1+1" to Symfony's ExpressionLanguage
     * (where e.g. "1+1..99999999" builds a huge array). Insert an invisible WORD JOINER (U+2060) before the
     * operator so user text is never mistaken for an arithmetic expression; the text looks the same.
     */
    private static function defuseArithmetic(string $text): string
    {
        return preg_replace('/(?<=\d)(\s*)([-+*\/])(?=\s*\d)/u', '$1' . "\u{2060}" . '$2', $text) ?? '';
    }
}
