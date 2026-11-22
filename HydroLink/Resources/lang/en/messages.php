<?php

return [
    'description' => 'Free Proton Mail accounts in FreeScout, through a local hydroxide bridge managed by the cron.',
    'disclaimer'  => 'Independent project, not affiliated with Proton AG. Proton and Proton Mail are trademarks of Proton AG. Unofficial API: test with a secondary address first.',

    // statuses
    'st_ok' => 'OK', 'st_problem' => 'Problem', 'st_none' => 'None', 'st_check' => 'Check', 'st_missing' => 'Missing',
    'st_update' => 'Update', 'st_off' => 'Off', 'st_on' => 'On',
    'st_alerts' => '{1} :n alert|[2,*] :n alerts',
    'svc_running' => 'Running', 'svc_starting' => 'Starting', 'svc_failed' => 'Failed', 'svc_stopped' => 'Stopped',
    'acc_pending' => 'Waiting', 'acc_running' => 'Signing in', 'acc_waiting_2fa' => '2FA code', 'acc_waiting_captcha' => 'Verification',
    'acc_needs_password' => 'Password', 'acc_connected' => 'Connected', 'acc_error' => 'Error', 'acc_removing' => 'Removing',
    'acc_paused' => 'Paused',

    // service card
    'service' => 'Service', 'cron' => 'Cron', 'cron_ok' => 'Running', 'cron_missing' => 'Missing',
    'start' => 'Start', 'stop' => 'Stop', 'restart' => 'Restart',
    'env_details' => 'Environment', 'env_proc_open' => 'proc_open (CLI)', 'env_exec' => 'Execution in storage/',
    'env_writable' => 'storage/ writable', 'env_arch' => 'Architecture',
    'queued_start' => 'Start requested: the cron handles it within a minute.',
    'queued_stop' => 'Stop requested: the cron handles it within a minute.',
    'queued_restart' => 'Restart requested: the cron handles it within a minute.',

    // accounts
    'rename' => 'Rename', 'name' => 'Name', 'save' => 'Save', 'renamed' => 'Name saved.', 'mailbox_link' => 'FreeScout mailbox',
    'rename_hint' => 'Shown on this card and given to the FreeScout mailbox (40 characters at most). Empty: default name.',
    'name_too_long' => 'The name is limited to 40 characters.', 'name_invalid' => 'The name contains characters that are not allowed.',
    'accounts' => 'Accounts', 'settings_title' => 'Settings',
    'add_account' => 'Add an account', 'address' => 'Address', 'password' => 'Password',
    'two_passwords' => 'Two-password account', 'mailbox_password' => 'Mailbox password',
    'connect' => 'Connect', 'add_hint' => 'The password is used once and is not kept by HydroLink.',
    'step_pending' => 'Waiting for the cron (less than a minute)', 'step_running' => 'Signing in to Proton',
    'totp' => '2FA code', 'send' => 'Send',
    'captcha_step' => 'Proton asks for a human verification. Open it, complete it, then confirm here.',
    'captcha_open' => 'Open the verification', 'captcha_done' => 'I completed the verification',
    'reconnect' => 'Reconnect', 'pause' => 'Pause', 'resume' => 'Resume',
    'delete' => 'Delete', 'delete_confirm' => 'Confirm deletion',
    'delete_hint' => 'The FreeScout mailbox and its tickets are kept, but it no longer uses this account.',
    'logs' => 'Logs', 'loading' => 'Loading...',
    'login_queued' => 'Sign-in requested: it starts within a minute.',
    'code_sent' => 'Code sent.', 'verification_sent' => 'Verification reported, the sign-in continues.',
    'paused' => 'Account paused: FreeScout no longer fetches this mailbox.', 'resumed' => 'Fetching resumed.',
    'removed' => 'Account deleted.', 'confirm_needed' => 'Confirmation missing.',
    'email_is_user' => 'This address belongs to a FreeScout user: a mailbox cannot have the same one.',
    'why_not_connected' => 'Available once the account is connected.', 'why_busy' => 'A sign-in is already in progress.',
    'why_no_2fa' => 'No 2FA code is expected for this account.', 'why_no_captcha' => 'No verification is expected.',
    'why_no_binary' => 'Install the binary first (Binary card).', 'why_no_account' => 'Add an account first.',

    // binary
    'binary' => 'Binary', 'installed' => 'Installed', 'available' => 'Available',
    'install' => 'Install', 'update' => 'Update', 'auto_update' => 'Automatic update',
    'why_queued' => 'Request sent: the cron handles it within a minute.',
    'queued_binary' => 'Update requested: the cron handles it within a minute.',
    'vt_waiting' => 'VirusTotal analysis in progress, the update will resume by itself.',
    'vt_step_on' => 'Each new binary: SHA-256, VirusTotal analysis, test, then rollback on failure.',
    'vt_step_off' => 'Each new binary: SHA-256, test, then rollback on failure. VirusTotal analysis skipped (no key).',

    // notifications
    'notifications' => 'Notifications', 'channels' => 'Channels', 'channel_mail' => 'FreeScout alert e-mail',
    'reminder' => 'Reminder (hours, 0 = never)', 'notify_recovery' => 'Notify the return to normal',
    'active_alerts' => 'Active alerts', 'test' => 'Test',
    'test_sent' => '{1} Test notification sent to :n channel.|[2,*] Test notification sent to :n channels.',
    'test_none' => 'No channel chosen or available.',
    'notify_problem' => 'HydroLink: :code :title', 'notify_reminder' => 'HydroLink (reminder): :code :title',
    'notify_recovered' => 'HydroLink: back to normal (:code)', 'notify_test' => 'HydroLink: test',
    'notify_recovered_text' => 'The problem :code (:title) is solved.', 'notify_test_text' => 'HydroLink notifications reach this channel.',

    // VirusTotal
    'vt_key' => 'API key', 'vt_key_set' => 'saved', 'optional' => 'Optional',
    'vt_threshold' => 'Detection threshold', 'vt_remove' => 'Remove the key',
    'vt_hint' => 'Above the threshold, the binary is quarantined and the update cancelled. The free quota is respected.',
    'vt_no_key' => 'No key saved.', 'vt_valid' => 'Valid key.', 'vt_refused' => 'Key refused by VirusTotal.',
    'vt_quota' => 'VirusTotal quota reached, try again later.', 'vt_error' => 'VirusTotal does not answer as expected.',

    // errors and generic
    'err_expected' => 'Expected', 'err_effect' => 'Effect', 'err_action' => 'Action', 'err_detail' => 'Detail',
    'saved' => 'Saved.', 'request_failed' => 'The request failed.', 'bad_request' => 'Invalid request.',
];
