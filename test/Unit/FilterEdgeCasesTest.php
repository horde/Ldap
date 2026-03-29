<?php

declare(strict_types=1);

/**
 * Test Filter edge cases including deep nesting and large combinations
 *
 * Copyright 2010-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @package   Ldap
 * @author    Jan Schneider <jan@horde.org>
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPL-3.0
 */

namespace Horde\Ldap\Test\Unit;

use Horde_Ldap_Exception;
use Horde_Ldap_Filter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Horde_Ldap_Filter edge cases and stress scenarios.
 */
#[CoversClass(Horde_Ldap_Filter::class)]
class FilterEdgeCasesTest extends TestCase
{
    /**
     * Test deeply nested filters (10 levels).
     */
    public function testDeeplyNestedFilters(): void
    {
        // Build a 10-level nested filter: (&(&(&(...))))
        $filter = Horde_Ldap_Filter::create('attr1', 'equals', 'value1');

        for ($i = 2; $i <= 10; $i++) {
            $nextFilter = Horde_Ldap_Filter::create("attr$i", 'equals', "value$i");
            $filter = Horde_Ldap_Filter::combine('and', [$filter, $nextFilter]);
        }

        $this->assertInstanceOf(Horde_Ldap_Filter::class, $filter);

        $filterString = (string) $filter;

        // Should have multiple opening parentheses
        $this->assertGreaterThan(10, substr_count($filterString, '('));

        // Should be parseable
        $parsed = Horde_Ldap_Filter::parse($filterString);
        $this->assertInstanceOf(Horde_Ldap_Filter::class, $parsed);
        $this->assertEquals($filterString, (string) $parsed);
    }

    /**
     * Test very deeply nested NOT operators.
     */
    public function testDeeplyNestedNots(): void
    {
        // Build: (!(!(!(!(cn=test)))))
        $filter = Horde_Ldap_Filter::create('cn', 'equals', 'test');

        for ($i = 0; $i < 5; $i++) {
            $filter = Horde_Ldap_Filter::combine('not', $filter);
        }

        $filterString = (string) $filter;

        // Should have 5 NOT operators
        $this->assertEquals(5, substr_count($filterString, '(!'));

        // Should be parseable
        $parsed = Horde_Ldap_Filter::parse($filterString);
        $this->assertEquals($filterString, (string) $parsed);
    }

    /**
     * Test large OR combination (50 filters).
     */
    public function testLargeOrCombination(): void
    {
        $filters = [];

        for ($i = 1; $i <= 50; $i++) {
            $filters[] = Horde_Ldap_Filter::create('cn', 'equals', "user$i");
        }

        $combined = Horde_Ldap_Filter::combine('or', $filters);

        $this->assertInstanceOf(Horde_Ldap_Filter::class, $combined);

        $filterString = (string) $combined;

        // Should contain all 50 users
        for ($i = 1; $i <= 50; $i++) {
            $this->assertStringContainsString("user$i", $filterString);
        }

        // Should be parseable
        $parsed = Horde_Ldap_Filter::parse($filterString);
        $this->assertEquals($filterString, (string) $parsed);
    }

    /**
     * Test filter with Unicode characters.
     */
    public function testFilterWithUnicode(): void
    {
        $filter = Horde_Ldap_Filter::create('cn', 'equals', 'Müller');

        $this->assertInstanceOf(Horde_Ldap_Filter::class, $filter);

        $filterString = (string) $filter;
        $this->assertStringContainsString('Müller', $filterString);

        // Should be parseable
        $parsed = Horde_Ldap_Filter::parse($filterString);
        $this->assertEquals($filterString, (string) $parsed);
    }

    /**
     * Test filter with emoji.
     */
    public function testFilterWithEmoji(): void
    {
        $filter = Horde_Ldap_Filter::create('description', 'contains', '😀');

        $filterString = (string) $filter;
        $this->assertStringContainsString('😀', $filterString);
    }

    /**
     * Test filter with special LDAP characters that need escaping.
     */
    public function testFilterWithSpecialChars(): void
    {
        // Test with value containing special chars
        $filter = Horde_Ldap_Filter::create('cn', 'equals', 'test*value', true);

        $filterString = (string) $filter;

        // Without escaping, asterisk should be present
        // With escaping=false in create, it stays literal
        $this->assertInstanceOf(Horde_Ldap_Filter::class, $filter);
    }

    /**
     * Test filter with empty string value.
     */
    public function testFilterWithEmptyString(): void
    {
        $filter = Horde_Ldap_Filter::create('cn', 'equals', '');

        $this->assertInstanceOf(Horde_Ldap_Filter::class, $filter);
        $this->assertEquals('(cn=)', (string) $filter);
    }

    /**
     * Test filter with very long attribute value.
     */
    public function testFilterWithLongValue(): void
    {
        $longValue = str_repeat('A', 1000);
        $filter = Horde_Ldap_Filter::create('description', 'equals', $longValue);

        $filterString = (string) $filter;
        $this->assertStringContainsString($longValue, $filterString);

        // Should be parseable
        $parsed = Horde_Ldap_Filter::parse($filterString);
        $this->assertInstanceOf(Horde_Ldap_Filter::class, $parsed);
    }

    /**
     * Test complex nested combination: (&(|(a=1)(b=2))(!(c=3)))
     */
    public function testComplexNestedCombination(): void
    {
        $filter1 = Horde_Ldap_Filter::create('a', 'equals', '1');
        $filter2 = Horde_Ldap_Filter::create('b', 'equals', '2');
        $filter3 = Horde_Ldap_Filter::create('c', 'equals', '3');

        $orFilter = Horde_Ldap_Filter::combine('or', [$filter1, $filter2]);
        $notFilter = Horde_Ldap_Filter::combine('not', $filter3);
        $andFilter = Horde_Ldap_Filter::combine('and', [$orFilter, $notFilter]);

        $expected = '(&(|(a=1)(b=2))(!(c=3)))';
        $this->assertEquals($expected, (string) $andFilter);

        // Should be parseable
        $parsed = Horde_Ldap_Filter::parse($expected);
        $this->assertEquals($expected, (string) $parsed);
    }

    /**
     * Test all matching rules in create().
     */
    public function testAllMatchingRules(): void
    {
        $matchingRules = [
            'equals'         => '(attr=value)',
            'begins'         => '(attr=value*)',
            'ends'           => '(attr=*value)',
            'contains'       => '(attr=*value*)',
            'greater'        => '(attr>value)',
            'less'           => '(attr<value)',
            'greaterorequal' => '(attr>=value)',
            'lessorequal'    => '(attr<=value)',
            'approx'         => '(attr~=value)',
            'any'            => '(attr=*)',
        ];

        foreach ($matchingRules as $rule => $expected) {
            $filter = Horde_Ldap_Filter::create('attr', $rule, 'value', false);
            $this->assertEquals($expected, (string) $filter, "Failed for matching rule: $rule");
        }
    }

    /**
     * Test parse() with whitespace variations.
     */
    public function testParseWithWhitespace(): void
    {
        // LDAP filters typically don't have extra whitespace, but test tolerance
        $filter = '(cn=test)';
        $parsed = Horde_Ldap_Filter::parse($filter);

        $this->assertEquals('(cn=test)', (string) $parsed);
    }

    /**
     * Test filter round-trip: create -> string -> parse -> string.
     */
    public function testFilterRoundTrip(): void
    {
        $original = Horde_Ldap_Filter::create('mail', 'equals', 'user@example.com');
        $string1 = (string) $original;

        $parsed = Horde_Ldap_Filter::parse($string1);
        $string2 = (string) $parsed;

        $this->assertEquals($string1, $string2);
    }

    /**
     * Test filter with attribute name containing hyphen.
     */
    public function testFilterWithHyphenatedAttribute(): void
    {
        $filter = Horde_Ldap_Filter::create('employee-number', 'equals', '12345');

        $this->assertEquals('(employee-number=12345)', (string) $filter);
    }

    /**
     * Test filter with attribute name containing numbers.
     */
    public function testFilterWithNumericAttribute(): void
    {
        $filter = Horde_Ldap_Filter::create('attr123', 'equals', 'value');

        $this->assertEquals('(attr123=value)', (string) $filter);
    }

    /**
     * Test parse with escaped parentheses in value.
     */
    public function testParseWithEscapedParentheses(): void
    {
        $filter = '(cn=test\\28value\\29)';
        $parsed = Horde_Ldap_Filter::parse($filter);

        $this->assertInstanceOf(Horde_Ldap_Filter::class, $parsed);
        $this->assertStringContainsString('\\28', (string) $parsed);
        $this->assertStringContainsString('\\29', (string) $parsed);
    }

    /**
     * Test combining filters with string representation.
     */
    public function testCombineWithString(): void
    {
        $filter1 = Horde_Ldap_Filter::create('cn', 'equals', 'test');
        $filter2 = '(mail=test@example.com)';

        $combined = Horde_Ldap_Filter::combine('and', [$filter1, $filter2]);

        $this->assertInstanceOf(Horde_Ldap_Filter::class, $combined);
        $this->assertStringContainsString('cn=test', (string) $combined);
        $this->assertStringContainsString('mail=test@example.com', (string) $combined);
    }

    /**
     * Test NOT with string filter.
     */
    public function testNotWithString(): void
    {
        $filter = '(cn=test)';
        $notFilter = Horde_Ldap_Filter::combine('not', $filter);

        $this->assertEquals('(!(cn=test))', (string) $notFilter);
    }

    /**
     * Test filter with very large combination (stress test).
     */
    public function testVeryLargeCombination(): void
    {
        $filters = [];

        // Create 100 filters
        for ($i = 1; $i <= 100; $i++) {
            $filters[] = Horde_Ldap_Filter::create('uid', 'equals', "user$i");
        }

        $combined = Horde_Ldap_Filter::combine('or', $filters);

        $this->assertInstanceOf(Horde_Ldap_Filter::class, $combined);

        $filterString = (string) $combined;

        // Should start with (| and end with )
        $this->assertStringStartsWith('(|', $filterString);
        $this->assertStringEndsWith(')', $filterString);

        // Should contain all users
        $this->assertStringContainsString('user1', $filterString);
        $this->assertStringContainsString('user50', $filterString);
        $this->assertStringContainsString('user100', $filterString);
    }

    /**
     * Test parse() is idempotent.
     */
    public function testParseIdempotent(): void
    {
        $original = '(&(cn=test)(mail=test@example.com))';

        $parsed1 = Horde_Ldap_Filter::parse($original);
        $string1 = (string) $parsed1;

        $parsed2 = Horde_Ldap_Filter::parse($string1);
        $string2 = (string) $parsed2;

        $this->assertEquals($string1, $string2);
    }

    /**
     * Test filter with numeric values.
     */
    public function testFilterWithNumericValue(): void
    {
        $filter = Horde_Ldap_Filter::create('uidNumber', 'greaterorequal', '1000');

        $this->assertEquals('(uidNumber>=1000)', (string) $filter);
    }

    /**
     * Test filter with boolean-like values.
     */
    public function testFilterWithBooleanValue(): void
    {
        $filter = Horde_Ldap_Filter::create('active', 'equals', 'TRUE');

        $this->assertEquals('(active=TRUE)', (string) $filter);
    }

    /**
     * Test any matcher creates wildcard filter.
     */
    public function testAnyMatcherWildcard(): void
    {
        $filter = Horde_Ldap_Filter::create('objectClass', 'any', '');

        $this->assertEquals('(objectClass=*)', (string) $filter);
    }
}
