<?php

declare(strict_types=1);

namespace vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Http\RequestHandlers;

use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Factories\MitalteliFamilyFactory;

use Fisharebest\Webtrees\Registry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Report parameters page on webtrees 2.0 / 2.1 / 2.2.
 *
 * Delegates EVERYTHING to the core handler (ReportSetupPage), so there is no
 * copy of core code that could drift out of sync. The only thing it does is
 * temporarily replace the family factory so that the family selector opens
 * with the preselected individual's family (see MitalteliFamilyFactory).
 */
class MitalteliReportSetupPage_Legacy implements RequestHandlerInterface
{
    /** @var RequestHandlerInterface */
    private $core;

    public function __construct(RequestHandlerInterface $core)
    {
        $this->core = $core;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $original = Registry::familyFactory();
        Registry::familyFactory(new MitalteliFamilyFactory());

        try {
            return $this->core->handle($request);
        } finally {
            Registry::familyFactory($original);
        }
    }
}
