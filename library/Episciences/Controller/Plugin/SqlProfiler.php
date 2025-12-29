<?php

/**
 * Front Controller Plugin to add SQL profiler stats to HTTP headers
 * This makes profiler data visible in browser DevTools for AJAX requests
 */
class Episciences_Controller_Plugin_SqlProfiler extends Zend_Controller_Plugin_Abstract
{
    /**
     * Called after the action is dispatched
     * Adds SQL profiler statistics to HTTP response headers
     *
     * @param Zend_Controller_Request_Abstract $request
     */
    public function postDispatch(Zend_Controller_Request_Abstract $request)
    {
        try {
            // Get main DB profiler
            $mainAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
            $mainProfiler = $mainAdapter->getProfiler();
            $mainEnabled = $mainProfiler->getEnabled();

            if (!$mainEnabled) {
                return; // Profiler disabled, nothing to do
            }

            $mainQueries = $mainProfiler->getTotalNumQueries();
            $mainTime = round($mainProfiler->getTotalElapsedSecs(), 4);

            // Get CAS DB profiler if available
            $casQueries = 0;
            $casTime = 0;
            try {
                $casAdapter = Ccsd_Db_Adapter_Cas::getAdapter();
                $casProfiler = $casAdapter->getProfiler();
                if ($casProfiler && $casProfiler->getEnabled()) {
                    $casQueries = $casProfiler->getTotalNumQueries();
                    $casTime = round($casProfiler->getTotalElapsedSecs(), 4);
                }
            } catch (Exception $e) {
                // CAS not available, skip
            }

            // Calculate totals
            $totalQueries = $mainQueries + $casQueries;
            $totalTime = round($mainTime + $casTime, 4);

            // Add custom headers (visible in browser DevTools Network tab)
            $response = $this->getResponse();
            $response->setHeader('X-SQL-Queries-Main', $mainQueries, true);
            $response->setHeader('X-SQL-Time-Main', $mainTime . 's', true);

            if ($casQueries > 0) {
                $response->setHeader('X-SQL-Queries-CAS', $casQueries, true);
                $response->setHeader('X-SQL-Time-CAS', $casTime . 's', true);
            }

            $response->setHeader('X-SQL-Queries-Total', $totalQueries, true);
            $response->setHeader('X-SQL-Time-Total', $totalTime . 's', true);

            // Add a summary header for quick view
            $summary = "Total: {$totalQueries} queries ({$totalTime}s) | Main: {$mainQueries} ({$mainTime}s)";
            if ($casQueries > 0) {
                $summary .= " | CAS: {$casQueries} ({$casTime}s)";
            }
            $response->setHeader('X-SQL-Summary', $summary, true);

        } catch (Exception $e) {
            // Silently fail - don't break the application if profiler has issues
            error_log('SqlProfiler plugin error: ' . $e->getMessage());
        }
    }
}
