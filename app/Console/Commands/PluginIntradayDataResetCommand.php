<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use DB;
use App\Repositories\InstrumentRepository;
use App\Market;
Use App\DataBanksIntraday;

class PluginIntradayDataResetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plugin:resetIntra';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset Intraday data file';

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

    public function writeData($data, $instrumentList, $file)
    {
        $strToadd='';
        foreach ($data as $row) {
            $instrumentInfo = $instrumentList->where('id', $row->instrument_id);

            if (count($instrumentInfo)) {
                $instrument_code = $instrumentInfo->first()->instrument_code;
                $time_formated = $row['lm_date_time']->format('H:i');
                $date_formated = $row['lm_date_time']->format('d/m/Y');

                $strToadd .= $instrument_code . ',' . $time_formated . ',' . $date_formated . ',' . $row->open_price . ',' . $row->high_price . ',' . $row->low_price . ',' . $row->close_price . ',' . $row->total_volume."\n";

            }

        }

        Storage::append($file, $strToadd);

        $zipper = new \Chumper\Zipper\Zipper;
        $files = glob(storage_path() .'/app/plugin/intra/*');
        $zipper->make(storage_path() .'/app/plugin/intra.zip')->add($files)->close();

    }


// live server command   /opt/cpanel/ea-php70/root/usr/bin/php /home/hostingmonitors/artisan plugin:resetIntra
    public function handle()
    {
        $file="plugin/intra/data.txt";
        //$heading = 'Code,Time,Date,Open,High,Low,Close,Volume';
        $heading = '';
        Storage::disk('local')->put($file, $heading);

        $tradeDate=Market::getActiveDates();
        $last_trade_date= $tradeDate->first()->trade_date->format('Y-m-d');

        $instrumentList=InstrumentRepository::getInstrumentsScripWithIndex();

        //$result = DB::select('SELECT count(id) as total FROM data_banks_eods');
        //$total_row= $result[0]->total;
        $total_row=500000;
        $limit=10000;
        for($i=0;$i<$total_row;$i=$i+ $limit)
        {
            dump(".... processing start=$i, limit=$limit");
            $data= DataBanksIntraday::whereDate('lm_date_time','<',$last_trade_date)->groupBy('lm_date_time')->orderBy('lm_date_time', 'desc')->skip($i)->take($limit)->get();
            self::writeData($data, $instrumentList, $file);
        }




        $this->info('ok');
    }
}