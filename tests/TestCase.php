<?php

declare(strict_types=1);

namespace Datashaman\Tongs\Plugins\Tests;

use LaravelZero\Framework\Testing\TestCase as BaseTestCase;
use Symfony\Component\Finder\Finder;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Assert that two directories are equal.
     *
     * @param string $xpected Path to expected folder structure.
     * @param string $actual Path to actual folder structure.
     */
    protected function assertDirEquals(string $expected, string $actual)
    {
        $expected = (new Finder())
            ->files()
            ->followLinks()
            ->in($expected);

        $actual = (new Finder())
            ->files()
            ->followLinks()
            ->in($actual);

        $expected = collect($expected)
            ->mapWithKeys(
                function ($file) {
                    return [
                        $file->getRelativePathname() => [
                            'contents' => trim($file->getContents()),
                            'mode' => substr(decoct($file->getPerms()), -4),
                        ],
                    ];
                }
            );

        $actual = collect($actual)
            ->mapWithKeys(
                function ($file) {
                    return [
                        $file->getRelativePathname() => [
                            'contents' => trim($file->getContents()),
                            'mode' => substr(decoct($file->getPerms()), -4),
                        ],
                    ];
                }
            );

        $this->assertEquals($expected, $actual);
    }

    /**
     * Generate the path to a fixture, or the root if not path given.
     *
     * @param string $path
     *
     * @return string
     */
    protected function fixture(string $path = '')
    {
        if ($path) {
            return __DIR__ . '/fixtures/' . $path;
        }

        return __DIR__ . '/fixtures';
    }
}
