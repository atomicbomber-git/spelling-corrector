<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Sastrawi\Tokenizer\TokenizerFactory;
use Sastrawi\Tokenizer\TokenizerInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(TokenizerInterface::class, function() {
            return (new TokenizerFactory)->createDefaultTokenizer();
        });
    }
}
