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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Horde_Ldap_Entry::class)]
class EntryTest extends TestCase
{
    public function testCreateFreshSuccess(): void
    {
        $entry = Horde_Ldap_Entry::createFresh(
            'cn=test',
            [
                'attr1' => 'single',
                'attr2' => ['mv1', 'mv2'],
            ]
        );
        $this->assertInstanceOf(Horde_Ldap_Entry::class, $entry);
    }
}
