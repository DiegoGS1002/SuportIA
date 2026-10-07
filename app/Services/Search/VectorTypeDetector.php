<?php

namespace App\Services\Search;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Detects the MySQL vector capabilities available on the current connection.
 *
 * MySQL Community Server 9.x supports the VECTOR column type along with
 * STRING_TO_VECTOR()/VECTOR_TO_STRING() conversion functions, but the
 * VECTOR_DISTANCE() similarity function is only available on MySQL
 * Enterprise/HeatWave. We detect both independently so storage always uses
 * the native VECTOR type when possible, while similarity search falls back
 * to a pure-PHP computation when VECTOR_DISTANCE() isn't available.
 */
final class VectorTypeDetector
{
    public function supportsNativeType(): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return false;
        }

        return Cache::remember('mysql_supports_vector_type', now()->addHour(), function (): bool {
            try {
                DB::selectOne("SELECT STRING_TO_VECTOR('[1,2,3]') AS v");

                return true;
            } catch (\Throwable) {
                return false;
            }
        });
    }

    public function supportsNativeDistance(): bool
    {
        if (! $this->supportsNativeType()) {
            return false;
        }

        return Cache::remember('mysql_supports_vector_distance', now()->addHour(), function (): bool {
            try {
                DB::selectOne("SELECT VECTOR_DISTANCE(STRING_TO_VECTOR('[1,2,3]'), STRING_TO_VECTOR('[1,2,4]'), 'cosine') AS v");

                return true;
            } catch (\Throwable) {
                return false;
            }
        });
    }
}
