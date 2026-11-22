<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keep-alive of the accounts: time of the last successful action and the code of the last failure. Nullable: the
 * previous version keeps working with these columns (module.json "updateGuard": {"migrations": "backward-compatible"}).
 */
class AddKeepaliveToHydrolinkAccounts extends Migration
{
    public function up()
    {
        if (Schema::hasTable('hydrolink_accounts') && !Schema::hasColumn('hydrolink_accounts', 'keepalive_at')) {
            Schema::table('hydrolink_accounts', function (Blueprint $table) {
                $table->timestamp('keepalive_at')->nullable();
                $table->string('keepalive_code', 16)->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('hydrolink_accounts', 'keepalive_at')) {
            Schema::table('hydrolink_accounts', function (Blueprint $table) {
                $table->dropColumn(['keepalive_at', 'keepalive_code']);
            });
        }
    }
}
