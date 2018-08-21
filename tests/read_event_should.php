<?php
/**
 * This file is part of the prooph/event-store-client.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\EventId;
use Prooph\EventStoreClient\EventReadResult;
use Prooph\EventStoreClient\EventReadStatus;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Exception\OutOfRangeException;
use Prooph\EventStoreClient\Internal\UuidGenerator;
use Throwable;

class read_event_should extends TestCase
{
    use SpecificationWithConnection;

    /** @var EventId */
    private $eventId0;
    /** @var EventId */
    private $eventId1;
    /** @var string */
    private $testStream;
    /** @var string */
    private $deletedStream;

    protected function when(): Generator
    {
        $this->eventId0 = EventId::generate();
        $this->eventId1 = EventId::generate();
        $this->testStream = 'test-stream-' . UuidGenerator::generate();
        $this->deletedStream = 'deleted-stream' . UuidGenerator::generate();

        yield $this->conn->appendToStreamAsync($this->testStream, -1, [
            new EventData($this->eventId0, 'event0', false, '123', '456'),
            new EventData($this->eventId1, 'event1', true, '{"foo":"bar"}', '{"meta":"data"}'),
        ]);

        yield $this->conn->deleteStreamAsync($this->deletedStream, -1, true);
    }

    /**
     * @test
     * @throws Throwable
     */
    public function throw_if_stream_id_is_empty(): void
    {
        $this->executeCallback(function () {
            $this->expectException(InvalidArgumentException::class);
            $this->conn->readEventAsync('', 0, false);
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function throw_if_event_number_is_less_than_minus_one(): void
    {
        $this->executeCallback(function () {
            $this->expectException(OutOfRangeException::class);
            $this->conn->readEventAsync('stream', -2, false);
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function notify_using_status_code_if_stream_not_found(): void
    {
        $this->executeCallback(function () {
            /** @var EventReadResult $res */
            $res = yield $this->conn->readEventAsync('unexisting-stream', 5, false);

            $this->assertTrue($res->status()->equals(EventReadStatus::noStream()));
            $this->assertNull($res->event());
            $this->assertSame('unexisting-stream', $res->stream());
            $this->assertSame(5, $res->eventNumber());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function return_no_stream_if_requested_last_event_in_empty_stream(): void
    {
        $this->executeCallback(function () {
            /** @var EventReadResult $res */
            $res = yield $this->conn->readEventAsync('some-really-empty-stream', -1, false);

            $this->assertTrue($res->status()->equals(EventReadStatus::noStream()));
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function notify_using_status_code_if_stream_was_deleted(): void
    {
        $this->executeCallback(function () {
            /** @var EventReadResult $res */
            $res = yield $this->conn->readEventAsync($this->deletedStream, 5, false);

            $this->assertTrue($res->status()->equals(EventReadStatus::streamDeleted()));
            $this->assertNull($res->event());
            $this->assertSame($this->deletedStream, $res->stream());
            $this->assertSame(5, $res->eventNumber());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function notify_using_status_code_if_stream_does_not_have_event(): void
    {
        $this->executeCallback(function () {
            /** @var EventReadResult $res */
            $res = yield $this->conn->readEventAsync($this->testStream, 5, false);

            $this->assertTrue($res->status()->equals(EventReadStatus::notFound()));
            $this->assertNull($res->event());
            $this->assertSame($this->testStream, $res->stream());
            $this->assertSame(5, $res->eventNumber());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function return_existing_event(): void
    {
        $this->executeCallback(function () {
            /** @var EventReadResult $res */
            $res = yield $this->conn->readEventAsync($this->testStream, 0, false);

            $this->assertTrue($res->status()->equals(EventReadStatus::success()));
            $this->assertTrue($res->event()->originalEvent()->eventId()->equals($this->eventId0));
            $this->assertSame($this->testStream, $res->stream());
            $this->assertSame(0, $res->eventNumber());
            $this->assertNotEquals(new \DateTimeImmutable(), $res->event()->originalEvent()->created());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function retrieve_the_is_json_flag_properly(): void
    {
        $this->executeCallback(function () {
            /** @var EventReadResult $res */
            $res = yield $this->conn->readEventAsync($this->testStream, 1, false);

            $this->assertTrue($res->status()->equals(EventReadStatus::success()));
            $this->assertTrue($res->event()->originalEvent()->eventId()->equals($this->eventId1));
            $this->assertTrue($res->event()->originalEvent()->isJson());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function return_last_event_in_stream_if_event_number_is_minus_one(): void
    {
        $this->executeCallback(function () {
            /** @var EventReadResult $res */
            $res = yield $this->conn->readEventAsync($this->testStream, -1, false);

            $this->assertTrue($res->status()->equals(EventReadStatus::success()));
            $this->assertTrue($res->event()->originalEvent()->eventId()->equals($this->eventId1));
            $this->assertSame($this->testStream, $res->stream());
            $this->assertSame(-1, $res->eventNumber());
            $this->assertNotEquals(new \DateTimeImmutable(), $res->event()->originalEvent()->created());
        });
    }
}
