<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * includes/flash.php
 *
 * One-shot messages carried across a redirect (the Post/Redirect/Get
 * pattern). Without this the browser would re-post the form on refresh,
 * which is how duplicate votes happen in badly written systems.
 *
 *   flash('success', 'Election created.');
 *   redirect('admin/elections.php');
 *
 * The header renders whatever is waiting and clears it.
 */

/** Queue a message for the next page load. */
function flash(string $type, string $message): void
{
    $allowed = ['success', 'danger', 'warning', 'info'];
    if (!in_array($type, $allowed, true)) {
        $type = 'info';
    }

    if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
        $_SESSION['_flash'] = [];
    }

    // De-duplicate identical messages
    foreach ($_SESSION['_flash'] as $existing) {
        if (($existing['message'] ?? '') === $message) {
            return;
        }
        // Don't add generic "Please sign in to continue" if a specific session timeout notice is already queued
        if ($message === 'Please sign in to continue.' && str_contains((string) ($existing['message'] ?? ''), 'signed out')) {
            return;
        }
    }

    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** Queue a success message. */
function flash_success(string $message): void
{
    flash('success', $message);
}

/** Queue an error message. */
function flash_error(string $message): void
{
    flash('danger', $message);
}

/** Queue a warning message. */
function flash_warning(string $message): void
{
    flash('warning', $message);
}

/** Queue a neutral note. */
function flash_info(string $message): void
{
    flash('info', $message);
}

/** Take every queued message and clear the queue. */
function take_flashes(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);

    return is_array($messages) ? $messages : [];
}

/** Icon name that pairs with each flash type. */
function flash_icon(string $type): string
{
    return match ($type) {
        'success' => 'check-circle',
        'danger'  => 'x-circle',
        'warning' => 'alert-triangle',
        default   => 'info',
    };
}
