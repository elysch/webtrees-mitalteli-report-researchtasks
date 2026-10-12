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

namespace vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Report;

/**
 * =====================================================================
 * WHY THIS CLASS LOOKS SO DIFFERENT FROM MitalteliReportParserGenerate_2_1
 * =====================================================================
 *
 * In webtrees >= 2.3, `Fisharebest\Webtrees\Report\ParserGenerate` is
 * `final`, so "extends ReportParserGenerate" (the pattern used since 2.0)
 * is impossible here: PHP refuses to extend a final class.
 *
 * The good news: the new base class `AbstractParser` dispatches tags
 * through a data structure, not a method-naming convention:
 *
 *     abstract protected function startHandlers(): array; // ['TagName' => Closure]
 *     abstract protected function endHandlers(): array;
 *
 * `ParserGenerate::startHandlers()` builds its table with first-class
 * callable syntax, e.g. `'List' => $this->listStartHandler(...)`. Such a
 * closure is bound to whatever `$this` was when the expression ran. So if
 * we build a private, fully-configured instance of core's `ParserGenerate`
 * (the "delegate") and pull its `startHandlers()`/`endHandlers()` arrays out
 * via Reflection (they are `protected`), every closure we get back is still
 * correctly bound to the delegate and will read/write the delegate's
 * internal state exactly as if core were running unmodified.
 *
 * We then build OUR OWN startHandlers()/endHandlers() by starting from that
 * harvested table and only replacing the handful of entries this module
 * actually customises, plus adding the extra tags report.xml uses that
 * core doesn't define (GetAllPersonNames, ListTotalReset, url).
 *
 * This mirrors the reflection-based "reach into a private property of a
 * class we don't really extend" trick this module was already using
 * (getFromParentPrivatePropertyWithReflection() etc.) — the only change is
 * that the object we reach into is now a composed sibling instead of a true
 * PHP parent.
 *
 * =====================================================================
 * STATUS OF EACH CUSTOM TAG/METHOD FROM THE 2.1 MODULE (as of this pass)
 * =====================================================================
 *  PORTED (verified against the new Element/AbstractParser API):
 *    - url / </url>                  (urlStartHandler / urlEndHandler)
 *    - ListTotalReset                (listTotalResetStartHandler)
 *    - removeDiacritics()            (pure string helper, no engine coupling)
 *    - convertValueToBool()          (pure helper)
 *    - parseKeyValueString()         (pure helper)
 *    - searchReplaceStringsFromContainsRegexString() (pure helper)
 *
 *  NOT YET PORTED — currently pass straight through to core's unmodified
 *  behaviour (i.e. NOT customised yet; do not assume module-specific
 *  fixes/features are active for these until they're ported):
 *    - GetPersonName, GetAllPersonNames
 *    - List, ListTotal (ListTotal will over-count if a List's own results
 *      are also subject to fact filters -- so far only RepeatTag applies
 *      them; check whether report.xml's <List> tags need the same
 *      treatment if their displayed totals look wrong)
 *    - SetVar, var
 *    - GedcomValue
 *    - TextBox / Textbox
 *
 *  Each of the "not yet ported" handlers needs its 2.1 implementation
 *  re-read side-by-side with core's equivalent in
 *  Fisharebest\Webtrees\Report\ParserGenerate (2.3) and re-targeted at the
 *  new Element / DocumentBuilder / VariableTable API before being moved
 *  into startHandlers()/endHandlers() below. Do this one tag at a time.
 *
 * =====================================================================
 * IMPORTANT ADDITIONAL OVERRIDE: startElement()/endElement()/characterData()
 * =====================================================================
 * `ParserGenerate` doesn't only customise the tag dispatch TABLE — it also
 * overrides `startElement()`, `endElement()` and `characterData()`
 * (inherited from AbstractParser) to add two things every single tag
 * depends on:
 *   1. `$varname` placeholder substitution in XML attributes (e.g.
 *      pageOrientation="$pageOrientation") BEFORE the tag handler runs.
 *   2. "Gating": while inside <if>, <Gedcom>, <RepeatTag>/<Facts>/<List>/
 *      <Relatives>, most tags/character-data are suppressed so nested
 *      report markup isn't evaluated eagerly.
 * Both live on core's private state (`$variables`, `$process_ifs`,
 * `$process_gedcoms`, `$process_repeats`, `$process_footnote`,
 * `$current_element`, `$print_data`) -- i.e. on $this->delegate, not on
 * $this. So these three methods are overridden below too, replicating
 * core's logic exactly but reaching into the delegate via reflection for
 * both its private properties AND its private gate methods
 * (gateAllowsStart/End/CharacterData are `private`, not `protected`).
 * Skipping this override is what caused the "$pageOrientation is not a
 * valid backing value" crash: without it, EVERY tag's attributes reach
 * the handlers unsubstituted.
 *
 * NOT ported: core's `addContextToException()` (adds report filename/line/
 * xref to error messages). It relies on the delegate's own $xml_reader,
 * which is never initialised because the delegate never actually parses
 * anything -- WE call process(), not it. Not worth faking; errors just
 * propagate with their original message instead of the enriched one.
 *
 * =====================================================================
 * WORKAROUND (prefilterDelegateList): the limit below is bypassed for
 * <RepeatTag> nested in <List> by applying its filterN attributes to the
 * records at the OUTER </List> (which does run through our table), before
 * core replays the body. Only level-1 tags (e.g. _TODO) are supported.
 *
 * KNOWN ARCHITECTURAL LIMIT: RepeatTag's custom filterN attributes
 * =====================================================================
 * repeatTagStartHandlerOverride()/prepareFactFilters()/applyFactFilters()/
 * substituteLinkedNoteValues() below implement this module's date-range
 * and CONTAINS_R regex filtering on <RepeatTag> -- but when <RepeatTag> is
 * nested inside <List> (as it is in report.xml), it does NOT actually run
 * through this override at all, and there is no fix for that within this
 * composition approach:
 *
 *   <List> captures its own body as raw text (readInnerXml()) and replays
 *   it once per matching record via $this->parseFragment($fragment) --
 *   called on the DELEGATE ($this = the real core ParserGenerate, since
 *   that's who List's handler is bound to). parseFragment() dispatches
 *   using the object's OWN $start_handlers/$end_handlers -- i.e. the
 *   DELEGATE's, unmodified, table. There is no way to make the delegate's
 *   own internal replay use OUR merged table instead: $start_handlers is
 *   `private readonly` on AbstractParser (can't be swapped after
 *   construction, not even via reflection once set), and ParserGenerate is
 *   `final` (can't subclass it to override parseFragment() there either).
 *
 * Net effect: with report.xml's current structure (RepeatTag inside List),
 * repeatTagStartHandlerOverride() only ever fires on the FIRST, "still
 * inside an open List" pass (where process_repeats > 1 and it correctly
 * no-ops) -- never on the real, content-producing replay. So right now,
 * <RepeatTag tag="_TODO"> shows ALL _TODO facts, WITHOUT the date-range or
 * CONTAINS_R filtering applied. That is a behaviour change from 2.1, not
 * a crash -- flag it to the user before treating this report as done.
 *
 * The only structurally sound fix is NOT more composition: it's forking
 * List/RepeatTag/Facts/Relatives (and everything they share state with --
 * $gedrec/$context/$current_element/$variables/etc.) into fully
 * module-owned code that doesn't delegate to core's ParserGenerate at all.
 * That is a much larger undertaking than anything ported so far and needs
 * its own dedicated pass, not a patch on top of this file.
 */

use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\ResearchTasksReportModule;

use Closure;
use DomainException;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;

use Carbon\CarbonImmutable;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Report\AbstractParser;
use Fisharebest\Webtrees\Report\ConfigProviderInterface;
use Fisharebest\Webtrees\Report\DocumentAcceptorInterface;
use Fisharebest\Webtrees\Report\ElementFactoryInterface;
use Fisharebest\Webtrees\Report\GedcomTextReader;
use Fisharebest\Webtrees\Report\OutputInterface;
use Fisharebest\Webtrees\Report\ParserGenerate;
use Fisharebest\Webtrees\Report\SetupInterface;
use Fisharebest\Webtrees\Report\StyleConsumerInterface;
use Fisharebest\Webtrees\Tree;

use function array_key_exists;
use function in_array;
use function preg_match_all;
use function strtolower;

/**
 * @phpstan-type RendererContract OutputInterface&SetupInterface&ConfigProviderInterface&ElementFactoryInterface&DocumentAcceptorInterface&StyleConsumerInterface
 */
class MitalteliParserGenerate_2_3 extends AbstractParser
{
    private const array CONTAINS_R_OP = [
        'NEEDLE'                      => ['KEY' => 'n'],
        'NEEDLE_OP_WHOLE_WORD'        => ['KEY' => 'nww'],
        'NEEDLE_OP_IGNORE_CASE'       => ['KEY' => 'nic'],
        'NEEDLE_OP_REMOVE_DIACRITICS' => ['KEY' => 'nrd'],
        'HAYSTACK_REPLACE_R'          => ['KEY' => 'hrr'],
        'HAYSTACK_WANT_R'             => ['KEY' => 'hwr'],
        'HAYSTACK_DONT_WANT_R'        => ['KEY' => 'hdwr'],
        'HAYSTACK_REMOVE_WANT'        => ['KEY' => 'hrw'],
        'HAYSTACK_INC_SHRD_NOTES_VAL' => ['KEY' => 'hisnv'],
        'REPEAT_W_DW_R'               => ['KEY' => 'hrn'],
        'searchstr'                   => ['KEY' => 'sstr'],
    ];

    /** The fully-configured, unmodified core parser we delegate to for every non-customised tag. */
    private ParserGenerate $delegate;

    /** Set while processing the <url>...</url> body. */
    private string $tag_url = '';

    /**
     * Records excluded by applyFactFilters(). Reset by <ListTotalReset>;
     * incremented in repeatTagStartHandlerOverride() below for every
     * subrecord core's RepeatTag found but our custom filterN attributes
     * rejected.
     */
    private int $repeats_rec_filtered_out = 0;

    /** True between the OUTER <List> start and end tags (the pass that runs through OUR handler table). */
    private bool $list_open = false;

    /**
     * Attributes (filterN=...) of the first <RepeatTag> seen inside the open <List>
     * during the outer pass. See prefilterDelegateList().
     *
     * @var array<string,string>|null
     */
    private ?array $list_fact_filter_attrs = null;

    public function __construct(
        string $report,
        OutputInterface&SetupInterface&ConfigProviderInterface&ElementFactoryInterface&DocumentAcceptorInterface&StyleConsumerInterface $renderer,
        array $vars,
        Tree $tree,
        string $author,
        CarbonImmutable $timestamp,
        bool $compression = true,
        bool $font_subsetting = true,
    ) {
        // Build the delegate FIRST: our own startHandlers()/endHandlers()
        // (called from parent::__construct() below) need it to already exist.
        $this->delegate = new ParserGenerate(
            $report,
            $renderer,
            $vars,
            $tree,
            $author,
            $timestamp,
            $compression,
            $font_subsetting,
        );

        parent::__construct($report);
    }

    /**
     * NOT overriding process() (tried that; AbstractParser::parse() is
     * `private`, so a subclass's own process() can't call $this->parse()
     * at all -- PHP rejects it even though $this is an AbstractParser).
     *
     * Instead: let the inherited process() run completely normally (it
     * opens $this->xml_reader itself and calls its own private parse()
     * from within AbstractParser's own code, which is fine). The FIRST
     * time startElement() fires (see below), $this->xml_reader is
     * guaranteed to already be set -- so that's where we copy the SAME
     * reader object onto the delegate, once, lazily.
     *
     * Why this matters at all: List/RepeatTag/Facts/Relatives call
     * $this->xml_reader->readInnerXml() to capture their body as raw text
     * and skip past it -- deferring real processing to a later
     * parseFragment() pass, once per matching record. Those handlers are
     * bound to the DELEGATE, so "$this->xml_reader" there means the
     * delegate's own property. If it were a separate reader (as an even
     * earlier version of this file used, to avoid a "must not be accessed
     * before initialization" crash), readInnerXml() would capture nothing
     * AND our own reader would never be told to skip that content -- it
     * would keep walking tag-by-tag through what should have been
     * skipped, confusing List/RepeatTag's own nesting counter
     * (process_repeats) and leaving the real per-record content never
     * rendered. Sharing the literal same object fixes both: readInnerXml()
     * correctly consumes/skips the real content, and -- because it's the
     * same object -- our own reader's position moves right along with it.
     */
    private bool $delegate_reader_synced = false;

    private function syncDelegateReaderOnce(): void
    {
        if ($this->delegate_reader_synced) {
            return;
        }

        (new ReflectionObject($this->delegate))->getProperty('xml_reader')->setValue($this->delegate, $this->xml_reader);
        $this->delegate_reader_synced = true;
    }

    /**
     * @return array<string,Closure(array<string,string>):void>
     */
    protected function startHandlers(): array
    {
        $handlers = $this->harvestDelegateHandlers('startHandlers');

        // --- overrides of core tags go here as they are ported ---
        $handlers['url'] = $this->urlStartHandler(...);

        // --- tags report.xml uses that core does not define ---
        // TODO: port these from MitalteliReportParserGenerate_2_1 instead of
        // delegating to core (core has no handler for them at all, so
        // leaving them unmapped would throw "Unknown XML element").
        $handlers['GetAllPersonNames'] = $this->notYetPortedStartHandler('GetAllPersonNames');
        $handlers['ListTotalReset']    = $this->listTotalResetStartHandler(...);

        // --- RepeatTag: let core's own (correct, rewritten) tag-path walk
        // populate $this->delegate->repeats, then apply this module's
        // custom filterN attributes (date ranges, CONTAINS_R regex) on top.
        $original_list_start = $handlers['List'];
        $handlers['List']    = function (array $attrs) use ($original_list_start): void {
            if ($this->getFromDelegatePrivatePropertyWithReflection('process_repeats') === 0) {
                $this->list_open              = true;
                $this->list_fact_filter_attrs = null;
            }

            $original_list_start($attrs);
        };

        $original_repeat_tag  = $handlers['RepeatTag'];
        $handlers['RepeatTag'] = function (array $attrs) use ($original_repeat_tag): void {
            $this->repeatTagStartHandlerOverride($attrs, $original_repeat_tag);
        };

        return $handlers;
    }

    /**
     * @return array<string,Closure():void>
     */
    protected function endHandlers(): array
    {
        $handlers = $this->harvestDelegateHandlers('endHandlers');

        $handlers['url'] = $this->urlEndHandler(...);

        // Outer-pass </List>: pre-filter the list BEFORE core replays the body.
        $original_list_end = $handlers['List'];
        $handlers['List']  = function () use ($original_list_end): void {
            if ($this->list_open && $this->getFromDelegatePrivatePropertyWithReflection('process_repeats') === 1) {
                $this->prefilterDelegateList();
                $this->list_open              = false;
                $this->list_fact_filter_attrs = null;
            }

            $original_list_end();
        };

        $handlers['GetAllPersonNames'] = $this->noopEndHandler(...);
        $handlers['ListTotalReset']    = $this->noopEndHandler(...);

        return $handlers;
    }

    /**
     * Pull the (protected) startHandlers()/endHandlers() table off the
     * delegate via reflection. The closures inside are already bound to
     * the delegate, so calling them operates on the delegate's own state.
     *
     * @return array<string,Closure>
     */
    private function harvestDelegateHandlers(string $method): array
    {
        $reflection = new ReflectionClass(ParserGenerate::class);
        $method     = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invoke($this->delegate);
    }

    /**
     * Ported from ParserGenerate::startElement(): substitute "$varname"
     * attribute values from the delegate's VariableTable, then dispatch
     * through OUR handler table (via AbstractParser::startElement()) --
     * gated exactly like core gates it, using core's own (private) gate
     * logic on the delegate's own (private) counters.
     */
    protected function startElement(string $name, array $attrs): void
    {
        $this->syncDelegateReaderOnce();

        $variables = $this->getFromDelegatePrivatePropertyWithReflection('variables');

        foreach ($attrs as $key => $value) {
            if (preg_match('/^[$]([a-z_][a-z_0-9]*)$/i', $value, $match) && $variables->has($match[1])) {
                $attrs[$key] = $variables->get($match[1]);
            }
        }

        if ($this->callOnDelegatePrivateMethodWithReflection('gateAllowsStart', [$name])) {
            parent::startElement($name, $attrs);
        }
    }

    protected function endElement(string $name): void
    {
        if ($this->callOnDelegatePrivateMethodWithReflection('gateAllowsEnd', [$name])) {
            parent::endElement($name);
        }
    }

    protected function characterData(string $data): void
    {
        $print_data = $this->getFromDelegatePrivatePropertyWithReflection('print_data');

        if ($print_data && $this->callOnDelegatePrivateMethodWithReflection('gateAllowsCharacterData')) {
            $current_element = $this->getFromDelegatePrivatePropertyWithReflection('current_element');
            $current_element->addText($data);
        }
    }

    /**
     * Same idea as getFromDelegatePrivatePropertyWithReflection(), but for
     * calling one of the delegate's `private` methods (gateAllowsStart(),
     * gateAllowsEnd(), gateAllowsCharacterData(), addContextToException()
     * are all `private`, not `protected`, so $this->delegate->method()
     * would not be callable even if PHP let us extend ParserGenerate).
     */
    private function callOnDelegatePrivateMethodWithReflection(string $method_name, array $args = []): mixed
    {
        $reflection = new ReflectionMethod($this->delegate, $method_name);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->delegate, ...$args);
    }

    /**
     * Placeholder for a tag whose 2.1 behaviour has not been ported yet.
     * Fails loudly instead of silently doing the wrong thing.
     */
    private function notYetPortedStartHandler(string $tag): Closure
    {
        return function (array $attrs) use ($tag): void {
            throw new DomainException(
                "REPORT ERROR: the <$tag> tag from mitalteli-report-researchtasks has not " .
                'been ported to webtrees >= 2.3 yet (see MitalteliParserGenerate_2_3 class docblock). ' .
                'Line: ' . $this->currentLineNumber(),
            );
        };
    }

    private function noopEndHandler(): void
    {
        // Intentionally empty — pairs with notYetPortedStartHandler() above.
    }

    /**
     * Handle <url url="...">...text...</url> — wraps the enclosed text
     * (already written into the current element) in an <a href> link.
     *
     * Ported unchanged from MitalteliReportParserGenerate_2_1: the new
     * Fisharebest\Webtrees\Report\Element class still stores its text in a
     * `text` property (now `protected` instead of `private`, but the
     * reflection reset below still works either way) and still only
     * exposes addText() as a mutator, so the "reset then re-add" trick is
     * unchanged.
     */
    protected function urlStartHandler(array $attrs): void
    {
        if (empty($attrs['url'])) {
            throw new DomainException('REPORT ERROR url: The attribute "url=" is missing, not set or empty in the XML file for tag url on line: ' . $this->currentLineNumber());
        }

        $this->tag_url = $attrs['url'];
    }

    protected function urlEndHandler(): void
    {
        if (empty($this->tag_url)) {
            throw new DomainException('REPORT ERROR /url: The attribute "url=" is missing, not set or empty in the XML file for tag url when closing on line: ' . $this->currentLineNumber());
        }

        $current_element = $this->getFromDelegatePrivatePropertyWithReflection('current_element');

        if ($current_element->getValue() !== '') {
            $text = '<a href="' . $this->tag_url . '">' . $current_element->getValue() . '</a>';

            $element_reflection = new ReflectionObject($current_element);
            $text_property      = $element_reflection->getProperty('text');
            $text_property->setAccessible(true);
            $text_property->setValue($current_element, '');
            $current_element->addText($text);
        }

        $this->tag_url = '';
    }

    /**
     * Handle <ListTotalReset/> — resets the running count that <ListTotal>
     * reports, and this module's own "records filtered out" counter (not
     * wired up to anything yet — see class docblock).
     *
     * Ported unchanged from MitalteliReportParserGenerate_2_1's
     * listTotalResetStartHandler(): core's ParserGenerate still tracks the
     * count in a private int $list_total property with the same name and
     * semantics in 2.3, so the only change is reaching into the delegate
     * instead of a true PHP parent.
     */
    protected function listTotalResetStartHandler(): void
    {
        $reflection = new ReflectionObject($this->delegate);
        $property   = $reflection->getProperty('list_total');
        $property->setAccessible(true);
        $property->setValue($this->delegate, 0);

        $this->repeats_rec_filtered_out = 0;
    }

    /**
     * Same pattern this module already used for reaching into a private
     * property of a class it "extended" — now aimed at the composed
     * delegate instead of a true PHP parent.
     */
    private function getFromDelegatePrivatePropertyWithReflection(string $property_name): mixed
    {
        $reflection = new ReflectionObject($this->delegate);
        $property   = $reflection->getProperty($property_name);
        $property->setAccessible(true);

        return $property->getValue($this->delegate);
    }

    // =====================================================================
    // Pure helper methods — ported verbatim, no coupling to the renderer/
    // parser rewrite, so nothing to re-verify here.
    // =====================================================================

    public function removeDiacritics(string $text): string
    {
        $normalized        = \Normalizer::normalize($text, \Normalizer::FORM_D);
        $withoutDiacritics = preg_replace('/\p{Mn}/u', '', $normalized);

        return $withoutDiacritics ?: $text;
    }

    public function convertValueToBool(?string $value): bool
    {
        $trueValues  = ['1', 'true', 'yes', 'on'];
        $falseValues = ['0', 'false', 'no', 'off'];

        $lowerValue = isset($value) ? strtolower($value) : '';

        if ($lowerValue === '' || in_array($lowerValue, $falseValues, true)) {
            return false;
        }

        if (in_array($lowerValue, $trueValues, true)) {
            return true;
        }

        throw new InvalidArgumentException("Invalid boolean string: $value");
    }

    public function parseKeyValueString(string $inputString): array
    {
        $result = [];
        preg_match_all('/(\S+)=(?:(?:\'([^\']*)\')|(?:"([^"]*)")|(\S+))(?:\s|$)/', $inputString, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $key   = $match[1];
            $value = null;

            if (isset($match[2]) && $match[2] !== '') {
                $value = $match[2];
            } elseif (isset($match[3]) && $match[3] !== '') {
                $value = $match[3];
            } elseif (isset($match[4]) && $match[4] !== '') {
                $value = $match[4];
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @return array{n:string,nww:bool,nic:bool,nrd:bool,hrr:string,hwr:string,hdwr:string,hrw:bool,hisnv:bool,hrn:int,sstr:string}|null
     */
    public function searchReplaceStringsFromContainsRegexString(string $tag, string $val): ?array
    {
        $args = $this->parseKeyValueString($val);

        if (!array_key_exists('NEEDLE', $args)) {
            throw new DomainException('CONTAINS_R requires a NEEDLE argument');
        }

        if (!isset($args['NEEDLE'])) {
            return null;
        }

        $args['HAYSTACK_WANT_R']      ??= '';
        $args['REPEAT_W_DW_R']        ??= 1;
        $args['HAYSTACK_DONT_WANT_R'] ??= '';

        $searchstr      = '';
        $searchWant     = '((?:\n(?:1 ' . $tag;
        $searchDontwant = '';

        if (!empty($args['HAYSTACK_WANT_R'])) {
            $searchWant .= '|' . $args['HAYSTACK_WANT_R'];
        }
        $searchWant .= ') ?.*)*)';

        if (!empty($args['HAYSTACK_DONT_WANT_R'])) {
            $searchDontwant = '(?:(?:\n(?:' . $args['HAYSTACK_DONT_WANT_R'] . ') ?.*)*)';
        }

        for ($i = 0; $i < (int) $args['REPEAT_W_DW_R']; $i++) {
            $searchstr .= $searchWant . $searchDontwant;
        }

        $op                                                          = [];
        $op[self::CONTAINS_R_OP['searchstr']['KEY']]                   = $searchstr;
        $op[self::CONTAINS_R_OP['HAYSTACK_REPLACE_R']['KEY']]          = $args['HAYSTACK_REPLACE_R'] ?? '\0';
        $op[self::CONTAINS_R_OP['HAYSTACK_WANT_R']['KEY']]             = $args['HAYSTACK_WANT_R'];
        $op[self::CONTAINS_R_OP['HAYSTACK_REMOVE_WANT']['KEY']]        = $this->convertValueToBool($args['HAYSTACK_REMOVE_WANT'] ?? null);
        $op[self::CONTAINS_R_OP['HAYSTACK_INC_SHRD_NOTES_VAL']['KEY']] = $this->convertValueToBool($args['HAYSTACK_INC_SHRD_NOTES_VAL'] ?? null);
        $op[self::CONTAINS_R_OP['NEEDLE']['KEY']]                      = $args['NEEDLE'];
        $op[self::CONTAINS_R_OP['NEEDLE_OP_WHOLE_WORD']['KEY']]        = $this->convertValueToBool($args['NEEDLE_OP_WHOLE_WORD'] ?? null);
        $op[self::CONTAINS_R_OP['NEEDLE_OP_IGNORE_CASE']['KEY']]       = $this->convertValueToBool($args['NEEDLE_OP_IGNORE_CASE'] ?? null);
        $op[self::CONTAINS_R_OP['NEEDLE_OP_REMOVE_DIACRITICS']['KEY']] = $this->convertValueToBool($args['NEEDLE_OP_REMOVE_DIACRITICS'] ?? null);

        return $op;
    }

    /**
     * Wraps core's own (harvested, unmodified) RepeatTag handler: let it do
     * its own tag-path walk to populate $delegate->repeats (its new 2.3
     * logic is a strict superset of what MitalteliReportParserGenerate_2_1's
     * traverseTag() used to do by hand), then remove any subrecord that
     * fails this module's custom filterN attributes.
     *
     * Ported from MitalteliReportParserGenerate_2_1::repeatTagStartHandler(),
     * but restructured: 2.1 filtered records WHILE walking the GEDCOM tree
     * itself (inside its own traverseTag() callback); here we let core do
     * that walk on its own, then filter the resulting list in one pass.
     * Same end result, far less code to keep in sync with core.
     */
    private function repeatTagStartHandlerOverride(array $attrs, Closure $original): void
    {
        $original($attrs);

        // Inside an open <List> (outer pass): core's handler only bumped the
        // nesting counter. Remember the filter attributes so that the </List>
        // handler can apply them to the records BEFORE the replay (the replay
        // runs through core's own table and never reaches this override).
        if ($this->list_open && $this->list_fact_filter_attrs === null && $this->hasFilterAttrs($attrs)) {
            $this->list_fact_filter_attrs = $attrs;
        }

        // core's own re-entrancy guard: only the OUTERMOST RepeatTag call
        // actually (re)populates $delegate->repeats -- nested calls made
        // while core is still walking the outer tag's own scope return
        // early and leave $repeats untouched, so filtering now would be
        // filtering nothing (or double-filtering a list already filtered
        // on the way out). Only touch $repeats when this WAS the call that
        // populated it.
        if ($this->getFromDelegatePrivatePropertyWithReflection('process_repeats') !== 1) {
            return;
        }

        $filters  = [];
        $filters2 = [];
        $this->prepareFactFilters($attrs, $filters, $filters2);

        if ($filters === [] && $filters2 === []) {
            return;
        }

        $reflection        = new ReflectionObject($this->delegate);
        $repeats_property  = $reflection->getProperty('repeats');
        $repeats_property->setAccessible(true);
        $repeats           = $repeats_property->getValue($this->delegate);

        $kept = [];

        foreach ($repeats as $subrecord) {
            $keep = true;

            foreach ($filters as $filter) {
                if (!preg_match('/' . $filter . '/i', $subrecord)) {
                    $keep = false;
                    break;
                }
            }

            if ($keep && $filters2 !== []) {
                $keep = $this->applyFactFilters($subrecord, $filters2);
            }

            if ($keep) {
                $kept[] = $subrecord;
            } else {
                $this->repeats_rec_filtered_out++;
            }
        }

        $repeats_property->setValue($this->delegate, $kept);
    }

    /**
     * @param array<string,string> $attrs
     */
    private function hasFilterAttrs(array $attrs): bool
    {
        foreach ($attrs as $key => $value) {
            if (preg_match('/^filter\d$/', $key) === 1 && $value !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * WORKAROUND for the architectural limit described in the class docblock.
     *
     * <List> replays its body through the DELEGATE's own handler table, so a
     * <RepeatTag filterN=...> nested in it never reaches
     * repeatTagStartHandlerOverride(). What DOES run through our table is the
     * OUTER pass of <List> (start and end tags). At </List>, right before core
     * replays the body once per record, $delegate->list is populated and still
     * editable. So we apply the nested RepeatTag's filters HERE, to the
     * records themselves: level-1 facts that do not match are removed from a
     * copy of each record, and records left with no matching fact are dropped
     * (so ListTotal is also correct). The replay then sees only matching facts.
     */
    private function prefilterDelegateList(): void
    {
        $attrs = $this->list_fact_filter_attrs;
        $tag   = $attrs['tag'] ?? '';

        // Only level-1 tags (e.g. _TODO) are supported by this workaround.
        if ($attrs === null || $tag === '' || $tag === '@desc' || str_contains($tag, ':')) {
            return;
        }

        $filters  = [];
        $filters2 = [];
        $this->prepareFactFilters($attrs, $filters, $filters2);

        if ($filters === [] && $filters2 === []) {
            return;
        }

        $list = $this->getFromDelegatePrivatePropertyWithReflection('list');
        $kept = [];

        foreach ($list as $record) {
            if (!$record->canShow()) {
                $kept[] = $record; // core counts these as private
                continue;
            }

            [$gedcom, $remaining] = $this->filterLevelOneFacts($record->gedcom(), $tag, $filters, $filters2);

            if ($remaining === 0) {
                $this->repeats_rec_filtered_out++;
                continue;
            }

            if ($record instanceof Individual) {
                $kept[] = Registry::individualFactory()->new($record->xref(), $gedcom, null, $record->tree());
            } elseif ($record instanceof Family) {
                $kept[] = Registry::familyFactory()->new($record->xref(), $gedcom, null, $record->tree());
            } else {
                $kept[] = $record;
            }
        }

        $property = (new ReflectionObject($this->delegate))->getProperty('list');
        $property->setAccessible(true);
        $property->setValue($this->delegate, $kept);
    }

    /**
     * Remove the level-1 facts "1 $tag" that fail the filters.
     *
     * @param array<int,string>              $filters
     * @param array<int,array<string,mixed>> $filters2
     *
     * @return array{0:string,1:int} [new gedcom, number of matching $tag facts left]
     */
    private function filterLevelOneFacts(string $gedcom, string $tag, array $filters, array $filters2): array
    {
        $blocks    = preg_split('/\n(?=1 )/', $gedcom) ?: [$gedcom];
        $out       = [];
        $remaining = 0;
        $is_tag    = '/^1 ' . preg_quote($tag, '/') . '(?: |$)/';

        foreach ($blocks as $index => $block) {
            if ($index > 0 && preg_match($is_tag, $block) === 1) {
                $keep = true;

                foreach ($filters as $filter) {
                    if (!preg_match('/' . $filter . '/i', $block)) {
                        $keep = false;
                        break;
                    }
                }

                if ($keep && $filters2 !== []) {
                    $keep = $this->applyFactFilters($block, $filters2);
                }

                if (!$keep) {
                    continue;
                }

                $remaining++;
            }

            $out[] = $block;
        }

        return [implode("\n", $out), $remaining];
    }

    /**
     * Ported unchanged from MitalteliReportParserGenerate_2_1's
     * prepareFactFilters(), except: $this->getMitVars() is gone entirely --
     * 2.1 needed it only to work around core's old array-shaped $vars; 2.3
     * exposes report variables through a plain VariableTable (has/get/set),
     * so "$vars_parent[$match[1]]['id']" becomes "$variables->get($match[1])"
     * directly, no format-conversion helper needed.
     */
    private function prepareFactFilters(array $attrs, array &$filters, array &$filters2): void
    {
        $condition = '';
        $match     = [];
        $filters   = [];
        $filters2  = [];

        $gedrec = $this->getFromDelegatePrivatePropertyWithReflection('gedrec');
        $fact   = $this->getFromDelegatePrivatePropertyWithReflection('fact');
        $desc   = $this->getFromDelegatePrivatePropertyWithReflection('desc');

        foreach ($attrs as $key => $value) {
            if (preg_match('/filter(\d)/', $key)) {
                $condition = $value;
                if (preg_match('/@(\w+)/', $condition, $match)) {
                    $id    = $match[1];
                    $value = "''";
                    if ($id === 'ID') {
                        if (preg_match('/0 @(.+)@/', $gedrec, $match)) {
                            $value = "'" . $match[1] . "'";
                        }
                    } elseif ($id === 'fact') {
                        $value = "'" . $fact . "'";
                    } elseif ($id === 'desc') {
                        $value = "'" . $desc . "'";
                    } elseif (preg_match("/\\d $id (.+)/", $gedrec, $match)) {
                        $value = "'" . str_replace('@', '', trim($match[1])) . "'";
                    }
                    $condition = preg_replace("/@$id/", $value, $condition);
                }

                if (preg_match('/(?P<tag>' . Gedcom::REGEX_TAG . '(' . ':' . Gedcom::REGEX_TAG . ')*' . ')\s*(?P<expr>[^\s$]+)\s*(?P<val>.+)/', $condition, $match)) {
                    $tag  = trim($match['tag']);
                    $expr = trim($match['expr']);
                    $val  = trim($match['val']);

                    $variables = $this->getFromDelegatePrivatePropertyWithReflection('variables');
                    while (preg_match('/\\$(\w+)/', $val, $match)) {
                        $regex = '/[$]' . $match[1] . '\b/u';
                        $val   = preg_replace($regex, trim($variables->get($match[1])), $val);
                    }

                    if ($val !== '') {
                        $searchstr = '';
                        $tags      = explode(':', $tag);

                        if (count($tags) > 1) {
                            $level = 1;
                            $t     = 'XXXX';
                            foreach ($tags as $t) {
                                if (!empty($searchstr)) {
                                    $searchstr .= "[^\n]*(\n[2-9][^\n]*)*\n";
                                }
                                if ($t === 'EMAIL' || $t === '_EMAIL') {
                                    $t = '_?EMAIL';
                                }
                                $searchstr .= $level . ' ' . $t;
                                $level++;
                            }
                        } else {
                            if ($tag === 'EMAIL' || $tag === '_EMAIL') {
                                $tag = '_?EMAIL';
                            }
                            $t         = $tag;
                            $searchstr = '1 ' . $tag;
                        }

                        switch ($expr) {
                            case 'CONTAINS':
                                if ($t === 'PLAC') {
                                    $searchstr .= "[^\n]*[, ]*" . $val;
                                } else {
                                    $searchstr .= "[^\n]*" . $val;
                                }
                                $filters[] = $searchstr;
                                break;

                            case 'CONTAINS_R':
                                $strings = $this->searchReplaceStringsFromContainsRegexString($tag, $val);

                                if ($strings === null) {
                                    break;
                                }

                                $filters2[] = [
                                    'tag'                     => $tag,
                                    'searchstr'               => $strings[self::CONTAINS_R_OP['searchstr']['KEY']],
                                    'replacestr'              => $strings[self::CONTAINS_R_OP['HAYSTACK_REPLACE_R']['KEY']],
                                    'remove'                  => $strings[self::CONTAINS_R_OP['HAYSTACK_REMOVE_WANT']['KEY']],
                                    'incshrdnotesval'         => $strings[self::CONTAINS_R_OP['HAYSTACK_INC_SHRD_NOTES_VAL']['KEY']],
                                    'want_regex'              => $strings[self::CONTAINS_R_OP['HAYSTACK_WANT_R']['KEY']],
                                    'needlestr'               => $strings[self::CONTAINS_R_OP['NEEDLE']['KEY']],
                                    'needle_wholeword'        => $strings[self::CONTAINS_R_OP['NEEDLE_OP_WHOLE_WORD']['KEY']],
                                    'needle_ignorecase'       => $strings[self::CONTAINS_R_OP['NEEDLE_OP_IGNORE_CASE']['KEY']],
                                    'needle_removediacritics' => $strings[self::CONTAINS_R_OP['NEEDLE_OP_REMOVE_DIACRITICS']['KEY']],
                                ];
                                break;

                            default:
                                $filters2[] = [
                                    'tag'  => $tag,
                                    'expr' => $expr,
                                    'val'  => $val,
                                ];
                                break;
                        }
                    }
                }
            }
        }
    }

    /**
     * Ported from MitalteliReportParserGenerate_2_1's applyFactFilters().
     * Two real changes, both because core exposed better tools in 2.3:
     *   - self::getSubRecord() / $this->getGedcomValue() (old, private,
     *     reflection-only) -> GedcomTextReader::getSubRecord()/
     *     getGedcomValue() (new, public, static -- no reflection needed).
     *   - the "incshrdnotesval" branch used to call the fully generic
     *     traverseTag() helper; that helper is NOT ported (core's new
     *     RepeatTag already reimplements that general tag-path walk, so a
     *     second generic copy isn't worth maintaining) -- instead this one
     *     remaining caller gets a narrow, purpose-built replacement:
     *     substituteLinkedNoteValues() below.
     *
     * @param array<int,array<string,mixed>> $filters2
     */
    private function applyFactFilters(string $grec, array $filters2): bool
    {
        $keep = true;
        $tree = $this->getFromDelegatePrivatePropertyWithReflection('tree');
        $context = $this->getFromDelegatePrivatePropertyWithReflection('context');

        foreach ($filters2 as $filter) {
            if (!$keep) {
                continue;
            }

            $search_exists = isset($filter['searchstr']);

            if (!$search_exists) {
                $tag  = $filter['tag'];
                $expr = $filter['expr'];
                $val  = $filter['val'];
                if ($val === "''") {
                    $val = '';
                }
                $tags = explode(':', $tag);
                $t    = end($tags);

                $v = GedcomTextReader::getGedcomValue($tag, 1, $grec, $tree, $context);

                if ($t === 'EMAIL' && $v === '') {
                    $tag  = str_replace('EMAIL', '_EMAIL', $tag);
                    $tags = explode(':', $tag);
                    $t    = end($tags);
                    $v    = GedcomTextReader::getSubRecord(1, $tag, $grec);
                }

                switch ($expr) {
                    case 'GTE':
                        if ($t === 'DATE') {
                            $keep = Date::compare(new Date($v), new Date($val)) >= 0;
                        } else {
                            $keep = $val >= $v;
                        }
                        break;

                    case 'LTE':
                        if ($t === 'DATE') {
                            $keep = Date::compare(new Date($v), new Date($val)) <= 0;
                        } else {
                            $keep = $val >= $v;
                        }
                        break;

                    case 'NE':
                        $keep = $v != $val;
                        break;

                    default:
                        $keep = $v == $val;
                        break;
                }
            } else {
                $tag             = $filter['tag'];
                $searchstr       = $filter['searchstr'];
                $replacestr      = $filter['replacestr'];
                $remove          = $filter['remove'];
                $incshrdnotesval = $filter['incshrdnotesval'];
                $want_regex      = $filter['want_regex'];
                $needlestr       = $filter['needlestr'];
                $needle_ww       = $filter['needle_wholeword'];
                $needle_ic       = $filter['needle_ignorecase'];
                $needle_rd       = $filter['needle_removediacritics'];

                if ($incshrdnotesval) {
                    $grec = $this->substituteLinkedNoteValues($grec, $tag);
                }

                $haystack = "\n" . $grec;
                $haystack = preg_replace('/' . $searchstr . '/', $replacestr, $haystack);

                if ($remove) {
                    $regex    = '\n(?:1 ' . $tag . '|' . $want_regex . ') ?';
                    $haystack = preg_replace('/' . $regex . '/', "\n", $haystack);
                }

                if ($needle_rd) {
                    $haystack  = $this->removeDiacritics($haystack);
                    $needlestr = $this->removeDiacritics($needlestr);
                }

                $needlestr = preg_quote($needlestr, '/');

                if ($needle_ww) {
                    $needlestr = '\b' . $needlestr . '\b';
                }

                if ($needle_ic) {
                    $needlestr = '(?i)' . $needlestr;
                }

                $needlestr = str_replace(' ', '\s*', $needlestr);
                $haystack  = preg_replace('/\n/', ' ', $haystack);

                if (!preg_match('/' . $needlestr . '/', $haystack)) {
                    $keep = false;
                }
            }
        }

        return $keep;
    }

    /**
     * Narrow replacement for the one thing MitalteliReportParserGenerate_2_1
     * still needed its generic traverseTag() helper for: finding "$tag:NOTE"
     * sub-records within $grec and, for any that are a cross-reference to a
     * shared note, inlining that note's actual text in place of the raw
     * "@N123@" so CONTAINS_R matching can search inside it too.
     *
     * Not a generic port -- core's own RepeatTag handler already does the
     * general colon-delimited tag-path walk (see
     * Fisharebest\Webtrees\Report\ParserGenerate::repeatTagStartHandler());
     * duplicating that generically here just to serve this one caller isn't
     * worth the upkeep, so this mirrors that same walk narrowly, scoped to
     * exactly "$tag:NOTE".
     */
    private function substituteLinkedNoteValues(string $grec, string $tag): string
    {
        $tree    = $this->getFromDelegatePrivatePropertyWithReflection('tree');
        $context = $this->getFromDelegatePrivatePropertyWithReflection('context');

        $level = (int) explode(' ', trim($grec))[0];
        if ($level === 0) {
            $level++;
        }

        $subrecord = GedcomTextReader::getSubRecord($level, "$level $tag", $grec);
        if ($subrecord === '') {
            return $grec;
        }

        $note_level  = $level + 1;
        $note_count  = preg_match_all("/$note_level NOTE(.*)/", $subrecord, $ignored, PREG_SET_ORDER);
        $local_grec  = $grec;

        for ($i = 1; $i <= $note_count; $i++) {
            $note_subrecord = GedcomTextReader::getSubRecord($note_level, "$note_level NOTE", $subrecord, $i);

            if (preg_match('/^\d ' . Gedcom::REGEX_TAG . ' @(' . Gedcom::REGEX_XREF . ')@/', $note_subrecord, $xref_match)) {
                $linked_object = Registry::gedcomRecordFactory()->make($xref_match[1], $tree);
                if ($linked_object !== null && !$linked_object->canShow()) {
                    continue;
                }
            }

            if (preg_match('/^(\d) ' . Gedcom::REGEX_TAG . ' (@' . Gedcom::REGEX_XREF . '@)/', $note_subrecord, $xref_match)) {
                $value      = GedcomTextReader::getGedcomValue('NOTE', (int) $xref_match[1], $note_subrecord, $tree, $context);
                $local_grec = str_replace($xref_match[2], $value, $local_grec);
            }
        }

        return $local_grec;
    }
}
