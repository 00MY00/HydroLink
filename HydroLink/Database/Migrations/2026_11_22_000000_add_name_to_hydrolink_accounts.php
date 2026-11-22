<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Name given to an account in the panel (also the name of its FreeScout mailbox). Nullable: the previous version
 * keeps working with this column (module.json "updateGuard": {"migrations": "backward-compatible"}).
 */
class AddNameToHydrolinkAccounts extends Migration
{
    public function up()
    {
        if (Schema::hasTable('hydrolink_accounts') && !Schema::hasColumn('hydrolink_accounts', 'name')) {
            Schema::table('hydrolink_accounts', function (Blueprint $table) {
                $table->string('name', 40)->nullable()->after('email');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('hydrolink_accounts', 'name')) {
            Schema::table('hydrolink_accounts', function (Blueprint $table) {
                $table->dropColumn('name');
            });
        }
    }
}
