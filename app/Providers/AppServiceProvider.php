<?php

namespace App\Providers;

use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   */
  public function register(): void
  {
    //
  }

  /**
   * Bootstrap any application services.
   */
  public function boot(): void
  {
    // Fix: Laravel's ServeCommand passthrough list uses 'SYSTEMROOT' (uppercase),
    // but Windows exposes the variable as 'SystemRoot' (mixed case). The case-sensitive
    // in_array() check fails to match, stripping SystemRoot from the child process
    // environment. Without it, PHP's built-in server cannot bind to any address.
    ServeCommand::$passthroughVariables[] = 'SystemRoot';
  }
}
