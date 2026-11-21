<?php

declare(strict_types=1);

namespace Modules\HydroLink\Features;

/** Finds the features of this folder (every class implementing Feature), sorted by order(). */
final class Registry
{
    /** @var Feature[]|null */
    private static $features;

    /** @return Feature[] key => feature */
    public static function all(): array
    {
        if (self::$features !== null) {
            return self::$features;
        }
        $found = [];
        foreach ((array) glob(__DIR__.'/*Feature.php') as $file) {
            $class = __NAMESPACE__.'\\'.basename((string) $file, '.php');
            if ($class !== Feature::class && class_exists($class) && is_subclass_of($class, Feature::class)) {
                $feature = new $class();
                $found[$feature->key()] = $feature;
            }
        }
        uasort($found, function (Feature $a, Feature $b) {
            return $a->order() <=> $b->order();
        });

        return self::$features = $found;
    }

    public static function get(string $key): ?Feature
    {
        return self::all()[$key] ?? null;
    }

    /** Setting key => definition, across all features. */
    public static function settings(): array
    {
        $out = [];
        foreach (self::all() as $feature) {
            $out += $feature->settings();
        }

        return $out;
    }

    public static function schedule($schedule): void
    {
        foreach (self::all() as $feature) {
            $feature->schedule($schedule);
        }
    }

    public static function reset(): void
    {
        self::$features = null;
    }
}
