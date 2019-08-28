<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Driver\Redis;

/**
 * Class LuaScripts.
 *
 * @credit This file is modified from illuminate/queue.
 */
class LuaScripts
{
    /**
     * Get the Lua script for computing the size of queue.
     *
     * KEYS[1] - The name of the primary queue
     * KEYS[2] - The name of the "delayed" queue
     * KEYS[3] - The name of the "reserved" queue
     *
     * @return string
     */
    public static function size()
    {
        return <<<'LUA'
return redis.call('llen', KEYS[1]) + redis.call('zcard', KEYS[2]) + redis.call('hlen', KEYS[3])
LUA;
    }

    /**
     * Get the Lua script for reserving waiting job.
     *
     * KEYS[1] - The "reserved" set we reserve jobs onto, for example: queues:foo:reserved
     * KEYS[2] - The "attempt" queue we record jobs' attempt number, for example: queues:foo:attempts
     * ARGV[1] - The id of the job to add to the "reserved" set
     *
     * @return string
     */
    public static function reserve()
    {
        return <<<'LUA'
-- Add the job onto the "reserved" set and add attempt time of the job...
local attempts = redis.call('hincrby', KEYS[2], ARGV[1], 1)
redis.call('hset', KEYS[1], ARGV[1], attempts)
return true
LUA;
    }

    /**
     * Get the Lua script for releasing reserved jobs with delay.
     *
     * KEYS[1] - The "delayed" queue we release jobs onto, for example: queues:foo:delayed
     * KEYS[2] - The queue the jobs are currently on, for example: queues:foo:reserved
     * ARGV[1] - The id of the job to add to the "delayed" queue
     * ARGV[2] - The UNIX timestamp at which the job should become available
     *
     * @return string
     */
    public static function release()
    {
        return <<<'LUA'
-- Remove the job from the current queue...
redis.call('hdel', KEYS[2], ARGV[1])

-- Add the job onto the "delayed" queue...
redis.call('zadd', KEYS[1], ARGV[2], ARGV[1])

return true
LUA;
    }

    /**
     * Get the Lua script to migrate expired jobs back onto the queue.
     *
     * KEYS[1] - The queue we are removing jobs from, for example: queues:foo:delayed
     * KEYS[2] - The queue we are moving jobs to, for example: queues:foo:waiting
     * ARGV[1] - The current UNIX timestamp
     *
     * @return string
     */
    public static function migrateExpiredJobs()
    {
        return <<<'LUA'
-- Get all of the jobs with an expired "score"...
local val = redis.call('zrangebyscore', KEYS[1], '-inf', ARGV[1])

-- If we have values in the array, we will remove them from the first queue
-- and add them onto the destination queue in chunks of 100, which moves
-- all of the appropriate jobs onto the destination queue very safely.
if(next(val) ~= nil) then
    redis.call('zremrangebyrank', KEYS[1], 0, #val - 1)

    for i = 1, #val, 100 do
        redis.call('lpush', KEYS[2], unpack(val, i, math.min(i+99, #val)))
    end
end

return val
LUA;
    }
}
