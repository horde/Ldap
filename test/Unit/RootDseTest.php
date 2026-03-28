<?php

declare(strict_types=1);

/**
 * Test RootDSE parsing and capability detection
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
use Horde_Ldap_RootDse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for Horde_Ldap_RootDse capability detection and attribute retrieval.
 *
 * RootDSE is a special LDAP entry that describes server capabilities.
 * These tests use mock Entry objects to test RootDSE logic without requiring
 * a live LDAP server.
 */
#[CoversClass(Horde_Ldap_RootDse::class)]
class RootDseTest extends TestCase
{
    /**
     * Create a mock RootDSE with given attributes.
     */
    private function createMockRootDse(array $attributes): Horde_Ldap_RootDse
    {
        // Create a mock Entry
        $entry = Horde_Ldap_Entry::createFresh('', $attributes);

        // Create RootDse without constructor (to avoid needing Ldap object)
        $reflection = new ReflectionClass(Horde_Ldap_RootDse::class);
        $rootDse = $reflection->newInstanceWithoutConstructor();

        // Set the _entry property
        $entryProperty = $reflection->getProperty('_entry');
        $entryProperty->setAccessible(true);
        $entryProperty->setValue($rootDse, $entry);

        return $rootDse;
    }

    /**
     * Test getValue() retrieves attribute values.
     */
    public function testGetValue(): void
    {
        $rootDse = $this->createMockRootDse([
            'vendorName' => 'OpenLDAP',
            'vendorVersion' => '2.4.50',
        ]);

        $this->assertEquals('OpenLDAP', $rootDse->getValue('vendorName'));
        $this->assertEquals('2.4.50', $rootDse->getValue('vendorVersion'));
    }

    /**
     * Test getValue() with 'all' option returns array.
     */
    public function testGetValueAll(): void
    {
        $rootDse = $this->createMockRootDse([
            'namingContexts' => ['dc=example,dc=com', 'dc=test,dc=org'],
        ]);

        $contexts = $rootDse->getValue('namingContexts', 'all');

        $this->assertIsArray($contexts);
        $this->assertCount(2, $contexts);
        $this->assertContains('dc=example,dc=com', $contexts);
        $this->assertContains('dc=test,dc=org', $contexts);
    }

    /**
     * Test supportedExtension() with single OID.
     */
    public function testSupportedExtensionSingle(): void
    {
        $rootDse = $this->createMockRootDse([
            'supportedExtension' => [
                '1.3.6.1.4.1.4203.1.11.1',  // Modify Password
                '1.3.6.1.4.1.4203.1.11.3',  // Who Am I
            ],
        ]);

        $this->assertTrue($rootDse->supportedExtension('1.3.6.1.4.1.4203.1.11.1'));
        $this->assertTrue($rootDse->supportedExtension('1.3.6.1.4.1.4203.1.11.3'));
        $this->assertFalse($rootDse->supportedExtension('1.2.3.4.5'));
    }

    /**
     * Test supportedExtension() with array of OIDs.
     */
    public function testSupportedExtensionArray(): void
    {
        $rootDse = $this->createMockRootDse([
            'supportedExtension' => [
                '1.3.6.1.4.1.4203.1.11.1',
                '1.3.6.1.4.1.4203.1.11.3',
            ],
        ]);

        // All OIDs present
        $this->assertTrue($rootDse->supportedExtension([
            '1.3.6.1.4.1.4203.1.11.1',
            '1.3.6.1.4.1.4203.1.11.3',
        ]));

        // Some OIDs missing
        $this->assertFalse($rootDse->supportedExtension([
            '1.3.6.1.4.1.4203.1.11.1',
            '1.2.3.4.5',  // Not supported
        ]));
    }

    /**
     * Test supportedVersion() checks LDAP protocol versions.
     */
    public function testSupportedVersion(): void
    {
        $rootDse = $this->createMockRootDse([
            'supportedLDAPVersion' => ['2', '3'],
        ]);

        $this->assertTrue($rootDse->supportedVersion('2'));
        $this->assertTrue($rootDse->supportedVersion('3'));
        $this->assertFalse($rootDse->supportedVersion('4'));
    }

    /**
     * Test supportedVersion() with array of versions.
     */
    public function testSupportedVersionArray(): void
    {
        $rootDse = $this->createMockRootDse([
            'supportedLDAPVersion' => ['2', '3'],
        ]);

        $this->assertTrue($rootDse->supportedVersion(['2', '3']));
        $this->assertFalse($rootDse->supportedVersion(['3', '4']));
    }

    /**
     * Test supportedControl() checks control OIDs.
     */
    public function testSupportedControl(): void
    {
        $rootDse = $this->createMockRootDse([
            'supportedControl' => [
                '1.2.840.113556.1.4.319',  // Paged Results
                '1.2.840.113556.1.4.473',  // Server-side Sort
            ],
        ]);

        $this->assertTrue($rootDse->supportedControl('1.2.840.113556.1.4.319'));
        $this->assertTrue($rootDse->supportedControl('1.2.840.113556.1.4.473'));
        $this->assertFalse($rootDse->supportedControl('1.2.3.4.5'));
    }

    /**
     * Test supportedControl() with array of OIDs.
     */
    public function testSupportedControlArray(): void
    {
        $rootDse = $this->createMockRootDse([
            'supportedControl' => [
                '1.2.840.113556.1.4.319',
                '1.2.840.113556.1.4.473',
            ],
        ]);

        $this->assertTrue($rootDse->supportedControl([
            '1.2.840.113556.1.4.319',
            '1.2.840.113556.1.4.473',
        ]));

        $this->assertFalse($rootDse->supportedControl([
            '1.2.840.113556.1.4.319',
            '1.2.3.4.5',
        ]));
    }

    /**
     * Test supportedSASLMechanism() checks SASL mechanisms.
     */
    public function testSupportedSASLMechanism(): void
    {
        $rootDse = $this->createMockRootDse([
            'supportedSASLMechanisms' => ['PLAIN', 'LOGIN', 'GSSAPI', 'DIGEST-MD5'],
        ]);

        $this->assertTrue($rootDse->supportedSASLMechanism('PLAIN'));
        $this->assertTrue($rootDse->supportedSASLMechanism('GSSAPI'));
        $this->assertFalse($rootDse->supportedSASLMechanism('CRAM-MD5'));
    }

    /**
     * Test supportedSASLMechanism() with array of mechanisms.
     */
    public function testSupportedSASLMechanismArray(): void
    {
        $rootDse = $this->createMockRootDse([
            'supportedSASLMechanisms' => ['PLAIN', 'LOGIN', 'GSSAPI'],
        ]);

        $this->assertTrue($rootDse->supportedSASLMechanism(['PLAIN', 'LOGIN']));
        $this->assertFalse($rootDse->supportedSASLMechanism(['PLAIN', 'CRAM-MD5']));
    }

    /**
     * Test empty supportedExtension attribute.
     */
    public function testSupportedExtensionEmpty(): void
    {
        $rootDse = $this->createMockRootDse([
            'supportedExtension' => [],
        ]);

        $this->assertFalse($rootDse->supportedExtension('1.2.3.4.5'));
    }

    /**
     * Test real-world OpenLDAP RootDSE attributes.
     */
    public function testOpenLDAPRootDse(): void
    {
        $rootDse = $this->createMockRootDse([
            'vendorName' => 'OpenLDAP',
            'vendorVersion' => '20450',
            'namingContexts' => ['dc=example,dc=com'],
            'supportedLDAPVersion' => ['3'],
            'supportedExtension' => [
                '1.3.6.1.4.1.4203.1.11.1',  // Modify Password
                '1.3.6.1.4.1.4203.1.11.3',  // Who Am I
            ],
            'supportedControl' => [
                '1.2.840.113556.1.4.319',   // Paged Results
                '1.3.6.1.4.1.4203.1.10.1',  // Subentries
            ],
            'supportedSASLMechanisms' => ['GSSAPI', 'GSS-SPNEGO', 'DIGEST-MD5', 'CRAM-MD5'],
        ]);

        $this->assertEquals('OpenLDAP', $rootDse->getValue('vendorName'));
        $this->assertTrue($rootDse->supportedVersion('3'));
        $this->assertFalse($rootDse->supportedVersion('2'));
        $this->assertTrue($rootDse->supportedExtension('1.3.6.1.4.1.4203.1.11.1'));
        $this->assertTrue($rootDse->supportedControl('1.2.840.113556.1.4.319'));
        $this->assertTrue($rootDse->supportedSASLMechanism('GSSAPI'));
    }

    /**
     * Test real-world Active Directory RootDSE attributes.
     */
    public function testActiveDirectoryRootDse(): void
    {
        $rootDse = $this->createMockRootDse([
            'vendorName' => 'Microsoft Corporation',
            'namingContexts' => [
                'DC=example,DC=com',
                'CN=Configuration,DC=example,DC=com',
                'CN=Schema,CN=Configuration,DC=example,DC=com',
            ],
            'supportedLDAPVersion' => ['3', '2'],
            'supportedControl' => [
                '1.2.840.113556.1.4.319',    // Paged Results
                '1.2.840.113556.1.4.801',    // Security Descriptor Flags
                '1.2.840.113556.1.4.473',    // Server-side Sort
            ],
            'supportedSASLMechanisms' => ['GSSAPI', 'GSS-SPNEGO', 'EXTERNAL', 'DIGEST-MD5'],
        ]);

        $this->assertEquals('Microsoft Corporation', $rootDse->getValue('vendorName'));
        $this->assertTrue($rootDse->supportedVersion(['2', '3']));
        $this->assertTrue($rootDse->supportedControl('1.2.840.113556.1.4.801'));
        $this->assertTrue($rootDse->supportedSASLMechanism(['GSSAPI', 'EXTERNAL']));

        // AD supports multiple naming contexts
        $contexts = $rootDse->getValue('namingContexts', 'all');
        $this->assertCount(3, $contexts);
        $this->assertContains('DC=example,DC=com', $contexts);
    }

    /**
     * Test serialization round-trip.
     */
    public function testSerialization(): void
    {
        $rootDse = $this->createMockRootDse([
            'vendorName' => 'TestVendor',
            'supportedLDAPVersion' => ['3'],
        ]);

        $serialized = serialize($rootDse);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(Horde_Ldap_RootDse::class, $unserialized);
        $this->assertEquals('TestVendor', $unserialized->getValue('vendorName'));
        $this->assertTrue($unserialized->supportedVersion('3'));
    }

    /**
     * Test case-insensitive attribute names.
     */
    public function testCaseInsensitiveAttributes(): void
    {
        $rootDse = $this->createMockRootDse([
            'vendorName' => 'TestVendor',
        ]);

        // Entry class handles case-insensitivity
        $this->assertEquals('TestVendor', $rootDse->getValue('vendorName'));
        $this->assertEquals('TestVendor', $rootDse->getValue('vendorname'));
        $this->assertEquals('TestVendor', $rootDse->getValue('VENDORNAME'));
    }
}
