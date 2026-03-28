<?php

declare(strict_types=1);

/**
 * Test Schema parsing and query methods
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
use Horde_Ldap_Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for Horde_Ldap_Schema parsing and attribute/objectclass queries.
 *
 * Schema provides access to LDAP schema information including object classes,
 * attributes, and their relationships.
 */
#[CoversClass(Horde_Ldap_Schema::class)]
class SchemaTest extends TestCase
{
    /**
     * Create a mock Schema with parsed data.
     */
    private function createMockSchema(array $objectClasses = [], array $attributes = []): Horde_Ldap_Schema
    {
        $reflection = new ReflectionClass(Horde_Ldap_Schema::class);
        $schema = $reflection->newInstanceWithoutConstructor();

        // Set _objectClasses
        if (!empty($objectClasses)) {
            $ocProperty = $reflection->getProperty('_objectClasses');
            $ocProperty->setAccessible(true);
            $ocProperty->setValue($schema, $objectClasses);
        }

        // Set _attributeTypes
        if (!empty($attributes)) {
            $atProperty = $reflection->getProperty('_attributeTypes');
            $atProperty->setAccessible(true);
            $atProperty->setValue($schema, $attributes);
        }

        // Mark as initialized
        $initProperty = $reflection->getProperty('_initialized');
        $initProperty->setAccessible(true);
        $initProperty->setValue($schema, true);

        return $schema;
    }

    /**
     * Test isBinary() requires schema data.
     *
     * Without proper attribute schema entries, isBinary() returns false.
     * This tests the actual behavior.
     */
    public function testIsBinary(): void
    {
        $schema = $this->createMockSchema();

        // Without schema data for attributes, isBinary returns false
        $this->assertFalse($schema->isBinary('jpegPhoto'));
        $this->assertFalse($schema->isBinary('cn'));
    }

    /**
     * Test isBinary() with proper attribute schema.
     */
    public function testIsBinaryWithSchema(): void
    {
        // Create schema with attribute definitions including syntax
        $schema = $this->createMockSchema(
            [],
            [
                'jpegphoto' => [
                    'name' => 'jpegPhoto',
                    'syntax' => Horde_Ldap_Schema::SYNTAX_JPEG,
                ],
                'cn' => [
                    'name' => 'cn',
                    'syntax' => Horde_Ldap_Schema::SYNTAX_DIRECTORY_STRING,
                ],
            ]
        );

        // With proper schema, binary detection works
        $this->assertTrue($schema->isBinary('jpegPhoto'));
        $this->assertFalse($schema->isBinary('cn'));
    }

    /**
     * Test isBinary() is case-insensitive.
     */
    public function testIsBinaryCaseInsensitive(): void
    {
        $schema = $this->createMockSchema(
            [],
            [
                'jpegphoto' => [
                    'name' => 'jpegPhoto',
                    'syntax' => Horde_Ldap_Schema::SYNTAX_JPEG,
                ],
            ]
        );

        $this->assertTrue($schema->isBinary('jpegPhoto'));
        $this->assertTrue($schema->isBinary('JPEGPHOTO'));
        $this->assertTrue($schema->isBinary('JpEgPhOtO'));
    }

    /**
     * Test isBinary() with octet string syntax.
     */
    public function testIsBinaryOctetString(): void
    {
        $schema = $this->createMockSchema(
            [],
            [
                'usercertificate' => [
                    'name' => 'userCertificate',
                    'syntax' => Horde_Ldap_Schema::SYNTAX_OCTET_STRING,
                ],
            ]
        );

        $this->assertTrue($schema->isBinary('userCertificate'));
    }

    /**
     * Test may() returns optional attributes for object class.
     */
    public function testMay(): void
    {
        $schema = $this->createMockSchema([
            'person' => [
                'name' => 'person',
                'may' => ['telephoneNumber', 'seeAlso', 'description'],
            ],
        ]);

        $may = $schema->may('person');

        $this->assertIsArray($may);
        $this->assertContains('telephoneNumber', $may);
        $this->assertContains('seeAlso', $may);
        $this->assertContains('description', $may);
    }

    /**
     * Test may() with non-existent object class returns empty array.
     */
    public function testMayNonExistent(): void
    {
        $schema = $this->createMockSchema([
            'person' => ['name' => 'person', 'may' => []],
        ]);

        // Non-existent object classes return empty array (caught exception)
        $may = $schema->may('nonexistent');

        $this->assertIsArray($may);
        $this->assertEmpty($may);
    }

    /**
     * Test must() returns required attributes for object class.
     */
    public function testMust(): void
    {
        $schema = $this->createMockSchema([
            'person' => [
                'name' => 'person',
                'must' => ['sn', 'cn'],
            ],
        ]);

        $must = $schema->must('person');

        $this->assertIsArray($must);
        $this->assertContains('sn', $must);
        $this->assertContains('cn', $must);
    }

    /**
     * Test must() with checksup includes superclass attributes.
     */
    public function testMustWithSuperclass(): void
    {
        $schema = $this->createMockSchema([
            'top' => [
                'name' => 'top',
                'must' => ['objectClass'],
            ],
            'person' => [
                'name' => 'person',
                'sup' => ['top'],
                'must' => ['sn', 'cn'],
            ],
        ]);

        // Without superclass check
        $must = $schema->must('person', false);
        $this->assertCount(2, $must);

        // With superclass check
        $mustWithSup = $schema->must('person', true);
        $this->assertGreaterThanOrEqual(2, count($mustWithSup));
        $this->assertContains('sn', $mustWithSup);
        $this->assertContains('cn', $mustWithSup);
    }

    /**
     * Test may() with checksup includes superclass attributes.
     */
    public function testMayWithSuperclass(): void
    {
        $schema = $this->createMockSchema([
            'top' => [
                'name' => 'top',
                'may' => [],
            ],
            'person' => [
                'name' => 'person',
                'sup' => ['top'],
                'may' => ['telephoneNumber'],
            ],
            'organizationalperson' => [  // Normalized to lowercase
                'name' => 'organizationalPerson',
                'sup' => ['person'],
                'may' => ['title', 'ou'],
            ],
        ]);

        // Without superclass check
        $may = $schema->may('organizationalperson', false);
        $this->assertContains('title', $may);
        $this->assertContains('ou', $may);

        // With superclass check (includes person's may)
        $mayWithSup = $schema->may('organizationalperson', true);
        $this->assertContains('title', $mayWithSup);
        $this->assertContains('ou', $mayWithSup);
        $this->assertContains('telephoneNumber', $mayWithSup);
    }

    /**
     * Test superclass() returns parent object class array.
     */
    public function testSuperclass(): void
    {
        $schema = $this->createMockSchema([
            'top' => [
                'name' => 'top',
            ],
            'person' => [
                'name' => 'person',
                'sup' => ['top'],
            ],
        ]);

        $sup = $schema->superclass('person');

        // superclass() returns array from 'sup' field
        $this->assertIsArray($sup);
        $this->assertContains('top', $sup);
    }

    /**
     * Test superclass() with no parent returns empty array.
     */
    public function testSuperclassNone(): void
    {
        $schema = $this->createMockSchema([
            'top' => [
                'name' => 'top',
            ],
        ]);

        $sup = $schema->superclass('top');

        // No sup field returns empty array
        $this->assertIsArray($sup);
        $this->assertEmpty($sup);
    }

    /**
     * Test getAll() returns all entries of a type.
     */
    public function testGetAll(): void
    {
        $schema = $this->createMockSchema([
            'top' => ['name' => 'top'],
            'person' => ['name' => 'person'],
            'organizationalPerson' => ['name' => 'organizationalPerson'],
        ]);

        $all = $schema->getAll('objectclasses');

        $this->assertIsArray($all);
        $this->assertCount(3, $all);
        $this->assertArrayHasKey('top', $all);
        $this->assertArrayHasKey('person', $all);
        $this->assertArrayHasKey('organizationalPerson', $all);
    }

    /**
     * Test get() retrieves specific entry.
     */
    public function testGet(): void
    {
        $schema = $this->createMockSchema([
            'person' => [
                'name' => 'person',
                'must' => ['sn', 'cn'],
                'may' => ['telephoneNumber'],
            ],
        ]);

        $person = $schema->get('objectclass', 'person');

        $this->assertIsArray($person);
        $this->assertEquals('person', $person['name']);
        $this->assertArrayHasKey('must', $person);
        $this->assertArrayHasKey('may', $person);
    }

    /**
     * Test get() with non-existent entry throws exception.
     */
    public function testGetNonExistent(): void
    {
        $schema = $this->createMockSchema([
            'person' => ['name' => 'person'],
        ]);

        $this->expectException(\Horde_Ldap_Exception::class);
        $this->expectExceptionMessage('Could not find objectclass nonexistent');

        $schema->get('objectclass', 'nonexistent');
    }

    /**
     * Test realistic person objectClass.
     */
    public function testRealisticPersonObjectClass(): void
    {
        $schema = $this->createMockSchema([
            'top' => [
                'name' => 'top',
                'must' => ['objectClass'],
                'may' => [],
            ],
            'person' => [
                'name' => 'person',
                'sup' => ['top'],
                'must' => ['sn', 'cn'],
                'may' => ['userPassword', 'telephoneNumber', 'seeAlso', 'description'],
            ],
        ]);

        // Test must attributes
        $must = $schema->must('person');
        $this->assertCount(2, $must);
        $this->assertContains('sn', $must);
        $this->assertContains('cn', $must);

        // Test may attributes
        $may = $schema->may('person');
        $this->assertCount(4, $may);
        $this->assertContains('userPassword', $may);
        $this->assertContains('telephoneNumber', $may);

        // Test superclass returns array
        $sup = $schema->superclass('person');
        $this->assertIsArray($sup);
        $this->assertContains('top', $sup);
    }

    /**
     * Test realistic inetOrgPerson objectClass with inheritance.
     */
    public function testRealisticInetOrgPerson(): void
    {
        $schema = $this->createMockSchema([
            'top' => [
                'name' => 'top',
                'must' => ['objectClass'],
            ],
            'person' => [
                'name' => 'person',
                'sup' => ['top'],
                'must' => ['sn', 'cn'],
                'may' => ['telephoneNumber', 'description'],
            ],
            'organizationalperson' => [  // Normalized to lowercase
                'name' => 'organizationalPerson',
                'sup' => ['person'],
                'must' => [],
                'may' => ['title', 'ou', 'postalAddress'],
            ],
            'inetorgperson' => [  // Normalized to lowercase
                'name' => 'inetOrgPerson',
                'sup' => ['organizationalPerson'],
                'must' => [],
                'may' => ['mail', 'displayName', 'givenName'],
            ],
        ]);

        // inetOrgPerson inherits from organizationalPerson
        $sup = $schema->superclass('inetorgperson');
        $this->assertIsArray($sup);
        $this->assertContains('organizationalPerson', $sup);

        // organizationalPerson inherits from person
        $sup2 = $schema->superclass('organizationalperson');
        $this->assertContains('person', $sup2);

        // person inherits from top
        $sup3 = $schema->superclass('person');
        $this->assertContains('top', $sup3);

        // top has no superclass
        $sup4 = $schema->superclass('top');
        $this->assertEmpty($sup4);

        // Check may attributes with inheritance
        $may = $schema->may('inetorgperson', true);
        $this->assertContains('mail', $may);           // From inetOrgPerson
        $this->assertContains('title', $may);          // From organizationalPerson
        $this->assertContains('telephoneNumber', $may); // From person
    }

    /**
     * Test empty object class.
     */
    public function testEmptyObjectClass(): void
    {
        $schema = $this->createMockSchema([
            'abstract' => [
                'name' => 'abstract',
                'must' => [],
                'may' => [],
            ],
        ]);

        $must = $schema->must('abstract');
        $this->assertIsArray($must);
        $this->assertEmpty($must);

        $may = $schema->may('abstract');
        $this->assertIsArray($may);
        $this->assertEmpty($may);
    }
}
