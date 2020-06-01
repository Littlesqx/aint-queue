<?php

namespace app\controllers;

use app\library\queue\Factory;
use app\library\queue\job\SimpleJob;
use yii\web\Controller;

class QueueController extends Controller
{
    public function actionPush()
    {
        $job = new SimpleJob(1, 'Hello', 'World');

        $queue = Factory::make(\Yii::$app->params['aint-queue']['example_channel']);

        $queue->push($job);
    }
}