<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The company profile: who the business is beyond its tax details — the
 * words on the letterhead, the mission and vision, and the logo, official
 * stamp and authorised signature printed on issued documents. The images
 * live on the private disk; only their paths are kept here. Email and phone
 * reuse the contact columns the onboarding already fills.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('tagline', 200)->nullable()->after('legal_name');
            $table->text('about')->nullable()->after('tagline');
            $table->text('mission')->nullable()->after('about');
            $table->text('vision')->nullable()->after('mission');
            $table->json('core_values')->nullable()->after('vision');
            $table->json('services')->nullable()->after('core_values');
            $table->string('website', 200)->nullable()->after('services');
            $table->string('physical_address', 500)->nullable()->after('website');
            $table->string('postal_address', 200)->nullable()->after('physical_address');
            $table->string('logo_path')->nullable()->after('postal_address');
            $table->string('stamp_path')->nullable()->after('logo_path');
            $table->string('signature_path')->nullable()->after('stamp_path');
            $table->string('signatory_name', 150)->nullable()->after('signature_path');
            $table->string('signatory_title', 150)->nullable()->after('signatory_name');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', fn (Blueprint $table) => $table->dropColumn([
            'tagline', 'about', 'mission', 'vision', 'core_values', 'services', 'website', 'physical_address',
            'postal_address', 'logo_path', 'stamp_path', 'signature_path', 'signatory_name', 'signatory_title',
        ]));
    }
};
