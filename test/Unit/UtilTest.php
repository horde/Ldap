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

use Horde_Ldap;
use Horde_Ldap_Util;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Horde_Ldap_Util::class)]
class UtilTest extends TestCase
{
    /**
     * Test escapeDNValue()
     */
    public function testEscapeDNValue(): void
    {
        $dnval    = '  ' . chr(22) . ' t,e+s"t,\\v<a>l;u#e=!    ';
        $expected = '\20\20\16 t\,e\+s\"t\,\\\\v\<a\>l\;u\#e\=!\20\20\20\20';

        // String call.
        $this->assertEquals(
            [$expected],
            Horde_Ldap_Util::escapeDNValue($dnval)
        );

        // Array call.
        $this->assertEquals(
            [$expected],
            Horde_Ldap_Util::escapeDNValue([$dnval])
        );

        // Multiple arrays.
        $this->assertEquals(
            [$expected, $expected, $expected],
            Horde_Ldap_Util::escapeDNValue([$dnval, $dnval, $dnval])
        );
    }

    /**
     * Test unescapeDNValue()
     */
    public function testUnescapeDNValue(): void
    {
        $dnval    = '\\20\\20\\16\\20t\\,e\\+s \\"t\\,\\\\v\\<a\\>l\\;u\\#e\\=!\\20\\20\\20\\20';
        $expected = '  ' . chr(22) . ' t,e+s "t,\\v<a>l;u#e=!    ';

        // String call.
        $this->assertEquals(
            [$expected],
            Horde_Ldap_Util::unescapeDNValue($dnval)
        );

        // Array call.
        $this->assertEquals(
            [$expected],
            Horde_Ldap_Util::unescapeDNValue([$dnval])
        );

        // Multiple arrays.
        $this->assertEquals(
            [$expected, $expected, $expected],
            Horde_Ldap_Util::unescapeDNValue([$dnval, $dnval, $dnval])
        );
    }

    /**
     * Test escaping of filter values.
     */
    public function testEscapeFilterValue(): void
    {
        $expected  = 't\28e,s\29t\2av\5cal\1eue';
        $filterval = 't(e,s)t*v\\al' . chr(30) . 'ue';

        // String call
        $this->assertEquals(
            [$expected],
            Horde_Ldap_Util::escapeFilterValue($filterval)
        );

        // Array call.
        $this->assertEquals(
            [$expected],
            Horde_Ldap_Util::escapeFilterValue([$filterval])
        );

        // Multiple arrays.
        $this->assertEquals(
            [$expected, $expected, $expected],
            Horde_Ldap_Util::escapeFilterValue([$filterval, $filterval, $filterval])
        );
    }

    /**
     * Test unescaping of filter values.
     */
    public function testUnescapeFilterValue(): void
    {
        $expected  = 't(e,s)t*v\\al' . chr(30) . 'ue';
        $filterval = 't\28e,s\29t\2av\5cal\1eue';

        // String call
        $this->assertEquals(
            [$expected],
            Horde_Ldap_Util::unescapeFilterValue($filterval)
        );

        // Array call.
        $this->assertEquals(
            [$expected],
            Horde_Ldap_Util::unescapeFilterValue([$filterval])
        );

        // Multiple arrays.
        $this->assertEquals(
            [$expected, $expected, $expected],
            Horde_Ldap_Util::unescapeFilterValue([$filterval, $filterval, $filterval])
        );
    }

    /**
     * Test asc2hex32()
     */
    public function testAsc2hex32(): void
    {
        $expected = '\00\01\02\03\04\05\06\07\08\09\0a\0b\0c\0d\0e\0f\10\11\12\13\14\15\16\17\18\19\1a\1b\1c\1d\1e\1f !"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~';
        $str = '';
        for ($i = 0; $i < 127; $i++) {
            $str .= chr($i);
        }
        $this->assertEquals($expected, Horde_Ldap_Util::asc2hex32($str));
    }

    /**
     * Test HEX unescaping
     */
    public function testHex2asc(): void
    {
        $expected = '';
        for ($i = 0; $i < 127; $i++) {
            $expected .= chr($i);
        }
        $str = '\00\01\02\03\04\05\06\07\08\09\0a\0b\0c\0d\0e\0f\10\11\12\13\14\15\16\17\18\19\1a\1b\1c\1d\1e\1f !"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~';
        $this->assertEquals($expected, Horde_Ldap_Util::hex2asc($str));
    }

    /**
     * Tests splitRDNMultivalue()
     *
     * In addition to the above test of the basic split correction, we test
     * here the functionality of multivalued RDNs.
     */
    public function testSplitRDNMultivalue(): void
    {
        // One value.
        $rdn = 'CN=J. Smith';
        $expected = ['CN=J. Smith'];
        $split = Horde_Ldap_Util::splitRDNMultivalue($rdn);
        $this->assertEquals($expected, $split);

        // Two values.
        $rdn = 'OU=Sales+CN=J. Smith';
        $expected = ['OU=Sales', 'CN=J. Smith'];
        $split = Horde_Ldap_Util::splitRDNMultivalue($rdn);
        $this->assertEquals($expected, $split);

        // Several multivals.
        $rdn = 'OU=Sales+CN=J. Smith+L=London+C=England';
        $expected = ['OU=Sales', 'CN=J. Smith', 'L=London', 'C=England'];
        $split = Horde_Ldap_Util::splitRDNMultivalue($rdn);
        $this->assertEquals($expected, $split);

        // Unescaped "+" in value.
        $rdn = 'OU=Sa+les+CN=J. Smith';
        $expected = ['OU=Sa+les', 'CN=J. Smith'];
        $split = Horde_Ldap_Util::splitRDNMultivalue($rdn);
        $this->assertEquals($expected, $split);

        // Unescaped "+" in attr name.
        $rdn = 'O+U=Sales+CN=J. Smith';
        $expected = ['O+U=Sales', 'CN=J. Smith'];
        $split = Horde_Ldap_Util::splitRDNMultivalue($rdn);
        $this->assertEquals($expected, $split);

        // Unescaped "+" in attr name + value.
        $rdn = 'O+U=Sales+CN=J. Sm+ith';
        $expected = ['O+U=Sales', 'CN=J. Sm+ith'];
        $split = Horde_Ldap_Util::splitRDNMultivalue($rdn);
        $this->assertEquals($expected, $split);

        // Unescaped "+" in attribute name, but not first attribute.  This
        // documents a known bug. However, unfortunately we can't know wether
        // the "C+" belongs to value "Sales" or attribute "C+N".  To solve
        // this, we must ask the schema which we do not right now.  The problem
        // is located in _correct_dn_splitting().
        $rdn = 'OU=Sales+C+N=J. Smith';
        // The "C+" is treaten as value of "OU".
        $expected = ['OU=Sales+C', 'N=J. Smith'];
        $split = Horde_Ldap_Util::splitRDNMultivalue($rdn);
        $this->assertEquals($expected, $split);

        // Escaped "+" in attribute name and value.
        $rdn = 'O\+U=Sales+CN=J. Sm\+ith';
        $expected = ['O\+U=Sales', 'CN=J. Sm\+ith'];
        $split = Horde_Ldap_Util::splitRDNMultivalue($rdn);
        $this->assertEquals($expected, $split);
    }

    /**
     * Tests attribute splitting ('foo=bar' => array('foo', 'bar'))
     */
    public function testSplitAttributeString(): void
    {
        $attrStr = 'foo=bar';

        // Properly.
        $expected = ['foo', 'bar'];
        $split = Horde_Ldap_Util::splitAttributeString($attrStr);
        $this->assertEquals($expected, $split);

        // Escaped "=".
        $attrStr = "fo\=o=b\=ar";
        $expected = ['fo\=o', 'b\=ar'];
        $split = Horde_Ldap_Util::splitAttributeString($attrStr);
        $this->assertEquals($expected, $split);

        // Escaped "=" and unescaped = later on.
        $attrStr = "fo\=o=b=ar";
        $expected = ['fo\=o', 'b=ar'];
        $split = Horde_Ldap_Util::splitAttributeString($attrStr);
        $this->assertEquals($expected, $split);
    }

    /**
     * Tests Ldap_explode_dn()
     */
    public function testExplodeDN(): void
    {
        $dn = 'ou=Sales+CN=J. Smith,dc=example,dc=net';
        $expectedCasefoldNone = [
            ['CN=J. Smith', 'ou=Sales'],
            'dc=example',
            'dc=net',
        ];
        $expectedCasefoldUpper = [
            ['CN=J. Smith', 'OU=Sales'],
            'DC=example',
            'DC=net',
        ];
        $expectedCasefoldLower = [
            ['cn=J. Smith', 'ou=Sales'],
            'dc=example',
            'dc=net',
        ];
        $expectedOnlyvalues = [
            ['J. Smith', 'Sales'],
            'example',
            'net',
        ];
        $expectedReverse = array_reverse($expectedCasefoldUpper);


        $dnExplodedCnone = Horde_Ldap_Util::explodeDN($dn, ['casefold' => 'none']);
        $this->assertEquals($expectedCasefoldNone, $dnExplodedCnone, 'Option casefold none failed');

        $dnExplodedCupper = Horde_Ldap_Util::explodeDN($dn, ['casefold' => 'upper']);
        $this->assertEquals($expectedCasefoldUpper, $dnExplodedCupper, 'Option casefold upper failed');

        $dnExplodedClower = Horde_Ldap_Util::explodeDN($dn, ['casefold' => 'lower']);
        $this->assertEquals($expectedCasefoldLower, $dnExplodedClower, 'Option casefold lower failed');

        $dnExplodedOnlyval = Horde_Ldap_Util::explodeDN($dn, ['onlyvalues' => true]);
        $this->assertEquals($expectedOnlyvalues, $dnExplodedOnlyval, 'Option onlyval failed');

        $dnExplodedReverse = Horde_Ldap_Util::explodeDN($dn, ['reverse' => true]);
        $this->assertEquals($expectedReverse, $dnExplodedReverse, 'Option reverse failed');

        $this->assertEquals(
            ['CN=J\\, Smith', 'DC=example', 'DC=net'],
            Horde_Ldap_Util::explodeDN('cn=J\\, Smith,dc=example,dc=net')
        );
    }

    /**
     * Tests if canonicalDN() works.
     *
     * Note: This tests depend on the default options of canonicalDN().
     */
    public function testCanonicalDN(): void
    {
        // Test empty dn (is valid according to RFC).
        $this->assertEquals('', Horde_Ldap_Util::canonicalDN(''));

        // Default options with common DN.
        $testdn   = 'cn=beni,DC=php,c=net';
        $expected = 'CN=beni,DC=php,C=net';
        $this->assertEquals($expected, Horde_Ldap_Util::canonicalDN($testdn));

        // Casefold tests with common DN.
        $expectedUp = 'CN=beni,DC=php,C=net';
        $expectedLo = 'cn=beni,dc=php,c=net';
        $expectedNo = 'cn=beni,DC=php,c=net';
        $this->assertEquals($expectedUp, Horde_Ldap_Util::canonicalDN($testdn, ['casefold' => 'upper']));
        $this->assertEquals($expectedLo, Horde_Ldap_Util::canonicalDN($testdn, ['casefold' => 'lower']));
        $this->assertEquals($expectedNo, Horde_Ldap_Util::canonicalDN($testdn, ['casefold' => 'none']));

        // Reverse.
        $expectedRev = 'C=net,DC=php,CN=beni';
        $this->assertEquals($expectedRev, Horde_Ldap_Util::canonicalDN($testdn, ['reverse' => true]), 'Option reverse failed');

        // DN as arrays.
        $dnIndex = ['cn=beni', 'dc=php', 'c=net'];
        $dnAssoc = ['cn' => 'beni', 'dc' => 'php', 'c' => 'net'];
        $expected = 'CN=beni,DC=php,C=net';
        $this->assertEquals($expected, Horde_Ldap_Util::canonicalDN($dnIndex));
        $this->assertEquals($expected, Horde_Ldap_Util::canonicalDN($dnAssoc));

        // DN with multiple RDN value.
        $testdn       = 'ou=dev+cn=beni,DC=php,c=net';
        $testdnIndex = [['ou=dev', 'cn=beni'], 'DC=php', 'c=net'];
        $testdnAssoc = [['ou' => 'dev', 'cn' => 'beni'], 'DC' => 'php', 'c' => 'net'];
        $expected     = 'CN=beni+OU=dev,DC=php,C=net';
        $this->assertEquals($expected, Horde_Ldap_Util::canonicalDN($testdn));
        $this->assertEquals($expected, Horde_Ldap_Util::canonicalDN($testdnAssoc));
        $this->assertEquals($expected, Horde_Ldap_Util::canonicalDN($expected));

        // Test DN with OID.
        $testdn = 'OID.2.5.4.3=beni,dc=php,c=net';
        $expected = '2.5.4.3=beni,DC=php,C=net';
        $this->assertEquals($expected, Horde_Ldap_Util::canonicalDN($testdn));

        // Test with leading and ending spaces.
        $testdn   = 'cn=  beni  ,DC=php,c=net';
        $expected = 'CN=\20\20beni\20\20,DC=php,C=net';
        $this->assertEquals($expected, Horde_Ldap_Util::canonicalDN($testdn));

        // Test with escaped commas. Doesn't work at the moment because
        // canonicalDN() escapes attribute values, which break if they are
        // already escaped.
        $testdn   = 'cn=beni\\,hi\=ll,DC=php,c=net';
        $expected = 'CN=beni\\,hi\=ll,DC=php,C=net';
        // $this->assertEquals($expected, Horde_Ldap_Util::canonicalDN($testdn));

        // Test with to-be escaped characters in attribute value.
        $specialchars = [
            ',' => '\,',
            '+' => '\+',
            '"' => '\"',
            '\\' => '\\\\',
            '<' => '\<',
            '>' => '\>',
            ';' => '\;',
            '#' => '\#',
            '=' => '\=',
            chr(18) => '\12',
            '/' => '\/',
        ];
        foreach ($specialchars as $char => $escape) {
            $testString = 'CN=be' . $char . 'ni,DC=ph' . $char . 'p,C=net';
            $testIndex  = ['CN=be' . $char . 'ni', 'DC=ph' . $char . 'p', 'C=net'];
            $testAssoc  = ['CN' => 'be' . $char . 'ni', 'DC' => 'ph' . $char . 'p', 'C' => 'net'];
            $expected    = 'CN=be' . $escape . 'ni,DC=ph' . $escape . 'p,C=net';

            $this->assertEquals($expected, Horde_Ldap_Util::canonicalDN($testString), 'String escaping test (' . $char . ') failed');
            $this->assertEquals($expected, Horde_Ldap_Util::canonicalDN($testIndex), 'Indexed array escaping test (' . $char . ') failed');
            $this->assertEquals($expected, Horde_Ldap_Util::canonicalDN($testAssoc), 'Associative array encoding test (' . $char . ') failed');
        }
    }

    /**
     * Test quoteDN() static method.
     *
     * Moved from LdapTest.php - this is a pure unit test of a static utility method.
     */
    public function testQuoteDN(): void
    {
        $this->assertEquals(
            'cn=John Smith,dc=example,dc=com',
            Horde_Ldap::quoteDN([
                ['cn', 'John Smith'],
                ['dc', 'example'],
                ['dc', 'com'],
            ])
        );
        $this->assertEquals(
            'cn=John+sn=Smith+o=Acme Inc.,dc=example,dc=com',
            Horde_Ldap::quoteDN([
                [
                    ['cn', 'John'],
                    ['sn', 'Smith'],
                    ['o', 'Acme Inc.'],
                ],
                ['dc', 'example'],
                ['dc', 'com'],
            ])
        );
        $this->assertEquals(
            'cn=John+sn=Smith+o=Acme Inc.',
            Horde_Ldap::quoteDN([
                [
                    ['cn', 'John'],
                    ['sn', 'Smith'],
                    ['o', 'Acme Inc.'],
                ],
            ])
        );
    }
}
