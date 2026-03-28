<?php

declare(strict_types=1);

/**
 * Test Entry state machine and change tracking
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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Horde_Ldap_Entry state management including isNew(), markAsNew(),
 * willBeDeleted(), willBeMoved(), currentDN(), dn() setter, and getChanges().
 *
 * These methods track entry lifecycle and modifications before update() is called.
 */
#[CoversClass(Horde_Ldap_Entry::class)]
class EntryStateTest extends TestCase
{
    /**
     * Test fresh entry is marked as new.
     */
    public function testFreshEntryIsNew(): void
    {
        $entry = Horde_Ldap_Entry::createFresh(
            'cn=test,dc=example,dc=com',
            ['cn' => 'test']
        );

        $this->assertTrue($entry->isNew());
    }

    /**
     * Test markAsNew() sets new state to true.
     */
    public function testMarkAsNewTrue(): void
    {
        $entry = Horde_Ldap_Entry::createFresh(
            'cn=test,dc=example,dc=com',
            ['cn' => 'test']
        );

        // Start as new
        $this->assertTrue($entry->isNew());

        // Mark as not new
        $entry->markAsNew(false);
        $this->assertFalse($entry->isNew());

        // Mark as new again
        $entry->markAsNew(true);
        $this->assertTrue($entry->isNew());
    }

    /**
     * Test markAsNew() with default parameter (true).
     */
    public function testMarkAsNewDefault(): void
    {
        $entry = Horde_Ldap_Entry::createFresh(
            'cn=test,dc=example,dc=com',
            ['cn' => 'test']
        );

        $entry->markAsNew(false);
        $this->assertFalse($entry->isNew());

        // Default parameter is true
        $entry->markAsNew();
        $this->assertTrue($entry->isNew());
    }

    /**
     * Test markAsNew() with non-boolean values.
     */
    public function testMarkAsNewCoercion(): void
    {
        $entry = Horde_Ldap_Entry::createFresh(
            'cn=test,dc=example,dc=com',
            ['cn' => 'test']
        );

        // Test truthy values
        $entry->markAsNew(1);
        $this->assertTrue($entry->isNew());

        $entry->markAsNew('string');
        $this->assertTrue($entry->isNew());

        // Test falsy values
        $entry->markAsNew(0);
        $this->assertFalse($entry->isNew());

        $entry->markAsNew('');
        $this->assertFalse($entry->isNew());
    }

    /**
     * Test willBeDeleted() returns false by default.
     */
    public function testWillBeDeletedDefault(): void
    {
        $entry = Horde_Ldap_Entry::createFresh(
            'cn=test,dc=example,dc=com',
            ['cn' => 'test']
        );

        $this->assertFalse($entry->willBeDeleted());
    }

    /**
     * Test willBeDeleted() returns true after delete() with no arguments.
     */
    public function testWillBeDeletedAfterDelete(): void
    {
        $entry = Horde_Ldap_Entry::createFresh(
            'cn=test,dc=example,dc=com',
            ['cn' => 'test']
        );

        // Calling delete() without arguments marks entry for deletion
        $entry->delete();

        $this->assertTrue($entry->willBeDeleted());
    }

    /**
     * Test willBeMoved() returns false when DN hasn't changed.
     */
    public function testWillBeMovedDefault(): void
    {
        $entry = Horde_Ldap_Entry::createFresh(
            'cn=test,dc=example,dc=com',
            ['cn' => 'test']
        );

        $this->assertFalse($entry->willBeMoved());
    }

    /**
     * Test willBeMoved() returns true after DN change.
     */
    public function testWillBeMovedAfterDnChange(): void
    {
        $entry = Horde_Ldap_Entry::createFresh(
            'cn=test,dc=example,dc=com',
            ['cn' => 'test']
        );

        $this->assertFalse($entry->willBeMoved());

        // Change DN
        $entry->dn('cn=newtest,dc=example,dc=com');

        $this->assertTrue($entry->willBeMoved());
    }

    /**
     * Test currentDN() returns original DN.
     */
    public function testCurrentDN(): void
    {
        $originalDN = 'cn=test,dc=example,dc=com';
        $entry = Horde_Ldap_Entry::createFresh($originalDN, ['cn' => 'test']);

        $this->assertEquals($originalDN, $entry->currentDN());
    }

    /**
     * Test currentDN() remains unchanged after dn() setter.
     */
    public function testCurrentDNUnchangedAfterDnChange(): void
    {
        $originalDN = 'cn=test,dc=example,dc=com';
        $newDN = 'cn=newtest,dc=example,dc=com';

        $entry = Horde_Ldap_Entry::createFresh($originalDN, ['cn' => 'test']);

        $this->assertEquals($originalDN, $entry->currentDN());

        // Change DN
        $entry->dn($newDN);

        // currentDN() should still return original
        $this->assertEquals($originalDN, $entry->currentDN());

        // But dn() should return new DN
        $this->assertEquals($newDN, $entry->dn());
    }

    /**
     * Test dn() getter returns current DN when not changed.
     */
    public function testDnGetterDefault(): void
    {
        $dn = 'cn=test,dc=example,dc=com';
        $entry = Horde_Ldap_Entry::createFresh($dn, ['cn' => 'test']);

        $this->assertEquals($dn, $entry->dn());
    }

    /**
     * Test dn() setter changes returned DN.
     */
    public function testDnSetterChangesReturnedDn(): void
    {
        $originalDN = 'cn=test,dc=example,dc=com';
        $newDN = 'cn=newtest,dc=example,dc=com';

        $entry = Horde_Ldap_Entry::createFresh($originalDN, ['cn' => 'test']);

        $this->assertEquals($originalDN, $entry->dn());

        $entry->dn($newDN);

        $this->assertEquals($newDN, $entry->dn());
    }

    /**
     * Test getChanges() returns initialized structure by default.
     */
    public function testGetChangesDefault(): void
    {
        $entry = Horde_Ldap_Entry::createFresh(
            'cn=test,dc=example,dc=com',
            ['cn' => 'test']
        );

        $changes = $entry->getChanges();

        $this->assertIsArray($changes);
        // _changes is initialized with empty sub-arrays
        $this->assertArrayHasKey('add', $changes);
        $this->assertArrayHasKey('delete', $changes);
        $this->assertArrayHasKey('replace', $changes);
        $this->assertEmpty($changes['add']);
        $this->assertEmpty($changes['delete']);
        $this->assertEmpty($changes['replace']);
    }

    /**
     * Test getChanges() tracks add operations.
     */
    public function testGetChangesAfterAdd(): void
    {
        $entry = Horde_Ldap_Entry::createFresh(
            'cn=test,dc=example,dc=com',
            ['cn' => 'test']
        );

        $entry->add(['sn' => 'surname']);

        $changes = $entry->getChanges();

        $this->assertArrayHasKey('add', $changes);
        $this->assertArrayHasKey('sn', $changes['add']);
        $this->assertEquals(['surname'], $changes['add']['sn']);
    }

    /**
     * Test getChanges() tracks multiple add operations.
     */
    public function testGetChangesMultipleAdds(): void
    {
        $entry = Horde_Ldap_Entry::createFresh(
            'cn=test,dc=example,dc=com',
            ['cn' => 'test']
        );

        $entry->add(['sn' => 'surname']);
        $entry->add(['mail' => 'test@example.com']);

        $changes = $entry->getChanges();

        $this->assertArrayHasKey('add', $changes);
        $this->assertArrayHasKey('sn', $changes['add']);
        $this->assertArrayHasKey('mail', $changes['add']);
    }

    /**
     * Test getChanges() tracks replace operations.
     */
    public function testGetChangesAfterReplace(): void
    {
        $entry = Horde_Ldap_Entry::createFresh(
            'cn=test,dc=example,dc=com',
            ['cn' => 'test', 'sn' => 'oldsurname']
        );

        $entry->replace(['sn' => 'newsurname']);

        $changes = $entry->getChanges();

        $this->assertArrayHasKey('replace', $changes);
        $this->assertArrayHasKey('sn', $changes['replace']);
        $this->assertEquals(['newsurname'], $changes['replace']['sn']);
    }

    /**
     * Test getChanges() tracks delete operations.
     */
    public function testGetChangesAfterDelete(): void
    {
        $entry = Horde_Ldap_Entry::createFresh(
            'cn=test,dc=example,dc=com',
            ['cn' => 'test', 'sn' => 'surname']
        );

        $entry->delete(['sn']);

        $changes = $entry->getChanges();

        $this->assertArrayHasKey('delete', $changes);
        $this->assertArrayHasKey('sn', $changes['delete']);
    }

    /**
     * Test getChanges() tracks delete of specific values.
     */
    public function testGetChangesDeleteSpecificValues(): void
    {
        $entry = Horde_Ldap_Entry::createFresh(
            'cn=test,dc=example,dc=com',
            ['objectClass' => ['top', 'person', 'organizationalPerson']]
        );

        $entry->delete(['objectClass' => 'person']);

        $changes = $entry->getChanges();

        $this->assertArrayHasKey('delete', $changes);
        // Attribute names are normalized - check both possible forms
        $deleteKeys = array_keys($changes['delete']);
        $lowerKeys = array_map('strtolower', $deleteKeys);
        $this->assertContains('objectclass', $lowerKeys);

        // Find the actual key (might be objectClass or objectclass)
        $actualKey = null;
        foreach ($changes['delete'] as $key => $value) {
            if (strtolower($key) === 'objectclass') {
                $actualKey = $key;
                break;
            }
        }
        $this->assertNotNull($actualKey);
        $this->assertContains('person', $changes['delete'][$actualKey]);
    }

    /**
     * Test getChanges() tracks mixed operations.
     */
    public function testGetChangesMixedOperations(): void
    {
        $entry = Horde_Ldap_Entry::createFresh(
            'cn=test,dc=example,dc=com',
            ['cn' => 'test', 'sn' => 'oldsurname', 'description' => 'old']
        );

        // Add new attribute
        $entry->add(['mail' => 'test@example.com']);

        // Replace existing attribute
        $entry->replace(['sn' => 'newsurname']);

        // Delete attribute
        $entry->delete(['description']);

        $changes = $entry->getChanges();

        $this->assertArrayHasKey('add', $changes);
        $this->assertArrayHasKey('replace', $changes);
        $this->assertArrayHasKey('delete', $changes);

        $this->assertArrayHasKey('mail', $changes['add']);
        $this->assertArrayHasKey('sn', $changes['replace']);
        $this->assertArrayHasKey('description', $changes['delete']);
    }

    /**
     * Test state after adding value to existing attribute.
     */
    public function testGetChangesAddToExistingAttribute(): void
    {
        $entry = Horde_Ldap_Entry::createFresh(
            'cn=test,dc=example,dc=com',
            ['objectClass' => ['top', 'person']]
        );

        $entry->add(['objectClass' => 'organizationalPerson']);

        $changes = $entry->getChanges();

        $this->assertArrayHasKey('add', $changes);

        // Find the actual key (case normalization)
        $actualKey = null;
        foreach ($changes['add'] as $key => $value) {
            if (strtolower($key) === 'objectclass') {
                $actualKey = $key;
                break;
            }
        }
        $this->assertNotNull($actualKey);
        $this->assertContains('organizationalPerson', $changes['add'][$actualKey]);
    }

    /**
     * Test that willBeMoved() and DN tracking work together.
     */
    public function testDnTrackingIntegration(): void
    {
        $originalDN = 'cn=test,ou=users,dc=example,dc=com';
        $newDN = 'cn=test,ou=people,dc=example,dc=com';

        $entry = Horde_Ldap_Entry::createFresh($originalDN, ['cn' => 'test']);

        // Initial state
        $this->assertEquals($originalDN, $entry->currentDN());
        $this->assertEquals($originalDN, $entry->dn());
        $this->assertFalse($entry->willBeMoved());

        // Change DN
        $entry->dn($newDN);

        // After change
        $this->assertEquals($originalDN, $entry->currentDN());  // Unchanged
        $this->assertEquals($newDN, $entry->dn());              // Changed
        $this->assertTrue($entry->willBeMoved());               // Detects difference
    }

    /**
     * Test state of completely new entry.
     */
    public function testNewEntryState(): void
    {
        $entry = Horde_Ldap_Entry::createFresh(
            'cn=test,dc=example,dc=com',
            ['cn' => 'test']
        );

        $this->assertTrue($entry->isNew());
        $this->assertFalse($entry->willBeDeleted());
        $this->assertFalse($entry->willBeMoved());

        // getChanges() has structure but empty sub-arrays
        $changes = $entry->getChanges();
        $this->assertEmpty($changes['add']);
        $this->assertEmpty($changes['delete']);
        $this->assertEmpty($changes['replace']);
    }

    /**
     * Test marking entry for deletion.
     */
    public function testDeleteEntryState(): void
    {
        $entry = Horde_Ldap_Entry::createFresh(
            'cn=test,dc=example,dc=com',
            ['cn' => 'test']
        );

        $this->assertFalse($entry->willBeDeleted());

        $entry->delete();  // No arguments marks whole entry for deletion

        $this->assertTrue($entry->willBeDeleted());
    }
}
