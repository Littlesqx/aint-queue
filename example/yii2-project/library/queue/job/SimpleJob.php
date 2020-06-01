<?php

namespace app\library\queue\job;

use Littlesqx\AintQueue\JobInterface;
use Littlesqx\AintQueue\JobMiddlewareInterface;
use Swoole\Coroutine;

class SimpleJob implements JobInterface
{
    private $payload;

    function __construct(...$payload)
    {
        $this->payload = $payload;
    }

    /**
     * Execute current job.
     *
     * @return mixed
     */
    public function handle(): void
    {
        $wg = new Coroutine\WaitGroup();
        $wg->add(2);
        $begin = microtime(true);

        Coroutine::create(function () use ($wg) {
            var_dump($this->payload);
            sleep(1);
            $wg->done();
        });
        Coroutine::create(function () use ($wg) {
            sleep(1);
            $wg->done();
        });

        $wg->wait();

        echo "took " , microtime(true) - $begin, "s \n";
    }

    /**
     * Determine whether current job can retry if fail.
     *
     * @param int $attempt
     * @param $error
     *
     * @return bool
     */
    public function canRetry(int $attempt, $error): bool
    {
        return false;
    }

    /**
     * Get current job's next execution unix time after failed.
     *
     * @param int $attempt
     *
     * @return int
     */
    public function retryAfter(int $attempt): int
    {
        return 0;
    }

    /**
     * After failed, this function will be called.
     *
     * @param int $id
     * @param array $payload
     */
    public function failed(int $id, array $payload): void
    {
        // TODO: Implement failed() method.
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return JobMiddlewareInterface[]
     */
    public function middleware(): array
    {
        return [];
    }
}