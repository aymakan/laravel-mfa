<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfa_verifications', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary()->comment('User ID');
            $table->timestamp('verified_at')->comment('When MFA was verified');
            $table->timestamp('expires_at')->comment('When verification expires');

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfa_verifications');
    }
};
