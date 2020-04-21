<?php

declare(strict_types=1);

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
 * @credit This file is modified from illuminate/queue
 */
class LuaScripts
{
    /**
     * Get the Lua script for popping waiting job.
     *
     * KEYS[1] - The "waiting" queue we pop jobs from, for example: queues:foo:waiting
     * KEYS[2] - The "reserved" set we reserve jobs onto, for example: queues:foo:reserved
     * KEYS[3] - The "attempt" queue we record jobs' attempt number, for example: queues:foo:attempts
     * ARGV[1] - The UNIX timestamp when job's handle timeout
     *
     * @return string
     */
    public static function pop(): string
    {
        return <<<'LUA'
-- Pop a job from queue...
local id = redis.call('rpop', KEYS[1])
if (id ~= false) then
    -- Add the job onto the "reserved" set and add attempt time of the job...
    redis.call('zadd', KEYS[2], ARGV[1], id)
    redis.call('hincrby', KEYS[3], id, 1)
end    
return id
LUA;
    }

    /**
     * Get the Lua script for releasing reserved jobs with delay.
     *
     * KEYS[1] - The "delayed" queue we release jobs onto, for example: queues:foo:delayed
     * KEYS[2] - The queue the jobs are currently on, for example: queues:foo:reserved
     * ARGV[1] - The id of delayed job will be added onto the "delayed" queue
     * ARGV[2] - The UNIX timestamp at which the job should become available
     *
     * @return string
     */
    public static function release(): string
    {
        return <<<'LUA'
-- Remove the job from the current queue...
redis.call('zrem', KEYS[2], ARGV[1])

-- Add the job onto the "delayed" queue...
redis.call('zadd', KEYS[1], ARGV[2], ARGV[1])

return true
LUA;
    }

    /**
     * Get the Lua script for releasing reserved jobs with delay.
     *
     * KEYS[1] - The "delayed" queue we release jobs onto, for example: queues:foo:delayed
     * KEYS[2] - The queue the jobs are currently on, for example: queues:foo:fail
     * ARGV[1] - The id of delayed job will be added onto the "delayed" queue
     * ARGV[2] - The UNIX timestamp at which the job should become available
     *
     * @return string
     */
    public static function reloadFail(): string
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
     * Get the Lua script for failing specific job.
     *
     * KEYS[1] - The "failed" set we record jobs to, for example: queues:foo:failed
     * KEYS[2] - The queue the jobs are currently on, for example: queues:foo:reserved
     * ARGV[1] - The id of failed job will be added onto the "failed" set
     * ARGV[2] - Payload of failed job execution
     *
     * @return string
     */
    public static function fail(): string
    {
        return <<<'LUA'
if (ARGV[1] ~= nil) then
    redis.call('hset', KEYS[1], ARGV[1], ARGV[2])
    redis.call('zrem', KEYS[2], ARGV[1])
end
LUA;
    }

    /**
     * Get the Lua script for removing specific job.
     *
     * KEYS[1] - The queue the jobs are currently on, for example: queues:foo:reserved
     * KEYS[2] - The queue the jobs' attempts stored set, for example: queues:foo:attempts
     * KEYS[3] - The queue the jobs' message stored set, for example: queues:foo:messages
     * ARGV[1] - The id of the job will be removed from queue
     *
     * @return string
     */
    public static function remove(): string
    {
        return <<< 'LUA'
if (ARGV[1] ~= nil) then
    redis.call('zrem', KEYS[1], ARGV[1])
    redis.call('hdel', KEYS[2], ARGV[1])
    redis.call('hdel', KEYS[3], ARGV[1])
    redis.call('hdel', KEYS[4], ARGV[1])
end
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
    public static function migrateExpiredJobs(): string
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
