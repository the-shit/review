<?php

namespace TheShit\Review;

use Illuminate\Support\ServiceProvider;

class ReviewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/review.php', 'review');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/review.php' => config_path('review.php'),
            ], 'review-config');
        }
    }
}
