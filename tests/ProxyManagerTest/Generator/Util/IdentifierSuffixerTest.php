<?php

declare(strict_types=1);

namespace ProxyManagerTest\Generator\Util;

use PackageVersions\Versions;
use PHPUnit\Framework\TestCase;
use ProxyManager\Generator\Util\IdentifierSuffixer;

/**
 * Tests for {@see \ProxyManager\Generator\Util\IdentifierSuffixer}
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 * @license MIT
 *
 * @group Coverage
 * @covers \ProxyManager\Generator\Util\IdentifierSuffixer
 */
class IdentifierSuffixerTest extends TestCase
{
    /**
     * @dataProvider getBaseIdentifierNames
     */
    public function testGeneratesSuffixedIdentifiers(string $name) : void
    {
        self::assertSame(
            IdentifierSuffixer::getIdentifier($name),
            IdentifierSuffixer::getIdentifier($name)
        );
    }

    /**
     * @dataProvider getBaseIdentifierNames
     */
    public function testGeneratedSuffixDependsOnPackageInstalledVersions(string $name) : void
    {
        self::assertStringEndsWith(
            \substr(sha1($name . sha1(serialize(Versions::VERSIONS))), 0, 5),
            IdentifierSuffixer::getIdentifier($name)
        );
    }

    /**
     * @dataProvider getBaseIdentifierNames
     *
     * @param string $name
     */
    public function testGeneratesValidIdentifiers(string $name) : void
    {
        self::assertRegExp(
            '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]+$/',
            IdentifierSuffixer::getIdentifier($name)
        );
    }

    /**
     * @dataProvider getBaseIdentifierNames
     *
     * @param string $name
     */
    public function testGeneratedIdentifierSuffix(string $name) : void
    {
        // 5 generated characters are enough to keep idiots from tampering with these properties "the easy way"
        self::assertGreaterThan(5, strlen(IdentifierSuffixer::getIdentifier($name)));
    }

    /**
     * Data provider generating identifier names to be checked
     *
     * @return string[][]
     */
    public function getBaseIdentifierNames() : array
    {
        return [
            [''],
            ['1'],
            ['foo'],
            ['Foo'],
            ['bar'],
            ['Bar'],
            ['foo_bar'],
        ];
    }
}
