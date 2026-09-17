<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('pm_conversations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_low_id');
            $table->unsignedInteger('user_high_id');
            $table->unsignedInteger('last_message_id')->nullable();
            $table->unsignedInteger('last_user_id')->nullable();
            $table->string('last_message_preview', 160)->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['user_low_id', 'user_high_id']);
            $table->foreign('user_low_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('user_high_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('last_user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('last_message_at');
        });

        $schema->create('pm_conversation_states', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('conversation_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->foreign('conversation_id')->references('id')->on('pm_conversations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'hidden_at']);
        });

        $schema->create('pm_messages', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('conversation_id');
            $table->unsignedInteger('user_id');
            $table->text('content');
            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on('pm_conversations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['conversation_id', 'created_at']);
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('pm_messages');
        $schema->dropIfExists('pm_conversation_states');
        $schema->dropIfExists('pm_conversations');
    },
];
