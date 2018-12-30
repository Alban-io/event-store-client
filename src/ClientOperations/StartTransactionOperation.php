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

namespace Prooph\EventStoreClient\ClientOperations;

use Amp\Deferred;
use Prooph\EventStore\AsyncEventStoreTransaction;
use Prooph\EventStore\Exception\AccessDeniedException;
use Prooph\EventStore\Exception\InvalidTransactionException;
use Prooph\EventStore\Exception\StreamDeletedException;
use Prooph\EventStore\Exception\UnexpectedOperationResult;
use Prooph\EventStore\Exception\WrongExpectedVersionException;
use Prooph\EventStore\Internal\AsyncEventStoreTransactionConnection;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\Messages\ClientMessages\OperationResult;
use Prooph\EventStoreClient\Messages\ClientMessages\TransactionStart;
use Prooph\EventStoreClient\Messages\ClientMessages\TransactionStartCompleted;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use ProtobufMessage;
use Psr\Log\LoggerInterface as Logger;

/** @internal */
class StartTransactionOperation extends AbstractOperation
{
    /** @var bool */
    private $requireMaster;
    /** @var string */
    private $stream;
    /** @var int */
    private $expectedVersion;
    /** @var AsyncEventStoreTransactionConnection */
    protected $parentConnection;

    public function __construct(
        Logger $logger,
        Deferred $deferred,
        bool $requireMaster,
        string $stream,
        int $expectedVersion,
        AsyncEventStoreTransactionConnection $parentConnection,
        ?UserCredentials $userCredentials
    ) {
        $this->requireMaster = $requireMaster;
        $this->stream = $stream;
        $this->expectedVersion = $expectedVersion;
        $this->parentConnection = $parentConnection;

        parent::__construct(
            $logger,
            $deferred,
            $userCredentials,
            TcpCommand::transactionStart(),
            TcpCommand::transactionStartCompleted(),
            TransactionStartCompleted::class
        );
    }

    protected function createRequestDto(): ProtobufMessage
    {
        $message = new TransactionStart();
        $message->setRequireMaster($this->requireMaster);
        $message->setEventStreamId($this->stream);
        $message->setExpectedVersion($this->expectedVersion);

        return $message;
    }

    protected function inspectResponse(ProtobufMessage $response): InspectionResult
    {
        \assert($response instanceof TransactionStartCompleted);

        switch ($response->getResult()) {
            case OperationResult::Success:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'Success');
            case OperationResult::PrepareTimeout:
                return new InspectionResult(InspectionDecision::retry(), 'PrepareTimeout');
            case OperationResult::CommitTimeout:
                return new InspectionResult(InspectionDecision::retry(), 'CommitTimeout');
            case OperationResult::ForwardTimeout:
                return new InspectionResult(InspectionDecision::retry(), 'ForwardTimeout');
            case OperationResult::WrongExpectedVersion:
                $this->fail(WrongExpectedVersionException::with(
                    $this->stream,
                    $this->expectedVersion
                ));

                return new InspectionResult(InspectionDecision::endOperation(), 'WrongExpectedVersion');
            case OperationResult::StreamDeleted:
                $this->fail(StreamDeletedException::with($this->stream));

                return new InspectionResult(InspectionDecision::endOperation(), 'StreamDeleted');
            case OperationResult::InvalidTransaction:
                $this->fail(new InvalidTransactionException());

                return new InspectionResult(InspectionDecision::endOperation(), 'InvalidTransaction');
            case OperationResult::AccessDenied:
                $this->fail(AccessDeniedException::toStream($this->stream));

                return new InspectionResult(InspectionDecision::endOperation(), 'AccessDenied');
            default:
                throw new UnexpectedOperationResult();
        }
    }

    protected function transformResponse(ProtobufMessage $response): AsyncEventStoreTransaction
    {
        \assert($response instanceof TransactionStartCompleted);

        return new AsyncEventStoreTransaction(
            $response->getTransactionId(),
            $this->credentials,
            $this->parentConnection
        );
    }

    public function name(): string
    {
        return 'StartTransaction';
    }

    public function __toString(): string
    {
        return \sprintf(
            'Stream: %s, ExpectedVersion: %d, RequireMaster: %s',
            $this->stream,
            $this->expectedVersion,
            $this->requireMaster ? 'yes' : 'no'
        );
    }
}
