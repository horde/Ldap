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

use Horde_Ldap_Search;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(Horde_Ldap_Search::class)]
class SearchTest extends TestCase
{
    /**
     * Test that destructor handles null search result gracefully.
     *
     * This simulates the error condition from integration tests where
     * ldap_free_result() is called with a null $result argument.
     *
     * This can happen when LDAP search functions return false (e.g., for
     * LDAP_NO_SUCH_OBJECT errors) and a Search object is still created.
     */
    public function testDestructorWithNullSearchResult(): void
    {
        // Create a Search object without calling constructor to avoid
        // the ldap_errno() call that requires a valid connection
        $reflection = new ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        // Set the _search property to null using reflection
        // This simulates the scenario where ldap_search() returns false
        $searchProperty = $reflection->getProperty('_search');
        $searchProperty->setAccessible(true);
        $searchProperty->setValue($search, null);

        // Explicitly unset to trigger destructor
        // This should NOT throw a fatal TypeError
        unset($search);

        // If we get here without a fatal error, the test passes
        $this->assertTrue(true);
    }

    /**
     * Test that destructor handles false search result gracefully.
     *
     * When LDAP functions fail, they return false instead of a result resource.
     */
    public function testDestructorWithFalseSearchResult(): void
    {
        $reflection = new ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        // Set the _search property to false
        $searchProperty = $reflection->getProperty('_search');
        $searchProperty->setAccessible(true);
        $searchProperty->setValue($search, false);

        // Explicitly unset to trigger destructor
        unset($search);

        $this->assertTrue(true);
    }

    /**
     * Test that count() method handles null search result gracefully.
     */
    public function testCountWithNullSearchResult(): void
    {
        $reflection = new ReflectionClass(Horde_Ldap_Search::class);
        $search = $reflection->newInstanceWithoutConstructor();

        // Set required properties
        $searchProperty = $reflection->getProperty('_search');
        $searchProperty->setAccessible(true);
        $searchProperty->setValue($search, null);

        // count() should return 0 for null search result (already handled in code)
        $this->assertEquals(0, $search->count());
    }
}
