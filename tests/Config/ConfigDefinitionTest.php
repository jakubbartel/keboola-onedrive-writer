<?php

namespace Keboola\OneDriveWriter\Tests\Config;

use Keboola\Component;
use Keboola\OneDriveWriter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ConfigDefinitionTest extends TestCase
{

    public function testLoadValidConfig() : void
    {
        $config = new Component\Config\BaseConfig(
            json_decode(file_get_contents(__DIR__ . '/fixtures/config_valid.json'), true),
            new OneDriveWriter\ConfigDefinition()
        );

        $this->assertInstanceOf(Component\Config\BaseConfig::class, $config);

        $this->assertEquals('abcde', $config->getValue(['parameters', 'path']));
    }
}
