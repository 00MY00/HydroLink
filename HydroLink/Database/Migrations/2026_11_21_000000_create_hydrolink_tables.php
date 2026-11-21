<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * hydrolink_accounts: one row per Proton account (no Proton password: hydroxide keeps its own encrypted session).
 * hydrolink_alerts:   one row per problem key, for the anti-spam of the notifications.
 */
class CreateHydrolinkTables extends Migration
{
    public function up()
    {
        Schema::create('hydrolink_accounts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email', 191)->unique();
            $table->unsignedInteger('mailbox_id')->nullable();
            $table->text('bridge_password')->nullable();   // encrypted with APP_KEY
            $table->string('status', 32)->default('pending');
            $table->string('error_code', 16)->nullable();
            $table->text('error_detail')->nullable();
            $table->text('captcha_url')->nullable();
            $table->boolean('paused')->default(false);
            $table->unsignedSmallInteger('api_errors')->default(0);
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('hydrolink_alerts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('alert_key', 100)->unique();
            $table->string('code', 16);
            $table->boolean('active')->default(true);
            $table->text('detail')->nullable();
            $table->timestamp('since')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('hydrolink_alerts');
        Schema::dropIfExists('hydrolink_accounts');
    }
}
