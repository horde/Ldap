<?php

declare(strict_types=1);

/**
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

#[CoversClass(Horde_Ldap_Filter::class)]
class FilterTest extends TestCase
{
    /**
     * Test correct parsing of invalid filter strings through parse().
     */
    public function testParseInvalidFilters(): void
    {
        $invalidFilters = [
            'some_damaged_filter_str',
            '(invalid=filter)(because=~no-surrounding brackets)',
            '((invalid=filter)(because=log_op is missing))',
            '(invalid-because-becauseinvalidoperator)',
            '(&(filterpart>=ok)(part2=~ok)(filterpart3_notok---becauseinvalidoperator))',
        ];

        foreach ($invalidFilters as $filter) {
            try {
                Horde_Ldap_Filter::parse($filter);
                $this->fail('Expected Horde_Ldap_Exception for filter: ' . $filter);
            } catch (Horde_Ldap_Exception $e) {
                // Expected exception
                $this->assertInstanceOf(Horde_Ldap_Exception::class, $e);
            }
        }
    }

    /**
     * Test correct parsing of valid filter strings through parse().
     */
    public function testParseValidFilters(): void
    {
        $parsed1 = Horde_Ldap_Filter::parse('(&(cn=foo)(ou=bar))');
        $this->assertInstanceOf(Horde_Ldap_Filter::class, $parsed1);
        $this->assertEquals('(&(cn=foo)(ou=bar))', (string) $parsed1);

        // In an earlier version there was a problem with the splitting of the
        // filter parts if the next part was also an combined filter.
        $parsed2Str = '(&(&(objectClass=posixgroup)(objectClass=foogroup))(uniquemember=uid=eeggs,ou=people,o=foo))';
        $parsed2 = Horde_Ldap_Filter::parse($parsed2Str);
        $this->assertInstanceOf(Horde_Ldap_Filter::class, $parsed2);
        $this->assertEquals($parsed2Str, (string) $parsed2);

        // In an earlier version there was a problem parsing certain
        // not-combined filter strings.
        $parsed3Str = '(!(jpegPhoto=*))';
        $parsed3 = Horde_Ldap_Filter::parse($parsed3Str);
        $this->assertInstanceOf(Horde_Ldap_Filter::class, $parsed3);
        $this->assertEquals($parsed3Str, (string) $parsed3);

        $parsed3ComplexStr = '(&(someAttr=someValue)(!(jpegPhoto=*)))';
        $parsed3Complex = Horde_Ldap_Filter::parse($parsed3ComplexStr);
        $this->assertInstanceOf(Horde_Ldap_Filter::class, $parsed3Complex);
        $this->assertEquals($parsed3ComplexStr, (string) $parsed3Complex);
    }

    /**
     * This tests the basic create() method of creating filters.
     */
    public function testCreate(): void
    {
        // Test values and an array containing the filter creating methods and
        // an regex to test the resulting filter.
        $testattr = 'testattr';
        $testval  = 'testval';
        $combinations = [
            'equals'         => "/\($testattr=$testval\)/",
            'begins'         => "/\($testattr=$testval\*\)/",
            'ends'           => "/\($testattr=\*$testval\)/",
            'contains'       => "/\($testattr=\*$testval\*\)/",
            'greater'        => "/\($testattr>$testval\)/",
            'less'           => "/\($testattr<$testval\)/",
            'greaterorequal' => "/\($testattr>=$testval\)/",
            'lessorequal'    => "/\($testattr<=$testval\)/",
            'approx'         => "/\($testattr~=$testval\)/",
            'any'            => "/\($testattr=\*\)/",
        ];

        foreach ($combinations as $match => $regex) {
            // Escaping is tested in util class.
            $filter = Horde_Ldap_Filter::create($testattr, $match, $testval, false);
            $this->assertInstanceOf(Horde_Ldap_Filter::class, $filter);
            $this->assertMatchesRegularExpression($regex, (string) $filter, "Filter generation failed for MatchType: $match");
        }
    }

    /**
     * Test creating filter with undefined matching rule.
     */
    public function testCreateInvalidMatchingRule(): void
    {
        $this->expectException(Horde_Ldap_Exception::class);
        Horde_Ldap_Filter::create('testattr', 'test_undefined_matchingrule', 'testval');
    }

    /**
     * Tests if __toString() works.
     */
    public function testToString(): void
    {
        $filter = Horde_Ldap_Filter::create('foo', 'equals', 'bar');
        $this->assertInstanceOf(Horde_Ldap_Filter::class, $filter);
        $this->assertEquals('(foo=bar)', (string) $filter);
    }

    /**
     * This tests the basic combination of filters.
     */
    public function testCombine(): void
    {
        // Setup.
        $filter0 = Horde_Ldap_Filter::create('foo', 'equals', 'bar');
        $this->assertInstanceOf(Horde_Ldap_Filter::class, $filter0);

        $filter1 = Horde_Ldap_Filter::create('bar', 'equals', 'foo');
        $this->assertInstanceOf(Horde_Ldap_Filter::class, $filter1);

        $filter2 = Horde_Ldap_Filter::create('you', 'equals', 'me');
        $this->assertInstanceOf(Horde_Ldap_Filter::class, $filter2);

        $filter3 = Horde_Ldap_Filter::parse('(perlinterface=used)');
        $this->assertInstanceOf(Horde_Ldap_Filter::class, $filter3);

        // Negation test.
        $filterNot1 = Horde_Ldap_Filter::combine('not', $filter0);
        $this->assertInstanceOf(Horde_Ldap_Filter::class, $filterNot1, 'Negation failed for literal NOT');
        $this->assertEquals('(!(foo=bar))', (string) $filterNot1);

        $filterNot2 = Horde_Ldap_Filter::combine('!', $filter0);
        $this->assertInstanceOf(Horde_Ldap_Filter::class, $filterNot2, 'Negation failed for logical NOT');
        $this->assertEquals('(!(foo=bar))', (string) $filterNot2);

        $filterNot3 = Horde_Ldap_Filter::combine('!', (string) $filter0);
        $this->assertInstanceOf(Horde_Ldap_Filter::class, $filterNot3, 'Negation failed for logical NOT');
        $this->assertEquals('(!' . $filter0 . ')', (string) $filterNot3);

        // Combination test: OR
        $filterCombOr1 = Horde_Ldap_Filter::combine('or', [$filter1, $filter2]);
        $this->assertInstanceOf(Horde_Ldap_Filter::class, $filterCombOr1, 'Combination failed for literal OR');
        $this->assertEquals('(|(bar=foo)(you=me))', (string) $filterCombOr1);

        $filterCombOr2 = Horde_Ldap_Filter::combine('|', [$filter1, $filter2]);
        $this->assertInstanceOf(Horde_Ldap_Filter::class, $filterCombOr2, 'combination failed for logical OR');
        $this->assertEquals('(|(bar=foo)(you=me))', (string) $filterCombOr2);

        // Combination test: AND
        $filterCombAnd1 = Horde_Ldap_Filter::combine('and', [$filter1, $filter2]);
        $this->assertInstanceOf(Horde_Ldap_Filter::class, $filterCombAnd1, 'Combination failed for literal AND');
        $this->assertEquals('(&(bar=foo)(you=me))', (string) $filterCombAnd1);

        $filterCombAnd2 = Horde_Ldap_Filter::combine('&', [$filter1, $filter2]);
        $this->assertInstanceOf(Horde_Ldap_Filter::class, $filterCombAnd2, 'combination failed for logical AND');
        $this->assertEquals('(&(bar=foo)(you=me))', (string) $filterCombAnd2);

        // Combination test: using filter created with perl interface.
        $filterCombPerl1 = Horde_Ldap_Filter::combine('and', [$filter1, $filter3]);
        $this->assertInstanceOf(Horde_Ldap_Filter::class, $filterCombPerl1, 'Combination failed for literal AND');
        $this->assertEquals('(&(bar=foo)(perlinterface=used))', (string) $filterCombPerl1);

        $filterCombPerl2 = Horde_Ldap_Filter::combine('&', [$filter1, $filter3]);
        $this->assertInstanceOf(Horde_Ldap_Filter::class, $filterCombPerl2, 'combination failed for logical AND');
        $this->assertEquals('(&(bar=foo)(perlinterface=used))', (string) $filterCombPerl2);

        // Combination test: using filter_str instead of object
        $filterCombFstr1 = Horde_Ldap_Filter::combine('and', [$filter1, '(filter_str=foo)']);
        $this->assertInstanceOf(Horde_Ldap_Filter::class, $filterCombFstr1, 'Combination failed for literal AND using filter_str');
        $this->assertEquals('(&(bar=foo)(filter_str=foo))', (string) $filterCombFstr1);

        // Combination test: deep combination
        $filterCompDeep = Horde_Ldap_Filter::combine('and', [$filter2, $filterNot1, $filterCombOr1, $filterCombPerl1]);
        $this->assertInstanceOf(Horde_Ldap_Filter::class, $filterCompDeep, 'Deep combination failed!');
        $this->assertEquals('(&(you=me)(!(foo=bar))(|(bar=foo)(you=me))(&(bar=foo)(perlinterface=used)))', (string) $filterCompDeep);
    }

    /**
     * Test failure cases in filter combination.
     */
    public function testCombineFailures(): void
    {
        $filter0 = Horde_Ldap_Filter::create('foo', 'equals', 'bar');
        $filter1 = Horde_Ldap_Filter::create('bar', 'equals', 'foo');
        $filterNot1 = Horde_Ldap_Filter::combine('not', $filter0);

        $failures = [
            fn() => Horde_Ldap_Filter::combine('not', 'damaged_filter_str'),
            fn() => Horde_Ldap_Filter::combine('not', [$filter0, $filter1]),
            fn() => Horde_Ldap_Filter::combine('not', null),
            fn() => Horde_Ldap_Filter::combine('and', $filterNot1),
            fn() => Horde_Ldap_Filter::combine('and', [$filterNot1]),
            fn() => Horde_Ldap_Filter::combine('or', [$filterNot1]),
            fn() => Horde_Ldap_Filter::combine('some_unknown_method', [$filterNot1]),
            fn() => Horde_Ldap_Filter::combine('and', [$filterNot1, 'some_invalid_filterstring']),
            fn() => Horde_Ldap_Filter::combine('and', [$filterNot1, null]),
        ];

        foreach ($failures as $failure) {
            try {
                $failure();
                $this->fail('Expected Horde_Ldap_Exception');
            } catch (Horde_Ldap_Exception $e) {
                // Expected exception
                $this->assertInstanceOf(Horde_Ldap_Exception::class, $e);
            }
        }
    }
}
