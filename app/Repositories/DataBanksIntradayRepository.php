<?php

/**
 * Created by PhpStorm.
 * User: sohail
 * Date: 4/17/2017
 * Time: 12:09 PM
 */

namespace App\Repositories;

Use App\DataBanksIntraday;
Use App\Market;
use Carbon\Carbon;

class DataBanksIntradayRepository {


    public static function getLatestTradeDataAll($tradeDate = null, $exchangeId = 0) {
        return DataBanksIntraday::getLatestTradeDataAll($tradeDate, $exchangeId);
    }

    public static function getPreviousDayData($instrumentsIdArr=array(),$tradeDate = null, $minute = 1, $exchangeId = 0)  {
        return DataBanksIntraday::getPreviousDayData($instrumentsIdArr,$tradeDate,$minute,$exchangeId, $exchangeId);
    }

    public static function upDownStats()
    {

        $allTradeData=DataBanksIntraday::getLatestTradeDataAll();


        $up = $allTradeData->filter(function ($value, $key) {
            return $value->price_change > 0;
        });

        $down = $allTradeData->filter(function ($value, $key) {
            return $value->price_change < 0;
        });

        $eq = $allTradeData->filter(function ($value, $key) {
            if($value->price_change == 0)
                return true;
            else
               return false;

        });

        $returnData = array();
        $returnData['up'] = $up;
        $returnData['down'] = $down;
        $returnData['eq'] = $eq;

        $prevDayData = DataBanksIntraday::getPreviousDayData();

        $up = $prevDayData->filter(function ($value, $key) {
            return $value->price_change > 0;
        });

        $down = $prevDayData->filter(function ($value, $key) {
            return $value->price_change < 0;
        });

        $eq = $prevDayData->filter(function ($value, $key) {
            if($value->price_change == 0)
                return true;
            else
                return false;
        });



        $returnData['up_prev'] = $up;
        $returnData['down_prev'] = $down;
        $returnData['eq_prev'] = $eq;

        return $returnData;
    }



    public static function significantValueLastMinute($field='price_change',$limit=10,$tradeDate = null, $exchangeId = 0)
    {
        $lastMinuteData=DataBanksIntraday::getLatestTradeDataAll($tradeDate,$exchangeId);
        $lastMinuteData=$lastMinuteData->keyBy('instrument_id');
        $prevMinuteData=DataBanksIntraday::getMinuteAgoTradeDataAll($tradeDate,1,$exchangeId);
        $prevMinuteData = $prevMinuteData->keyBy('instrument_id');

        $lastMinuteData=self::growthCalculate($lastMinuteData,$prevMinuteData,$field,$limit);
        return $lastMinuteData;
    }


    // growth per %
    public static function significantValueLastMinutePer($field='price_change',$limit=10,$tradeDate = null, $exchangeId = 0)
    {
        $lastMinuteData=DataBanksIntraday::getLatestTradeDataAll($tradeDate,$exchangeId);
        $lastMinuteData=$lastMinuteData->keyBy('instrument_id');
        $prevMinuteData=DataBanksIntraday::getMinuteAgoTradeDataAll($tradeDate,1,$exchangeId);
        $prevMinuteData = $prevMinuteData->keyBy('instrument_id');


        $lastMinuteData=self::growthCalculatePer($lastMinuteData,$prevMinuteData,$field,$limit);
        return $lastMinuteData;
    }


    public static function significantValue2Days($field='price_change',$limit=10,$tradeDate = null, $exchangeId = 0)
    {
        $lastMinuteData=DataBanksIntraday::getLatestTradeDataAll($tradeDate,$exchangeId);
        $lastMinuteData=$lastMinuteData->keyBy('instrument_id');
        $prevDayData=DataBanksIntraday::getPreviousDayData(array(),$tradeDate,1,$exchangeId);
        $prevDayData = $prevDayData->keyBy('instrument_id');

        $lastMinuteData=self::growthCalculate($lastMinuteData,$prevDayData,$field,$limit);
        return $lastMinuteData;
    }

    // this should be use to get last minute trade data for a share
    public static function getMinuteData($instrumentsIdArr=array(),$minute=15,$field='total_volume',$tradeDate=null,$exchangeId=0)
    {

        $minuteData=DataBanksIntraday::getWholeDayData($instrumentsIdArr,$minute,$tradeDate,$exchangeId);
        $minuteData=$minuteData->groupBy('instrument_id');

        $returnData=array();
        foreach($minuteData as $instrument_id=>$dataObj) {
            $returnData[$instrument_id]=self::calculateDifference($dataObj,$field);
        }

        return collect($returnData);
    }
    //$tradeDate needed for make cache variable only
    public static function getMinuteDataByMarketId($marketId=array(),$instrumentId=array(),$field=null,$tradeDate=null)
    {
        $minuteData=DataBanksIntraday::getIntraDayDataByMarketId($marketId,$instrumentId,$tradeDate);

        $returnData=array();
        if(!is_null($field))
        {
            foreach($minuteData as $market_id=>$dataObj) {
                $returnData[$market_id]=self::calculateDifference($dataObj,$field);
            }

            return($returnData);
        }
        return $minuteData;
    }

    public static function getYdayMinuteData($instrumentsIdArr=array(),$minute=15,$field='total_volume',$exchangeId=0)
    {
        $minuteData=DataBanksIntraday::getPreviousDayData($instrumentsIdArr,null,$minute,$exchangeId);
        $minuteData=$minuteData->groupBy('instrument_id');


        $returnData = array();

        foreach ($minuteData as $instrument_id => $dataObj) {
            $returnData[$instrument_id] = self::calculateDifference($dataObj, $field);
        }

        return collect($returnData);
    }

    /*
     * This is to calculate the difference between 2 object data
     *
     * */

    public static function growthCalculate($lastMinuteData, $prevMinuteData, $field = 'price_change', $limit = 10) {
        // writing the new property name to add in object.
        $new_property = $field . "_growth";
        $collection = $lastMinuteData->each(function ($item, $key) use($prevMinuteData, $field, $new_property) {

            // checking if it has traded previous minute
            if (isset($prevMinuteData[$key])) {
                $change = $item->$field - $prevMinuteData[$key]->$field;
                $item->$new_property = (float) number_format($change, 2, '.', '');
            }
        });
        $collection = $collection->sortByDesc($new_property)->take($limit);
        return $collection;
    }
    public static function growthCalculatePer($lastMinuteData,$prevMinuteData,$field='price_change',$limit=10)
    {
        // writing the new property name to add in object.
        $new_property=$field."_growth_per";
        $collection = $lastMinuteData->each(function ($item, $key) use($prevMinuteData,$field,$new_property) {

            // checking if it has traded previous minute
            if(isset($prevMinuteData[$key])) {
                $change=($item->$field-$prevMinuteData[$key]->$field)/$prevMinuteData[$key]->$field*100;
                $item->$new_property=(float) number_format($change, 2, '.', '');
            }

        });
        $collection = $collection->sortByDesc($new_property)->take($limit);
        return $collection;
    }

    /*
     * $dayBefore mainly used to get market monitor yesterday data
     * */
    public static function getDataForMinuteChart($inst_id,$days=1,$dayBefore=0)
    {

        $instrumentsIdArr=array();
        $instrumentsIdArr[]=$inst_id;

        $totalDay=$days+$dayBefore;
        $activeDate = Market::getActiveDates($totalDay);

        $marketIdArr=$activeDate->pluck('id');
        $marketId=array();

        if($dayBefore)
        {
            $marketId[]=$marketIdArr[$dayBefore];
        }else
        {
            $marketId=$marketIdArr;
        }

        $multipleDaydata=DataBanksIntradayRepository::getMinuteDataByMarketId($marketId,$instrumentsIdArr,'total_volume');
        $bullBear=self::lastNdaysBullBear($multipleDaydata);

        $intradayData=$multipleDaydata[$marketId[0]];

        $close_price=$intradayData->pluck('close_price')->toArray();
        $dateTime=$intradayData->pluck('lm_date_time')->toArray();
        $total_volume_diff=$intradayData->pluck('total_volume_difference')->toArray();

        $total_volume_data=array();
        $close_data=array();
        $date_data=array();

        $no_of_bar=count($close_price)-2;

        for($i=$no_of_bar;$i>=0;--$i)
        {
            if(!isset($close_price[$i]))
                continue;

            $temp=array();


            if($close_price[$i+1]>$close_price[$i]) // if price fall
            {
                $temp['color']='#EF4836';
            }
            if($close_price[$i+1]<$close_price[$i]) // if price increases
            {
                $temp['color']='#1BA39C';
            }
            if($close_price[$i+1]==$close_price[$i]) // if price equal
            {
                $temp['color']='#ACB5C3';
            }

            $temp['y']=$total_volume_diff[$i];
            $total_volume_data[]=$temp;

            $temp['y']=$close_price[$i]+0;
            $close_data[]=$temp;

            $date_data[]=$dateTime[$i]->format('h:i');

        }
        $yday_close_price=$intradayData->first()->yday_close_price;
        $cp=$intradayData->first()->close_price;
        $day_total_volume=$intradayData->first()->total_volume;
        $trade_date=$intradayData->first()->trade_date;
        $lm_date_time=$intradayData->first()->lm_date_time->format('jS M,D h:i');


        $returnData=array();
        $returnData['date_data']=$date_data;
        $returnData['trade_date']=$trade_date;
        $returnData['lm_date_time']=$lm_date_time;
        $returnData['volume_data']=$total_volume_data;
        $returnData['close_data']=$close_data;
        $returnData['yday_close_price']=$yday_close_price;
        $returnData['cp']=$cp;
        $returnData['day_total_volume']=$day_total_volume;
        $returnData['bullBear'] = $bullBear;

        return $returnData;

    }

    public static function lastNdaysBullBear($data=array())
    {
        $return=array();

        foreach($data as $market_id=>$dataCollection)
        {

            $bullVolume=0;
            $bearVolume=0;
            $neutralVolume=0;

            $reverse_close_price= $dataCollection->reverse()->pluck('close_price')->toArray();  // 10.30 am fast
            $yday_close_price=$dataCollection->first()->yday_close_price;
            $trade_date=$dataCollection->first()->trade_date->format('Y-m-d');

            array_unshift($reverse_close_price, $yday_close_price); // adding yclose to compare starting volume at 10.30 am

            $reverse_total_volume_diff=$dataCollection->reverse()->pluck('total_volume_difference')->toArray(); // 10:30 minute data first ($close_price[0)

            for($i=0;$i<count($reverse_close_price)-1;$i++)
            {

                if(!isset($reverse_close_price[$i]))
                    continue;

                if(isset($reverse_close_price[$i+1]))
                    $temp=$reverse_close_price[$i+1];
                else
                    $temp=$yday_close_price;

                if($temp<$reverse_close_price[$i]) // if price fall
                {
                    $bearVolume=$bearVolume+$reverse_total_volume_diff[$i];
                }
                if($temp>$reverse_close_price[$i]) // if price increases
                {
                    $bullVolume=$bullVolume+$reverse_total_volume_diff[$i];
                }
                if($temp==$reverse_close_price[$i]) // if price equal
                {
                    $neutralVolume=$neutralVolume+$reverse_total_volume_diff[$i];
                }


            }
            $temp=array();
           /* $temp['totalBull']=number_format($bullVolume, 0, '.', '');
            $temp['totalBear']=number_format($bearVolume, 0, '.', '');
            $temp['totalNeutral']=number_format($neutralVolume, 0, '.', '');*/

            $temp['totalBull']=$bullVolume;
            $temp['totalBear']=$bearVolume;
            $temp['totalNeutral']=$neutralVolume;
            $temp['trade_date']=$trade_date;
            $return[]=$temp;

        }

        return $return;



    }

    /*
     * This is to calculate the difference between 2 consecutive row of same object data
     *
     * If we dont take whole day data. 1st difference (last value of the obj) will be incorrect. So we have to discard this
     * For example. If we pass 35 minutes data from 1.55 PM. It will assume 1.54 data 0 (which is not true). As a result
     * It will return (all data i.e: volume -0 ). In this case we can discard 1st va;ue and start using from next
     * */

    public static function calculateDifference($data, $field = 'total_volume') {
        // writing the new property name to add in object.
        $new_property = $field . "_difference";

        // copy total separate obj

        $data1=clone $data;
        //removing 1st element from the obj
        $data1->shift();

        $data2=$data;


        $collection = $data2->each(function ($item, $key) use($data1, $field, $new_property) {

            // checking if key exist in shifted data ($data1). It will miss last element normally

            if(isset($data1[$key])) {
                $change=$item->$field-$data1[$key]->$field;
                $item->$new_property=(float) number_format($change, 2, '.', '');
            }else
            // very 1st data (10.30) has no previous data. so we are subtracting 0
                $change=$item->$field-0;
                $item->$new_property=(float) number_format($change, 2, '.', '');


        });

        return $collection;
    }

    public static function getDataForTradingView($instrumentId,$from,$to,$resolution)
    {
        $from=Carbon::createFromTimestamp($from);
        $to=Carbon::createFromTimestamp($to);

        $rawdata = DataBanksIntraday::getIntraDayDataByRange($instrumentId, $from->format('Y-m-d'), $to->format('Y-m-d'));
        $rawdata = $rawdata->reverse();
        $rawdata=$rawdata->keyBy('batch');
        $rawdata=$rawdata->groupBy('market_id');

       // dd($rawdata);

        //$data=$data->chunk(5);


        $returnData=array();
        foreach($rawdata as $market_id=>$wholeDayData)
        {
            foreach ($wholeDayData->chunk($resolution) as $chunk)
            {
                $start=$chunk->first()->toArray();
                $end=$chunk->last()->toArray();

                /*  $candle=array();
                  $candle['t']=$end['date_timestamp'];
                  $candle['o']=$start['pub_last_traded_price'];
                  $candle['h']=$chunk->max('pub_last_traded_price');
                  $candle['l']=$chunk->min('pub_last_traded_price');
                  $candle['c']=$end['pub_last_traded_price'];
                  $candle['v']=$end['total_volume']-$start['total_volume'];
                  $data[]=$candle;*/

                $returnData['t'][] = $end['date_timestamp'];;
                $returnData['c'][] = $end['pub_last_traded_price'];;
                $returnData['o'][] = $start['pub_last_traded_price'];;
                $returnData['h'][] = $chunk->max('pub_last_traded_price');;
                $returnData['l'][] = $chunk->min('pub_last_traded_price');;
                $returnData['v'][] = $end['total_volume']-$start['total_volume'];


            }
        }


        if(count($returnData)) {
            $returnData['s'] = "ok";
        }else
        {
           // $returnData['s'] = "no_data";
          //  $returnData['nextTime'] = strtotime('1999-01-01');
        }

        return collect($returnData)->toJson();

    }

}
