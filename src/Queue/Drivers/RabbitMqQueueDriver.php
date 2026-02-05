<?php

declare(strict_types=1);

namespace FiberFlow\Queue\Drivers;

use Amp\RabbitMq\Connection;
use Amp\RabbitMq\Consumer;
use Amp\RabbitMq\Exchange;
use Amp\RabbitMq\Queue;
use FiberFlow\Queue\Contracts\AsyncQueueDriver;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Jobs\RedisJob;

/**
 * RabbitMQ queue driver with async operations.
 */
class RabbitMqQueueDriver implements AsyncQueueDriver
{
    /**
     * RabbitMQ connection.
     */
    protected ?Connection $connection = null;

    /**
     * Exchange name.
     */
    protected string $exchange;

    /**
     * Connection config.
     */
    protected array $config;

    /**
     * Create a new RabbitMQ queue driver.
     *
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->exchange = $config['exchange'] ?? 'fiberflow';
    }

    /**
     * Get or create connection.
     */
    protected function getConnection(): Connection
    {
        if ($this->connection === null) {
            $this->connection = new Connection(
                $this->config['host'] ?? 'localhost',
                $this->config['port'] ?? 5672,
                $this->config['user'] ?? 'guest',
                $this->config['password'] ?? 'guest',
                $this->config['vhost'] ?? '/',
            );
        }

        return $this->connection;
    }

    /**
     * Push a job onto the queue.
     */
    public function push(string $queue, string $payload, int $delay = 0): ?string
    {
        $connection = $this->getConnection();
        $channel = $connection->channel();

        // Declare exchange
        $exchange = new Exchange($channel);
        $exchange->declare($this->exchange, 'direct', false, true, false);

        // Declare queue
        $queueObj = new Queue($channel);
        $queueObj->declare($queue, false, true, false, false);
        $queueObj->bind($this->exchange, $queue);

        // Publish message
        $properties = [];
        if ($delay > 0) {
            $properties['expiration'] = (string) ($delay * 1000); // milliseconds
        }

        $messageId = uniqid('job_', true);
        $properties['message_id'] = $messageId;

        $exchange->publish($payload, $queue, AMQP_NOPARAM, $properties);

        return $messageId;
    }

    /**
     * Pop the next job from the queue.
     */
    public function pop(string $queue): ?Job
    {
        $connection = $this->getConnection();
        $channel = $connection->channel();

        // Declare queue
        $queueObj = new Queue($channel);
        $queueObj->declare($queue, false, true, false, false);

        // Create consumer
        $consumer = new Consumer($channel);
        $consumer->consume($queue, '', false, false, false, false);

        // Get message (non-blocking)
        $message = $consumer->get();

        if ($message === null) {
            return null;
        }

        // Create Laravel job wrapper
        return new RedisJob(
            app(),
            app('queue.redis'),
            $message->getBody(),
            '',
            $queue,
            $message->getDeliveryTag(),
        );
    }

    /**
     * Delete a job from the queue.
     */
    public function delete(string $queue, string $deliveryTag): void
    {
        $connection = $this->getConnection();
        $channel = $connection->channel();

        // Acknowledge message
        $channel->basic_ack((int) $deliveryTag);
    }

    /**
     * Release a job back to the queue.
     */
    public function release(string $queue, string $deliveryTag, int $delay = 0): void
    {
        $connection = $this->getConnection();
        $channel = $connection->channel();

        // Reject and requeue message
        $channel->basic_nack((int) $deliveryTag, false, true);
    }

    /**
     * Get the size of the queue.
     */
    public function size(string $queue): int
    {
        $connection = $this->getConnection();
        $channel = $connection->channel();

        $queueObj = new Queue($channel);
        $info = $queueObj->declare($queue, true, true, false, false);

        return $info['message_count'] ?? 0;
    }

    /**
     * Clear all jobs from the queue.
     */
    public function clear(string $queue): void
    {
        $connection = $this->getConnection();
        $channel = $connection->channel();

        $queueObj = new Queue($channel);
        $queueObj->purge($queue);
    }

    /**
     * Get driver name.
     */
    public function getName(): string
    {
        return 'rabbitmq';
    }

    /**
     * Check if driver supports async operations.
     */
    public function isAsync(): bool
    {
        return true;
    }

    /**
     * Close the driver connection.
     */
    public function close(): void
    {
        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }
    }
}
