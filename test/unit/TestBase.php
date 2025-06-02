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
use Horde_Ldap;
use Horde_Ldap_Exception;
use PHPUnit\Framework\TestCase;
use Exception;

/**
 * @coversNothing
 */
class TestBase extends TestCase
{
    protected static ?array $ldapcfg;

    public function setUp(): void
    {
        // Check extension.
        try {
            Horde_Ldap::checkLDAPExtension();
        } catch (Horde_Ldap_Exception $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = self::getConfig();
        if (!$config) {
            $this->markTestSkipped('No configuration for LDAP tests. Either set the environment variable LDAP_TEST_CONFIG to a JSON-encoded configuration or create a conf.php file in the current directory or two levels up in the component root dir.');
        }
        self::$ldapcfg = $config;
    }
    /**
     * Get the LDAP configuration
     * 
     * - Checks for an environment variable LDAP_TEST_CONFIG with a json-encoded config
     * - Checks for a conf.php file in the current directory or two levels up
     */
    public static function getConfig(): ?array
    {
        $env = getenv();
        if (isset($env['LDAP_TEST_CONFIG']) && !empty($env['LDAP_TEST_CONFIG'])) {
            $config = json_decode($env['LDAP_TEST_CONFIG']);
        }
        $configFileLocations = [
            dirname(__FILE__) . '/conf.php',
            dirname(__FILE__, 3) . '/conf.php',
        ];
        foreach ($configFileLocations as $configFile) {
            if (file_exists($configFile)) {
                require $configFile;
                if (!isset($conf) || !is_array($conf)) {
                    throw new Exception('Invalid LDAP configuration in ' . $configFile);
                }
                return $conf;
            }
        }
        return null;
    }
}
