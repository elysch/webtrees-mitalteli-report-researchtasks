<?php

declare(strict_types=1);

namespace vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace;

use Fisharebest\Webtrees\I18N;

/**
 * Default values of the report parameters page that the administrator can define.
 *
 * They are used only when the page is opened "fresh" (menu or link). Choices made by the visitor
 * (reload, history navigation, "open in a new tab") always take precedence.
 *
 * Security: the stored value is NEVER trusted. It is validated against the list of fields below
 * both when it is saved and when it is read: unknown keys are dropped, checkboxes become "1"/"0",
 * selects must be one of the listed options and free text is cleaned and length-limited.
 * PHP 7.4 compatible.
 */
final class MitalteliReportDefaults
{
    /** Module preference that holds the JSON with the administrator's values. */
    public const PREFERENCE = 'default_values';

    public const MAX_TEXT_CHARS = 100;

    /**
     * Configurable fields. The key is the report input name ("vars[<key>]"), except "format"
     * (core select of the page) and "newTab" (checkbox added by this module).
     * "default" is the value the report has when the administrator defines nothing
     * (keep it in sync with resources/report.xml).
     * Not configurable on purpose: individual, family and dates (they depend on each visit).
     *
     * @return array<string,array{type:string,label:string,default:string,options?:array<string,array{0:string,1:?string}>}>
     */
    public static function fields(): array
    {
        return [
            'format'                       => ['type' => 'select', 'label' => 'Format', 'default' => 'HTML', 'options' => [
                'HTML' => ['', null],
                'PDF'  => ['', null],
            ]],
            'newTab'                       => ['type' => 'checkbox', 'label' => 'Open the report in a new tab', 'default' => '0'],
            'withParents'                  => ['type' => 'checkbox', 'label' => 'Show parents', 'default' => '1'],
            'showXref'                     => ['type' => 'checkbox', 'label' => 'Show XREF', 'default' => '1'],
            'showFilters'                  => ['type' => 'checkbox', 'label' => 'Show Filters', 'default' => '1'],
            'hideEmptyDates'               => ['type' => 'checkbox', 'label' => 'Hide records without a date', 'default' => '1'],
            'includeAllNamesIndi'          => ['type' => 'checkbox', 'label' => 'Include all names on individuals', 'default' => '0'],
            'showAllIndividuals'           => ['type' => 'checkbox', 'label' => 'Show all individuals', 'default' => '0'],
            'showAllFamilies'              => ['type' => 'checkbox', 'label' => 'Show all families', 'default' => '1'],
            'filter_rt'                    => ['type' => 'text', 'label' => 'Research Task Filter', 'default' => ''],
            'filter_rt_whole_word'         => ['type' => 'checkbox', 'label' => 'Match whole string', 'default' => '0'],
            'filter_rt_ignore_case'        => ['type' => 'checkbox', 'label' => 'Match ignoring case', 'default' => '1'],
            'filter_rt_remove_diacritics'  => ['type' => 'checkbox', 'label' => 'Match ignoring diacritics', 'default' => '1'],
            'sortby'                       => ['type' => 'select', 'label' => 'Sort order', 'default' => 'NAME', 'options' => [
                'NAME'           => ['sort by lastname', null],
                '_TODO:DATE'     => ['sort date (only takes first research task)', null],
                '_TODO'          => ['sort by text (only takes first research task)', null],
                '_TODO:NOTE'     => ['sort by note (only takes first research task)', null],
                '_TODO:_WT_USER' => ['sort by user (only takes first research task)', null],
            ]],
            'pageSize'                     => ['type' => 'select', 'label' => 'Page size', 'default' => 'A4', 'options' => [
                'A4'         => ['A4', 'paper size'],
                'A3'         => ['A3', 'paper size'],
                'US-Letter'  => ['Letter', 'paper size'],
                'US-Tabloid' => ['Tabloid', 'paper size'],
            ]],
        ];
    }

    /** Translated label of a select option (an empty message id means "show the value as is"). */
    public static function optionLabel(string $field, string $value): string
    {
        $option = self::fields()[$field]['options'][$value] ?? null;

        if ($option === null || $option[0] === '') {
            return $value;
        }

        return $option[1] === null
            ? I18N::translate($option[0])
            : I18N::translateContext($option[1], $option[0]);
    }

    /**
     * Whitelist + type validation of raw values (POST data or decoded JSON).
     * Every configurable field is always present in the result.
     *
     * @param array<mixed> $raw
     *
     * @return array<string,string>
     */
    public static function normalize(array $raw): array
    {
        $clean = [];

        foreach (self::fields() as $key => $field) {
            $value = $raw[$key] ?? null;
            $value = is_scalar($value) ? (string) $value : '';

            switch ($field['type']) {
                case 'checkbox':
                    $clean[$key] = ($value === '1' || $value === 'on') ? '1' : '0';
                    break;

                case 'select':
                    $clean[$key] = array_key_exists($value, $field['options']) ? $value : $field['default'];
                    break;

                default:
                    $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? '';
                    $value = mb_check_encoding($value, 'UTF-8') ? trim($value) : '';
                    $clean[$key] = mb_substr($value, 0, self::MAX_TEXT_CHARS, 'UTF-8');
            }
        }

        return $clean;
    }

    /**
     * Values stored by the administrator, validated again. Empty array = nothing defined.
     *
     * @return array<string,string>
     */
    public static function load(string $stored): array
    {
        if ($stored === '') {
            return [];
        }

        $decoded = json_decode($stored, true);

        return is_array($decoded) ? self::normalize($decoded) : [];
    }

    /** Values to show in the admin form: the stored ones, or the report's own defaults. */
    public static function forForm(string $stored): array
    {
        $values = self::load($stored);

        if ($values === []) {
            foreach (self::fields() as $key => $field) {
                $values[$key] = $field['default'];
            }
        }

        return $values;
    }

    /** @param array<string,string> $values */
    public static function encode(array $values): string
    {
        return (string) json_encode(self::normalize($values), JSON_UNESCAPED_UNICODE);
    }
}
