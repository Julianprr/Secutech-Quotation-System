<?php

/*
 * Anthropic API configuration.
 *
 * Real key is NOT stored in this file, so it's safe to commit to
 * a public repository like GitHub.
 *
 * Loaded from one of two places (in this order):
 *
 *   1. config/api.local.php - a git-ignored file with your real key.
 *                              Copy config/api.local.example.php to create it.
 *
 *   2. Environment variable  - ANTHROPIC_API_KEY
 */

$local_config = __DIR__ . '/api.local.php';

if (file_exists($local_config)) {
    require $local_config;
} else {
    if (!defined('ANTHROPIC_API_KEY')) {
        define('ANTHROPIC_API_KEY', getenv('ANTHROPIC_API_KEY') ?: '');
    }
}

/*
 * Which Claude model the assistant uses.
 *
 * claude-sonnet-5        - best accuracy for parsing quote details (default)
 * claude-haiku-4-5-20251001 - faster and cheaper, still solid for this task
 */
if (!defined('CLAUDE_MODEL')) {
    define('CLAUDE_MODEL', 'claude-sonnet-5');
}
