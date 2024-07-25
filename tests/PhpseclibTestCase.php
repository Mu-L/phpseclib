<?php

/**
 * @author    Andreas Fischer <bantu@phpbb.com>
 * @copyright 2013 Andreas Fischer
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 */

declare(strict_types=1);

namespace phpseclib3\Tests;

use PHPUnit\Framework\TestCase;

abstract class PhpseclibTestCase extends TestCase
{
    protected $tempFilesToUnlinkOnTearDown = [];

    public function tearDown(): void
    {
        foreach ($this->tempFilesToUnlinkOnTearDown as $filename) {
            if (!file_exists($filename) || unlink($filename)) {
                unset($this->tempFilesToUnlinkOnTearDown[$filename]);
            }
        }
        parent::tearDown();
    }

    /**
     * Creates a temporary file on the local filesystem and returns its path.
     * The $number_of_writes and $bytes_per_write parameters can be used to
     * write $number_of_writes * $bytes_per_write times the character 'a' to the
     * temporary file. All files created using this method will be deleted from
     * the filesystem on tearDown(), i.e. after each test method was run.
     */
    protected function createTempFile(int $number_of_writes = 0, int $bytes_per_write = 0): string
    {
        $filename = tempnam(sys_get_temp_dir(), 'phpseclib-test-');
        $this->assertTrue(file_exists($filename));
        $this->tempFilesToUnlinkOnTearDown[] = $filename;
        if ($number_of_writes > 0 && $bytes_per_write > 0) {
            $fp = fopen($filename, 'wb');
            for ($i = 0; $i < $number_of_writes; ++$i) {
                fwrite($fp, str_repeat('a', $bytes_per_write));
            }
            fclose($fp);
            $this->assertSame($number_of_writes * $bytes_per_write, filesize($filename));
        }
        return $filename;
    }

    /**
     * @return null
     */
    protected static function ensureConstant(string $constant, $expected)
    {
        if (defined($constant)) {
            $value = constant($constant);

            if ($value !== $expected) {
                if (extension_loaded('runkit')) {
                    if (!runkit_constant_redefine($constant, $expected)) {
                        self::markTestSkipped(sprintf(
                            "Failed to redefine constant %s to %s",
                            $constant,
                            $expected
                        ));
                    }
                } else {
                    self::markTestSkipped(sprintf(
                        "Skipping test because constant %s is %s instead of %s",
                        $constant,
                        $value,
                        $expected
                    ));
                }
            }
        } else {
            define($constant, $expected);
        }
    }

    protected static function getVar($obj, $var)
    {
        $reflection = new \ReflectionClass($obj::class);
        // private variables are not inherited, climb hierarchy until located
        while (true) {
            try {
                $prop = $reflection->getProperty($var);
                break;
            } catch (\ReflectionException $e) {
                $reflection = $reflection->getParentClass();
                if (!$reflection) {
                    throw $e;
                }
            }
        }
        $prop->setAccessible(true);
        return $prop->getValue($obj);
    }

    protected static function setVar($obj, $var, $value): void
    {
        $reflection = new \ReflectionClass($obj::class);
        // private variables are not inherited, climb hierarchy until located
        while (true) {
            try {
                $prop = $reflection->getProperty($var);
                break;
            } catch (\ReflectionException $e) {
                $reflection = $reflection->getParentClass();
                if (!$reflection) {
                    throw $e;
                }
            }
        }
        $prop->setAccessible(true);
        $prop->setValue($obj, $value);
    }

    public static function callFunc($obj, $func, $params = [])
    {
        $reflection = new \ReflectionClass($obj::class);
        $method = $reflection->getMethod($func);
        $method->setAccessible(true);
        return $method->invokeArgs($obj, $params);
    }
}
