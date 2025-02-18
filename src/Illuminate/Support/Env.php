<?php

namespace Illuminate\Support;

use Dotenv\Repository\Adapter\PutenvAdapter;
use Dotenv\Repository\RepositoryBuilder;
use PhpOption\None;
use PhpOption\Option;
use RuntimeException;

class Env
{
    /**
     * Indicates if the putenv adapter is enabled.
     *
     * @var bool
     */
    protected static $putenv = true;

    /**
     * The environment repository instance.
     *
     * @var \Dotenv\Repository\RepositoryInterface|null
     */
    protected static $repository;

    /**
     * Enable the putenv adapter.
     *
     * @return void
     */
    public static function enablePutenv()
    {
        static::$putenv = true;
        static::$repository = null;
    }

    /**
     * Disable the putenv adapter.
     *
     * @return void
     */
    public static function disablePutenv()
    {
        static::$putenv = false;
        static::$repository = null;
    }

    /**
     * Get the environment repository instance.
     *
     * @return \Dotenv\Repository\RepositoryInterface
     */
    public static function getRepository()
    {
        if (static::$repository === null) {
            $builder = RepositoryBuilder::createWithDefaultAdapters();

            if (static::$putenv) {
                $builder = $builder->addAdapter(PutenvAdapter::class);
            }

            static::$repository = $builder->immutable()->make();
        }

        return static::$repository;
    }

    /**
     * Get the value of an environment variable with optional fallback keys.
     *
     * @param  string|array  $key
     * @param  mixed  $default
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        return self::getOption($key)->getOrCall(fn () => value($default));
    }

    /**
     * Get the value of a required environment variable with optional fallback keys.
     *
     * @param  string|array  $key
     * @return mixed
     *
     * @throws \RuntimeException
     */
    public static function getOrFail($key)
    {
        $keyRef = is_array($key) ? reset($key) : $key;
        return self::getOption($key)->getOrThrow(new RuntimeException("Environment variable [$keyRef] has no value."));
    }

    /**
     * Get the possible option for this environment variable with optional fallback keys.
     * Recursively processes the array keys until a value is found.
     *
     * @param  string|array  $key
     * @return \PhpOption\Option|\PhpOption\Some
     */
    protected static function getOption($key)
    {
        $value = Option::fromValue(static::getRepository()->get(is_array($key) ? array_shift($key) : $key))
            ->map(function ($value) {
                switch (strtolower($value)) {
                    case 'true':
                    case '(true)':
                        return true;
                    case 'false':
                    case '(false)':
                        return false;
                    case 'empty':
                    case '(empty)':
                        return '';
                    case 'null':
                    case '(null)':
                        return;
                }

                if (preg_match('/\A([\'"])(.*)\1\z/', $value, $matches)) {
                    return $matches[2];
                }

                return $value;
            });


        if (($value instanceof None || $value->get() === null) && is_array($key) && count($key) > 0) {
            return static::getOption($key);
        }

        return $value;
    }
}
