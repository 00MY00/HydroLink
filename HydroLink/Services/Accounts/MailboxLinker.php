<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Accounts;

use App\Mailbox;
use Modules\HydroLink\Entities\Account;
use Modules\HydroLink\Services\Process\Ports;
use Modules\HydroLink\Services\Support\Settings;

/**
 * Creates or links the FreeScout mailbox of an account: IMAP and SMTP on 127.0.0.1 (hydroxide), encryption "None"
 * (hydroxide allows plain authentication without TLS, and only listens locally), user name = the Proton address,
 * password = the bridge password (FreeScout encrypts in_password / out_password itself, app/Mailbox.php).
 */
final class MailboxLinker
{
    public static function link(Account $account, string $bridge): Mailbox
    {
        $mailbox = $account->mailbox_id ? Mailbox::find($account->mailbox_id) : null;
        $mailbox = $mailbox ?: Mailbox::where('email', $account->email)->first();
        if (!$mailbox) {
            $mailbox = new Mailbox();
            $mailbox->email = $account->email;
            $mailbox->name = $account->name ?: self::name($account->email);
        }
        $mailbox->fill(self::settings($account->email, $bridge));
        $mailbox->save();
        if (!$account->mailbox_id) {
            $account->mailbox_id = $mailbox->id;
            $account->save();
        }

        return $mailbox;
    }

    public static function settings(string $email, string $bridge): array
    {
        return [
            'in_protocol'      => Mailbox::IN_PROTOCOL_IMAP,
            'in_server'        => Ports::HOST,
            'in_port'          => Settings::imapPort(),
            'in_username'      => $email,
            'in_password'      => $bridge,
            'in_encryption'    => Mailbox::IN_ENCRYPTION_NONE,
            'in_validate_cert' => false,
            'out_method'       => Mailbox::OUT_METHOD_SMTP,
            'out_server'       => Ports::HOST,
            'out_port'         => Settings::smtpPort(),
            'out_username'     => $email,
            'out_password'     => $bridge,
            'out_encryption'   => Mailbox::OUT_ENCRYPTION_NONE,
        ];
    }

    /** Stops FreeScout from fetching / sending through hydroxide (the mailbox and its tickets are kept). */
    public static function unlink(Account $account): void
    {
        $mailbox = $account->mailbox_id ? Mailbox::find($account->mailbox_id) : null;
        if ($mailbox && $mailbox->in_server === Ports::HOST && $mailbox->in_username === $account->email) {
            $mailbox->in_password = '';
            $mailbox->out_password = '';
            $mailbox->save();
        }
    }

    /** "support@example.com" => "support" (mailbox names are limited to 40 characters by FreeScout). */
    public static function name(string $email): string
    {
        $local = strstr($email, '@', true);

        return mb_substr($local !== false && $local !== '' ? $local : $email, 0, 40);
    }
}
