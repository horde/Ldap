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

namespace Horde\Ldap\Test\Live;

use Exception;
use Horde_Ldap;
use Horde_Ldap_Entry;
use Horde_Ldap_Search;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Horde_Ldap_Search::class)]
class SearchTest extends TestBase
{
    public static function tearDownAfterClass(): void
    {
        if (!self::$ldapConfig) {
            return;
        }
        try {
            $ldap = new Horde_Ldap(self::$ldapConfig['server']);
            try {
                $ldap->delete('ou=Horde_Ldap_Test_search1,' . self::$ldapConfig['server']['basedn']);
            } catch (Exception $e) {
            }
            try {
                $ldap->delete('ou=Horde_Ldap_Test_search2,' . self::$ldapConfig['server']['basedn']);
            } catch (Exception $e) {
            }
        } catch (Exception $e) {
        }
    }

    /**
     * Tests SPL iterator.
     */
    public function testSPLIterator(): void
    {
        $ldap = new Horde_Ldap(self::$ldapConfig['server']);

        // Some testdata, so we have some entries to search for.
        $base = self::$ldapConfig['server']['basedn'];
        $ou1 = Horde_Ldap_Entry::createFresh(
            'ou=Horde_Ldap_Test_search1,' . $base,
            [
                'objectClass' => ['top', 'organizationalUnit'],
                'ou' => 'Horde_Ldap_Test_search1',
            ]
        );
        $ou2 = Horde_Ldap_Entry::createFresh(
            'ou=Horde_Ldap_Test_search2,' . $base,
            [
                'objectClass' => ['top', 'organizationalUnit'],
                'ou' => 'Horde_Ldap_Test_search2',
            ]
        );

        $ldap->add($ou1);
        $this->assertTrue($ldap->exists($ou1->dn()));
        $ldap->add($ou2);
        $this->assertTrue($ldap->exists($ou2->dn()));

        /* Search and test each method. */
        $search = $ldap->search(null, '(ou=Horde_Ldap*)');
        $this->assertInstanceOf(Horde_Ldap_Search::class, $search);
        $this->assertEquals(2, $search->count());

        // current() is supposed to return first valid element.
        $e1 = $search->current();
        $this->assertInstanceOf(Horde_Ldap_Entry::class, $e1);
        $this->assertEquals($e1->dn(), $search->key());
        $this->assertTrue($search->valid());

        // Shift to next entry.
        $search->next();
        $e2 = $search->current();
        $this->assertInstanceOf(Horde_Ldap_Entry::class, $e2);
        $this->assertEquals($e2->dn(), $search->key());
        $this->assertTrue($search->valid());

        // Shift to non existent third entry.
        $search->next();
        $this->assertFalse($search->current());
        $this->assertFalse($search->key());
        $this->assertFalse($search->valid());

        // Rewind and test, which should return the first entry a second time.
        $search->rewind();
        $e11 = $search->current();
        $this->assertInstanceOf(Horde_Ldap_Entry::class, $e11);
        $this->assertEquals($e11->dn(), $search->key());
        $this->assertTrue($search->valid());
        $this->assertEquals($e1->dn(), $e11->dn());

        // Don't rewind but call current, should return first entry again.
        $e12 = $search->current();
        $this->assertInstanceOf(Horde_Ldap_Entry::class, $e12);
        $this->assertEquals($e12->dn(), $search->key());
        $this->assertTrue($search->valid());
        $this->assertEquals($e1->dn(), $e12->dn());

        // Rewind again and test, which should return the first entry a third
        // time.
        $search->rewind();
        $e13 = $search->current();
        $this->assertInstanceOf(Horde_Ldap_Entry::class, $e13);
        $this->assertEquals($e13->dn(), $search->key());
        $this->assertTrue($search->valid());
        $this->assertEquals($e1->dn(), $e13->dn());

        /* Try methods on empty search result. */
        $search = $ldap->search(null, '(ou=Horde_LdapTest_NotExistentEntry)');
        $this->assertInstanceOf(Horde_Ldap_Search::class, $search);
        $this->assertEquals(0, $search->count());
        $this->assertFalse($search->current());
        $this->assertFalse($search->key());
        $this->assertFalse($search->valid());
        $search->next();
        $this->assertFalse($search->current());
        $this->assertFalse($search->key());
        $this->assertFalse($search->valid());

        /* Search and simple iterate through the test entries.  Then, rewind
         * and do it again several times. */
        $search2 = $ldap->search(null, '(ou=Horde_Ldap*)');
        $this->assertInstanceOf(Horde_Ldap_Search::class, $search2);
        $this->assertEquals(2, $search2->count());
        for ($i = 0; $i <= 5; $i++) {
            $counter = 0;
            foreach ($search2 as $dn => $entry) {
                $counter++;
                // Check on type.
                $this->assertInstanceOf(Horde_Ldap_Entry::class, $entry);
                // Check on key.
                $this->assertThat(strlen($dn), $this->greaterThan(1));
                $this->assertEquals($dn, $entry->dn());
            }
            $this->assertEquals($search2->count(), $counter, "Failed at loop $i");

            // Revert to start.
            $search2->rewind();
        }
    }
}
