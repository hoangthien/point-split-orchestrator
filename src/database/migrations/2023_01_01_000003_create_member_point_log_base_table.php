
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // This is just a reference table structure
        // The actual data will be stored in partitioned tables
        // We're only creating this as a reference, it won't be used for actual data
        if (!Schema::hasTable('member_point_log_reference')) {
            Schema::create('member_point_log_reference', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('member_id');
                $table->unsignedBigInteger('customer_id');
                $table->integer('points');
                $table->string('description')->nullable();
                $table->string('type')->nullable();
                $table->timestamps();
                
                $table->foreign('member_id')->references('id')->on('members');
                $table->foreign('customer_id')->references('id')->on('customers');
                $table->index('member_id');
                $table->index('customer_id');
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_point_log_reference');
    }
};
