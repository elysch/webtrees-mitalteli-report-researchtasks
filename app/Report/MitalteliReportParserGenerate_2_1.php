<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2025 webtrees development team
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

use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\ResearchTasksReportModule;

use Fisharebest\Webtrees\Report\ReportParserGenerate;
use Fisharebest\Webtrees\Report\AbstractRenderer;

use VERSION;

use DomainException;
use InvalidArgumentException;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Elements\UnknownElement;
use Fisharebest\Webtrees\Factories\MarkdownFactory;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Str;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

use function addcslashes;
use function array_pop;
use function count;
use function end;
use function explode;
use function in_array;
use function method_exists;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function preg_split;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strip_tags;
use function strlen;
use function strpos;
use function substr;
use function trim;
use function uasort;
use function xml_get_current_line_number;

use const PREG_SET_ORDER;

/**
 * Class MitalteliReportParserGenerate_2_1 - parse a report.xml file and generate the report.
 */
class MitalteliReportParserGenerate_2_1 extends ReportParserGenerate
{

    // Fisharebest\Webtrees\Report\ReportParserGenerate
    private $parent_reflection_class;

    private $tag_url;

    private $repeats_rec_filtered_out = 0;

    #USE AN OPTIONS ARRAY CONSTANT TO MAKE SURE THE OPTIONS ARE WRITTEN CORRECTLY
    private const CONTAINS_R_OP = [
        'NEEDLE'                      => [ "KEY" => "n"    ],
        'NEEDLE_OP_WHOLE_WORD'        => [ "KEY" => "nww"  ],
        'NEEDLE_OP_IGNORE_CASE'       => [ "KEY" => "nic"  ],
        'NEEDLE_OP_REMOVE_DIACRITICS' => [ "KEY" => "nrd"  ],
        'HAYSTACK_REPLACE_R'          => [ "KEY" => "hrr"  ],
        'HAYSTACK_WANT_R'             => [ "KEY" => "hwr"  ],
        'HAYSTACK_DONT_WANT_R'        => [ "KEY" => "hdwr" ],
        'HAYSTACK_REMOVE_WANT'        => [ "KEY" => "hrw"  ],     
        'REPEAT_W_DW_R'               => [ "KEY" => "hrn"  ],
        'searchstr'                   => [ "KEY" => "sstr" ],
    ];

    /**
     * Get the value of a private property from the parent class using reflection
     *
     * @param string $attribute_name The name of the private property in the parent class
     *
     * @return mixed The value of the private property
     *
     * @throws \ReflectionException If the property does not exist
     */
    public function getFromParentPrivatePropertyWithReflection(string $attribute_name)
    {
        $property = $this->parent_reflection_class->getProperty($attribute_name);
        $property->setAccessible(true); // Make the private property accessible
        return $property->getValue($this); // Get the value from the parent object instance
    }

    /**
     * Set the value of a private property from the parent class using reflection
     *
     * @param string $attribute_name The name of the private property in the parent class
     * @param mixed  $value          The value to set
     *
     * @return void
     *
     * @throws \ReflectionException If the property does not exist
     */
    public function setToParentPrivatePropertyWithReflection(string $attribute_name, $value)
    {
        $property = $this->parent_reflection_class->getProperty($attribute_name);
        $property->setAccessible(true);
        $property->setValue($this, $value); // Set the value on the parent object instance
    }

    /**
     * Call a private method from the parent class using reflection
     *
     * @param string $methodName The name of the private method in the parent class
     * @param mixed  ...$params  The parameters to pass to the method
     *
     * @return mixed The return value of the private method
     *
     * @throws \ReflectionException If the method does not exist
     */
    public function callFromParentPrivateMethodWithReflection($methodName)
    {
        if (!method_exists($this, $methodName)) {
            throw new \ReflectionException("Method {$methodName} does not exist in parent object.");
        }
        $reflectionMethod = $this->parent_reflection_class->getMethod($methodName);
        $reflectionMethod->setAccessible(true);

        $params = array_slice(func_get_args(), 1); //get all the parameters after $methodName
        return $reflectionMethod->invokeArgs($this, $params);
    }

    /**
     * Create a parser for a report
     *
     * @param string               $report The XML filename
     * @param AbstractRenderer     $report_root
     * @param array<array<string>> $vars
     * @param Tree                 $tree
     */
    public function __construct(string $report, AbstractRenderer $report_root, array $vars, Tree $tree)
    {
        $coreWebtreesCustomizationDir = ResearchTasksReportModule::getCoreWebtreesCustomizationDirectory();

        // Check if the report string contains the module directory path
        // if not running from this module, redirect to the standard ReportGenerate handler
        if (str_contains($report, $coreWebtreesCustomizationDir)) {
            $this_reflection_class = new \ReflectionClass('\vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Report\MitalteliReportParserGenerate_2_1');
            $this->parent_reflection_class = $this_reflection_class->getParentClass();
        }
        parent::{__FUNCTION__}($report, $report_root, $vars, $tree);
    }

    /**
     * Handle <url>
     *
     * @return void
     */
    protected function urlStartHandler(array $attrs): void
    {
        if (empty($attrs['url'])) {
            $parser_parent = $this->getFromParentPrivatePropertyWithReflection('parser');
            throw new DomainException('REPORT ERROR url: The attribute "url=" is missing, not set or empty in the XML file for tag url on line: ' . xml_get_current_line_number($parser_parent));
        }

        $this->tag_url = $attrs['url'];
    }

    /**
     * Handle </url>
     *
     * @return void
     */
    protected function urlEndHandler(): void
    {
        if (empty($this->tag_url)) {
            $parser_parent = $this->getFromParentPrivatePropertyWithReflection('parser');
            throw new DomainException('REPORT ERROR /url: The attribute "url=" is missing, not set or empty in the XML file for tag url when closing on line: ' . xml_get_current_line_number($parser_parent));
        }

        if (!empty($this->getFromParentPrivatePropertyWithReflection('current_element')->getValue())) {
            $text = '<a href="' . $this->tag_url . '">' .
                $this->getFromParentPrivatePropertyWithReflection('current_element')->getValue() .
                '</a>';

            $this->getFromParentPrivatePropertyWithReflection('current_element')->setText($text);
        }
        $this->tag_url = '';
    }

    /**
     * Handle <getPersonName />
     * Get the name
     * 1. id is empty - current GEDCOM record
     * 2. id is set with a record id
     *
     * @param array<string> $attrs an array of key value pairs for the attributes
     *
     * @return void
     */
    protected function getPersonNameStartHandler(array $attrs): void
    {
        $coreWebtreesCustomizationDir = ResearchTasksReportModule::getCoreWebtreesCustomizationDirectory();

        // Check if the report string contains the module directory path
        // if not running from this module, redirect to the standard ReportGenerate handler
        if (!str_contains($this->report, $coreWebtreesCustomizationDir)) {
            parent::{__FUNCTION__}($attrs); // Call the parent method and
            return;
        }
        
        $id    = '';
        $match = [];
        if (empty($attrs['id'])) {
            if (preg_match('/0 @(.+)@/', $this->getFromParentPrivatePropertyWithReflection('gedrec'), $match)) {
                $id = $match[1];
            }
        } elseif (preg_match('/\$(.+)/', $attrs['id'], $match)) {
            $vars_parent = $this->getFromParentPrivatePropertyWithReflection('vars');
            if (isset($vars_parent[$match[1]]['id'])) {
                $id = $vars_parent[$match[1]]['id'];
            }
        } elseif (preg_match('/@(.+)/', $attrs['id'], $match)) {
            $gmatch = [];
            if (preg_match("/\d $match[1] @([^@]+)@/", $this->getFromParentPrivatePropertyWithReflection('gedrec'), $gmatch)) {
                $id = $gmatch[1];
            }
        } else {
            $id = $attrs['id'];
        }
        if (!empty($id)) {
            $setvarname = "";;
            if (isset($attrs['setvar'])) {
                $setvarname = $attrs['setvar'];
            }
            unset($setvarappend);
            if (isset($attrs['varappend'])) {
                $setvarappend = $attrs['varappend'];
            }

            $record = Registry::gedcomRecordFactory()->make($id, $this->getFromParentPrivatePropertyWithReflection('tree'));
            if ($record === null) {
                return;
            }
            if (!$record->canShowName()) {
                if (empty($setvarname)) {
                    $this->getFromParentPrivatePropertyWithReflection('current_element')->addText(I18N::translate('Private'));
                } else {
                    $vars_parent = $this->getFromParentPrivatePropertyWithReflection('vars');
                    if (isset($setvarappend)) {
                        $vars_parent[$setvarname]['id'] = $vars_parent[$setvarname]['id'] . $setvarappend . I18N::translate('Private');
                    } else {
                        $vars_parent[$setvarname]['id'] = I18N::translate('Private');
                    }
                    $this->setToParentPrivatePropertyWithReflection('vars', $vars_parent);
                }
            } else {
                $name = $record->fullName();
                $name = strip_tags($name);
                if (!empty($attrs['truncate'])) {
                    $name = Str::limit($name, (int) $attrs['truncate'], I18N::translate('…'));
                } else {
                    $addname = (string) $record->alternateName();
                    $addname = strip_tags($addname);
                    if (!empty($addname)) {
                        $name .= ' ' . $addname;
                    }
                }
                if (empty($setvarname)) {
                    $this->getFromParentPrivatePropertyWithReflection('current_element')->addText(trim($name));
                } else {
                    $vars_parent = $this->getFromParentPrivatePropertyWithReflection('vars');
                    if (isset($setvarappend)) {
                        $vars_parent[$setvarname]['id'] = $vars_parent[$setvarname]['id'] . $setvarappend . trim($name);
                    } else {
                        $vars_parent[$setvarname]['id'] = trim($name);
                    }
                    $this->setToParentPrivatePropertyWithReflection('vars', $vars_parent);
                }
            }
        }
    }

    /**
     * Handle <getAllPersonNames />
     * Get all the names
     * 1. id is empty - current GEDCOM record
     * 2. id is set with a record id
     *
     * @param array<string> $attrs an array of key value pairs for the attributes
     *
     * @return void
     */
    protected function getAllPersonNamesStartHandler(array $attrs): void
    {
        $id    = '';
        $match = [];
        if (empty($attrs['id'])) {
            if (preg_match('/0 @(.+)@/', $this->getFromParentPrivatePropertyWithReflection('gedrec'), $match)) {
                $id = $match[1];
            }
        } elseif (preg_match('/\$(.+)/', $attrs['id'], $match)) {
            $vars_parent = $this->getFromParentPrivatePropertyWithReflection('vars');
            if (isset($vars_parent[$match[1]]['id'])) {
                $id = $vars_parent[$match[1]]['id'];
            }
        } elseif (preg_match('/@(.+)/', $attrs['id'], $match)) {
            $gmatch = [];
            if (preg_match("/\d $match[1] @([^@]+)@/", $this->getFromParentPrivatePropertyWithReflection('gedrec'), $gmatch)) {
                $id = $gmatch[1];
            }
        } else {
            $id = $attrs['id'];
        }
        if (!empty($id)) {
            $setvarname = "";;
            if (isset($attrs['setvar'])) {
                $setvarname = $attrs['setvar'];
            }
            unset($setvarappend);
            if (isset($attrs['varappend'])) {
                $setvarappend = $attrs['varappend'];
            }
            #$separator = "<br />";
            $separator = "\n";
            if (!empty($attrs['separator'])) {
                $separator = $attrs['separator'];
            }

            $record = Registry::gedcomRecordFactory()->make($id, $this->getFromParentPrivatePropertyWithReflection('tree'));
            if ($record === null) {
                return;
            }
            if (!$record->canShowName()) {
                if (empty($setvarname)) {
                    $this->getFromParentPrivatePropertyWithReflection('current_element')->addText(I18N::translate('Private'));
                } else {
                    $vars_parent = $this->getFromParentPrivatePropertyWithReflection('vars');
                    if (isset($setvarappend)) {
                        $vars_parent[$setvarname]['id'] = $vars_parent[$setvarname]['id'] . $setvarappend . I18N::translate('Private');
                    } else {
                        $vars_parent[$setvarname]['id'] = I18N::translate('Private');
                    }
                    $this->setToParentPrivatePropertyWithReflection('vars', $vars_parent);
                }
            } else {
                $result = [];
                $namesArray = $record->getAllNames();
                foreach ($namesArray as $nameTmp) {
                    $nameTmp = strip_tags($nameTmp['full']);
                    if (!empty($attrs['truncate'])) {
                        $nameTmp = Str::limit($nameTmp, (int) $attrs['truncate'], I18N::translate('…'));
                    }
                    $nameTmp = trim($nameTmp);
                    if (!empty($nameTmp)) {
                        array_push($result, trim($nameTmp));
                    }
                }
                $names = implode($separator, $result);
                if (empty($setvarname)) {
                    $this->getFromParentPrivatePropertyWithReflection('current_element')->addText($names);
                } else {
                    $vars_parent = $this->getFromParentPrivatePropertyWithReflection('vars');
                    if (isset($setvarappend)) {
                        $vars_parent[$setvarname]['id'] = $vars_parent[$setvarname]['id'] . $setvarappend . $names;
                    } else {
                        $vars_parent[$setvarname]['id'] = $names;
                    }
                    $this->setToParentPrivatePropertyWithReflection('vars', $vars_parent);
                }
            }
        }
    }

    /**
     * Handle <gedcomValue />
     *
     * @param array<string> $attrs
     *
     * @return void
     */
    protected function gedcomValueStartHandler(array $attrs): void
    {
        $coreWebtreesCustomizationDir = ResearchTasksReportModule::getCoreWebtreesCustomizationDirectory();

        // Check if the report string contains the module directory path
        // if not running from this module, redirect to the standard ReportGenerate handler
        if (!str_contains($this->report, $coreWebtreesCustomizationDir)) {
            parent::{__FUNCTION__}($attrs); // Call the parent method and
            return;
        }
        
        $id    = '';
        $match = [];
        if (preg_match('/0 @(.+)@/', $this->getFromParentPrivatePropertyWithReflection('gedrec'), $match)) {
            $id = $match[1];
        }

        if (isset($attrs['newline']) && $attrs['newline'] === '1') {
            $useBreak = '1';
        } else {
            $useBreak = '0';
        }

        $tag = $attrs['tag'];
        if (!empty($tag)) {
            if ($tag === '@desc') {
                $desc_parent = $this->getFromParentPrivatePropertyWithReflection('desc');
                $value = trim($desc_parent);
                $this->getFromParentPrivatePropertyWithReflection('current_element')->addText($value);
            }
            if ($tag === '@id') {
                $this->getFromParentPrivatePropertyWithReflection('current_element')->addText($id);
            } else {
                $tag = str_replace('@fact', $this->getFromParentPrivatePropertyWithReflection('fact'), $tag);
                if (empty($attrs['level'])) {
                    $level = (int) explode(' ', trim($this->getFromParentPrivatePropertyWithReflection('gedrec')))[0];
                    if ($level === 0) {
                        $level++;
                    }
                } else {
                    $level = (int) $attrs['level'];
                }
                $tags  = preg_split('/[: ]/', $tag);
                $value = $this->callFromParentPrivateMethodWithReflection('getGedcomValue', $tag, $level, $this->getFromParentPrivatePropertyWithReflection('gedrec'));
                switch (end($tags)) {
                    case 'DATE':
                        $tmp   = new Date($value);
                        $dfmt = "%j %F %Y";
                        if (!empty($attrs['truncate'])) {
                            if ($attrs['truncate'] === "d") {
                                $dfmt = "%j %M %Y";
                            }
                            if ($attrs['truncate'] === "Y") {
                                $dfmt = "%Y";
                            }
                        }
                        $value = strip_tags($tmp->display(null, $dfmt));
                        break;
                    case 'PLAC':
                        $tmp   = new Place($value, $this->getFromParentPrivatePropertyWithReflection('tree'));
                        $value = $tmp->shortName();
                        break;
                }
                if ($useBreak === '1') {
                    // Insert <br> when multiple dates exist.
                    // This works around a TCPDF bug that incorrectly wraps RTL dates on LTR pages
                    $value = str_replace('(', '<br>(', $value);
                    $value = str_replace('<span dir="ltr"><br>', '<br><span dir="ltr">', $value);
                    $value = str_replace('<span dir="rtl"><br>', '<br><span dir="rtl">', $value);
                    if (substr($value, 0, 4) === '<br>') {
                        $value = substr($value, 4);
                    }
                }
                $tmp = explode(':', $tag);
                if (in_array(end($tmp), ['NOTE', 'TEXT'], true)) {
                    #MARKDOWN IN REPORTS PRODUCES UNPREDICTED RESULTS AND OFTEN IT DOESN'T LOOK OK
                    #if ($this->tree->getPreference('FORMAT_TEXT') === 'markdown') {
                    #    $value = strip_tags(Registry::markdownFactory()->markdown($value, $this->tree), ['br']);
                    #    $value = strtr($value, [MarkdownFactory::BREAK => ' ']);
                    #} else {
                    $value = str_replace("\n", "<br>", $value);
                    $xvalue = $value;
                    $value = preg_replace("/#+ *([^<]*)/", "<b>$1</b>", $value);
                    $value = preg_replace("/\*\*\*([^\*]*)\*\*\*/", "<b><em>$1</em></b>", $value);
                    $value = preg_replace("/\*\*([^\*]*)\*\*/", "<b>$1</b>", $value);
                    $value = preg_replace("/\*([^*]*)\*/", "<em>$1</em>", $value);
                    #}
                    $value = strtr($value, [MarkdownFactory::BREAK => ' ']);
                }

                if (!empty($attrs['truncate'])) {
                    $value = strip_tags($value);
                    if ((int) $attrs['truncate'] > 0) {
                        $value = Str::limit($value, (int) $attrs['truncate'], I18N::translate('…'));
                    }
                }
                $this->getFromParentPrivatePropertyWithReflection('current_element')->addText($value);
            }
        }
    }

    /**
     * Variable lookup
     * Retrieve predefined variables :
     * @ desc GEDCOM fact description, example:
     *        1 EVEN This is a description
     * @ fact GEDCOM fact tag, such as BIRT, DEAT etc.
     * $ I18N::translate('...')
     * $ language_settings[]
     *
     * @param array<string> $attrs an array of key value pairs for the attributes
     *
     * @return void
     */
    protected function varStartHandler(array $attrs): void
    {
        $coreWebtreesCustomizationDir = ResearchTasksReportModule::getCoreWebtreesCustomizationDirectory();

        // Check if the report string contains the module directory path
        // if not running from this module, redirect to the standard ReportGenerate handler
        if (!str_contains($this->report, $coreWebtreesCustomizationDir)) {
            parent::{__FUNCTION__}($attrs); // Call the parent method and
            return;
        }

        if (!isset($attrs['var'])) {
            $parser_parent = $this->getFromParentPrivatePropertyWithReflection('parser');
            throw new DomainException('REPORT ERROR var: The attribute "var=" is missing or not set in the XML file on line: ' . xml_get_current_line_number($parser_parent));
        }

        $var = $attrs['var'];
        // SetVar element preset variables
        $vars_parent = $this->getFromParentPrivatePropertyWithReflection('vars');
        if (!empty($vars_parent[$var]['id'])) {
            $var = $vars_parent[$var]['id'];
        } else {
            $fact_parent = $this->getFromParentPrivatePropertyWithReflection('fact');
            $type_parent = $this->getFromParentPrivatePropertyWithReflection('type');
            $desc_parent = $this->getFromParentPrivatePropertyWithReflection('desc');

            $tfact = $fact_parent;
            if (($fact_parent === 'EVEN' || $fact_parent === 'FACT') && $type_parent !== '') {
                // Use :
                // n TYPE This text if string
                $tfact = $type_parent;
            } else {
                foreach ([Individual::RECORD_TYPE, Family::RECORD_TYPE] as $record_type) {
                    $element = Registry::elementFactory()->make($record_type . ':' . $fact_parent);

                    if (!$element instanceof UnknownElement) {
                        $tfact = $element->label();
                        break;
                    }
                }
            }

            $var = strtr($var, ['@desc' => $desc_parent, '@fact' => $tfact]);

            if (preg_match('/^I18N::number\((.+)\)$/', $var, $match)) {
                $var = I18N::number((int) $match[1]);
            } elseif (preg_match('/^I18N::translate\(\'(.+)\'\)$/', $var, $match)) {
                $var = I18N::translate($match[1]);
            } elseif (preg_match('/^I18N::translateContext\(\'(.+)\', *\'(.+)\'\)$/', $var, $match)) {
                $var = I18N::translateContext($match[1], $match[2]);
            }
        }
        // Check if variable is set as a date and reformat the date
        if (isset($attrs['date'])) {
            if ($attrs['date'] === '1') {
                $g   = new Date($var);
                $var = $g->display();
            }
        }
        $this->getFromParentPrivatePropertyWithReflection('current_element')->addText($var);
        $this->text = $var; // Used for title/descriptio
    }

    /**
     * Setting upp or changing variables in the XML
     * The XML variable name and value is stored in $this->vars
     *
     * @param array<string> $attrs an array of key value pairs for the attributes
     *
     * @return void
     */
    protected function setVarStartHandler(array $attrs): void
    {
        $coreWebtreesCustomizationDir = ResearchTasksReportModule::getCoreWebtreesCustomizationDirectory();

        // Check if the report string contains the module directory path
        // if not running from this module, redirect to the standard ReportGenerate handler
        if (!str_contains($this->report, $coreWebtreesCustomizationDir)) {
            parent::{__FUNCTION__}($attrs); // Call the parent method and
            return;
        }
        
        if (empty($attrs['name'])) {
            throw new DomainException('REPORT ERROR var: The attribute "name" is missing or not set in the XML file');
        }

        $name  = $attrs['name'];
        $value = $attrs['value'];
        $match = [];
        // Current GEDCOM record strings
        if ($value === '@ID') {
            if (preg_match('/0 @(.+)@/', $this->getFromParentPrivatePropertyWithReflection('gedrec'), $match)) {
                $value = $match[1];
            }
        } elseif ($value === '@fact') {
            $value = $this->getFromParentPrivatePropertyWithReflection('fact');
        } elseif ($value === '@desc') {
            $value = $this->getFromParentPrivatePropertyWithReflection('desc');
        } elseif ($value === '@generation') {
            $value = (string) $this->getFromParentPrivatePropertyWithReflection('generation');
        } elseif ($value === '@base_url') {
            $value = "";
            if (array_key_exists("REQUEST_URI", $_SERVER)) {
                $value = filter_var(urldecode($_SERVER["REQUEST_URI"]), FILTER_SANITIZE_URL);
            }
            $url1 = "";
            $i = strpos($value, "route=");
            if ($i !== false) { // url-rewrite not active
                $url1 = substr($value, 0, $i + 6);
                $value = substr($value, $i + 6);
            }
            $i = strpos($value, "/report");
            if ($i !== false) {
                $value = substr($value, 0, $i);
            }
            $value = $url1 . $value;
        } elseif (preg_match("/@(\w+)/", $value, $match)) {
            $gmatch = [];
            if (preg_match("/\d $match[1] (.+)/", $this->getFromParentPrivatePropertyWithReflection('gedrec'), $gmatch)) {
                $value = str_replace('@', '', trim($gmatch[1]));
            }
        }
        if (preg_match("/\\$(\w+)/", $name, $match)) {
            $vars_parent = $this->getFromParentPrivatePropertyWithReflection('vars');
            $name = $vars_parent["'" . $match[1] . "'"]['id'];
        }
        $count = preg_match_all("/\\$(\w+)/", $value, $match, PREG_SET_ORDER);
        $i     = 0;
        while ($i < $count) {
            $vars_parent = $this->getFromParentPrivatePropertyWithReflection('vars');
            $t     = $vars_parent[$match[$i][1]]['id'];
            $value = preg_replace('/\$' . $match[$i][1] . '/', $t, $value, 1);
            $i++;
        }
        if (preg_match('/^I18N::number\((.+)\)$/', $value, $match)) {
            $value = I18N::number((int) $match[1]);
        } elseif (preg_match('/^I18N::translate\(\'(.+)\'\)$/', $value, $match)) {
            $value = I18N::translate($match[1]);
        } elseif (preg_match('/^I18N::translateContext\(\'(.+)\', *\'(.+)\'\)$/', $value, $match)) {
            $value = I18N::translateContext($match[1], $match[2]);
        }

        // Arithmetic functions
        if (preg_match("/(\d+)\s*([-+*\/])\s*(\d+)/", $value, $match)) {
            // Create an expression language with the functions used by our reports.
            $expression_provider  = new \Fisharebest\Webtrees\Report\ReportExpressionLanguageProvider();
            $expression_cache     = new NullAdapter();
            $expression_language  = new ExpressionLanguage($expression_cache, [$expression_provider]);

            $value = (string) $expression_language->evaluate($value);
        }

        if (str_contains($value, '@')) {
            $value = '';
        }
        $vars_parent = $this->getFromParentPrivatePropertyWithReflection('vars');
        $vars_parent[$name]['id'] = $value;
        $this->setToParentPrivatePropertyWithReflection('vars', $vars_parent);
    }

    /**
     * Handle <list>
     *
     * THE FILTERS MUST HAVE atleast one space (\s regex) to separate expression from the rest.
     * "SOMEFACT='something'" or "SOMEFACT=$variable" won't work, should be specified like this:
     * "SOMEFACT = 'something'" or "SOMEFACT = $variable"
     *
     * @param array<string> $attrs
     *
     * @return void
     */
    protected function listStartHandler(array $attrs): void
    {
        $coreWebtreesCustomizationDir = ResearchTasksReportModule::getCoreWebtreesCustomizationDirectory();

        // Check if the report string contains the module directory path
        // if not running from this module, redirect to the standard ReportGenerate handler
        if (!str_contains($this->report, $coreWebtreesCustomizationDir)) {
            parent::{__FUNCTION__}($attrs); // Call the parent method and
            return;
        }

        /////////////////////////////////////////////////////////////////////////////////////////////////////////
        //                                                                                                     //
        // TODO SORT THE ENTRIES THAT CAN'T BE SORTED USING SQL.                                               // !ESL
        //      Since many gedcom entries are stored in the same database field, in a _TODO report is          //
        //      not possible to sort entries correctly (AFAICT) since the list is by GedcomRecord and not      //
        //      by fact. A GedcomRecord can have more than one _TODO fact.                                     //
        //      So, for example, if we have a list of individuals with _TODO facts, and we want to sort them   //
        //      by _TODO date, we can only sort them by the first _TODO date in the gedcom record.             //
        //      The same applies to filtering. If we want to filter by _TODO date, we can only filter by the   //
        //      first _TODO date in the gedcom record.                                                         //
        //                                                                                                     //
        //       ********************************************************************************************  //
        //       *** NOTE: Since the list is based on individuals or families (GedcomRecord), can't apply ***  //
        //       ***       filters or sort order per fact or per fact attribute.                          ***  //
        //       ********************************************************************************************  //
        //                                                                                                     //
        //       Maybe is possible to sort entries in the repeat array that the <RepeatTag...> tag             //
        //       (repeatTagStartHandler) uses.                                                                 //
        //                                                                                                     //
        /////////////////////////////////////////////////////////////////////////////////////////////////////////

        $this->setToParentPrivatePropertyWithReflection('process_repeats', $this->getFromParentPrivatePropertyWithReflection('process_repeats') + 1);
        if ($this->getFromParentPrivatePropertyWithReflection('process_repeats') > 1) {
            return;
        }

        $match = [];
        if (isset($attrs['sortby'])) {
            $sortby = $attrs['sortby'];
            if (preg_match("/\\$(\w+)/", $sortby, $match)) {
                $vars_parent = $this->getFromParentPrivatePropertyWithReflection('vars');
                $sortby = $vars_parent[$match[1]]['id'];
                $sortby = trim($sortby);
            }
        } else {
            $sortby = 'NAME';
        }

        $listname = $attrs['list'] ?? 'individual';

        // Some filters/sorts can be applied using SQL, while others require PHP
        switch ($listname) {
            case 'pending':
                if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
                    require_once 'MitalteliReportParserGenerate_2_1-listStartHandler-pending-8.php';
                } else {
                    require_once 'MitalteliReportParserGenerate_2_1-listStartHandler-pending-7.php';
                }
                break;

            case 'individual':
                $query = DB::table('individuals')
                    ->where('i_file', '=', $this->getFromParentPrivatePropertyWithReflection('tree')->id())
                    ->select(['i_id AS xref', 'i_gedcom AS gedcom'])
                    ->distinct();

                foreach ($attrs as $attr => $value) {
                    if (str_starts_with($attr, 'filter') && $value !== '') {
                        // Substitute any variables in the filter value
                        $value = $this->callFromParentPrivateMethodWithReflection('substituteVars', $value, false);
                        // Convert the various filters into SQL
                        if (preg_match('/^(\w+):DATE (LTE|GTE) (.+)$/', $value, $match)) {
                            $query->join('dates AS ' . $attr, static function (JoinClause $join) use ($attr): void {
                                $join
                                    ->on($attr . '.d_gid', '=', 'i_id')
                                    ->on($attr . '.d_file', '=', 'i_file');
                            });

                            $query->where($attr . '.d_fact', '=', $match[1]);

                            $date = new Date($match[3]);

                            if ($match[2] === 'LTE') {
                                $query->where($attr . '.d_julianday2', '<=', $date->maximumJulianDay());
                            } else {
                                $query->where($attr . '.d_julianday1', '>=', $date->minimumJulianDay());
                            }

                            // This filter has been fully processed
                            unset($attrs[$attr]);
                        } elseif (preg_match('/^NAME CONTAINS (.+)$/', $value, $match)) {
                            $query->join('name AS ' . $attr, static function (JoinClause $join) use ($attr): void {
                                $join
                                    ->on($attr . '.n_id', '=', 'i_id')
                                    ->on($attr . '.n_file', '=', 'i_file');
                            });
                            // Search the DB only if there is any name supplied
                            $names = explode(' ', $match[1]);
                            foreach ($names as $name) {
                                $query->where($attr . '.n_full', 'LIKE', '%' . addcslashes($name, '\\%_') . '%');
                            }

                            // This filter has been fully processed
                            unset($attrs[$attr]);
                        } elseif (preg_match('/^XREF\s*EQ\s*([^\s]+)\s*$/', $value, $match)) {
                            if (!empty($match[1])) {
                                $query->where('i_id', '=', $match[1]);
                            }

                            // This filter has been fully processed
                            unset($attrs[$attr]);
                        } elseif (preg_match('/^LIKE \/(.+)\/$/', $value, $match)) {
                            // Convert newline escape sequences to actual new lines
                            $match[1] = str_replace('\n', "\n", $match[1]);

                            $query->where('i_gedcom', 'LIKE', $match[1]);

                            // This filter has been fully processed
                            unset($attrs[$attr]);
                        } elseif (preg_match('/^(?:\w*):PLAC CONTAINS (.+)$/', $value, $match)) {
                            // Don't unset this filter. This is just initial filtering for performance
                            $query
                                ->join('placelinks AS ' . $attr . 'a', static function (JoinClause $join) use ($attr): void {
                                    $join
                                        ->on($attr . 'a.pl_file', '=', 'i_file')
                                        ->on($attr . 'a.pl_gid', '=', 'i_id');
                                })
                                ->join('places AS ' . $attr . 'b', static function (JoinClause $join) use ($attr): void {
                                    $join
                                        ->on($attr . 'b.p_file', '=', $attr . 'a.pl_file')
                                        ->on($attr . 'b.p_id', '=', $attr . 'a.pl_p_id');
                                })
                                ->where($attr . 'b.p_place', 'LIKE', '%' . addcslashes($match[1], '\\%_') . '%');
                        } elseif (preg_match('/^(\w*):(\w+) CONTAINS (.+)$/', $value, $match)) {
                            // Don't unset this filter. This is just initial filtering for performance
                            $match[3] = strtr($match[3], ['\\' => '\\\\', '%'  => '\\%', '_'  => '\\_', ' ' => '%']);
                            $like = "%\n1 " . $match[1] . "%\n2 " . $match[2] . '%' . $match[3] . '%';
                            $query->where('i_gedcom', 'LIKE', $like);
                        } elseif (preg_match('/^(\w+) CONTAINS (.*)$/', $value, $match)) {
                            // Don't unset this filter. This is just initial filtering for performance
                            $match[2] = strtr($match[2], ['\\' => '\\\\', '%'  => '\\%', '_'  => '\\_', ' ' => '%']);
                            $like = "%\n1 " . $match[1] . '%' . $match[2] . '%';
                            $query->where('i_gedcom', 'LIKE', $like);
                        }
                    }
                }

                ##!ESL
                #$query_str_tmp = vsprintf(str_replace('?', '%s', $query->toSql()), collect($query->getBindings())->map(function ($binding) {
                #    return is_numeric($binding) ? $binding : "'{$binding}'";
                #    })->toArray());
                #error_log("# QUERY INDIVIDUAL ini #: [" . print_r($query_str_tmp, true) . "] :# QUERY INDIVIDUAL fin #");
                ##die("<pre>". print_r($query_str_tmp, true) ."</pre>");
                ##die("<pre>". print_r($query->getBindings(), true) ."</pre>");

                $this->setToParentPrivatePropertyWithReflection('list', []);

                foreach ($query->get() as $row) {
                    $list = $this->getFromParentPrivatePropertyWithReflection('list');
                    $row_tmp = Registry::individualFactory()->make($row->xref, $this->getFromParentPrivatePropertyWithReflection('tree'), $row->gedcom);
                    $list[$row->xref] = $row_tmp;
                    $this->setToParentPrivatePropertyWithReflection('list', $list);
                }
                break;

            case 'family':
                $query = DB::table('families')
                    ->where('f_file', '=', $this->getFromParentPrivatePropertyWithReflection('tree')->id())
                    ->select(['f_id AS xref', 'f_gedcom AS gedcom'])
                    ->distinct();

                foreach ($attrs as $attr => $value) {
                    if (str_starts_with($attr, 'filter') && $value !== '') {
                        // Substitute any variables in the filter value
                        $value = $this->callFromParentPrivateMethodWithReflection('substituteVars', $value, false);
                        // Convert the various filters into SQL
                        if (preg_match('/^(\w+):DATE (LTE|GTE) (.+)$/', $value, $match)) {
                            $query->join('dates AS ' . $attr, static function (JoinClause $join) use ($attr): void {
                                $join
                                    ->on($attr . '.d_gid', '=', 'f_id')
                                    ->on($attr . '.d_file', '=', 'f_file');
                            });

                            $query->where($attr . '.d_fact', '=', $match[1]);

                            $date = new Date($match[3]);

                            if ($match[2] === 'LTE') {
                                $query->where($attr . '.d_julianday2', '<=', $date->maximumJulianDay());
                            } else {
                                $query->where($attr . '.d_julianday1', '>=', $date->minimumJulianDay());
                            }

                            // This filter has been fully processed
                            unset($attrs[$attr]);
                        } elseif (preg_match('/^LIKE \/(.+)\/$/', $value, $match)) {
                            // Convert newline escape sequences to actual new lines
                            $match[1] = str_replace('\n', "\n", $match[1]);

                            $query->where('f_gedcom', 'LIKE', $match[1]);

                            // This filter has been fully processed
                            unset($attrs[$attr]);
                        } elseif (preg_match('/^NAME CONTAINS (.*)$/', $value, $match)) {
                            if ($sortby === 'NAME' || $match[1] !== '') {
                                $query->join('name AS ' . $attr, static function (JoinClause $join) use ($attr): void {
                                    $join
                                        ->on($attr . '.n_file', '=', 'f_file')
                                        ->where(static function (Builder $query): void {
                                            $query
                                                ->whereColumn('n_id', '=', 'f_husb')
                                                ->orWhereColumn('n_id', '=', 'f_wife');
                                        });
                                });
                                // Search the DB only if there is any name supplied
                                if ($match[1] != '') {
                                    $names = explode(' ', $match[1]);
                                    foreach ($names as $name) {
                                        $query->where($attr . '.n_full', 'LIKE', '%' . addcslashes($name, '\\%_') . '%');
                                    }
                                }
                            }

                            // This filter has been fully processed
                            unset($attrs[$attr]);
                        } elseif (preg_match('/^(?:\w*):PLAC CONTAINS (.+)$/', $value, $match)) {
                            // Don't unset this filter. This is just initial filtering for performance
                            $query
                                ->join('placelinks AS ' . $attr . 'a', static function (JoinClause $join) use ($attr): void {
                                    $join
                                        ->on($attr . 'a.pl_file', '=', 'f_file')
                                        ->on($attr . 'a.pl_gid', '=', 'f_id');
                                })
                                ->join('places AS ' . $attr . 'b', static function (JoinClause $join) use ($attr): void {
                                    $join
                                        ->on($attr . 'b.p_file', '=', $attr . 'a.pl_file')
                                        ->on($attr . 'b.p_id', '=', $attr . 'a.pl_p_id');
                                })
                                ->where($attr . 'b.p_place', 'LIKE', '%' . addcslashes($match[1], '\\%_') . '%');
                        } elseif (preg_match('/^(\w*):(\w+) CONTAINS (.+)$/', $value, $match)) {
                            // Don't unset this filter. This is just initial filtering for performance
                            $match[3] = strtr($match[3], ['\\' => '\\\\', '%'  => '\\%', '_'  => '\\_', ' ' => '%']);
                            $like = "%\n1 " . $match[1] . "%\n2 " . $match[2] . '%' . $match[3] . '%';
                            $query->where('f_gedcom', 'LIKE', $like);
                        } elseif (preg_match('/^(\w+) CONTAINS (.+)$/', $value, $match)) {
                            // Don't unset this filter. This is just initial filtering for performance
                            $match[2] = strtr($match[2], ['\\' => '\\\\', '%'  => '\\%', '_'  => '\\_', ' ' => '%']);
                            $like = "%\n1 " . $match[1] . '%' . $match[2] . '%';
                            $query->where('f_gedcom', 'LIKE', $like);
                        }
                    }
                }

                ##!ESL
                #$query_str_tmp = vsprintf(str_replace('?', '%s', $query->toSql()), collect($query->getBindings())->map(function ($binding) {
                #    return is_numeric($binding) ? $binding : "'{$binding}'";
                #    })->toArray());
                #error_log("# QUERY INDIVIDUAL ini #: [" . print_r($query_str_tmp, true) . "] :# QUERY INDIVIDUAL fin #");
                ##die("<pre>". print_r($query_str_tmp, true) ."</pre>");
                ##die("<pre>". print_r($query->getBindings(), true) ."</pre>");

                $this->setToParentPrivatePropertyWithReflection('list', []);

                foreach ($query->get() as $row) {
                    $list = $this->getFromParentPrivatePropertyWithReflection('list');
                    $row_tmp = Registry::familyFactory()->make($row->xref, $this->getFromParentPrivatePropertyWithReflection('tree'), $row->gedcom);
                    $list[$row->xref] = $row_tmp;
                    $this->setToParentPrivatePropertyWithReflection('list', $list);
                }
                break;

            default:
                throw new DomainException('Invalid list name: ' . $listname);
        }

        $filters  = [];
        $filters2 = [];
        if (isset($attrs['filter1']) && count($this->getFromParentPrivatePropertyWithReflection('list')) > 0) {
            $this->prepareFactFilters($attrs, $filters, $filters2);
        }
        //-- apply other filters to the list that could not be added to the search string
        if ($filters !== []) {
            foreach ($this->getFromParentPrivatePropertyWithReflection('list') as $key => $record) {
                foreach ($filters as $filter) {
                    if (!preg_match('/' . $filter . '/i', $record->privatizeGedcom(Auth::accessLevel($this->getFromParentPrivatePropertyWithReflection('tree'))))) {
                        $list_parent = $this->getFromParentPrivatePropertyWithReflection('list');
                        unset($list_parent[$key]);
                        $this->setToParentPrivatePropertyWithReflection('list', $list_parent);
                        break;
                    }
                }
            }
        }
        if ($filters2 !== []) {
            $mylist = [];
            foreach ($this->getFromParentPrivatePropertyWithReflection('list') as $indi) {
                $key  = $indi->xref();
                $grec = $indi->privatizeGedcom(Auth::accessLevel($this->getFromParentPrivatePropertyWithReflection('tree')));
                $keep = $this->applyFactFilters($grec, $filters2);
                if ($keep) {
                    $mylist[$key] = $indi;
                }
            }
            $this->setToParentPrivatePropertyWithReflection('list', $mylist);
        }

        $list_parent = $this->getFromParentPrivatePropertyWithReflection('list');
        switch ($sortby) {
            case 'NAME':
                uasort($list_parent, GedcomRecord::nameComparator());
                break;
            case 'CHAN':
                uasort($list_parent, GedcomRecord::lastChangeComparator());
                break;
            case 'BIRT:DATE':
                uasort($list_parent, Individual::birthDateComparator());
                break;
            case 'DEAT:DATE':
                uasort($list_parent, Individual::deathDateComparator());
                break;
            case 'MARR:DATE':
                uasort($list_parent, Family::marriageDateComparator());
                break;
            case (substr_compare($sortby, 'DATE', -strlen('DATE'))): # ends_with
                # THIS SORTS ACORDING TO THE FIRST FACT EXISTING IN EACH GedcomRecord (Individual or Family or Pending)
                # Empty dates go to the end

                uasort(
                    $list_parent,
                    static function (GedcomRecord $x, GedcomRecord $y) use ($sortby): int {
                        $date1 = null;
                        $date2 = null;

                        $tmpSortByStr = $sortby;
                        if (substr_count($sortby, ':') > 0) {
                            $tmpSortbyArr  = explode(':', $sortby);
                            array_pop($tmpSortbyArr);
                            $tmpSortByStr = implode(':', $tmpSortbyArr);

                            $fact1 = $x->facts([$tmpSortByStr])->first();

                            if (empty($fact1)) {
                                return 1;
                            } else {
                                $date1 = $fact1->date();
                            }

                            $fact2 = $y->facts([$tmpSortByStr])->first();

                            if (empty($fact2)) {
                                return -1;
                            } else {
                                $date2 = $fact2->date();
                            }

                        } else {
                            #VALIDATE IF STILL WORKS IN THIS CASE
                            $fact1 = $x->facts([])->first();
                            $fact2 = $y->facts([])->first();
                            $date1 = $fact1 ? $fact1->date() : null;
                            $date2 = $fact2 ? $fact2->date() : null;
                        }

                        if (empty($date1) || (! $date1->isOK())) {
                            return 1;
                        }

                        if (empty($date2) || (! $date2->isOK())) {
                            return -1;
                        }

                        return (\Fisharebest\Webtrees\Date::compare($date1, $date2));
                    }
                );
                break;
            case '_TODO':
            case '_TODO:NOTE':
            case '_TODO:_WT_USER':

                # THIS SORTS ACORDING TO THE FIRST FACT EXISTING IN EACH GedcomRecord (Individual or Family or Pending)
                # Empty strings go to the end

                #TODO Handle INDI:_TODO... and FAM:_TODO...

                uasort(
                    $list_parent,
                    static function (GedcomRecord $x, GedcomRecord $y) use ($sortby): int {
                        $string1 = "";
                        $string2 = "";

                        $tmpSortByStr = $sortby;
                        if (substr_count($sortby, ':') > 0) { #Fact with attributes
                            $tmpSortbyArr  = explode(':', $sortby);
                            $attribute = array_pop($tmpSortbyArr);
                            $tmpSortByStr = implode(':', $tmpSortbyArr);

                            $fact1 = $x->facts([$tmpSortByStr])->first();

                            if (empty($fact1)) {
                                return 1;
                            } else {
                                $string1 = $fact1->attribute($attribute);
                            }

                            $fact2 = $y->facts([$tmpSortByStr])->first();

                            if (empty($fact2)) {
                                return -1;
                            } else {
                                $string2 = $fact2->attribute($attribute);
                            }
                        } else { #Fact without attributes
                            $fact1 = $x->facts([$sortby])->first();
                            if (empty($fact1)) {
                                return 1;
                            } else {
                                $string1 = $fact1->value();
                            }

                            $fact2 = $y->facts([$sortby])->first();
                            if (empty($fact2)) {
                                return -1;
                            } else {
                                $string2 = $fact2->value();
                            }
                        }

                        if (empty($string1)) {
                            return 1;
                        }

                        if (empty($string2)) {
                            return -1;
                        }

                        return strcmp($string1, $string2);
                    }
                );
                break;
            default:
                // unsorted or already sorted by SQL
                break;

        }
        $this->setToParentPrivatePropertyWithReflection('list', $list_parent);

        $repeats_stack_parent = $this->getFromParentPrivatePropertyWithReflection('repeats_stack');
        $repeats_stack_parent[] = [
            $this->getFromParentPrivatePropertyWithReflection('repeats'),
            $this->getFromParentPrivatePropertyWithReflection('repeat_bytes')
        ];
        $this->setToParentPrivatePropertyWithReflection('repeats_stack', $repeats_stack_parent);
        $parser_parent = $this->getFromParentPrivatePropertyWithReflection('parser');
        $this->setToParentPrivatePropertyWithReflection('repeat_bytes', xml_get_current_line_number($parser_parent) + 1);
    }

    /**
     * Handle <listTotalReset>
     * Assigns a zero value to list_total, variable that handles the
     * total number is collected from <list> and <relatives>
     *
     * @return void
     */
    protected function listTotalResetStartHandler(): void
    {
        $this->setToParentPrivatePropertyWithReflection('list_total', 0);
        $this->repeats_rec_filtered_out = '0';
    }

    /**
     * Handle <listTotal>
     * Prints the total number of records in a list
     * The total number is collected from <list> and <relatives>
     *
     * @return void
     */
    protected function listTotalStartHandler(): void
    {
        $coreWebtreesCustomizationDir = ResearchTasksReportModule::getCoreWebtreesCustomizationDirectory();

        // Check if the report string contains the module directory path
        // if not running from this module, redirect to the standard ReportGenerate handler
        if (!str_contains($this->report, $coreWebtreesCustomizationDir)) {
            parent::{__FUNCTION__}(); // Call the parent method and
            return;
        }

        if ($this->repeats_rec_filtered_out == 0) {
            parent::{__FUNCTION__}(); // Call the parent method
        } else {
            if ($this->repeats_rec_filtered_out > $this->getFromParentPrivatePropertyWithReflection('list_total')) {
                // Adjust the list total by subtracting the number of filtered out records
                // In theory thish should not happen, but just in case
                $this->repeats_rec_filtered_out = $this->getFromParentPrivatePropertyWithReflection('list_total');
            }
            if ($this->getFromParentPrivatePropertyWithReflection('list_private') == 0) {
                $this->getFromParentPrivatePropertyWithReflection('current_element')->addText((string) ($this->getFromParentPrivatePropertyWithReflection('list_total') - $this->repeats_rec_filtered_out));
            } else {
                $this->getFromParentPrivatePropertyWithReflection('current_element')->addText((($this->getFromParentPrivatePropertyWithReflection('list_total') - $this->repeats_rec_filtered_out) - $this->getFromParentPrivatePropertyWithReflection('list_private')) . ' / ' . ($this->getFromParentPrivatePropertyWithReflection('list_total') - $this->repeats_rec_filtered_out));
            }
        }
    }

    public function removeDiacritics(string $text): string
    {
        $normalized = \Normalizer::normalize($text, \Normalizer::FORM_D);
        $withoutDiacritics = preg_replace('/\p{Mn}/u', '', $normalized);

        return $withoutDiacritics ?: $text;

        #Annother approach using Transliterator
        #$transliterator = \Transliterator::createFromRules(':: Any-Latin; :: Latin-ASCII; :: NFD; :: [:Nonspacing Mark:] Remove; :: Lower(); :: NFC;', Transliterator::FORWARD);
        #return $transliterator->transliterate($string);
    }
    
    /**
     * Convert a string representation of a boolean to a boolean
     *
     * Accepts the following case-insensitive values:
     * - True values: '1', 'true', 'yes', 'on'
     * - False values: '0', 'false', 'no', 'off'
     * - Empty or not set string is considered false
     *
     * @param string $value The input string to convert
     *
     * @return bool The converted boolean value
     *
     * @throws InvalidArgumentException if the input string is not a valid boolean representation
     */
    public function convertValueToBool(?string $value): bool
    {
        $trueValues  = ['1', 'true', 'yes', 'on'];
        $falseValues = ['0', 'false', 'no', 'off'];

        $lowerValue = '';
        if (isset($value)) {
            $lowerValue = strtolower($value);
        }

        if (empty($lowerValue) || in_array($lowerValue, $falseValues, true)) {
            return false;
        }

        if (in_array($lowerValue, $trueValues, true)) {
            return true;
        }

        throw new InvalidArgumentException("Invalid boolean string: $value");
    }

    /**
     * Parse a string of key=value pairs into an associative array
     *
     * This function handles quoted values and spaces within values.
     * It supports single quotes, double quotes, and unquoted values.
     *
     * @param string $inputString The input string containing key=value pairs
     *
     * @return array An associative array of parsed key-value pairs
     */
    public function parseKeyValueString(string $inputString): array
    {
        $result = [];
        // Regex to match key=value pairs, handling quoted values and spaces
        // It captures:
        // 1. The key (non-whitespace characters before '=')
        // 2. The value (either unquoted or single/double-quoted)
        preg_match_all('/(\S+)=(?:(?:\'([^\']*)\')|(?:"([^"]*)")|(\S+))(?:\s|$)/', $inputString, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $key = $match[1];
            $value = null;

            // Determine the actual value based on which capture group matched
            if (isset($match[2]) && $match[2] !== '') { // Single-quoted value
                $value = $match[2];
            } elseif (isset($match[3]) && $match[3] !== '') { // Double-quoted value
                $value = $match[3];
            } elseif (isset($match[4]) && $match[4] !== '') { // Unquoted value
                $value = $match[4];
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Generate search and replace strings from a CONTAINS_R filter
     *
     * @param string $tag The GEDCOM tag to search within (e.g., '_TODO')
     * @param string $val The value string containing parameters for the filter
     *
     * @return array{SEARCH:string,REPLACE:string,REMOVE:bool,HAYSTACK_WANT_R:string,NEEDLE:string} or null if NEEDLE is missing
     * (should be returned an array and only be null if NEEDLE is missing because it means the user is not asking that kind of filter)
     *
     * @throws DomainException if the NEEDLE argument is missing
     */
    public function searchReplaceStringsFromContainsRegexString(string $tag, string $val): ?array
    {
        // Example usage:
        // filter4="_TODO CONTAINS_R NEEDLE='$filter_rt' NEEDLE_OP_WHOLE_WORD='$filter_rt_whole_word' NEEDLE_OP_IGNORE_CASE='$filter_rt_ignore_case' NEEDLE_OP_REMOVE_DIACRITICS='$filter_rt_remove_diacritics' HAYSTACK_WANT_R='2 CONT|2 NOTE|3 CONT' HAYSTACK_DONT_WANT_R='2 DATE|2 _WT_USER' REPEAT_W_DW_R=3 HAYSTACK_REPLACE_R='\1\2\3' HAYSTACK_REMOVE_WANT=1"
        // filter4="_TODO CONTAINS_R NEEDLE='$filter_rt' HAYSTACK_WANT_R='2 CONT|2 NOTE|3 CONT' HAYSTACK_DONT_WANT_R='2 DATE|2 _WT_USER' REPEAT_W_DW_R=3 HAYSTACK_REPLACE_R='\1\2\3' HAYSTACK_REMOVE_WANT=1"
        // filter4="_TODO:NOTE CONTAINS_R NEEDLE='$filter_rt' HAYSTACK_WANT_R='3 CONT' HAYSTACK_REMOVE_WANT=1"

        #REGEX TO GET ALL TEXT VALUES FROM _TODO: /((?:\n((?:1 _TODO|2 CONT|2 NOTE|3 CONT) ?).*)*)(?:(?:\n(?:2 DATE|2 _WT_USER) ?.*)*)((?:\n((?:1 _TODO|2 CONT|2 NOTE|3 CONT) ?).*)*)(?:(?:\n(?:2 DATE|2 _WT_USER) ?.*)*)((?:\n((?:1 _TODO|2 CONT|2 NOTE|3 CONT) ?).*)*)/
        #THE RESULT IS IN \1\2\3
        #THE RESULT CONTAINS ALL THE _TODO LINES, THE CONT AND NOTE LINES, BUT NOT THE DATE AND _WT_USER LINES
        #AFTER THAT IT'S NEEDED TO REMOVE FROM THE RESULT THE STRINGS: "1 _TODO", "2 CONT", "2 NOTE" AND "3 CONT"

        $args = $this->parseKeyValueString($val);

        if (!array_key_exists('NEEDLE', $args)) {
            throw new DomainException('CONTAINS_R requires a NEEDLE argument');
        }

        if (!isset($args['NEEDLE'])) {
            return null;
        }

        if (!isset($args['HAYSTACK_WANT_R'])) {
            $args['HAYSTACK_WANT_R'] = '';
        }

        if (!isset($args['REPEAT_W_DW_R'])) {
            $args['REPEAT_W_DW_R'] = 1;
        }

        if (!isset($args['HAYSTACK_DONT_WANT_R'])) {
            $args['HAYSTACK_DONT_WANT_R'] = '';
        }

        $searchstr = '';
        $searchWant = '((?:\n(?:1 ' . $tag;
        $searchDontwant = '';

        # Build the HAYSTACK_WANT_R part
        if (!empty($args['HAYSTACK_WANT_R'])) {
            $searchWant .= '|' . $args['HAYSTACK_WANT_R'];
        }
        $searchWant .= ') ?.*)*)';

        # Build the HAYSTACK_DONT_WANT_R part
        if (!empty($args['HAYSTACK_DONT_WANT_R'])) {
            $searchDontwant = '(?:(?:\n(?:' . $args['HAYSTACK_DONT_WANT_R'] . ') ?.*)*)';
        }

        for ($i = 0; $i < (int)$args['REPEAT_W_DW_R']; $i++) {
            $searchstr .= $searchWant . $searchDontwant;
        }

        #USE AN OPTIONS ARRAY CONSTANT TO MAKE SURE THE OPTIONS ARE WRITTEN CORRECTLY
        $op = [];
        $op[self::CONTAINS_R_OP["searchstr"]["KEY"]] = $searchstr;
        $op[self::CONTAINS_R_OP["HAYSTACK_REPLACE_R"]["KEY"]] = $args['HAYSTACK_REPLACE_R'] ?? '\0';
        $op[self::CONTAINS_R_OP["HAYSTACK_WANT_R"]["KEY"]] = $args['HAYSTACK_WANT_R'];
        $op[self::CONTAINS_R_OP["HAYSTACK_REMOVE_WANT"]["KEY"]] = $this->convertValueToBool($args['HAYSTACK_REMOVE_WANT'] ?? null);
        $op[self::CONTAINS_R_OP["NEEDLE"]["KEY"]] = $args['NEEDLE'];
        $op[self::CONTAINS_R_OP["NEEDLE_OP_WHOLE_WORD"]["KEY"]] = $this->convertValueToBool($args['NEEDLE_OP_WHOLE_WORD'] ?? null );
        $op[self::CONTAINS_R_OP["NEEDLE_OP_IGNORE_CASE"]["KEY"]] = $this->convertValueToBool($args['NEEDLE_OP_IGNORE_CASE'] ?? null);
        $op[self::CONTAINS_R_OP["NEEDLE_OP_REMOVE_DIACRITICS"]["KEY"]] = $this->convertValueToBool($args['NEEDLE_OP_REMOVE_DIACRITICS'] ?? null);

        return $op;
    }

    /**
     * Prepare the filters that cannot be applied using SQL
     *
     * @param array<string,string> $attrs    The attributes from the XML tag
     * @param array<string,string>    $filters  The filters that can be applied using regex on the gedcom record
     * @param array<string,array{tag:string,expr?:string,val?:string,searchstr?:string,replacestr?:string,remove?:bool,want_regex?:string,needlestr?:string}> $filters2 The filters that need to be applied using PHP
     *
     * @return void
     */
    protected function prepareFactFilters(array $attrs, array &$filters, array &$filters2): void
    {
        $condition = '';
        $match     = [];
        $filters   = [];
        $filters2  = [];

        foreach ($attrs as $key => $value) {
            if (preg_match("/filter(\d)/", $key)) {
                $condition = $value;
                if (preg_match("/@(\w+)/", $condition, $match)) {
                    $id    = $match[1];
                    $value = "''";
                    if ($id === 'ID') {
                        if (preg_match('/0 @(.+)@/', $this->getFromParentPrivatePropertyWithReflection('gedrec'), $match)) {
                            $value = "'" . $match[1] . "'";
                        }
                    } elseif ($id === 'fact') {
                        $value = "'" . $this->getFromParentPrivatePropertyWithReflection('fact') . "'";
                    } elseif ($id === 'desc') {
                        $value = "'" . $this->getFromParentPrivatePropertyWithReflection('desc') . "'";
                    } elseif (preg_match("/\d $id (.+)/", $this->getFromParentPrivatePropertyWithReflection('gedrec'), $match)) {
                        $value = "'" . str_replace('@', '', trim($match[1])) . "'";
                    }
                    $condition = preg_replace("/@$id/", $value, $condition);
                }
                //-- handle regular expressions
                if (preg_match("/(?P<tag>" . Gedcom::REGEX_TAG . "(" . ":" . Gedcom::REGEX_TAG . ")*" . ")\s*(?P<expr>[^\s$]+)\s*(?P<val>.+)/", $condition, $match)) {
                    $tag  = trim($match["tag"]);
                    $expr = trim($match["expr"]);
                    $val  = trim($match["val"]);
                    while (preg_match("/\\$(\w+)/", $val, $match)) {
                        $vars_parent = $this->getFromParentPrivatePropertyWithReflection('vars');
                        //-- Replace all occurrences of $var in the value with the actual value from the vars array with word boundary anchors (\b) in order to prevent middle-word replacements
                        //-- It also uses unicode mode (/u) to handle UTF-8 characters correctly
                        $regex = '/[$]' . $match[1] . '\b/u';
                        $val = preg_replace($regex, trim($vars_parent[$match[1]]['id']), $val);
                    }
                    if ($val !== '') {
                        $searchstr = '';
                        $tags      = explode(':', $tag);
                        //-- only limit to a level number if we are specifically looking at a level
                        if (count($tags) > 1) {
                            $level = 1;
                            $t = 'XXXX';
                            foreach ($tags as $t) {
                                if (!empty($searchstr)) {
                                    $searchstr .= "[^\n]*(\n[2-9][^\n]*)*\n";
                                }
                                //-- search for both EMAIL and _EMAIL... silly double gedcom standard
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
                                    'tag'                      => $tag,
                                    'searchstr'                => $strings[self::CONTAINS_R_OP["searchstr"]["KEY"]],
                                    'replacestr'               => $strings[self::CONTAINS_R_OP["HAYSTACK_REPLACE_R"]["KEY"]],
                                    'remove'                   => $strings[self::CONTAINS_R_OP["HAYSTACK_REMOVE_WANT"]["KEY"]],
                                    'want_regex'               => $strings[self::CONTAINS_R_OP["HAYSTACK_WANT_R"]["KEY"]],
                                    'needlestr'                => $strings[self::CONTAINS_R_OP["NEEDLE"]["KEY"]],
                                    'needle_wholeword'         => $strings[self::CONTAINS_R_OP["NEEDLE_OP_WHOLE_WORD"]["KEY"]],
                                    'needle_ignorecase'        => $strings[self::CONTAINS_R_OP["NEEDLE_OP_IGNORE_CASE"]["KEY"]],
                                    'needle_removediacritics'  => $strings[self::CONTAINS_R_OP["NEEDLE_OP_REMOVE_DIACRITICS"]["KEY"]],
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
     * Apply fact filters to a gedcom record that cannot be applied using SQL

     * @param string $grec    The gedcom record to filter
     * @param array{tag:string,expr?:string,val?:string,searchstr?:string,replacestr?:string,remove?:bool,want_regex?:string,needlestr?:string,needle_wholeword?:bool,needle_ignorecase?:,needle_removediacritics?:bool} $filters2 The filters to apply
     *
     * @return bool true if the record passes all filters, false otherwise
     */
    protected function applyFactFilters(string $grec, array $filters2): bool
    {
        $keep = true;
        foreach ($filters2 as $filter) {
            if ($keep) {
                $search_exists  = isset($filter['searchstr']);

                if (!$search_exists) {
                    $tag  = $filter['tag'];
                    $expr = $filter['expr'];
                    $val  = $filter['val'];
                    if ($val === "''") {
                        $val = '';
                    }
                    $tags = explode(':', $tag);
                    $t    = end($tags);

                    $v = $this->callFromParentPrivateMethodWithReflection('getGedcomValue', $tag, 1, $grec);
                    if ($v === null) {
                        $v = '';
                    }

                    //-- check for EMAIL and _EMAIL (silly double gedcom standard :P)
                    if ($t === 'EMAIL' && empty($v)) {
                        $tag  = str_replace('EMAIL', '_EMAIL', $tag);
                        $tags = explode(':', $tag);
                        $t    = end($tags);
                        $v    = self::getSubRecord(1, $tag, $grec);
                    }

                    switch ($expr) {
                        case 'GTE':
                            if ($t === 'DATE') {
                                $date1 = new Date($v);
                                $date2 = new Date($val);
                                $keep  = (Date::compare($date1, $date2) >= 0);
                            } elseif ($val >= $v) {
                                $keep = true;
                            }
                            break;
                        case 'LTE':
                            if ($t === 'DATE') {
                                $date1 = new Date($v);
                                $date2 = new Date($val);
                                $keep  = (Date::compare($date1, $date2) <= 0);
                            } elseif ($val >= $v) {
                                $keep = true;
                            }
                            break;
                        case 'NE':
                            if ($v != $val) {
                                $keep = true;
                            } else {
                                $keep = false;
                            }
                            break;
                        default:
                            if ($v == $val) {
                                $keep = true;
                            } else {
                                $keep = false;
                            }
                            break;
                    }
                } else {
                    $tag         = $filter['tag'];
                    $searchstr   = $filter['searchstr'];
                    $replacestr  = $filter['replacestr'];
                    $remove      = $filter['remove'];
                    $want_regex  = $filter['want_regex'];
                    $needlestr   = $filter['needlestr'];
                    $needle_ww   = $filter['needle_wholeword'];
                    $needle_ic   = $filter['needle_ignorecase'];
                    $needle_rd   = $filter['needle_removediacritics'];

                    $haystack = "\n" . $grec;

                    $haystack = preg_replace('/' . $searchstr . '/', $replacestr, $haystack);

                    if ($remove) {
                        //-- Remove the want regex from the haystack if requested
                        $regex = '\n(?:1 ' . $tag . '|' . $want_regex . ') ?';
                        $haystack = preg_replace("/" . $regex . "/", "\n", $haystack);
                    }

                    //-- Prepare the search. Remove diacritics if requested, both from haystack and needle
                    if ($needle_rd) {
                        $haystack = $this->removeDiacritics($haystack);
                        $needlestr = $this->removeDiacritics($needlestr);
                    }

                    //-- Prepare the search. Escape special regex characters in needle and "/" delimiter
                    $needlestr = preg_quote($needlestr, '/');

                    //-- Prepare the search. Add word boundary anchors if requested
                    if ($needle_ww) {
                        $needlestr = '\b' . $needlestr . '\b';
                    }

                    //-- Prepare the search. Ignore case if requested
                    if ($needle_ic) {
                        $needlestr = '(?i)' . $needlestr;
                    }

                    //-- Prepare the search. Allow for extra spaces
                    $needlestr = str_replace(' ', '\s*', $needlestr);
                    
                    //-- Prepare the search. Remove new lines from haystack
                    $haystack = preg_replace('/\n/', " ", $haystack);


                    //-- Check if the needle exists in the haystack
                    if (!preg_match('/' . $needlestr . '/', $haystack)) {
                        $keep = false;
                        continue;
                    }
                }
            }
        }

        return $keep;
    }

    /**
     * Handle <repeatTag>
     * Collects all the sub-records that match the tag and optional filters
     * and stores them in the repeats array
     *
     * @param array<string,string> $attrs The attributes from the XML tag
     *
     * @return void
     *
     * @throws DomainException if an invalid tag is provided
     */
    protected function repeatTagStartHandler(array $attrs): void
    {
        $coreWebtreesCustomizationDir = ResearchTasksReportModule::getCoreWebtreesCustomizationDirectory();

        // Check if the report string contains the module directory path
        // if not running from this module, redirect to the standard ReportGenerate handler
        if (!str_contains($this->report, $coreWebtreesCustomizationDir)) {
            parent::{__FUNCTION__}($attrs); // Call the parent method and
            return;
        }

        $this->setToParentPrivatePropertyWithReflection('process_repeats', $this->getFromParentPrivatePropertyWithReflection('process_repeats') + 1);
        if ($this->getFromParentPrivatePropertyWithReflection('process_repeats') > 1) {
            return;
        }

        $repeats_stack = $this->getFromParentPrivatePropertyWithReflection('repeats_stack');
        $repeats_stack[] = [$this->getFromParentPrivatePropertyWithReflection('repeats'), $this->getFromParentPrivatePropertyWithReflection('repeat_bytes')];
        $this->setToParentPrivatePropertyWithReflection('repeats_stack', $repeats_stack);

        $this->setToParentPrivatePropertyWithReflection('repeats', []);

        $this->setToParentPrivatePropertyWithReflection('repeat_bytes', xml_get_current_line_number($this->getFromParentPrivatePropertyWithReflection('parser')));

        $tag = $attrs['tag'] ?? '';
        if (!empty($tag)) {
            if ($tag === '@desc') {
                $value = $this->getFromParentPrivatePropertyWithReflection('desc');
                $value = trim($value);
                $this->getFromParentPrivatePropertyWithReflection('current_element')->addText($value);
            } else {
                $tag   = str_replace('@fact', $this->getFromParentPrivatePropertyWithReflection('fact'), $tag);
                $tags  = explode(':', $tag);
                $level = (int) explode(' ', trim($this->getFromParentPrivatePropertyWithReflection('gedrec')))[0];
                if ($level === 0) {
                    $level++;
                }
                $subrec = $this->getFromParentPrivatePropertyWithReflection('gedrec');
                $t      = $tag;
                $count  = count($tags);
                $i      = 0;
                while ($i < $count) {
                    $t = $tags[$i];
                    if (!empty($t)) {
                        if ($i < ($count - 1)) {
                            $subrec = self::getSubRecord($level, "$level $t", $subrec);
                            if (empty($subrec)) {
                                $level--;
                                $subrec = self::getSubRecord($level, "@ $t", $this->getFromParentPrivatePropertyWithReflection('gedrec'));
                                if (empty($subrec)) {
                                    return;
                                }
                            }
                        }
                        $level++;
                    }
                    $i++;
                }
                $level--;
                $count = preg_match_all("/$level $t(.*)/", $subrec, $match, PREG_SET_ORDER);
                $i     = 0;
                $do_filter = false;
                while ($i < $count) {
                    $do_filter = true;
                    $kept_todo = false;
                    $i++;
                    // Privacy check - is this a link, and are we allowed to view the linked object?
                    $subrecord = self::getSubRecord($level, "$level $t", $subrec, $i);
                    if (preg_match('/^\d ' . Gedcom::REGEX_TAG . ' @(' . Gedcom::REGEX_XREF . ')@/', $subrecord, $xref_match)) {
                        $linked_object = Registry::gedcomRecordFactory()->make($xref_match[1], $this->getFromParentPrivatePropertyWithReflection('tree'));
                        if ($linked_object && !$linked_object->canShow()) {
                            continue;
                        }
                    }
                    $repeats = $this->getFromParentPrivatePropertyWithReflection('repeats');

                    $filters  = [];
                    $filters2 = [];
                    $this->prepareFactFilters($attrs, $filters, $filters2);

                    $keep  = true;
                    if ($filters !== []) {
                        foreach ($filters as $filter) {
                            if (!preg_match('/' . $filter . '/i', $subrecord)) {
                                $keep = false;
                                break;
                            }
                        }
                    }

                    if ($keep) {
                        if ($filters2 !== []) {
                            $keep = $this->applyFactFilters($subrecord, $filters2);
                        }
                    }
                    if ($keep) {
                        $repeats[] = $subrecord;
                        $kept_todo = true;
                    }

                    $this->setToParentPrivatePropertyWithReflection('repeats', $repeats);
                }
                if ($do_filter && (!$kept_todo)) {
                    $this->repeats_rec_filtered_out++;
                }
            }
        }
    }
}
