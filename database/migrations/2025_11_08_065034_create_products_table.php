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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('unique_key')->unique();
            $table->string('product_title')->nullable();
            $table->text('product_description')->nullable();
            $table->string('style_number')->nullable();
            $table->string('available_sizes')->nullable();
            $table->string('brand_logo_image')->nullable();
            $table->string('thumbnail_image')->nullable();
            $table->string('color_swatch_image')->nullable();
            $table->string('product_image')->nullable();
            $table->string('spec_sheet')->nullable();
            $table->string('price_text')->nullable();
            $table->decimal('suggested_price', 10, 2)->nullable();
            $table->string('category_name')->nullable();
            $table->string('subcategory_name')->nullable();
            $table->string('color_name')->nullable();
            $table->string('color_square_image')->nullable();
            $table->string('color_product_image')->nullable();
            $table->string('color_product_image_thumbnail')->nullable();
            $table->string('size')->nullable();
            $table->integer('qty')->nullable();
            $table->decimal('piece_weight', 10, 2)->nullable();
            $table->decimal('piece_price', 10, 2)->nullable();
            $table->decimal('dozens_price', 10, 2)->nullable();
            $table->decimal('case_price', 10, 2)->nullable();
            $table->string('price_group')->nullable();
            $table->integer('case_size')->nullable();
            $table->string('inventory_key')->nullable();
            $table->integer('size_index')->nullable();
            $table->string('sanmar_mainframe_color')->nullable();
            $table->string('mill')->nullable();
            $table->string('product_status')->nullable();
            $table->string('companion_styles')->nullable();
            $table->decimal('msrp', 10, 2)->nullable();
            $table->string('map_pricing')->nullable();
            $table->string('front_model_image_url')->nullable();
            $table->string('back_model_image')->nullable();
            $table->string('front_flat_image')->nullable();
            $table->string('back_flat_image')->nullable();
            $table->string('product_measurements')->nullable();
            $table->string('pms_color')->nullable();
            $table->string('gtin')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
