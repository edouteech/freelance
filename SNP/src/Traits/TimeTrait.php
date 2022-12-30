<?php

namespace App\Traits;

trait TimeTrait
{
    private function formatMilliseconds($milliseconds)
    {
        $seconds = floor($milliseconds / 1000);
        $minutes = floor($seconds / 60);
        $hours = floor($minutes / 60);
        $milliseconds = $milliseconds % 1000;
        $seconds = $seconds % 60;
        $minutes = $minutes % 60;

        return sprintf('%dH%dm%d', $hours, $minutes, $hours);
    }
}