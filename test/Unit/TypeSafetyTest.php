<?php

declare(strict_types=1);

/**
 * Test type safety issues with LDAP extension typed objects in PHP 8.1+
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

use Horde_Ldap_Search;
use Horde_Ldap_Entry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for type safety with LDAP extension functions that expect typed
 * parameters (LDAP\Connection, LDAP\Result, LDAP\ResultEntry) in PHP 8.1+.
 *
 * These tests expose scenarios where null/false values could cause TypeError
 * exceptions that are NOT suppressed by @ error suppression operator.
 */
#[CoversClass(Horde_Ldap_Search::class)]
#[CoversClass(Horde_Ldap_Entry::class)]
class TypeSafetyTest extends TestCase
{
    /**
     * Test that Search::count() handles null search result gracefully.
     *
     * Without proper type checking, ldap_count_entries() would throw TypeError.
     */
    public function testSearchCountWithNullResult(): void
    {
        $reflection = new ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        $searchProperty = $reflection->getProperty('_search');
        $searchProperty->setAccessible(true);
        $searchProperty->setValue($search, null);

        // Should return 0, not throw TypeError
        $this->assertEquals(0, $search->count());
    }

    /**
     * Test that Search::count() handles false search result gracefully.
     *
     * This can occur when ldap_search() fails but object is still created.
     */
    public function testSearchCountWithFalseResult(): void
    {
        $reflection = new ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        $searchProperty = $reflection->getProperty('_search');
        $searchProperty->setAccessible(true);
        $searchProperty->setValue($search, false);

        // Should return 0, not throw TypeError
        $this->assertEquals(0, $search->count());
    }

    /**
     * Test that Search::shiftEntry() handles null search result gracefully.
     *
     * Without checking _search type before calling ldap_first_entry(),
     * it would throw TypeError.
     */
    public function testShiftEntryWithNullSearchResult(): void
    {
        $reflection = new ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        // Set _search to null
        $searchProperty = $reflection->getProperty('_search');
        $searchProperty->setAccessible(true);
        $searchProperty->setValue($search, null);

        // Set _link to null as well
        $linkProperty = $reflection->getProperty('_link');
        $linkProperty->setAccessible(true);
        $linkProperty->setValue($search, null);

        // Set _entry to null (initial state)
        $entryProperty = $reflection->getProperty('_entry');
        $entryProperty->setAccessible(true);
        $entryProperty->setValue($search, null);

        // Should return false without throwing TypeError
        $this->assertFalse($search->shiftEntry());
    }

    /**
     * Test that Search::shiftEntry() handles false search result gracefully.
     */
    public function testShiftEntryWithFalseSearchResult(): void
    {
        $reflection = new ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        $searchProperty = $reflection->getProperty('_search');
        $searchProperty->setAccessible(true);
        $searchProperty->setValue($search, false);

        $linkProperty = $reflection->getProperty('_link');
        $linkProperty->setAccessible(true);
        $linkProperty->setValue($search, null);

        $entryProperty = $reflection->getProperty('_entry');
        $entryProperty->setAccessible(true);
        $entryProperty->setValue($search, null);

        // Should return false without throwing TypeError
        $this->assertFalse($search->shiftEntry());
    }

    /**
     * Test that Search::entries() handles invalid search result.
     *
     * This calls shiftEntry() in a loop, so should handle null/false gracefully.
     */
    public function testEntriesWithNullSearchResult(): void
    {
        $reflection = new ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        $searchProperty = $reflection->getProperty('_search');
        $searchProperty->setAccessible(true);
        $searchProperty->setValue($search, null);

        $linkProperty = $reflection->getProperty('_link');
        $linkProperty->setAccessible(true);
        $linkProperty->setValue($search, null);

        $entryProperty = $reflection->getProperty('_entry');
        $entryProperty->setAccessible(true);
        $entryProperty->setValue($search, null);

        // Should return empty array without throwing TypeError
        $this->assertEquals([], $search->entries());
    }

    /**
     * Test that Search::popEntry() handles invalid search result.
     */
    public function testPopEntryWithNullSearchResult(): void
    {
        $reflection = new ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        $searchProperty = $reflection->getProperty('_search');
        $searchProperty->setAccessible(true);
        $searchProperty->setValue($search, null);

        $linkProperty = $reflection->getProperty('_link');
        $linkProperty->setAccessible(true);
        $linkProperty->setValue($search, null);

        $entryProperty = $reflection->getProperty('_entry');
        $entryProperty->setAccessible(true);
        $entryProperty->setValue($search, null);

        // Should return false without throwing TypeError
        $this->assertFalse($search->popEntry());
    }

    /**
     * Test that Search SPL Iterator methods handle invalid search result.
     */
    public function testIteratorWithNullSearchResult(): void
    {
        $reflection = new ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        $searchProperty = $reflection->getProperty('_search');
        $searchProperty->setAccessible(true);
        $searchProperty->setValue($search, null);

        $linkProperty = $reflection->getProperty('_link');
        $linkProperty->setAccessible(true);
        $linkProperty->setValue($search, null);

        $entryProperty = $reflection->getProperty('_entry');
        $entryProperty->setAccessible(true);
        $entryProperty->setValue($search, null);

        // Initialize iterator cache
        $cacheProperty = $reflection->getProperty('_iteratorCache');
        $cacheProperty->setAccessible(true);
        $cacheProperty->setValue($search, []);

        // Should handle gracefully without throwing TypeError
        $this->assertFalse($search->current());
        $this->assertFalse($search->key());
        $this->assertFalse($search->valid());

        // Calling next() should not throw
        $search->next();
        $this->assertFalse($search->current());

        // Rewind should work
        $search->rewind();
        $this->assertFalse($search->current());
    }

    /**
     * Test that Entry::attributes() handles null link/entry gracefully.
     *
     * Without proper type checking before calling ldap_first_attribute(),
     * it would throw TypeError.
     */
    public function testEntryAttributesWithNullLinkOrEntry(): void
    {
        $reflection = new ReflectionClass(Horde_Ldap_Entry::class);
        $entry = $reflection->newInstanceWithoutConstructor();

        // Set _link and _entry to null
        $linkProperty = $reflection->getProperty('_link');
        $linkProperty->setAccessible(true);
        $linkProperty->setValue($entry, null);

        $entryProperty = $reflection->getProperty('_entry');
        $entryProperty->setAccessible(true);
        $entryProperty->setValue($entry, null);

        // Set _new to false (connected entry)
        $newProperty = $reflection->getProperty('_new');
        $newProperty->setAccessible(true);
        $newProperty->setValue($entry, false);

        // Should return empty array without throwing TypeError
        $this->assertEquals([], $entry->attributes());
    }

    /**
     * Test that Entry::attributes() handles false link/entry gracefully.
     */
    public function testEntryAttributesWithFalseLinkOrEntry(): void
    {
        $reflection = new ReflectionClass(Horde_Ldap_Entry::class);
        $entry = $reflection->newInstanceWithoutConstructor();

        $linkProperty = $reflection->getProperty('_link');
        $linkProperty->setAccessible(true);
        $linkProperty->setValue($entry, false);

        $entryProperty = $reflection->getProperty('_entry');
        $entryProperty->setAccessible(true);
        $entryProperty->setValue($entry, false);

        $newProperty = $reflection->getProperty('_new');
        $newProperty->setAccessible(true);
        $newProperty->setValue($entry, false);

        // Should return empty array without throwing TypeError
        $this->assertEquals([], $entry->attributes());
    }

    /**
     * Test that Entry::dn() handles false DN value.
     *
     * If ldap_get_dn() returns false but is assigned to _dn,
     * later code might fail on type assumptions.
     */
    public function testEntryDnWithFalseValue(): void
    {
        $reflection = new ReflectionClass(Horde_Ldap_Entry::class);
        $entry = $reflection->newInstanceWithoutConstructor();

        // Set _dn to false (simulating failed ldap_get_dn())
        $dnProperty = $reflection->getProperty('_dn');
        $dnProperty->setAccessible(true);
        $dnProperty->setValue($entry, false);

        // dn() should handle this - currently returns false
        // which might cause issues in string operations
        $result = $entry->dn();

        // Document current behavior: dn() returns false
        // This could be problematic in string comparisons
        $this->assertFalse($result);
    }

    /**
     * Test that Entry::currentDN() handles false _dn value.
     */
    public function testEntryCurrentDnWithFalseValue(): void
    {
        $reflection = new ReflectionClass(Horde_Ldap_Entry::class);
        $entry = $reflection->newInstanceWithoutConstructor();

        $dnProperty = $reflection->getProperty('_dn');
        $dnProperty->setAccessible(true);
        $dnProperty->setValue($entry, false);

        // currentDN() should handle this
        $result = $entry->currentDN();

        // Document current behavior
        $this->assertFalse($result);
    }

    /**
     * Test Search with integer instead of proper type.
     *
     * Edge case: someone passes 0 or other falsy integer.
     */
    public function testSearchWithIntegerSearchResult(): void
    {
        $reflection = new ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        $searchProperty = $reflection->getProperty('_search');
        $searchProperty->setAccessible(true);
        $searchProperty->setValue($search, 0);

        // count() uses !$this->_search which catches 0
        $this->assertEquals(0, $search->count());
    }

    /**
     * Test Search with string instead of proper type.
     *
     * Edge case: someone passes empty string or other value.
     */
    public function testSearchWithEmptyStringSearchResult(): void
    {
        $reflection = new ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        $searchProperty = $reflection->getProperty('_search');
        $searchProperty->setAccessible(true);
        $searchProperty->setValue($search, '');

        // count() uses !$this->_search which catches empty string
        $this->assertEquals(0, $search->count());
    }
}
