<?php
date_default_timezone_set('Europe/London');

/* Allow the script to hang around waiting for connections. */
set_time_limit(0);

/* Turn on implicit output flushing so we see what comes in. */
ob_implicit_flush();

// used as visual delimiter on screen
$dashed_separator = '----------------------';

// employ this as a `verbose` variable for functions
define('DEBUG', true);
