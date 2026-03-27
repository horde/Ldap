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

use Horde_Ldap_Entry;
use Horde_Ldap_Exception;
use Horde_Ldap_Ldif;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Horde_Ldap_Ldif::class)]
class LdifTest extends TestCase
{
    /**
     * Default configuration for tests.
     *
     * The config is bound to the ldif test file
     * tests/Fixtures/unsorted_w50.ldif, so don't change or tests will fail.
     */
    protected array $defaultConfig = [
        'encode'  => 'base64',
        'wrap'    => 50,
        'change'  => 0,
        'sort'    => 0,
        'version' => 1,
    ];

    /**
     * Test entries data.
     *
     * Please do not just modify these values, they are closely related to the
     * LDIF test data. Order of attributes matters for unsorted LDIF tests.
     */
    protected array $testData = [
        'cn=test1,ou=example,dc=cno' => [
            'cn'          => 'test1',
            'attr3'       => ['foo', 'bar'],
            'attr1'       => 12345,
            'attr4'       => 'brrrzztt',
            'objectclass' => 'oc1',
            'attr2'       => ['1234', 'baz'],
        ],
        'cn=test blabla,ou=example,dc=cno' => [
            'cn'          => 'test blabla',
            'attr3'       => ['foo', 'bar'],
            'attr1'       => 12345,
            'attr4'       => 'blablaöäü',
            'objectclass' => 'oc2',
            'attr2'       => ['1234', 'baz'],
            'verylong'    => 'fhu08rhvt7b478vt5hv78h45nfgt45h78t34hhhhhhhhhv5bg8h6ttttttttt3489t57nhvgh4788trhg8999vnhtgthgui65hgb5789thvngwr789cghm738',
        ],
        'cn=test öäü,ou=example,dc=cno' => [
            'cn'          => 'test öäü',
            'attr3'       => ['foo', 'bar'],
            'attr1'       => 12345,
            'attr4'       => 'blablaöäü',
            'objectclass' => 'oc3',
            'attr2'       => ['1234', 'baz'],
            'attr5'       => 'endspace ',
            'attr6'       => ':badinitchar',
        ],
        ':cn=endspace,dc=cno ' => [
            'cn'          => 'endspace',
        ],
    ];

    /**
     * Test file written to.
     */
    protected string $outfile = 'test.out.ldif';

    /**
     * Test entries.
     *
     * They will be created in setUp()
     */
    protected array $testEntries = [];

    /**
     * Opens an outfile and ensures correct permissions.
     */
    public function setUp(): void
    {
        // Initialize test entries.
        $this->testEntries = [];
        foreach ($this->testData as $dn => $attrs) {
            $entry = Horde_Ldap_Entry::createFresh($dn, $attrs);
            $this->assertInstanceOf(Horde_Ldap_Entry::class, $entry);
            $this->testEntries[] = $entry;
        }

        // Create outfile if not exists and enforce proper access rights.
        if (!file_exists($this->outfile)) {
            if (!touch($this->outfile)) {
                $this->markTestSkipped('Unable to create ' . $this->outfile);
            }
        }
        if (!chmod($this->outfile, 0o644)) {
            $this->markTestSkipped('Unable to chmod(0644) ' . $this->outfile);
        }
    }

    /**
     * Removes the outfile.
     */
    public function tearDown(): void
    {
        @unlink($this->outfile);
    }

    /**
     * Construction tests.
     *
     * Construct LDIF object and see if we can get a handle.
     */
    public function testConstruction(): void
    {
        $supportedModes = ['r', 'w', 'a'];
        $plus           = ['', '+'];

        // Test all open modes, all of them should return a correct handle.
        foreach ($supportedModes as $mode) {
            foreach ($plus as $p) {
                $ldif = new Horde_Ldap_Ldif($this->outfile, $mode, $this->defaultConfig);
                $this->assertTrue(is_resource($ldif->handle()));
            }
        }
    }

    /**
     * Test illegal option passing.
     */
    public function testConstructionInvalidOptions(): void
    {
        $this->expectException(Horde_Ldap_Exception::class);
        $ldif = new Horde_Ldap_Ldif($this->outfile, 'r', ['somebad' => 'option']);
    }

    /**
     * Test passing custom handle.
     */
    public function testConstructionCustomHandle(): void
    {
        $handle = fopen($this->outfile, 'r');
        $ldif = new Horde_Ldap_Ldif($handle, 'r', $this->defaultConfig);
        $this->assertTrue(is_resource($ldif->handle()));
    }

    /**
     * Test invalid file mode.
     */
    public function testConstructionInvalidMode(): void
    {
        $this->expectException(Horde_Ldap_Exception::class);
        $ldif = new Horde_Ldap_Ldif($this->outfile, 'y', $this->defaultConfig);
    }

    /**
     * Test non-existent file for reading.
     */
    public function testConstructionNonExistentFileRead(): void
    {
        $this->expectException(Horde_Ldap_Exception::class);
        $ldif = new Horde_Ldap_Ldif('some/nonexistent/file_for_net_ldap_ldif', 'r', $this->defaultConfig);
    }

    /**
     * Test writing to non-existent file.
     */
    public function testConstructionNonExistentFileWrite(): void
    {
        $ldif = new Horde_Ldap_Ldif('testfile_for_net_ldap_ldif', 'w', $this->defaultConfig);
        $this->assertTrue(is_resource($ldif->handle()));
        @unlink('testfile_for_net_ldap_ldif');
    }

    /**
     * Test writing to non-existent path.
     */
    public function testConstructionNonExistentPath(): void
    {
        $this->expectException(Horde_Ldap_Exception::class);
        $ldif = new Horde_Ldap_Ldif('some/nonexistent/file_for_net_ldap_ldif', 'w', $this->defaultConfig);
    }

    /**
     * Test writing to existing file without permission.
     */
    public function testConstructionNoWritePermission(): void
    {
        // chmod() should succeed since we test that in setUp().
        if (chmod($this->outfile, 0o444)) {
            $this->expectException(Horde_Ldap_Exception::class);
            $ldif = new Horde_Ldap_Ldif($this->outfile, 'w', $this->defaultConfig);
        } else {
            $this->markTestSkipped('Could not chmod ' . $this->outfile . ', write test without permission skipped');
        }
    }

    /**
     * Tests if entries from an LDIF file are correctly constructed.
     */
    public function testReadEntry(): void
    {
        /* UNIX line endings. */
        $ldif = new Horde_Ldap_Ldif(dirname(__DIR__) . '/Fixtures/unsorted_w50.ldif', 'r', $this->defaultConfig);
        $this->assertTrue(is_resource($ldif->handle()));

        $entries = [];
        do {
            $entry = $ldif->readEntry();
            $this->assertInstanceOf(Horde_Ldap_Entry::class, $entry);
            $entries[] = $entry;
        } while (!$ldif->eof());

        $this->compareEntries($this->testEntries, $entries);

        /* Windows line endings. */
        $ldif = new Horde_Ldap_Ldif(dirname(__DIR__) . '/Fixtures/unsorted_w50_WIN.ldif', 'r', $this->defaultConfig);
        $this->assertTrue(is_resource($ldif->handle()));

        $entries = [];
        do {
            $entry = $ldif->readEntry();
            $this->assertInstanceOf(Horde_Ldap_Entry::class, $entry);
            $entries[] = $entry;
        } while (!$ldif->eof());

        $this->compareEntries($this->testEntries, $entries);
    }

    /**
     * Tests if entries are correctly written.
     *
     * This tests converting entries to LDIF lines, wrapping, encoding, etc.
     */
    public function testWriteEntry(): void
    {
        $testconf = $this->defaultConfig;

        /* Test wrapped operation. */
        $testconf['wrap'] = 50;
        $testconf['sort'] = 0;
        $expected = array_map([$this, 'lineEnd'], file(dirname(__DIR__) . '/Fixtures/unsorted_w50.ldif'));

        // Strip 4 starting lines because of comments in the file header.
        array_splice($expected, 0, 4);

        // Write LDIF.
        $ldif = new Horde_Ldap_Ldif($this->outfile, 'w', $testconf);
        $this->assertTrue(is_resource($ldif->handle()));
        $ldif->writeEntry($this->testEntries);
        $ldif->done();

        // Compare files.
        $this->assertEquals($expected, file($this->outfile));

        $testconf['wrap'] = 30;
        $testconf['sort'] = 0;
        $expected = array_map([$this, 'lineEnd'], file(dirname(__DIR__) . '/Fixtures/unsorted_w30.ldif'));

        // Strip 4 starting lines because of comments in the file header.
        array_splice($expected, 0, 4);

        // Write LDIF.
        $ldif = new Horde_Ldap_Ldif($this->outfile, 'w', $testconf);
        $this->assertTrue(is_resource($ldif->handle()));
        $ldif->writeEntry($this->testEntries);
        $ldif->done();

        // Compare files.
        $this->assertEquals($expected, file($this->outfile));

        /* Test unwrapped operation. */
        $testconf['wrap'] = 40;
        $testconf['sort'] = 1;
        $expected = array_map([$this, 'lineEnd'], file(dirname(__DIR__) . '/Fixtures/sorted_w40.ldif'));

        // Strip 4 starting lines because of comments in the file header.
        array_splice($expected, 0, 4);

        // Write LDIF.
        $ldif = new Horde_Ldap_Ldif($this->outfile, 'w', $testconf);
        $this->assertTrue(is_resource($ldif->handle()));
        $ldif->writeEntry($this->testEntries);
        $ldif->done();

        // Compare files.
        $this->assertEquals($expected, file($this->outfile));

        $testconf['wrap'] = 50;
        $testconf['sort'] = 1;
        $expected = array_map([$this, 'lineEnd'], file(dirname(__DIR__) . '/Fixtures/sorted_w50.ldif'));

        // Strip 4 starting lines because of comments in the file header.
        array_splice($expected, 0, 4);

        // Write LDIF.
        $ldif = new Horde_Ldap_Ldif($this->outfile, 'w', $testconf);
        $this->assertTrue(is_resource($ldif->handle()));
        $ldif->writeEntry($this->testEntries);
        $ldif->done();

        // Compare files.
        $this->assertEquals($expected, file($this->outfile));

        /* Test raw option. */
        $testconf['wrap'] = 50;
        $testconf['sort'] = 1;
        $testconf['raw']  = '/attr6/';
        $expected = array_map([$this, 'lineEnd'], file(dirname(__DIR__) . '/Fixtures/sorted_w50.ldif'));
        // Strip 4 starting lines because of comments in the file header.
        array_splice($expected, 0, 4);

        // Write LDIF.
        $ldif = new Horde_Ldap_Ldif($this->outfile, 'w', $testconf);
        $this->assertTrue(is_resource($ldif->handle()));
        $ldif->writeEntry($this->testEntries);
        $ldif->done();

        // Compare files, with expected attributes adjusted.
        $this->assertEquals($expected, file($this->outfile));
    }

    /**
     * Test writing with non entry as parameter.
     */
    public function testWriteEntryInvalidParameter(): void
    {
        $ldif = new Horde_Ldap_Ldif($this->outfile, 'w');
        $this->assertTrue(is_resource($ldif->handle()));
        $this->expectException(Horde_Ldap_Exception::class);
        $ldif->writeEntry('malformed_parameter');
    }

    /**
     * Test version writing.
     */
    public function testWriteVersion(): void
    {
        $testconf = $this->defaultConfig;

        $expected = array_map([$this, 'lineEnd'], file(dirname(__DIR__) . '/Fixtures/unsorted_w50.ldif'));

        // Strip 4 starting lines because of comments in the file header.
        array_splice($expected, 0, 4);

        // Strip 1 additional line (the "version: 1" line that should not be
        // written now) and adjust test config.
        array_shift($expected);
        unset($testconf['version']);

        // Write LDIF.
        $ldif = new Horde_Ldap_Ldif($this->outfile, 'w', $testconf);
        $this->assertTrue(is_resource($ldif->handle()));
        $ldif->writeEntry($this->testEntries);
        $ldif->done();

        // Compare files.
        $this->assertEquals($expected, file($this->outfile));
    }

    /**
     * Round trip test: Read LDIF, parse to entries, write that to LDIF and
     * compare both files.
     */
    public function testReadWriteRead(): void
    {
        $ldif = new Horde_Ldap_Ldif(dirname(__DIR__) . '/Fixtures/unsorted_w50.ldif', 'r', $this->defaultConfig);
        $this->assertTrue(is_resource($ldif->handle()));

        // Read LDIF.
        $entries = [];
        do {
            $entry = $ldif->readEntry();
            $this->assertInstanceOf(Horde_Ldap_Entry::class, $entry);
            $entries[] = $entry;
        } while (!$ldif->eof());
        $ldif->done();

        // Write LDIF.
        $ldif = new Horde_Ldap_Ldif($this->outfile, 'w', $this->defaultConfig);
        $this->assertTrue(is_resource($ldif->handle()));
        $ldif->writeEntry($entries);
        $ldif->done();

        // Compare files.
        $expected = array_map([$this, 'lineEnd'], file(dirname(__DIR__) . '/Fixtures/unsorted_w50.ldif'));

        // Strip 4 starting lines because of comments in the file header.
        array_splice($expected, 0, 4);

        $this->assertEquals($expected, file($this->outfile));
    }

    /**
     * Tests if entry changes are correctly written.
     */
    public function testWriteEntryChanges(): void
    {
        $testentries = $this->testEntries;
        $testentries[] = Horde_Ldap_Entry::createFresh('cn=foo,ou=example,dc=cno', ['cn' => 'foo']);
        $testentries[] = Horde_Ldap_Entry::createFresh('cn=footest,ou=example,dc=cno', ['cn' => 'foo']);

        $testconf = $this->defaultConfig;
        $testconf['change'] = 1;

        /* No changes should produce empty file. */
        $ldif = new Horde_Ldap_Ldif($this->outfile, 'w', $testconf);
        $this->assertTrue(is_resource($ldif->handle()));
        $ldif->writeEntry($testentries);
        $ldif->done();
        $this->assertEquals([], file($this->outfile));

        /* Changes test. */
        // Prepare some changes.
        $testentries[0]->delete('attr1');
        $testentries[0]->delete(['attr2' => 'baz']);
        $testentries[0]->delete(['attr4', 'attr3' => 'bar']);

        // Prepare some replaces and adds.
        $testentries[2]->replace(['attr1' => 'newvaluefor1']);
        $testentries[2]->replace(['attr2' => ['newvalue1for2', 'newvalue2for2']]);
        $testentries[2]->replace(['attr3' => '']);
        $testentries[2]->replace(['newattr' => 'foo']);

        // Delete whole entry.
        $testentries[3]->delete();

        // Rename and move.
        $testentries[4]->dn('cn=Bar,ou=example,dc=cno');
        $testentries[5]->dn('cn=foobartest,ou=newexample,dc=cno');

        // Carry out write.
        $ldif = new Horde_Ldap_Ldif($this->outfile, 'w', $testconf);
        $this->assertTrue(is_resource($ldif->handle()));
        $ldif->writeEntry($testentries);
        $ldif->done();

        // Compare results.
        $expected = array_map([$this, 'lineEnd'], file(dirname(__DIR__) . '/Fixtures/changes.ldif'));

        // Strip 4 starting lines because of comments in the file header.
        array_splice($expected, 0, 4);

        $this->assertEquals($expected, file($this->outfile));
    }

    /**
     * Test error for line number reporting.
     */
    public function testErrorLineNumberReporting(): void
    {
        $ldif = new Horde_Ldap_Ldif(dirname(__DIR__) . '/Fixtures/malformed_syntax.ldif', 'r', $this->defaultConfig);
        $this->expectException(Horde_Ldap_Exception::class);
        do {
            $entry = $ldif->readEntry();
        } while (!$ldif->eof());
    }

    /**
     * Test error for non-existing path.
     */
    public function testErrorNonExistingPath(): void
    {
        $this->expectException(Horde_Ldap_Exception::class);
        $ldif = new Horde_Ldap_Ldif(__DIR__ . '/some_not_existing/path/for/net_ldap_ldif', 'r', $this->defaultConfig);
    }

    /**
     * Tests currentLines() and nextLines().
     *
     * This should always return the same lines unless forced.
     */
    public function testLineMethods(): void
    {
        $ldif = new Horde_Ldap_Ldif(dirname(__DIR__) . '/Fixtures/unsorted_w50.ldif', 'r', $this->defaultConfig);
        $this->assertEquals([], $ldif->currentLines(), 'Horde_Ldap_Ldif initialization error!');

        // Read first lines.
        $lines = $ldif->nextLines();

        // Read the first lines several times and test.
        for ($i = 0; $i <= 10; $i++) {
            $rLines = $ldif->nextLines();
            $this->assertEquals($lines, $rLines);
        }

        // Now force to iterate and see if the content changes.
        $rLines = $ldif->nextLines(true);
        $this->assertNotEquals($lines, $rLines);

        // It could be confusing to some people, but calling currentEntry()
        // would not work now, like the description of the method says.
        $noEntry = $ldif->currentLines();
        $this->assertEquals([], $noEntry);
    }

    /**
     * Tests currentEntry(). This should always return the same object.
     */
    public function testCurrentEntry(): void
    {
        $ldif = new Horde_Ldap_Ldif(dirname(__DIR__) . '/Fixtures/unsorted_w50.ldif', 'r', $this->defaultConfig);

        // Read first entry.
        $entry = $ldif->readEntry();

        // Test if currentEntry remains the first one.
        for ($i = 0; $i <= 10; $i++) {
            $e = $ldif->currentEntry();
            $this->assertEquals($entry, $e);
        }
    }

    /**
     * Compares two Horde_Ldap_Entries.
     *
     * This helper function compares two entries (or array of entries) and
     * checks if they are equal. They are equal if all DNs from the first crowd
     * exist in the second AND each attribute is present and equal at the
     * respective entry. The search is case sensitive.
     */
    protected function compareEntries(array|Horde_Ldap_Entry $entry1, array|Horde_Ldap_Entry $entry2): bool
    {
        if (!is_array($entry1)) {
            $entry1 = [$entry1];
        }
        if (!is_array($entry2)) {
            $entry2 = [$entry2];
        }

        $entriesData1 = $entriesData2  = [];

        // Step 1: extract and sort data.
        foreach ($entry1 as $e) {
            $values = $e->getValues();
            foreach ($values as $attrName => $attrValues) {
                if (!is_array($attrValues)) {
                    $attrValues = [$attrValues];
                }
                $values[$attrName] = $attrValues;
            }
            $entriesData1[$e->dn()] = $values;
        }
        foreach ($entry2 as $e) {
            $values = $e->getValues();
            foreach ($values as $attrName => $attrValues) {
                if (!is_array($attrValues)) {
                    $attrValues = [$attrValues];
                }
                $values[$attrName] = $attrValues;
            }
            $entriesData2[$e->dn()] = $values;
        }

        // Step 2: compare DNs (entries).
        $this->assertEquals(array_keys($entriesData1), array_keys($entriesData2), 'Entries DNs not equal! (missing entry or wrong DN)');

        // Step 3: look for attribute existence and compare values.
        foreach ($entriesData1 as $dn => $attributes) {
            $this->assertEquals($entriesData1[$dn], $entriesData2[$dn], 'Entries ' . $dn . ' attributes are not equal');
            foreach ($attributes as $attrName => $attrValues) {
                $this->assertEquals(0, count(array_diff($entriesData1[$dn][$attrName], $entriesData2[$dn][$attrName])), 'Entries ' . $dn . ' attribute ' . $attrName . ' values are not equal');
            }
        }

        return true;
    }

    /**
     * Create line endings for current OS.
     *
     * This is neccessary to make write tests platform indendent.
     */
    protected function lineEnd(string $line): string
    {
        return rtrim($line) . PHP_EOL;
    }
}
