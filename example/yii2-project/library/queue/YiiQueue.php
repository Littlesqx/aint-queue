<?php

namespace app\library\queue;


use Littlesqx\AintQueue\Driver\DriverFactory;
use Littlesqx\AintQueue\JobInterface;
use Littlesqx\AintQueue\QueueInterface;
use yii\base\Component;

class YiiQueue extends Component
{
    /**
     * @var QueueInterface
     */
    private $queue;

    public $channel;

    public function init()
    {
        parent::init();

        $config = require __DIR__.'/../../config/aint-queue.php';

        $driverOption = $config[$this->channel]['driver'] ?? [];

        $this->queue = DriverFactory::make($this->channel, $driverOption);
    }

    public function push(JobInterface $job, int $delay = 0)
    {
        $this->queue->push($job, $delay);
    }
}