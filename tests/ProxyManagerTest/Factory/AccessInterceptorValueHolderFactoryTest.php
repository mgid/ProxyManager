<?php

declare(strict_types=1);

namespace ProxyManagerTest\Factory;

use PHPUnit\Framework\TestCase;
use ProxyManager\Autoloader\AutoloaderInterface;
use ProxyManager\Configuration;
use ProxyManager\Factory\AccessInterceptorValueHolderFactory;
use ProxyManager\Generator\ClassGenerator;
use ProxyManager\Generator\Util\UniqueIdentifierGenerator;
use ProxyManager\GeneratorStrategy\GeneratorStrategyInterface;
use ProxyManager\Inflector\ClassNameInflectorInterface;
use ProxyManager\Signature\ClassSignatureGeneratorInterface;
use ProxyManager\Signature\SignatureCheckerInterface;
use ProxyManagerTestAsset\AccessInterceptorValueHolderMock;
use ProxyManagerTestAsset\EmptyClass;
use stdClass;

/**
 * Tests for {@see \ProxyManager\Factory\AccessInterceptorValueHolderFactory}
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 * @license MIT
 *
 * @group Coverage
 */
class AccessInterceptorValueHolderFactoryTest extends TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $inflector;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $signatureChecker;

    /**
     * @var ClassSignatureGeneratorInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $classSignatureGenerator;

    /**
     * @var Configuration|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $config;

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        $this->config                  = $this->createMock(Configuration::class);
        $this->inflector               = $this->createMock(ClassNameInflectorInterface::class);
        $this->signatureChecker        = $this->createMock(SignatureCheckerInterface::class);
        $this->classSignatureGenerator = $this->createMock(ClassSignatureGeneratorInterface::class);

        $this
            ->config
            ->expects(self::any())
            ->method('getClassNameInflector')
            ->will(self::returnValue($this->inflector));

        $this
            ->config
            ->expects(self::any())
            ->method('getSignatureChecker')
            ->will(self::returnValue($this->signatureChecker));

        $this
            ->config
            ->expects(self::any())
            ->method('getClassSignatureGenerator')
            ->will(self::returnValue($this->classSignatureGenerator));
    }

    /**
     * {@inheritDoc}
     *
     * @covers \ProxyManager\Factory\AccessInterceptorValueHolderFactory::__construct
     */
    public function testWithOptionalFactory() : void
    {
        $factory = new AccessInterceptorValueHolderFactory();
        self::assertAttributeNotEmpty('configuration', $factory);
        self::assertAttributeInstanceOf(Configuration::class, 'configuration', $factory);
    }

    /**
     * {@inheritDoc}
     *
     * @covers \ProxyManager\Factory\AccessInterceptorValueHolderFactory::__construct
     * @covers \ProxyManager\Factory\AccessInterceptorValueHolderFactory::createProxy
     * @covers \ProxyManager\Factory\AccessInterceptorValueHolderFactory::getGenerator
     */
    public function testWillSkipAutoGeneration() : void
    {
        $instance = new stdClass();
        $generator      = $this->createMock(GeneratorStrategyInterface::class);

        $this
            ->inflector
            ->expects(self::once())
            ->method('getProxyClassName')
            ->with('stdClass')
            ->will(self::returnValue(AccessInterceptorValueHolderMock::class));

        $generator
            ->expects(self::once())
            ->method('classExists')
            ->with(
                AccessInterceptorValueHolderMock::class,
                $this->config
            )
            ->willReturn(true);

        $factory            = new AccessInterceptorValueHolderFactory($this->config);
        $this->config->expects(self::any())->method('getGeneratorStrategy')->will(self::returnValue($generator));
        $prefixInterceptors = [function () {
            self::fail('Not supposed to be called');
        }];
        $suffixInterceptors = [function () {
            self::fail('Not supposed to be called');
        }];
        /* @var $proxy AccessInterceptorValueHolderMock */
        $proxy              = $factory->createProxy($instance, $prefixInterceptors, $suffixInterceptors);

        self::assertInstanceOf(AccessInterceptorValueHolderMock::class, $proxy);
        self::assertSame($instance, $proxy->instance);
        self::assertSame($prefixInterceptors, $proxy->prefixInterceptors);
        self::assertSame($suffixInterceptors, $proxy->suffixInterceptors);
    }

    /**
     * {@inheritDoc}
     *
     * @covers \ProxyManager\Factory\AccessInterceptorValueHolderFactory::__construct
     * @covers \ProxyManager\Factory\AccessInterceptorValueHolderFactory::createProxy
     * @covers \ProxyManager\Factory\AccessInterceptorValueHolderFactory::getGenerator
     *
     * NOTE: serious mocking going on in here (a class is generated on-the-fly) - careful
     */
    public function testWillTryAutoGeneration() : void
    {
        $instance       = new stdClass();
        $proxyClassName = UniqueIdentifierGenerator::getIdentifier('bar');
        $generator      = $this->createMock(GeneratorStrategyInterface::class);
        $autoloader     = $this->createMock(AutoloaderInterface::class);

        $this->config->expects(self::any())->method('getGeneratorStrategy')->will(self::returnValue($generator));
        $this->config->expects(self::any())->method('getProxyAutoloader')->will(self::returnValue($autoloader));

        $generator
            ->expects(self::once())
            ->method('generate')
            ->with(
                self::callback(
                    function (ClassGenerator $targetClass) use ($proxyClassName) : bool {
                        return $targetClass->getName() === $proxyClassName;
                    }
                )
            );

        // simulate autoloading
        $autoloader
            ->expects(self::once())
            ->method('__invoke')
            ->with($proxyClassName)
            ->willReturnCallback(function () use ($proxyClassName) : bool {
                eval(
                    'class ' . $proxyClassName
                    . ' extends \\ProxyManagerTestAsset\\AccessInterceptorValueHolderMock {}'
                );

                return true;
            });

        $this
            ->inflector
            ->expects(self::once())
            ->method('getProxyClassName')
            ->with('stdClass')
            ->will(self::returnValue($proxyClassName));

        $this
            ->inflector
            ->expects(self::once())
            ->method('getUserClassName')
            ->with('stdClass')
            ->will(self::returnValue(EmptyClass::class));

        $this->signatureChecker->expects(self::atLeastOnce())->method('checkSignature');
        $this->classSignatureGenerator->expects(self::once())->method('addSignature')->will(self::returnArgument(0));

        $factory            = new AccessInterceptorValueHolderFactory($this->config);
        $prefixInterceptors = [function () {
            self::fail('Not supposed to be called');
        }];
        $suffixInterceptors = [function () {
            self::fail('Not supposed to be called');
        }];
        /* @var $proxy AccessInterceptorValueHolderMock */
        $proxy              = $factory->createProxy($instance, $prefixInterceptors, $suffixInterceptors);

        self::assertInstanceOf($proxyClassName, $proxy);
        self::assertSame($instance, $proxy->instance);
        self::assertSame($prefixInterceptors, $proxy->prefixInterceptors);
        self::assertSame($suffixInterceptors, $proxy->suffixInterceptors);
    }
}
