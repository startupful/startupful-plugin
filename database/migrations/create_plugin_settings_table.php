<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePluginSettingsTable extends Migration
{
    public function up()
    {
        Schema::create('plugin_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plugin_id')->constrained('plugins')->onDelete('cascade');
            $table->string('plugin_name');
            $table->string('key');
            $table->text('value');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('plugin_settings');
    }
}