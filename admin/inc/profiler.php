<?php
/**
 * Simple profiler to measure time spent in different parts of the request lifecycle
 */
class Profiler {
    private static $startTime = null;
    private static $checkpoints = [];
    private static $labels = [];
    
    public static function start() {
        self::$startTime = microtime(true);
        self::$checkpoints = [self::$startTime];
        self::$labels = ['start'];
    }
    
    public static function checkpoint($label) {
        $now = microtime(true);
        self::$checkpoints[] = $now;
        self::$labels[] = $label;
    }
    
    public static function getReport() {
        if (self::$startTime === null) {
            return "Profiler not started";
        }
        
        $totalTime = microtime(true) - self::$startTime;
        $report = [];
        $report[] = "=== PROFILER REPORT ===";
        $report[] = "Total Time: " . round($totalTime * 1000, 2) . "ms";
        $report[] = "";
        
        for ($i = 1; $i < count(self::$checkpoints); $i++) {
            $duration = self::$checkpoints[$i] - self::$checkpoints[$i-1];
            $label = self::$labels[$i];
            $report[] = sprintf("%-30s: %8.2fms", $label, $duration * 1000);
        }
        
        $report[] = "";
        $report[] = "Checkpoints: " . implode(" | ", self::$labels);
        
        return implode("\n", $report);
    }
    
    public static function getCheckpointTimes() {
        if (self::$startTime === null) {
            return [];
        }
        
        $times = [];
        for ($i = 1; $i < count(self::$checkpoints); $i++) {
            $duration = self::$checkpoints[$i] - self::$checkpoints[$i-1];
            $label = self::$labels[$i];
            $times[$label] = $duration;
        }
        return $times;
    }
}