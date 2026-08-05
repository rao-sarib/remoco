<?php
/**
 * Front door.
 *
 * main.php is the public landing page, so requesting the site root serves it
 * directly rather than redirecting. Keeping the entry point as its own file means
 * the document root can be pointed at public/ and everything resolves from there.
 */
require __DIR__ . '/main.php';
