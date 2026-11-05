<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(url(''));
csrf_verify();

$ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
$user = current_user();
$action = (string)($_POST['action'] ?? '');

function out(bool $ok, string $msg, array $extra = []): void
{
    global $ajax;
    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $msg] + $extra);
        exit;
    }
    flash_set($ok ? 'ok' : 'err', $msg);
    redirect($extra['redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? url('')));
}

/** Link tabhi milega jab user ka apna ho (ya admin ho) */
function owned_link(?array $user, int $id): ?array
{
    if (!$user) return null;
    $l = q('SELECT * FROM links WHERE id = ?', [$id])->fetch();
    return ($l && ((int)$l['user_id'] === (int)$user['id'] || $user['role'] === 'admin')) ? $l : null;
}

switch ($action) {
    case 'create':
        $limit = $user ? USER_HOURLY_LIMIT : GUEST_HOURLY_LIMIT;
        if (!rate_limit('create:' . ($user ? 'u' . $user['id'] : client_ip()), $limit, 3600)) {
            out(false, 'You have reached the guest link limit. Please try again later.');
        }
        $gt = $user ? null : guest_token(true);
        [$l, $err] = create_link($_POST, $user, $gt);
        if ($err) out(false, $err);
        $ajax ? out(true, 'Your short link is ready!', ['html' => render_result_card($l), 'code' => $l['code'], 'short_url' => short_url($l['code'])])
              : out(true, 'Your short link is ready: ' . short_url($l['code']), ['redirect' => url('dashboard.php')]);
        break;

    case 'bulk':
        if (!$user) out(false, 'Please log in to use bulk shortening.');
        $lines = preg_split('/\R/', (string)($_POST['urls'] ?? '')) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), 'strlen'));
        if (!$lines) out(false, 'Please enter at least one URL.');
        if (count($lines) > BULK_MAX) out(false, 'You can shorten a maximum of ' . BULK_MAX . ' URLs at a time.');
        if (!rate_limit('bulk:' . $user['id'], 10, 3600)) out(false, 'The bulk creation limit has been reached (10 per hour).');
        $okc = 0; $bad = [];
        foreach ($lines as $line) {
            [$l, $err] = create_link(['url' => $line], $user, null);
            if ($l) $okc++; else $bad[] = mb_strimwidth($line, 0, 40, '…') . ' (' . $err . ')';
        }
        $msg = "$okc links created.";
        if ($bad) $msg .= ' Failed: ' . implode(' | ', array_slice($bad, 0, 5)) . (count($bad) > 5 ? ' …' : '');
        out($okc > 0, $msg, ['redirect' => url('dashboard.php')]);
        break;

    case 'toggle':
        $l = owned_link($user, (int)($_POST['id'] ?? 0));
        if (!$l) out(false, 'Link not found.');
        q('UPDATE links SET is_active = 1 - is_active WHERE id = ?', [$l['id']]);
        out(true, 'Link updated successfully.');
        break;

    case 'delete':
        $l = owned_link($user, (int)($_POST['id'] ?? 0));
        if (!$l) out(false, 'Link not found.');
        q('DELETE FROM links WHERE id = ?', [$l['id']]);   // clicks FK cascade se hat jaate hain
        out(true, 'Link deleted successfully.', ['redirect' => $user['role'] === 'admin' && !empty($_POST['back']) ? url('admin.php?tab=links') : url('dashboard.php')]);
        break;

    default:
        out(false, 'Invalid action.');
}
