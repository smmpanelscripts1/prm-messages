<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->table('pm_conversations', function (Blueprint $table) {
            $table->index('user_low_id', 'pm_conversations_user_low_id_index');
        });

        $schema->table('pm_conversations', function (Blueprint $table) {
            $table->dropUnique('pm_conversations_user_low_id_user_high_id_unique');
        });

        if (! $schema->hasColumn('pm_conversations', 'subject')) {
            $schema->table('pm_conversations', function (Blueprint $table) {
                $table->string('subject', 160)->nullable()->after('user_high_id');
            });
        }
    },
    'down' => function (Builder $schema) {
        $schema->table('pm_conversations', function (Blueprint $table) {
            $table->unique(['user_low_id', 'user_high_id']);
            $table->dropIndex('pm_conversations_user_low_id_index');
        });

        if ($schema->hasColumn('pm_conversations', 'subject')) {
            $schema->table('pm_conversations', function (Blueprint $table) {
                $table->dropColumn('subject');
            });
        }
    },
];
