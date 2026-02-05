<?php

declare(strict_types=1);

namespace FiberFlow\Queue\Drivers;

use FiberFlow\Queue\Contracts\AsyncQueueDriver;
use Illuminate\Contracts\Queue\Job;
use PHPinnacle\Ridge\Channel;
use PHPinnacle\Ridge\Client;
use PHPinnacle\Ridge\Message;

/**
 * RabbitMQ queue driver with async operations.
 */
class RabbitMqQueueDriver implements AsyncQueueDriver
{
    /**
     * RabbitMQ client.
     */
    protected ?Client $client = null;

    /**
     * RabbitMQ channel.
     */
    protected ?Channel $channel = null;

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
     * Get or create client and channel.
     */
    protected function getChannel(): Channel
    {
        if ($this->channel === null) {
            $dsn = sprintf(
                'amqp://%s:%s@%s:%d%s',
                $this->config['user'] ?? 'guest',
                $this->config['password'] ?? 'guest',
                $this->config['host'] ?? 'localhost',
                $this->config['port'] ?? 5672,
                $this->config['vhost'] ?? '/',
            );

            $this->client = Client::create($dsn);
            $this->client->connect();
            $this->channel = $this->client->channel();
        }

        return $this->channel;
    }

    /**
     * Push a job onto the queue.
     */
    public function push(string $queue, string $payload, int $delay = 0): ?string
    {
        $channel = $this->getChannel();

        // Declare queue
        $channel->queueDeclare($queue, false, true, false, false);

        // Publish message
        $messageId = uniqid('job_', true);

        $channel->publish($payload, '', $queue);

        return $messageId;
    }

    /**
     * Pop the next job from the queue.
     */
    public function pop(string $queue): ?Job
    {
        // TODO: Implement proper message consumption with Ridge library
        // For now, return null as this requires complex async consumer setup
        return null;
    }

    /**
     * Delete a job from the queue.
     */
    public function delete(string $queue, string $deliveryTag): void
    {
        $channel = $this->getChannel();

        // Acknowledge message
        $channel->ack((int) $deliveryTag);
    }

    /**
     * Release a job back to the queue.
     */
    public function release(string $queue, string $deliveryTag, int $delay = 0): void
    {
        $channel = $this->getChannel();

        // Reject and requeue message
        $channel->nack((int) $deliveryTag, false, true);
    }

    /**
     * Get the size of the queue.
     */
    public function size(string $queue): int
    {
        $channel = $this->getChannel();

        $queueInfo = $channel->queueDeclare($queue, true, true, false, false);

        return $queueInfo->messages();
    }

    /**
     * Clear all jobs from the queue.
     */
    public function clear(string $queue): void
    {
        $channel = $this->getChannel();

        $channel->queuePurge($queue);
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
        if ($this->client !== null) {
            $this->client->disconnect();
            $this->client = null;
            $this->channel = null;
        }
    }
}
