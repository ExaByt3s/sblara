<?php

/*
  |--------------------------------------------------------------------------
  | Web Routes
  |--------------------------------------------------------------------------
  |
  | Here is where you can register web routes for your application. These
  | routes are loaded by the RouteServiceProvider within a group which
  | contains the "web" middleware group. Now create something great!
  |
 */
Route::get('my-page', function(){
    return Response::make('Hello!')->setTtl(60); // Cache 1 minute
});
Route::get('/', function () {
    //return view('dashboard');
    return response()->view('dashboard')->setTtl(60);
});

Route::get('/test', function () {
    return view('test');
});


Route::get('/taChart', 'DataBanksEodController@panel');
Route::get('/ta/ajax/{reportrange?}/{instrument?}/{comparewith?}/{Indicators?}/{configure?}/{charttype?}/{overlay?}/{mov1?}/{avgPeriod1?}/{mov2?}/{avgPeriod2?}/{adj?}/', 'DataBanksEodController@chart_img_trac');
Route::get('/getchart/{img}', 'DataBanksEodController@getchart');


Route::get('/dd', 'TestController@funtest');
Route::get('/monitor', function () {
    return view('monitor');
});

Route::get('/ajax/monitor/{inst_id}/{period}/{day_before?}', 'AjaxController@monitor')->name('Ajax.Monitor');
Route::get('/ajax/yDay/{inst_id}/{period}', 'AjaxController@yDay')->name('Ajax.yDay');

Route::get('/ajax/market/{inst_id}', 'AjaxController@market')->name('Ajax.Market');

Route::post('/monitor/save_data', 'AjaxController@saveData')->name('Ajax.saveData');


//trading view
Route::get('/config', function () {
    return '{"supports_search":true,"supports_group_request":false,"supports_marks":true,"supports_timescale_marks":true,"supports_time":true,"exchanges":[{"value":"","name":"All Exchanges","desc":""},{"value":"XETRA","name":"XETRA","desc":"XETRA"},{"value":"NSE","name":"NSE","desc":"NSE"},{"value":"NasdaqNM","name":"NasdaqNM","desc":"NasdaqNM"},{"value":"NYSE","name":"NYSE","desc":"NYSE"},{"value":"CDNX","name":"CDNX","desc":"CDNX"},{"value":"Stuttgart","name":"Stuttgart","desc":"Stuttgart"}],"symbolsTypes":[{"name":"All types","value":""},{"name":"Stock","value":"stock"},{"name":"Index","value":"index"}],"supportedResolutions":["D","2D","3D","W","3W","M","6M"]}';
});

Route::get('/time', function () {
    return time();
});

//tradingview function to get symbol details

Route::get('symbols/', 'TradingViewController@symbols');

//tradingview function to search symbol
//https://demo_feed.tradingview.com/search?limit=30&query=A&type=&exchange=
Route::get('search/', 'TradingViewController@search');


//https://demo_feed.tradingview.com/history?symbol=ABB&resolution=D&from=1491726479&to=1492590479
Route::get('history/', 'TradingViewController@history');

Route::get('/ajax', function () {
    return 786;
});
Auth::routes();

Route::get('/home', 'HomeController@index');
Route::resource('/portfolio', 'PortfolioController');
Route::resource('/portfolio_transaction', 'PortfolioTransactionController');
Route::get('/portfolio_diversity/{portfolio_id}', 'PortfolioController@diversity');
Route::get('/portfolio_market_summary/{portfolio_id}', 'PortfolioController@marketSummary');
Route::get('/portfolio_gain_loss/{portfolio_id}', 'PortfolioController@gainLoss');
Route::get('/portfolio_performance/{portfolio_id}', 'PortfolioController@performance');
Route::post('search_json', 'SearchController@search');
