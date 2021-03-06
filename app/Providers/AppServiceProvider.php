<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Artisan;
use Schema;
use App\Setting;
use App\User;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        

        if(!is_file(base_path('.env'))) {
            touch(base_path('.env'));
            Artisan::call('key:generate');
        }
        if(!is_file(database_path('app.sqlite'))) {
            // first time setup
            touch(database_path('app.sqlite'));
            Artisan::call('migrate', array('--path' => 'database/migrations', '--force' => true, '--seed' => true));
            //Cache
            //Artisan::call('config:cache');
            //Artisan::call('route:cache');
        }
        if(is_file(database_path('app.sqlite'))) {
            if(Schema::hasTable('settings')) {

                // check version to see if an upgrade is needed
                $db_version = Setting::_fetch('version');
                $app_version = config('app.version');
                if(version_compare($app_version, $db_version) == 1) { // app is higher than db, so need to run migrations etc
                    Artisan::call('migrate', array('--path' => 'database/migrations', '--force' => true, '--seed' => true));                   
                }
            } else {
                Artisan::call('migrate', array('--path' => 'database/migrations', '--force' => true, '--seed' => true)); 
            }

        }
        if(!is_file(public_path('storage'))) {
            Artisan::call('storage:link');
            \Session::put('current_user', null);
        }

        // User specific settings need to go here as session isn't available at this point in the app
        view()->composer('*', function ($view) 
        {

            if(isset($_SERVER['HTTP_AUTHORIZATION']) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
                list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = 
                explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
            }
            if(!\Auth::check()) {
                if(isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
                    $credentials = ['username' => $_SERVER['PHP_AUTH_USER'], 'password' => $_SERVER['PHP_AUTH_PW']];
                    
                    if (\Auth::attempt($credentials)) {
                        // Authentication passed...
                        $user = \Auth::user();
                        //\Session::put('current_user', $user);
                        session(['current_user' => $user]);                
                    }
                }
            }


            $alt_bg = '';
            if($bg_image = Setting::fetch('background_image')) {
                $alt_bg = ' style="background-image: url(/storage/'.$bg_image.')"';
            }
            $lang = Setting::fetch('language');
            \App::setLocale($lang);

            $allusers = User::all();
            $current_user = User::currentUser();

            $view->with('alt_bg', $alt_bg );    
            $view->with('allusers', $allusers );    
            $view->with('current_user', $current_user );   

    
            
            
        });  


        if (env('FORCE_HTTPS') === true) {
            \URL::forceScheme('https');
        }

        if(env('APP_URL') != 'http://localhost') {
            \URL::forceRootUrl(env('APP_URL'));
        }

    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('settings', function () {
            return new Setting();
        });
    }
}
