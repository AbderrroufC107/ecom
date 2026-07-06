<?php
// Check if opcache is enabled and reset it
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache reset done\n";
} else {
    echo "OPcache not available\n";
}
