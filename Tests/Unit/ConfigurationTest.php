<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Tests\Unit;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Exception\InvalidConfigurationException;
use Flownative\Prometheus\Configuration;
use Neos\Flow\Tests\UnitTestCase;

class ConfigurationTest extends UnitTestCase
{
    /**
     * @return array
     * @see https://prometheus.io/docs/concepts/data_model/#metric-names-and-labels
     */
    public function validParameters(): array
    {
        return [
            ['counter', 'A counter for testing', ['flownative', 'prometheus', 'test']],
            ['gauge', 'A temperature for testing', ['flownative', 'prometheus', 'test']],
            ['counter', '', ['flownative', 'empty', 'help']],
            ['counter', '', ['_leading_underscore']],
            ['counter', '', ['with_Uppercase_characters']],
            ['counter', '', ['with_12345_digits']],
            ['counter', '', ['withTrailingDigit9']],
        ];
    }

    /**
     * @param string $type
     * @param string $help
     * @param array $labels
     * @test
     * @dataProvider validParameters
     * @throws InvalidConfigurationException
     */
    public function constructorAcceptsValidParameters(string $type, string $help, array $labels): void
    {
        $configuration = new Configuration($type, $help, $labels);

        self::assertSame($type, $configuration->getType());
        self::assertSame($help, $configuration->getHelp());
        self::assertSame($labels, $configuration->getLabels());
    }

    /**
     * @return array
     * @see https://prometheus.io/docs/concepts/data_model/#metric-names-and-labels
     */
    public function invalidParameters(): array
    {
        return [
            ['unknown', 'Unknown type for testing', ['flownative', 'prometheus', 'test']],
            ['counter', '', ['label with spaces']],
            ['counter', '', ['label-with-dashes']],
            ['counter', '', [' label_with_leading_space']],
            ['counter', '', ['99label_with_leading_digit']],
            ['counter', '', ['__label_with_two_leading_underscores']]
        ];
    }

    /**
     * @param string $type
     * @param string $help
     * @param array $labels
     * @throws InvalidConfigurationException
     * @test
     * @dataProvider invalidParameters
     */
    public function constructorRejectsInvalidParameters(string $type, string $help, array $labels): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new Configuration($type, $help, $labels);
    }


}
