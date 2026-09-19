<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class OrderServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Domain\Orders\OrderRepository::class,
            \App\Infrastructure\Orders\EloquentOrderRepository::class
        );

        $this->app->bind(
            \App\Domain\Products\ProductRepository::class,
            \App\Infrastructure\Products\EloquentProductRepository::class
        );
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        //
    }
}
