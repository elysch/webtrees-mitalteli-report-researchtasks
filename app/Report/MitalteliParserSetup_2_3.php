<?php

declare(strict_types=1);

namespace vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Report;

/**
 * webtrees renders the report *setup* page (where the user fills in
 * parameters before generating) with a completely separate parser from the
 * one that actually generates the report: Fisharebest\Webtrees\Report\ParserSetup.
 * It only cares about <Input>, <Title>, <Description>, <Style>, <var> — every
 * other tag is a no-op — but it is `final` and its startHandlers()/endHandlers()
 * tables don't list this module's custom tags (GetAllPersonNames,
 * ListTotalReset, url) or the "Textbox" (lowercase b) variant used in
 * report.xml, so it throws "Unknown XML element" before the setup page ever
 * renders.
 *
 * Same composition/reflection pattern as MitalteliParserGenerate_2_3: we
 * can't extend `ParserSetup` (final), so we extend the open `AbstractParser`
 * and harvest ParserSetup's own handler table via reflection, then just add
 * no-op entries for the tags it doesn't know about — the setup page doesn't
 * need real behaviour for them, it only needs to not crash while it looks
 * for <Input>/<Title>/<Description>.
 */

use Fisharebest\Webtrees\Report\AbstractParser;
use Fisharebest\Webtrees\Report\InputDefinition;
use Fisharebest\Webtrees\Report\ParserSetup;

use Closure;
use ReflectionClass;

class MitalteliParserSetup_2_3 extends AbstractParser
{
    private ParserSetup $delegate;

    public function __construct(string $report)
    {
        $this->delegate = new ParserSetup($report);

        parent::__construct($report);
    }

    protected function startHandlers(): array
    {
        $handlers = $this->harvestDelegateHandlers('startHandlers');

        foreach ($this->extraTags() as $tag) {
            $handlers[$tag] ??= $this->noop(...);
        }

        return $handlers;
    }

    protected function endHandlers(): array
    {
        $handlers = $this->harvestDelegateHandlers('endHandlers');

        foreach ($this->extraTags() as $tag) {
            $handlers[$tag] ??= $this->noop(...);
        }

        return $handlers;
    }

    /**
     * Tags this module's report.xml uses that core's ParserSetup does not
     * define at all. Add to this list if a future edit of report.xml
     * introduces another tag core doesn't know about and you get another
     * "Unknown XML element" on THIS page (as opposed to the report-run page).
     *
     * @return list<string>
     */
    private function extraTags(): array
    {
        return ['GetAllPersonNames', 'ListTotalReset', 'url', 'Textbox'];
    }

    protected function noop(): void
    {
    }

    /** @return array<string,Closure> */
    private function harvestDelegateHandlers(string $method): array
    {
        $reflection = new ReflectionClass(ParserSetup::class);
        $method     = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invoke($this->delegate);
    }

    // --- passthrough accessors: the real state lives on $this->delegate,
    // because the harvested closures are bound to it, not to $this. ---

    public function reportTitle(): string
    {
        return $this->delegate->reportTitle();
    }

    public function reportDescription(): string
    {
        return $this->delegate->reportDescription();
    }

    /** @return array<InputDefinition> */
    public function reportInputs(): array
    {
        return $this->delegate->reportInputs();
    }
}
