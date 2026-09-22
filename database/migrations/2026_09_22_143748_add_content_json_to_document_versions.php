<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A version written in the app (an SOP started from a template) keeps its
 * text, so the next revision is edited rather than retyped. Uploaded files
 * leave it empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->json('content_json')->nullable()->after('file_name');
        });
    }

    public function down(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropColumn('content_json');
        });
    }
};
