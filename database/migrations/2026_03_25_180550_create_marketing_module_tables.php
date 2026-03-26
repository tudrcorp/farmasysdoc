<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('discount_type')->default('percent');
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('marketing_email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->longText('body_html');
            $table->text('body_plain')->nullable();
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('marketing_contents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->nullable()->unique();
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->string('promo_type')->default('banner')->index();
            $table->string('cta_label')->nullable();
            $table->string('cta_url', 2048)->nullable();
            $table->string('image_path', 2048)->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_utm_links', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('base_url');
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();
            $table->text('full_url')->nullable();
            $table->unsignedBigInteger('clicks_count')->default(0);
            $table->timestamps();
        });

        Schema::create('marketing_segments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('marketing_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('promotion')->index();
            $table->string('send_mode')->default('all')->index();
            $table->foreignId('marketing_campaign_id')->nullable()->constrained('marketing_campaigns')->nullOnDelete();
            $table->foreignId('marketing_email_template_id')->nullable()->constrained('marketing_email_templates')->nullOnDelete();
            $table->foreignId('marketing_segment_id')->nullable()->constrained('marketing_segments')->nullOnDelete();
            $table->json('channels')->nullable();
            $table->string('subject')->nullable();
            $table->longText('email_html')->nullable();
            $table->text('whatsapp_body')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_broadcast_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_broadcast_id')->constrained('marketing_broadcasts')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['marketing_broadcast_id', 'client_id'], 'mkt_bc_clients_broadcast_client_unique');
        });

        Schema::create('marketing_broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_broadcast_id')->constrained('marketing_broadcasts')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('email_status')->default('pending')->index();
            $table->string('whatsapp_status')->default('pending')->index();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('whatsapp_sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->unique(['marketing_broadcast_id', 'client_id'], 'mkt_bc_rcpts_broadcast_client_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_broadcast_recipients');
        Schema::dropIfExists('marketing_broadcast_clients');
        Schema::dropIfExists('marketing_broadcasts');
        Schema::dropIfExists('marketing_segments');
        Schema::dropIfExists('marketing_utm_links');
        Schema::dropIfExists('marketing_contents');
        Schema::dropIfExists('marketing_email_templates');
        Schema::dropIfExists('marketing_coupons');
        Schema::dropIfExists('marketing_campaigns');
    }
};
