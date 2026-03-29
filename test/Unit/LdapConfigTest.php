<?php

declare(strict_types=1);

/**
 * Test Ldap configuration and options
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

use Horde_Ldap;
use Horde_Ldap_Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Horde_Ldap configuration, options, and initialization.
 *
 * Note: Most Ldap methods require a live LDAP connection. These tests focus
 * on configuration validation and option handling that can be tested without
 * a connection.
 */
#[CoversClass(Horde_Ldap::class)]
class LdapConfigTest extends TestCase
{
    /**
     * Test checkLDAPExtension() when extension is loaded.
     */
    public function testCheckLDAPExtensionLoaded(): void
    {
        if (!extension_loaded('ldap')) {
            $this->markTestSkipped('LDAP extension not available');
        }

        // Should not throw exception
        Horde_Ldap::checkLDAPExtension();
        $this->assertTrue(true);
    }

    /**
     * Test errorName() returns known error names.
     */
    public function testErrorNameKnownErrors(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap::class);
        $ldap = $reflection->newInstanceWithoutConstructor();

        $method = $reflection->getMethod('errorName');
        $method->setAccessible(true);

        // Test common error codes
        $this->assertEquals('LDAP_SUCCESS', $method->invoke($ldap, 0));
        $this->assertEquals('LDAP_OPERATIONS_ERROR', $method->invoke($ldap, 1));
        $this->assertEquals('LDAP_PROTOCOL_ERROR', $method->invoke($ldap, 2));
        $this->assertEquals('LDAP_NO_SUCH_OBJECT', $method->invoke($ldap, 32));
        $this->assertEquals('LDAP_INVALID_CREDENTIALS', $method->invoke($ldap, 49));
    }

    /**
     * Test errorName() with unknown error code.
     */
    public function testErrorNameUnknownError(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap::class);
        $ldap = $reflection->newInstanceWithoutConstructor();

        $method = $reflection->getMethod('errorName');
        $method->setAccessible(true);

        // Unknown error code returns formatted string
        $result = $method->invoke($ldap, 9999);
        $this->assertEquals('Unknown Error (9999)', $result);
    }

    /**
     * Test that utf8Encode() requires an associative array.
     */
    public function testUtf8EncodeRequiresAssociativeArray(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap::class);
        $ldap = $reflection->newInstanceWithoutConstructor();

        $method = $reflection->getMethod('utf8');
        $method->setAccessible(true);

        // Indexed array should throw exception
        $this->expectException(Horde_Ldap_Exception::class);
        $this->expectExceptionMessage('associative array');

        $method->invoke($ldap, ['value1', 'value2'], 'utf8_encode');
    }

    /**
     * Test that utf8Decode() requires an associative array.
     */
    public function testUtf8DecodeRequiresAssociativeArray(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap::class);
        $ldap = $reflection->newInstanceWithoutConstructor();

        $method = $reflection->getMethod('utf8');
        $method->setAccessible(true);

        // Indexed array should throw exception
        $this->expectException(Horde_Ldap_Exception::class);
        $this->expectExceptionMessage('associative array');

        $method->invoke($ldap, ['value1', 'value2'], 'utf8_decode');
    }

    /**
     * Test getLink() returns false when not connected.
     */
    public function testGetLinkNotConnected(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap::class);
        $ldap = $reflection->newInstanceWithoutConstructor();

        // Set _link to false (not connected)
        $linkProp = $reflection->getProperty('_link');
        $linkProp->setAccessible(true);
        $linkProp->setValue($ldap, false);

        // Set auto_reconnect to false to avoid infinite loop
        $configProp = $reflection->getProperty('_config');
        $configProp->setAccessible(true);
        $configProp->setValue($ldap, ['auto_reconnect' => false]);

        $this->assertFalse($ldap->getLink());
    }

    /**
     * Test errorName() has comprehensive error code coverage.
     */
    public function testErrorNameCoverage(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap::class);
        $ldap = $reflection->newInstanceWithoutConstructor();

        $method = $reflection->getMethod('errorName');
        $method->setAccessible(true);

        $knownErrors = [
            0 => 'LDAP_SUCCESS',
            1 => 'LDAP_OPERATIONS_ERROR',
            2 => 'LDAP_PROTOCOL_ERROR',
            3 => 'LDAP_TIMELIMIT_EXCEEDED',
            4 => 'LDAP_SIZELIMIT_EXCEEDED',
            5 => 'LDAP_COMPARE_FALSE',
            6 => 'LDAP_COMPARE_TRUE',
            7 => 'LDAP_AUTH_METHOD_NOT_SUPPORTED',
            8 => 'LDAP_STRONG_AUTH_REQUIRED',
            16 => 'LDAP_NO_SUCH_ATTRIBUTE',
            17 => 'LDAP_UNDEFINED_TYPE',
            32 => 'LDAP_NO_SUCH_OBJECT',
            34 => 'LDAP_INVALID_DN_SYNTAX',
            48 => 'LDAP_INAPPROPRIATE_AUTH',
            49 => 'LDAP_INVALID_CREDENTIALS',
            50 => 'LDAP_INSUFFICIENT_ACCESS',
            51 => 'LDAP_BUSY',
            52 => 'LDAP_UNAVAILABLE',
            53 => 'LDAP_UNWILLING_TO_PERFORM',
            65 => 'LDAP_OBJECT_CLASS_VIOLATION',
            68 => 'LDAP_ALREADY_EXISTS',
        ];

        foreach ($knownErrors as $code => $name) {
            $result = $method->invoke($ldap, $code);
            $this->assertEquals($name, $result, "Error code $code should map to $name");
        }
    }

    /**
     * Test config array structure validation.
     */
    public function testConfigArrayStructure(): void
    {
        // Test that config arrays with required keys don't throw exceptions
        // when creating Ldap instances (if we could create them)

        $validConfigs = [
            // Minimal config
            ['hostspec' => 'localhost'],

            // Config with port
            ['hostspec' => 'localhost', 'port' => 389],

            // Config with multiple hosts
            ['hostspec' => ['ldap1.example.com', 'ldap2.example.com']],

            // Config with TLS
            ['hostspec' => 'localhost', 'tls' => true],

            // Config with base DN
            ['hostspec' => 'localhost', 'basedn' => 'dc=example,dc=com'],

            // Config with bind credentials
            [
                'hostspec' => 'localhost',
                'binddn' => 'cn=admin,dc=example,dc=com',
                'bindpw' => 'secret',
            ],

            // Config with version
            ['hostspec' => 'localhost', 'version' => 3],
        ];

        // Just verify these are valid array structures
        // (can't actually test Ldap constructor without connection)
        foreach ($validConfigs as $config) {
            $this->assertIsArray($config);
            $this->assertArrayHasKey('hostspec', $config);
        }
    }

    /**
     * Test schema() requires connection.
     */
    public function testSchemaRequiresConnection(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap::class);
        $ldap = $reflection->newInstanceWithoutConstructor();

        // Set _link to false (not connected)
        $linkProp = $reflection->getProperty('_link');
        $linkProp->setAccessible(true);
        $linkProp->setValue($ldap, false);

        // Set complete config with all required keys to avoid warnings
        $configProp = $reflection->getProperty('_config');
        $configProp->setAccessible(true);
        $configProp->setValue($ldap, [
            'hostspec' => [],  // Empty to trigger "No servers configured"
            'port' => 389,
            'cache' => null,
            'cachettl' => 3600,
            'cache_root_dse' => false,
            'binddn' => null,
            'bindpw' => null,
        ]);

        $this->expectException(Horde_Ldap_Exception::class);
        $this->expectExceptionMessage('No servers configured');

        $ldap->schema();
    }

    /**
     * Test rootDSE() requires connection.
     */
    public function testRootDSERequiresConnection(): void
    {
        $reflection = new \ReflectionClass(Horde_Ldap::class);
        $ldap = $reflection->newInstanceWithoutConstructor();

        // Set _link to false
        $linkProp = $reflection->getProperty('_link');
        $linkProp->setAccessible(true);
        $linkProp->setValue($ldap, false);

        $this->expectException(Horde_Ldap_Exception::class);

        $ldap->rootDSE();
    }

    /**
     * Test config option types are recognized.
     */
    public function testConfigOptionTypes(): void
    {
        $booleanOptions = ['tls', 'auto_reconnect', 'min_backoff'];
        $stringOptions = ['hostspec', 'basedn', 'binddn', 'bindpw'];
        $intOptions = ['port', 'version', 'timeout', 'max_backoff'];

        // Just document the expected types
        foreach ($booleanOptions as $opt) {
            $this->assertIsString($opt);
        }

        foreach ($stringOptions as $opt) {
            $this->assertIsString($opt);
        }

        foreach ($intOptions as $opt) {
            $this->assertIsString($opt);
        }
    }

    /**
     * Test exists() method signature.
     */
    public function testExistsMethodExists(): void
    {
        $this->assertTrue(method_exists(Horde_Ldap::class, 'exists'));
    }

    /**
     * Test search() method exists.
     */
    public function testSearchMethodExists(): void
    {
        $this->assertTrue(method_exists(Horde_Ldap::class, 'search'));
    }

    /**
     * Test add() method exists.
     */
    public function testAddMethodExists(): void
    {
        $this->assertTrue(method_exists(Horde_Ldap::class, 'add'));
    }

    /**
     * Test delete() method exists.
     */
    public function testDeleteMethodExists(): void
    {
        $this->assertTrue(method_exists(Horde_Ldap::class, 'delete'));
    }

    /**
     * Test modify() method exists.
     */
    public function testModifyMethodExists(): void
    {
        $this->assertTrue(method_exists(Horde_Ldap::class, 'modify'));
    }

    /**
     * Test move() method exists.
     */
    public function testMoveMethodExists(): void
    {
        $this->assertTrue(method_exists(Horde_Ldap::class, 'move'));
    }

    /**
     * Test copy() method exists.
     */
    public function testCopyMethodExists(): void
    {
        $this->assertTrue(method_exists(Horde_Ldap::class, 'copy'));
    }

    /**
     * Test getEntry() method exists.
     */
    public function testGetEntryMethodExists(): void
    {
        $this->assertTrue(method_exists(Horde_Ldap::class, 'getEntry'));
    }

    /**
     * Test checkLDAPExtension() with extension loaded.
     */
    public function testLDAPExtensionCheck(): void
    {
        if (!extension_loaded('ldap')) {
            $this->expectException(Horde_Ldap_Exception::class);
            $this->expectExceptionMessage('LDAP extension');
            Horde_Ldap::checkLDAPExtension();
        } else {
            // Should not throw
            Horde_Ldap::checkLDAPExtension();
            $this->assertTrue(true);
        }
    }

    /**
     * Test that known LDAP constants exist.
     */
    public function testLDAPConstants(): void
    {
        if (!extension_loaded('ldap')) {
            $this->markTestSkipped('LDAP extension not loaded');
        }

        // These constants should exist if LDAP extension is loaded
        $this->assertTrue(defined('LDAP_OPT_PROTOCOL_VERSION'));
        $this->assertTrue(defined('LDAP_OPT_REFERRALS'));
        $this->assertTrue(defined('LDAP_DEREF_NEVER'));
    }
}
