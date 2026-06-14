<?php
/**
 * Simple profiler output handler
 * Can be called at the end of any page to display timing breakdown
 */
if (!function_exists('profiler_output')) {
    function profiler_output(): void
    {
        if (class_exists('Profiler') && method_exists('Profiler', 'getReport')) {
            $report = Profiler::getReport();
            // Only show in debug mode or for administrators
            if (isset($_GET['debug']) || (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'Super Admin')) {
                echo "<!--\n";
                echo $report;
                echo "\n-->";
            }
            // Also log to error log for server-side review
            error_log("=== PAGE PROFILING REPORT ===\n" . $report);
        }
    }
}