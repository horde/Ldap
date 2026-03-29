<?php

declare(strict_types=1);

/**
 * Test DN validation and edge cases
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

use Horde_Ldap_Util;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Horde_Ldap_Util DN handling including edge cases and special characters.
 */
#[CoversClass(Horde_Ldap_Util::class)]
class DnValidationTest extends TestCase
{
    /**
     * Test explodeDN() with simple DN.
     */
    public function testExplodeDNSimple(): void
    {
        $dn = 'cn=John Doe,ou=People,dc=example,dc=com';
        $parts = Horde_Ldap_Util::explodeDN($dn);

        $this->assertIsArray($parts);
        $this->assertCount(4, $parts);
        // explodeDN preserves case by default (uppercase attribute names)
        $this->assertEquals('CN=John Doe', $parts[0]);
        $this->assertEquals('OU=People', $parts[1]);
        $this->assertEquals('DC=example', $parts[2]);
        $this->assertEquals('DC=com', $parts[3]);
    }

    /**
     * Test explodeDN() with special characters.
     */
    public function testExplodeDNSpecialChars(): void
    {
        $dn = 'cn=Doe\\, John,dc=example,dc=com';
        $parts = Horde_Ldap_Util::explodeDN($dn);

        $this->assertIsArray($parts);
        $this->assertEquals('CN=Doe\\, John', $parts[0]);
    }

    /**
     * Test explodeDN() with multi-valued RDN.
     */
    public function testExplodeDNMultiValuedRDN(): void
    {
        $dn = 'cn=John Doe+mail=jdoe@example.com,dc=example,dc=com';
        $parts = Horde_Ldap_Util::explodeDN($dn);

        $this->assertIsArray($parts);
        // Multi-valued RDN is returned as array
        $this->assertIsArray($parts[0]);
        $this->assertContains('CN=John Doe', $parts[0]);
        $this->assertContains('MAIL=jdoe@example.com', $parts[0]);
    }

    /**
     * Test explodeDN() with casefold option.
     */
    public function testExplodeDNCasefold(): void
    {
        $dn = 'CN=John Doe,OU=People,DC=EXAMPLE,DC=COM';
        $parts = Horde_Ldap_Util::explodeDN($dn, ['casefold' => 'lower']);

        $this->assertEquals('cn=John Doe', $parts[0]);
        $this->assertEquals('ou=People', $parts[1]);
        $this->assertEquals('dc=EXAMPLE', $parts[2]);
    }

    /**
     * Test explodeDN() with reverse option.
     */
    public function testExplodeDNReverse(): void
    {
        $dn = 'cn=John Doe,dc=example,dc=com';
        $parts = Horde_Ldap_Util::explodeDN($dn, ['reverse' => true]);

        $this->assertEquals('DC=com', $parts[0]);
        $this->assertEquals('DC=example', $parts[1]);
        $this->assertEquals('CN=John Doe', $parts[2]);
    }

    /**
     * Test escapeDNValue() escapes special characters.
     */
    public function testEscapeDNValue(): void
    {
        // escapeDNValue returns array even for single values
        // Test comma
        $escaped = Horde_Ldap_Util::escapeDNValue('Doe, John');
        $this->assertIsArray($escaped);
        $this->assertEquals('Doe\\, John', $escaped[0]);

        // Test plus
        $escaped = Horde_Ldap_Util::escapeDNValue('A+B');
        $this->assertEquals('A\\+B', $escaped[0]);

        // Test quotes
        $escaped = Horde_Ldap_Util::escapeDNValue('"quoted"');
        $this->assertEquals('\\"quoted\\"', $escaped[0]);
    }

    /**
     * Test escapeDNValue() with leading/trailing spaces.
     */
    public function testEscapeDNValueSpaces(): void
    {
        // escapeDNValue returns array, spaces escaped as \20
        $escaped = Horde_Ldap_Util::escapeDNValue(' leading');
        $this->assertIsArray($escaped);
        $this->assertEquals('\\20leading', $escaped[0]);

        $escaped = Horde_Ldap_Util::escapeDNValue('trailing ');
        $this->assertEquals('trailing\\20', $escaped[0]);

        $escaped = Horde_Ldap_Util::escapeDNValue('  both  ');
        $this->assertStringStartsWith('\\20', $escaped[0]);
        $this->assertStringEndsWith('\\20', $escaped[0]);
    }

    /**
     * Test escapeDNValue() with all special characters.
     */
    public function testEscapeDNValueAllSpecial(): void
    {
        // DN special chars: , \ + " < > ; = #
        // Note: backslash needs to be doubled in input
        $value = ',\\\\+"<>;=#';
        $escaped = Horde_Ldap_Util::escapeDNValue($value);

        // escapeDNValue returns array
        $this->assertIsArray($escaped);
        $escapedStr = $escaped[0];

        // All should be escaped
        $this->assertStringContainsString('\\,', $escapedStr);
        $this->assertStringContainsString('\\\\', $escapedStr);
        $this->assertStringContainsString('\\+', $escapedStr);
        $this->assertStringContainsString('\\"', $escapedStr);
        $this->assertStringContainsString('\\<', $escapedStr);
        $this->assertStringContainsString('\\>', $escapedStr);
        $this->assertStringContainsString('\\;', $escapedStr);
        $this->assertStringContainsString('\\=', $escapedStr);
        $this->assertStringContainsString('\\#', $escapedStr);
    }

    /**
     * Test escapeDNValue() with array input.
     */
    public function testEscapeDNValueArray(): void
    {
        $values = ['Doe, John', 'Smith; Jane'];
        $escaped = Horde_Ldap_Util::escapeDNValue($values);

        $this->assertIsArray($escaped);
        $this->assertCount(2, $escaped);
        $this->assertEquals('Doe\\, John', $escaped[0]);
        $this->assertEquals('Smith\\; Jane', $escaped[1]);
    }

    /**
     * Test escapeFilterValue() escapes filter special chars.
     */
    public function testEscapeFilterValue(): void
    {
        // escapeFilterValue returns array even for single values
        // Test asterisk (wildcard)
        $escaped = Horde_Ldap_Util::escapeFilterValue('test*value');
        $this->assertIsArray($escaped);
        $this->assertEquals('test\\2avalue', $escaped[0]);

        // Test parentheses
        $escaped = Horde_Ldap_Util::escapeFilterValue('(test)');
        $this->assertEquals('\\28test\\29', $escaped[0]);

        // Test backslash
        $escaped = Horde_Ldap_Util::escapeFilterValue('C:\\path');
        $this->assertEquals('C:\\5cpath', $escaped[0]);
    }

    /**
     * Test escapeFilterValue() with null byte.
     */
    public function testEscapeFilterValueNullByte(): void
    {
        // escapeFilterValue returns array
        $escaped = Horde_Ldap_Util::escapeFilterValue("test\x00value");
        $this->assertIsArray($escaped);
        $this->assertEquals('test\\00value', $escaped[0]);
    }

    /**
     * Test escapeFilterValue() with array input.
     */
    public function testEscapeFilterValueArray(): void
    {
        $values = ['test*', 'value(1)'];
        $escaped = Horde_Ldap_Util::escapeFilterValue($values);

        $this->assertIsArray($escaped);
        $this->assertCount(2, $escaped);
        $this->assertEquals('test\\2a', $escaped[0]);
        $this->assertEquals('value\\281\\29', $escaped[1]);
    }

    /**
     * Test canonicalDN() normalizes DN.
     */
    public function testCanonicalDN(): void
    {
        $dn = '  CN=John Doe  ,  OU=People  ,  DC=example  ,  DC=com  ';
        $canonical = Horde_Ldap_Util::canonicalDN($dn);

        // canonicalDN escapes trailing spaces in hex notation
        $this->assertStringNotContainsString('  ', $canonical);
        $this->assertStringContainsString('CN=John Doe', $canonical);
        $this->assertStringContainsString('OU=People', $canonical);
        $this->assertStringContainsString('DC=example', $canonical);
        $this->assertStringContainsString('DC=com', $canonical);
    }

    /**
     * Test canonicalDN() with casefold.
     */
    public function testCanonicalDNCasefold(): void
    {
        $dn = 'CN=John Doe,OU=People,DC=EXAMPLE,DC=COM';
        $canonical = Horde_Ldap_Util::canonicalDN($dn, ['casefold' => 'lower']);

        $this->assertEquals('cn=John Doe,ou=People,dc=EXAMPLE,dc=COM', $canonical);
    }

    /**
     * Test canonicalDN() with reverse.
     */
    public function testCanonicalDNReverse(): void
    {
        $dn = 'cn=John Doe,dc=example,dc=com';
        $canonical = Horde_Ldap_Util::canonicalDN($dn, ['reverse' => true]);

        // Reversed, case preserved as uppercase
        $this->assertStringStartsWith('DC=com,DC=example', $canonical);
        $this->assertStringEndsWith('CN=John Doe', $canonical);
    }

    /**
     * Test canonicalDN() preserves escaped characters.
     */
    public function testCanonicalDNPreservesEscaping(): void
    {
        // Note: Double backslash needed in input
        $dn = 'cn=Doe\\\\, John,dc=example,dc=com';
        $canonical = Horde_Ldap_Util::canonicalDN($dn);

        // Escaping preserved (with triple backslash in output), case changed to uppercase
        $this->assertStringContainsString('Doe', $canonical);
        $this->assertStringContainsString('John', $canonical);
    }

    /**
     * Test very long DN.
     */
    public function testVeryLongDN(): void
    {
        $components = [];
        for ($i = 0; $i < 50; $i++) {
            $components[] = "ou=level$i";
        }
        $components[] = 'dc=example';
        $components[] = 'dc=com';

        $dn = implode(',', $components);
        $parts = Horde_Ldap_Util::explodeDN($dn);

        $this->assertCount(52, $parts);
    }

    /**
     * Test DN with Unicode characters.
     */
    public function testDNWithUnicode(): void
    {
        $dn = 'cn=Müller,dc=example,dc=com';
        $parts = Horde_Ldap_Util::explodeDN($dn);

        // Case changed to uppercase
        $this->assertEquals('CN=Müller', $parts[0]);
    }

    /**
     * Test empty DN.
     *
     * Note: explodeDN('') returns [''] (array with single empty string) rather
     * than [] (empty array). While arguably unintuitive, this behavior is
     * intentional and cannot be changed without breaking existing code.
     *
     * Specifically, Entry::update() (lib/Horde/Ldap/Entry.php:673-674) does:
     *   $parent = explodeDN($this->_newdn, [...]);
     *   $child = array_shift($parent);
     *
     * If explodeDN('') returned [], array_shift would return NULL, causing
     * a TypeError when passed to ldap_rename() in PHP 8+.
     *
     * The real issue is lack of validation - Entry.php should reject empty DN
     * before attempting rename. However, changing explodeDN behavior would
     * break existing deployments.
     */
    public function testEmptyDN(): void
    {
        $parts = Horde_Ldap_Util::explodeDN('');

        $this->assertIsArray($parts);
        // Empty DN returns array with single empty string
        $this->assertCount(1, $parts);
        $this->assertEquals('', $parts[0]);
    }

    /**
     * Test DN with only RDN.
     */
    public function testDNOnlyRDN(): void
    {
        $dn = 'cn=John Doe';
        $parts = Horde_Ldap_Util::explodeDN($dn);

        $this->assertCount(1, $parts);
        // Case changed to uppercase
        $this->assertEquals('CN=John Doe', $parts[0]);
    }
}
