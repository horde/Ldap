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

namespace Horde\Ldap\Test\Live;

use Exception;
use Horde_Ldap;
use Horde_Ldap_Exception;
use PHPUnit\Framework\TestCase;

/**
 * Base class for LDAP tests that require a live LDAP server.
 *
 * Tests extending this class will be skipped if no LDAP configuration
 * is available via LDAP_TEST_CONFIG environment variable or conf.php file.
 */
abstract class TestBase extends TestCase
{
    protected static ?array $ldapConfig = null;

    public function setUp(): void
    {
        // Check extension
        try {
            Horde_Ldap::checkLDAPExtension();
        } catch (Horde_Ldap_Exception $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = self::getConfig();
        if ($config === null) {
            $this->markTestSkipped(
                'No configuration for LDAP tests. Set environment variable ' .
                'LDAP_TEST_CONFIG (JSON) or create conf.php in project root.'
            );
        }
        self::$ldapConfig = $config;
    }

    /**
     * Get LDAP configuration from environment or conf.php file.
     *
     * Configuration format:
     * [
     *     'server' => [
     *         'hostspec' => 'localhost',
     *         'port' => 389,
     *         'binddn' => 'cn=admin,dc=example,dc=com',
     *         'bindpw' => 'secret',
     *         'basedn' => 'dc=example,dc=com',
     *     ],
     *     'capability' => [
     *         'anonymous' => false,
     *         'tls' => false,
     *     ],
     * ]
     */
    protected static function getConfig(): ?array
    {
        // Check environment variable first
        $envConfig = getenv('LDAP_TEST_CONFIG');
        if ($envConfig !== false && $envConfig !== '') {
            $decoded = json_decode($envConfig, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // Check for conf.php in project root
        $configFile = dirname(__DIR__, 2) . '/conf.php';
        if (file_exists($configFile)) {
            require $configFile;
            if (isset($conf) && is_array($conf)) {
                return $conf;
            }
            throw new Exception('Invalid LDAP configuration in ' . $configFile);
        }

        return null;
    }
}
