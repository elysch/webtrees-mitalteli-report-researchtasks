<?php

declare(strict_types=1);

namespace vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Factories;

use Fisharebest\Webtrees\Factories\FamilyFactory;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;

/**
 * Factoría de familias usada SOLO mientras se construye la página de parámetros
 * del reporte en webtrees 2.0 - 2.2 (ver MitalteliReportSetupPage_Legacy).
 *
 * El manejador del núcleo pasa el mismo "xref" de la URL (normalmente el individuo
 * del usuario) a los selectores de individuo y de familia. Como ese xref no es de
 * una familia, el selector de familia quedaba vacío. Aquí, si el xref es de un
 * individuo, se devuelve su primera familia como cónyuge o, si no tiene, la
 * familia de la que es hijo/a.
 *
 * Se mantiene la sintaxis compatible con PHP 7.3 (webtrees 2.0).
 */
class MitalteliFamilyFactory extends FamilyFactory
{
    public function make(string $xref, Tree $tree, ?string $gedcom = null): ?Family
    {
        $family = parent::make($xref, $tree, $gedcom);

        if ($family === null && $gedcom === null && $xref !== '') {
            $individual = Registry::individualFactory()->make($xref, $tree);

            if ($individual !== null) {
                $family = $individual->spouseFamilies()->first()
                    ?? $individual->childFamilies()->first();
            }
        }

        return $family;
    }
}
