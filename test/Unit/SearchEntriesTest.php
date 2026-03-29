<?php

declare(strict_types=1);

/**
 * Test Search result retrieval methods
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

use Horde_Ldap_Entry;
use Horde_Ldap_Exception;
use Horde_Ldap_Search;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Horde_Ldap_Search entry retrieval and manipulation methods.
 *
 * Note: Many Search methods require a live LDAP connection and valid search
 * results. These tests focus on error handling and edge cases that can be
 * tested without a live server. See Live/SearchTest.php for integration tests.
 */
#[CoversClass(Horde_Ldap_Search::class)]
class SearchEntriesTest extends TestCase
{
    /**
     * Test entries() with null search returns empty array.
     */
    public function testEntriesWithNullSearch(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        // Set properties to simulate failed search
        $searchProp = $reflection->getProperty('_search');
        $searchProp->setAccessible(true);
        $searchProp->setValue($search, null);

        $linkProp = $reflection->getProperty('_link');
        $linkProp->setAccessible(true);
        $linkProp->setValue($search, null);

        $entryProp = $reflection->getProperty('_entry');
        $entryProp->setAccessible(true);
        $entryProp->setValue($search, null);

        // entries() calls shiftEntry() in loop, should handle gracefully
        $entries = $search->entries();

        $this->assertIsArray($entries);
        $this->assertEmpty($entries);
    }

    /**
     * Test asArray() with empty results returns empty array.
     */
    public function testAsArrayEmpty(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        $searchProp = $reflection->getProperty('_search');
        $searchProp->setAccessible(true);
        $searchProp->setValue($search, null);

        $linkProp = $reflection->getProperty('_link');
        $linkProp->setAccessible(true);
        $linkProp->setValue($search, null);

        $entryProp = $reflection->getProperty('_entry');
        $entryProp->setAccessible(true);
        $entryProp->setValue($search, null);

        $array = $search->asArray();

        $this->assertIsArray($array);
        $this->assertEmpty($array);
    }

    /**
     * Test sortedAsArray() with empty results returns empty array.
     */
    public function testSortedAsArrayEmpty(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        $searchProp = $reflection->getProperty('_search');
        $searchProp->setAccessible(true);
        $searchProp->setValue($search, null);

        $linkProp = $reflection->getProperty('_link');
        $linkProp->setAccessible(true);
        $linkProp->setValue($search, null);

        $entryProp = $reflection->getProperty('_entry');
        $entryProp->setAccessible(true);
        $entryProp->setValue($search, null);

        $sorted = $search->sortedAsArray(['cn'], SORT_ASC);

        $this->assertIsArray($sorted);
        $this->assertEmpty($sorted);
    }

    /**
     * Test sortedAsArray() with invalid sort direction throws exception.
     */
    public function testSortedAsArrayInvalidDirection(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        $searchProp = $reflection->getProperty('_search');
        $searchProp->setAccessible(true);
        $searchProp->setValue($search, null);

        $linkProp = $reflection->getProperty('_link');
        $linkProp->setAccessible(true);
        $linkProp->setValue($search, null);

        $this->expectException(Horde_Ldap_Exception::class);
        $this->expectExceptionMessage('sorting direction not understood');

        // 999 is neither SORT_ASC nor SORT_DESC
        $search->sortedAsArray(['cn'], 999);
    }

    /**
     * Test popEntry() with empty results returns false.
     */
    public function testPopEntryEmpty(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        $searchProp = $reflection->getProperty('_search');
        $searchProp->setAccessible(true);
        $searchProp->setValue($search, null);

        $linkProp = $reflection->getProperty('_link');
        $linkProp->setAccessible(true);
        $linkProp->setValue($search, null);

        $entryProp = $reflection->getProperty('_entry');
        $entryProp->setAccessible(true);
        $entryProp->setValue($search, null);

        // Set _entry_cache to false to trigger entries() call
        $cacheProp = $reflection->getProperty('_entry_cache');
        $cacheProp->setAccessible(true);
        $cacheProp->setValue($search, false);

        $popped = $search->popEntry();

        $this->assertFalse($popped);
    }

    /**
     * Test searchedAttributes() returns attributes array.
     */
    public function testSearchedAttributes(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        // Set searched attributes
        $attrsProp = $reflection->getProperty('_searchedAttrs');
        $attrsProp->setAccessible(true);
        $attrsProp->setValue($search, ['cn', 'mail', 'sn']);

        // searchedAttributes() is protected, need to make it accessible
        $method = $reflection->getMethod('searchedAttributes');
        $method->setAccessible(true);

        $attrs = $method->invoke($search);

        $this->assertIsArray($attrs);
        $this->assertCount(3, $attrs);
        $this->assertContains('cn', $attrs);
        $this->assertContains('mail', $attrs);
        $this->assertContains('sn', $attrs);
    }

    /**
     * Test searchedAttributes() with empty attributes.
     */
    public function testSearchedAttributesEmpty(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        $attrsProp = $reflection->getProperty('_searchedAttrs');
        $attrsProp->setAccessible(true);
        $attrsProp->setValue($search, []);

        $method = $reflection->getMethod('searchedAttributes');
        $method->setAccessible(true);

        $attrs = $method->invoke($search);

        $this->assertIsArray($attrs);
        $this->assertEmpty($attrs);
    }

    /**
     * Test getErrorCode() returns stored error code.
     */
    public function testGetErrorCode(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        $errorProp = $reflection->getProperty('_errorCode');
        $errorProp->setAccessible(true);
        $errorProp->setValue($search, 32); // LDAP_NO_SUCH_OBJECT

        $this->assertEquals(32, $search->getErrorCode());
    }

    /**
     * Test sizeLimitExceeded() checks for error code 4.
     */
    public function testSizeLimitExceeded(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        $errorProp = $reflection->getProperty('_errorCode');
        $errorProp->setAccessible(true);

        // Error code 4 = LDAP_SIZELIMIT_EXCEEDED
        $errorProp->setValue($search, 4);
        $this->assertTrue($search->sizeLimitExceeded());

        // Other error codes
        $errorProp->setValue($search, 0);
        $this->assertFalse($search->sizeLimitExceeded());

        $errorProp->setValue($search, 32);
        $this->assertFalse($search->sizeLimitExceeded());
    }

    /**
     * Test setSearch() sets search property.
     */
    public function testSetSearch(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        $search->setSearch('test_resource');

        $searchProp = $reflection->getProperty('_search');
        $searchProp->setAccessible(true);

        $this->assertEquals('test_resource', $searchProp->getValue($search));
    }

    /**
     * Test setLink() sets link property.
     */
    public function testSetLink(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        $search->setLink('test_link');

        $linkProp = $reflection->getProperty('_link');
        $linkProp->setAccessible(true);

        $this->assertEquals('test_link', $linkProp->getValue($search));
    }

    /**
     * Test count() with null search returns 0.
     */
    public function testCountWithNullSearch(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        $searchProp = $reflection->getProperty('_search');
        $searchProp->setAccessible(true);
        $searchProp->setValue($search, null);

        $count = $search->count();

        $this->assertEquals(0, $count);
    }

    /**
     * Test count() with false search returns 0.
     */
    public function testCountWithFalseSearch(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        $searchProp = $reflection->getProperty('_search');
        $searchProp->setAccessible(true);
        $searchProp->setValue($search, false);

        $count = $search->count();

        $this->assertEquals(0, $count);
    }
}
