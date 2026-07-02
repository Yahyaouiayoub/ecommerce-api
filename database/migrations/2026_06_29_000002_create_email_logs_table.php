<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_email');
            $table->string('subject')->nullable();
            $table->string('mailable_type')->nullable()->comment('Class name of the mailable (e.g. App\\Mail\\OrderConfirmationMail)');
            $table->string('mailer_driver')->nullable()->comment('Actual mail driver used at send time (smtp, log, etc.)');
            $table->string('status', 20)->default('sent')->comment('sent, failed');
            $table->text('error_message')->nullable();
            $table->json('headers')->nullable();
            $table->nullableMorphs('related'); // polymorphic: order_id, invoice_id, etc.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
