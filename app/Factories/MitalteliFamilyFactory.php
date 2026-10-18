<?php

declare(strict_types=1);

namespace vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Factories;

use Fisharebest\Webtrees\Factories\FamilyFactory;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;

/**
 * Family factory used ONLY while the report parameters page is built on
 * webtrees 2.0 - 2.2 (see MitalteliReportSetupPage_Legacy).
 *
 * The core handler passes the same "xref" from the URL (normally the user's
 * individual) to both the individual and the family selectors. Since that xref
 * is not a family, the family selector used to be empty. Here, if the xref
 * belongs to an individual, their first family as a spouse is returned or, if
 * they have none, the family they are a child of.
 *
 * Syntax stays compatible with PHP 7.3 (webtrees 2.0).
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
