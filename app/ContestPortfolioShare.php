<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ContestPortfolioShare extends Model
{
    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'transaction_time'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'amount', 
        'rate', 
        'transaction_time',
        'commission',
    ];

    /**
     * A share may have a intrument.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function intrument() 
    {
        return $this->belongsTo(Instrument::class, 'instrument_id');
    }

    /**
     * A share may have a transaction type.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function transactionType() 
    {
        return $this->belongsTo(TransactionType::class, 'id');
    }

    /**
     * A share may have a portfolio.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function portfolio() 
    {
        return $this->belongsTo(Portfolio::class, 'id');
    }
}