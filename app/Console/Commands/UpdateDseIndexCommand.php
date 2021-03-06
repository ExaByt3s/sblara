<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use DB;
use App\Market;
use App\DataBanksEod;
use App\Repositories\InstrumentRepository;
use App\Repositories\SectorListRepository;
use App\Repositories\DataBanksIntradayRepository;
use App\Repositories\DataBankEodRepository;
Use App\DataBanksIntraday;
use App\Repositories\CorporateActionRepository;
use App\Repositories\FundamentalRepository;
use App\Repositories\MarketStatRepository;
use App\Repositories\IndexRepository;



class UpdateDseIndexCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dse:UpdateDseIndex';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetching dse index from DSE IDX tables';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */

// live server command   /opt/cpanel/ea-php70/root/usr/bin/php /home/hostingmonitors/artisan dse:UpdateDseIndex
    public function handle()
    {

        if(!Market::isMarketOpen())
        {
            $this->info('market is not open');

        }
        else
        {

            $querystr = "select * from IDX ORDER BY IDX_DATE_TIME ASC";
            $dataFromDseServer = DB::connection('dse')->select($querystr);

            $date_time = $dataFromDseServer[0]->IDX_DATE_TIME;
            $convertedTimestamp = strtotime($date_time);
            $trade_date = date('Y-m-d', $convertedTimestamp);

            $querystr = "select * from TRD";
            $tradeDataFromDseServer = DB::connection('dse')->select($querystr);

            $TRD_TOTAL_TRADES= $TRD_TOTAL_VOLUME= $TRD_TOTAL_VALUE=0;
            foreach ($tradeDataFromDseServer as $data) {

                $TRD_TOTAL_TRADES = $data->TRD_TOTAL_TRADES;
                $TRD_TOTAL_VOLUME = $data->TRD_TOTAL_VOLUME;
                $TRD_TOTAL_VALUE = $data->TRD_TOTAL_VALUE;
                $TRD_LM_DATE_TIME = date('Y-m-d H:i:s', strtotime($data->TRD_LM_DATE_TIME));
             //   $trade_time = date('H:i', strtotime($data->TRD_LM_DATE_TIME));
             //   $trade_date = date('Y-m-d', strtotime($data->TRD_LM_DATE_TIME));


            }




            // Market is open. Now we will check if dse server returning today's data. Sometimes dse return previous day data
            if($activeTradeDates=Market::validateTradeDate($trade_date))
            {
                // its returning today data. So we will proceed here
                $market_id=$activeTradeDates->id;
                $data_bank_intraday_batch=$activeTradeDates->data_bank_intraday_batch;

                $today_eod_data = DB::table('data_banks_eods')->select('instrument_id', 'volume', 'trade', 'tradevalues')->where('market_id', $market_id)->get();
                $last_intraday_data=DB::select("SELECT instruments.id as instrument_id,instruments.batch_id,total_volume,total_value,data_banks_intradays.batch,data_banks_intradays.lm_date_time FROM instruments, data_banks_intradays WHERE instruments.batch_id=data_banks_intradays.batch AND instruments.id = data_banks_intradays.instrument_id and instruments.id in (10001,10002,10003,10006) and data_banks_intradays.market_id=$market_id");
                $last_intraday_data=collect($last_intraday_data)->keyBy('instrument_id');


                $index_id=array();
                $index_id_arr=array();

                foreach($dataFromDseServer as $data)
                {
                    $instrumentList = InstrumentRepository::getInstrumentsScripWithIndex();
                    $instrument_info = $instrumentList->where('instrument_code', trim($data->IDX_INDEX_ID))->first();

                    if (!is_null($instrument_info)) {
                        $instrument_id = $instrument_info->id;
                        $index_id[$instrument_id]= $instrument_id;
                        $index_id_arr[$instrument_id]= $instrument_id;
                        $temp = array();
                        $temp['market_id'] = $market_id;
                        $temp['instrument_id'] = $instrument_id;
                        $temp['capital_value'] = $data->IDX_CAPITAL_VALUE;
                        $temp['deviation'] = $data->IDX_DEVIATION;
                        $temp['percentage_deviation'] = $data->lDX_PERCENTAGE_DEVIATION;
                        $temp['date_time'] = date('Y-m-d H:i:s', strtotime($data->IDX_DATE_TIME));
                        $temp['index_date'] = date('Y-m-d', strtotime($data->IDX_DATE_TIME));
                        $temp['index_time'] = date('H:i', strtotime($data->IDX_DATE_TIME));
                        $dataToSave[] = $temp;

                    }





                }



                $market = Market::getActiveDates(2);
                $yday_market_id = $market[1]->id;
                $index_id=collect($index_id)->implode(',');
                $yday_index_data = \DB::select("select * from data_banks_eods where market_id=$yday_market_id and instrument_id in ($index_id)");
                $yday_index_data=collect($yday_index_data)->keyBy('instrument_id');

                $today_intraday_index_data = DB::select("SELECT instrument_id,batch FROM data_banks_intradays WHERE market_id=$market_id and instrument_id in ($index_id) and batch=$data_bank_intraday_batch");
                $today_intraday_index_data=collect($today_intraday_index_data)->keyBy('instrument_id');



                if (!empty($dataToSave)) {

                    //first delete all index of trade_date
                    DB::table('index_values')->where('market_id', $market_id)->delete();

                    // re insert all index of trade date.
                    DB::table('index_values')->insert($dataToSave);

                    $this->info(count($dataToSave) . ' row inserted into index_values');

                }




                /* CALCULATING OHLC VALUES TO SAVE INTO DATA_BANK_INTRADAYS and DATA_BANK_EODS TABLE */


                $IntradayDataToSave = array();
                $dataToSave_collection=collect($dataToSave);
                $dataToSave_collection=$dataToSave_collection->groupBy('instrument_id');

                $index_ohlc=array();
                foreach($dataToSave_collection as $instrument_id=>$all_data_of_a_index)
                {
                    $collect= collect($all_data_of_a_index);

                    $first= $collect->first();
                    $index_ohlc[$instrument_id]['open']= $first['capital_value'];

                    $index_ohlc[$instrument_id]['high']= $collect->max('capital_value');
                    $index_ohlc[$instrument_id]['low']= $collect->min('capital_value');

                    $last = $collect->last();
                    $index_ohlc[$instrument_id]['close'] = $last['capital_value'];
                    $index_ohlc[$instrument_id]['yday_close_price'] = $yday_index_data[$instrument_id]->close;
                    $index_ohlc[$instrument_id]['date_time'] = $last['date_time'];


                }




                // new index? add here
                $instrument_list_of_index = FundamentalRepository::getFundamentalDataAll(array('ds30_listed','dsex_listed'));



                // new index? add here
                $instrument_list_of_dsex = collect($instrument_list_of_index['dsex_listed'])->where('meta_value', '1');
                $instrument_list_of_ds30 = collect($instrument_list_of_index['ds30_listed'])->where('meta_value', '1');


                // new index? add here
                // extracting all instrument_id
                $instrument_id_of_all_dsex_listed_company = $instrument_list_of_dsex->pluck('instrument_id');
                $instrument_id_of_all_ds30_listed_company = $instrument_list_of_ds30->pluck('instrument_id');


                ////////////////////// EOD for DSEX \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

                $dsexVolume = 0;
                $dsexTrade = 0;
                $dsexTradeValues = 0;
                foreach ($instrument_id_of_all_dsex_listed_company as $ins_id) {


                    $trade_data= $today_eod_data->where('instrument_id', $ins_id)->first();
                    if(!is_null($trade_data))
                    {
                        $dsexVolume+= $trade_data->volume;
                        $dsexTrade+= $trade_data->trade;
                        $dsexTradeValues+= $trade_data->tradevalues;

                    }

                }

                $instrument_id = 10001;
                $eod = DataBanksEod::updateOrCreate(
                    ['market_id' => $market_id, 'instrument_id' => $instrument_id],
                    [
                        'open' => $index_ohlc[$instrument_id]['open'],
                        'high' => $index_ohlc[$instrument_id]['high'],
                        'low' => $index_ohlc[$instrument_id]['low'],
                        'close' => $index_ohlc[$instrument_id]['close'],
                        'volume' => $dsexVolume,
                        'trade' => $dsexTrade,
                        'tradevalues' => $dsexTradeValues,
                        'updated' => date('Y-m-d H:i:s'),
                        'date' => $trade_date
                    ]
                );

                if(isset($last_intraday_data[$instrument_id]))
                    $new_volume= $dsexVolume-$last_intraday_data[$instrument_id]->total_volume;
                else
                    $new_volume = $dsexVolume - 0;

                /////////////////// Intraday Data DSEX \\\\\\\\\\\\\\\\\\\\
                if(!isset($today_intraday_index_data[$instrument_id]) and $data_bank_intraday_batch>0)
                {

                    $temp = array();
                    $temp['market_id'] = $market_id;
                    $temp['instrument_id'] = $instrument_id;
                    $temp['open_price'] = $index_ohlc[$instrument_id]['open'];
                    $temp['pub_last_traded_price'] = $index_ohlc[$instrument_id]['close'];
                    $temp['high_price'] = $index_ohlc[$instrument_id]['high'];
                    $temp['low_price'] = $index_ohlc[$instrument_id]['low'];
                    $temp['close_price'] = $index_ohlc[$instrument_id]['close'];
                    $temp['total_trades'] = $dsexTrade;
                    $temp['total_volume'] = $dsexVolume;
                    $temp['new_volume'] = $new_volume;
                    $temp['total_value'] = $dsexTradeValues;
                    $temp['lm_date_time'] = date('Y-m-d H:i:s', strtotime($index_ohlc[$instrument_id]['date_time']));
                    $temp['trade_time'] = date('H:i', strtotime($index_ohlc[$instrument_id]['date_time']));
                    $temp['trade_date'] = date('Y-m-d', strtotime($index_ohlc[$instrument_id]['date_time']));
                    $temp['yday_close_price'] = $index_ohlc[$instrument_id]['yday_close_price'];
                    $temp['batch'] = $data_bank_intraday_batch;
                    $IntradayDataToSave[] = $temp;
                }


                ////////////////////// EOD for 00DSEX \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

              /*  $value_00DSEX = 0;
                $trade_00DSEX = 0;
                $volume_00DSEX= 0;



                foreach($today_eod_data as $eod_data)
                {
                    if($eod_data->instrument_id==10001 || $eod_data->instrument_id == 10002 || $eod_data->instrument_id == 10003 || $eod_data->instrument_id == 10004 || $eod_data->instrument_id == 10005 || $eod_data->instrument_id == 10006)
                        continue;
                    $value_00DSEX+= $eod_data->tradevalues;
                    $trade_00DSEX+= $eod_data->trade;
                    $volume_00DSEX+= $eod_data->volume;
                }
*/


                $instrument_id = 10001;
                $eod = DataBanksEod::updateOrCreate(
                    ['market_id' => $market_id, 'instrument_id' => 10006],
                    [
                        'open' => $index_ohlc[$instrument_id]['open'],
                        'high' => $index_ohlc[$instrument_id]['high'],
                        'low' => $index_ohlc[$instrument_id]['low'],
                        'close' => $index_ohlc[$instrument_id]['close'],
                        'volume' => $TRD_TOTAL_VALUE,                      //  here we arte using value as volume
                        'trade' => $TRD_TOTAL_TRADES,
                        'tradevalues' => $TRD_TOTAL_VOLUME,
                        'updated' => date('Y-m-d H:i:s'),
                        'date' => $trade_date
                    ]
                );


                if (isset($last_intraday_data[10006]))
                    $new_volume = $TRD_TOTAL_VALUE - $last_intraday_data[10006]->total_volume;
                else
                    $new_volume = $TRD_TOTAL_VALUE - 0;




                /////////////////// Intraday Data 00DSEX \\\\\\\\\\\\\\\\\\\\
                if (!isset($today_intraday_index_data[$instrument_id]) and $data_bank_intraday_batch > 0) {

                    $temp = array();
                    $temp['market_id'] = $market_id;
                    $temp['instrument_id'] = 10006;
                    $temp['open_price'] = $index_ohlc[$instrument_id]['open'];
                    $temp['pub_last_traded_price'] = $index_ohlc[$instrument_id]['close'];
                    $temp['high_price'] = $index_ohlc[$instrument_id]['high'];
                    $temp['low_price'] = $index_ohlc[$instrument_id]['low'];
                    $temp['close_price'] = $index_ohlc[$instrument_id]['close'];
                    $temp['total_trades'] = $TRD_TOTAL_TRADES;
                    $temp['total_volume'] = $TRD_TOTAL_VALUE;
                    $temp['new_volume'] = $new_volume;
                    $temp['total_value'] = $TRD_TOTAL_VOLUME;
                    $temp['lm_date_time'] = date('Y-m-d H:i:s', strtotime($index_ohlc[$instrument_id]['date_time']));
                    $temp['trade_time'] = date('H:i', strtotime($index_ohlc[$instrument_id]['date_time']));
                    $temp['trade_date'] = date('Y-m-d', strtotime($index_ohlc[$instrument_id]['date_time']));
                    $temp['yday_close_price'] = $index_ohlc[$instrument_id]['yday_close_price'];
                    $temp['batch'] = $data_bank_intraday_batch;
                    if($new_volume>0)
                    $IntradayDataToSave[] = $temp;
                }


                //////////////////////// EOD for DS30 \\\\\\\\\\\\\\\\\\\\\\\\\\\

                $ds30Volume = 0;
                $ds30Trade = 0;
                $ds30TradeValues = 0;
                foreach ($instrument_id_of_all_ds30_listed_company as $ins_id) {


                    $trade_data= $today_eod_data->where('instrument_id', $ins_id)->first();
                    if(!is_null($trade_data))
                    {
                        $ds30Volume+= $trade_data->volume;
                        $ds30Trade+= $trade_data->trade;
                        $ds30TradeValues+= $trade_data->tradevalues;

                    }

                }


                $instrument_id=10002;
                $eod = DataBanksEod::updateOrCreate(
                    ['market_id' => $market_id, 'instrument_id' => $instrument_id],
                    [
                        'open' => $index_ohlc[$instrument_id]['open'],
                        'high' => $index_ohlc[$instrument_id]['high'],
                        'low' => $index_ohlc[$instrument_id]['low'],
                        'close' => $index_ohlc[$instrument_id]['close'],
                        'volume' => $ds30Volume,
                        'trade' => $ds30Trade,
                        'tradevalues' => $ds30TradeValues,
                        'updated' => date('Y-m-d H:i:s'),
                        'date' => $trade_date
                    ]
                );

                if (isset($last_intraday_data[$instrument_id]))
                    $new_volume = $ds30Volume - $last_intraday_data[$instrument_id]->total_volume;
                else
                    $new_volume = $ds30Volume - 0;

                ///////////////////////// Intraday Data DS30 \\\\\\\\\\\\\\\\\\\\\\\\\\


                if (!isset($today_intraday_index_data[$instrument_id]) and $data_bank_intraday_batch > 0)
                {
                    $temp = array();
                    $temp['market_id'] = $market_id;
                    $temp['instrument_id'] = $instrument_id;
                    $temp['open_price'] = $index_ohlc[$instrument_id]['open'];
                    $temp['pub_last_traded_price'] = $index_ohlc[$instrument_id]['close'];
                    $temp['high_price'] = $index_ohlc[$instrument_id]['high'];
                    $temp['low_price'] = $index_ohlc[$instrument_id]['low'];
                    $temp['close_price'] = $index_ohlc[$instrument_id]['close'];
                    $temp['total_trades'] = $ds30Trade;
                    $temp['total_volume'] = $ds30Volume;
                    $temp['new_volume'] = $new_volume;
                    $temp['total_value'] = $ds30TradeValues;
                    $temp['lm_date_time'] = date('Y-m-d H:i:s', strtotime($index_ohlc[$instrument_id]['date_time']));
                    $temp['trade_time'] = date('H:i', strtotime($index_ohlc[$instrument_id]['date_time']));
                    $temp['trade_date'] = date('Y-m-d', strtotime($index_ohlc[$instrument_id]['date_time']));
                    $temp['yday_close_price'] = $index_ohlc[$instrument_id]['yday_close_price'];
                    $temp['batch'] = $data_bank_intraday_batch;
                    $IntradayDataToSave[] = $temp;


                }

                //////////////////////// EOD for DSES \\\\\\\\\\\\\\\\\\\\\\\\\\\

                $dsesVolume = 0;
                $dsesTrade = 0;
                $dsesTradeValues = 0;


                $instrument_id=10003;
                $eod = DataBanksEod::updateOrCreate(
                    ['market_id' => $market_id, 'instrument_id' => $instrument_id],
                    [
                        'open' => $index_ohlc[$instrument_id]['open'],
                        'high' => $index_ohlc[$instrument_id]['high'],
                        'low' => $index_ohlc[$instrument_id]['low'],
                        'close' => $index_ohlc[$instrument_id]['close'],
                        'volume' => $dsesVolume,
                        'trade' => $dsesTrade,
                        'tradevalues' => $dsesTradeValues,
                        'updated' => date('Y-m-d H:i:s'),
                        'date' => $trade_date
                    ]
                );


                ///////////////////////// Intraday Data DSES \\\\\\\\\\\\\\\\\\\\\\\\\\


                if (!isset($today_intraday_index_data[$instrument_id]) and $data_bank_intraday_batch > 0)
                {
                    $temp = array();
                    $temp['market_id'] = $market_id;
                    $temp['instrument_id'] = $instrument_id;
                    $temp['open_price'] = $index_ohlc[$instrument_id]['open'];
                    $temp['pub_last_traded_price'] = $index_ohlc[$instrument_id]['close'];
                    $temp['high_price'] = $index_ohlc[$instrument_id]['high'];
                    $temp['low_price'] = $index_ohlc[$instrument_id]['low'];
                    $temp['close_price'] = $index_ohlc[$instrument_id]['close'];
                    $temp['total_trades'] = $ds30Trade;
                    $temp['total_volume'] = $ds30Volume;
                    $temp['new_volume'] = 0;
                    $temp['total_value'] = $ds30TradeValues;
                    $temp['lm_date_time'] = date('Y-m-d H:i:s', strtotime($index_ohlc[$instrument_id]['date_time']));
                    $temp['trade_time'] = date('H:i', strtotime($index_ohlc[$instrument_id]['date_time']));
                    $temp['trade_date'] = date('Y-m-d', strtotime($index_ohlc[$instrument_id]['date_time']));
                    $temp['yday_close_price'] = $index_ohlc[$instrument_id]['yday_close_price'];
                    $temp['batch'] = $data_bank_intraday_batch;

                    $IntradayDataToSave[] = $temp;

                }



                if (!empty($IntradayDataToSave)) {

                    DB::table('data_banks_intradays')->insert($IntradayDataToSave);
                    $this->info(count($IntradayDataToSave) . ' index data inserted into data_banks_intradays');


                    /*set last updated batch_id in instruments table start*/
                    $index_id_arr[]=10006;

                    DB::table('instruments')->whereIn('id', $index_id_arr)->update(['batch_id' => $data_bank_intraday_batch]);
                    /*set last updated batch_id in instruments table end*/


                }

            }
            else
            {
                // Its not returning today data. We will just send a message in console
                $this->info('Dse returning previous data');
            }


        }

    }
}
