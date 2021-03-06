@php
$index = \App\Market::indexValue();
$up = \App\Market::upCount();
$down = \App\Market::downCount();
$trade = \App\Market::totalTrade();
$value = \App\Market::totalValue();
@endphp
sdf
@if(request()->has('dev'))
<div class="fixedStat" style="text-align: center;">
	<ul style="text-align: center; display: inline-block;">
		<li style="color: @if($index->percentage_deviation < 0)red @else #009d7c @endif; min-width: 130px">
			<div style="line-height: 25px"><strong>DSEX<i class="fa fa-caret-down"></i>{{number_format($index->percentage_deviation, 2)}}%</strong></div>
			<div style="line-height: 25px"><strong>{{number_format($index->capital_value, 0, '.', ',')}}</strong></div>
		</li>
		<li >
			<div style="line-height: 25px; color: #009dc7; text-align: left;"><strong><i class="fa fa-caret-up"></i>{{$up}}</strong></div>
			<div style="line-height: 25px;  color: red; text-align: left;"><strong><i class="fa fa-caret-down"></i>{{$down}}</strong></div>
		</li>
		<li style="color:red">
			<div style="line-height: 25px"><strong>Trade</strong></div>
			<div style="line-height: 25px"><strong>{{number_format($trade, 0, '.', ',')}}</strong></div>
		</li>
		<li style="color: red; min-width: 90px">
			<div style="line-height: 25px"><strong>Value</strong><small> (mn)</small></div>
			<div style="line-height: 25px"><strong>{{number_format($value, 2, '.', ',')}}</strong></div>
		</li>
	</ul>
</div>
@endif