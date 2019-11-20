<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Tests\Unit;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Collector\Counter;
use Flownative\Prometheus\Collector\Gauge;
use Flownative\Prometheus\Renderer;
use Flownative\Prometheus\Sample;
use Flownative\Prometheus\SampleCollection;
use Neos\Flow\Tests\UnitTestCase;

class RendererTest extends UnitTestCase
{
    /**
     * @test
     */
    public function rendererProvidesFormatVersion(): void
    {
        self::assertSame('0.0.4', (new Renderer())->getFormatVersion());
    }

    /**
     * @test
     */
    public function renderGeneratesCorrectRepresentationWithoutLabels(): void
    {
        $sampleCollections = [
            new SampleCollection(
                'metric_without_timestamp_and_labels',
                Gauge::TYPE,
                'A test gauge',
                [],
                [
                    new Sample('metric_without_timestamp_and_labels', [], 12.47)
                ]
            ),
            new SampleCollection(
                'counter_without_timestamp_and_labels',
                Counter::TYPE,
                'A test counter',
                [],
                [
                    new Sample('counter_without_timestamp_and_labels', [], 42)
                ]
            )
        ];

        $expectedOutput = <<<EOD
# HELP counter_without_timestamp_and_labels A test counter
# TYPE counter_without_timestamp_and_labels counter
counter_without_timestamp_and_labels 42

# HELP metric_without_timestamp_and_labels A test gauge
# TYPE metric_without_timestamp_and_labels gauge
metric_without_timestamp_and_labels 12.47
EOD;

        $actualOutput = (new Renderer())->render($sampleCollections);
        self::assertSame($expectedOutput, $actualOutput);
    }

    /**
     * @test
     */
    public function renderGeneratesCorrectRepresentationWithLabels(): void
    {
        $sampleCollections = [
            new SampleCollection(
                'http_requests_total',
                Counter::TYPE,
                'The total number of HTTP requests.',
                [],
                [
                    new Sample('http_requests_total', ['method' => 'post', 'code' => 200], 1027),
                    new Sample('http_requests_total', ['method' => 'post', 'code' => 400], 3),
                ]
            ),
            new SampleCollection(
                'counter_without_samples',
                Counter::TYPE,
                'A counter which was not used',
                [],
                []
            )
        ];

        $expectedOutput = <<<EOD
# HELP http_requests_total The total number of HTTP requests.
# TYPE http_requests_total counter
http_requests_total{method="post",code="200"} 1027
http_requests_total{method="post",code="400"} 3
EOD;

        $actualOutput = (new Renderer())->render($sampleCollections);
        self::assertSame($expectedOutput, $actualOutput);
    }

    /**
     * @test
     */
    public function renderEscapesLabelValues(): void
    {
        $sampleCollections = [
            new SampleCollection(
                'msdos_file_access_time_seconds',
                Gauge::TYPE,
                '',
                [],
                [
                    new Sample('msdos_file_access_time_seconds', ['path' => 'C:\DIR\FILE.TXT', 'error' => 'Cannot find file:' . chr(10) . '"FILE.TXT"'], 1.4726)
                ]
            )
        ];

        $expectedOutput = <<<'EOD'
# TYPE msdos_file_access_time_seconds gauge
msdos_file_access_time_seconds{path="C:\\DIR\\FILE.TXT",error="Cannot find file:\n\"FILE.TXT\""} 1.4726
EOD;

        $actualOutput = (new Renderer())->render($sampleCollections);
        self::assertSame($expectedOutput, $actualOutput);
    }
}
