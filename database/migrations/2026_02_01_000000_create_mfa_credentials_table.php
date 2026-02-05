<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfa_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('User ID');
            $table->string('type', 32)->default('totp')->comment('Type of MFA credential');
            $table->text('secret')->comment('Secret key for MFA credential');
            $table->timestamp('enabled_at')->nullable()->comment('Timestamp when MFA credential is enabled');
            $table->timestamp('last_used_at')->nullable()->comment('Timestamp when MFA credential was last used');
            $table->string('last_used_otp', 16)->nullable()->comment('Last used OTP');
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index('enabled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfa_credentials');
    }
};
