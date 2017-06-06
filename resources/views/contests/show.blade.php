@extends('layouts.metronic.default')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="portlet light bordered">
            <div class="portlet-title">
                <div class="caption font-green-haze">
                    <i class="icon-badge font-green-haze"></i>
                    <span class="caption-subject bold uppercase"> 
                        <span class="text-primary">{{ $contest->name }}</span> Details
                    </span>
                </div>
            </div>

            <div class="portlet-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th colspan="6">
                                <span class="caption-subject bold uppercase"> 
                                    Top ({{ $contest->contestUsers->count() }}) Performers from the start of 
                                    <span class="text-primary">{{ $contest->name }}</span>
                                </span>
                            </th>
                        </tr>

                        <tr class="text-center">
                            <th class="text-center">Rank</th>
                            <th class="text-center">Name</th>
                            <th class="text-center">Join Date</th>
                            <th class="text-center">Growth %</th>
                            <th class="text-center">Shares Holding</th>
                            <th class="text-center">Portfolio</th>
                        </tr>
                    </thead>

                    <tbody>
                        @php
                            $rank = 1;
                        @endphp
                        
                        @forelse ($contest->contestUsers as $user)
                            @php
                                $growthPercent = 0;
                                $contestAmount = $contest->contest_amount;

                                $totalPortfolioValue = $user->pivot->current_portfolio_value;
                                $growth = $totalPortfolioValue - $contestAmount;
                                $growthPercent = number_format($growth / $contestAmount * 100, 2);
                            @endphp
                            
                            <tr class="text-center">
                                <td>{{ $rank++ }}</td>
                                <td>
                                    @if (auth()->check() AND $user->name === auth()->user()->name)
                                        <a href="{{ route('contests.portfolios.show', $user->pivot->id) }}">{{ $user->name }}</a>
                                    @else
                                        {{ $user->name }}
                                    @endif
                                </td>
                                <td>{{ $user->join_date }}</td>
                                <td>
                                    @if ($growthPercent > 0)
                                        <span class="text-success">{{ $growthPercent }}</span>
                                    @else
                                        <span class="text-danger">{{ $growthPercent }}</span>
                                    @endif
                                </td>
                                <td>{{ $user->shares->count() }}</td>
                                <td>{{ $user->pivot->current_portfolio_value }}</td>
                            </tr>
                        @empty
                            <tr class="no-records-found text-center">
                                <td colspan="6">No matching records found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
