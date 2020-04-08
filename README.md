[![MIT license](http://img.shields.io/badge/license-MIT-brightgreen.svg)](http://opensource.org/licenses/MIT)
[![Packagist](https://img.shields.io/packagist/v/flownative/prometheus.svg)](https://packagist.org/packages/flownative/prometheus)
[![Maintenance level: Love](https://img.shields.io/badge/maintenance-%E2%99%A1%E2%99%A1%E2%99%A1-ff69b4.svg)](https://www.flownative.com/en/products/open-source.html)

# Prometheus client library for Neos Flow / PHP

This [Flow](https://flow.neos.io) package allows you to collect and provide metrics to [Prometheus](https://www.prometheus.io). 
It supports client-side aggregation of metrics data and provides an endpoint for Prometheus for scraping these metrics.  

## How does it work?

Your Flow application can provide different kinds of metrics, for example the current number of registered users (a gauge) or 
the number of requests to your API (a counter). Metrics values are stored in a storage – currently only Redis is supported, and
there's an in-memory storage for testing.

The metrics endpoint (by default http(s)://your-host/metrics) collects all current metric values from the storage
and renders it in a format which can be read by Prometheus. Therefore, metrics are _not_ collected or generated during a request
to the metrics endpoint. Depending on how expensive it is to update a metric (think: number of incoming HTTP requests vs. books 
sold but returned throughout the last 15 years), the values may be updated on the fly (e.g. by registering a Flow HTTP Component)
or through a helper process (a cron-job or long-running command-line process).

## Installation

The Prometheus integration is installed as a regular Flow package via Composer. For your existing project, simply include 
`flownative/prometheus` into the dependencies of your Flow or Neos distribution:

```bash
$ composer require flownative/prometheus:0.*
```

## Configuration

### Storage

By default, the `InMemoryStorage` will be used. You will want to use the `RedisStorage` instead, so you don't loose all metrics
values between requests. The `RedisStorage` contained in this package does *not* require a special PHP extension, as it is implemented
in plain PHP.

In order to use the `RedisStorage`, create an `Objects.yaml` in your package's or Flow distribution's `Configuration` directory
and add the following configuration:

```yaml
Flownative\Prometheus\DefaultCollectorRegistry:
  arguments:
    1:
      object: Flownative\Prometheus\Storage\RedisStorage

Flownative\Prometheus\Storage\RedisStorage:
  arguments:
    1:
      value:
        hostname: '%env:MY_REDIS_HOST%'
        port: '%env:MY_REDIS_PORT%'
        password: '%env:MY_REDIS_PASSWORD%'
        database: 20
```

In this example, environment variables are used for passing access parameters to the `RedisStorage`. Test your setup by opening the 
path `/metrics` of your Flow instance in a browser. You should see the following comment:

```
# Flownative Prometheus Metrics Exporter: There are currently no metrics with data to export.
```

The `RedisStorage` also supports Redis cluster setups with Sentinel servers. If you'd like to connect to a cluster and use Sentinels
for autodiscovery, omit the hostname and password options and use the sentinel option instead:

```yaml
Flownative\Prometheus\Storage\RedisStorage:
  arguments:
    1:
      value:
        password: '%env:MY_REDIS_PASSWORD%'
        database: 20
        sentinels:
          - 'tcp://10.101.213.145:26379'
          - 'tcp://10.101.213.146:26379'
          - 'tcp://10.101.213.147:26379'
        service: 'mymaster'
```

Instead of providing sentinels as an array you can also set them as a comma-separated string.

The `RedisStorage` can be configured to ignore connection errors. This may protect your application against fatal errors at times  
when Redis is not available. Of course, no metrics are stored while Redis connections fail.  

```yaml
Flownative\Prometheus\Storage\RedisStorage:
  arguments:
    1:
      value:
        hostname: '%env:MY_REDIS_HOST%'
        port: '%env:MY_REDIS_PORT%'
        password: '%env:MY_REDIS_PASSWORD%'
        database: 20
        ignoreConnectionErrors: true
```

### Telemetry Path

The path, where metrics are provided for scraping, is "/metrics" by default. You can change this path by setting a respective
option for the HTTP component:

```yaml
Neos:
  Flow:
    http:
      chain:
        'process':
          chain:
            'Flownative.Prometheus:metricsExporter':
              componentOptions:
                telemetryPath: '/some-other-path'
```

### Security

By default, the telemetry endpoint is *not* active. It is active when the environment variable `FLOWNATIVE_PROMETHEUS_ENABLE` is set to "true" (ie. "true" is a string value!).
You can achieve this by setting the variable in your webserver's virtual host configuration.

The idea behind enabling telemetry through such a variable is, that you configure your webserver to provide metrics through a different port than your actual website or application.
This way its easy to hide metrics through firewall rules or by not providing access to that port through your load balancer. 

The telemetry endpoint can also be protected by requiring clients to authenticate first with username and password. HTTP Basic Authentication is configured as follows:

```yaml
Neos:
  Flow:
    http:
      chain:
        'process':
          chain:
            'Flownative.Prometheus:metricsExporter':
              componentOptions:
                basicAuth:

                  # If set to non-empty values, HTTP Basic Auth is enabled:
                  username: 'my-username'
                  password: 'my-password'

                  # Optional:
                  realm: 'Acme App Metrics'
```

## Usage

The `DefaultCollectorRegistry` is pre-configured and can be injected via Dependency Injection:

```php
    /**
     * @Flow\Inject
     * @var \Flownative\Prometheus\CollectorRegistry\DefaultCollectorRegistry
     */
    protected $collectorRegistry;
```

A simple counter:

```php
    $this->collectorRegistry->getCounter('acme_myproject_controller_hits_total')
        ->inc();   
```

A counter using labels:

```php
    $this->collectorRegistry->getCounter('acme_myproject_controller_hits_total')
        ->inc(1, ['result' => 'success']);   
    …
    $this->collectorRegistry->getCounter('acme_myproject_controller_hits_total')
        ->inc(1, ['result' => 'failed']);   
```

A gauge:

```php
    $this->collectorRegistry->getGauge('neos_flow_sessions')
        ->set(count($this->sessionManager->getActiveSessions()),
            [
                'state' => 'active'
            ]
        );
```

Manual usage of the Collector Registry, using the `InMemoryStorage`:

````php
    $registry = new CollectorRegistry(new InMemoryStorage());
    $registry->register('flownative_prometheus_test_calls_total', Counter::TYPE, 'a test call counter', ['tests', 'counter']);

    $counter = $registry->getCounter('flownative_prometheus_test_calls_total');
    $counter->inc(5.5);

    $sampleCollections = $registry->collect();

    $renderer = new Renderer();
    echo ($renderer->render($sampleCollections));
````

## Running the tests

All key features are backed by unit tests. Currently you need Redis running in order to run them. Provide
the necessary credentials via `REDIS_HOST`, `REDIS_PORT`, and `REDIS_PASSWORD` (see `Objects.yaml` contained
in this package).

Apart from that, tests are run like any other unit test suite for Flow.
