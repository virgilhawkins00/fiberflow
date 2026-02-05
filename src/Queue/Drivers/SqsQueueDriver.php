<?php

declare(strict_types=1);

namespace FiberFlow\Queue\Drivers;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use FiberFlow\Queue\Contracts\AsyncQueueDriver;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Jobs\SqsJob;

/**
 * AWS SQS queue driver with async operations.
 */
class SqsQueueDriver implements AsyncQueueDriver
{
    /**
     * HTTP client for async requests.
     */
    protected $client;

    /**
     * SQS queue URL.
     */
    protected string $queueUrl;

    /**
     * AWS credentials.
     */
    protected array $credentials;

    /**
     * Create a new SQS queue driver.
     *
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->client = HttpClientBuilder::buildDefault();
        $this->queueUrl = $config['queue_url'] ?? '';
        $this->credentials = [
            'key' => $config['key'] ?? '',
            'secret' => $config['secret'] ?? '',
            'region' => $config['region'] ?? 'us-east-1',
        ];
    }

    /**
     * Push a job onto the queue.
     */
    public function push(string $queue, string $payload, int $delay = 0): ?string
    {
        $params = [
            'Action' => 'SendMessage',
            'MessageBody' => $payload,
            'QueueUrl' => $this->getQueueUrl($queue),
        ];

        if ($delay > 0) {
            $params['DelaySeconds'] = $delay;
        }

        $response = $this->makeRequest('POST', $params);

        // Parse message ID from response
        if (preg_match('/<MessageId>(.*?)<\/MessageId>/', $response, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Pop the next job from the queue.
     */
    public function pop(string $queue): ?Job
    {
        $params = [
            'Action' => 'ReceiveMessage',
            'QueueUrl' => $this->getQueueUrl($queue),
            'MaxNumberOfMessages' => 1,
            'WaitTimeSeconds' => 0,
        ];

        $response = $this->makeRequest('POST', $params);

        // Parse message from response
        if (! preg_match('/<Body>(.*?)<\/Body>/', $response, $bodyMatches)) {
            return null;
        }

        if (! preg_match('/<ReceiptHandle>(.*?)<\/ReceiptHandle>/', $response, $handleMatches)) {
            return null;
        }

        return new SqsJob(
            app(),
            app('queue.sqs'),
            [
                'Body' => $bodyMatches[1],
                'ReceiptHandle' => $handleMatches[1],
            ],
            app('queue')->connection(),
            $queue,
        );
    }

    /**
     * Delete a job from the queue.
     */
    public function delete(string $queue, string $receiptHandle): void
    {
        $params = [
            'Action' => 'DeleteMessage',
            'QueueUrl' => $this->getQueueUrl($queue),
            'ReceiptHandle' => $receiptHandle,
        ];

        $this->makeRequest('POST', $params);
    }

    /**
     * Release a job back to the queue.
     */
    public function release(string $queue, string $receiptHandle, int $delay = 0): void
    {
        $params = [
            'Action' => 'ChangeMessageVisibility',
            'QueueUrl' => $this->getQueueUrl($queue),
            'ReceiptHandle' => $receiptHandle,
            'VisibilityTimeout' => $delay,
        ];

        $this->makeRequest('POST', $params);
    }

    /**
     * Get the size of the queue.
     */
    public function size(string $queue): int
    {
        $params = [
            'Action' => 'GetQueueAttributes',
            'QueueUrl' => $this->getQueueUrl($queue),
            'AttributeName.1' => 'ApproximateNumberOfMessages',
        ];

        $response = $this->makeRequest('POST', $params);

        if (preg_match('/<Value>(\d+)<\/Value>/', $response, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * Clear all jobs from the queue.
     */
    public function clear(string $queue): void
    {
        $params = [
            'Action' => 'PurgeQueue',
            'QueueUrl' => $this->getQueueUrl($queue),
        ];

        $this->makeRequest('POST', $params);
    }

    /**
     * Get driver name.
     */
    public function getName(): string
    {
        return 'sqs';
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
        // HTTP client doesn't need explicit closing
    }

    /**
     * Make an async HTTP request to SQS.
     */
    protected function makeRequest(string $method, array $params): string
    {
        $url = $this->queueUrl.'?'.http_build_query($params);
        $request = new Request($url, $method);

        // Add AWS signature headers (simplified - production would use AWS SDK)
        $request->setHeader('Content-Type', 'application/x-www-form-urlencoded');

        $response = $this->client->request($request);

        return $response->getBody()->buffer();
    }

    /**
     * Get the full queue URL.
     */
    protected function getQueueUrl(string $queue): string
    {
        return $this->queueUrl.'/'.$queue;
    }
}
