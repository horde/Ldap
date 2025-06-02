<?php

/**
 * Copyright 2010-2017 Horde LLC (http://www.horde.org/)
 *
 * @package    Ldap
 * @subpackage UnitTests
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.gnu.org/licenses/lgpl-3.0.html LGPL-3.0
 */

namespace Horde\Ldap\Test\Unit;

use PHPUnit\Framework\TestCase;
use Horde_Ldap_Entry;

/**
 * @coversNothing
 */
class EntryTest extends TestCase
{
    public function testCreateFreshSuccess()
    {
        $entry = Horde_Ldap_Entry::createFresh(
            'cn=test',
            ['attr1' => 'single',
                'attr2' => ['mv1', 'mv2']]
        );
        $this->assertInstanceOf('Horde_Ldap_Entry', $entry);
    }
}
