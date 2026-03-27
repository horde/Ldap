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
use Horde_Exception_NotFound;
use Horde_Ldap;
use Horde_Ldap_Entry;
use Horde_Ldap_Exception;
use Horde_Ldap_Filter;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Horde_Ldap::class)]
class LdapTest extends TestBase
{
    public static function tearDownAfterClass(): void
    {
        self::$ldapConfig = self::getConfig();
        if (!self::$ldapConfig) {
            return;
        }

        $clean = [
            'cn=Horde_Ldap_TestEntry,',
            'ou=Horde_Ldap_Test_subdelete,',
            'ou=Horde_Ldap_Test_modify,',
            'ou=Horde_Ldap_Test_search1,',
            'ou=Horde_Ldap_Test_search2,',
            'ou=Horde_Ldap_Test_exists,',
            'ou=Horde_Ldap_Test_exists_2+l=somewhere,',
            'ou=Horde_Ldap_Test_getEntry,',
            'ou=Horde_Ldap_Test_move,',
            'ou=Horde_Ldap_Test_pool,',
            'ou=Horde_Ldap_Test_tgt,',
        ];
        try {
            $ldap = new Horde_Ldap(self::$ldapConfig['server']);
            foreach ($clean as $dn) {
                try {
                    $ldap->delete($dn . self::$ldapConfig['server']['basedn'], true);
                } catch (Exception $e) {
                }
            }
        } catch (Exception $e) {
        }
    }

    /**
     * Tests if the server can connect and bind correctly.
     */
    public function testConnectAndPrivilegedBind(): void
    {
        // This connect is supposed to fail.
        $lcfg = [
            'hostspec' => 'nonexistant.ldap.horde.org',
            'timeout' => 1,
        ];
        try {
            $ldap = new Horde_Ldap($lcfg);
            $this->fail('Horde_Ldap_Exception expected.');
        } catch (Horde_Ldap_Exception $e) {
        }

        // Failing with multiple hosts.
        $lcfg = [
            'hostspec' => [
                'nonexistant1.ldap.horde.org',
                'nonexistant2.ldap.horde.org',
            ],
            'timeout' => 1,
        ];
        try {
            $ldap = new Horde_Ldap($lcfg);
            $this->fail('Horde_Ldap_Exception expected.');
        } catch (Horde_Ldap_Exception $e) {
        }

        // Simple working connect and privileged bind.
        $ldap = new Horde_Ldap(self::$ldapConfig['server']);

        // Working connect and privileged bind with first host down.
        $lcfg = [
            'hostspec' => [
                'nonexistant.ldap.horde.org',
                self::$ldapConfig['server']['hostspec'],
            ],
            'port'    => self::$ldapConfig['server']['port'],
            'binddn'  => self::$ldapConfig['server']['binddn'],
            'bindpw'  => self::$ldapConfig['server']['bindpw'],
            'timeout' => 1,
        ];
        $ldap = new Horde_Ldap($lcfg);
    }

    /**
     * Tests if the server can connect and bind anonymously, if supported.
     */
    public function testConnectAndAnonymousBind(): void
    {
        if (!self::$ldapConfig['capability']['anonymous']) {
            $this->markTestSkipped('Server does not support anonymous bind');
        }

        // Simple working connect and anonymous bind.
        $lcfg = [
            'hostspec' => self::$ldapConfig['server']['hostspec'],
            'port'     => self::$ldapConfig['server']['port'],
        ];
        $ldap = new Horde_Ldap($lcfg);
    }

    /**
     * Tests if the server can connect and bind, but not rebind with empty password.
     */
    public function testConnectAndEmptyRebind(): void
    {
        $this->expectException(Horde_Ldap_Exception::class);

        // Simple working connect and privileged bind.
        $ldap = new Horde_Ldap(self::$ldapConfig['server']);
        $ldap->bind(self::$ldapConfig['server']['binddn'], '');
    }

    /**
     * Tests startTLS() if server supports it.
     */
    public function testStartTLS(): void
    {
        if (!self::$ldapConfig['capability']['tls']) {
            $this->markTestSkipped('Server does not support TLS');
        }

        // Simple working connect and privileged bind.
        $lcfg = ['starttls' => true] + self::$ldapConfig['server'];
        $ldap = new Horde_Ldap($lcfg);
    }

    /**
     * Test if adding and deleting a fresh entry works.
     */
    public function testAdd(): void
    {
        $ldap = new Horde_Ldap(self::$ldapConfig['server']);

        // Adding a fresh entry.
        $cn = 'Horde_Ldap_TestEntry';
        $dn = 'cn=' . $cn . ',' . self::$ldapConfig['server']['basedn'];
        $freshEntry = Horde_Ldap_Entry::createFresh(
            $dn,
            [
                'objectClass' => ['top', 'person'],
                'cn'          => $cn,
                'sn'          => 'TestEntry',
            ]
        );
        $this->assertInstanceOf(Horde_Ldap_Entry::class, $freshEntry);
        $ldap->add($freshEntry);

        // Deleting this entry.
        $ldap->delete($freshEntry);
    }

    /**
     * Basic deletion is tested in testAdd(), so here we just test if
     * advanced deletion tasks work properly.
     */
    public function testDelete(): void
    {
        $ldap = new Horde_Ldap(self::$ldapConfig['server']);

        // Some parameter checks.
        try {
            $ldap->delete(1234);
            $this->fail('Horde_Ldap_Exception expected.');
        } catch (Horde_Ldap_Exception $e) {
        }
        try {
            $ldap->delete($ldap);
            $this->fail('Horde_Ldap_Exception expected.');
        } catch (Horde_Ldap_Exception $e) {
        }

        // In order to test subtree deletion, we need some little tree
        // which we need to establish first.
        $base   = self::$ldapConfig['server']['basedn'];
        $testdn = 'ou=Horde_Ldap_Test_subdelete,' . $base;

        $ou = Horde_Ldap_Entry::createFresh(
            $testdn,
            [
                'objectClass' => ['top', 'organizationalUnit'],
                'ou' => 'Horde_Ldap_Test_subdelete',
            ]
        );
        $ou1 = Horde_Ldap_Entry::createFresh(
            'ou=test1,' . $testdn,
            [
                'objectClass' => ['top', 'organizationalUnit'],
                'ou' => 'test1',
            ]
        );
        $ou1L1 = Horde_Ldap_Entry::createFresh(
            'l=subtest,ou=test1,' . $testdn,
            [
                'objectClass' => ['top', 'locality'],
                'l' => 'test1',
            ]
        );
        $ou2 = Horde_Ldap_Entry::createFresh(
            'ou=test2,' . $testdn,
            [
                'objectClass' => ['top', 'organizationalUnit'],
                'ou' => 'test2',
            ]
        );
        $ou3 = Horde_Ldap_Entry::createFresh(
            'ou=test3,' . $testdn,
            [
                'objectClass' => ['top', 'organizationalUnit'],
                'ou' => 'test3',
            ]
        );
        $ldap->add($ou);
        $ldap->add($ou1);
        $ldap->add($ou1L1);
        $ldap->add($ou2);
        $ldap->add($ou3);
        $this->assertTrue($ldap->exists($ou->dn()));
        $this->assertTrue($ldap->exists($ou1->dn()));
        $this->assertTrue($ldap->exists($ou1L1->dn()));
        $this->assertTrue($ldap->exists($ou2->dn()));
        $this->assertTrue($ldap->exists($ou3->dn()));
        // Tree established now. We can run some tests now :D

        // Try to delete some non existent entry inside that subtree (fails).
        try {
            $ldap->delete('cn=not_existent,ou=test1,' . $testdn);
            $this->fail('Horde_Ldap_Exception expected.');
        } catch (Horde_Ldap_Exception $e) {
            $this->assertEquals('LDAP_NO_SUCH_OBJECT', Horde_Ldap::errorName($e->getCode()));
        }

        // Try to delete main test ou without recursive set (fails too).
        try {
            $ldap->delete($testdn);
            $this->fail('Horde_Ldap_Exception expected.');
        } catch (Horde_Ldap_Exception $e) {
            $this->assertEquals('LDAP_NOT_ALLOWED_ON_NONLEAF', Horde_Ldap::errorName($e->getCode()));
        }

        // Retry with subtree delete, this should work.
        $ldap->delete($testdn, true);

        // The DN is not allowed to exist anymore.
        $this->assertFalse($ldap->exists($testdn));
    }

    /**
     * Test modify().
     */
    public function testModify(): void
    {
        $ldap = new Horde_Ldap(self::$ldapConfig['server']);

        // We need a test entry.
        $localEntry = Horde_Ldap_Entry::createFresh(
            'ou=Horde_Ldap_Test_modify,' . self::$ldapConfig['server']['basedn'],
            [
                'objectClass'     => ['top', 'organizationalUnit'],
                'ou'              => 'Horde_Ldap_Test_modify',
                'street'          => 'Beniroad',
                'telephoneNumber' => ['1234', '5678'],
                'postalcode'      => '12345',
                'postalAddress'   => 'someAddress',
                'st'              => ['State 1', 'State 2'],
            ]
        );
        $ldap->add($localEntry);
        $this->assertTrue($ldap->exists($localEntry->dn()));

        // Test invalid actions.
        try {
            $ldap->modify($localEntry, ['foo' => 'bar']);
            $this->fail('Expected exception when passing invalid actions to modify().');
        } catch (Horde_Ldap_Exception $e) {
        }

        // Prepare some changes.
        $changes = [
            'add' => [
                'businessCategory' => ['foocat', 'barcat'],
                'description' => 'testval',
            ],
            'delete' => ['postalAddress'],
            'replace' => ['telephoneNumber' => ['345', '567']],
            'changes' => [
                'replace' => ['street' => 'Highway to Hell'],
                'add' => ['l' => 'someLocality'],
                'delete' => [
                    'postalcode',
                    'st' => ['State 1'],
                ],
            ],
        ];

        // Perform those changes.
        $ldap->modify($localEntry, $changes);

        // Verify correct attribute changes.
        $actualEntry = $ldap->getEntry(
            $localEntry->dn(),
            [
                'objectClass', 'ou',
                'postalAddress', 'street',
                'telephoneNumber', 'postalcode',
                'st', 'l', 'businessCategory',
                'description',
            ]
        );
        $this->assertInstanceOf(Horde_Ldap_Entry::class, $actualEntry);
        $expectedAttributes = [
            'objectClass'      => ['top', 'organizationalUnit'],
            'ou'               => 'Horde_Ldap_Test_modify',
            'street'           => 'Highway to Hell',
            'l'                => 'someLocality',
            'telephoneNumber'  => ['345', '567'],
            'businessCategory' => ['foocat', 'barcat'],
            'description'      => 'testval',
            'st'               => 'State 2',
        ];

        $localAttributes  = $localEntry->getValues();
        $actualAttributes = $actualEntry->getValues();

        // To enable easy check, we need to sort the values of the remaining
        // multival attributes as well as the attribute names.
        ksort($expectedAttributes);
        ksort($localAttributes);
        ksort($actualAttributes);
        sort($expectedAttributes['businessCategory']);
        sort($localAttributes['businessCategory']);
        sort($actualAttributes['businessCategory']);

        // The attributes must match the expected values.  Both, the entry
        // inside the directory and our local copy must reflect the same
        // values.
        $this->assertEquals($expectedAttributes, $actualAttributes, 'The directory entries attributes are not OK!');
        $this->assertEquals($expectedAttributes, $localAttributes, 'The local entries attributes are not OK!');
    }

    /**
     * Test search().
     */
    public function testSearch(): void
    {
        $ldap = new Horde_Ldap(self::$ldapConfig['server']);

        // Some testdata, so we can test sizelimit.
        $base = self::$ldapConfig['server']['basedn'];
        $ou1 = Horde_Ldap_Entry::createFresh(
            'ou=Horde_Ldap_Test_search1,' . $base,
            [
                'objectClass' => ['top', 'organizationalUnit'],
                'ou' => 'Horde_Ldap_Test_search1',
            ]
        );
        $ou11 = Horde_Ldap_Entry::createFresh(
            'ou=Horde_Ldap_Test_search1_1,' . $ou1->dn(),
            [
                'objectClass' => ['top', 'organizationalUnit'],
                'ou' => 'Horde_Ldap_Test_search1_1',
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
        $ldap->add($ou11);
        $this->assertTrue($ldap->exists($ou11->dn()));
        $ldap->add($ou2);
        $this->assertTrue($ldap->exists($ou2->dn()));


        // Search for test filter, should at least return our two test entries.
        $res = $ldap->search(
            null,
            '(ou=Horde_Ldap*)',
            ['attributes' => '1.1']
        );
        $this->assertInstanceOf(Horde_Ldap_Search::class, $res);
        $this->assertThat($res->count(), $this->greaterThanOrEqual(2));

        // Same, but with Horde_Ldap_Filter object.
        $filtero = Horde_Ldap_Filter::create('ou', 'begins', 'Horde_Ldap');
        $this->assertInstanceOf(Horde_Ldap_Filter::class, $filtero);
        $res = $ldap->search(
            null,
            $filtero,
            ['attributes' => '1.1']
        );
        $this->assertInstanceOf(Horde_Ldap_Search::class, $res);
        $this->assertThat($res->count(), $this->greaterThanOrEqual(2));

        // Search using default filter for base-onelevel scope, should at least
        // return our two test entries.
        $res = $ldap->search(
            null,
            null,
            ['scope' => 'one', 'attributes' => '1.1']
        );
        $this->assertInstanceOf(Horde_Ldap_Search::class, $res);
        $this->assertThat($res->count(), $this->greaterThanOrEqual(2));

        // Base-search using custom base (string), should only return the test
        // entry $ou1 and not the entry below it.
        $res = $ldap->search(
            $ou1->dn(),
            null,
            ['scope' => 'base', 'attributes' => '1.1']
        );
        $this->assertInstanceOf(Horde_Ldap_Search::class, $res);
        $this->assertEquals(1, $res->count());

        // Search using custom base, this time using an entry object.  This
        // tests if passing an entry object as base works, should only return
        // the test entry $ou1.
        $res = $ldap->search(
            $ou1,
            '(ou=*)',
            ['scope' => 'base', 'attributes' => '1.1']
        );
        $this->assertInstanceOf(Horde_Ldap_Search::class, $res);
        $this->assertEquals(1, $res->count());

        // Search using default filter for base-onelevel scope with sizelimit,
        // should of course return more than one entry, but not more than
        // sizelimit
        $res = $ldap->search(
            null,
            null,
            ['scope' => 'one', 'sizelimit' => 1, 'attributes' => '1.1']
        );
        $this->assertInstanceOf(Horde_Ldap_Search::class, $res);
        $this->assertEquals(1, $res->count());
        // Sizelimit should be exceeded now.
        $this->assertTrue($res->sizeLimitExceeded());

        // Bad filter.
        try {
            $res = $ldap->search(
                null,
                'somebadfilter',
                ['attributes' => '1.1']
            );
            $this->fail('Horde_Ldap_Exception expected.');
        } catch (Horde_Ldap_Exception $e) {
        }

        // Bad base.
        try {
            $res = $ldap->search(
                'badbase',
                null,
                ['attributes' => '1.1']
            );
            $this->fail('Horde_Ldap_Exception expected.');
        } catch (Horde_Ldap_Exception $e) {
        }

        // Nullresult.
        $res = $ldap->search(
            null,
            '(cn=nevermatching_filter)',
            ['scope' => 'base', 'attributes' => '1.1']
        );
        $this->assertInstanceOf(Horde_Ldap_Search::class, $res);
        $this->assertEquals(0, $res->count());
    }

    /**
     * Test exists().
     */
    public function testExists(): void
    {
        $ldap = new Horde_Ldap(self::$ldapConfig['server']);

        $dn = 'ou=Horde_Ldap_Test_exists,' . self::$ldapConfig['server']['basedn'];

        // Testing not existing DN.
        $this->assertFalse($ldap->exists($dn));

        // Passing an entry object (should work). exists() should return false,
        // because we didn't add the test entry yet.
        $ou1 = Horde_Ldap_Entry::createFresh(
            $dn,
            ['objectClass' => ['top', 'organizationalUnit']]
        );

        $this->assertFalse($ldap->exists($dn));
        $this->assertFalse($ldap->exists($ou1));

        // Testing not existing DN.
        $ldap->add($ou1);
        $this->assertTrue($ldap->exists($dn));

        // Passing an float instead of a string.
        try {
            $ldap->exists(1.234);
            $this->fail('Horde_Ldap_Exception expected.');
        } catch (Horde_Ldap_Exception $e) {
        }

        // Testing multivalued RDNs.
        $dn = 'ou=Horde_Ldap_Test_exists_2+l=somewhere,' . self::$ldapConfig['server']['basedn'];
        $ou2 = Horde_Ldap_Entry::createFresh(
            $dn,
            ['objectClass' => ['top', 'organizationalUnit']]
        );
        $this->assertFalse($ldap->exists($dn));
        $ldap->add($ou2);
        $this->assertTrue($ldap->exists($dn));
    }

    /**
     * Test getEntry().
     */
    public function testGetEntry(): void
    {
        $ldap = new Horde_Ldap(self::$ldapConfig['server']);
        $dn = 'ou=Horde_Ldap_Test_getEntry,' . self::$ldapConfig['server']['basedn'];
        $entry = Horde_Ldap_Entry::createFresh(
            $dn,
            [
                'objectClass' => ['top', 'organizationalUnit'],
                'ou' => 'Horde_Ldap_Test_getEntry',
            ]
        );
        $ldap->add($entry);

        // Existing DN.
        $this->assertInstanceOf(Horde_Ldap_Entry::class, $ldap->getEntry($dn));

        // Not existing DN.
        try {
            $ldap->getEntry('cn=notexistent,' . self::$ldapConfig['server']['basedn']);
            $this->fail('Horde_Ldap_Exception expected.');
        } catch (Horde_Exception_NotFound $e) {
        }
    }

    /**
     * Test move().
     */
    public function testMove(): void
    {
        $ldap = new Horde_Ldap(self::$ldapConfig['server']);

        // For Moving tests, we need some little tree again.
        $base   = self::$ldapConfig['server']['basedn'];
        $testdn = 'ou=Horde_Ldap_Test_move,' . $base;

        $ou = Horde_Ldap_Entry::createFresh(
            $testdn,
            [
                'objectClass' => ['top', 'organizationalUnit'],
                'ou' => 'Horde_Ldap_Test_move',
            ]
        );
        $ou1 = Horde_Ldap_Entry::createFresh(
            'ou=source,' . $testdn,
            [
                'objectClass' => ['top', 'organizationalUnit'],
                'ou' => 'source',
            ]
        );
        $ou1L1 = Horde_Ldap_Entry::createFresh(
            'l=moveitem,ou=source,' . $testdn,
            [
                'objectClass' => ['top', 'locality'],
                'l' => 'moveitem',
                'description' => 'movetest',
            ]
        );
        $ou2 = Horde_Ldap_Entry::createFresh(
            'ou=target,' . $testdn,
            [
                'objectClass' => ['top', 'organizationalUnit'],
                'ou' => 'target',
            ]
        );
        $ou3 = Horde_Ldap_Entry::createFresh(
            'ou=target_otherdir,' . $testdn,
            [
                'objectClass' => ['top', 'organizationalUnit'],
                'ou' => 'target_otherdir',
            ]
        );
        $ldap->add($ou);
        $ldap->add($ou1);
        $ldap->add($ou1L1);
        $ldap->add($ou2);
        $ldap->add($ou3);
        $this->assertTrue($ldap->exists($ou->dn()));
        $this->assertTrue($ldap->exists($ou1->dn()));
        $this->assertTrue($ldap->exists($ou1L1->dn()));
        $this->assertTrue($ldap->exists($ou2->dn()));
        $this->assertTrue($ldap->exists($ou3->dn()));
        // Tree established.

        // Local rename.
        $olddn = $ou1L1->currentDN();
        $ldap->move($ou1L1, str_replace('moveitem', 'move_item', $ou1L1->dn()));
        $this->assertTrue($ldap->exists($ou1L1->dn()));
        $this->assertFalse($ldap->exists($olddn));

        // Local move.
        $olddn = $ou1L1->currentDN();
        $ldap->move($ou1L1, 'l=move_item,' . $ou2->dn());
        $this->assertTrue($ldap->exists($ou1L1->dn()));
        $this->assertFalse($ldap->exists($olddn));

        // Local move backward, with rename. Here we use the DN of the object,
        // to test DN conversion.
        // Note that this will outdate the object since it does not has
        // knowledge about the move.
        $olddn = $ou1L1->currentDN();
        $newdn = 'l=moveditem,' . $ou2->dn();
        $ldap->move($olddn, $newdn);
        $this->assertTrue($ldap->exists($newdn));
        $this->assertFalse($ldap->exists($olddn));
        // Refetch since the object's DN was outdated.
        $ou1L1 = $ldap->getEntry($newdn);

        // Fake-cross directory move using two separate links to the same
        // directory. This other directory is represented by
        // ou=target_otherdir.
        $ldap2 = new Horde_Ldap(self::$ldapConfig['server']);
        $olddn = $ou1L1->currentDN();
        $ldap->move($ou1L1, 'l=movedcrossdir,' . $ou3->dn(), $ldap2);
        $this->assertFalse($ldap->exists($olddn));
        $this->assertTrue($ldap2->exists($ou1L1->dn()));

        // Try to move over an existing entry.
        try {
            $ldap->move($ou2, $ou3->dn(), $ldap2);
            $this->fail('Horde_Ldap_Exception expected.');
        } catch (Horde_Ldap_Exception $e) {
        }

        // Try cross directory move without providing an valid entry but a DN.
        try {
            $ldap->move($ou1L1->dn(), 'l=movedcrossdir2,' . $ou2->dn(), $ldap2);
            $this->fail('Horde_Ldap_Exception expected.');
        } catch (Horde_Ldap_Exception $e) {
        }

        // Try passing an invalid entry object.
        try {
            $ldap->move($ldap, 'l=move_item,' . $ou2->dn());
            $this->fail('Horde_Ldap_Exception expected.');
        } catch (Horde_Ldap_Exception $e) {
        }

        // Try passing an invalid LDAP object.
        try {
            $ldap->move($ou1L1, 'l=move_item,' . $ou2->dn(), $ou1);
            $this->fail('Horde_Ldap_Exception expected.');
        } catch (Horde_Ldap_Exception $e) {
        }
    }

    /**
     * Test copy().
     */
    public function testCopy(): void
    {
        $ldap = new Horde_Ldap(self::$ldapConfig['server']);

        // Some testdata.
        $base = self::$ldapConfig['server']['basedn'];
        $ou1 = Horde_Ldap_Entry::createFresh(
            'ou=Horde_Ldap_Test_pool,' . $base,
            [
                'objectClass' => ['top', 'organizationalUnit'],
                'ou' => 'Horde_Ldap_Test_copy',
            ]
        );
        $ou2 = Horde_Ldap_Entry::createFresh(
            'ou=Horde_Ldap_Test_tgt,' . $base,
            [
                'objectClass' => ['top', 'organizationalUnit'],
                'ou' => 'Horde_Ldap_Test_copy',
            ]
        );
        $ldap->add($ou1);
        $this->assertTrue($ldap->exists($ou1->dn()));
        $ldap->add($ou2);
        $this->assertTrue($ldap->exists($ou2->dn()));

        $entry = Horde_Ldap_Entry::createFresh(
            'l=cptest,' . $ou1->dn(),
            [
                'objectClass' => ['top', 'locality'],
                'l' => 'cptest',
            ]
        );
        $ldap->add($entry);
        $ldap->exists($entry->dn());

        // Copy over the entry to another tree with rename.
        $entrycp = $ldap->copy($entry, 'l=test_copied,' . $ou2->dn());
        $this->assertInstanceOf(Horde_Ldap_Entry::class, $entrycp);
        $this->assertNotEquals($entry->dn(), $entrycp->dn());
        $this->assertTrue($ldap->exists($entrycp->dn()));

        // Copy same again (fails, entry exists).
        try {
            $entrycpF = $ldap->copy($entry, 'l=test_copied,' . $ou2->dn());
            $this->fail('Horde_Ldap_Exception expected.');
        } catch (Horde_Ldap_Exception $e) {
        }

        // Use only DNs to copy (fails).
        try {
            $entrycp = $ldap->copy($entry->dn(), 'l=test_copied2,' . $ou2->dn());
            $this->fail('Horde_Ldap_Exception expected.');
        } catch (Horde_Ldap_Exception $e) {
        }
    }

    /**
     * Tests retrieval of root DSE object.
     */
    public function testRootDSE(): void
    {
        $ldap = new Horde_Ldap(self::$ldapConfig['server']);
        $this->assertInstanceOf(Horde_Ldap_RootDse::class, $ldap->rootDSE());
    }

    /**
     * Tests retrieval of schema through LDAP object.
     */
    public function testSchema(): void
    {
        $ldap = new Horde_Ldap(self::$ldapConfig['server']);
        $this->assertInstanceOf(Horde_Ldap_Schema::class, $ldap->schema());
    }

    /**
     * Test getLink().
     */
    public function testGetLink(): void
    {
        $ldap = new Horde_Ldap(self::$ldapConfig['server']);
        $this->assertTrue(is_resource($ldap->getLink()));
    }
}
