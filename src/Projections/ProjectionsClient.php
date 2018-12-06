<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\Projections;

use Amp\Artax\Response;
use Amp\Deferred;
use Amp\Promise;
use Prooph\EventStoreClient\EndPoint;
use Prooph\EventStoreClient\Exception\JsonException;
use Prooph\EventStoreClient\Exception\ProjectionCommandConflictException;
use Prooph\EventStoreClient\Exception\ProjectionCommandFailedException;
use Prooph\EventStoreClient\Transport\Http\EndpointExtensions;
use Prooph\EventStoreClient\Transport\Http\HttpAsyncClient;
use Prooph\EventStoreClient\Transport\Http\HttpStatusCode;
use Prooph\EventStoreClient\UserCredentials;
use Prooph\EventStoreClient\Util\Json;
use Throwable;

/** @internal */
class ProjectionsClient
{
    /** @var HttpAsyncClient */
    private $client;
    /** @var int */
    private $operationTimeout;

    public function __construct(int $operationTimeout)
    {
        $this->client = new HttpAsyncClient($operationTimeout);
        $this->operationTimeout = $operationTimeout;
    }

    public function enable(
        EndPoint $endPoint,
        string $name,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/projection/%s/command/enable',
                $name
            ),
            '',
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    public function disable(
        EndPoint $endPoint,
        string $name,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/projection/%s/command/disable',
                $name
            ),
            '',
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    public function abort(
        EndPoint $endPoint,
        string $name,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/projection/%s/command/abort',
                $name
            ),
            '',
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    public function createOneTime(
        EndPoint $endPoint,
        string $query,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/projections/onetime?type=JS'
            ),
            $query,
            $userCredentials,
            HttpStatusCode::CREATED
        );
    }

    public function createTransient(
        EndPoint $endPoint,
        string $name,
        string $query,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/projections/transient?name=%s&type=JS',
                $name
            ),
            $query,
            $userCredentials,
            HttpStatusCode::CREATED
        );
    }

    public function createContinuous(
        EndPoint $endPoint,
        string $name,
        string $query,
        bool $trackEmittedStreams = false,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/projections/continuous?name=%s&type=JS&emit=1&trackemittedstreams=%d',
                $name,
                (int) $trackEmittedStreams
            ),
            $query,
            $userCredentials,
            HttpStatusCode::CREATED
        );
    }

    /**
     * @return Promise<ProjectionDetails[]>
     */
    public function listAll(
        EndPoint $endPoint,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        $deferred = new Deferred();

        $promise = $this->sendGet(
            EndpointExtensions::rawUrlToHttpUrl($endPoint, $httpSchema, '/projections/any'),
            $userCredentials,
            HttpStatusCode::OK
        );

        $promise->onResolve(function (?Throwable $exception, ?string $body) use ($deferred): void {
            if ($exception) {
                $deferred->fail($exception);

                return;
            }

            try {
                $data = Json::decode($body);
            } catch (JsonException $e) {
                $deferred->fail($e);

                return;
            }

            if (null === $data['projections']) {
                $deferred->resolve(null);

                return;
            }

            $projectionDetails = [];

            foreach ($data['projections'] as $entry) {
                $projectionDetails[] = $this->buildProjectionDetails($entry);
            }

            $deferred->resolve($projectionDetails);
        });

        return $deferred->promise();
    }

    /**
     * @return Promise<ProjectionDetails[]>
     */
    public function listOneTime(
        EndPoint $endPoint,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        $deferred = new Deferred();

        $promise = $this->sendGet(
            EndpointExtensions::rawUrlToHttpUrl($endPoint, $httpSchema, '/projections/onetime'),
            $userCredentials,
            HttpStatusCode::OK
        );

        $promise->onResolve(function (?Throwable $exception, ?string $body) use ($deferred): void {
            if ($exception) {
                $deferred->fail($exception);

                return;
            }

            try {
                $data = Json::decode($body);
            } catch (JsonException $e) {
                $deferred->fail($e);

                return;
            }

            if (null === $data['projections']) {
                $deferred->resolve(null);

                return;
            }

            $projectionDetails = [];

            foreach ($data['projections'] as $entry) {
                $projectionDetails[] = $this->buildProjectionDetails($entry);
            }

            $deferred->resolve($projectionDetails);
        });

        return $deferred->promise();
    }

    /**
     * @return Promise<ProjectionDetails[]>
     */
    public function listContinuous(
        EndPoint $endPoint,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        $deferred = new Deferred();

        $promise = $this->sendGet(
            EndpointExtensions::rawUrlToHttpUrl($endPoint, $httpSchema, '/projections/continuous'),
            $userCredentials,
            HttpStatusCode::OK
        );

        $promise->onResolve(function (?Throwable $exception, ?string $body) use ($deferred): void {
            if ($exception) {
                $deferred->fail($exception);

                return;
            }

            try {
                $data = Json::decode($body);
            } catch (JsonException $e) {
                $deferred->fail($e);

                return;
            }

            if (null === $data['projections']) {
                $deferred->resolve(null);

                return;
            }

            $projectionDetails = [];

            foreach ($data['projections'] as $entry) {
                $projectionDetails[] = $this->buildProjectionDetails($entry);
            }

            $deferred->resolve($projectionDetails);
        });

        return $deferred->promise();
    }

    /**
     * @return Promise<string>
     */
    public function getStatus(
        EndPoint $endPoint,
        string $name,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendGet(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/projection/%s',
                $name
            ),
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    /**
     * @return Promise<string>
     */
    public function getState(
        EndPoint $endPoint,
        string $name,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendGet(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/projection/%s/state',
                $name
            ),
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    /**
     * @return Promise<string>
     */
    public function getPartitionState(
        EndPoint $endPoint,
        string $name,
        string $partition,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendGet(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/projection/%s/state?partition=%s',
                $name,
                $partition
            ),
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    /**
     * @return Promise<string>
     */
    public function getResult(
        EndPoint $endPoint,
        string $name,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendGet(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/projection/%s/result',
                $name
            ),
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    /**
     * @return Promise<string>
     */
    public function getPartitionResult(
        EndPoint $endPoint,
        string $name,
        string $partition,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendGet(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/projection/%s/result?partition=%s',
                $name,
                $partition
            ),
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    /**
     * @return Promise<string>
     */
    public function getStatistics(
        EndPoint $endPoint,
        string $name,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendGet(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/projection/%s/statistics',
                $name
            ),
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    /**
     * @return Promise<string>
     */
    public function getQuery(
        EndPoint $endPoint,
        string $name,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendGet(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/projection/%s/query',
                $name
            ),
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    public function updateQuery(
        EndPoint $endPoint,
        string $name,
        string $query,
        bool $emitEnabled = false,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendPut(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/projection/%s/query?emit=' . (int) $emitEnabled,
                $name
            ),
            $query,
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    public function reset(
        EndPoint $endPoint,
        string $name,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/projection/%s/command/reset',
                $name
            ),
            '',
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    public function delete(
        EndPoint $endPoint,
        string $name,
        bool $deleteEmittedStreams,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendDelete(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/projection/%s?deleteEmittedStreams=%d',
                $name,
                (int) $deleteEmittedStreams
            ),
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    private function sendGet(
        string $url,
        ?UserCredentials $userCredentials,
        int $expectedCode
    ): Promise {
        $deferred = new Deferred();

        $this->client->get(
            $url,
            $userCredentials,
            function (Response $response) use ($deferred, $expectedCode, $url): void {
                if ($response->getStatus() === $expectedCode) {
                    $deferred->resolve($response->getBody());
                } else {
                    $deferred->fail(new ProjectionCommandFailedException(
                        $response->getStatus(),
                        \sprintf(
                            'Server returned %d (%s) for GET on %s',
                            $response->getStatus(),
                            $response->getReason(),
                            $url
                        )
                    ));
                }
            },
            function (Throwable $exception) use ($deferred): void {
                $deferred->fail($exception);
            }
        );

        return $deferred->promise();
    }

    private function sendDelete(
        string $url,
        ?UserCredentials $userCredentials,
        int $expectedCode
    ): Promise {
        $deferred = new Deferred();

        $this->client->delete(
            $url,
            $userCredentials,
            function (Response $response) use ($deferred, $expectedCode, $url): void {
                if ($response->getStatus() === $expectedCode) {
                    $deferred->resolve($response->getBody());
                } else {
                    $deferred->fail(new ProjectionCommandFailedException(
                        $response->getStatus(),
                        \sprintf(
                            'Server returned %d (%s) for DELETE on %s',
                            $response->getStatus(),
                            $response->getReason(),
                            $url
                        )
                    ));
                }
            },
            function (Throwable $exception) use ($deferred): void {
                $deferred->fail($exception);
            }
        );

        return $deferred->promise();
    }

    private function sendPut(
        string $url,
        string $content,
        ?UserCredentials $userCredentials,
        int $expectedCode
    ): Promise {
        $deferred = new Deferred();

        $this->client->put(
            $url,
            $content,
            'application/json',
            $userCredentials,
            function (Response $response) use ($deferred, $expectedCode, $url): void {
                if ($response->getStatus() === $expectedCode) {
                    $deferred->resolve(null);
                } else {
                    $deferred->fail(new ProjectionCommandFailedException(
                        $response->getStatus(),
                        \sprintf(
                            'Server returned %d (%s) for PUT on %s',
                            $response->getStatus(),
                            $response->getReason(),
                            $url
                        )
                    ));
                }
            },
            function (Throwable $exception) use ($deferred): void {
                $deferred->fail($exception);
            }
        );

        return $deferred->promise();
    }

    private function sendPost(
        string $url,
        string $content,
        ?UserCredentials $userCredentials,
        int $expectedCode
    ): Promise {
        $deferred = new Deferred();

        $this->client->post(
            $url,
            $content,
            'application/json',
            $userCredentials,
            function (Response $response) use ($deferred, $expectedCode, $url): void {
                if ($response->getStatus() === $expectedCode) {
                    $deferred->resolve(null);
                } elseif ($response->getStatus() === HttpStatusCode::CONFLICT) {
                    $deferred->fail(new ProjectionCommandConflictException($response->getStatus(), $response->getReason()));
                } else {
                    $deferred->fail(new ProjectionCommandFailedException(
                        $response->getStatus(),
                        \sprintf(
                            'Server returned %d (%s) for POST on %s',
                            $response->getStatus(),
                            $response->getReason(),
                            $url
                        )
                    ));
                }
            },
            function (Throwable $exception) use ($deferred): void {
                $deferred->fail($exception);
            }
        );

        return $deferred->promise();
    }

    private function buildProjectionDetails(array $entry): ProjectionDetails
    {
        return new ProjectionDetails(
            $entry['coreProcessingTime'],
            $entry['version'],
            $entry['epoch'],
            $entry['effectiveName'],
            $entry['writesInProgress'],
            $entry['readsInProgress'],
            $entry['partitionsCached'],
            $entry['status'],
            $entry['stateReason'],
            $entry['name'],
            $entry['mode'],
            $entry['position'],
            $entry['progress'],
            $entry['lastCheckpoint'],
            $entry['eventsProcessedAfterRestart'],
            $entry['statusUrl'],
            $entry['stateUrl'],
            $entry['resultUrl'],
            $entry['queryUrl'],
            $entry['enableCommandUrl'],
            $entry['disableCommandUrl'],
            $entry['checkpointStatus'],
            $entry['bufferedEvents'],
            $entry['writePendingEventsBeforeCheckpoint'],
            $entry['writePendingEventsAfterCheckpoint']
        );
    }
}
