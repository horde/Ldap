<?php

declare(strict_types=1);

/**
 * Test empty DN validation in add/delete/modify/rename operations
 *
 * Copyright 2010-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @package   Ldap
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPL-3.0
 */

namespace Horde\Ldap\Test\Unit;

use Horde_Ldap;
use Horde_Ldap_Entry;
use Horde_Ldap_Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for empty DN validation in LDAP operations.
 *
 * Empty DN ('') is valid for querying the Root DSE but invalid for
 * add/delete/modify/rename operations. These tests verify that the library
 * validates and rejects empty DNs early with clear error messages, rather
 * than passing them to the LDAP server.
 */
#[CoversClass(Horde_Ldap::class)]
#[CoversClass(Horde_Ldap_Entry::class)]
class EmptyDnValidationTest extends TestCase
{
    /**
     * Test that add() rejects empty DN.
     */
    public function testAddRejectsEmptyDN(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap::class);
        $ldap = $reflection->newInstanceWithoutConstructor();

        // Create entry with empty DN
        $entryReflection = new \ReflectionClass(Horde_Ldap_Entry::class);
        $entry = $entryReflection->newInstanceWithoutConstructor();

        $dnProp = $entryReflection->getProperty('_dn');
        $dnProp->setAccessible(true);
        $dnProp->setValue($entry, '');

        $this->expectException(Horde_Ldap_Exception::class);
        $this->expectExceptionMessage('Cannot add entry with empty DN');

        $ldap->add($entry);
    }

    /**
     * Test that delete() rejects empty DN string.
     */
    public function testDeleteRejectsEmptyDNString(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap::class);
        $ldap = $reflection->newInstanceWithoutConstructor();

        // Set _link to false (not connected, avoids destructor issues)
        $linkProp = $reflection->getProperty('_link');
        $linkProp->setAccessible(true);
        $linkProp->setValue($ldap, false);

        $this->expectException(Horde_Ldap_Exception::class);
        $this->expectExceptionMessage('Cannot delete entry with empty DN');

        $ldap->delete('');
    }

    /**
     * Test that delete() rejects entry with empty DN.
     */
    public function testDeleteRejectsEmptyDNEntry(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap::class);
        $ldap = $reflection->newInstanceWithoutConstructor();

        // Set _link to false
        $linkProp = $reflection->getProperty('_link');
        $linkProp->setAccessible(true);
        $linkProp->setValue($ldap, false);

        // Create entry with empty DN
        $entryReflection = new \ReflectionClass(Horde_Ldap_Entry::class);
        $entry = $entryReflection->newInstanceWithoutConstructor();

        $dnProp = $entryReflection->getProperty('_dn');
        $dnProp->setAccessible(true);
        $dnProp->setValue($entry, '');

        $this->expectException(Horde_Ldap_Exception::class);
        $this->expectExceptionMessage('Cannot delete entry with empty DN');

        $ldap->delete($entry);
    }

    /**
     * Test that modify() rejects empty DN string.
     */
    public function testModifyRejectsEmptyDNString(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap::class);
        $ldap = $reflection->newInstanceWithoutConstructor();

        // Set _link to false
        $linkProp = $reflection->getProperty('_link');
        $linkProp->setAccessible(true);
        $linkProp->setValue($ldap, false);

        $this->expectException(Horde_Ldap_Exception::class);
        $this->expectExceptionMessage('Cannot modify entry with empty DN');

        $ldap->modify('', ['replace' => ['description' => 'test']]);
    }

    /**
     * Test that Entry::update() rejects rename to empty DN.
     */
    public function testEntryUpdateRejectsRenameToEmptyDN(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap_Entry::class);
        $entry = $reflection->newInstanceWithoutConstructor();

        // Set current DN
        $dnProp = $reflection->getProperty('_dn');
        $dnProp->setAccessible(true);
        $dnProp->setValue($entry, 'cn=test,dc=example,dc=com');

        // Set new DN to empty
        $newdnProp = $reflection->getProperty('_newdn');
        $newdnProp->setAccessible(true);
        $newdnProp->setValue($entry, '');

        // Set not new (to trigger rename path)
        $newProp = $reflection->getProperty('_new');
        $newProp->setAccessible(true);
        $newProp->setValue($entry, false);

        // Set not delete
        $deleteProp = $reflection->getProperty('_delete');
        $deleteProp->setAccessible(true);
        $deleteProp->setValue($entry, false);

        // Mock LDAP connection
        $ldapReflection = new \ReflectionClass(Horde_Ldap::class);
        $ldap = $ldapReflection->newInstanceWithoutConstructor();

        $linkProp = $ldapReflection->getProperty('_link');
        $linkProp->setAccessible(true);
        $linkProp->setValue($ldap, false); // Not connected to avoid destructor issues

        $ldapProp = $reflection->getProperty('_ldap');
        $ldapProp->setAccessible(true);
        $ldapProp->setValue($entry, $ldap);

        $this->expectException(Horde_Ldap_Exception::class);
        $this->expectExceptionMessage('Cannot rename entry to empty DN');

        $entry->update();
    }

    /**
     * Test that Entry::update() rejects rename to same DN.
     */
    public function testEntryUpdateRejectsRenameToSameDN(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap_Entry::class);
        $entry = $reflection->newInstanceWithoutConstructor();

        $dn = 'cn=test,dc=example,dc=com';

        // Set current DN
        $dnProp = $reflection->getProperty('_dn');
        $dnProp->setAccessible(true);
        $dnProp->setValue($entry, $dn);

        // Set new DN to same as current
        $newdnProp = $reflection->getProperty('_newdn');
        $newdnProp->setAccessible(true);
        $newdnProp->setValue($entry, $dn);

        // Set not new
        $newProp = $reflection->getProperty('_new');
        $newProp->setAccessible(true);
        $newProp->setValue($entry, false);

        // Set not delete
        $deleteProp = $reflection->getProperty('_delete');
        $deleteProp->setAccessible(true);
        $deleteProp->setValue($entry, false);

        // Mock LDAP connection
        $ldapReflection = new \ReflectionClass(Horde_Ldap::class);
        $ldap = $ldapReflection->newInstanceWithoutConstructor();

        $linkProp = $ldapReflection->getProperty('_link');
        $linkProp->setAccessible(true);
        $linkProp->setValue($ldap, false);

        $ldapProp = $reflection->getProperty('_ldap');
        $ldapProp->setAccessible(true);
        $ldapProp->setValue($entry, $ldap);

        $this->expectException(Horde_Ldap_Exception::class);
        $this->expectExceptionMessage('New DN must differ from current DN');

        $entry->update();
    }

    /**
     * Test that error messages are clear and informative.
     */
    public function testErrorMessagesAreDescriptive(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap::class);
        $ldap = $reflection->newInstanceWithoutConstructor();

        $linkProp = $reflection->getProperty('_link');
        $linkProp->setAccessible(true);
        $linkProp->setValue($ldap, false);

        try {
            $ldap->delete('');
            $this->fail('Expected exception was not thrown');
        } catch (Horde_Ldap_Exception $e) {
            // Verify message mentions both the problem and the reason
            $message = $e->getMessage();
            $this->assertStringContainsString('empty DN', $message);
            $this->assertStringContainsString('root DSE', $message);
            $this->assertStringContainsString('cannot be deleted', $message);
        }
    }
}
