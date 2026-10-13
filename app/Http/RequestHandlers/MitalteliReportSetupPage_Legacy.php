<?php

declare(strict_types=1);

namespace vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Http\RequestHandlers;

use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Factories\MitalteliFamilyFactory;

use Fisharebest\Webtrees\Registry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Página de parámetros del reporte en webtrees 2.0 / 2.1 / 2.2.
 *
 * Delega TODO en el manejador del núcleo (ReportSetupPage), de modo que no hay
 * copia de código del núcleo que se desincronice. Lo único que hace es sustituir
 * temporalmente la factoría de familias para que el selector de familia se abra
 * con la familia del individuo preseleccionado (ver MitalteliFamilyFactory).
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
